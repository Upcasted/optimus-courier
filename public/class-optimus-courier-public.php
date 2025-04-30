<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://upcasted.com
 * @since      1.0.0
 *
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/public
 * @author     Upcasted <contact@upcasted.com>
 */
class Optimus_Courier_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Register shortcode
		add_shortcode('display_optimus_courier_awb_tracking', array($this, 'display_tracking_form'));

	}

	/**
	 * Format tracking response into readable HTML
	 *
	 * @param array $response The tracking response from API
	 * @return string Formatted HTML
	 */
	private function format_tracking_response($response) {
		if (!is_array($response) || isset($response['error']) && $response['error'] !== 0) {
			return '<div class="tracking-error woocommerce-error">' . esc_html__('Nu s-au putut prelua informațiile de tracking.', 'optimus-courier') . '</div>';
		}

		$html = '<div class="tracking-details woocommerce">';
		if (isset($response['data']) && is_array($response['data'])) {
			$html .= '<table class="woocommerce-table shop_table tracking-timeline">';
			$html .= '<thead><tr>';
			$html .= '<th class="woocommerce-table__header">' . esc_html__('Data', 'optimus-courier') . '</th>';
			$html .= '<th class="woocommerce-table__header">' . esc_html__('Status', 'optimus-courier') . '</th>';
			$html .= '</tr></thead>';
			$html .= '<tbody>';
			
			foreach ($response['data'] as $event) {
				$html .= '<tr class="woocommerce-table__row">';
				$html .= '<td class="woocommerce-table__cell">' . esc_html($event['date']) . '</td>';
				$html .= '<td class="woocommerce-table__cell">' . esc_html($event['title']) . '</td>';
				$html .= '</tr>';
			}
			
			$html .= '</tbody></table>';
		}
		$html .= '</div>';
		
		return $html;
	}

	/**
	 * Display AWB tracking form shortcode
	 *
	 * @return string
	 */
	public function display_tracking_form() {
		$result = '';
		$awb = isset($_GET['awb']) ? sanitize_text_field($_GET['awb']) : '';
		
		// Handle POST submission
		if (isset($_POST['optimus_awb']) && wp_verify_nonce($_POST['optimus_tracking_nonce'], 'optimus_tracking')) {
			$api = new Optimus_Courier_Api();
			$tracking_result = $api->track_awb(sanitize_text_field($_POST['optimus_awb']));
			$result = $this->format_tracking_response($tracking_result);
		}
		// Handle GET parameter auto-submission
		elseif (!empty($awb)) {
			$api = new Optimus_Courier_Api();
			$tracking_result = $api->track_awb($awb);
			$result = $this->format_tracking_response($tracking_result);
		}

		$form = '<div class="optimus-tracking-form woocommerce">';
		$form .= '<form method="post" class="woocommerce-form">';
		$form .= wp_nonce_field('optimus_tracking', 'optimus_tracking_nonce', true, false);
		$form .= '<p class="form-row form-row-wide">';
		$form .= '<label for="optimus_awb" class="woocommerce-form__label">' . esc_html__('AWB Optimus Courier:', 'optimus-courier') . '</label>';
		$form .= '<input type="text" id="optimus_awb" name="optimus_awb" value="' . esc_attr($awb) . '" class="woocommerce-Input woocommerce-Input--text input-text" required>';
		$form .= '</p>';
		$form .= '<p class="form-row">';
		$form .= '<button type="submit" class="button btn btn-primary">' . esc_html__('Urmărește AWB', 'optimus-courier') . '</button>';
		$form .= '</p>';
		$form .= '</form>';
		$form .= $result;
		$form .= '</div>';

		return $form;
	}

}
