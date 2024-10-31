<?php
class SEPANET extends WC_Payment_Gateway 
{

	// Setup our Gateway's id, description and other values
	function __construct() 
	{		
		// The global ID for this Payment method
		$this->id = "sepanet";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = __( "SEPA.net", 'sepanet' );
		// $this->order_button_text  = __( 'Buchung abschließen', 'woocommerce' );

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = __( "SEPA.net Payment Gateway Plug-in for WooCommerce", 'sepanet' );

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = __( "SEPA.net", 'sepanet' );

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout 
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;		

		// Supports the default credit card form
		// $this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();
		
		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}
		
		// Lets check for SSL
		// add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
		
		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}		
	}

	

	// Build the administration fields for this specific Gateway
	public function init_form_fields() 
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'activate / deactivate', 'sepanet' ),
				'label'		=> __( 'Activate this payment gateway', 'sepanet' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', 'sepanet' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'sepanet' ),
				'default'	=> __( 'Direct debit procedure via SEPA.net', 'sepanet' ),
			),
			'description' => array(
				'title'		=> __( 'Description', 'sepanet' ),
				'type'		=> 'textarea',
				'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'sepanet' ),
				'default'	=> __( 'Pay fast and easy with the SEPA.net direct debit procedure. Please ensure that you have provided a mobile phone number. You will receive a text message with a TAN for authentication purposes.', 'sepanet' ),
				'css'		=> 'max-width:350px;'
			),
			'mp_offerer_id' => array(
				'title'		=> __( 'sepanet Offerer ID', 'sepanet' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Your SEPA.net customer number.', 'sepanet' ),
			),
			'mp_securitykey' => array(
				'title'		=> __( 'SEPA.net security key', 'sepanet' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'The security key, which you have received during the registration process.', 'sepanet' ),
			),
			'environment' => array(
				'title'		=> __( 'test mode', 'sepanet' ),
				'label'		=> __( 'test mode', 'sepanet' ),
				'type'		=> 'checkbox',
				'description' => __( 'Place the payment gateway in test mode.', 'sepanet' ),
				'default'	=> '0',
			),
			'billing' => array(
				'title'		=> __( 'SEPA.net bill', 'sepanet' ),
				'label'		=> __( 'SEPA.net sending a bill', 'sepanet' ),
				'type'		=> 'checkbox',
				'description' => __( 'Should SEPA.net send a bill?', 'sepanet' ),
				'default'	=> '0',
			)
		);
	}

	public function payment_fields() 
	{
		wp_register_script('wc-sepanet', plugins_url('wc-sepanet.js', __FILE__), array('jquery'), '', true);
    	wp_enqueue_script('wc-sepanet');    

		$oid = $this->mp_offerer_id;	//Ihre sepanet-Kundennummer
		$sec = md5($this->mp_securitykey);	//Ihr sepanet-Security-String

    	wp_localize_script( 'wc-sepanet', 'postsepanet', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'oid' => $oid,
			'sec' => $sec
		));

		$default_args = array(
			'fields_have_names' => true, // Some gateways like stripe don't need names as the form is tokenized.
		);

		$args = wp_parse_args( $args, apply_filters( 'woocommerce_iban_form_args', $default_args, $this->id ) );

		$default_fields = array(
			'iban-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-iban">' . __( 'IBAN', 'sepanet' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-iban" class="input-text wc-sepanet-iban" type="text" autocomplete="off" placeholder="•••• •••••••• ••••••••••" name="' . ( $args['fields_have_names'] ? $this->id . '-iban' : '' ) . '" />
			</p>',
			'bic-field' => '<p class="form-row form-row-wide">
				<label for="' . esc_attr( $this->id ) . '-bic">' . __( 'BIC', 'sepanet' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-bic" class="input-text wc-sepanet-bic" type="text" autocomplete="off" placeholder="•••••••••••" name="' . ( $args['fields_have_names'] ? $this->id . '-bic' : '' ) . '" />
			</p>',
			'tan-button' => '<p class="form-row form-row-wide">'.__('SEPA.net requires for the validation a TAN. Please enter your mobile phone number and click subsequently on the "Request TAN" button. You will receive a text message, which contains a TAN, and which needs to be entered in the corresponding field.', 'sepanet').'</p><p class="form-row form-row-first">
				<label for="' . esc_attr( $this->id ) . '-tansend">' . __( 'Request TAN', 'sepanet' ) . ' <span class="required">*</span></label>
				<a id="' . esc_attr( $this->id ) . '-tansend" class="button wc-sepanet-tansend" href="#">' . __( 'Request TAN', 'sepanet' ) . '</a>
			</p>',
			'tan-field' => '<p class="form-row form-row-last">
				<label for="' . esc_attr( $this->id ) . '-tan">' . __( 'TAN', 'sepanet' ) . ' <span class="required">*</span></label>
				<input id="' . esc_attr( $this->id ) . '-tan" class="input-text wc-sepanet-tan" type="text" autocomplete="off" placeholder="••••" name="' . ( $args['fields_have_names'] ? $this->id . '-tan' : '' ) . '" />
			</p>'
		);

		$fields = wp_parse_args( $fields, apply_filters( 'woocommerce_iban_form_fields', $default_fields, $this->id ) );
		?>
		<fieldset id="<?php echo $this->id; ?>-cc-form">
			<?php do_action( 'woocommerce_iban_form_start', $this->id ); ?>
			<?php
				foreach ( $fields as $field ) {
					echo $field;
				}
			?>
			<?php do_action( 'woocommerce_iban_form_end', $this->id ); ?>
			<div class="clear"></div>
		</fieldset>
		<?php
	}

	public function validate_fields() {
		$valid = true; 
		if(empty($_POST['sepanet-tan']))
		{
			wc_add_notice( __('Please enter the transaction authentication number (TAN).', 'sepanet'), 'error' );
			$valid = false; 
		}
		if(empty($_POST['sepanet-iban']))
		{
			wc_add_notice( __('Please enter your IBAN.', 'sepanet'), 'error' );
			$valid = false; 
		}
		if(empty($_POST['sepanet-bic']))
		{
			wc_add_notice( __('Please enter your BIC.', 'sepanet'), 'error' );
			$valid = false; 
		}
		return $valid; 
	}

	private function sepanet_http_build_query($array)
	{
		$url_create_params = '';
		foreach($array as $key=>$val)
		{
			if(empty($url_create_params)) $url_create_params .= '?'.$key.'='.urlencode($val);
			else
			{
				$url_create_params .= '&'.$key.'='.urlencode($val);
			}
		}
		return $url_create_params;
	}

	private function formatAmount($amount)
	{
		$amount=str_replace(",",".",$amount);
		if(is_numeric($amount)){
			$amount=sprintf("%01.2f",$amount);
		}
	    
		return $amount;
	}

	/**
	 * Get gateway icon.
	 * @return string
	 */
	public function get_icon() {
		$icon_html = '';
		$icon_html .= '<img src="http://www.sepa.net/downloads/sepa_88x30.jpg" alt="' . esc_attr__( 'sepanet', 'woocommerce' ) . '" />';
		// $icon_html .= sprintf( '<a href="%1$s" class="about_paypal" onclick="javascript:window.open(\'%1$s\',\'WIPaypal\',\'toolbar=no, location=no, directories=no, status=no, menubar=no, scrollbars=yes, resizable=yes, width=1060, height=700\'); return false;" title="' . esc_attr__( 'Was ist sepanet?', 'woocommerce' ) . '">' . esc_attr__( 'Was ist sepanet?', 'woocommerce' ) . '</a>', esc_url( 'http://www.sepanet.de' ) );

		return apply_filters( 'woocommerce_gateway_icon', $icon_html, $this->id );
	}

	// Submit payment and handle response
	public function process_payment( $order_id ) 
	{
		global $woocommerce;
		
		// Get this Order's information so that we know
		// who to charge and how much
		$customer_order = new WC_Order( $order_id );

		$address = $customer_order->get_address();
		$blog_title = get_bloginfo('wpurl');
		$regEx="/^((http|https):\/\/)?([\w\-_]+\.[\w\-_]|lokale Testseite)/i";
		if(!preg_match($regEx,$blog_title))
		{
			$blog_title = 'www.'.trim(get_bloginfo()).'.de';
		}
		$productName = '';
		$product_id = '0';
		$testmode = ( $this->environment == "yes" ) ? '1' : '0';
		$billing = ( $this->billing == "yes" ) ? '1' : '0';
		$items = $customer_order->get_items();
		if(!empty($items))
		{
			foreach($items as $key=>$val)
			{
				$productName .= $val['name'].';';
				$product_id = $val['product_id'];
			}
		}
		$sepanet_url = 'https://payment.sepa.net/capp/gateways';

		$tax = $this->formatAmount(19);
		$price = $this->formatAmount($customer_order->get_total());

		$oid = $this->mp_offerer_id;	//Ihre sepanet-Kundennummer
		$sec = md5($this->mp_securitykey);	//Ihr sepanet-Security-String	

		// $url_create = $sepanet_url.'/subscription_create?oid='.$oid.'&sec='.$sec.'&customer_phone='.$address['phone'].'&customer_email='.$address['email'].'&customer_name='.$address['first_name'].'&customer_iban='.$_POST['spyr_sepanet-iban'].'&customer_tan='.$_POST['spyr_sepanet-tan'].'&shopDomain='.$blog_title.'&productName='.$productName.'&productId='.$product_id.'&testmode='.$testmode.'';
		$url_create = $sepanet_url.'/subscription_create';

		$sub_create = array(
				'oid' => $oid,
				'sec' => $sec,
				'customer_phone' => $address['phone'],
				'customer_email' => $address['email'],
				'customer_name' => $address['first_name'].' '.$address['last_name'],
				'customer_iban' => $_POST['sepanet-iban'],
				'customer_tan' => $_POST['sepanet-tan'],
				'customer_bic' => $_POST['sepanet-bic'],
				'shopDomain' => $blog_title,
				'productName' => $productName,
				'productId' => $product_id,
				'productId' => $product_id,
				'testmode' => $testmode
			);
		
		$url_create_params = $this->sepanet_http_build_query($sub_create);
		$url_create = $url_create.$url_create_params;
	
		$response = wp_remote_post( $url_create, array(
			'method'    => 'POST',
			'body'      => http_build_query( array() ),
			'timeout'   => 90,
			'sslverify' => false,
		) );
		
		$xmlstring = wp_remote_retrieve_body( $response );		
		$xml = simplexml_load_string($xmlstring);
		
	    // Verarbeitung der API-Antwort
	    if($xml->result == 'error') // Ist ein Fehler aufgetreten?
	    {
	        $errormessage = __( 'Response error.', 'sepanet' );
	        foreach($xml->errors as $error) // Liste alle Fehler auf
	        { 
	            // Fehlerverarbeitung, muss angepasst werden.
	            foreach($error as $errstring)
	            {
	            	$errormessage .= (string)$errstring.';';
	            	wc_add_notice( __((string)$errstring), 'error' );
	           	}	                     
	        }
	        return false;
	    } 
	    else // Wenn alles OK ist
	    {
	    	$sub_id = (string)$xml->subscription_id;
	    	$url_exec = $sepanet_url.'/subscription_exec';
	    	$sub_exec = array(
				'oid' => $oid,
				'sec' => $sec,
				'subscription_id' => $sub_id,
				'price' => $price,
				'tax' => $tax,
				'billing' => $billing,
				'testmode' => $testmode
			);
			$url_exec_params = $this->sepanet_http_build_query($sub_exec);
			$url_exec = $url_exec.$url_exec_params;

			$response = wp_remote_post( $url_exec, array(
				'method'    => 'POST',
				'body'      => http_build_query( array() ),
				'timeout'   => 90,
				'sslverify' => false,
			) );
			
			$xmlstring = wp_remote_retrieve_body( $response );		
			$xml = simplexml_load_string($xmlstring);
			
		    // Verarbeitung der API-Antwort
		    if($xml->result == 'error') // Ist ein Fehler aufgetreten?
		    {
		        $errormessage = __( 'Response error.', 'sepanet' );
		        foreach($xml->errors as $error) // Liste alle Fehler auf
		        { 
		            // Fehlerverarbeitung, muss angepasst werden.
		            foreach($error as $errstring)
		            {
		            	$errormessage .= (string)$errstring.';';
		            	wc_add_notice( __((string)$errstring), 'error' );
		           	}
		        }
		        return false;
		    } 
		    else // Wenn alles OK ist
		    {
		    	$customer_order->add_order_note( __( 'The payment was succesfully sent to SEPA.net.', 'sepanet' ) );
												 
				// Mark order as Paid
				$customer_order->payment_complete();

				// Empty the cart (Very important step)
				$woocommerce->cart->empty_cart();

				// Redirect to thank you page
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $customer_order ),
				);
		    }
	    }
	}
}