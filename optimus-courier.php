<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://upcasted.com
 * @since             1.0.0
 * @package           Optimus_Courier
 *
 * @wordpress-plugin
 * Plugin Name:       Optimus Courier
 * Plugin URI:        https://optimuscourier.ro
 * Description:       Plugin WordPress pentru integrarea Optimus Courier cu WooCommerce: expedieri automate, tracking și actualizare status comenzi.
 * Version:           1.0.0
 * Author:            Upcasted
 * Author URI:        https://upcasted.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       optimus-courier
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Load Composer's autoloader
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'OPTIMUS_COURIER_VERSION', '1.0.0' );

/**
 * Define the default tracking URL for the plugin
 */
define( 'OPTIMUS_COURIER_DEFAULT_TRACKING_URL', 'https://optimuscourier.ro/search/' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-optimus-courier-activator.php
 */
function activate_optimus_courier() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-optimus-courier-activator.php';
	Optimus_Courier_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-optimus-courier-deactivator.php
 */
function deactivate_optimus_courier() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-optimus-courier-deactivator.php';
	Optimus_Courier_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_optimus_courier' );
register_deactivation_hook( __FILE__, 'deactivate_optimus_courier' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-optimus-courier.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_optimus_courier() {

	$plugin = new Optimus_Courier();
	$plugin->run();

}
run_optimus_courier();
