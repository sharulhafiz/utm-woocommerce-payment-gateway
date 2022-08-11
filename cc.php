<?php
/**
   * WC_UTM_FPX_CC
   * 
   * This class if for credit card payment gateway
  */
  class WC_UTM_FPX_CC extends WC_Payment_Gateway {
    public function __construct(){
      $this -> id = 'utmfpxcc';
      $this -> medthod_title = 'UTM FPX CC';
      $this -> has_fields = true;

      $this -> init_form_fields();
      $this -> init_settings();

      $this -> title = $this -> settings['title'];
      $this -> description = $this -> settings['description'];
      $this -> redirect_page_id = $this -> settings['redirect_page_id'];
      $this -> liveurl = 'https://utmvpc.utm.my/vpc/01_new_vpc_main.php';
	  $this -> validationkey = $this->settings['validationkey'];	

      $this -> msg['message'] = "";
      $this -> msg['class'] = "";

      // add_action('init', array(&$this, 'check_payu_response'));

      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
      add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));
      // add_action('woocommerce_api_utmfpx', array( $this, 'check_payu_response' ) );
    }
    function init_form_fields(){
      $this -> form_fields = array(
        'enabled' => array(
            'title' => __('Enable/Disable', 'mrova'),
            'type' => 'checkbox',
            'label' => __('Enable UTM FPX CC Payment Module.', 'mrova'),
            'default' => 'no'),
        'title' => array(
            'title' => __('Title:', 'mrova'),
            'type'=> 'text',
            'description' => __('This controls the title which the user sees during checkout.', 'mrova'),
            'default' => __('UTM FPX CC', 'mrova')),
        'description' => array(
            'title' => __('Description:', 'mrova'),
            'type' => 'textarea',
            'description' => __('This controls the description which the user sees during checkout.', 'mrova'),
            'default' => __('Pay securely by credit card through UTM FPX Secure Servers.', 'mrova')),
		'paymentapiid' => array(
            'title' => __('Gateway ID:', 'mrova'),
            'type' => 'text',
            'description' => __('Gateway ID.', 'mrova'),
            'default' => __('35', 'mrova')),
        'paymentapiuser' => array(
            'title' => __('Username:', 'mrova'),
            'type' => 'password',
            'description' => __('API username.', 'mrova'),
            'default' => __('Username', 'mrova')),
        'paymentapipass' => array(
            'title' => __('Password:', 'mrova'),
            'type' => 'password',
            'description' => __('API password.', 'mrova'),
            'default' => __('Password', 'mrova')),
        'paymentapikey' => array(
            'title' => __('API Key:', 'mrova'),
            'type' => 'password',
            'description' => __('API Key.', 'mrova'),
            'default' => __('API KEY', 'mrova')),
		// 'redirect_page_id' => array(
        //     'title' => __('Return Page'),
        //     'type' => 'select',
        //     'options' => $this -> get_pages('Select Page'),
        //     'description' => "URL of success page"
        // ),
		'validationkey' => array(
            'title' => __('Validation Key:', 'mrova'),
            'type' => 'text',
            'description' => __('Validation Key.', 'mrova'),
            'default' => __('Validation Key', 'mrova'))   
      );
    }

    public function admin_options(){
      echo '<h3>'.__('UTM FPX CC Payment Gateway', 'mrova').'</h3>';
      echo '<p>'.__('UTM FPX CC is most popular payment gateway for online shopping in Malaysia').'</p>';
      echo '<table class="form-table">';
      // Generate the HTML For the settings form.
      $this -> generate_settings_html();
      echo '</table>';
    }

    /**
     *  There are no payment fields for payu, but we want to show the description if set.
     **/
    function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }
    /**
     * Receipt Page
     **/
    function receipt_page($order){
        echo '<p>'.__('Thank you for your order, please click the button below to pay with UTM FPX CC.', 'mrova').'</p>';
        echo $this -> generate_payu_form($order);

    }
    /**
     * Generate payu button link
     **/
    public function generate_payu_form($order_id){
      global $current_user;
      get_currentuserinfo();
      global $woocommerce;
    	$order = new WC_Order( $order_id );
      $order -> add_order_note('Customer redirected to UTM FPX CC');
      $redirect_url = ($this -> redirect_page_id=="" || $this -> redirect_page_id==0)?get_site_url() . "/":get_permalink($this -> redirect_page_id);

      $siteid = get_current_blog_id();
      if($siteid == 534){ //if ushop
        $idtabung = 13; // id tabung ushop = 13
        $productinfo = "UTM Marketplace order $order_id";
      } else {
        $idtabung = 35; // id tabung penerbit = 35
        $productinfo = "UTM Press order $order_id";
      }
      $payu_args = array(
        'productinfo'         => $productinfo,
        'firstname'           => $order -> billing_first_name,
        'lastname'            => $order -> billing_last_name,
        'address1'            => $order -> billing_address_1,
        'address2'            => $order -> billing_address_2,
        'city'                => $order -> billing_city,
        'state'               => $order -> billing_state,
        'country'             => $order -> billing_country,
        // 'zipcode'          => $order -> billing_zip,
        'email'               => $order -> billing_email,
        'phone'               => $order -> billing_phone,
        'desc'                => $productinfo,
        'icnum'               => $current_user->ID,
        'name'                => $order -> billing_first_name . " " . $order -> billing_last_name,
        'amount'              => $order->order_total,
        'systemType'          => $idtabung,
        "payProgramCode"      => "UTMU001",
        "payWo"               => $order->order_total - $order->order_tax,
        "payGST"              => $order->order_tax,
        "clientRefNo"         => $order_id . "|" . str_replace("wc_order_","",$order->get_order_key())
      );

      $payu_args_array = array();
      foreach($payu_args as $key => $value){
        $payu_args_array[] = "<input type='hidden' name='$key' value='$value'/>";
      }
      return '<form action="'.$this -> liveurl.'" method="post" id="payu_payment_form">' . implode('', $payu_args_array) . '
            <input type="submit" class="button-alt" id="submit_payu_payment_form" value="'.__('Pay via UTM FPX', 'mrova').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'mrova').'</a>
            <script type="text/javascript">
              jQuery(function(){
                jQuery("body").block({
                    message: "<img src=\"'.$woocommerce->plugin_url().'/assets/images/ajax-loader.gif\" alt=\"Redirecting…\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to Payment Gateway to make payment.', 'mrova').'",
                    overlayCSS: {
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
                      lineHeight:       "32px"
                    }
                  })
                  jQuery("#submit_payu_payment_form").click();});
              </script>
            </form>';
    }
    /**
     * Process the payment and return the result
     **/
    function process_payment($order_id){
      global $woocommerce;
    	$order = new WC_Order( $order_id );

      return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
      );
    }

    // get all pages
    function get_pages($title = false, $indent = true) {
      $wp_pages = get_pages('sort_column=menu_order');
      $page_list = array();
      if ($title) $page_list[] = $title;
      foreach ($wp_pages as $page) {
        $prefix = '';
        // show indented child pages?
        if ($indent) {
          $has_parent = $page->post_parent;
          while($has_parent) {
            $prefix .=  ' - ';
            $next_page = get_page($has_parent);
            $has_parent = $next_page->post_parent;
          }
        }
        // add to page list array array
        $page_list[$page->ID] = $prefix . $page->post_title;
      }
      return $page_list;
    }
  } //end class cc