<?php

namespace WCShiposDelivery;

use WCShiposDelivery\WebService;
use WCShiposDelivery\Admin;
use WCShiposDelivery\Ajax;

/**
 * Class Delivery
 *
 * This is the plugin core class
 */
class Delivery {

	/**
	 * @var \WCShiposDelivery\Admin
	 */
	public $matat_admin_view;


	public $matat_admin_view_new;

	/**
	 * @var \WCShiposDelivery\Ajax
	 */
	public $matat_ajax;

	/**
	 * @var \WCShiposDelivery\RestRoutes
	 */
	public RestRoutes $matat_rest_routes;

	public function __construct() {
		$this->load_dependencies();
		$this->matat_admin_view     = new Admin();
		$this->matat_admin_view_new = new Admin();
		$this->matat_ajax           = new Ajax();
		$this->matat_rest_routes    = new RestRoutes();
	}

	/**
	 * Load dependencies
	 *
	 * @since 1.0.0
	 */
	private function load_dependencies() {
		// require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/matat-woocommerce-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/matat-actions.php';
		if ( get_option( 'dvsfw_is_pickup	' ) == 'yes' ) {
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/Pickup.php';
		}
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/free-shipping.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/GetPackage.php';
	}

	/**
	 * Set the admin WordPress hooks
	 *
	 * @since 1.0.0
	 */
	private function set_admin_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this->matat_admin_view, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->matat_admin_view, 'enqueue_scripts' ) );
		add_action( 'admin_menu', array( $this->matat_admin_view, 'add_plugin_menu_and_options' ) );
		add_action( 'rest_api_init', array( $this->matat_rest_routes, 'register_rest_routes' ) );
		add_action( 'admin_bar_menu', array( $this->matat_admin_view, 'admin_bar_item' ), 500 );
		add_action( 'init', array( $this, 'check_token_validity' ) );
		if ( License::getInstance()->is_licensed() ) {
			add_action( 'add_meta_boxes', array( $this->matat_admin_view, 'meta_boxes' ) );
			add_action( 'wp_ajax_matat_open_new_order', array( $this->matat_ajax, 'matat_open_new_order' ) );
			add_action( 'wp_ajax_matat_sync_pickup_point', array( $this->matat_ajax, 'matat_sync_pickup_point' ) );
			add_action( 'wp_ajax_matat_get_order_details', array( $this->matat_ajax, 'matat_get_order_details' ) );
			add_action( 'wp_ajax_matat_change_order_status', array( $this->matat_ajax, 'matat_change_order_status' ) );
			add_action( 'wp_ajax_dvsfw_reopen_ship', array( $this->matat_ajax, 'dvsfw_reopen_ship' ) );
			add_action( 'wp_ajax_dvsfw_get_pickup_locations', array(
				$this->matat_ajax,
				'dvsfw_get_pickup_locations'
			) );
			add_action( 'wp_ajax_nopriv_dvsfw_get_pickup_locations', array(
				$this->matat_ajax,
				'dvsfw_get_pickup_locations'
			) );
			add_action( 'wp_ajax_dvsfw_get_coordinates', array( $this->matat_ajax, 'dvsfw_get_coordinates' ) );
			add_action( 'wp_ajax_nopriv_dvsfw_get_coordinates', array( $this->matat_ajax, 'dvsfw_get_coordinates' ) );
			add_action( 'admin_init', array( $this, 'matat_plugins_loaded' ), 20 );
			add_action( 'manage_shop_order_posts_custom_column', array(
				$this->matat_admin_view,
				'matat_admin_column'
			), 3, 2 );
			add_action( 'woocommerce_shop_order_list_table_custom_column', array(
				$this->matat_admin_view_new,
				'matat_admin_column'
			), 3, 2 );
		}
	}

	/**
	 * Set the admin WordPress filters
	 *
	 * @since 1.1
	 */
	private function set_admin_filters() {
		add_filter( 'manage_shop_order_posts_columns', array(
			$this->matat_admin_view,
			'matat_admin_column_head'
		), 50 );
		add_filter( 'woocommerce_shop_order_list_table_columns', array(
			$this->matat_admin_view_new,
			'matat_admin_column_head'
		), 50 );
	}

	/**
	 * Init method after class is created
	 *
	 * @since 1.0.0
	 */
	public function run() {
		$this->set_admin_hooks();
		$this->set_admin_filters();
	}

	/**
	 * Listen to GET request for labels
	 *
	 * @since 1.0.0
	 */
	public function matat_plugins_loaded() {
		global $pagenow;

		if ( $pagenow == 'post.php' && isset( $_GET['matat_pdf'] ) && sanitize_text_field( wp_unslash( $_GET['matat_pdf'] ) ) == 'create' ) {
			if ( ! wp_verify_nonce( sanitize_text_field( $_GET['matat_label_wpnonce'] ), 'matat_create_label' ) ) {
				wp_die( esc_html__( 'Failed security check', 'wc-shipos-delivery' ) );
			}

			// get label url from matat matat and redirect

			$ship_id   = isset( $_GET['ship_id'] ) ? sanitize_text_field( wp_unslash( $_GET['ship_id'] ) ) : 0;
			$order     = wc_get_order( absint( wp_unslash( $_GET['order_id'] ) ) );
			$ship_data = Utils::get_order_meta( $order, '_dvsfw_ship_data', false );
			$shipos    = new WebService();
			$url       = $shipos->matat_label_url( ( (array) $ship_data[ $ship_id ] )['delivery_number'] ?? 0 );
			// TODO save the url in order meta if it's not empty
			do_action( 'dvsfw_before_print_label', $url, $ship_id, $order );
			if ( $url ) {
				wp_redirect( $url );
			}
			exit;
		}
	}

	public function check_token_validity() {
		$token = get_option( 'dvsfw_shipos_token' );
		if ( ! $token ) {
			return;
		}

		$transient_name = 'DVSFW_TOKEN_VALID';
		$transient_data = get_transient( $transient_name );

		if ( $transient_data ) {
			return;
		}

		$headers                  = array( 'accept' => 'application/json' );
		$headers['Authorization'] = 'Bearer ' . $token;
		$response                 = wp_remote_post(
			License::getInstance()->get_shipos_url() . DVSFW_GET_LICENSES,
			array(
				'method'      => 'POST',
				'timeout'     => 45,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,
				'headers'     => $headers,
				'body'        => [],
				'cookies'     => array(),
			)
		);
		$response_code            = wp_remote_retrieve_response_code( $response );

		// Token Expired
		if ( $response_code === 401 ) {
			update_option( 'dvsfw_shipos_token', null );
		} else {
			set_transient( $transient_name, true, HOUR_IN_SECONDS );
		}
	}
}
