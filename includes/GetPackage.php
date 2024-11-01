<?php

use WCShiposDelivery\WebService;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

function is_getpackage_enabled(): bool {

	if ( ! ( get_option( 'dvsfw_getpackage_enable' ) && get_option( 'dvsfw_getpackage_license' ) ) ) {
		return false;
	}

	$web_service = new WebService();
	$licenses    = $web_service->get_licenses();

	if ( is_wp_error( $licenses ) ) {
		return false;
	}

	$licenses = array_filter( $licenses, function ( $license ) {
		return ( $license->provider->name ?? null ) === 'GetPackage';
	} );

	if ( empty( $licenses ) ) {
		return false;
	}

	return true;
}

if ( ! is_getpackage_enabled() ) {
	return;
}


// Initialize GetPackage Shipping Method
add_action( 'woocommerce_shipping_init', 'dvsfw_getpackage_shipping_method_init' );
function dvsfw_getpackage_shipping_method_init() {
	if ( ! class_exists( 'WC_Shipping_Method' ) ) {
		return;
	}

	class WC_Shipping_Shipos_GetPackage_Delivery extends WC_Shipping_Method {

		/**
		 * Ignore discounts.
		 *
		 * If set, shipping discount would be available based on pre-discount order amount.
		 *
		 * @var string
		 */
		public string $ignore_discounts;

		public function __construct( $instance_id = 0 ) {

			parent::__construct( $instance_id );

			$this->id                 = 'shipos_getpackage';
			$this->method_title       = __( 'Shipos GetPackage', 'wc-shipos-delivery' );
			$this->method_description = __( 'Shipos GetPackage', 'wc-shipos-delivery' );
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->init();
		}

		public function init() {
			$this->init_form_fields();
			$this->title            = $this->get_option( 'title' );
			$this->enabled          = $this->get_option( 'enabled' );
			$this->ignore_discounts = $this->get_option( 'ignore_discounts' );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->instance_form_fields = array(
				'title'                   => array(
					'title'       => __( 'Shipos GetPackage Delivery', 'wc-shipos-delivery' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'wc-shipos-delivery' ),
					'default'     => __( 'Shipos GetPackage Delivery', 'wc-shipos-delivery' ),
					'desc_tip'    => true,
				),
				'cost'                    => array(
					'title'       => __( 'Cost', 'wc-shipos-delivery' ),
					'type'        => 'number',
					'description' => __( 'Enter delivery cost', 'wc-shipos-delivery' ),
					'default'     => '0',
					'desc_tip'    => true,
				),
				'discount_cost'           => array(
					'title'       => __( 'Discount cost', 'wc-shipos-delivery' ),
					'type'        => 'number',
					'description' => __( 'Enter discount cost', 'wc-shipos-delivery' ),
					'default'     => 0,
					'desc_tip'    => true,
				),
				'discount_cost_condition' => array(
					'title'       => __( 'Discount cost condition', 'wc-shipos-delivery' ),
					'type'        => 'number',
					'description' => __( 'Changing shipping method price if the order amount is higher than...', 'wc-shipos-delivery' ),
					'default'     => 0,
					'desc_tip'    => true,
				),
				'ignore_discounts'        => array(
					'title'       => __( 'Coupons discounts', 'wc-shipos-delivery' ),
					'label'       => __( 'Apply minimum order rule before coupon discount', 'wc-shipos-delivery' ),
					'type'        => 'checkbox',
					'description' => __( 'If checked, free shipping would be available based on pre-discount order amount.', 'wc-shipos-delivery' ),
					'default'     => 'no',
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * calculate_shipping function.
		 *
		 * @param array $package (default: array())
		 */
		public function calculate_shipping( $package = array() ) {

			// if discount cost and discount cost condition are 0, then use the default cost.
			if ( (float) $this->instance_settings['discount_cost'] == 0 && (float) $this->instance_settings['discount_cost_condition'] == 0 ) {
				$cost = $this->instance_settings['cost'];
			} else {
				global $woocommerce;
				$cost  = $this->instance_settings['cost'];
				$total = $woocommerce->cart->get_displayed_subtotal();

				if ( $this->ignore_discounts === 'no' ) {
					$total = $total - WC()->cart->get_discount_total();
					if ( WC()->cart->display_prices_including_tax() ) {
						$total = $total - WC()->cart->get_discount_tax();
					}
				}

				if ( $total > $this->instance_settings['discount_cost_condition'] ) {
					$cost = $this->instance_settings['discount_cost'];
				}
			}

			$this->add_rate(
				array(
					'id'    => $this->get_rate_id(),
					'label' => $this->title,
					'cost'  => $cost,
				)
			);

		}
	}
}

// Add GetPackage Shipping Method
add_filter( 'woocommerce_shipping_methods', 'dvsfw_add_getpackage_shipping_method' );
function dvsfw_add_getpackage_shipping_method( $methods ) {
	$methods['shipos_getpackage'] = 'WC_Shipping_Shipos_GetPackage_Delivery';

	return $methods;
}

// Show/Hide GetPackage Method based on checkout fields
add_filter( 'woocommerce_package_rates', 'dvsfw_enable_getpackage_by_address', 10, 2 );
function dvsfw_enable_getpackage_by_address( $rates, $package ) {

	// First we disable the get_package rate
	$original_rates = $rates; // This is the $rates array containing the get_package rate
	foreach ( $rates as $rate_id => $rate ) {
		if ( $rate->method_id === 'shipos_getpackage' ) {
			unset( $rates[ $rate_id ] ); // Remove the get_package rate
		}
	}

	// Collect values to check whether get_package will be available to use
	$checkout = WC()->checkout();
	$data     = [
		"city"          => $checkout->get_value( 'shipping_city' ),
		"street"        => $checkout->get_value( 'shipping_address_1' ),
		"street_number" => $checkout->get_value( 'shipping_address_2' ),
		"name"          => $checkout->get_value( 'shipping_first_name' ) . ' ' . $checkout->get_value( 'shipping_last_name' ),
		"phone"         => $checkout->get_value( 'shipping_phone' ) ?: $checkout->get_value( 'billing_phone' ),
		"date"          => date( 'Y-m-d' ),
		"package_size"  => 'MEDIUM'
	];

	// Pass each value through a filter
	foreach ( $data as $key => $value ) {
		$data[ $key ] = apply_filters( "dvsfw_getpackage_shipping_$key", $value );
	}

	// If any of the data has empty value, we show rates without get_package
	if ( count( $data ) !== count( array_filter( $data ) ) ) {
		return $rates;
	}

	// Check if same day delivery through get_package is available for the address
	$quote = dvsfw_get_getpackage_quote( $data );
	if ( isset( $quote->totalRate ) && isset( $quote->taxRate ) ) {
		$totalRate = (float ) $quote->totalRate;
		$taxRate   = (float ) $quote->taxRate;
		if ( $totalRate + $taxRate <= 40 ) {
			return $original_rates; // Show rates with get_package
		}
	}

	// Show rates without get_package
	return $rates;
}

function dvsfw_get_getpackage_quote( array $data ) {
	$license_key = get_option( 'dvsfw_getpackage_license' );
	$web_service = new WebService();


	return $web_service->matat_same_day_delivery_quote( $data, $license_key );
}