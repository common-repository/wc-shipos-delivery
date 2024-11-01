<?php
add_filter('woocommerce_shipping_free_shipping_is_available', 'modify_free_shipping_calculation', 10, 3);


function modify_free_shipping_calculation($is_available, $package, $shipping_method)
{
    // Only proceed if it's a free shipping method
    if ($shipping_method->id !== 'free_shipping') {
        return $is_available;
    }

    $price_type = get_option('dvsfw_free_shipping_by_price', 'after_discount');
    if ($price_type) {
	    $min_amount = $shipping_method->min_amount;
        $cart_total = WC()->cart->get_subtotal() - WC()->cart->get_discount_total();
        if ($price_type === 'before_discount') {
            $cart_total = WC()->cart->get_subtotal();
        }

        return $cart_total >= $min_amount;
    }
    return $is_available;
}

?>