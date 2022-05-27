<?php
/**
 * WC_Gateway_Ipint_Settings class
 *
 * @author   iPint <help@ipint.io>
 * @package  WooCommerce Ipint Payments Gateway
 * @since    1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ipint Gateway.
 *
 * @class    WC_Gateway_Ipint_Settings
 * @version  1.0.1
 */
class WC_Gateway_Ipint_Settings {

	// Return instance of iPints settings class
	public static function get_instance() {
        if (!isset(self::$obj)) {
            self::$obj = new WC_Gateway_Ipint_Settings();
        }
         
        return self::$obj;
    }

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public static function init_form_fields() {

		$form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'ipint' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable iPint Payments', 'ipint' ),
				'default' => 'yes'
			),
			'debug' => array(
				'title'       => __( 'Debug Mode', 'ipint' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Debug Mode', 'ipint' ),
				'default'     => __( 'no', 'ipint' )
			),
			'title' => array(
				'title'       => __( 'Title', 'ipint' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'ipint' ),
				'default'     => _x( 'iPint Payment', 'iPint payment method', 'ipint' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'ipint' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'ipint' ),
				'default'     => __( 'Pay from your favorite Crpto Currency.', 'ipint' ),
				'desc_tip'    => true,
			),
			'mode' => array(
				'title'       => __( 'Mode', 'ipint' ),
				'type'        => 'select',
				'description' => __( 'Payment Gateway Mode', 'ipint' ),
				'default'     => __( 'test', 'ipint' ),
				'desc_tip'    => true,
				'options' => array(
					'live' => 'Live',
					'test' => 'Test'
				)
			),
			'api_key' => array(
				'title'       => __( 'API Key', 'ipint' ),
				'type'        => 'text',
				'description' => __( 'iPint API Key', 'ipint' ),
				'default'     => _x( '', 'iPint API Key', 'ipint' ),
				'desc_tip'    => true,
			),
			'secret_key' => array(
				'title'       => __( 'Secret Key', 'ipint' ),
				'type'        => 'text',
				'description' => __( 'iPint Secret Key', 'ipint' ),
				'default'     => _x( '', 'iPint Secret Key', 'ipint' ),
				'desc_tip'    => true,
			)
		);

		return $form_fields;
	}


}
