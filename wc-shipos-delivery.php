<?php
/**
 * @wordpress-plugin
 * Plugin Name: Deliver via Shipos for WooCommerce
 * Plugin URI: https://matat.co.il/
 * Description: A plugin for Ship os Delivery orders from within WooCommerce.
 * Version: 2.1.1
 * Author: Amit Matat
 * Author URI: https://www.linkedin.com/in/amitmatatof/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wc-shipos-delivery
 * Requires Plugins: woocommerce
 * Deliver via Shipos for WooCommerce
 * @package
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Register namespace and clases
 * admin-specific hooks, and public-facing site hooks.
 */

require_once 'class-autoloader.php';

$autoloader = new WCShiposDelivery\Autoloader();

/**
 *
 * Check the license for the authentication for the delivery
 */

use WCShiposDelivery\License;
use WCShiposDelivery\Delivery;
use WCShiposDelivery\Settings;

License::getInstance();
new Settings();

/**
 * Begins execution of the plugin.
 *
 * @since    1.0.0
 */
define( 'DVSFW_PLUGIN_SLUG', 'wc-shipos-delivery' );
define( 'DVSFW_PLUGIN_VERSION', '2.1.1' );


define( 'DVSFW_GET_BY_ID', 'shipping/get_order_details' );
define( 'DVSFW_SAVE_NEW', 'shipping/open_new_order' );
define( 'DVSFW_CHANGE_STATUS', 'shipping/change_order_status' );
define( 'DVSFW_GET_SHIPPING_LABEL', 'shipping/label' );
define( 'DVSFW_GET_BULK_SHIPPING_LABEL', 'shipping/bulk-label' );
define( 'DVSFW_GET_PICKUP_LOCATIONS', 'shipping/pickup_locations' );
define( 'DVSFW_GET_SAME_DAY_DELIVERY_QUOTE', 'shipping/same_day_delivery_quote' );
define( 'DVSFW_GET_SHIPMENT_BY_ORDERR', 'shipping/order' );
define( 'DVSFW_GET_LICENSES', 'licenses' );

register_activation_hook( __FILE__, 'dvsfw_activate_shipos_delivery' );

/**
 * store the plugin install date
 */
function dvsfw_activate_shipos_delivery() {
	$dvsfw_install_date_db = get_option( 'dvsfw_install_date' );
	if ( empty( $dvsfw_install_date_db ) ) {
		add_option( 'dvsfw_install_date', date( 'd-m-Y' ) );
	} else {
		update_option( 'dvsfw_install_date', date( 'd-m-Y' ) );
	}

	$dvsfw_dev_mode_db = get_option( 'dvsfw_dev_mode' );
	if ( empty( $dvsfw_dev_mode_db ) ) {
		add_option( 'dvsfw_dev_mode', 'no' );
	}
}

add_action( 'activated_plugin', 'dvsfw_activation_redirect' );
function dvsfw_activation_redirect( $plugin ) {
	// Skip redirection when plugin was activated using bulk activation method
	if ( isset( $_POST['action'] ) && $_POST['action'] === "activate-selected" ) {
		return;
	}
	if ( $plugin == plugin_basename( __FILE__ ) ) {
		exit( wp_redirect( admin_url( 'admin.php?page=deliver-via-shipos' ) ) );
	}
}

add_filter( 'plugin_action_links', 'add_plugin_action_link', 10, 2 );
function add_plugin_action_link( $links, $file ) {
	if ( $file == plugin_basename( __FILE__ ) ) {
		$shipos_link = '<a href="' . admin_url( 'admin.php?page=deliver-via-shipos' ) . '">Ship OS Shipping</a>';
		array_unshift( $links, $shipos_link );
	}

	return $links;
}

/**
 * Load plugin dependencies
 */
function dvsfw_run_delivery() {
	$dvsfw_use_order_notes = get_option( 'dvsfw_use_order_notes' );
	if ( empty( $dvsfw_use_order_notes ) ) {
		add_option( 'dvsfw_use_order_notes', 'yes' );
	}

	$plugin = new Delivery();
	$plugin->run();
}

/**
 * Check if WooCommerce is installed
 */
if ( in_array( 'woocommerce/woocommerce.php', get_option( 'active_plugins' ) ) ) {
	dvsfw_run_delivery();
} else {
	add_action( 'admin_notices', 'dvsfw_woo_error_notice' );
}

if ( get_option( 'dvsfw_dev_mode' ) === 'yes' ) {
	add_action( 'admin_notices', 'dvsfw_woo_test_mode_notice' );
}

/**
 * Load Text domain
 */
add_action( 'init', 'dvsfw_load_textdomain' );

function dvsfw_load_textdomain() {
	load_plugin_textdomain( 'wc-shipos-delivery', false, basename( dirname( __FILE__ ) ) . '/languages' );
}

/**
 * Show Error message if WooCommerce is not installed
 */
function dvsfw_woo_error_notice() {
	?>
    <div class="error notice">
        <p><?php esc_html_e( 'WooCommerce is not active. Please activate plugin before using Matat Delivery plugin.', 'wc-shipos-delivery' ); ?></p>
    </div>
	<?php
}

function dvsfw_woo_test_mode_notice() {
	?>
    <div class="error notice">
        <h3><?php esc_html_e( 'Warning! Shipos is currently running in test mode.', 'wc-shipos-delivery' ); ?></h3>
        <h4><a href="/wp-admin/admin.php?page=wc-settings&tab=settings_tab_shipos&show_dev_option=1">Change Settings</a>
        </h4>
    </div>
	<?php
}