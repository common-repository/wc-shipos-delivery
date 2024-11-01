<?php

namespace WCShiposDelivery;

class Utils {
	/**
	 * Get order meta using HPOS or Post Meta, whichever is available
	 *
	 * @param mixed $the_order - Post object / post ID / order object / order ID of the order.
	 * @param string $key - Meta key to retrieve
	 * @param bool $single - return first found meta with key, or all with $key.
	 *
	 * @return array|mixed|string
	 */
	public static function get_order_meta( $the_order, string $key, bool $single = true ) {

		if ( ! $key ) {
			return null;
		}

		$order = wc_get_order( $the_order );

		$meta = $order->get_meta( $key, $single );

		if ( ! empty( $meta ) ) {

			// For some reason, when retrieving all meta of a key using get_meta,
			// instead of returning an array of values, it returns an array of objects
			// with "value" key which has the actual value we need.
			// That's why we map it to return array of values instead with keys as actual meta id.
			if ( ! $single ) {
				$new_meta = [];
				foreach ($meta as $meta_value) {
					$new_meta[$meta_value->id] = $meta_value->value;
				}
				return $new_meta;
			}

			return $meta;
		}

		return get_post_meta( $order->get_id(), $key, $single );
	}
}