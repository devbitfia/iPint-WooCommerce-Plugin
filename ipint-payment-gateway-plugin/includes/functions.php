<?php
/**
 * iPint Common functions
 *
 * @author   The Tech Makers <info@thetechmakers.com>
 * @package  WooCommerce Ipint Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function register_log($log) {
	$ipint_gateway = new WC_Gateway_Ipint();

    $debug = $ipint_gateway->get_option('debug');

    if( $debug == "yes" ){
		$filename = ABSPATH . "debug.log";

		file_put_contents($filename, $log, FILE_APPEND);
		file_put_contents($filename, "\r\n", FILE_APPEND);
	}
}
