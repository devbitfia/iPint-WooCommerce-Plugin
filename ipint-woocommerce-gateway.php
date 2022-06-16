<?php
/**
 * Plugin Name: iPint Payments Gateway
 * Plugin URI: https://ipint.io/
 * Description: Adds the iPint Payments gateway to your WooCommerce website.
 * Version: 1.0
 *
 * Author: iPint <help@ipint.io>
 *
 * Text Domain: ipint
 * Domain Path: /i18n/languages/
 *
 * Requires at least: 4.2
 * Tested up to: 6.0
 *
 */
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'IPINT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'IPINT_LIVE_API_URL',  'https://api.ipint.io:8003');
define( 'IPINT_TEST_API_URL',  'https://api.ipint.io:8002');
define( 'IPINT_PAYMENT_URL',  'https://ipint.io');
define( 'IPINT_PROXY_URL',  '');
/**
 * WC iPint Payment gateway plugin class.
 *
 * @class WC_Ipint_Payments
 */
class WC_Ipint_Payments {
	/**
	 * Plugin bootstrapping.
	 */
	public static function init() {
		// iPint Payments gateway class.
		add_action( 'plugins_loaded', array( __CLASS__, 'includes' ), 0 );
 
		add_filter( 'generate_rewrite_rules', array( __CLASS__, 'register_ipint_website_url2' ) );
		add_filter('query_vars', array( __CLASS__, 'ipint_register_query_vars' ) );
		add_action('template_redirect', array( __CLASS__, 'ipint_handle_order_received' ) );
		
		// Make the iPint Payments gateway available to WC.
		add_filter( 'woocommerce_payment_gateways', array( __CLASS__, 'add_gateway' ) );
		
		// to display meta fields in admin order detail page
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'ipint_display_order_data_in_admin' ) );
		// Display order meta fields on order received page
		add_action('woocommerce_thankyou', array( __CLASS__, 'ipint_display_order_data_in_thankyou_page') );
		// Display order meta fields on mail
		add_action('woocommerce_email_order_details', array( __CLASS__, 'ipint_mail_order_data'), 200, 4 );
		// add_action('woocommerce_checkout_process', array( __CLASS__, 'process_custom_payment' ));
		
	}
	public static function include_custom_order_status_to_reports( $statuses ){
	    // Adding the custom order status to the 3 default woocommerce order statuses
	    return array( 'wc-payment-processing', 'processing', 'in-progress', 'completed', 'on-hold' );
	}
	public static function register_payment_processing_order_status() {
		register_post_status( 'wc-payment-processing', array(
			'label'                     => _x('Payment Processing', 'ipint'),
			'public'                    => true,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => true,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Payment processing (%s)', 'Payment processing (%s)' )
		) );
	}
	// Add to list of WC Order statuses
	public static function add_payment_processing_to_order_statuses( $order_statuses ) {
		$new_order_statuses = array();
    	// add new order status after processing
		foreach ( $order_statuses as $key => $status ) {
			$new_order_statuses[ $key ] = $status;
			if ( 'wc-pending' === $key ) {
				$new_order_statuses['wc-payment-processing'] = _x('Payment processing', 'ipint');
			}
		}
		return $new_order_statuses;
	}
	/**
	 * Add the iPint Payment gateway to the list of available gateways.
	 *
	 * @param array
	 */
	public static function add_gateway( $gateways ) {
		$gateways[] = 'WC_Gateway_Ipint';
		return $gateways;
	}
	public static function add_payment_processing_to_bulk_actions_shop_order( $actions ) {
		$new_actions = array();
	    // Add new custom order status after processing
	    foreach ($actions as $key => $action) {
	        $new_actions[$key] = $action;
	        if ('trash' === $key) {
	        	$new_actions['wc-payment-processing'] = __( 'Payment Processing', 'ipint' );
	        }
	    }
	    return $new_actions;
	}
	// display the extra data in the order admin panel
	public static function ipint_display_order_data_in_admin( $order ){
		$order_data = self::get_ipint_meta_fields('admin');
			
	    echo '<div class="order_data_column">';
		echo '<h4> <label>'. __( "Order Data", "ipint" ).'</label></h4>';
		foreach( $order_data as $key => $value ){
			if( get_post_meta( $order->get_id(), $key, true ) != '' ){
				if( $key == 'ipint_transaction_time' ){
					$order_timestamp = get_post_meta( $order->get_id(), $key, true );
					$datetime = new DateTime("@$order_timestamp");
					$order_time = $datetime->format('d-m-Y H:i:s');
					echo '<p class="'.esc_attr($key).'"><strong>' . esc_html($value) . ': </strong>' . esc_html($order_time) . '</p>';
				} else if( $key == 'ipint_transaction_onclick' ){
					$transaction_onclick = get_post_meta( $order->get_id(), $key, true );
					if($transaction_onclick != ''){
			        	echo '<p><a href="'.esc_url($transaction_onclick).'" target="blank">'.esc_html($value).'</a></p>';
			        }
				} else{
					$meta_value = get_post_meta( $order->get_id(), $key, true );
					echo '<p class="'.esc_attr($key).'"><strong>' . esc_html($value) . ': </strong>' . esc_html($meta_value) . '</p>';
				}
			}
		}
		
	    echo '</div>';
	    echo '<style>.order_data_column{ width: fit-content !important;margin-top: 40px; }</style>';
	} 
	// display the extra data in the Thankyou page
	public static function ipint_display_order_data_in_thankyou_page( $order ){
		$order_data = self::get_ipint_meta_fields('frontend');
	    echo '<div class="order_data_table">';
	    echo '<table><thead><tr><td colspan="2">'.__( 'Payment Details', 'ipint' ).'</td></tr></thead><tbody>';
	    foreach( $order_data as $key => $value ){
	    	if( get_post_meta( $order, $key, true ) != '' ){
	    		if( $key == 'ipint_transaction_onclick' ){
	    			$transaction_onclick = get_post_meta( $order, $key, true );
	    			echo '<tr><td colspan="2"><a href="'.esc_url($transaction_onclick).'" target="blank">'. __("View on blockchain explorer", "ipint").'</a></td></tr>';
	    		}else{
	    			echo '<tr><td><strong>' . esc_html($value) . ': </strong></td><td>'. esc_html( get_post_meta( $order, $key, true ) ) .'</td></tr>';
	    		}
	    	}
		}
		echo '</tbody></table>';
	    echo '</div>';
	} 
	// Send order meta data on mail 
	public static function ipint_mail_order_data( $order, $sent_to_admin, $plain_text, $email ){
		$order_data = self::get_ipint_meta_fields('frontend');
	    
	    echo '<table><thead><tr><td colspan="2">'. __( 'Payment Details', 'ipint' ) .'</td></tr></thead><tbody>';
	    foreach( $order_data as $key => $value ){
			if( get_post_meta( $order, $key, true ) != '' ){
				if( $key == 'ipint_transaction_onclick' ){
	    			$transaction_onclick = get_post_meta( $order, $key, true );
	    			echo '<tr><td colspan="2"><a href="'.esc_url($transaction_onclick).'" target="blank">'. __('View on blockchain explorer', 'ipint') .'</a></td></tr>';
	    		}else{
	    			echo '<tr><td><strong>' . esc_html($value) . ': </strong></td><td>'. esc_html( get_post_meta( $order, $key, true ) ).'</td></tr>';
	    		}
			}
		}
		echo '</tbody></table>';
	    echo '</div>';
	} 
	/**
	 * Plugin includes.
	 */
	public static function includes() {
		// Make the WC_Gateway_Ipint class available.
		if ( class_exists( 'WC_Payment_Gateway' ) ) {
			require_once( 'includes/class-wc-gateway-ipint.php' );
			require_once( 'includes/class-wc-gateway-ipint-settings.php' );
			require_once( 'includes/functions.php' );
		}
	}
	/**
	 * Plugin url.
	 *
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}
	public static function register_ipint_website_url() {
		if(is_admin()){
			return false;
		}
		
		global $woocommerce;
		$checkout_url = $woocommerce->cart->get_checkout_url();
		$checkout_endpoint = str_replace(home_url(), "", $checkout_url);
		$checkout_endpoint = trim($checkout_endpoint, '/');
		// Create iPint redirect URL
		// add_rewrite_rule( $checkout_endpoint .'/ipint-payment/?$', 'index.php?ipint-payment=$matches[1]', 'top' );
		add_rewrite_rule( 'ipintpayment/?([^/]*)/?', 'index.php?ipintpayment=$matches[1]', 'top' );
		add_rewrite_rule( 'ipint-callback/([a-z0-9A-Z]+)[/]?$', 'index.php?ipintcallback=$matches[1]', 'top' );
		// add_rewrite_endpoint('ipint-payment', EP_ROOT | EP_PAGES);
		add_filter( 'query_vars', function( $query_vars ) {
			$query_vars[] = 'ipintpayment';
			$query_vars[] = 'ipint-callback';
			return $query_vars;
		} );
		
		add_action( 'template_include', function( $template ) {
			if ( get_query_var( 'ipintpayment' ) == false || get_query_var( 'ipintpayment' ) == '' ) {
				return $template;
			}
			return IPINT_PLUGIN_PATH . 'templates'. DIRECTORY_SEPARATOR .'ipint-website-redirect-url.php';
		} );
		function prefix_url_rewrite_templates() {
			if ( get_query_var( 'ipintpayment' ) ) {
				add_filter( 'template_include', function() {
					return IPINT_PLUGIN_PATH . 'templates'. DIRECTORY_SEPARATOR .'ipint-website-redirect-url.php';
				});
			}
		}
		add_action( 'template_redirect', 'prefix_url_rewrite_templates' );
		
	}
	public static function register_ipint_website_url2( $wp_rewrite ) {
		$new_rules = array(
			// 'ipintpayment/?$'  => 'index.php?page=ipintpayment',
			'ipintpayment/(\d+)/?$'  => sprintf('index.php?ipint_page=ipintpayment&order_id=%s', $wp_rewrite->preg_index(1)),
			// 'ipintcallback/?$' => 'index.php?page=ipintcallback',
			'ipintcallback/(\d+)/?$' => sprintf('index.php?ipint_page=ipintcallback&order_id=%s', $wp_rewrite->preg_index(1))
		);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
		return $wp_rewrite->rules;
	}
	public static function ipint_handle_order_received() {
		$page = get_query_var('ipint_page');
		$order_id = (int) get_query_var('order_id', 0);
		
		if ($page == 'ipintpayment' && !empty($order_id) && $order_id > 0) {
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$WC_Gateway_Ipint = new WC_Gateway_Ipint();
			
			// Mark as on-hold (we're awaiting the cheque)
			$order->update_status( 'wc-processing', __( 'Payment Processing', 'ipint' ));
			
			// Reduce stock levels
			wc_reduce_stock_levels( $order_id );
			$WC_Gateway_Ipint->ipit_update_order_data( $order_id );
			// $this->ipit_update_order_data($order_id);
			
			// Remove cart
			$woocommerce->cart->empty_cart();
			// die;
			$redirect_url = $order->get_checkout_order_received_url();
			wp_safe_redirect($redirect_url);
		} else if ($page == 'ipintcallback' && !empty($order_id) && $order_id > 0) {
			$post_body = file_get_contents('php://input');
			
			$post_body = json_decode($post_body);
			global $woocommerce;
			$order = new WC_Order( $order_id );
			$WC_Gateway_Ipint = new WC_Gateway_Ipint();
			$endpoint = $WC_Gateway_Ipint->get_ipint_invoice_url();
			$endpoint .= "?id=". get_post_meta($order_id, 'ipint_invoice_id', true);
			$nonce = intval(microtime(true) * 1000000);
			$api_path = '/invoice?id='. get_post_meta($order_id, 'ipint_invoice_id', true);
			$signature = '/api/'. $nonce . $api_path;
			$signature = hash_hmac('sha384', $signature, $WC_Gateway_Ipint->get_ipint_secret_key());
			$post_data = array(
				'method'      => 'GET',
				'timeout'     => 60,
				'redirection' => 5,
				'httpversion' => '1.0',
				'blocking'    => true,			
				'sslverify'   => false,
				'headers'     => array(
					'Content-Type' => 'application/json',
					'apikey'       => $WC_Gateway_Ipint->get_ipint_api_key(),
					'signature'    => $signature,
					'nonce'        => $nonce,
				),
			);
			$response = wp_remote_post( $endpoint, $post_data );
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} else {
				$response = json_decode( $response['body'], true );			
				wc_reduce_stock_levels( $order_id );
				
				$WC_Gateway_Ipint->ipit_update_order_response_data($order_id, $response['data']);
				$order->payment_complete();
			}
			die;
		}
	}
	
	/**
	 * Register custom query vars
	 *
	 * @param array $vars The array of available query variables
	 *
	 * @return array
	 *
	 * @link https://codex.wordpress.org/Plugin_API/Filter_Reference/query_vars
	 */
	public static function ipint_register_query_vars($vars){
		$vars[] = 'ipint_page';
		$vars[] = 'order_id';
		// $vars[] = 'ipintpayment';
		return $vars;
	}
	public static function add_ipint_website_return_url($query_vars) {
		$query_vars['ipint-payment'] = get_option( 'ipint_return_url', 'ipint-return-url' );
		return $query_vars;
	}
	public static function ipint_return_url_title($query_vars) {
		$title = __( 'iPint Payment Return URL', 'ipint' );
		return $title;
	}
	function process_custom_payment(){
		
		if($_POST['payment_method'] != 'payment_method')
			return;
		// if( !isset($_POST['mobile']) || empty($_POST['mobile']) )
		// 	wc_add_notice( __( 'Please add your mobile number', $this->domain ), 'error' );
		/*$order = wc_get_order( $order_id );
		$order_data  = $order->get_data(); 
		// Getting minimum amount
		$min_amount_api_url = $this->get_ipint_api_url().'/limits?preferred_fiat='.$order_data['currency'].'&api_key='.$this->get_ipint_api_key();
		$minimum_amount_response = wp_remote_post( $min_amount_api_url, array(
			'method'      => 'GET',
			'timeout'     => 60,
			'redirection' => 5,
			'httpversion' => '1.0',
			'blocking'    => true,			
			'sslverify'   => false
		) );*/
	}
	public static function get_ipint_meta_fields($show_as){
		if($show_as == 'frontend'){
			$meta_fields = array( 
				'ipint_transaction_status'                => _x('Transaction Status', 'ipint'),
				'ipint_invoice_amount_in_local_currency'  => _x('Invoice Amount(in Local Currency)', 'ipint'),
				'ipint_received_amount_in_local_currency' => _x('Received Amount(in Local Currency)', 'ipint'),
				'ipint_transaction_onclick'               => _x('View on blockchain explorer', 'ipint')
			);
		}else{
			$meta_fields = array( 
				'ipint_transaction_status'                => _x('Transaction Status', 'ipint'),
				'ipint_invoice_amount_in_usd'             => _x('Invoice Amount(in USD)', 'ipint'),
				'ipint_invoice_amount_in_local_currency'  => _x('Invoice Amount(in Local Currency)', 'ipint'),
				'ipint_received_amount_in_usd'            => _x('Received Amount(in USD)', 'ipint'),
				'ipint_received_amount_in_local_currency' => _x('Received Amount(in Local Currency)', 'ipint'),
				'ipint_transaction_time'                  => _x('Transaction Time', 'ipint'),
				'ipint_transaction_onclick'               => _x('View on blockchain explorer', 'ipint')
			);
		}
		return $meta_fields;
	}
	
}
WC_Ipint_Payments::init();
