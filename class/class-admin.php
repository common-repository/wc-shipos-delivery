<?php

/**
 * Admin class
 * This is used to display Shipos delivery box on admin shop order
 *
 * @since 1.0.0
 * @package WCShiposDelivery
 */

namespace WCShiposDelivery;

/**
 * Class Admin
 * This is used to display Matat delivery box on admin shop order
 *
 * @since 1.0.0
 */
class Admin {

	/**
	 * Enqueue Scripts and Localize Script
	 *
	 * @since 1.0.0
	 */
	public function enqueue_scripts( string $hook ) {
		global $post_type;

		if ( 'shop_order' === $post_type || 'woocommerce_page_wc-settings' === get_current_screen()->base || $hook === 'woocommerce_page_wc-orders' ) {
			wp_register_script(
				'wc-shipos-delivery-admin',
				plugins_url( 'admin/js/wc-shipos-delivery-admin.js', dirname( __FILE__ ) ),
				array( 'jquery' ),
				DVSFW_PLUGIN_VERSION,
				true
			);
			wp_localize_script(
				'wc-shipos-delivery-admin',
				'matat_delivery',
				array(
					'ajax_url'                    => admin_url( 'admin-ajax.php' ),
					'admin_url'                   => admin_url( 'admin.php' ),
					'matat_ajax_nonce'            => wp_create_nonce( 'dvsfw_submit_open_ship' ),
					'matat_ajax_get_nonce'        => wp_create_nonce( 'dvsfw_submit_get_ship' ),
					'matat_ajax_change_nonce'     => wp_create_nonce( 'dvsfw_submit_change_ship' ),
					'matat_ajax_reopen_nonce'     => wp_create_nonce( 'dvsfw_reopen_ship' ),
					'matat_ajax_loader'           => plugins_url( 'admin/img/reload.gif', dirname( __FILE__ ) ),
					'matat_err_message'           => __( 'We are experiencing a communication error. Please try again later.', 'wc-shipos-delivery' ),
					'matat_cancel_ship'           => __( 'Cancel shipment', 'wc-shipos-delivery' ),
					'matat_cancel_ship_ok'        => __( 'Shipment canceled successfully', 'wc-shipos-delivery' ),
					'dvsfw_reopen_ship'           => __( 'Reopen shipment', 'wc-shipos-delivery' ),
					'matat_status_1'              => __( 'Open', 'wc-shipos-delivery' ),
					'matat_status_2'              => __( 'Delivery man on his way', 'wc-shipos-delivery' ),
					'matat_status_3'              => __( 'Delivered', 'wc-shipos-delivery' ),
					'matat_status_4'              => __( 'Collected from customer', 'wc-shipos-delivery' ),
					'matat_status_5'              => __( 'Back from costumer', 'wc-shipos-delivery' ),
					'matat_status_7'              => __( 'Approved', 'wc-shipos-delivery' ),
					'matat_status_8'              => __( 'Canceled', 'wc-shipos-delivery' ),
					'matat_status_9'              => __( 'Second delivery man ', 'wc-shipos-delivery' ),
					'matat_status_12'             => __( 'On hold', 'wc-shipos-delivery' ),
					'matat_err_message_code'      => __( 'Error fetching data from Matat. Please check your API Settings on Settings -> Matat Delivery', 'wc-shipos-delivery' ),
					'matat_err_message_open_code' => __( 'Error opening shipment in Matat. Please check your API Settings on Settings -> Matat Delivery', 'wc-shipos-delivery' ),
				)
			);
			wp_enqueue_script( 'wc-shipos-delivery-admin' );
		}
	}

	/**
	 * Enqueue styles
	 *
	 * @param string $hook Hook name.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_styles( string $hook ) {
		global $post_type;

		wp_register_style(
			'wc-shipos-delivery-admin',
			plugins_url( 'admin/css/wc-shipos-delivery-admin.css', dirname( __FILE__ ) ),
			array(),
			DVSFW_PLUGIN_VERSION
		);
		wp_enqueue_style( 'wc-shipos-delivery-admin' );

		if ( ( 'edit.php' === $hook && 'shop_order' === $post_type ) || $hook === 'woocommerce_page_wc-orders' || $hook === 'post.php' ) {
			wp_register_style(
				'wc-shipos-delivery-table-admin',
				plugins_url( 'admin/css/wc-shipos-delivery-table-admin.css', dirname( __FILE__ ) ),
				array(),
				DVSFW_PLUGIN_VERSION
			);
			wp_enqueue_style( 'wc-shipos-delivery-table-admin' );
		}
	}

	/**
	 * Register Meta Boxes.
	 *
	 * @since 1.0.0
	 */
	public function meta_boxes() {
		add_meta_box(
			'matat-delivery-new',
			__( 'Matat Shipos', 'wc-shipos-delivery' ),
			array( $this, 'matat_meta_box_side' ),
			'shop_order',
			'side',
			'high'
		);

		// Add Shipos meta box to new orders screen (using orders table)
		add_meta_box(
			'matat-delivery-new',
			__( 'Matat Shipos', 'wc-shipos-delivery' ),
			array( $this, 'matat_meta_box_side' ),
			'woocommerce_page_wc-orders',
			'side',
			'high'
		);
	}

	public function matat_meta_box_side( $_order ) {
		$order = wc_get_order( $_order );

		// Check if order has local pickup shipping method.
		if ( ! empty( $order->get_shipping_method() ) ) {
			if ( strpos( 'Local pickup', $order->get_shipping_method() ) !== false ) {
				esc_html_e( 'Order shipping method is Local pickup. Create delivery option is disabled for this order', 'wc-shipos-delivery' );

				return;
			}
		}

		$order_id = $order->get_id();
		if ( function_exists( 'wc_seq_order_number_pro' ) ) {
			if ( get_class( $_order ) === 'WP_Post' ) {
				$order_id = $_order->ID;
			}
		}

		$web_service = new WebService();
		$licenses    = get_option( 'dvsfw_shipos_token' ) ? $web_service->get_licenses() : [];

		if ( is_wp_error( $licenses ) ) {
			error_log( $licenses->get_error_message() );
			echo "Unable to fetch licenses";

			return;
		}

		$matat_label_nonce = wp_create_nonce( 'matat_create_label' );
		$dvsfw_order_id_db = Utils::get_order_meta( $order_id, '_dvsfw_ship_data', false );
		if ( empty( $dvsfw_order_id_db ) ) {
			$dvsfw_order_id_db = $this->getShippingFromApi( $order_id );
		}

		if ( ! empty( $dvsfw_order_id_db ) ) {
//			$matat_delivery_number_array = Utils::get_order_meta( $order, '_dvsfw_ship_data', false );
			$cancel = Utils::get_order_meta( $order, '_order_canceled', false );

			$matat_delivery_number = $dvsfw_order_id_db;
			?>
            <h4><?php esc_html_e( 'Matat shipment details: ', 'wc-shipos-delivery' ); ?></h4>
			<?php
			foreach ( $matat_delivery_number as $key => $single ) {
				$single              = (array) $single;
				$matat_label_query   = 'post.php?matat_pdf=create&matat_label_wpnonce=' . $matat_label_nonce . '&order_id=' . $order_id . '&ship_id=' . $key;
				$matat_delivery_time = explode( ' ', $single['delivery_time'] );
				$ship_type           = '';
				if ( $single['type'] == '1' ) {
					if ( absint( $single['return'] ) == '2' ) {
						$ship_type = __( 'Double delivery', 'wc-shipos-delivery' );
					} else {
						$ship_type = __( 'Regular delivery', 'wc-shipos-delivery' );
					}
				} elseif ( $single['type'] == '2' ) {
					$ship_type = __( 'Collecting delivery', 'wc-shipos-delivery' );
				}

				?>
                <div class="matat-wrapper">
                    <div id="matat_ship_exists" data-order="<?php echo esc_attr( $order_id ); ?>">

                        <p><?php esc_html_e( 'Shipment number:', 'wc-shipos-delivery' ); ?> <span
                                    class="matat_delivery_id"><?php echo wp_kses_post( $single['delivery_number'] ); ?> (<?php echo wp_kses_post( $ship_type ); ?>)</span>
                            <br>
                            <span style="color: #FF0006;margin: 0"><?php echo $single['company'] ? wp_kses_post( $single['company']->name ) : '' ?></span>
                        </p>
                    </div>
                    <div class="matat-button-container">
                        <a class="matat-button matat-print-button" target="_blank"
                           data-order="<?php echo esc_attr( $order_id ); ?>"
                           href="<?php echo esc_url( $matat_label_query ); ?>"><?php esc_html_e( 'Print label', 'wc-shipos-delivery' ); ?></a>
						<?php
						$order_cancel = false;
						if ( in_array( $single['delivery_number'], $cancel ) ) {
							$order_cancel = true;
						}
						?>
                        <button <?php echo $order_cancel ? 'disabled' : ''; ?> class="matat-button matat-cancel-ship"
                                                                               data-license-key="<?php echo esc_attr( $single['license_key'] ?? null ); ?>"
                                                                               data-order-id="<?php echo esc_attr( $order_id ); ?>"
                                                                               data-shipping-id="<?php echo esc_attr( $single['delivery_number'] ); ?>">
							<?php
							if ( $order_cancel ) {
								esc_html_e( 'Shipment canceled', 'wc-shipos-delivery' );
							} else {
								esc_html_e( 'Cancel delivery', 'wc-shipos-delivery' );
							}
							?>
                        </button>
						<?php
						add_thickbox();

						$deliver_status_link = '#TB_inline?width=200&height=270&inlineId=modal-window-' . $order_id;
						?>

                        <a href="<?php echo esc_url( $deliver_status_link ); ?>"
                           class="thickbox ship_status matat-button"
                           data-license-key="<?php echo esc_attr( $single['license_key'] ?? null ); ?>"
                           data-status="<?php echo esc_attr( $single['delivery_number'] ); ?>"><?php esc_html_e( 'Delivery status', 'wc-shipos-delivery' ); ?></a>
                        <div id="modal-window-<?php echo esc_attr( $order_id ); ?>" style="display:none;">
                            <table style="border-collapse: collapse; width: 100%;">
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;">
                                        <span class="matat_delivery_time"><?php echo wp_kses_post( $single['delivery_number'] ); ?></span>
                                    </td>
                                    <th style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Shipment number:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;">
                                        <span class="matat_delivery_time"><?php echo wp_kses_post( $ship_type ); ?></span>
                                    </td>
                                    <th style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Type:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;">
                                        <span class="matat_delivery_time"><?php echo wp_kses_post( $single['company']->name ); ?></span>
                                    </td>
                                    <th style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Company:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;">
                                        <span class="matat_delivery_time"><?php echo wp_kses_post( $matat_delivery_time[0] ); ?></span>
                                    </td>
                                    <th style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Delivery time:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;"><span
                                                class="matat_receiver_name"></span></td>
                                    <th class="matat_ship_open"
                                        style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Receiver name:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;"><span
                                                class="matat_delivery_status"></span></td>
                                    <th style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Delivery status:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                                <tr>
                                    <td style="border: 1px solid #000; padding: 8px;"><span
                                                class="matat_shipped_on"></span></td>
                                    <th class="matat_ship_open"
                                        style="border: 1px solid #000; padding: 8px;"><?php esc_html_e( 'Shipped on:', 'wc-shipos-delivery' ); ?></th>
                                </tr>
                            </table>
                        </div>


                    </div>

                </div>
                <hr>
				<?php
			}
		}

		// If the order has GetPackage shipping method, we will only show GetPackage as the valid license
		$shipping_methods = $order->get_shipping_methods();
		$shipping_method  = reset( $shipping_methods );
		if ( $shipping_method && $shipping_method->get_method_id() === 'shipos_getpackage' ) {
			$license_key = get_option( 'dvsfw_getpackage_license' );
			$licenses    = array_filter( $licenses, function ( $license ) use ( $license_key ) {
				return $license->key === $license_key;
			} );
		}

		?>

        <div class="matat-wrapper">
            <div id="matat_open_ship_new">
                <h4><?php esc_html_e( 'Create shipping:', 'wc-shipos-delivery' ); ?></h4>
				<?php
				$exchange_details = Utils::get_order_meta( $order, 'product_exchange_details' );
				?>
                <div class="dvsfw_form-group">
					<?php if ( count( $licenses ) > 1 ) {
						// Get the default license key, trim it and provide a fallback empty string if not set
						$default_license_key = trim( get_option( 'wc-shipos-delivery' )['dvsfw_license_key'] ?? '' );
						?>
                        <label for="dvsfw_license_new">Choose a License:</label>
                        <select name="dvsfw_license_new" id="dvsfw_license_new">
							<?php foreach ( $licenses as $lic ) : ?>
                                <option value="<?php echo esc_attr( $lic->key ); ?>" <?php selected( $lic->key === $default_license_key ) ?>>
									<?php echo esc_html( implode( '-', array_filter( [
										$lic->name,
										$lic->company
									] ) ) ); ?>
                                </option>
							<?php endforeach; ?>
                        </select>
					<?php } else {
						$license              = reset( $licenses );
						$license_display_text = $license->name ?: $license->company;
						?>
                        <input type="hidden" name="dvsfw_license_new" id="dvsfw_license_new"
                               value="<?php echo esc_attr( $license->key ); ?>">
                        <span><strong>Company:</strong> <?php echo esc_html( $license_display_text ) ?></span>
					<?php } ?>
                </div>

                <div class="dvsfw_form-group">
                    <input id="dvsfw_return_new" type="checkbox" name="dvsfw_return_new"
                           value="2" <?php checked( ! empty( $exchange_details ) ); ?>>
					<?php esc_html_e( 'Double', 'wc-shipos-delivery' ); ?>
                    <div class="collect_wrap hidden" style="margin: 10px 0;">
                        <label for="collect"><?php esc_html_e( 'Amount to collect', 'wc-shipos-delivery' ); ?></label>
                        <input type="number" name="collect" id="collect">
                    </div>
                </div>

                <div class="dvsfw_form-group">
                    <input type="radio" class="dvsfw_delivery_type_new" name="dvsfw_delivery_type_new" value="1"
                           checked><?php esc_html_e( 'Regular delivery', 'wc-shipos-delivery' ); ?>
                    <br>
                    <input type="radio" class="dvsfw_delivery_type_new" name="dvsfw_delivery_type_new"
                           value="2"><?php esc_html_e( 'Collecting', 'wc-shipos-delivery' ); ?>
                </div>

                <div style="display: flex; gap: 20px">
                    <div class="dvsfw_form-group">
                        <label for="dvsfw_exaction_date_new"><?php _e( 'Deliver on:', 'wc-shipos-delivery' ); ?></label>
                        <br>
                        <input id="dvsfw_exaction_date_new" type="date" name="date"
                               value="<?php echo esc_attr( date( 'Y-m-d' ) ); ?>">
                    </div>

                    <div class="dvsfw_form-group">
                        <label for="dvsfw_packages_new"><?php _e( 'Packages:', 'wc-shipos-delivery' ); ?></label> <br>
                        <input id="dvsfw_packages_new" type="number" name="packages" value="1">
                    </div>
                </div>

                <div class="matat-button-container-open-new">
                    <button class="matat-button matat-open-button-new"
                            data-order="<?php echo esc_attr( $order_id ); ?>">
						<?php esc_html_e( 'Open shipment', 'wc-shipos-delivery' ); ?>
                    </button>
                </div>
            </div>
            <div class="matat-success-ship">
                <p><?php esc_html_e( 'Shipment number:', 'wc-shipos-delivery' ); ?><span
                            class="matat-success-ship-number"></span>
                </p>
                <div class="matat-button-container">
                    <a class="matat-button matat-print-button" target="_blank"
                       data-order="<?php echo esc_attr( $order_id ); ?>"
                       href="<?php echo esc_url( $matat_label_query ?? '' ); ?>"><?php esc_html_e( 'Print label', 'wc-shipos-delivery' ); ?></a>
                </div>
            </div>
            <div class="matat-powered-by-new">
                <span><?php esc_html_e( 'Powered by', 'wc-shipos-delivery' ); ?> </span><a target="_blank"
                                                                                           href="https://matat.co.il"><img
                            src="<?php echo esc_url( plugins_url( 'admin/img/matatlogo.png', dirname( __FILE__ ) ) ); ?>"></a>
            </div>
        </div>
		<?php
	}


	public function getShippingFromApi( $order_id ): array {
		$order = wc_get_order( $order_id );

		$shipmentApiCounter  = Utils::get_order_meta( $order, 'dvsfw_shipment_api_counter' );
		$shipmentCreateStart = Utils::get_order_meta( $order, 'dvsfw_shipment_create_start' );

		if ( empty( $shipmentCreateStart ) ) {
			return [];
		}

		$counter    = $shipmentApiCounter ?: 0;
		$webService = new WebService();
		$response   = $webService->get_shipment_data_from_api( $order_id );
		$counter ++;
		$order->update_meta_data( 'dvsfw_shipment_api_counter', $counter );

		if ( $response && isset( $response[ array_key_first( $response ) ]->delivery_number ) ) {
			foreach ( $response as $ship_data ) {
				$order->update_meta_data( '_dvsfw_ship_data', $ship_data );
			}
		}

		if ( empty( $response ) && $counter >= 3 ) {
			$order->delete_meta_data( 'dvsfw_shipment_create_start' );
		}

		$order->save();

		return $response;
	}

	/**
	 * Shop orders columns head
	 *
	 * @param array $columns Shop orders columns.
	 *
	 * @since 1.1
	 */
	public function matat_admin_column_head( array $columns ): array {
		$columns['matat_delivery_column'] = __( 'Matat Shipos', 'wc-shipos-delivery' );

		return $columns;
	}

	/**
	 * Shop orders columns content
	 *
	 * @param string $column Shop orders columns.
	 * @param mixed $post Shop orders id.
	 *
	 * @since 1.1
	 */
	public function matat_admin_column( string $column, $post ) {

		if ( $column !== 'matat_delivery_column' ) {
			return;
		}

		$order    = wc_get_order( $post );
		$order_id = $order->get_id();
		if ( function_exists( 'wc_seq_order_number_pro' ) ) {
			if ( gettype( $post ) === 'integer' ) {
				$order_id = $post;
			} else {
				$order_id = $post->get_id();
			}
		}

		if ( ! empty( $order->get_shipping_method() ) ) {
			if ( strpos( 'Local pickup', $order->get_shipping_method() ) !== false ) {
				esc_html_e( 'Order shipping method is Local pickup. Create delivery option is disabled for this order', 'wc-shipos-delivery' );

				return;
			}
		}

		$matat_label_nonce = wp_create_nonce( 'matat_create_label' );
		$cancel            = Utils::get_order_meta( $order_id, '_order_canceled' );
		$matat_order_meta  = Utils::get_order_meta( $order_id, '_dvsfw_ship_data', false );
		if ( empty( $matat_order_meta ) && 'matat_delivery_column' === $column ) {
			$matat_order_meta = $this->getShippingFromApi( $order_id );
		}

		// Show shipment cancelled button if there is only one shipment made and it has been cancelled
		if ( $cancel && ! Utils::get_order_meta( $order, '_dvsfw_more_than_one' ) ) {
			?>
            <button disabled class="matat-button matat-cancel-ship"
                    style="background: red;display:inline-block;text-align: center;">
				<?php esc_html_e( 'Shipment canceled', 'wc-shipos-delivery' ); ?>
            </button>
			<?php
			return;
		}

		if ( $matat_order_meta ) {
			$last              = array_key_last( $matat_order_meta );
			$last_order_meta   = (array) $matat_order_meta[ $last ];
			$matat_label_query = 'post.php?matat_pdf=create&matat_label_wpnonce=' . $matat_label_nonce . '&order_id=' . $order_id . '&ship_id=' . $last;
			$company_name      = $last_order_meta['company'] ? $last_order_meta['company']->name : '';
			?>
            <div class="matat-table-deliv-num-new">
                <span>
                    <?php
                    echo wp_kses_post( $last_order_meta['delivery_number'] );
                    ?>
                </span>
            </div>
            <div class="matat-button-container">
                <a class="matat-button matat-print-button-new" target="_blank"
                   data-order="<?php echo esc_attr( $order_id ); ?>"
                   href="<?php echo esc_url( $matat_label_query ); ?>">
					<?php esc_html_e( 'Print label', 'wc-shipos-delivery' ); ?>
                </a>
                <br>
                <span style="color: #FF0006;margin: 0">
                    <?php echo wp_kses_post( $company_name ) ?>
                </span>
            </div>
			<?php
		} else {
			?>
            <div class="matat-table-deliv-not-new order-<?php echo esc_attr( $order_id ); ?>">
				<?php
				$web_service = new WebService();
				$licenses    = get_option( 'dvsfw_shipos_token' ) ? $web_service->get_licenses() : [];

				if ( is_wp_error( $licenses ) ) {
					echo "Unable to fetch licenses";

					return;
				}

				// If the order has GetPackage shipping method, we will only show GetPackage as the valid license
				$shipping_methods = $order->get_shipping_methods();
				$shipping_method  = reset( $shipping_methods );
				if ( $shipping_method && $shipping_method->get_method_id() === 'shipos_getpackage' ) {
					$license_key = get_option( 'dvsfw_getpackage_license' );
					$licenses    = array_filter( $licenses, function ( $license ) use ( $license_key ) {
						return $license->key === $license_key;
					} );
				}

				foreach ( $licenses as $license ) {
					$label = count( $licenses ) === 1
						? __( 'Create shipping', 'wc-shipos-delivery' )
						: mb_substr( $license->name ?: $license->company, 0, 20 );

					?>
                    <button class="matat-button matat_shop_order_delivery_new"
                            data-license="<?php echo esc_attr( $license->key ); ?>"
                            title="<?php echo esc_attr( $license->company ); ?>"
                            style="background: #F7931E;margin-bottom: 5px;"
                            data-order="<?php echo esc_attr( $order_id ); ?>"
                            data-nonce="<?php echo esc_attr( $matat_label_nonce ); ?>">
						<?php esc_html_e( $label, 'wc-shipos-delivery' ); ?>
                    </button>
					<?php
				}
				?>
            </div>
			<?php
		}
	}

	public function add_plugin_menu_and_options() {
		$logo  = esc_url( plugins_url( 'admin/img/shipos-logo-white.svg', dirname( __FILE__ ) ) );
		$title = '<div style="display:flex; gap: .25rem"><img src="' . $logo . '"  alt="" style="width: 20px"/>Ship OS shipping ' . '</div>';
		add_submenu_page( 'woocommerce', 'Deliver via Shipos for Woocommerce', $title, 'manage_woocommerce', 'deliver-via-shipos', array(
			$this,
			'shipos_page'
		) );
	}

	public function shipos_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'wc-shipos-delivery' ) );
		}

		if ( isset( $_GET['token'] ) ) {
			update_option( 'dvsfw_shipos_token', $_GET['token'] );
			header( "Location: " . admin_url() . 'admin.php?page=deliver-via-shipos' );
			die();
		}

		if ( isset( $_GET['user_id'] ) ) {
			header( "Location: " . admin_url() . 'admin.php?page=deliver-via-shipos' );
			die();
		}

		include __DIR__ . '/../partials/options-page.php';
	}

	public function admin_bar_item( \WP_Admin_Bar $admin_bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$admin_bar->add_menu( array(
			'id'     => 'deliver-via-shipos',
			'parent' => null,
			'group'  => null,
			'title'  => 'ניהול משלוחים',
			//you can use img tag with image link. it will show the image icon Instead of the title.
			'href'   => admin_url( 'admin.php?page=deliver-via-shipos' ),
			'meta'   => [
				'title' => __( 'Deliver via Shipos', 'wc-shipos-delivery' ), //This title will show on hover
			]
		) );
	}
}
