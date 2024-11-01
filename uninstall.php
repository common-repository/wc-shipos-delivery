<?php
/**
 * Uninstall script for Deliver via Shipos for WooCommerce plugin
 *
 * @package Deliver via Shipos for WooCommerce
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}


delete_option( 'dvsfw_automatic' );
delete_option( 'dvsfw_automatic_status' );
delete_option( 'dvsfw_license_key' );
