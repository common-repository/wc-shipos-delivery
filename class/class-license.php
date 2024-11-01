<?php

namespace WCShiposDelivery;

class License {


	private static $instance;

	private $slug;

	private $options = array();

	public string $license_url;
	public string $testmode;


	public static function getInstance() {
		if ( ! self::$instance ) {
			self::$instance = new static();
		}

		return self::$instance;
	}

	public static function __callStatic( $name, $arguments ) {
		if ( $name == 'render_menu' ) {
			self::getInstance()->shipos_license_page();
		}
	}

	private function __construct() {
		$this->slug = 'wc-shipos-delivery';

		$this->options = get_option( $this->slug );

		$this->get_shipos_url();

	}

	public function get_shipos_url(): string {
		$this->testmode = get_option( 'dvsfw_dev_mode' ) ?? false;

		if ( $this->testmode == 'yes' ) {
			$this->license_url = 'https://stg.shipos.co.il/api/';
		} else {
			$this->license_url = 'https://app.shipos.co.il/api/';
		}

		// Override the license_url for local testing if defined (in wp-config.php)
		if ( defined( 'DVSFW_DEFAULT_URL' ) ) {
			$this->license_url = DVSFW_DEFAULT_URL;
		}

		return $this->license_url;
	}

	public function is_licensed() {
		return isset( $this->options['shipos_license_status'] ) && $this->options['shipos_license_status'] == 'valid';
	}

	public function shipos_activate_license( $license_key = null ) {
		$license = trim( sanitize_text_field( wp_unslash( $license_key ?: $_POST['dvsfw_license_key'] ) ) );

		$shipos_action = 'activate_license';

		$data         = $this->check_license( $shipos_action, $license );
		$message      = $data['message'];
		$license_data = $data['license_data'];

		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			return $message;
		}

		// $license_data->license will be either "valid" or "invalid"
		$this->options['dvsfw_license_key']     = $license;
		$this->options['shipos_license_status'] = $license_data->license;

		update_option( $this->slug, $this->options );
	}

	public function check_license( $shipos_action = 'check_license', $license = false ) {

		if ( ! $license ) {
			$license = $this->options['dvs'];
		}

		// data to send in our API request
		$api_params = array(
			'license_key' => $license,
			'domain'      => wp_parse_url( home_url() )['host'],
		);

		// Call the custom API.
		$response = wp_remote_get(
			$this->get_shipos_url() . 'license/' . $shipos_action,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
				'headers'   => array( 'accept' => 'application/json' ),
			)
		);

		$message = null;
		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			$message = ( is_wp_error( $response ) && ! empty( $response->get_error_message() ) ) ? $response->get_error_message() : __( 'An error occurred, please try again.', 'wc-shipos-delivery' );

		} else {

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {

				switch ( $license_data->error ) {

					case 'revoked':
						$message = __( 'Your license key has been disabled.', 'wc-shipos-delivery' );
						break;

					case 'missing':
						$message = __( 'Invalid license.', 'wc-shipos-delivery' );
						break;

					default:
						$message = __( 'An error occurred, please try again.', 'wc-shipos-delivery' );
						break;
				}
			}
		}

		return array(
			'license_data' => $license_data,
			'message'      => $message,
		);
	}

	public function deactivate_license() {
		unset( $this->options['shipos_license_status'] );
		update_option( $this->slug, $this->options );
	}

	public function get_licenses() {
		$web_service = new WebService();
		$licenses    = $web_service->get_licenses();

		if ( is_wp_error( $licenses ) ) {
			return $licenses;
		}

		if ( $licenses and ! empty( $licenses ) ) {
			$this->options['licenses'] = $licenses;
		}

		return true;

	}
}
