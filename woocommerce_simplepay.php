<?php
   /*
	   Plugin Name: SimplePay WooCommerce Fizetési Felület
	   Description: A Simplepay WooCommerce Payment Gateway lehetővé teszi a Verve Card, a MasterCard és a Visa kártya használatával történő helyi és nemzetközi átutalást.
	   Version: 2.2.0
	   Author: Balla Sándor
   */

   if ( ! defined( 'ABSPATH' ) )
	  exit;

   add_action( 'plugins_loaded', 'we_wc_simplepay_init', 0 );

   function we_wc_simplepay_init() {

	  if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	  /**
	   * Gateway class
	   */
	  class WC_We_SimplePay_Gateway extends WC_Payment_Gateway {

		 public function __construct() {

			$this->id 					= 'we_simplepay_gateway';
			$this->icon 				= apply_filters( 'woocommerce_simplepay_icon', plugins_url( 'assets/images/simplepay-icon.png' , __FILE__ ) );
			$this->has_fields 			= false;
			$this->order_button_text    = 'Fizetés';
			$this->notify_url        	= WC()->api_request_url( 'WC_We_SimplePay_Gateway' );
			$this->method_title     	= 'SimplePay';
			$this->method_description  	= 'Fizető eszközök: MasterCard, Visa and Verve Cards';

			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title 				= $this->get_option( 'title' );
			$this->description 			= $this->get_option( 'description' );
			$this->logo_url				= $this->get_option( 'logo_url' );
			$this->testmode             = $this->get_option( 'testmode' ) === 'yes' ? true : false;

			$this->public_test_key  	= $this->get_option( 'public_test_key' );
			$this->private_test_key  	= $this->get_option( 'private_test_key' );

			$this->public_live_key  	= $this->get_option( 'public_live_key' );
			$this->private_live_key  	= $this->get_option( 'private_live_key' );

			$this->public_key      		= $this->testmode ? $this->public_test_key : $this->public_live_key;
			$this->private_key      	= $this->testmode ? $this->private_test_key : $this->private_live_key;

			//Actions
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
			add_action( 'woocommerce_receipt_we_simplepay_gateway', array( $this, 'receipt_page' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_we_simplepay_gateway', array( $this, 'charge_token' ) );

			// Check if the gateway can be used
			if ( ! $this->is_valid_for_use() ) {
			   $this->enabled = false;
			}

		 }


		 /**
		  * Ellenőrizze, hogy az áruház pénzneme HUF-ra van állítva
		  **/
		 public function is_valid_for_use() {

			if( ! in_array( get_woocommerce_currency(), array( 'HUF' ) ) ) {
			   $this->msg = 'Nem támogatott fizetőeszköz van beállítva.(HUF) Beállítás ; <a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=general">itt</a>';
			   return false;
			}

			return true;

		 }


		 /**
		  * Ellenőrizze,hogy be van e kapcsolava a modul
		  */
		 public function is_available() {

			if ( $this->enabled == "yes" ) {

			   if ( ! ( $this->public_key && $this->private_key ) ) {
				  return false;
			   }

			   return true;

			}

			return false;

		 }


		 /**
		  * Admin Panel Options
		  **/
		 public function admin_options() {

			echo '<h3>SimplePay</h3>';
			echo '<p>A Simplepay WooCommerce Payment Gateway lehetővé teszi a WooCommerce áruházon belüli helyi és nemzetközi fizetést a MasterCard, a Visa és a Verve kártyákon keresztül.</p>';
			echo '<p>Merchant fiók megnyitásához kattintson <a href="https://simplepay.ng" target="_blank">ide</a>';

			if ( $this->is_valid_for_use() ) {

			   echo '<table class="form-table">';
			   $this->generate_settings_html();
			   echo '</table>';

			} else {	 ?>

			   <div class="inline error"><p><strong>SimplePay ki van kapcsolva</strong>: <?php echo $this->msg ?></p></div>

			<?php }

		 }


		 /**
		  * Inicializálja az átjáró beállításait az űrlapmezőkben
		  **/
		 function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> 'Enable/Disable',
					'type' 			=> 'checkbox',
					'label' 		=> 'Enable SimplePay Payment Gateway',
					'description' 	=> 'Enable or disable the gateway.',
					'desc_tip'      => true,
					'default' 		=> 'yes'
				),
				'title' => array(
					'title' 		=> 'Title',
					'type' 			=> 'text',
					'description' 	=> 'Ezt látja a felhasználó fizetéskor.',
					'desc_tip'      => false,
					'default' 		=> 'SimplePay'
				),
				'description' => array(
					'title' 		=> 'Description',
					'type' 			=> 'textarea',
					'description' 	=> 'Ezt látja a felhasználó fizetéskor.',
					'default' 		=> 'Fizetőeszközök: MasterCard, VisaCard, Verve Card & eTranzact'
				),
				'logo_url' 		=> array(
					'title' 		=> 'Logo URL',
					'type' 			=> 'text',
					'description' 	=> 'Adja meg az Üzlet/Oldal Logo URL-jét, ez jelenik meg a SimplePay fizetési oldalon' ,
					'default' 		=> '',
					'desc_tip'      => false
				),
				'public_test_key' => array(
					'title'       => 'Nyilvános Teszt kulcs',
					'type'        => 'text',
					'description' => 'Adja meg nyilvános teszt kulcsát itt.',
					'default'     => ''
				),
				'private_test_key' => array(
					'title'       => 'Private Test Key',
					'type'        => 'text',
					'description' => 'Adja meg privát kulcsát itt',
					'default'     => ''
				),
				'public_live_key' => array(
					'title'       => 'Public Live Key',
					'type'        => 'text',
					'description' => 'Enter your Public Live Key here.',
					'default'     => ''
				),
				'private_live_key' => array(
					'title'       => 'Private Live Key',
					'type'        => 'text',
					'description' => 'Enter your Private Live Key here.',
					'default'     => ''
				),
				'testing' => array(
					'title'       	=> 'Gateway Testing',
					'type'        	=> 'title',
					'description' 	=> '',
				),
				'testmode' => array(
					'title'       		=> 'Teszt mód',
					'type'        		=> 'checkbox',
					'label'       		=> 'Teszt Mód',
					'default'     		=> 'no',
					'description' 		=> 'Teszt mód lehetővé teszi,hogy élesítés előtt ellenőrizze a fizetést. <br />Ha készen áll a befizetés megkezdésére webhelyén, kérjük, törölje ezt a jelölést.'
				)
			);

		 }


		 /**
		  * Outputs scripts used for SimplePay payment
		  */
		 public function payment_scripts() {

			if ( ! is_checkout_pay_page() ) {
			   return;
			}

			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			wp_enqueue_script( 'we_simplepay', 'https://checkout.simplepay.ng/v2/simplepay.js', array( 'jquery' ), '1.0.0', true );

			wp_enqueue_script( 'wc_simplepay', plugins_url( 'assets/js/simplepay'. $suffix . '.js', __FILE__ ), array( 'we_simplepay' ), '1.0.0', true );

			if ( is_checkout_pay_page() && get_query_var( 'order-pay' ) ) {

			   $order_key 			= urldecode( $_GET['key'] );
			   $order_id  			= absint( get_query_var( 'order-pay' ) );

			   $order        		= wc_get_order( $order_id );

			   $email  			= method_exists( $order, 'get_billing_email' ) ? $order->get_billing_email() : $order->billing_email;
			   $billing_address_1 	= method_exists( $order, 'get_billing_address_1' ) ? $order->get_billing_address_1() : $order->billing_address_1;
			   $billing_address_2 	= method_exists( $order, 'get_billing_address_2' ) ? $order->get_billing_address_2() : $order->billing_address_2;
			   $city  				= method_exists( $order, 'get_billing_city' ) ? $order->get_billing_city() : $order->billing_city;
			   $country  			= method_exists( $order, 'get_billing_country' ) ? $order->get_billing_country() : $order->billing_country;

			   $amount 			= $order->get_total() * 100;
			   $address 			= $billing_address_1 . ' ' . $billing_address_2;

			   $description 		= 'Payment for Order #' . $order_id;

			   $the_order_id 		= method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
			   $the_order_key 		= method_exists( $order, 'get_order_key' ) ? $order->get_order_key() : $order->order_key;

			   if ( $the_order_id == $order_id && $the_order_key == $order_key ) {
				  $simplepay_params['key'] 			= $this->public_key;
				  $simplepay_params['email'] 			= $email;
				  $simplepay_params['address'] 		= $address;
				  $simplepay_params['city'] 			= $city;
				  $simplepay_params['country'] 		= $country;
				  $simplepay_params['amount']  		= $amount;
				  $simplepay_params['order_id']  		= $order_id;
				  $simplepay_params['description']	= $description;
				  $simplepay_params['currency']  		= 'HUF';
				  $simplepay_params['logo']			= $this->logo_url;
			   }

			}

			wp_localize_script( 'wc_simplepay', 'wc_simplepay_params', $simplepay_params );

		 }


		 /**
		  * Process the payment and return the result
		  **/
		 public function process_payment( $order_id ) {

			$order 			= wc_get_order( $order_id );

			return array(
				'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
			);

		 }


		 /**
		  * Output for the order received page.
		  **/
		 public function receipt_page( $order_id ) {

			$order = wc_get_order( $order_id );

			echo '<p>Thank you for your order, please click the button below to pay with debit/credit card using SimplePay.</p>';

			echo '<div id="simplepay_form"><form id="order_review" method="post" action="'. WC()->api_request_url( 'WC_We_SimplePay_Gateway' ) .'"></form><button class="button alt" id="simplepay-payment-button">Pay Now</button> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">Cancel order &amp; restore cart</a></div>
			';
		 }


		 /**
		  * Verify a payment token
		  **/
		 public function charge_token() {

			if( isset( $_POST['wc_simplepay_token'], $_POST['wc_simplepay_order_id'] ) ) {

			   $verify_url 	= 'https://checkout.simplepay.ng/v2/payments/card/charge/';

			   $order_id 		= (int) $_POST['wc_simplepay_order_id'];

			   $order 			= wc_get_order( $order_id );
			   $order_total	= $order->get_total() * 100;

			   $headers = array(
				   'Content-Type'	=> 'application/json',
				   'Authorization' => 'Basic ' . base64_encode( $this->private_key . ':' . '' )
			   );

			   $body = array(
				   'token' 			=> $_POST['wc_simplepay_token'],
				   'amount'			=> $order_total,
				   'amount_currency'	=> 'NGN',
			   );

			   $args = array(
				   'headers'	=> $headers,
				   'body'		=> json_encode( $body ),
				   'timeout'	=> 60,
				   'method'	=> 'POST'
			   );

			   $request = wp_remote_post( $verify_url, $args );

			   if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

				  $simplepay_response = json_decode( wp_remote_retrieve_body( $request ) );

				  $amount_paid 		= $simplepay_response->amount;
				  $transaction_id		= $simplepay_response->id;

				  do_action( 'we_wc_simplepay_after_payment', $simplepay_response );

				  if( '20000' == $simplepay_response->response_code ) {

					 // check if the amount paid is equal to the order amount.
					 if( $amount_paid < $order_total ) {

						//Update the order status
						$order->update_status( 'on-hold', '' );

						add_post_meta( $order_id, '_transaction_id', $transaction_id, true );

						//Error Note
						$notice = 'Thank you for shopping with us.<br />The payment was successful, but the amount paid is not the same as the order amount.<br />Your order is currently on-hold.<br />Kindly contact us for more information regarding your order and payment status.';

						$notice_type = 'notice';

						//Add Admin Order Note
						$order->add_order_note( 'Look into this order. <br />This order is currently on hold.<br />Reason: Amount paid is less than the order amount.<br />Amount Paid was &#8358;'. $amount_paid/100 .' while the order amount is &#8358;'. $order_total/100 .'<br />Simplepay Transaction ID: '.$transaction_id );

						// Reduce stock levels
						$order->reduce_order_stock();

						wc_add_notice( $notice, $notice_type );

					 } else {

						$order->payment_complete( $transaction_id );

						$order->add_order_note( sprintf( 'Payment via Simplepay successful (Transaction ID: %s)', $transaction_id ) );
					 }

					 wc_empty_cart();

					 wp_redirect( $this->get_return_url( $order ) );

					 exit;

				  } else {

					 wp_redirect( wc_get_page_permalink( 'checkout' ) );

					 exit;
				  }

			   }

			}

			wp_redirect( wc_get_page_permalink( 'checkout' ) );

			exit;

		 }

	  }


	  /**
	   * Add SimplePay Gateway to WC
	   **/
	  function we_wc_add_simplepay_gateway( $methods ) {

		 $methods[] = 'WC_We_SimplePay_Gateway';
		 return $methods;

	  }
	  add_filter('woocommerce_payment_gateways', 'we_wc_add_simplepay_gateway' );


	  /**
	   * Add Settings link to the plugin entry in the plugins menu
	   **/
	  function we_simplepay_plugin_action_links( $links, $file ) {

		 static $this_plugin;

		 if ( ! $this_plugin ) {

			$this_plugin = plugin_basename( __FILE__ );

		 }

		 if ( $file == $this_plugin ) {

			$settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_we_simplepay_gateway">Settings</a>';
			array_unshift($links, $settings_link);

		 }

		 return $links;

	  }
	  add_filter( 'plugin_action_links', 'we_simplepay_plugin_action_links', 10, 2 );


	  /**
	   * Display the testmode notice
	   **/
	  function we_wc_simplepay_testmode_notice() {

		 $simplepay_settings = get_option( 'woocommerce_we_simplepay_gateway_settings' );

		 $testmode 			= $simplepay_settings['testmode'] === 'yes' ? true : false;

		 $public_test_key  	= $simplepay_settings['public_test_key'];
		 $private_test_key  	= $simplepay_settings['private_test_key'];

		 $public_live_key  	= $simplepay_settings['public_live_key'];
		 $private_live_key  	= $simplepay_settings['private_live_key'];

		 $public_key      	= $testmode ? $public_test_key : $public_live_key;
		 $private_key      	= $testmode ? $private_test_key : $private_live_key;

		 if ( $testmode ) {
			?>
			<div class="update-nag">
			   SimplePay testmode is still enabled. Click <a href="<?php echo get_bloginfo('wpurl') ?>/wp-admin/admin.php?page=wc-settings&tab=checkout&section=we_simplepay_gateway">here</a> to disable it when you want to start accepting live payment on your site.
			</div>
			<?php
		 }

		 // Check required fields
		 if ( ! ( $public_key && $private_key ) ) {
			echo '<div class="error"><p>' . sprintf( 'Please enter your SimplePay API keys <a href="%s">here</a> to be able to use the SimplePay WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=we_simplepay_gateway' ) ) . '</p></div>';
		 }

	  }
	  add_action( 'admin_notices', 'we_wc_simplepay_testmode_notice' );

   }