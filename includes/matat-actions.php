<?php


use WCShiposDelivery\Ajax;
use WCShiposDelivery\Utils;
use WCShiposDelivery\WebService;

if ( get_option( 'dvsfw_automatic_status' ) ) {

	function dvsfw_woocommerce_new_order( $order_id ) {

		$order = wc_get_order( $order_id );

		$_order             = $order->get_shipping_methods();
		$shipping           = @array_shift( $_order );
		$shipping_method_id = $shipping['method_id'];

		$matat_order_meta = Utils::get_order_meta( $order, '_dvsfw_ship_data', false );

		if ( ! $matat_order_meta ) {
			if ( $shipping_method_id != 'local_pickup' ) {
				$matat_web_service = new Ajax();
				$matat_web_service->matat_open_new_order( $order_id );
			}
		}

	}

	$automatic_status = 'woocommerce_order_status_' . str_replace( 'wc-', '', get_option( 'dvsfw_automatic_status' ) );
	add_action( $automatic_status, 'dvsfw_woocommerce_new_order' );
}

/**
 * @param $bulk_array
 *
 * @return mixed
 * Add new bulk edit to shop order
 */
function dvsfw_order_bulk_actions( $bulk_array ) {

	$bulk_array['shipos_bulk']       = __( 'Ship OS - create bulk shipping', 'wc-shipos-delivery' );
	$bulk_array['shipos_bulk_label'] = __( 'Create shipos labels', 'wc-shipos-delivery' );

	return $bulk_array;

}

add_filter( 'bulk_actions-edit-shop_order', 'dvsfw_order_bulk_actions' );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'dvsfw_order_bulk_actions' );

/**
 * @param $redirect
 * @param $doaction
 * @param $object_ids
 *
 * @return string
 * Add logic to create bulk edit
 */

function dvsfw_bulk_action_handler( $redirect, $doaction, $object_ids ) {
	if ( $doaction !== 'shipos_bulk' ) {
		return $redirect;
	}

	$query = http_build_query( [
		"page"             => "deliver-via-shipos",
		"dvsfw_select_ids" => $object_ids,
		'dvsfw_action'     => 'bulk_ship'
	] );

	$url = admin_url( 'admin.php?' . $query );

	wp_redirect( $url );
	exit();
}

add_filter( 'handle_bulk_actions-edit-shop_order', 'dvsfw_bulk_action_handler', 10, 3 );
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'dvsfw_bulk_action_handler', 10, 3 );

function dvsfw_bulk_label_handler( $redirect, $action, $object_ids ) {
	if ( $action !== 'shipos_bulk_label' ) {
		return $redirect;
	}

	$matat_web_service = new WebService();

	$errors         = array();
	$shipping_codes = array();
	foreach ( $object_ids as $order_id ) {

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			continue;
		}

		$matat_shipments = Utils::get_order_meta( $order, '_dvsfw_ship_data', false );

		if ( empty( $matat_shipments ) ) {
			continue;
		}

		$last = (array) $matat_shipments[ array_key_last( $matat_shipments ) ];

		$shipping_code = $last['delivery_number'] ?? null;

		if ( ! $shipping_code ) {
			continue;
		}

		$shipping_codes[] = $shipping_code;
	}

	if ( empty( $shipping_codes ) ) {
		$errors[] = __( 'Error: None of the selected orders have shipments.', 'wc-shipos-delivery' );
	} elseif ( count( $shipping_codes ) > 100 ) {
		$errors[] = __( 'Error: No of selected shipments have exceeded max amount of 100.', 'wc-shipos-delivery' );
	} else {
		$url = $matat_web_service->matat_bulk_label_url( $shipping_codes );
		if ( $url ) {
			return add_query_arg( 'dvsfw_bulk_label_url', urlencode( $url ), $redirect );
		}
	}

	return add_query_arg( 'dvsfw_bulk_labels', $errors, $redirect );
}

add_filter( 'handle_bulk_actions-edit-shop_order', 'dvsfw_bulk_label_handler', 10, 3 );
add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', 'dvsfw_bulk_label_handler', 10, 3 );

add_action( 'admin_notices', 'dvsfw_bulk_action_error_notices' );

function dvsfw_bulk_action_error_notices() {
	if ( isset( $_GET['dvsfw_bulk_done'] ) && ! empty( $_GET['dvsfw_bulk_done'] ) ) {
		$class = 'notice notice-error';

		// sanitize the array
		$bulk_order_ids = array_map( 'sanitize_text_field', $_GET['dvsfw_bulk_done'] );

		foreach ( $bulk_order_ids as $order_id => $error ) {
			$message = sprintf(
			/* translators: %d: WooCommerce Order id, %s: Error message */
				__( 'There is an error with order id (%1$d): %2$s', 'wc-shipos-delivery' ),
				esc_html( $order_id ),
				esc_html( $error )
			);
			echo wp_kses_post( sprintf( '<div class="%1$s"><p>%2$s</p></div><br>', esc_attr( $class ), esc_html( $message ) ) );
		}
	}
}

add_action( 'admin_notices', 'dvsfw_bulk_label_error_notices' );

function dvsfw_bulk_action_url_handler() {
	$url = isset( $_GET['dvsfw_bulk_label_url'] ) ? $_GET['dvsfw_bulk_label_url'] : null;
	if ( $url ) {
		echo "
		<script>
            window.open('" . $url . "', '_blank', 'noreferrer');
		</script>
		";
	}
}

add_action( 'admin_footer', 'dvsfw_bulk_action_url_handler' );

function dvsfw_bulk_label_error_notices() {
	if ( ! empty( $_GET['dvsfw_bulk_labels'] ) ) {
		$class = 'notice notice-error';

		// sanitize the array
		$errors = array_map( 'sanitize_text_field', $_GET['dvsfw_bulk_labels'] );

		foreach ( $errors as $error ) {
			echo wp_kses_post( sprintf( '<div class="%1$s"><p>%2$s</p></div><br>', esc_attr( $class ), esc_html( $error ) ) );
		}
	}
}

/**
 * Return notice after bulf edit
 */
function dvsfw_bulk_action_notices() {

	$li = '';
	if ( isset( $_REQUEST['dvsfw_bulk_done']['shipNumber'] ) ) {
		$ship_numbers = array_map( 'sanitize_text_field', $_REQUEST['dvsfw_bulk_done']['shipNumber'] );
		foreach ( $ship_numbers as $shipping ) {
			/* translators: %s: Shipping */
			$li .= '<li>' . sprintf( __( 'Shipping: %s', 'wc-shipos-delivery' ), esc_html( $shipping ) ) . '</li>';
		}

		// first of all we have to make a message,
		// of course it could be just "Posts updated." like this:
		if ( ! empty( $_REQUEST['dvsfw_bulk_done'] ) ) {
			$total = sanitize_text_field( $_REQUEST['dvsfw_bulk_done']['total'] );
			// echo intval($_REQUEST['dvsfw_bulk_done']);

			$output = '<div id="message" class="updated notice is-dismissible"><p>';
			$output .= sprintf(
			/* translators: %s: Total */
				__( '%s Sent', 'wc-shipos-delivery' ),
				esc_html( $total )
			);
			$output .= '</p><ul>';
			$output .= $li;

			echo wp_kses_post( $output );
		}
	}

}

add_action( 'admin_notices', 'dvsfw_bulk_action_notices' );





