<?php

/**
 * @wordpress-plugin
 * Plugin Name:       Optimus Courier
 * Plugin URI:        https://optimuscourier.ro
 * Description:       Plugin WordPress pentru integrarea Optimus Courier cu WooCommerce: expedieri automate, tracking și actualizare status comenzi.
 * Version:           1.0.2
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

// Create a simple PSR-4 autoloader for our dependencies
spl_autoload_register(function ($className) {
    // Map for our namespaces to directories
    $namespaces = [
        'OptimusCourier\\Dependencies\\setasign\\' => __DIR__ . '/src/Dependencies/setasign/',
    ];
    
    foreach ($namespaces as $namespace => $baseDir) {
        $len = strlen($namespace);
        if (strncmp($namespace, $className, $len) !== 0) {
            continue;
        }
        
        $relativeClass = substr($className, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Load the main FPDF class
$fpdf_file = __DIR__ . '/src/Dependencies/setasign/fpdf/fpdf.php';
if (file_exists($fpdf_file)) {
    require_once $fpdf_file;
} else {
    if (is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>Optimus Courier Plugin Error:</strong> ';
            echo 'FPDF library is missing. Please reinstall the plugin completely.';
            echo '</p></div>';
        });
    }
    return;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'OPTIMUS_COURIER_VERSION', '1.0.2' );

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
	// Only run if dependencies are loaded
	if (!class_exists('OptimusCourier\\Dependencies\\setasign\\Fpdi\\Fpdi')) {
		if (is_admin()) {
			add_action('admin_notices', function() {
				echo '<div class="notice notice-error"><p>';
				echo '<strong>Optimus Courier Plugin Error:</strong> ';
				echo 'Required FPDI class could not be loaded. Please reinstall the plugin.';
				echo '</p></div>';
			});
		}
		return;
	}

	$plugin = new Optimus_Courier();
	$plugin->run();
}
run_optimus_courier();
