<?php
/**
 * WC_Gateway_Ipint class
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
 * @class    WC_Gateway_Ipint
 * @version  1.0.2
 */
class WC_Gateway_Ipint extends WC_Payment_Gateway {
	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'ipint';
		$this->icon               = apply_filters( 'woocommerce_ipint_gateway_icon', '' );
		$this->has_fields         = false;
		$this->supports           = array(
			'products',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
            'subscription_date_changes',
			'multiple_subscriptions'
		);
		$this->method_title       = _x( 'iPint Payment', 'iPint payment method', 'ipint' );
		$this->method_description = __( 'Allows iPint Payments.', 'ipint' );
		// Load the settings.
		$this->form_fields = WC_Gateway_Ipint_Settings::init_form_fields();
		$this->init_settings();
		// Define user set variables.
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );
		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_scheduled_subscription_payment_ipint', array( $this, 'process_subscription_payment' ), 10, 2 );
	}
	/**
	 * Process the payment and return the result.
	 *
	 * @param  int  $order_id
	 * @return array
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		$order_data  = $order->get_data();
			
		if( $this->get_option('api_key') == '' || $this->get_option('secret_key') == '' ){
			wc_add_notice( __( "iPint credentials should not be blank.", 'ipint' ), 'error' );
			return;
		}else{

			// Getting minimum amount
			$min_amount_api_url = $this->get_ipint_api_url().'/limits?preferred_fiat='.$order_data['currency'].'&api_key='.$this->get_ipint_api_key();
			$minimum_amount_response = wp_remote_post( $min_amount_api_url, array(
				'method'      => 'GET',
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,			
				'sslverify'   => false
			) );
			if ( is_wp_error( $minimum_amount_response ) ) {
				$error_message = $minimum_amount_response->get_error_message();
				wc_add_notice( __( "Something went wrong: $error_message", 'ipint' ), 'error' );
				return;
			} else {
				$minimum_amount_response = json_decode( $minimum_amount_response['body'], true );
				$minimum_amount = $minimum_amount_response['minimum_amount'];
				$maximum_amount = $minimum_amount_response['maximum_amount'];
				$order_currency = $order_data['currency'];
				// Now checking if total amount is equal, or greater than minimum amount, or not
				
				if( $order_data['total'] >= $minimum_amount && $order_data['total'] <= $maximum_amount ){ 
					$admin_note = sanitize_text_field( $_POST[ $this->id.'-admin-note']);
					if( isset( $admin_note ) && trim($admin_note) != ''){
						$order->add_order_note( sanitize_text_field( $admin_note ), 1 );
					}
					$post_data = array(
						'client_email_id'                => $order_data['billing']['email'],
						'client_preferred_fiat_currency' => $order_data['currency'],
						'amount'                         => $order_data['total'],
						'merchant_website'               => $this->get_redirect_url($order_id),
						'invoice_callback_url'           => $this->get_callback_url($order_id)
					);
					$post_data = json_encode($post_data);
					$response = wp_remote_post( $this->get_ipint_payment_url(), array(
						'method'      => 'POST',
						'timeout'     => 60,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,			
						'sslverify'   => false,
						'headers'     => array(
							'Content-Type' => 'application/json',
							'apikey' => $this->get_ipint_api_key(),
						),
						'body'        => $post_data,
						'data_format' => 'body',
					) );
					if ( is_wp_error( $response ) ) {
						$error_message = $response->get_error_message();
						wc_add_notice( __( "Something went wrong: $error_message", 'ipint' ), 'error' );
						return;
					} else {
						$response = json_decode( $response['body'], true );
						
						if( $response["error"] == 1 ) {
							$response['result'] = 'failed';
							wc_add_notice( __( $response["message"], 'ipint' ), 'error' );
							return;
						} else{ 
							$redirect_url = $this->get_ipint_redirect_url( $response["session_id"] );
							
							$response['redirect'] = $redirect_url;
							$response['result'] = 'success';
						}
						return $response;
						
					}
				} else if( $order_data['total'] >= $maximum_amount ) {
					
					$checkout_page_id = wc_get_page_id( 'checkout' );
					$checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';
					$response['redirect'] = $checkout_page_url;
					$response['result'] = 'failed';
					wc_add_notice( __( 'Total amount should be equal or less than '.$maximum_amount.' '.$order_currency, 'ipint' ), 'error' );
					return;
				} else {
					
					$checkout_page_id = wc_get_page_id( 'checkout' );
					$checkout_page_url = $checkout_page_id ? get_permalink( $checkout_page_id ) : '';
					$response['redirect'] = $checkout_page_url;
					$response['result'] = 'failed';
					wc_add_notice( __( 'Total amount should be equal or greater than '.$minimum_amount.' '.$order_currency, 'ipint' ), 'error' );
					return;
				}
			}
		}
	}
	public function ipit_update_order_data($order_id){
		$ipint_order_fields = [
			'invoice_id',
			// 'invoice_creation_time',
			'transaction_status',
			'transaction_hash',
			'transaction_onclick',
			'transaction_time',
			'transaction_crypto',
			'invoice_crypto_amount',
			'invoice_amount_in_usd',
			'invoice_amount_in_local_currency',
			'received_crypto_amount',
			'received_amount_in_usd',
			'received_amount_in_local_currency',
			'wallet_address',
			'blockchain_transaction_status',
			'blockchain_confirmations',
			'company_name',
			'merchant_website'
		];
		foreach($ipint_order_fields as $key => $field){			
			update_post_meta( $order_id, 'ipint_'.$field, sanitize_text_field($_GET[$field]) );
		}
	}
	public function ipit_update_order_response_data($order_id, $response_data){

		$ipint_order_fields = [
			'transaction_status',
			'transaction_hash',
			'transaction_onclick',
			'transaction_time',
			'transaction_crypto',
			'invoice_crypto_amount',
			'invoice_amount_in_usd',
			'invoice_amount_in_local_currency',
			'received_crypto_amount',
			'received_amount_in_usd',
			'received_amount_in_local_currency',
			'wallet_address',
			'blockchain_transaction_status',
			'blockchain_confirmations'
		];
		foreach($ipint_order_fields as $key => $field){			
			update_post_meta( $order_id, 'ipint_'.$field, sanitize_text_field( $response_data[$field] ) );
		}
	}
	/**
	 * Process subscription payment.
	 *
	 * @param  float     $amount
	 * @param  WC_Order  $order
	 * @return void
	 */
	public function process_subscription_payment( $amount, $order ) {
		$order->payment_complete();
	}
	public function get_redirect_url($order_id){
		// $redirect_url = get_permalink('ipintpayment');
		$redirect_url = home_url();
		$redirect_url .= "/ipintpayment/". $order_id;
		// return apply_filter('change_ipint_return_url', $redirect_url);
		return esc_url( $redirect_url );
	}
	public function get_callback_url($order_id){
		$redirect_url = home_url();
		$redirect_url .= "/ipintcallback/". $order_id;
		// return apply_filter('change_ipint_return_url', $redirect_url);
		return esc_url( $redirect_url );
	}
	public function get_ipint_api_url() {
		if( $this->get_option('mode') == 'live' ){
			return esc_url( IPINT_LIVE_API_URL );
		} else {
			return esc_url( IPINT_TEST_API_URL );
		} 
	}
	public function get_ipint_payment_redirect_url() {
		if( $this->get_option('mode') == 'live' ){
			return esc_url( IPINT_PAYMENT_URL );
		} else {
			return esc_url( IPINT_PAYMENT_URL );
		} 
	}	
	public function get_ipint_payment_url($noproxy = false) {
		
		$api_url = $this->get_ipint_api_url() .'/checkout';
		if( !$noproxy && !empty(IPINT_PROXY_URL) ){
			return esc_url( IPINT_PROXY_URL ."?api_url=". urlencode($api_url) );
		} else {
			return esc_url( $api_url );
		}
	}
	public function get_ipint_invoice_url($noproxy = false) {
		
		$api_url = $this->get_ipint_api_url() .'/invoice';
		if( !$noproxy && !empty(IPINT_PROXY_URL) ){
			return esc_url( IPINT_PROXY_URL ."?api_url=". urlencode($api_url) );
		} else {
			return esc_url( $api_url );
		}
	}
	
	public function get_ipint_redirect_url($session_id) {
		if( $this->get_option('mode') == 'live' ){
			$redirect_url = $this->get_ipint_payment_redirect_url() .'/checkout?id='. $session_id;
		} else {
			$redirect_url = $this->get_ipint_payment_redirect_url() .'/test-checkout?id='. $session_id;
		}
		return esc_url( $redirect_url );
	}	
	public function get_ipint_api_key() {
		return $this->get_option('api_key');
	}
	public function get_ipint_secret_key() {
		return $this->get_option('secret_key');
	}
	
}
