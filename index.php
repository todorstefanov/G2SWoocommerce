<?php
/*
Plugin Name: WooCommerce Gate2Shop Gateway
Plugin URI: http://www.gate2shop.com
Description: Gate2Shop gateway for woocommerce
Version: 1.0
Author: Gate2Shop
Author URI:http://gate2shop.com
*/
add_action('plugins_loaded', 'woocommerce_g2s_init', 0);
function woocommerce_g2s_init(){
  if(!class_exists('WC_Payment_Gateway')) return;

  include 'WC_G2S.php';
   /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_g2s_gateway($methods) {
        $methods[] = 'WC_G2S';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_g2s_gateway' );

	function my_enqueue($hook) {
		include("token.php");
		$timestamp= time();
		$g = new WC_G2S;
		$g->setEnvironment();
		//$cl =  new WC_Customer;

		wp_register_script( "g2s_js_script", WP_PLUGIN_URL.'/woocommerce-g2s/js/g2s.js', array('jquery') );
		wp_localize_script( 'g2s_js_script', 'myAjax', array( 'ajaxurl' => WP_PLUGIN_URL.'/woocommerce-g2s/ajax/getAPMs.php', 'token' =>generateToken($timestamp),'t'=>$timestamp));
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'g2s_js_script' );
	}
	add_action( 'init', 'my_enqueue' );

	add_action( 'woocommerce_thankyou_order_received_text', 'my_function' );

	function my_function() {
		global $woocommerce;
		//$order = new WC_Order( $order_id );
		$g = new WC_G2S;
		if ($g->checkAdvancedCheckSum()){
			$woocommerce -> cart -> empty_cart();
			echo "Thank you. Your payment process is completed. Your order status will be updated soon.";
		}
	}
}
?>