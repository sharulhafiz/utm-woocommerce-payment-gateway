<?php
/*
Plugin Name: WooCommerce UTM FPX Payment Gateway
Plugin URI: http://www.utm.my
Description: UTM FPX Payment gateway for woocommerce
Version: 1.2
Author: Sharul Hafiz
Author URI: http://people.utm.my/sharulhafiz
Source: http://www.mrova.com/lets-create-a-payment-gateway-plugin-payu-for-woocommerce/
*/
add_action('plugins_loaded', 'woocommerce_utm_fpx_init', 0);
function woocommerce_utm_fpx_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  include('fpx.php');
  include('cc.php');

  /**
  * Add the Gateway to WooCommerce
  **/
  function woocommerce_add_utm_fpx_gateway($methods) {
      $methods[] = 'WC_UTM_FPX';
      $methods[] = 'WC_UTM_FPX_CC';
      return $methods;
  }

  add_filter('woocommerce_payment_gateways', 'woocommerce_add_utm_fpx_gateway' );
} // ends function

include('validate-payment.php');
include('shipping.php');