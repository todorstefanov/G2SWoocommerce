<?php
session_start();

class WC_G2S extends WC_Payment_Gateway {
	public function __construct() {
		$this->id            = 'g2s';
		$this->plugin_path   = ABSPATH . 'wp-content/plugins/woocommerce-g2s/';
		$this->medthod_title = 'Gate2Shop';
		$this->has_fields    = false;

		$this->init_form_fields();
		$this->init_settings();
		$this->title                 = $this->settings['title'];
		$this->description           = $this->settings['description'];
		$this->merchant_id           = $this->settings['merchant_id'];
		$this->merchantsite_id       = $this->settings['merchantsite_id'];
		$_SESSION['merchant_id']     = $this->merchant_id;
		$_SESSION['merchantsite_id'] = $this->merchantsite_id;
		$this->plugin_url            = get_site_url() . '/wp-content/plugins/woocommerce-g2s/';

		$this->secret         = $this->settings['secret'];
		$this->test           = $this->settings['test'];
		$this->liveurl        = 'https://secure.gate2shop.com/ppp/purchase.do';
		$this->testurl        = 'https://ppp-test.gate2shop.com/ppp/purchase.do';
		$this->liveWSDL       = 'https://secure.gate2shop.com/PaymentOptionInfoService?wsdl';
		$this->testWSDL       = 'https://ppp-test.gate2shop.com/PaymentOptionInfoService?wsdl';
		$this->icon           = $this->plugin_url . "icons/g2s.png";
		$this->msg['message'] = "";
		$this->msg['class']   = "";


		//add_action('init', array(&$this, 'check_g2s_response'));
		if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				&$this,
				'process_admin_options'
			) );
		} else {
			add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
		}
		add_action( 'woocommerce_checkout_process', array( $this, 'g2s_checkout_process' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
		add_action( 'woocommerce_api_wc_gateway_g2s', array( $this, 'process_g2s_notification' ) );

	}

	function init_form_fields() {

		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'g2s' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable G2S Payment Module.', 'g2s' ),
				'default' => 'no'
			),
			'title'           => array(
				'title'       => __( 'Title:', 'g2s' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'g2s' ),
				'default'     => __( 'Gate2Shop', 'g2s' )
			),
			'description'     => array(
				'title'       => __( 'Description:', 'g2s' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'g2s' ),
				'default'     => __( 'Pay securely by Credit or Debit card or local payment option through Gate2Shop secured payment page.', 'g2s' )
			),
			'merchant_id'     => array(
				'title'       => __( 'Merchant ID', 'g2s' ),
				'type'        => 'text',
				'description' => __( 'Merchant ID is provided by Gate2Shop.' )
			),
			'merchantsite_id' => array(
				'title'       => __( 'Merchant Site ID', 'g2s' ),
				'type'        => 'text',
				'description' => __( 'Merchant Site ID is provided by Gate2Shop.' )
			),
			'secret'          => array(
				'title'       => __( 'Secret key', 'g2s' ),
				'type'        => 'text',
				'description' => __( 'Secret key is provided by Gate2Shop', 'g2s' ),
			),
			'test'            => array(
				'title'   => __( 'Test mode', 'g2s' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable test mode', 'g2s' ),
				'default' => 'no'
			)
		);
	}

	public function admin_options() {
		echo '<h3>' . __( 'Gate2Shop ', 'g2s' ) . '</h3>';
		echo '<p>' . __( 'G2S payment option' ) . '</p>';
		echo '<table class="form-table">';
		// Generate the HTML For the settings form.
		$this->generate_settings_html();
		echo '</table>';

	}

	function setEnvironment() {
		if ( $this->test === 'yes' ) {
			$this->useWSDL = $this->testWSDL;
			$this->URL     = $this->testurl;
		} else {
			$this->useWSDL = $this->liveWSDL;
			$this->URL     = $this->liveurl;
		}
	}

	function formatLocation( $locale ) {
		switch ( $locale ) {
			case 'en_GB':
				return 'en';
				break;
			case 'de_DE':
				return 'de';
				break;
		}
	}

	/**
	 *  Add fields on the payment page
	 **/
	function payment_fields() {
		if ( $this->description ) {
			echo wpautop( wptexturize( $this->description ) ) . "<br />";
		}
		//$this->has_fields = true;
		$apms = $this->getAPMS();
		if ( $apms ) {
			echo $apms;
		}
	}

	function getAPMS() {
		include( 'nusoap/nusoap.php' );
		global $woocommerce;
		$cl = new WC_Customer;
		$this->setEnvironment();
		$client                        = new nusoap_client( $this->useWSDL, true );
		$parameters                    = array(
			"merchantId"     => $this->merchant_id,
			"merchantSiteId" => $this->merchantsite_id
		);
		$parameters["amount"]          = "1";
		$parameters["languageCode"]    = $this->formatLocation( get_locale() );
		$parameters["gwMerchantName"]  = "";
		$parameters["gwPassword"]      = "";
		$parameters["currencyIsoCode"] = get_woocommerce_currency();
		$parameters["countryIsoCode"]  = ( $_SESSION['g2s_country'] == '' ) ? $cl->get_country() : $_SESSION['g2s_country'];
		try {
			$soap_response = $client->call( 'getMerchantSitePaymentOptions', $parameters );

			return $this->ShowAPMs( $soap_response );
		} catch ( nusoap_fault $fault ) {
			die( 'error' );

			return false;
		}
	}

	function ShowAPMs( $apms ) {
		$data = '';
		if ( isset( $apms["PaymentOptionsDetails"]["displayInfo"] ) ) {
			$logo  = $this->plugin_url . 'icons/' . utf8_encode( $apms["PaymentOptionsDetails"]["optionName"] ) . '.png';
			$logo2 = $this->plugin_path . 'icons/' . utf8_encode( $apms["PaymentOptionsDetails"]["optionName"] ) . '.png';
			$data .= '
				<input id="payment_method_' . $this->id . '_' . $apms["PaymentOptionsDetails"]["optionName"] . '" type="radio" 
					class="input-radio" name="payment_method_g2s" 
					value="' . $this->id . '_' . $apms["PaymentOptionsDetails"]["optionName"] . '"  />
				<label for="payment_method_' . $this->id . '_' . $apms["PaymentOptionsDetails"]["optionName"] . '">
					' . utf8_encode( $apms["PaymentOptionsDetails"]["displayInfo"]["paymentOptionDisplayName"] ) . ' ';
			if ( file_exists( $logo2 ) ) {
				$data .= '<img src="' . $this->plugin_url . 'icons/' . utf8_encode( $apms["PaymentOptionsDetails"]["optionName"] ) . '.png" height="30px">';
			}
			$data .= '</label>';
		} else if ( isset( $apms["PaymentOptionsDetails"] ) ) {
			foreach ( $apms["PaymentOptionsDetails"] as $apmDetails ) {
				$logo  = $this->plugin_url . 'icons/' . utf8_encode( $apmDetails["optionName"] ) . '.png';
				$logo2 = $this->plugin_path . 'icons/' . utf8_encode( $apmDetails["optionName"] ) . '.png';
				$data .= '
				<div style="paddin:10px 0px;">
					<input id="payment_method_' . $this->id . '_' . $apmDetails["optionName"] . '" type="radio" 
						class="input-radio" name="payment_method_g2s" 
						value="' . $this->id . '_' . $apmDetails["optionName"] . '"  />
					<label for="payment_method_' . $this->id . '_' . $apmDetails["optionName"] . '" >
						' . utf8_encode( $apmDetails["displayInfo"]["paymentOptionDisplayName"] ) . ' ';
				if ( file_exists( $logo2 ) ) {
					$data .= '<img src="' . $logo . '" style="height:20px;">';
				}
				$data .= '</label>
				</div>';
			}
		}

		return $data;
	}


	/**
	 * Receipt Page
	 **/
	function receipt_page( $order ) {
		//echo '<p>'.__('Thank you for your order, we are redirecting you to Gate2Shop secured payment page.', 'g2s').'</p>';
		echo $this->generate_g2s_form( $order );
	}

	/**
	 * Generate payu button link
	 **/
	public function generate_g2s_form( $order_id ) {
		global $woocommerce;
		$TimeStamp  = date( 'Ymdhis' );
		$order      = new WC_Order( $order_id );
		$items      = $order->get_items();
		$item_price = 0;
		$i          = 1;
		$this->setEnvironment();
		foreach ( $items as $item ) {
			$params[ 'item_name_' . $i ]     = $item['name'];
			$params[ 'item_number_' . $i ]   = $item["item_meta"]['_product_id'][0];
			$params[ 'item_amount_' . $i ]   = number_format( $item['line_total'] / (int) $item['qty'], 2, '.', '' );
			$params[ 'item_quantity_' . $i ] = $item['qty'];
			$item_price += number_format( ( $item['line_total'] ), 2, '.', '' );
			$i ++;
		}
		$item_price_total        = number_format( $item_price, 2, '.', '' );
		$params['handling']      = number_format( ( $order->order_total - $item_price_total ), 2, '.', '' );
		$params['numberofitems'] = $i - 1;

		$params['merchant_id']      = $this->merchant_id;
		$params['merchant_site_id'] = $this->merchantsite_id;
		$params['time_stamp']       = $TimeStamp;
		$params['encoding']         = 'utf8';
		$params['version']          = '4.0.0';

		$payment_page = get_permalink( woocommerce_get_page_id( 'pay' ) );
		if ( get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
			$payment_page = str_replace( 'http:', 'https:', $payment_page );
		}
		$notify_url = add_query_arg( array( 'wc-api' => 'WC_Gateway_G2s' ), home_url( '/' ) );

		$params['success_url'] = $this->get_return_url();
		$params['pending_url'] = $this->get_return_url();
		$params['error_url']   = $payment_page;
		$params['back_url']    = $payment_page;
		$params['notify_url']  = $notify_url;

		$params['invoice_id']         = $order_id . '_' . $TimeStamp;
		$params['merchant_unique_id'] = $order_id . '_' . $TimeStamp;
		$params['first_name']         = htmlspecialchars($order->billing_first_name, ENT_QUOTES, "UTF-8",true);
		$params['last_name']          = htmlspecialchars($order->billing_last_name, ENT_QUOTES, "UTF-8",true);

		$params['address1']           = htmlspecialchars($order->billing_address_1, ENT_QUOTES, "UTF-8",true);

		$params['address2']           = $order->billing_address_2;
		$params['zip']                = $order->billing_zip;
		$params['city']               = $order->billing_city;
		$params['state']              = $order->billing_state;
		$params['country']            = $order->billing_country;
		$params['phone1']             = $order->billing_phone;
		$params['email']              = $order->billing_email;
		$params['user_token_id']      = md5( $order->billing_email );
		$params['payment_method']     = str_replace( $this->id . '_', '', $_SESSION['g2s_subpayment'] );
		$params['merchantLocale']     = $this->formatLocation( get_locale() );

		$params['total_amount'] = $order->order_total;
		$params['currency']     = get_woocommerce_currency();
		$for_hash               = '';
		foreach ( $params as $k => $v ) {
			$for_hash .= $v;
		}
		$params['checksum'] = md5( stripslashes( $this->secret . $for_hash ) );

		$params_array = array();
		foreach ( $params as $key => $value ) {
			$params_array[] = '<input type="hidden" name="' . htmlspecialchars( $key ) . '" value="' . htmlspecialchars( $value ) . '"/>';
		}

		return '<form action="' . $this->URL . '" method="post" id="g2s_payment_form">
            ' . implode( '', $params_array ) . '
            <noscript><input type="submit" class="button-alt" id="submit_g2s_payment_form" value="' . __( 'Pay via Gate2shop', 'g2s' ) . '" /> 
			<a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __( 'Cancel order &amp; restore cart', 'g2s' ) . '</a>
			 </noscript>
            <script type="text/javascript">
			jQuery(function(){
			jQuery("body").block(
					{
						message: "<img src=\"' . $this->plugin_url . '/icons/loading.gif\" alt=\"Redirecting\" style=\"width:100px;float:left; margin-right: 10px;\" />' . __( 'Thank you for your order. We are now redirecting you to Gate2shop Payment Gateway to make payment.', 'g2s' ) . '",
							overlayCSS:
							{
								background: "#fff",
									opacity: 0.6
						},
				css: {
					padding:        20,
						textAlign:      "center",
						color:          "#555",
						border:         "3px solid #aaa",
						backgroundColor:"#fff",
						cursor:         "wait",
						lineHeight:"32px"
				}
				});
				jQuery("#g2s_payment_form").submit();
				
				});</script>
						</form>';


	}

	/**
	 * Process the payment and return the result
	 **/
	function process_payment( $order_id ) {
		$order        = new WC_Order( $order_id );
		$redirect_url = add_query_arg( array(
			'order' => $order->id,
			'key'   => $order->order_key,
		), get_permalink( woocommerce_get_page_id( 'pay' ) ) );

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}

	function g2s_checkout_process() {
		$_SESSION['g2s_subpayment'] = $_POST['payment_method_g2s'];

		return true;
	}

	/**
	 * Check for valid callback
	 **/
	function process_g2s_notification() {
		global $woocommerce;

		try {
			$arr      = explode( "_", $_REQUEST['invoice_id'] );
			$order_id = $arr[0];
			$order    = new WC_Order( $order_id );
			if ( $order ) {
				$verified = false;
				// md5sig validation
				if ( $this->secret ) {
					$hash    = $this->secret . $_REQUEST['ppp_status'] . $_REQUEST['PPP_TransactionID'];
					$md5hash = md5( $hash );
					$md5sig  = $_REQUEST['responsechecksum'];
					if ( $md5hash == $md5sig ) {
						$verified = true;
					}
				}
			}


			if ( $verified ) {
				$status          = $_REQUEST['Status'];
				$transactionType = $_REQUEST['transactionType'];
				echo "Transaction Type: " . $transactionType;
				if ( ( $transactionType == 'Void' ) || ( $transactionType == 'Chargeback' ) || ( $transactionType == 'Credit' ) ) {
					$status = 'CANCELED';
				}

				switch ( $status ) {
					case 'CANCELED':
						$message              = 'Payment status changed to:' . $transactionType . '. PPP_TransactionID = ' . $_REQUEST['PPP_TransactionID'] . ", Status = " . $status . ', GW_TransactionID = ' . $this->request->get['TransactionID'];
						$this->msg['message'] = $message;
						$this->msg['class']   = 'woocommerce_message';
						$order->update_status( 'failed' );
						$order->add_order_note( 'Failed' );
						$order->add_order_note( $this->msg['message'] );
						break;

					case 'APPROVED':
						$message              = 'The amount has been authorized and captured by gate2shop. PPP_TransactionID = ' . $_REQUEST['PPP_TransactionID'] . ", Status = " . $status . ", TransactionType = " . $transactionType . ', GW_TransactionID = ' . $_REQUEST['TransactionID'];
						$this->msg['message'] = $message;
						$this->msg['class']   = 'woocommerce_message';
						$order->payment_complete();
						$order->add_order_note( 'Gate2shop payment is successful<br/>Unnique Id: ' . $_REQUEST['PPP_TransactionID'] );
						$order->add_order_note( $this->msg['message'] );
						$woocommerce->cart->empty_cart();
						break;

					case 'ERROR':
					case 'DECLINED':
						$message              = 'Payment failed. PPP_TransactionID = ' . $this->request->get['PPP_TransactionID'] . ", Status = " . $status . ", Error code = " . $_REQUEST['ErrCode'] . ", Message = " . $this->request->get['message'] . ", TransactionType = " . $transactionType . ', GW_TransactionID = ' . $_REQUEST['TransactionID'];
						$this->msg['message'] = $message;
						$this->msg['class']   = 'woocommerce_message';
						$order->update_status( 'failed' );
						$order->add_order_note( 'Failed' );
						$order->add_order_note( $this->msg['message'] );
						break;

					case 'PENDING':
						$message              = 'Payment is still pending ' . $_REQUEST['PPP_TransactionID'] . ", Status = " . $status . ", TransactionType = " . $transactionType . ', GW_TransactionID = ' . $_REQUEST['TransactionID'];
						$this->msg['message'] = $message;
						$this->msg['class']   = 'woocommerce_message woocommerce_message_info';
						$order->add_order_note( 'Gate2shop payment status is pending<br/>Unnique Id: ' . $_REQUEST['PPP_TransactionID'] );
						$order->add_order_note( $this->msg['message'] );
						$order->update_status( 'on-hold' );
						$woocommerce->cart->empty_cart();
						break;
				}

			} else {
				$this->msg['class']   = 'error';
				$this->msg['message'] = "Security Error. Illegal access detected";
				$order->update_status( 'failed' );
				$order->add_order_note( 'Failed' );
				$order->add_order_note( $this->msg['message'] );
			}
			add_action( 'the_content', array( &$this, 'showMessage' ) );
		} catch ( Exception $e ) {
			$msg = "Error";
		}

	}

	function showMessage( $content ) {
		return '<div class="box ' . $this->msg['class'] . '-box">' . $this->msg['message'] . '</div>' . $content;
	}


	// get all pages
	function get_pages( $title = false, $indent = true ) {
		$wp_pages  = get_pages( 'sort_column=menu_order' );
		$page_list = array();
		if ( $title ) {
			$page_list[] = $title;
		}
		foreach ( $wp_pages as $page ) {
			$prefix = '';
			// show indented child pages?
			if ( $indent ) {
				$has_parent = $page->post_parent;
				while ( $has_parent ) {
					$prefix .= ' - ';
					$next_page  = get_page( $has_parent );
					$has_parent = $next_page->post_parent;
				}
			}
			// add to page list array array
			$page_list[ $page->ID ] = $prefix . $page->post_title;
		}

		return $page_list;
	}

	function checkAdvancedCheckSum() {
		$str = md5( $this->secret . $_GET['totalAmount'] . $_GET['currency'] . $_GET['responseTimeStamp'] . $_GET['PPP_TransactionID'] . $_GET['Status'] . $_GET['productId'] );
		if ( $str == $_GET['advanceResponseChecksum'] ) {
			return true;
		} else {
			return false;
		}
	}


}

?>