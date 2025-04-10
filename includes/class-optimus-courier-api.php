<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Optimus_Courier_API {

    /**
     * API Base URL
     *
     * @var string
     */
    private $base_url = 'https://awb.optimuscourier.ro';

    /**
     * API Username
     *
     * @var string
     */
    private $username;

    /**
     * API Key
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor
     *
     * @param string|null $username API username.
     * @param string|null $api_key API key.
     */
    public function __construct( $username = null, $api_key = null ) {
        if ($username === null || $api_key === null) {
            $this->username = self::get_username();
            $this->api_key = self::get_api_key();
        } else {
            $this->username = $username;
            $this->api_key = $api_key;
        }
    }

    /**
     * Get the stored API username
     *
     * @return string
     */
    public static function get_username() {
        $settings = get_option('optimus_courier_settings', []);
        return isset($settings['optimus_courier_username']) ? $settings['optimus_courier_username'] : '';
    }

    /**
     * Get the stored API key
     *
     * @return string
     */
    public static function get_api_key() {
        $settings = get_option('optimus_courier_settings', []);
        return isset($settings['optimus_courier_api_key']) ? $settings['optimus_courier_api_key'] : '';
    }

    /**
     * Make a POST request to the API.
     *
     * @param string $endpoint API endpoint.
     * @param array $data Data to send in the request.
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    private function post_request( $endpoint, $data ) {
        $url = $this->base_url . $endpoint;

        $data = array_merge( $data, [
            'username' => $this->username,
            'api_key'  => $this->api_key,
        ] );

        $response = wp_remote_post( $url, [
            'body' => $data,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        return json_decode( $body, true );
    }

    /**
     * Create a new AWB.
     *
     * @param array $awb_data Data for the AWB.
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    public function create_awb( $awb_data ) {
        if (empty($this->username) || empty($this->api_key)) {
            return new WP_Error('missing_credentials', 'API credentials are missing');
        }

        $request_data = array_merge([
            'username' => $this->username,
            'api_key' => $this->api_key,
            'action' => 'new_awb'
        ], $awb_data);

        return $this->post_request('/api', $request_data);
    }

    /**
     * Get the PDF for an AWB.
     *
     * @param string $awb_id AWB ID.
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    public function get_awb_pdf( $awb_id ) {
        return $this->post_request( '/api', [
            'action' => 'get_pdf',
            'id'     => $awb_id,
        ] );
    }

    /**
     * Get the list of counties (judete).
     *
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    public function get_counties() {
        return $this->post_request( '/api-judete', [] );
    }

    /**
     * Get the status of an AWB.
     *
     * @param string $awb AWB number or ID.
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    public function get_awb_status( $awb ) {
        return $this->post_request( '/api-status', [
            'awb' => $awb,
        ] );
    }

    /**
     * Track an AWB.
     *
     * @param string $awb AWB number or ID.
     * @param array $awb_data Additional data for tracking.
     * @return array|WP_Error Response data or WP_Error on failure.
     */
    public function track_awb($awb, $awb_data = []) {
        if (empty($this->username) || empty($this->api_key)) {
            return new WP_Error('missing_credentials', 'API credentials are missing');
        }

        $request_data = array_merge([
            'username' => $this->username,
            'api_key' => $this->api_key,
            'awb' => $awb,
            'action' => 'track'
        ], $awb_data);

        return $this->post_request('/api', $request_data);
    }
}
