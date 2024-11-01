<?php

namespace WCShiposDelivery;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class RestRoutes {

	// Register additional REST API Routes
	public function register_rest_routes() {
		register_rest_route( 'wc/v3', '/order_shipment_created_meta', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_order_shipment_created_meta' ),
			'permission_callback' => array( $this, 'is_authenticated' ),
		) );

		register_rest_route( 'wc/v3', '/order_shipment_cancelled_meta', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_order_shipment_cancelled_meta' ),
			'permission_callback' => array( $this, 'is_authenticated' ),
		) );

		register_rest_route( 'wc/v3', '/shipos_settings', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_shipos_settings' ),
			'permission_callback' => array( $this, 'is_authenticated' ),
		) );

		register_rest_route( 'wc/v3', '/shipos_license_update', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'update_license_key' ),
			'permission_callback' => array( $this, 'is_authenticated' ),
		) );
	}

	/**
	 * Permission callback function for authentication
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool|WP_Error
	 */
	public function is_authenticated( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'You are not authenticated.', 'wc-shipos-delivery' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	public function get_shipos_settings( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$options = $wpdb->get_results( "SELECT * FROM $wpdb->options WHERE option_name LIKE 'dvsfw_%'" );

		if ( ! $options ) {
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Could not fetch options.',
			), 200 );
		}

		$settings = array();
		foreach ( $options as $option ) {
			$settings[ $option->option_name ] = $option->option_value;
		}

		$keys = [
			"dvsfw_apartment_field_key",
			"dvsfw_entrance_field_key",
			"dvsfw_floor_field_key",
			"dvsfw_house_no_field_key",
			"dvsfw_order_comment_field_key",
			"dvsfw_phone_no_field_key",
			"dvsfw_use_order_notes",
		];

		// Get only the keys from $keys variable
		$settings = array_intersect_key( $settings, array_flip( $keys ) );

		$data = array(
			'data'    => $settings,
			'status'  => 'success',
			'message' => 'Shipos settings fetched successfully.',
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function update_order_shipment_created_meta( WP_REST_Request $request ): WP_REST_Response {

		$order_id        = $request->get_param( 'order_id' );
		$meta_data       = $request->get_param( 'meta_data' );
		$shipos_response = $request->get_param( 'shipos_response' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Order not found.',
			), 200 );
		}

		if ( Utils::get_order_meta( $order, '_dvsfw_ship_data', false ) ) {
			$order->update_meta_data( '_dvsfw_more_than_one', true );
		}
		$order->add_meta_data( '_dvsfw_ship_data', $meta_data );
		$order->add_meta_data( '_shipos_response', $shipos_response );
		$order->save();

		do_action( 'dvsfw_shipping_created', $order, $meta_data, $shipos_response );

		$data = array(
			'status'  => 'success',
			'message' => 'Order meta updated successfully.',
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function update_order_shipment_cancelled_meta( WP_REST_Request $request ): WP_REST_Response {

		$order_id      = $request->get_param( 'order_id' );
		$shipping_code = $request->get_param( 'shipping_code' );

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_REST_Response( array(
				'status'  => 'error',
				'message' => 'Order not found.',
			), 200 );
		}

		$order->add_meta_data( '_order_canceled', $shipping_code );
		$order->save();

		do_action( 'dvsfw_shipping_cancelled', $order, $shipping_code );

		$data = array(
			'status'  => 'success',
			'message' => 'Order meta updated successfully.',
		);

		return new WP_REST_Response( $data, 200 );
	}

	public function update_license_key(): WP_REST_Response {
		$license = License::getInstance();
		$license->get_licenses();

		return new WP_REST_Response( array(
			'status' => 'success',
		), 200 );
	}

}