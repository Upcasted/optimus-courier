<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://upcasted.com
 * @since      1.0.0
 *
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Optimus_Courier
 * @subpackage Optimus_Courier/includes
 * @author     Upcasted <contact@upcasted.com>
 */
class Optimus_Courier {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Optimus_Courier_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'OPTIMUS_COURIER_VERSION' ) ) {
			$this->version = OPTIMUS_COURIER_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'optimus-courier';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Optimus_Courier_Loader. Orchestrates the hooks of the plugin.
	 * - Optimus_Courier_i18n. Defines internationalization functionality.
	 * - Optimus_Courier_Admin. Defines all hooks for the admin area.
	 * - Optimus_Courier_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		// Include the API class
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-optimus-courier-api.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-optimus-courier-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-optimus-courier-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-optimus-courier-admin.php';

		// Initialize WooCommerce integration
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-optimus-courier-woocommerce.php';
		
		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-optimus-courier-public.php';

		$this->loader = new Optimus_Courier_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Optimus_Courier_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Optimus_Courier_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Optimus_Courier_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_settings_page' );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		
		// Initialize WooCommerce-specific hooks after WooCommerce is loaded
		$this->loader->add_action('plugins_loaded', $this, 'initialize_woocommerce_hooks');
	}

	/**
	 * Initialize WooCommerce-specific hooks.
	 *
	 * @since    1.0.0
	 */
	public function initialize_woocommerce_hooks() {
		if (class_exists('WooCommerce')) {
			$optimusCourier = new Optimus_Courier_WooCommerce();

			$this->loader->add_action('init', $optimusCourier, 'register_order_meta');

			// Add WooCommerce-specific hooks
			$this->loader->add_filter('manage_woocommerce_page_wc-orders_columns', $optimusCourier, 'add_awb_column');
			$this->loader->add_action('manage_woocommerce_page_wc-orders_custom_column', $optimusCourier, 'render_awb_column', 10, 2);
			$this->loader->add_action('woocommerce_admin_order_data_after_billing_address', $optimusCourier, 'display_awb_in_order_meta', 10, 1);
			$this->loader->add_action('add_meta_boxes', $optimusCourier, 'add_awb_meta_box');
			$this->loader->add_action('woocommerce_order_details_before_order_table', $optimusCourier, 'display_awb_tracking_info');

			// Register bulk actions based on HPOS status
			if ($optimusCourier->is_hpos_enabled()) {
				$this->loader->add_filter('bulk_actions-woocommerce_page_wc-orders', $optimusCourier, 'register_bulk_actions');
				$this->loader->add_action('handle_bulk_actions-woocommerce_page_wc-orders', $optimusCourier, 'handle_bulk_action', 10, 3);
				$this->loader->add_filter('woocommerce_order_table_search_query_meta_keys', $optimusCourier, 'custom_woocommerce_shop_order_search_fields');
			} else {
				$this->loader->add_filter('bulk_actions-edit-shop_order', $optimusCourier, 'register_bulk_actions');
				$this->loader->add_filter('handle_bulk_actions-edit-shop_order', $optimusCourier, 'handle_bulk_action', 10, 3);
				$this->loader->add_filter('woocommerce_bulk_actions', $optimusCourier, 'register_bulk_actions');
			}

			// Add AJAX handlers
			$this->loader->add_action('wp_ajax_generate_awb_from_meta_box', $optimusCourier, 'generate_awb_from_meta_box');
			$this->loader->add_action('wp_ajax_optimus_generate_awb', $optimusCourier, 'generate_awb_ajax');
			$this->loader->add_action('wp_ajax_optimus_download_awb', $optimusCourier, 'handle_download_awb_ajax');
			$this->loader->add_action('wp_ajax_optimus_delete_awb', $optimusCourier, 'handle_delete_awb_ajax');	

			$this->run();
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$plugin_public = new Optimus_Courier_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Optimus_Courier_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
