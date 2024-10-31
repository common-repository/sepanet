<?php
/*
Plugin Name: SEPA.net - WooCommerce Gateway
Plugin URI: http://www.sepa.net/
Text Domain: sepanet
Description: Extends WooCommerce by Adding the SEPA.net Gateway.
Version: 1.1
Author: Janis Kosarew, GRÜN Software AG
Author URI: http://www.sepa.net/
*/

// Include our Gateway Class and Register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'spyr_sepanet_init', 0 );

function spyr_sepanet_init() 
{
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	
	// If we made it this far, then include our Gateway Class
	include_once( 'woocommerce-sepanet.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'spyr_add_sepanet_gateway' );

	function spyr_add_sepanet_gateway( $methods ) 
	{
		$methods[] = 'SEPANET';
		return $methods;
	}
}


// Add custom action links
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'spyr_sepanet_action_links' );
function spyr_sepanet_action_links( $links ) 
{
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'sepanet' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}

add_action( 'wp_ajax_nopriv_post_sepanet_tansend', 'post_sepanet_tansend' );
add_action( 'wp_ajax_post_sepanet_tansend', 'post_sepanet_tansend' );

function post_sepanet_tansend() 
{
	$sepanet_url = 'https://payment.sepa.net/capp/gateways';

	$oid = $_POST['oid'];	//Ihre sepanet-Kundennummer
	$sec = $_POST['sec'];	//Ihr sepanet-Security-String
	$phone = $_POST['phone'];	//Ihr sepanet-Security-String

	$url_tan = $sepanet_url.'/tan_send?sec='.$sec.'&oid='.$oid.'&customer_phone='.$phone;
	$tan_post = array(
			// 'oid' => $oid,
			// 'sec' => $sec,
			// 'customer_phone' => $phone
		);

	$response = wp_remote_post( $url_tan, array(
		'method'    => 'POST',
		'body'      => http_build_query( $tan_post ),
		'timeout'   => 90,
		'sslverify' => false,
	) );

	if ( is_wp_error( $response ) ) 
			throw new Exception( __( 'We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience.', 'sepanet' ) );

	if ( empty( $response['body'] ) )
		throw new Exception( __( 'sepanet Response was empty.', 'sepanet' ) );

	$xml = wp_remote_retrieve_body( $response );
	$xml = simplexml_load_string($xml);

    // Verarbeitung der API-Antwort
    if($xml->result == 'error') // Ist ein Fehler aufgetreten?
    {
        $errormessage = __( 'Response error.', 'sepanet' );
        foreach($xml->errors->error as $error) // Liste alle Fehler auf
        { 
            // Fehlerverarbeitung, muss angepasst werden.
            $errormessage .= $error.', ';                
        }
        throw new Exception( $errormessage, 'sepanet' );
    } 
    else // Wenn alles OK ist
    { 
        echo 'true';
    }
}