<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://upcasted.com
 * @since      1.0.0
 *
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/admin
 * @author     Upcasted <contact@upcasted.com>
 */
class Optimus_Courier_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function add_settings_page() {
        add_menu_page(
            'Optimus Courier Settings',
            'Optimus Courier',
            'manage_options',
            'optimus-courier-settings',
            [ $this, 'render_settings_page' ],
            'dashicons-admin-generic',
            100
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Optimus Courier Settings</h1>
            <?php settings_errors( 'optimus_courier_settings' ); // Display success or error messages ?>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'optimus_courier_settings_group' );
                do_settings_sections( 'optimus-courier-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function register_settings() {
        // Register settings
        register_setting( 'optimus_courier_settings_group', 'optimus_courier_settings' );

        // Setări Conectare
        add_settings_section(
            'optimus_courier_connection_section',
            'Setări Conectare API',
            [ $this, 'render_connection_status' ], // Add callback to display connection status
            'optimus-courier-settings'
        );

        add_settings_field(
            'optimus_courier_username',
            'Username',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_connection_section',
            [ 'label_for' => 'optimus_courier_username', 'option_key' => 'optimus_courier_username' ]
        );

        // API Key
        add_settings_field(
            'optimus_courier_api_key',
            'API Key',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_connection_section',
            [
                'label_for' => 'optimus_courier_api_key',
                'option_key' => 'optimus_courier_api_key',
                'type' => 'password', // Password input
                'description' => 'Introduceți cheia API.'
            ]
        );

        // Setări Standard
        add_settings_section(
            'optimus_courier_standard_section',
            'Setări Implicite Generare AWB',
            null,
            'optimus-courier-settings'
        );

        // Număr Colete
        add_settings_field(
            'optimus_courier_nr_colete',
            'Număr Colete',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_standard_section',
            [
                'label_for' => 'optimus_courier_nr_colete',
                'option_key' => 'optimus_courier_nr_colete',
                'type' => 'number', // Numeric input
                'default' => 1, // Default value
                'description' => 'Introduceți numărul de colete.'
            ]
        );

        // Greutate (kg)
        add_settings_field(
            'optimus_courier_greutate',
            'Greutate (kg)',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_standard_section',
            [
                'label_for' => 'optimus_courier_greutate',
                'option_key' => 'optimus_courier_greutate',
                'type' => 'text', // Allow decimals
                'description' => 'Introduceți greutatea în kilograme (ex: 0.5).'
            ]
        );

        add_settings_field(
            'optimus_courier_continut',
            'Conținut',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_standard_section',
            [ 'label_for' => 'optimus_courier_continut', 'option_key' => 'optimus_courier_continut' ]
        );

        // Setări Automatizări
        add_settings_section(
            'optimus_courier_automation_section',
            'Setări Automatizări',
            null,
            'optimus-courier-settings'
        );

        add_settings_field(
            'optimus_courier_awb_auto_status',
            'Generare AWB Automat',
            [ $this, 'render_order_status_dropdown' ],
            'optimus-courier-settings',
            'optimus_courier_automation_section',
            [ 
                'label_for' => 'optimus_courier_awb_auto_status', 
                'option_key' => 'optimus_courier_awb_auto_status', 
                'description' => 'Alegeți statusul comenzii la care să se genereze automat AWB-ul.'
            ]
        );

        add_settings_field(
            'optimus_courier_complete_order_auto',
            'Marchează Comanda Completă Automat',
            [ $this, 'render_checkbox_field' ],
            'optimus-courier-settings',
            'optimus_courier_automation_section',
            [ 
                'label_for' => 'optimus_courier_complete_order_auto', 
                'option_key' => 'optimus_courier_complete_order_auto',
                'description' => 'Dacă este activată, comanda va fi marcată automat ca fiind completă după generarea AWB-ului.'
            ]
        );

        // Notificări
        add_settings_section(
            'optimus_courier_notifications_section',
            'Notificări',
            null,
            'optimus-courier-settings'
        );

        add_settings_field(
            'optimus_courier_notify_client',
            'Notificare Client la Generare AWB',
            [ $this, 'render_checkbox_field' ],
            'optimus-courier-settings',
            'optimus_courier_notifications_section',
            [ 'label_for' => 'optimus_courier_notify_client', 'option_key' => 'optimus_courier_notify_client' ]
        );

        add_settings_field(
            'optimus_courier_email_subject',
            'Subiect Email Notificare',
            [ $this, 'render_text_field' ],
            'optimus-courier-settings',
            'optimus_courier_notifications_section',
            [ 
                'label_for' => 'optimus_courier_email_subject', 
                'option_key' => 'optimus_courier_email_subject',
                'description' => 'Puteti folosi in text variablile {order_number}, {awb_number}, {customer_name}, {shop_name}' 
            ]
        );

        add_settings_field(
            'optimus_courier_email_content',
            'Conținut Email Notificare',
            [ $this, 'render_wysiwyg_field' ],
            'optimus-courier-settings',
            'optimus_courier_notifications_section',
            [ 
                'label_for' => 'optimus_courier_email_content', 
                'option_key' => 'optimus_courier_email_content',
                'description' => 'Puteti folosi in text variablile {order_number}, {awb_number}, {awb_tracking_link}, {customer_name}, {shop_name}' 
            ]
        );

        add_settings_field(
            'optimus_courier_tracking_page_url',
            'URL Pagină de Tracking',
            [ $this, 'render_tracking_page_field' ],
            'optimus-courier-settings',
            'optimus_courier_notifications_section',
            [ 
                'label_for' => 'optimus_courier_tracking_page_url', 
                'option_key' => 'optimus_courier_tracking_page_url',
                'type' => 'url',
                'description' => 'Creați o pagină nouă, adăugați shortcode-ul de mai jos și copiați aici URL-ul paginii.' 
            ]
        );

        // Add a callback to validate settings
        add_filter( 'pre_update_option_optimus_courier_settings', [ $this, 'validate_settings' ], 10, 2 );
    }

    // Display connection status
    public function render_connection_status() {
        $options = get_option('optimus_courier_settings');
        if (!empty($options['is_connected'])) {
            echo '<div class="notice notice-success inline"><p>API conectat cu succes!</p></div>';
        }
    }

    // Validate settings and check connection
    public function validate_settings($new_value, $old_value) {
        if (isset($new_value['optimus_courier_username'], $new_value['optimus_courier_api_key'])) {
            $api = new Optimus_Courier_API($new_value['optimus_courier_username'], $new_value['optimus_courier_api_key']);
            $response = $api->get_counties();

            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                
                // Handle specific error cases
                switch ($error_code) {
                    case 'http_request_failed':
                        $message = 'Timeout sau eroare de conexiune la API. Verificați conexiunea la internet.';
                        break;
                    case 'http_request_not_found':
                        $message = 'API-ul nu poate fi accesat. URL invalid.';
                        break;
                    default:
                        $message = 'Eroare API: ' . $error_message;
                }
                
                add_settings_error(
                    'optimus_courier_settings',
                    'optimus_courier_api_error',
                    $message,
                    'error'
                );
                $new_value['is_connected'] = false;
                return $new_value;
            }

            if (!is_array($response)) {
                add_settings_error(
                    'optimus_courier_settings',
                    'optimus_courier_api_error',
                    'Răspuns invalid de la API. Format neașteptat.',
                    'error'
                );
                $new_value['is_connected'] = false;
                return $new_value;
            }

            if (!isset($response['error'])) {
                add_settings_error(
                    'optimus_courier_settings',
                    'optimus_courier_api_error',
                    'Răspuns incomplet de la API.',
                    'error'
                );
                $new_value['is_connected'] = false;
                return $new_value;
            }

            if ($response['error'] !== 0) {
                $error_message = !empty($response['error_message']) ? $response['error_message'] : 'Credențiale invalide';
                add_settings_error(
                    'optimus_courier_settings',
                    'optimus_courier_api_error',
                    'Eroare API: ' . $error_message,
                    'error'
                );
                $new_value['is_connected'] = false;
                return $new_value;
            }

            // Success case
            $new_value['is_connected'] = true;
            add_settings_error(
                'optimus_courier_settings',
                'optimus_courier_api_success',
                'Conexiunea cu API-ul a fost realizată cu succes!',
                'updated'
            );
        }

        return $new_value;
    }

    public function render_text_field( $args ) {
        $options = get_option( 'optimus_courier_settings', [] );
        $default_value = $args['default'] ?? ''; // Use default value if provided
        $value = $options[ $args['option_key'] ] ?? $default_value;
        $type = isset( $args['type'] ) ? $args['type'] : 'text'; // Default to text input
        echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $args['label_for'] ) . '" name="optimus_courier_settings[' . esc_attr( $args['option_key'] ) . ']" value="' . esc_attr( $value ) . '" />';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_checkbox_field( $args ) {
        $options = get_option( 'optimus_courier_settings', [] );
        $checked = ! empty( $options[ $args['option_key'] ] ) ? 'checked' : '';
        echo '<input type="checkbox" id="' . esc_attr( $args['label_for'] ) . '" name="optimus_courier_settings[' . esc_attr( $args['option_key'] ) . ']" ' . $checked . ' />';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_textarea_field( $args ) {
        $options = get_option( 'optimus_courier_settings', [] );
        $value = $options[ $args['option_key'] ] ?? '';
        echo '<textarea id="' . esc_attr( $args['label_for'] ) . '" name="optimus_courier_settings[' . esc_attr( $args['option_key'] ) . ']">' . esc_textarea( $value ) . '</textarea>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_wysiwyg_field( $args ) {
        $options = get_option( 'optimus_courier_settings', [] );
        $content = $options[ $args['option_key'] ] ?? '';
    
        $editor_id = esc_attr( $args['label_for'] );
        $settings = [
            'textarea_name' => 'optimus_courier_settings[' . esc_attr( $args['option_key'] ) . ']',
            'textarea_rows' => 10,
            'media_buttons' => true, // Allows adding media
            'teeny'         => false, // Full editor
            'quicktags'     => true,  // Enable HTML editing
        ];
    
        wp_editor( $content, $editor_id, $settings );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_order_status_dropdown( $args ) {
        $options = get_option( 'optimus_courier_settings', [] );
        $selected_status = $options[ $args['option_key'] ] ?? 'none';

        // Get WooCommerce order statuses
        if ( function_exists( 'wc_get_order_statuses' ) ) {
            $statuses = wc_get_order_statuses();
        } else {
            $statuses = [];
        }

        // Render the dropdown
        echo '<select id="' . esc_attr( $args['label_for'] ) . '" name="optimus_courier_settings[' . esc_attr( $args['option_key'] ) . ']">';
        echo '<option value="none"' . selected( $selected_status, 'none', false ) . '>None</option>';
        foreach ( $statuses as $status_key => $status_label ) {
            echo '<option value="' . esc_attr( $status_key ) . '"' . selected( $selected_status, $status_key, false ) . '>' . esc_html( $status_label ) . '</option>';
        }
        echo '</select>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function render_tracking_page_field($args) {
        $options = get_option('optimus_courier_settings', []);
        $value = $options[$args['option_key']] ?? '';
        
        echo '<input type="' . esc_attr($args['type']) . '" id="' . esc_attr($args['label_for']) . 
             '" name="optimus_courier_settings[' . esc_attr($args['option_key']) . ']" value="' . esc_attr($value) . '" />';
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
            echo '<div class="shortcode-copy-wrapper" style="margin: 10px 0;">
                    <code id="tracking-shortcode" style="background: #f0f0f1; padding: 4px 8px; display: inline-block; cursor: pointer;" 
                          onclick="copyShortcode(this)" title="Click pentru a copia">[display_optimus_courier_awb_tracking]</code>
                    <span class="copy-feedback" style="display: none; color: #008a20; margin-left: 8px;">✓ Copiat!</span>
                  </div>';
        }
    }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Optimus_Courier_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Optimus_Courier_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/optimus-courier-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
        wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/optimus-courier-admin.js', array( 'jquery' ), $this->version, false );
        
        // Get tracking URL from settings or use default
        $settings = get_option('optimus_courier_settings', []);
        $tracking_url = !empty($settings['optimus_courier_tracking_page_url']) 
            ? $settings['optimus_courier_tracking_page_url'] 
            : OPTIMUS_COURIER_DEFAULT_TRACKING_URL;

        wp_localize_script( $this->plugin_name, 'optimus_courier', array(
            'nonce' => wp_create_nonce('optimus_courier_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php'),
            'plugin_url' => plugin_dir_url(__FILE__),
            'tracking_page_url' => $tracking_url,
        ));
        wp_enqueue_script($this->plugin_name);
    }

}
