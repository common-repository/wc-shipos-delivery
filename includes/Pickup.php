<?php

use WCShiposDelivery\Utils;
use WCShiposDelivery\WebService;

function dvsfw_Delivery_shipping_method_init() {
	if ( ! class_exists( 'WC_Shipping_Shipos_Delivery' ) ) {

		class WC_Shipping_Shipos_Delivery extends WC_Shipping_Method {

			/**
			 * Ignore discounts.
			 *
			 * If set, shipping discount would be available based on pre-discount order amount.
			 *
			 * @var string
			 */
			public $ignore_discounts;

			/**
			 * Constructor. The instance ID is passed to this.
			 */
			public function __construct( $instance_id = 0 ) {
				$this->id                 = 'shipos_delivery';
				$this->instance_id        = absint( $instance_id );
				$this->method_title       = __( 'Shipos delivery', 'wc-shipos-delivery' );
				$this->method_description = __( 'Shipos delivery', 'wc-shipos-delivery' );
				$this->supports           = array(
					'shipping-zones',
					'instance-settings',
					'instance-settings-modal',
				);

				$this->instance_form_fields = array(
					'title'                   => array(
						'title'       => __( 'Shipos delivery', 'wc-shipos-delivery' ),
						'type'        => 'text',
						'description' => __( 'This controls the title which the user sees during checkout.', 'wc-shipos-delivery' ),
						'default'     => __( 'Shipos delivery', 'wc-shipos-delivery' ),
						'desc_tip'    => true,
					),
					'cost'                    => array(
						'title'       => __( 'Cost', 'wc-shipos-delivery' ),
						'type'        => 'number',
						'description' => __( 'Enter delivery cost', 'wc-shipos-delivery' ),
						'default'     => 0,
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
				$this->enabled              = $this->get_option( 'enabled' );
				$this->title                = $this->get_option( 'title' );
				$this->ignore_discounts     = $this->get_option( 'ignore_discounts' );

				add_action( 'woocommerce_update_options_shipping_' . $this->id, array(
					$this,
					'process_admin_options'
				) );
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

					if ( 'no' === $this->ignore_discounts ) {
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
		// class ends here
	}
}

add_action( 'woocommerce_shipping_init', 'dvsfw_Delivery_shipping_method_init' );

function register_Shipos_Delivery( $methods ) {
	$methods['shipos_delivery'] = new WC_Shipping_Shipos_Delivery();

	return $methods;
}

add_filter( 'woocommerce_shipping_methods', 'register_Shipos_Delivery' );


// Add the chita delivery field to the checkout page
$public_hooked_location = apply_filters( 'shipos_delivery_location', 'woocommerce_after_shipping_rate' );

function dvsfw_pickup( $checkout ) {
	// display Chita Delivery button on checkout page only and for local pickup only
	if ( $checkout->method_id == 'shipos_delivery' && is_checkout() ) { ?>
        <div id="shipos_pickup_checkout" style="display: none">
        <div class="shipos_opener_wrap">
            <button class="shipos_popup_open" type="button">
				<?php
				$button_text = '<span class="shipos-btn-title">';
				$button_text .= esc_html__( 'Click to select a pickup point', 'wc-shipos-delivery' );
				$button_text .= '</span>';
				echo apply_filters( 'dvsfw_shipos_delivery_button_title', $button_text );
				echo apply_filters( 'dvsfw_shipos_delivery_button_subtitle', '' );
				?>
            </button>
        </div>

		<?php

		woocommerce_form_field(
			'shipos_delivery',
			array(
				'type'     => 'text',
				'class'    => array( 'shipos-delivery-form-row-wide' ),
				'label'    => __( 'Chosen Delivery Spot', 'wc-shipos-delivery' ),
				'required' => true,
			)
		);

		woocommerce_form_field(
			'shipos_delivery_id',
			array(
				'type'        => 'hidden',
				'class'       => array( 'hidden form-row-wide' ),
				'label_class' => array( 'hidden' ),
			)
		);

		echo '</div>';

		?>

		<?php
	}
}

add_action( $public_hooked_location, 'dvsfw_pickup' );

add_action( 'wp_footer', 'dvsfw_pickup_popup' );

function dvsfw_pickup_popup() {
	if ( is_checkout() ) {
		?>
        <div class="shipos-pickup-popup" id="shipos-pickup-popup">
            <div class="popup-frame">
                <div class="popup-content">
                    <div class="popup-inner-content">
                        <div class="popup-header">
                            <button class="shipos_popup_close" type="button"><img
                                        src="<?php echo esc_url( plugins_url( '/public/img/close.svg', dirname( __FILE__ ) ) ); ?>"
                                        alt="close"></button>
                            <div class="popup-title"><?php esc_html_e( 'Choose a Collection Point', 'wc-shipos-delivery' ); ?></div>
                            <div class="popup-subtitle"><?php esc_html_e( 'Search and Select a collection point by origin address', 'wc-shipos-delivery' ); ?></div>
                        </div>
                        <div class="popup-body">
                            <div class="shipos_tabs">
                                <div v-if="showBoth" class="shipos_tabs_nav">
                                    <button class="map_tab_item" @click="activeTab = 'map'"
                                            :class="{active: activeTab === 'map'}">
										<?php esc_html_e( 'Select On Map', 'wc-shipos-delivery' ); ?>
                                    </button>
                                    <button class="manual_tab_item" @click="activeTab = 'manual'"
                                            :class="{active: activeTab === 'manual'}">
										<?php esc_html_e( 'Select Manually', 'wc-shipos-delivery' ); ?>
                                    </button>
                                </div>
                                <div class="shipos_tab" v-show="activeTab === 'map'">
                                    <div class="address-search-form">
                                        <div class="form-input-wrap">
                                            <!--                                        <div class="input-wrap ">-->
                                            <input type="text" class="shipos-form-input" placeholder="הקלד את שם היישוב"
                                                   id="shipos_map_search_input" autocomplete="on">
                                            <!--                                        </div>-->
                                            <!--<div class="btn-wrap">
												<button type="button" class="btn-shipos-primary">
												  <?php /*esc_html_e('Search', 'wc-shipos-delivery'); */ ?>
												</button>
											</div>-->
                                        </div>
                                    </div>
                                    <div id="shipos_map"></div>
                                    <div v-if="map.pickedLocation">
                                        <strong><?php esc_html__( 'Selected pickup point:', 'wc-shipos-delivery' ); ?></strong>
                                        {{ map.pickedLocation.name + ' ' +
                                        map.pickedLocation.street + ' ' + map.pickedLocation.house + ' ' +
                                        map.pickedLocation.city }}
                                    </div>
                                    <div class="pickup-result-holder result_active has_ship_actions"
                                         v-if="map.pickedLocation">
                                        <div class="shipos-action-buttons">
                                            <button class="btn-shipos-primary" type="button"
                                                    @click="setSelectedLocation(map.pickedLocation)">
												<?php esc_html_e( 'Select Collection Point', 'wc-shipos-delivery' ); ?>
                                            </button>
                                            <button class="btn-outline btn_clear_shipos" type="button"
                                                    @click="clearSelectedLocation">
												<?php esc_html_e( 'Clear', 'wc-shipos-delivery' ); ?>
                                            </button>
                                        </div>
                                    </div>

                                </div>
                                <div class="shipos_tab" v-show="activeTab === 'manual'">
                                    <div class="address-search-form">
                                        <div class="form-input-wrap">
                                            <div class="input-wrap ">
                                                <input type="text" class="shipos-form-input"
                                                       placeholder="הקלד את שם היישוב" id="shipos_search_input"
                                                       v-model="searchInput" autocomplete="off" ref="searchInput"
                                                       @focus="showAutoCompleteOptions = true"
                                                       v-on:keyup="showAutoCompleteOptions = true">
                                                <ul class="shipos_location_autocomplete"
                                                    v-show="showAutoCompleteOptions && cities.length"
                                                    :style="{width: autocompleteWidth}">
                                                    <li v-for="(city,idx) in cities" :key="idx"
                                                        @click="handleAutoCompleteClick(city)">
                                                        {{city}}
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="btn-wrap">
                                                <button type="button" @click="getFilteredLocations"
                                                        class="btn-shipos-primary">
													<?php esc_html_e( 'Search', 'wc-shipos-delivery' ); ?>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shipos_locations_loader" v-if="loading">
                                        <img src="<?php echo esc_url( plugins_url( 'admin/img/reload.gif', dirname( __FILE__ ) ) ); ?>"
                                             alt="Loading..."/>
                                    </div>
                                    <div class="pickup-result-holder result_active has_ship_actions"
                                         v-if="filteredLocations.length">
                                        <div class="result-count-title"> <?php esc_html_e( 'Total Results: ', 'wc-shipos-delivery' ); ?>
                                            {{ filteredLocations.length }}
                                        </div>
                                        <div class="pickup-locations" v-if="!loading">
                                            <div class="pickup-location-item" v-for="location in filteredLocations"
                                                 :key="location.n_code">
                                                <label>
                                                    <input type="radio" class="shipos_pickup"
                                                           name="shipos_pickup_location" v-model="pickedLocation"
                                                           :value="location">
                                                    <div class="pickup-title"> {{ location.name }} {{ location.city }}
                                                    </div>
                                                    <div class="pickup-address">
                                                        <img src="<?php echo esc_url( plugins_url( '/public/img/location.svg', dirname( __FILE__ ) ) ); ?>"
                                                             class="location" alt="location">
                                                        <div class="text-wrap">
                                                            {{ location.street }} {{ location.street_code }}, {{
                                                                location.city }}
                                                            <!--<a :href="`https://maps.google.com/maps?q=${location.latitude},${location.longitude}`"
											   target="_blank">לצפייה במפה</a>-->
                                                            <a :href="`https://maps.google.com/maps?q=${location.street}+${location.house}+${location.city}`"
                                                               target="_blank"><?php esc_html_e( 'View on Map', 'wc-shipos-delivery' ); ?></a>
                                                        </div>
                                                    </div>
                                                    <div class="pickup-address">
                                                        <img src="<?php echo esc_url( plugins_url( '/public/img/time.svg', dirname( __FILE__ ) ) ); ?>"
                                                             class="time" alt="time">
                                                        <div class="text-wrap">
                                                            {{ location.remarks }}
                                                        </div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div class="shipos-action-buttons" v-if="pickedLocation">
                                            <button class="btn-shipos-primary" type="button"
                                                    @click="setSelectedLocation(pickedLocation)">
												<?php esc_html_e( 'Select Collection Point', 'wc-shipos-delivery' ); ?>
                                            </button>
                                            <button class="btn-outline btn_clear_shipos" type="button"
                                                    @click="clearSelectedLocation">
												<?php esc_html_e( 'Clear', 'wc-shipos-delivery' ); ?>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
		<?php
	}
}

function dvsfw_pickup_checkout_scripts() {
	if ( is_checkout() ) :
		$vuejs_filename    = get_option( 'dvsfw_dev_mode' ) === 'yes' ? 'vue.js' : 'vue.min.js';
		$pickup_preference = get_option( 'dvsfw_pickup_point_display_preference' );

		wp_enqueue_script( 'dvsfw_pickup_checkout_vue_js', plugins_url( '', dirname( __FILE__ ) ) . '/public/js/' . $vuejs_filename, false, DVSFW_PLUGIN_VERSION, false );
		wp_enqueue_script( 'dvsfw_pickup_checkout_js', plugins_url( '', dirname( __FILE__ ) ) . '/public/js/wc-shipos-delivery.js', array( 'jquery' ), DVSFW_PLUGIN_VERSION, false );
		$localizeScript = array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'nonce'             => wp_create_nonce( 'dvsfw_pickup_checkout_js' ),
			'pickup_preference' => $pickup_preference,
		);
		wp_enqueue_style( 'dvsfw_pickup_checkout_css', plugins_url( '', dirname( __FILE__ ) ) . '/public/css/wc-shipos-delivery.css', false, DVSFW_PLUGIN_VERSION );

		$google_maps_api_key = get_option( 'dvsfw_google_maps_api_key' );

		if ( $google_maps_api_key && ( $pickup_preference === 'map' || $pickup_preference === 'both' ) ) {
			$localizeScript = array_merge(
				$localizeScript,
				array(
					'default_pickup_preference' => get_option( 'dvsfw_pickup_point_default_display', 'map' ),
					'google_maps_api_key'       => $google_maps_api_key,
				)
			);
		}

		wp_localize_script( 'dvsfw_pickup_checkout_js', 'dvsfw_pickup_checkout_js', $localizeScript );

	endif;
}

add_action( 'wp_enqueue_scripts', 'dvsfw_pickup_checkout_scripts' );

function dvsfw_custom_shipos_checkout_field_notice() {
	// Check if set, if its not set add an error.
	$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
	$chosen_shipping = $chosen_methods[0];

	if ( false !== strpos( $chosen_shipping, 'shipos_delivery' ) ) {
		if ( ! sanitize_text_field( $_POST['shipos_delivery'] ) ) {
			wc_add_notice( __( 'Please select delivery spot.', 'wc-shipos-delivery' ), 'error' );
		}
	}
}

add_action( 'woocommerce_checkout_process', 'dvsfw_custom_shipos_checkout_field_notice' );

function dvsfw_update_pickup_order_meta( $order_id ) {

	$order = wc_get_order( $order_id );
	if ( ! $order ) {
		return;
	}

	if ( sanitize_text_field( $_POST['shipos_delivery'] ) ) {
		$order->update_meta_data( 'shipos_delivery_address', sanitize_text_field( $_POST['shipos_delivery'] ) );
	}

	if ( sanitize_text_field( $_POST['shipos_delivery_id'] ) ) {
		$order->update_meta_data( 'shipos_delivery_address_id', sanitize_text_field( $_POST['shipos_delivery_id'] ) );
	}
	$order->save();
}

add_action( 'woocommerce_checkout_update_order_meta', 'dvsfw_update_pickup_order_meta' );

function dvsfw_display_admin_order_meta( $order ) {
	$shipos_delivery_address = Utils::get_order_meta( $order, 'shipos_delivery_address' );

	if ( ! empty( $shipos_delivery_address ) ) {
		echo wp_kses_post(
			sprintf( '<p><strong>%s</strong> %s</p>', __( 'Requested Delivery point', 'wc-shipos-delivery' ), Utils::get_order_meta( $order, 'shipos_delivery_address' ) )
		);
	}
}

add_action( 'woocommerce_admin_order_data_after_shipping_address', 'dvsfw_display_admin_order_meta', 10, 1 );

function dvsfw_get_location() {
	$web_service = new WebService();
	$response    = $web_service->matat_pickup_locations();

	if ( ! $response ) {
		esc_html_e( 'Oops! Error getting response from server...', 'wc-shipos-delivery' );
	} else {
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
		if ( $wp_filesystem->put_contents( $filename, wp_json_encode( $response ) ) ) {
			esc_html_e( 'Pickup location created successfully...', 'wc-shipos-delivery' );
		} else {
			esc_html_e( 'Oops! Error creating pickup json file...', 'wc-shipos-delivery' );
		}
	}
}

if ( ! wp_next_scheduled( 'dvsfw_get_location_daily' ) ) {
	wp_schedule_event( time(), 'daily', 'dvsfw_get_location_daily' );
}

add_action( 'dvsfw_get_location_daily', 'dvsfw_get_location' );

function custom_woocommerce_email_order_meta_fields( $fields, $sent_to_admin, $order ) {

	$shipos_delivery_address = Utils::get_order_meta( $order, 'shipos_delivery_address' );

	if ( ! empty( $shipos_delivery_address ) ) {

		$fields['shipos_delivery_pickup'] = array(
			'label' => __( 'Requested Delivery point', 'wc-shipos-delivery' ),
			'value' => Utils::get_order_meta( $order, 'shipos_delivery_address' ),
		);

	}

	return $fields;

}

add_filter( 'woocommerce_email_order_meta_fields', 'custom_woocommerce_email_order_meta_fields', 10, 3 );
