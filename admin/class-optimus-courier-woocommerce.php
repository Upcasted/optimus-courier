<?php

use setasign\Fpdi\Fpdi;
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

class Optimus_Courier_WooCommerce {
    private $api;
    public $is_hpos_enabled;
    private $tracking_url;

    public function __construct() {
        $this->api = new Optimus_Courier_API();
        $this->is_hpos_enabled = $this->is_hpos_enabled();
        
        $settings = get_option('optimus_courier_settings', []);
        $this->tracking_url = !empty($settings['optimus_courier_tracking_page_url']) 
            ? $settings['optimus_courier_tracking_page_url'] 
            : OPTIMUS_COURIER_DEFAULT_TRACKING_URL;

        // Add hook for automatic AWB generation
        add_action('woocommerce_order_status_changed', array($this, 'handle_automatic_awb_generation'), 10, 4);    
    }

    /**
     * Safe debug logging helper
     *
     * @param string $message Message to log
     * @return void
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            if (function_exists('wp_debug_log')) {
                wp_debug_log($message);
            } else {
                error_log($message);
            }
        }
    }

    public function is_hpos_enabled() {
        if (class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    public function register_order_meta() {
        if (!$this->is_hpos_enabled) {
            register_post_meta('shop_order', '_optimus_awb_number', array(
                'show_in_admin' => true,
                'type' => 'string',
                'single' => true,
                'sanitize_callback' => 'sanitize_text_field',
                'description' => esc_html__('Numar AWB Optimus Courier', 'optimus-courier'),
            ));
        }
    }

    public function display_awb_in_order_meta($order) {
        $awb = $order->get_meta('_optimus_awb_number');
        if (!empty($awb)) {
            $awb_numbers = array_map('trim', explode(',', $awb));
            echo '<p><strong>' . esc_html__('AWB:', 'optimus-courier') . '</strong><br>';
            foreach ($awb_numbers as $awb_number) {
                echo '<a href="' . esc_url($this->tracking_url . '?awb=' . esc_attr($awb_number)) . '" target="_blank">' . esc_html($awb_number) . '</a><br>';
            }
            echo '</p>';
        }
    }

    public function add_awb_column($columns) {
        // Add a custom column for AWB numbers
        $columns['awb_number'] = esc_html__('AWB Optimus Courier', 'optimus-courier');
        return $columns;
    }

    public function render_awb_column($column, $post_or_order) {
        if ('awb_number' === $column) {
            $order = null;
            // Handle both HPOS and traditional storage
            if (is_object($post_or_order) && method_exists($post_or_order, 'get_id')) {
                $order = $post_or_order;
            } else {
                $order_id = absint($post_or_order);
                $order = wc_get_order($order_id);
            }

            if (!$order) {
                echo 'Order not found';
                return;
            }

            $awb = $order->get_meta('_optimus_awb_number');

            // Display the AWB number or a generate button
            if (!empty($awb)) {
                $awb_numbers = array_map('trim', explode(',', $awb));
                echo '<div class="awb-numbers">';
                foreach ($awb_numbers as $awb_number) {
                    $download_url = admin_url('admin-ajax.php?action=optimus_download_awb&awb=' . urlencode($awb_number) . '&nonce=' . wp_create_nonce('optimus_courier_nonce'));
                    echo '<a href="' . esc_url($this->tracking_url . '?awb=' . esc_attr($awb_number)) . '" target="_blank">' . esc_html($awb_number) . '</a><br>';
                }
                
                echo '<div style="display: flex; align-items: center; gap: 5px;">';
                echo '<a href="' . esc_url($download_url) . '" class="button button-small" target="_blank">' . 
                         esc_html__('Descarcă AWB', 'optimus-courier') . '</a>';
                // Only show refresh icon if order is not completed
                if ($order->get_status() !== 'completed') {
                    echo '<button class="regenerate-awb" data-order-id="' . esc_attr($order->get_id()) . '" title="'. esc_attr__('Regenerează AWB', 'optimus-courier') .'">
                            <span class="dashicons dashicons-update"></span></button>';
                }
                echo '</div>';
                echo '</div>';
            } else {
                echo '<div class="awb-numbers"></div>';
                printf(
                    '<button class="button generate-awb" data-order-id="%d">%s</button>',
                    esc_attr($order->get_id()),
                    esc_html__('Genereaza AWB', 'optimus-courier')
                );
            }
        }
    }

    /**
     * Complete order if auto-complete setting is enabled
     *
     * @param WC_Order $order
     * @return void
     */
    private function maybe_complete_order($order) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        $settings = get_option('optimus_courier_settings', []);
        if (isset($settings['optimus_courier_complete_order_auto']) && 
            $settings['optimus_courier_complete_order_auto'] === 'on' && 
            $order->get_status() !== 'completed') {
            
            $order->update_status(
                'completed', 
                esc_html__('Comanda a fost marcată automat ca finalizată după generarea AWB-ului.', 'optimus-courier')
            );
        }
    }

    public function generate_awb_ajax() {
        // For bulk actions, we skip the nonce check as we created it internally
        if (!isset($_POST['bulk_action'])) {
            check_ajax_referer('optimus_courier_nonce', 'nonce');
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error('ID comandă invalid');
        }

        // Check if there's an ongoing operation for this order
        $lock_key = 'optimus_awb_lock_' . $order_id;
        if (get_transient($lock_key)) {
            if (!isset($_POST['bulk_action'])) {
                wp_send_json_error('O operație pentru această comandă este deja în curs');
            }
            return array('success' => false, 'message' => 'O operație pentru această comandă este deja în curs');
        }

        // Set a 30-second lock
        set_transient($lock_key, true, 30);

        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error('Comanda nu a fost găsită');
            }

            // Add this check at the beginning of AWB generation
            $existing_awb = $order->get_meta('_optimus_awb_number');
            if (!empty($existing_awb)) {
                if (!isset($_POST['bulk_action'])) {
                    wp_send_json_error('AWB deja generat pentru această comandă');
                }
                return array('success' => true, 'awb_number' => $existing_awb, 'skipped' => true);
            }

            // Calculate total weight from all products
            $total_weight = 0;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && $product->has_weight()) {
                    $total_weight += floatval($product->get_weight()) * $item->get_quantity();
                }
            }
            // Use 1kg as minimum weight if no weight is set
            $total_weight = max(1.00, $total_weight);

            // Prepare AWB data from order
            $awb_data = array(
                'destinatar_nume' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'destinatar_contact' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'destinatar_adresa' => $order->get_shipping_address_1(),
                'destinatar_localitate' => $order->get_shipping_city(),
                'destinatar_judet' => $order->get_shipping_state(),
                'destinatar_cod_postal' => $order->get_shipping_postcode(),
                'destinatar_telefon' => $order->get_shipping_phone() ?: $order->get_billing_phone(),
                'destinatar_email' => $order->get_billing_email(),
                'colet_buc' => apply_filters(
                    'optimus_courier_colet_buc',
                    (($settings = get_option('optimus_courier_settings', [])) && isset($settings['optimus_courier_nr_colete'])) ? $settings['optimus_courier_nr_colete'] : 1,
                    $order
                ),
                'colet_greutate' => $total_weight > 1.00 ? $total_weight : (float) (get_option('optimus_courier_settings')['optimus_courier_greutate'] ?? 1.00),
                'data_colectare' => gmdate('Y-m-d'),
                'ref_factura' => $order->get_order_number(),
                //'ramburs_valoare' => $order->get_total()
            );

            // Create AWB using class's API instance
            $response = $this->api->create_awb($awb_data);

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->log_debug('Optimus Courier AWB generation error: ' . $error_message);
                if (!isset($_POST['bulk_action'])) {
                    wp_send_json_error(array(
                        'message' => $error_message,
                        'type' => 'wp_error'
                    ));
                }
                return array('success' => false, 'message' => $error_message, 'type' => 'wp_error');
            }

            // Check for API error
            if (isset($response['error']) && $response['error'] !== 0) {
                $error_message = isset($response['error_message']) ? $response['error_message'] : 'Unknown error';
                $error_code = isset($response['error']) ? $response['error'] : 'unknown';
                $awb_id = isset($response['id']) ? $response['id'] : null;
                
                $this->log_debug(sprintf(
                    'Optimus Courier API error: Code: %s, Message: %s, AWB ID: %s',
                    $error_code,
                    $error_message,
                    $awb_id ?? 'N/A'
                ));

                if (!isset($_POST['bulk_action'])) {
                    wp_send_json_error(array(
                        'message' => $error_message,
                        'error_code' => $error_code,
                        'awb_id' => $awb_id,
                        'type' => 'api_error'
                    ));
                }
                return array(
                    'success' => false,
                    'message' => $error_message,
                    'error_code' => $error_code,
                    'awb_id' => $awb_id,
                    'type' => 'api_error'
                );
            }

            // If we get here, remove the lock before returning success
            delete_transient($lock_key);

            // Check if we have PCL numbers
            if (!empty($response['pcl']) && is_array($response['pcl'])) {
                $awb_number = implode(', ', $response['pcl']);
                update_post_meta($order_id, '_optimus_awb_number', $awb_number);
                $order->update_meta_data('_optimus_awb_number', $awb_number);
                $order->save_meta_data();
                
                // Send email notification
                $this->send_awb_notification_email($order, $awb_number);

                // Add auto-complete check here
                $this->maybe_complete_order($order);

                if (!isset($_POST['bulk_action'])) {
                    wp_send_json_success(array('awb_number' => $awb_number));
                }
                return array('success' => true, 'awb_number' => $awb_number);
            } else {
                $error_message = 'No AWB number received from API';
                if (!isset($_POST['bulk_action'])) {
                    wp_send_json_error($error_message);
                }
                return array('success' => false, 'message' => $error_message);
            }
        } catch (Exception $e) {
            // Make sure to remove the lock if anything fails
            delete_transient($lock_key);
            
            if (!isset($_POST['bulk_action'])) {
                wp_send_json_error($e->getMessage());
            }
            return array('success' => false, 'message' => $e->getMessage());
        }

        // Remove the lock before returning error
        delete_transient($lock_key);
        
        $error_message = 'No AWB number received from API';
        if (!isset($_POST['bulk_action'])) {
            wp_send_json_error($error_message);
        }
        return array('success' => false, 'message' => $error_message);
    }

    /**
     * Handle automatic AWB generation based on order status
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function handle_automatic_awb_generation($order_id, $old_status, $new_status, $order) {
        $settings = get_option('optimus_courier_settings', []);
        
        // Get the configured status from settings and strip 'wc-' if present
        $auto_status = !empty($settings['optimus_courier_awb_auto_status']) 
            ? str_replace('wc-', '', $settings['optimus_courier_awb_auto_status'])
            : '';
        
        // Compare statuses without 'wc-' prefix
        if (empty($auto_status) || $new_status !== $auto_status) {
            return;
        }
            
        // Skip if AWB already exists
        if ($order->get_meta('_optimus_awb_number')) {
            return;
        }

        // Prepare POST data for AWB generation
        $_POST['order_id'] = $order_id;
        $_POST['bulk_action'] = true;
        $_POST['nonce'] = wp_create_nonce('optimus_courier_nonce');

        // Generate AWB
        $result = $this->generate_awb_ajax();

        // If AWB generation was successful and auto-complete is enabled
        if ($result['success'] && 
            isset($settings['optimus_courier_complete_order_auto']) && 
            $settings['optimus_courier_complete_order_auto'] === 'on') {
            
            // Only update if the order is not already completed
            if ($order->get_status() !== 'completed') {
                $order->update_status('completed', esc_html__('Order automatically completed after AWB generation.', 'optimus-courier'));
            }
        }
    }

    public function register_bulk_actions($bulk_actions) {
        $bulk_actions['generate_awb'] = esc_html__('Generează AWB', 'optimus-courier');
        $bulk_actions['download_merged_awb'] = esc_html__('Descarcă AWB-uri într-un singur fișier PDF', 'optimus-courier');
        $bulk_actions['download_awb_zip'] = esc_html__('Descarcă AWB-uri în fișiere individuale', 'optimus-courier');
        return $bulk_actions;
    }

    public function handle_bulk_action($redirect_to, $action, $ids) {
        if ($action === 'generate_awb') {
            $processed = 0;
            $failed = 0;
            $skipped = 0;
            $failed_orders = array();
            $error_messages = array();

            foreach ($ids as $order_id) {
                $order = wc_get_order($order_id);
                if (!$order) {
                    $failed++;
                    $failed_orders[] = $order_id;
                    $error_messages[$order_id] = 'Order not found';
                    continue;
                }

                if ($order->get_meta('_optimus_awb_number')) {
                    $skipped++;
                    continue;
                }

                $_POST['order_id'] = $order_id;
                $_POST['bulk_action'] = true;
                $_POST['nonce'] = wp_create_nonce('optimus_courier_nonce');
                
                try {
                    $result = $this->generate_awb_ajax();
                    if ($result['success']) {
                        if (!empty($result['skipped'])) {
                            $skipped++;
                        } else {
                            $processed++;
                            $this->send_awb_notification_email($order, $result['awb_number']);
                        }
                        // $processed++;
                        // // Send email notification for successfully generated AWBs
                        // $this->send_awb_notification_email($order, $result['awb_number']);
                    } else {
                        $failed++;
                        $failed_orders[] = $order_id;
                        $error_messages[$order_id] = $result['message'];
                    }
                } catch (Exception $e) {
                    $failed++;
                    $failed_orders[] = $order_id;
                    $error_messages[$order_id] = $e->getMessage();
                    $this->log_debug('Optimus Courier AWB generation failed for order ' . $order_id . ': ' . $e->getMessage());
                }
            }

            $redirect_to = remove_query_arg(['processed_count', 'failed_count', 'skipped_count', 'failed_orders', 'error_messages', 'bulk_action_done'], wp_get_referer());
            return add_query_arg(array(
                'processed_count' => $processed,
                'failed_count' => $failed,
                'skipped_count' => $skipped,
                'failed_orders' => implode(',', $failed_orders),
                'error_messages' => base64_encode(json_encode($error_messages)),
                'bulk_action_done' => 'generate_awb'
            ), $redirect_to);
        }

        if ($action === 'download_merged_awb') {
            $awb_numbers = array();
            //error_log(implode(',', $ids));
            foreach ($ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $awb = $order->get_meta('_optimus_awb_number');
                    if (!empty($awb)) {
                        $awb_numbers[] = trim(explode(',', $awb)[0]); // Get only the first AWB number
                    }
                }
            }

            if (!empty($awb_numbers)) {
                $this->download_merged_awb_pdf($awb_numbers);
            } else {
                $redirect_to = add_query_arg('bulk_action_error', 'no_awb', $redirect_to);
            }

            return $redirect_to;
        }

        if ($action === 'download_awb_zip') {
            $awb_files = array();

            foreach ($ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $awb = $order->get_meta('_optimus_awb_number');
                    if (!empty($awb)) {
                        $awb_numbers = array_map('trim', explode(',', $awb));
                        foreach ($awb_numbers as $awb_number) {
                            $response = $this->api->get_awb_pdf($awb_number);

                            if (!is_wp_error($response) && isset($response['pdf_data'])) {
                                $pdf_content = base64_decode($response['pdf_data']);
                                if ($pdf_content !== false) {
                                    $awb_files['awb-' . $awb_number . '.pdf'] = $pdf_content;
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($awb_files)) {
                $this->download_awb_zip($awb_files);
            } else {
                $redirect_to = add_query_arg('bulk_action_error', 'no_awb', $redirect_to);
            }

            return $redirect_to;
        }

        return $redirect_to;
    }

    /**
     * Download merged AWB PDFs directly to the browser as a file for saving.
     *
     * @param array $awb_ids AWB IDs to merge.
     * @return void
     */
    public function download_merged_awb_pdf(array $awb_ids) {
        if (empty($awb_ids)) {
            wp_die(esc_html__('No AWB IDs provided', 'optimus-courier'));
        }

        // Initialize FPDI
        $pdf = new Fpdi();

        foreach ($awb_ids as $awb_id) {
            $response = $this->api->get_awb_pdf($awb_id);

            if (is_wp_error($response)) {
                $this->log_debug('Optimus Courier API error for AWB ' . $awb_id . ': ' . $response->get_error_message());
                continue;
            }

            if (isset($response['error']) && $response['error'] !== 0) {
                $this->log_debug('Optimus Courier API error for AWB ' . $awb_id . ': ' . ($response['error_message'] ?? 'Unknown error'));
                continue;
            }

            $pdf_data = $response['pdf_data'] ?? null;

            if (empty($pdf_data)) {
                $this->log_debug('No PDF data received for AWB ' . $awb_id);
                continue;
            }

            $decoded_content = base64_decode($pdf_data);
            if ($decoded_content === false) {
                $this->log_debug('Invalid PDF data received for AWB ' . $awb_id);
                continue;
            }

            // Save the decoded content to a temporary file
            $temp_file = tempnam(sys_get_temp_dir(), 'awb_pdf_');
            file_put_contents($temp_file, $decoded_content);

            $pdf = $this->pdf_page_setup($pdf, $temp_file);

            // Clean up the temporary file
            if (file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
        }

        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="merged-awb.pdf"'); // Force download
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        }

        $pdf->Output('D', 'merged-awb.pdf'); // 'D' forces download
        exit;
    }

    /**
     * Output PDF from bytes with proper headers.
     *
     * @param string $content PDF content
     * @param string $filename Output filename
     * @param string $disposition Content disposition (inline|attachment)
     * @return void
     */
    private function output_pdf(string $content, string $filename, string $disposition = 'inline'): void 
    {
        if (!in_array($disposition, ['inline', 'attachment'])) {
            throw new \BadMethodCallException('Invalid disposition type');
        }

        try {
            // Create a temporary file to store the PDF content
            $temp_file = tempnam(sys_get_temp_dir(), 'pdf_');
            if ($temp_file === false) {
                throw new \RuntimeException('Could not create temporary file');
            }

            // Write the content to the temporary file
            if (file_put_contents($temp_file, $content) === false) {
                throw new \RuntimeException('Could not write to temporary file');
            }

            // Initialize FPDI
            $pdf = new Fpdi();

            $pdf = $this->pdf_page_setup($pdf, $temp_file);

            // Clean up the temporary file
            if (file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }

            // Send headers
            if (!headers_sent()) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('Cache-Control: post-check=0, pre-check=0', false);
                header('Pragma: no-cache');
            }

            // Output the PDF
            $pdf->Output('D', $filename); // 'D' forces download
            exit;

        } catch (Exception $e) {
            $this->log_debug('PDF Output Error: ' . $e->getMessage());
            if (isset($temp_file) && file_exists($temp_file)) {
                wp_delete_file($temp_file);
            }
            wp_die(esc_html__('Error outputting PDF', 'optimus-courier'));
        }
    }

    /**
     * Create and download a ZIP file containing AWB PDFs.
     *
     * @param array $awb_files Array of filenames and their content.
     * @return void
     */
    private function download_awb_zip(array $awb_files) {
        if (empty($awb_files)) {
            wp_die(esc_html__('No AWB files to include in ZIP', 'optimus-courier'));
        }

        $zip = new \ZipArchive();
        $temp_file = tempnam(sys_get_temp_dir(), 'awb_zip_');
        $temp_files = []; // Track temporary files for cleanup

        if ($zip->open($temp_file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            wp_die(esc_html__('Could not create ZIP file', 'optimus-courier'));
        }

        foreach ($awb_files as $filename => $content) {
            try {
                // Create a temporary file for the raw PDF content
                $temp_pdf = tempnam(sys_get_temp_dir(), 'awb_pdf_');
                file_put_contents($temp_pdf, $content);
                $temp_files[] = $temp_pdf;

                // Use FPDI to process the PDF
                $pdf = new Fpdi();
                $pdf = $this->pdf_page_setup($pdf, $temp_pdf);

                // Save the processed PDF to another temporary file
                $processed_pdf = tempnam(sys_get_temp_dir(), 'processed_pdf_');
                $pdf->Output($processed_pdf, 'F');
                $temp_files[] = $processed_pdf;

                // Add the processed PDF to the ZIP archive
                if (!$zip->addFile($processed_pdf, $filename)) {
                    $this->log_debug('Failed to add file to ZIP: ' . $filename);
                }
            } catch (Exception $e) {
                $this->log_debug('Failed to process PDF for ZIP: ' . $filename . ' - ' . $e->getMessage());
            }
        }

        $zip->close();

        if (!file_exists($temp_file)) {
            wp_die(esc_html__('ZIP file could not be created', 'optimus-courier'));
        }

        // Ensure proper headers are sent for downloading the ZIP file
        if (!headers_sent()) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="awb-files.zip"');
            header('Content-Length: ' . filesize($temp_file));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
        }

        // Output the ZIP file
        WP_Filesystem();
        global $wp_filesystem;
        if ($wp_filesystem->exists($temp_file)) {
            echo $wp_filesystem->get_contents($temp_file);
        }

        // Clean up temporary files after the ZIP has been sent
        if (file_exists($temp_file)) {
            wp_delete_file($temp_file);
        }
        foreach ($temp_files as $file) {
            if (file_exists($file)) {
                wp_delete_file($file);
            }
        }

        exit;
    }

    /**
     * Set up the page size and content scaling for the PDF.
     *
     * @param Fpdi $pdf FPDI instance.
     * @param string $temp_file Temporary file path.
     * @return Fpdi Modified FPDI instance.
     */
    private function pdf_page_setup($pdf, $temp_file) {
        // Set the page size to A7 in landscape orientation
        $a7_width = 152; // A7 width in mm
        $a7_height = 101; // A7 height in mm

        // Add pages from the current PDF to the merged PDF
        $page_count = $pdf->setSourceFile($temp_file);
        for ($i = 1; $i <= $page_count; $i++) {
            $tpl_idx = $pdf->importPage($i);

            // Get the dimensions of the imported page
            $size = $pdf->getTemplateSize($tpl_idx);

            // Add a new A7 page in landscape orientation
            $pdf->AddPage('L', [$a7_width, $a7_height]);

            // Calculate scaling to fit the content within the A7 page
            $scale = 1;

            // Center the content on the A7 page
            $x_offset = ($a7_width - $size['width'] * $scale);
            $y_offset = ($a7_height - $size['height'] * $scale) / 2;

            // Use the imported page as a template, scaled and centered
            $pdf->useTemplate($tpl_idx, $x_offset, $y_offset, $size['width'] * $scale, $size['height'] * $scale);
        }
        return $pdf;
    }

    /**
     * Handle the AJAX request for downloading AWB PDF
     */
    public function handle_download_awb_ajax() {
        // Verify nonce using the same nonce as in wp_localize_script
        check_ajax_referer('optimus_courier_nonce', 'nonce');

        // Get AWB number with proper unslash
        $awb_number = isset($_GET['awb']) ? sanitize_text_field(wp_unslash($_GET['awb'])) : '';
        if (empty($awb_number)) {
            wp_die(esc_html__('Numărul AWB este obligatoriu', 'optimus-courier'));
        }

        // Download the PDF
        $this->download_awb_pdf($awb_number);
        exit;
    }

    /**
     * Download the AWB PDF directly to the browser.
     *
     * @param string $awb_id AWB ID.
     * @return void
     */
    public function download_awb_pdf($awb_id) {
        if (empty($awb_id)) {
            wp_die(esc_html__('ID-ul AWB este obligatoriu', 'optimus-courier'));
        }

        // Use the API instance to get the PDF
        $response = $this->api->get_awb_pdf($awb_id);
        
        if (is_wp_error($response)) {
            wp_die(esc_html($response->get_error_message()));
        }

        // Check for API error response
        if (isset($response['error']) && $response['error'] !== 0) {
            $error_message = isset($response['error_message']) ? $response['error_message'] : __('Eroare necunoscută', 'optimus-courier');
            wp_die(sprintf(
                esc_html__('Eroare API: %s', 'optimus-courier'),
                esc_html($error_message)
            ));
        }

        // Look for PDF data in the correct response key
        $pdf_data = null;
        if (!empty($response['pdf_data'])) {
            $pdf_data = $response['pdf_data'];
        }

        if (empty($pdf_data)) {
            wp_die(esc_html__('Nu s-au primit date PDF de la API', 'optimus-courier'));
        }
        
        $pdf_content = base64_decode($pdf_data);
        if ($pdf_content === false) {
            wp_die(esc_html__('Date PDF invalide', 'optimus-courier'));
        }

        $this->output_pdf(
            $pdf_content,
            'awb-' . sanitize_file_name($awb_id) . '.pdf',
            'inline'
        );
    }

    public function custom_woocommerce_shop_order_search_fields( $search_fields ) {
        $search_fields[] = '_optimus_awb_number'; // Add the AWB meta field.
        return $search_fields;
    }

    public function add_awb_meta_box() {
        $screen = class_exists(CustomOrdersTableController::class) && wc_get_container()->get(CustomOrdersTableController::class)->custom_orders_table_usage_is_enabled()
            ? wc_get_page_screen_id('shop-order')
            : 'shop_order';

        add_meta_box(
            'optimus_courier_awb_meta_box',
            esc_html__('Generate AWB', 'optimus-courier'),
            [$this, 'render_awb_meta_box'],
            $screen, // Dynamically set screen ID for HPOS compatibility
            'side',  // Ensure it's on the right side
            'low'    // Set priority to low to place it below order notes
        );
    }

    public function render_awb_meta_box($post) {
        $order = wc_get_order($post->get_id());
        if (!$order) {
            echo '<p>' . esc_html__('Comanda nu a fost găsită.', 'optimus-courier') . '</p>';
            return;
        }

        $order_id = $order->get_id();
        $awb_number = $order->get_meta('_optimus_awb_number');
        $settings = get_option('optimus_courier_settings', []);
        $default_weight = $settings['optimus_courier_greutate'] ?? 1.00;
        $default_parcels = $settings['optimus_courier_nr_colete'] ?? 1;

        if (!empty($awb_number)) {
            // Display AWB numbers with tracking links
            $awb_numbers = array_map('trim', explode(',', $awb_number));
            echo '<p><strong>' . esc_html__('AWB Numbers:', 'optimus-courier') . '</strong></p>';
            echo '<div class="awb-numbers">';
            foreach ($awb_numbers as $awb) {
                echo '<p><a href="' . esc_url($this->tracking_url . '?awb=' . esc_attr($awb)) . '" target="_blank">' . esc_html($awb) . '</a></p>';
            }
            
            // Add download and regenerate buttons
            echo '<div style="display: flex; align-items: center; gap: 5px;">';
            $download_url = admin_url('admin-ajax.php?action=optimus_download_awb&awb=' . urlencode($awb) . '&nonce=' . wp_create_nonce('optimus_courier_nonce'));
            
            echo '<a href="' . esc_url($download_url) . '" class="button button-small" target="_blank">' . 
                esc_html__('Descarcă AWB', 'optimus-courier') . '</a>';

            // Only show regenerate button if order is not completed
            if ($order->get_status() !== 'completed') {
                echo '<button class="regenerate-awb" data-order-id="' . esc_attr($order->get_id()) . '" title="' . esc_attr__('Regenerate AWB', 'optimus-courier') . '">
                        <span class="dashicons dashicons-update"></span>
                      </button>';
                
                // Add delete button
                echo '<button class="delete-awb button button-small button-link-delete" data-order-id="' . esc_attr($order->get_id()) . '" data-awb="' . esc_attr($awb_number) . '">
                        ' . esc_html__('Șterge AWB', 'optimus-courier') . '
                      </button>';
            }
            echo '</div>';
            echo '</div>';
        }
        else {
            // Display the inputs inside a div instead of a form
            echo '<div id="optimus-courier-awb-container" data-order-id="' . esc_attr($order_id) . '">';
            wp_nonce_field('optimus_courier_generate_awb', 'optimus_courier_nonce');

            echo '<p><label>' . esc_html__('Nume Destinatar:', 'optimus-courier') . '</label>';
            echo '<input type="text" name="destinatar_nume" value="' . esc_attr($order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name()) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Adresă Destinatar:', 'optimus-courier') . '</label>';
            echo '<input type="text" name="destinatar_adresa" value="' . esc_attr($order->get_shipping_address_1()) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Oraș:', 'optimus-courier') . '</label>';
            echo '<input type="text" name="destinatar_localitate" value="' . esc_attr($order->get_shipping_city()) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Județ:', 'optimus-courier') . '</label>';
            $states = WC()->countries->get_states($order->get_shipping_country());
            $state_full_name = isset($states[$order->get_shipping_state()]) ? $states[$order->get_shipping_state()] : $order->get_shipping_state();
            echo '<input type="text" name="destinatar_judet" value="' . esc_attr($state_full_name) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Cod Poștal:', 'optimus-courier') . '</label>';
            echo '<input type="text" name="destinatar_cod_postal" value="' . esc_attr($order->get_shipping_postcode()) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Telefon:', 'optimus-courier') . '</label>';
            $phone = $order->get_shipping_phone() ?: $order->get_billing_phone();
            echo '<input type="text" name="destinatar_telefon" value="' . esc_attr($phone) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Email:', 'optimus-courier') . '</label>';
            echo '<input type="email" name="destinatar_email" value="' . esc_attr($order->get_billing_email()) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Greutate (kg):', 'optimus-courier') . '</label>';
            $total_weight = 0;
            foreach ($order->get_items() as $item) {
                $product = $item->get_product();
                if ($product && $product->has_weight()) {
                    $total_weight += floatval($product->get_weight()) * $item->get_quantity();
                }
            }
            $total_weight = $total_weight > 0 ? $total_weight : (float) ($default_weight > 0 ? $default_weight : 1.00);
            echo '<input type="number" step="0.01" name="colet_greutate" value="' . esc_attr($total_weight) . '" class="widefat"></p>';

            echo '<p><label>' . esc_html__('Număr Colete:', 'optimus-courier') . '</label>';
            echo '<input type="number" name="colet_buc" value="' . esc_attr($default_parcels) . '" class="widefat"></p>';

            echo '<button type="button" class="button button-primary" id="generate-awb-button">' . esc_html__('Generează AWB', 'optimus-courier') . '</button>';
            echo '</div>';
        }
    }

    public function generate_awb_from_meta_box() {
        if (!check_ajax_referer('optimus_courier_nonce', 'nonce', false)) {
            wp_send_json_error('Verificarea de securitate a eșuat', 403);
            return;
        }

        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error('Permisiuni insuficiente', 403);
            return;
        }

        // Validate POST data exists
        if (!isset($_POST) || empty($_POST)) {
            wp_send_json_error(esc_html__('Nu s-au primit date pentru generarea AWB-ului', 'optimus-courier'));
            return;
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        if (!$order_id) {
            wp_send_json_error(esc_html__('ID comandă invalid sau lipsă', 'optimus-courier'));
            return;
        }

        // Validate and sanitize all POST data
        $required_fields = [
            'destinatar_nume' => esc_html__('Numele destinatarului', 'optimus-courier'),
            'destinatar_adresa' => esc_html__('Adresa destinatarului', 'optimus-courier'),
            'destinatar_localitate' => esc_html__('Localitatea', 'optimus-courier'),
            'destinatar_judet' => esc_html__('Județul', 'optimus-courier'),
            'destinatar_cod_postal' => esc_html__('Codul poștal', 'optimus-courier'),
            'destinatar_telefon' => esc_html__('Telefonul', 'optimus-courier'),
            'destinatar_email' => esc_html__('Email-ul', 'optimus-courier'),
            'colet_greutate' => esc_html__('Greutatea coletului', 'optimus-courier'),
            'colet_buc' => esc_html__('Numărul de colete', 'optimus-courier')
        ];

        $missing_fields = [];
        foreach ($required_fields as $field => $label) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                $missing_fields[] = $label;
            }
        }

        if (!empty($missing_fields)) {
            wp_send_json_error(sprintf(
                esc_html__('Următoarele câmpuri sunt obligatorii: %s', 'optimus-courier'),
                implode(', ', $missing_fields)
            ));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(esc_html__('Comanda nu a fost găsită', 'optimus-courier'));
            return;
        }

        // Collect and sanitize form data with safe defaults
        $awb_data = array(
            'destinatar_nume' => sanitize_text_field(wp_unslash($_POST['destinatar_nume'])),
            'destinatar_adresa' => sanitize_text_field(wp_unslash($_POST['destinatar_adresa'])),
            'destinatar_localitate' => sanitize_text_field(wp_unslash($_POST['destinatar_localitate'])),
            'destinatar_judet' => sanitize_text_field(wp_unslash($_POST['destinatar_judet'])),
            'destinatar_cod_postal' => sanitize_text_field(wp_unslash($_POST['destinatar_cod_postal'])),
            'destinatar_telefon' => sanitize_text_field(wp_unslash($_POST['destinatar_telefon'])),
            'destinatar_email' => sanitize_email(wp_unslash($_POST['destinatar_email'])),
            'colet_greutate' => (float) filter_var(wp_unslash($_POST['colet_greutate']), FILTER_VALIDATE_FLOAT) ?: 0.0,
            'colet_buc' => (int) filter_var(wp_unslash($_POST['colet_buc']), FILTER_VALIDATE_INT) ?: 0,
            'data_colectare' => gmdate('Y-m-d'),
            'ref_factura' => $order->get_order_number(),
        );

        // Additional validation with specific error messages
        if ($awb_data['colet_greutate'] <= 0) {
            wp_send_json_error(esc_html__('Greutatea coletului trebuie să fie un număr pozitiv', 'optimus-courier'));
            return;
        }

        if ($awb_data['colet_buc'] <= 0) {
            wp_send_json_error(esc_html__('Numărul de colete trebuie să fie un număr întreg pozitiv', 'optimus-courier'));
            return;
        }

        if (!is_email($awb_data['destinatar_email'])) {
            wp_send_json_error(esc_html__('Adresa de email este invalidă', 'optimus-courier'));
            return;
        }

        // Call API to generate AWB
        $response = $this->api->create_awb($awb_data);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        if (isset($response['error']) && $response['error'] !== 0) {
            wp_send_json_error($response['error_message'] ?? esc_html__('Eroare necunoscută', 'optimus-courier'));
        }

        if (!empty($response['pcl']) && is_array($response['pcl'])) {
            $awb_number = implode(', ', $response['pcl']);
            update_post_meta($order_id, '_optimus_awb_number', $awb_number);
            $order->update_meta_data('_optimus_awb_number', $awb_number);
            $order->save_meta_data();

            // Add auto-complete check here
            $this->maybe_complete_order($order);

            wp_send_json_success(['awb_number' => $awb_number]);
        } else {
            wp_send_json_error(esc_html__('Nu s-a primit niciun număr AWB de la API', 'optimus-courier'));
        }
    }

    /**
     * Afișează informațiile de urmărire AWB pe pagina detaliilor comenzii
     *
     * @param WC_Order $order Obiectul comenzii
     */
    public function display_awb_tracking_info($order) {
        $awb = $order->get_meta('_optimus_awb_number');
        
        if (!empty($awb)) {
            $awb_numbers = array_map('trim', explode(',', $awb));
            
            echo '<section class="woocommerce-order-awb-tracking">';
            echo '<h2 class="woocommerce-order-awb-tracking__title">' . esc_html__('Informații urmărire colet', 'optimus-courier') . '</h2>';
            echo '<div class="woocommerce-order-awb-tracking__content">';
            
            foreach ($awb_numbers as $awb_number) {
                echo '<p class="woocommerce-order-awb-tracking__number">';
                echo '<strong>' . esc_html__('Număr AWB:', 'optimus-courier') . '</strong> ';
                echo '<a href="' . esc_url($this->tracking_url . '?awb=' . esc_attr($awb_number)) . '" target="_blank">';
                echo esc_html($awb_number);
                echo ' <span class="screen-reader-text">' . esc_html__('(se deschide într-o filă nouă)', 'optimus-courier') . '</span>';
                echo '</a>';
                echo '</p>';
            }
            
            echo '</div>';
            echo '</section>';
        }
    }

    /**
     * Send AWB generation email notification to customer
     *
     * @param WC_Order $order
     * @param string $awb_number
     * @return void
     */
    private function send_awb_notification_email($order, $awb_number) {
        try {
            $settings = get_option('optimus_courier_settings', []);
            
            // Check if notifications are enabled
            if (!isset($settings['optimus_courier_notify_client']) || $settings['optimus_courier_notify_client'] !== 'on') {
                return;
            }

            // Verify order and email
            if (!$order || !is_a($order, 'WC_Order')) {
                return;
            }

            $to = $order->get_billing_email();
            if (empty($to)) {
                return;
            }

            // Get email content from settings with fallback
            $email_content = isset($settings['optimus_courier_email_content']) && !empty($settings['optimus_courier_email_content'])
                ? $settings['optimus_courier_email_content'] 
                : esc_html__('Comanda dumneavoastră #{order_number} a fost expediată. Puteți urmări coletul folosind numărul(ele) AWB: {awb_number}. Link urmărire: {awb_tracking_link}', 'optimus-courier');

            // Process AWB numbers and create tracking links
            $awb_numbers = array_map('trim', explode(',', $awb_number));
            $tracking_links = array_map(function($awb) {
                return $this->tracking_url . '?awb=' . urlencode($awb);
            }, $awb_numbers);

            // Replace placeholders
            $replacements = [
                '{order_number}' => $order->get_order_number(),
                '{awb_number}' => $awb_number,
                '{awb_tracking_link}' => implode("\n", $tracking_links),
                '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                '{shop_name}' => get_bloginfo('name')
            ];

            $email_content = str_replace(array_keys($replacements), array_values($replacements), $email_content);

            // Get WooCommerce mailer
            $mailer = WC()->mailer();
            
            // Get email subject from settings with fallback
            $subject = isset($settings['optimus_courier_email_subject']) && !empty($settings['optimus_courier_email_subject'])
                ? $settings['optimus_courier_email_subject']
                : esc_html__('Comanda #{order_number} a fost expediată - {shop_name}', 'optimus-courier');

            // Replace placeholders in subject
            $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);

            // Create email headers
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
                'Reply-To: ' . get_option('admin_email')
            ];

            // Wrap content in WooCommerce template
            $wrapped_content = $mailer->wrap_message(
                sprintf(esc_html__('Comanda #%s a fost expediată', 'optimus-courier'), $order->get_order_number()),
                $email_content
            );

            // Send email using WooCommerce mailer
            $sent = $mailer->send($to, $subject, $wrapped_content, $headers);

            // Fallback to wp_mail if WooCommerce mailer fails
            if (!$sent) {
                wp_mail($to, $subject, $wrapped_content, $headers);
            }

        } catch (Exception $e) {
            // Silently fail - we don't want to interrupt the AWB generation process
            return;
        }
    }

    /**
     * Handle the AJAX request for deleting AWB
     */
    public function handle_delete_awb_ajax() {
        if (!check_ajax_referer('optimus_courier_nonce', 'nonce', false)) {
            wp_send_json_error('Verificarea de securitate a eșuat', 403);
            return;
        }
        
        if (!current_user_can('edit_shop_orders')) {
            wp_send_json_error(esc_html__('Permisiuni insuficiente', 'optimus-courier'), 403);
            return;
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $awb_number = isset($_POST['awb']) ? sanitize_text_field($_POST['awb']) : '';

        if (!$order_id || empty($awb_number)) {
            wp_send_json_error(esc_html__('Date invalide', 'optimus-courier'));
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(esc_html__('Comanda nu a fost găsită', 'optimus-courier'));
            return;
        }

        // Delete AWB from order meta
        $order->delete_meta_data('_optimus_awb_number');
        $order->save_meta_data();
        delete_post_meta($order_id, '_optimus_awb_number');

        // Call API to delete AWB
        // $response = $this->api->delete_awb($awb_number);

        // if (is_wp_error($response)) {
        //     wp_send_json_error($response->get_error_message());
        //     return;
        // }

        // if (isset($response['error']) && $response['error'] !== 0) {
        //     $error_message = isset($response['error_message']) ? $response['error_message'] : esc_html__('Eroare necunoscută', 'optimus-courier');
        //     wp_send_json_error($error_message);
        //     return;
        // }

        wp_send_json_success(array(
            'message' => esc_html__('AWB șters cu succes', 'optimus-courier')
        ));
    }
}


