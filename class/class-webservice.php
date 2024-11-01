<?php

namespace WCShiposDelivery;

/**
 * Class WebService
 * This is used to communicate with Shipos API
 */
class WebService {


	private $matat_login = array();

	public function __construct() {
		$this->dvsfw_prepare_data();
	}

	/**
	 * Prepare all the data from options
	 *
	 * @return void
	 */
	private function dvsfw_prepare_data() {
		$this->matat_login['url']             = License::getInstance()->get_shipos_url();
		$this->matat_login['license_key']     = trim( get_option( 'wc-shipos-delivery' )['dvsfw_license_key'] ?? '' );
		$this->matat_login['domain']          = wp_parse_url( home_url() )['host'];
		$this->matat_login['dvsfw_automatic'] = get_option( 'dvsfw_automatic' );
	}

	/**
	 * Open CURL with Matat API
	 *
	 * @param $data
	 * @param $matat_func
	 *
	 * @return mixed
	 * @throws \Exception
	 */
	private function dvsfw_shipos_api_connection( $data, $matat_func, $license_key = null, $method = 'POST' ) {
		$url                 = $this->matat_login['url'] . $matat_func;
		$data['license_key'] = $license_key ?: $this->matat_login['license_key'];
		$data['domain']      = $this->matat_login['domain'];
		$data['token']       = get_option( 'dvsfw_shipos_token' );
		$data['version']     = DVSFW_PLUGIN_VERSION;
		$headers             = array( 'accept' => 'application/json' );
		if ( $data['token'] ) {
			$headers['Authorization'] = 'Bearer ' . $data['token'];
		}

		$sslverify = defined( 'DVSFW_DISABLE_SSL_VERIFY' ) ? DVSFW_DISABLE_SSL_VERIFY : true;

		$response = wp_remote_post(
			$url,
			array(
				'method'      => $method,
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => $data,
				'cookies'     => array(),
				'sslverify'   => $sslverify
			)
		);

//		ray( $response );

		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Get the package status by id
	 *
	 * @param $shipping_id
	 *
	 * @return \stdClass
	 */
	public function get_ship_status( $shipping_id, $license_key = null ) {
		try {
			return $this->dvsfw_shipos_api_connection( array( 'shipping_id' => $shipping_id ), DVSFW_GET_BY_ID, $license_key );
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			wp_die();
		}
	}

	/**
	 * Create Shipment
	 *
	 * @param $ship_data
	 *
	 * @return \stdClass
	 */
	public function create_ship( $ship_data, $order, $order_items, $shipping_lines, $licenseKey ) {
		$data_send['ship_data']               = $ship_data;
		$data_send['order']                   = $order;
		$data_send['order']['shipping_lines'] = $shipping_lines;
		$data_send['order_items']             = $order_items;

		try {
			return $this->dvsfw_shipos_api_connection( $data_send, DVSFW_SAVE_NEW, $licenseKey );
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			// display error message on admin.
			add_action(
				'admin_notices',
				function () use ( $e ) {
					?>
                    <div class="notice notice-error is-dismissible">
                        <p><?php esc_html_e( $e->getMessage(), 'wc-shipos-delivery' ); ?></p>
                    </div>
					<?php
				}
			);
		}
	}

	/**
	 * Change shipment status
	 *
	 * @param $shipping_id
	 *
	 * @return \stdClass
	 */
	public function change_ship_status( $shipping_id, $license_key = null ) {
		try {
			return $this->dvsfw_shipos_api_connection( array( 'shipping_id' => $shipping_id ), DVSFW_CHANGE_STATUS, $license_key );
		} catch ( \Exception $e ) {
			error_log( $e->getMessage() );
			wp_die();
		}
	}

	/**
	 * Send post request to API for the shipment label url
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function matat_label_url( $delivery_number ) {
		$data['shipping_id'] = $delivery_number;

		$response = $this->dvsfw_shipos_api_connection( $data, DVSFW_GET_SHIPPING_LABEL );

		return $response->data->url ?? '';
	}

	/**
	 * Send post request to API for the shipment bulk label
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function matat_bulk_label_url( $shipping_codes ) {
		$data['shipping_codes'] = $shipping_codes;

		$response = $this->dvsfw_shipos_api_connection( $data, DVSFW_GET_BULK_SHIPPING_LABEL );

		return $response->data->url ?? '';
	}

	/**
	 * Send post request to API for the pickup locations
	 *
	 * @param $data
	 *
	 * @return mixed
	 */
	public function matat_pickup_locations() {

		$response = $this->dvsfw_shipos_api_connection( null, DVSFW_GET_PICKUP_LOCATIONS );

		return $response->data->locations ?? null;
	}

	/**
	 * Send post request to API for the pickup locations
	 *
	 * @return mixed
	 */
	public function matat_same_day_delivery_quote( $data = null, $license_key = null ) {

		$response = $this->dvsfw_shipos_api_connection( $data, DVSFW_GET_SAME_DAY_DELIVERY_QUOTE, $license_key, 'GET' );

		return $response->data ?? null;
	}

	public function get_shipment_data_from_api( $order_id ): array {
		$data['ids'] = [ $order_id ];
		$response    = $this->dvsfw_shipos_api_connection( $data, DVSFW_GET_SHIPMENT_BY_ORDERR );

		return (array) $response;
	}

	public function get_licenses() {

		try {
			$transient_name = 'DVSFW_GET_LICENSES';
			$transient_data = get_transient( $transient_name );

			if ( $transient_data !== false ) {
				return $transient_data;
			}

			$url_parts = wp_parse_url( home_url() );
			$domain    = $url_parts['host'];
			$response  = $this->dvsfw_shipos_api_connection( [ 'domain' => $domain ], DVSFW_GET_LICENSES );
			if ( $response->success ?? false ) {
				set_transient( $transient_name, (array) $response->data, 15 * MINUTE_IN_SECONDS );

				return (array) $response->data;
			}

			set_transient( $transient_name, [], MINUTE_IN_SECONDS );

			return new \WP_Error( 'licenses_fetch_error', $response->message ?? 'Unknown error' );
		} catch ( \Exception $e ) {
			error_log( "(Shipos) Error when fetching licenses: " . $e->getMessage() );

			return new \WP_Error( 'licenses_fetch_error', $e->getMessage() );

		}
	}
}


