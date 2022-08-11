<?php
function reset_default_shipping_method( $method, $available_methods ) {
    $default_method = 'local_pickup:6'; //provide here the service name which will selected default
    if( array_key_exists($method, $available_methods ) )
    	return $default_method;
    else
    	return $method;
}
add_filter('woocommerce_shipping_chosen_method', 'reset_default_shipping_method', 10, 2);
