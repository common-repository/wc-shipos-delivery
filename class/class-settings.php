<?php
/**
 * @package Deliver via Shipos for WooCommerce
 * @subpackage Deliver via Shipos for WooCommerce/admin
 * @since 1.0.0
 * @version 1.0.2
 */

namespace WCShiposDelivery;

use WCShiposDelivery\License;

class Settings {


	private static string $notice;

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 */
	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_settings_tab_shipos', array( __CLASS__, 'settings_tab' ) );
		add_action( 'woocommerce_update_options_settings_tab_shipos', array( __CLASS__, 'update_settings' ) );
		add_filter( 'woocommerce_sections_settings_tab_shipos', array( __CLASS__, 'getpackage_section' ) );

		// add settings
		$plugin_base_file = dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/wc-shipos-delivery.php';
		add_filter( 'plugin_action_links_' . $plugin_base_file, array( __CLASS__, 'shipos_plugin_settings' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option_dvsfw_license_key', array(
			__CLASS__,
			'check_posted_license'
		), 10, 3 );
	}

	public static function shipos_plugin_settings( $settings ) {

		$settings[] = '<a href="' . get_admin_url( '', 'admin.php?page=wc-settings&tab=settings_tab_shipos' ) . '">' . esc_html__( 'Settings', 'wc-shipos-delivery' ) . '</a>';

		return $settings;
	}

	public static function getpackage_section() {
		global $current_section;

		$sections = array(
			''                       => __( 'General', 'wc-shipos-delivery' ),
			'getpackage_integration' => __( 'GetPackage Integration', 'wc-shipos-delivery' ),
		);

		echo '<ul class="subsubsub">';

		foreach ( $sections as $id => $label ) {
			$url = add_query_arg(
				array(
					'page'    => 'wc-settings',
					'tab'     => 'settings_tab_shipos',
					'section' => $id,
				),
				admin_url( 'admin.php' )
			);

			$current   = $current_section == $id ? 'class="current"' : '';
			$separator = array_key_last( $sections ) === $id ? "" : " | ";
			echo "<li><a href=\"$url\" $current>$label</a>$separator </li>";
		}

		echo '</ul><br class="clear" />';
	}

	/**
	 * Add a new settings tab to the WooCommerce settings tabs array.
	 *
	 * @param array $settings_tabs Array of WooCommerce setting tabs & their labels, excluding the Subscription tab.
	 *
	 * @return array $settings_tabs Array of WooCommerce setting tabs & their labels, including the Subscription tab.
	 */
	public static function add_settings_tab( array $settings_tabs ): array {
		$settings_tabs['settings_tab_shipos'] = esc_html__( 'Shipos Delivery', 'wc-shipos-delivery' );

		return $settings_tabs;
	}

	/**
	 * Uses the WooCommerce admin fields API to output settings via the @see woocommerce_admin_fields() function.
	 *
	 * @uses woocommerce_admin_fields()
	 * @uses self::get_settings()
	 */
	public static function settings_tab() {

		woocommerce_admin_fields( self::get_settings() );
	}

	/**
	 * Uses the WooCommerce options API to save settings via the @see woocommerce_update_options() function.
	 *
	 * @uses woocommerce_update_options()
	 * @uses self::get_settings()
	 */
	public static function update_settings() {
		// set default url for dev/prod mode
		if ( ! empty( $_POST['dvsfw_dev_mode'] ) ) {
			update_option( 'dvsfw_dev_mode', 'yes' );
		} else {
			update_option( 'dvsfw_dev_mode', 'no' );
		}

		woocommerce_update_options( self::get_settings() );
	}

	/**
	 * Get all the settings for this plugin for @return array Array of settings for @see woocommerce_admin_fields() function.
	 *
	 * @see woocommerce_admin_fields() function.
	 */
	public static function get_settings(): array {

		$section = $_GET['section'] ?? "";

		if ( $section === 'getpackage_integration' ) {
			$web_service = new WebService();
			$licenses    = $web_service->get_licenses();

			if ( is_wp_error( $licenses ) ) {
				echo __( "Unable to fetch licenses", 'wc-shipos-delivery' );

				return [];
			}

			$licenses = array_filter( $licenses, function ( $license ) {
				return ( $license->provider->name ?? null ) === 'GetPackage';
			} );

			if ( empty( $licenses ) ) {
				echo __( "You do not have any licenses supporting GetPackage", 'wc-shipos-delivery' );

				return [];
			}

			/*echo "<pre>";
			print_r(json_encode($licenses, JSON_PRETTY_PRINT));
			echo "</pre>";*/

			$options = [];
			foreach ( $licenses as $license ) {
				$options[ $license->key ] = $license->company;
			}

			$settings = [
				'section_title'            => [
					'name' => __( 'Shipos GetPackage Integration', 'wc-shipos-delivery' ),
					'type' => 'title',
					'id'   => 'wc_settings_tab_getpackage_integration_section_title',
				],
				'dvsfw_getpackage_enable'  => [
					'name' => __( "Enable", 'wc-shipos-delivery' ),
					'type' => 'checkbox',
					'desc' => __( 'Enable same day delivery shipping method using GetPackage', 'wc-shipos-delivery' ),
					'id'   => 'dvsfw_getpackage_enable',
				],
				'dvsfw_getpackage_license' => [
					'name'    => __( "Select company", 'wc-shipos-delivery' ),
					'type'    => 'select',
					'options' => $options,
					'id'      => 'dvsfw_getpackage_license',
				]
			];

			$settings['section_end'] = array(
				'type' => 'sectionend',
				'desc' => '',
				'id'   => 'wc_settings_tab_getpackage_integration_section_end',
			);
		} else {
			$settings = static::get_general_settings();
		}

		return apply_filters( 'wc_settings_tab_shipos_settings', $settings );
	}

	/**
	 * Check license and activate/deactivate license if license field is dirty
	 *
	 * @param $value
	 * @param $option
	 * @param $raw_value
	 *
	 * @return mixed
	 */
	public static function check_posted_license( $value, $option, $raw_value ) {
		// check license on update
		$license = License::getInstance();
		// if empty field passed deactivate the license
		if ( '' == $value || empty( $value ) ) {
			self::$notice = '<div id="message" class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please fill the license field and start shipping.', 'wc-shipos-delivery' ) . '</p></div>';
			add_action( 'admin_notices', array( __CLASS__, 'dvsfw_settings_admin_notice' ) );
			$license->deactivate_license();

			return $value;
		} // if license key field is dirty
		elseif ( ! get_option( 'dvsfw_license_key' ) || $value != get_option( 'dvsfw_license_key' ) ) {
			$api_response = $license->check_license( 'activate_license', $value );
			$license_data = $api_response['license_data'];

			if ( $license_data->license !== 'valid' ) {
				$license->deactivate_license();
				if ( $license_data->message ) {
					self::$notice = '<div id="message" class="notice notice-error is-dismissible"><p>' . wp_kses_post( $license_data->message ) . '</p></div>';
					add_action( 'admin_notices', array( __CLASS__, 'dvsfw_settings_admin_notice' ) );
				}
			} else {
				$message = $license->shipos_activate_license();
				if ( $message ) {
					self::$notice = '<div id="message" class="notice notice-error is-dismissible"><p>' . wp_kses_post( $message ) . '</p></div>';
					add_action( 'admin_notices', array( __CLASS__, 'dvsfw_settings_admin_notice' ) );
				} else {
					self::$notice = '<div id="message" class="notice notice-success is-dismissible"><p>' . esc_html__( 'License successfully activated', 'wc-shipos-delivery' ) . '</p></div>';
					add_action( 'admin_notices', array( __CLASS__, 'dvsfw_settings_admin_notice' ) );
				}
			}
		}

		return $value;
	}

	public static function dvsfw_settings_admin_notice() {
		echo wp_kses_post( self::$notice );
	}

	public static function get_general_settings(): array {
		$is_pickup = '';
		if ( get_option( 'dvsfw_is_pickup' ) == 'yes' ) {
			$is_pickup = "<button class='button-primary sync_pickup'>" . esc_html__( 'Sync pickup location', 'wc-shipos-delivery' ) . '</button>';
		}

		$settings = array(
			'section_title'                         => array(
				'name' => esc_html__( 'Shipos option settings', 'wc-shipos-delivery' ),
				'type' => 'title',
				'desc' => $is_pickup,
				'id'   => 'wc_settings_tab_shipos_section_title',
			),
			'dvsfw_license_key'                     => array(
				'name'  => esc_html__( 'License Key', 'wc-shipos-delivery' ),
				'type'  => 'text',
				'class' => 'matat-blur-on-lose-focus',
				'id'    => 'dvsfw_license_key',
			),
			'dvsfw_automatic_status'                => array(
				'name'    => esc_html__( 'When do you want to send Shipment?', 'wc-shipos-delivery' ),
				'type'    => 'select',
				'options' => array_merge( array( '0' => esc_html__( 'Manually by clicking', 'wc-shipos-delivery' ) ), wc_get_order_statuses() ),
				'id'      => 'dvsfw_automatic_status',
			),
			'dvsfw_use_order_notes'                 => array(
				'name' => esc_html__( 'Use order notes?', 'wc-shipos-delivery' ),
				'type' => 'checkbox',
				'desc' => esc_html__( 'Use order notes if order comment field is empty', 'wc-shipos-delivery' ),
				'id'   => 'dvsfw_use_order_notes',
			),
			'dvsfw_is_pickup'                       => array(
				'name' => esc_html__( 'Activate collection points?', 'wc-shipos-delivery' ),
				'type' => 'checkbox',
				'desc' => esc_html__( 'Not supported by all courier companies', 'wc-shipos-delivery' ),
				'id'   => 'dvsfw_is_pickup',
			),
			'dvsfw_google_maps_api_key'             => array(
				'name' => esc_html__( 'Google Maps API Key', 'wc-shipos-delivery' ),
				'type' => 'password',
				'id'   => 'dvsfw_google_maps_api_key',
			),
			'dvsfw_pickup_point_display_preference' => array(
				'name'    => esc_html__( 'Pickup point display preference', 'wc-shipos-delivery' ),
				'type'    => 'select',
				'options' => array(
					'manual' => esc_html__( 'Selection from a list', 'wc-shipos-delivery' ),
					'map'    => esc_html__( 'Google Map', 'wc-shipos-delivery' ),
					'both'   => esc_html__( 'Both list and Google Map', 'wc-shipos-delivery' ),
				),
				'id'      => 'dvsfw_pickup_point_display_preference',
			),
			'dvsfw_pickup_point_default_display'    => array(
				'name'    => esc_html__( 'Default Pickup point display', 'wc-shipos-delivery' ),
				'type'    => 'radio',
				'options' => array(
					'map'    => esc_html__( 'Google Map', 'wc-shipos-delivery' ),
					'manual' => esc_html__( 'List', 'wc-shipos-delivery' ),
				),
				'id'      => 'dvsfw_pickup_point_default_display',
				'default' => 'manual',
			),
			'dvsfw_free_shipping_by_price'          => array(
				'name'    => esc_html__( 'Apply Free Shipping By Price', 'wc-shipos-delivery' ),
				'type'    => 'select',
				'options' => array(
					'after_discount'  => esc_html__( 'After Discount', 'wc-shipos-delivery' ),
					'before_discount' => esc_html__( 'Before Discount', 'wc-shipos-delivery' ),
				),
				'id'      => 'dvsfw_free_shipping_by_price',
			),
			'dvsfw_phone_no_field_key'              => array(
				'name' => esc_html__( 'Phone Number Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_phone_no_field_key',
			),
			'dvsfw_house_no_field_key'              => array(
				'name' => esc_html__( 'House Number Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_house_no_field_key',
			),
			'dvsfw_apartment_field_key'             => array(
				'name' => esc_html__( 'Apartment Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_apartment_field_key',
			),
			'dvsfw_floor_field_key'                 => array(
				'name' => esc_html__( 'Floor Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_floor_field_key',
			),
			'dvsfw_entrance_field_key'              => array(
				'name' => esc_html__( 'Entrance Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_entrance_field_key',
			),
			'dvsfw_order_comment_field_key'         => array(
				'name' => esc_html__( 'Order Comment Field Key', 'wc-shipos-delivery' ),
				'type' => 'text',
				'id'   => 'dvsfw_order_comment_field_key',
			),
		);

		if ( isset( $_GET['show_dev_option'] ) && $_GET['show_dev_option'] === '1' ) {
			$settings['dvsfw_dev_mode'] = array(
				'name' => esc_html__( 'Test Mode', 'wc-shipos-delivery' ),
				'type' => 'checkbox',
				'desc' => esc_html__( 'Run the shipment for testing  ?', 'wc-shipos-delivery' ),
				'id'   => 'dvsfw_dev_mode',
			);
		}

		$settings['section_end'] = array(
			'type' => 'sectionend',
			'desc' => '',
			'id'   => 'wc_settings_tab_shipos_section_end',
		);

		return $settings;
	}
}

Settings::init();
