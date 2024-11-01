<?php

namespace WCShiposDelivery;

use WC_Product_Variation;
use WCShiposDelivery\WebService;

/**
 * Class Ajax
 *
 * This is used to handle all ajax requests form admin meta box
 */
class Ajax {

	public $matat_web_service;

	public function __construct() {
		$this->matat_web_service = new WebService();
	}

	public function matat_sync_pickup_point() {

		dvsfw_get_location();

		wp_die();

	}

	/**
	 * Open new delivery and add it to postmeta
	 *
	 * @since 1.0.0
	 */
	public function matat_open_new_order( $automatic = null ) {
		if ( ! $automatic ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['dvsfw_wpnonce'] ) ), 'dvsfw_submit_open_ship' ) ) {
				wp_die( esc_html__( 'Failed security check', 'wc-shipos-delivery' ) );
			}
		}

		if ( ! $automatic ) {
			$order_id = absint( $_REQUEST['dvsfw_order_id'] );
		} else {
			$order_id = $automatic;

		}

		$order = wc_get_order( $order_id );
		$order->add_meta_data( 'dvsfw_shipment_create_start', date( 'Y-m-d H:i:s' ) );
		$order->save();

		$shipping_lines = [];
		foreach ( $order->get_items( 'shipping' ) as $item ) {
			$shipping_lines[] = [
				"id"           => $item->get_method_id(),
				"taxes"        => $item->get_taxes(),
				"total"        => $item->get_total(),
				"meta_data"    => [],
				"method_id"    => $item->get_method_id(),
				"total_tax"    => $item->get_total_tax(),
				"instance_id"  => $item->get_instance_id(),
				"method_title" => $item->get_method_title()
			];
		}


		$shipping_details = $order->get_address( 'shipping' );
		$billing_details  = $order->get_address( 'billing' );

		$note = Utils::get_order_meta( $order, get_option( 'dvsfw_order_comment_field_key' ) );
		if ( empty( $note ) && get_option( 'dvsfw_use_order_notes' ) === 'yes' ) {
			$note = $order->get_customer_note();
		}
		$note                     = apply_filters( 'dvsfw_customer_note', $note, $order_id );
		$custom_shipping_phone_no = Utils::get_order_meta( $order, get_option( 'dvsfw_phone_no_field_key' ) ) ?: ( $shipping_details['phone'] ?? null );


		$ship_data                   = array();
		$ship_data['street']         = $shipping_details['address_1'];
		$ship_data['number']         = $shipping_details['address_2'];
		$ship_data['city']           = $shipping_details['city'];
		$ship_data['company']        = $shipping_details['company'];
		$ship_data['note']           = $note ?: "";
		$ship_data['urgent']         = '1';
		$ship_data['pickup']         = Utils::get_order_meta( $order, 'shipos_delivery_address_id' );
		$ship_data['pickup_address'] = Utils::get_order_meta( $order, 'shipos_delivery_address' );
		$ship_data['house_no']       = Utils::get_order_meta( $order, get_option( 'dvsfw_house_no_field_key' ) );
		$ship_data['apartment']      = Utils::get_order_meta( $order, get_option( 'dvsfw_apartment_field_key' ) );
		$ship_data['floor']          = Utils::get_order_meta( $order, get_option( 'dvsfw_floor_field_key' ) );
		$ship_data['entrance']       = Utils::get_order_meta( $order, get_option( 'dvsfw_entrance_field_key' ) );
		$ship_data['type']           = $automatic ? '1' : absint( $_REQUEST['dvsfw_delivey_type'] );
		$ship_data['packages']       = $automatic ? '1' : absint( $_REQUEST['dvsfw_packages'] );
		$ship_data['return']         = $automatic ? '1' : ( isset( $_REQUEST['dvsfw_return'] ) ? absint( $_REQUEST['dvsfw_return'] ) : '1' );
		$ship_data['motor']          = '1';
		$ship_data['woo_id']         = $order_id;
		$ship_data['extra_note']     = '';
		$ship_data['contact_name']   = $shipping_details['first_name'] . ' ' . $shipping_details['last_name'];
		$ship_data['contact_phone']  = $custom_shipping_phone_no ?: $billing_details['phone'];
		$ship_data['contact_mail']   = $billing_details['email'];
		$ship_data['exaction_date']  = $automatic ? date( 'Y-m-d' ) : sanitize_text_field( wp_unslash( $_REQUEST['dvsfw_exaction_date'] ) );
		$ship_data['collect']        = '';
		$ship_data['delivery_time']  = date( 'd-m-Y g-i-s' );
		$licenseKey                  = $_REQUEST['dvsfw_license'] ?? null;

		$order_items = array();
		foreach ( $order->get_items() as $item ) {
			$product_id   = $item->get_data()['product_id'];
			$product      = wc_get_product( $product_id );
			$product_data = $product->get_data();

			$order_item = array_merge( $item->get_data(), $product_data );
			$order_item = array_intersect_key(
				$order_item,
				array_flip(
					array(
						'id',
						'name',
						'price',
						'quantity',
						'total',
						'sku',
					)
				)
			); // Get only these keys from the array $order_item

			$order_item['variation_id'] = $item->get_variation_id();
			if ( $product->is_type( 'variable' ) ) {
				$variation                    = new WC_Product_Variation( $order_item['variation_id'] );
				$order_item['variation_name'] = $variation->get_name();
				$order_item['sku']            = $variation->get_sku();
				$order_item['image']          = wp_get_attachment_url( $variation->get_image_id() );
			} else {
				$order_item['variation_name'] = $item->get_name();
				$order_item['image']          = wp_get_attachment_url( $product->get_image_id() );
			}

			$order_item['gallery_images'] = array_map(
				function ( $image_id ) {
					return wp_get_attachment_url( $image_id );
				},
				$product->get_gallery_image_ids()
			);

			$order_items[] = $order_item;
		}

		$response = $this->matat_web_service->create_ship( $ship_data, $order->get_data(), $order_items, $shipping_lines, $licenseKey );
		if ( ! $response->success ) {
			$order->delete_meta_data( 'dvsfw_shipment_create_start' );
			$order->save();
			$error_msg = $response->message ?? __( 'Error creating shipment', 'wc-shipos-delivery' );

			if ( ! $automatic ) {
				wp_send_json_error( $error_msg );
				wp_die();
			} else {
				$order->add_order_note( $error_msg );

				return [
					"data"    => $response,
					"message" => $error_msg,
					"success" => false
				];
			}
		}

		$ship_data['delivery_number'] = $response->data->code;
		$ship_data['company']         = $response->data->company;
		$ship_data['license_key']     = $licenseKey;
		if ( ! empty( Utils::get_order_meta( $order, '_dvsfw_ship_data', false ) ) ) {
			$order->update_meta_data( '_dvsfw_more_than_one', true );
		}
		$order->add_meta_data( '_dvsfw_ship_data', $ship_data );
		$order->add_meta_data( '_shipos_response', (array) $response );
		$order->save();

		$ship_type = '';
		if ( ! $automatic ) {
			if ( $ship_data['type'] == '1' ) {
				if ( absint( $ship_data['return'] ) == '2' ) {
					$ship_type = __( 'Double delivery', 'wc-shipos-delivery' );
				} else {
					$ship_type = __( 'Regular delivery', 'wc-shipos-delivery' );
				}
			} elseif ( $ship_data['type'] == '2' ) {
				$ship_type = __( 'Collecting delivery', 'wc-shipos-delivery' );
			}
		} else {

			$ship_type = __( 'Regular delivery', 'wc-shipos-delivery' );
		}

		$message = sprintf(
		/* translators: 1: Shipping Number, 2: Shipping Type */
			__( 'Shipping successfully created, shipping number: %1$s Shipping type: %2$s', 'wc-shipos-delivery' ),
			$response->data->code,
			$ship_type
		);

		$order->add_order_note( $message );
		$matat_order_meta = Utils::get_order_meta( $order_id, '_dvsfw_ship_data', false );
		$last             = array_key_last( $matat_order_meta );

		if ( ! $automatic ) {
			$response = [
				"data"        => $ship_data['delivery_number'],
				"shipping_id" => $last,
				"company"     => $ship_data['company']->name,
				"success"     => true
			];
			wp_send_json( $response, null, 0 );
		} else {
			return [
				"data"        => $ship_data['delivery_number'],
				"shipping_id" => $last,
				"message"     => $message,
				"success"     => true
			];
		}
	}

	/**
	 * Get Matat delivery status
	 *
	 * @since 1.0.0
	 */
	public function matat_get_order_details() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['matat_get_wpnonce'] ) ), 'dvsfw_submit_get_ship' ) ) {
			wp_die( esc_html__( 'Failed security check', 'wc-shipos-delivery' ) );

		}
		$order_id          = absint( $_REQUEST['dvsfw_order_id'] );
		$license_key       = sanitize_text_field( $_REQUEST['license_key'] ?? null );
		$matat_ship_status = $this->matat_web_service->get_ship_status( $order_id, $license_key );

		echo( wp_json_encode( $matat_ship_status->data ) );
		wp_die();
	}

	/**
	 * Change Matat delivery status
	 *
	 * @since 1.0.0
	 */
	public function matat_change_order_status() {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['matat_change_wpnonce'] ) ), 'dvsfw_submit_change_ship' ) ) {
			wp_die( esc_html__( 'Failed security check', 'wc-shipos-delivery' ) );
		}

		$ship_id     = sanitize_text_field( $_REQUEST['dvsfw_ship_id'] );
		$license_key = sanitize_text_field( $_REQUEST['dvsfw_license_key'] );
		$order_id    = absint( $_REQUEST['order_id'] );

		$matat_ship_status = $this->matat_web_service->change_ship_status( $ship_id, $license_key );

		if ( ! $matat_ship_status->success ) {
			wp_send_json_error( array( 'message' => $matat_ship_status->message ) );
			die();
		}

		$order = wc_get_order( $order_id );
		$order->add_order_note( $ship_id . __( ' Order canceled', 'wc-shipos-delivery' ) );
		$order->update_meta_data( '_order_canceled', $ship_id );
		$order->save();

		wp_send_json_success();
		die();

	}

	/**
	 * Reopen Ship
	 *
	 * @since 1.0.0
	 */
	public function dvsfw_reopen_ship() {
		if ( ! wp_verify_nonce( sanitize_text_field( $_REQUEST['dvsfw_reopen_wpnonce'] ), 'dvsfw_reopen_ship' ) ) {
			wp_die( esc_html__( 'Failed security check', 'wc-shipos-delivery' ) );
		}
		$order = wc_get_order( absint( $_REQUEST['dvsfw_woo_order_id'] ) );
		if ( $order ) {
			$order->delete_meta_data( '_dvsfw_ship_data' );
		}
		wp_die();
	}

	public function dvsfw_get_pickup_locations() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() ) {
			error_log( __( 'Failed to initialise WC_Filesystem API while trying to sync Shipos Cities.', 'wc-shipos-delivery' ) );

			return;
		}
		global $wp_filesystem;
		$upload_dir = wp_upload_dir();
		$upload_dir = $upload_dir['basedir'] . '/shipos-delivery';

		// create the folder if it doesn't exist
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}

		$filename = $upload_dir . '/pickup_locations.json';

		if ( file_exists( $filename ) ) {
			$message = __( 'Json file retrieved locally', 'wc-shipos-delivery' );

			$output = json_decode( $wp_filesystem->get_contents( $filename ) );

		} else {
			$message = __( 'Locations retrieved from server', 'wc-shipos-delivery' );
			$output  = $this->matat_web_service->matat_pickup_locations();
		}
		wp_send_json(
			array(
				'data'    => $output,
				'message' => $message,
			)
		);
		wp_die();
	}

	public function dvsfw_get_coordinates() {
		$location = sanitize_text_field( wp_unslash( $_POST['location'] ) );
		$api_key  = get_option( 'dvsfw_google_maps_api_key' );
		$request  = wp_remote_get( "https://maps.googleapis.com/maps/api/geocode/json?address=$location&key=$api_key" );

		if ( is_wp_error( $request ) ) {
			wp_send_json_error( array(), 500 );
			wp_die();
		}

		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( isset( $body['results'] ) && empty( $body['results'] ) ) {
			wp_send_json_error( $body );
		}

		$coordinates = $body['results'][0]['geometry']['location'] ?? null;

		wp_send_json_success(
			array(
				'data'    => array(
					'latitude'  => $coordinates['lat'] ?? null,
					'longitude' => $coordinates['lng'] ?? null,
				),
				'request' => $request,
			)
		);

		wp_die();
	}
}
