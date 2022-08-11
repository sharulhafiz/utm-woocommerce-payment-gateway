<?php
/**
 * Thank you page. This will check UTM Payment Gateway through API for payment status
 */
include(plugin_dir_path(__FILE__) . 'pdo/pdo.php');

add_action('woocommerce_thankyou', 'check_payment_status', 10, 1);
function check_payment_status()
{
	if (!isset($_REQUEST['key'])) {
		exit();
	}
    global $woocommerce;
    global $message;
	global $messageclass;
	global $post;
    $message = "";
    $messageclass = "";
    $arrContextOptions=array(
        "ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false,
        ),
	);

	$url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $order_id = explode('/', $url);
    $order_id = $order_id[sizeof($order_id)-2];
    if ($order_id == '') {
        exit();
    }
    $order = new WC_Order($order_id);

	// HERE define you payment gateway ID (from $this->id in your plugin code)
	$payment_gateway_id = $order->get_payment_method();

	// Get an instance of the WC_Payment_Gateways object
	$payment_gateways   = WC_Payment_Gateways::instance();

	// Get the desired WC_Payment_Gateway object
	$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];
	// echo '<pre>';
	// var_dump($payment_gateway->settings['paymentapiuser']);

	$servername = "oracledbscan.utm.my/SMUTM";
	$tns = " 
			(DESCRIPTION =
				(ADDRESS_LIST =
					(ADDRESS = (PROTOCOL = TCP)(HOST = oracledbscan.utm.my)(PORT = 1521))
				)
				(CONNECT_DATA =
					(SERVICE_NAME = SMUTM)
				)
			)
			";
    $username = $payment_gateway->settings['paymentapiuser'];
    $password = $payment_gateway->settings['paymentapipass'];
    $pgid = $payment_gateway->settings['paymentapiid'];
	// exit($username.$password);
    try {
        $conn = new PDOOCI\PDO("oci:dbname=".$tns, $username, $password);
        // set the PDO error mode to exception
        $conn -> setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		// echo "** Connected successfully <br><br>";
		$dbstatus = true;
    } catch (PDOException $e) {
		echo "** Connection failed: " . $e->getMessage();
		$dbstatus = false;
    }
	
	// echo $order;
	// echo '<br>' . $order->get_status();
	// echo '<br>' . $order->get_order_key();

    if ($order->get_status() == 'processing') {
        // if processing, do nothing
		$message = "<h4>Your order is still processing. We will updating your order soon.</h4><br><br>";
		echo $message;
        $messageclass = 'success-color woocommerce_message';
        // echo '<div class="box '.$messageclass.'-box"><strong>'.$message.'</strong></div>'.$content;
        // echo '<style>#main > div.cart-container.container.page-wrapper.page-checkout > div > div > div.large-5.col > div > p {display:none}</style>';
	// } else if ($order->get_status() == 'pending') {
	} else {
		if ($order->get_payment_method() == "utmfpx" || $order->get_payment_method() == "utmfpxcc") {
			if($order->get_payment_method() == "utmfpx"){
				$pgmethod = "FPX";
			} else {
				$pgmethod = "CC";
			}
			$order_key = str_replace("wc_order_", "", $order->get_order_key());
			$param = $order->get_order_number().'|'.$order_key;

			if($dbstatus){
				// new API
				$order_ref = $order->get_order_number().'|'.$order_key;
				$sqlQuery = "SELECT UTM_API.CRYPTO_$pgid.ENCRYPTDES('$pgid/KEYAPI-MYUTM-19X3-4X53/$order_ref/$pgmethod') AS PLAINTEXT FROM DUAL";
				$data = $conn->prepare($sqlQuery); //guna utk insert
				$data -> execute(); //guna utk insert
				$results=$data->fetchAll(PDO::FETCH_ASSOC);
				$ciphertext = $results[0]["PLAINTEXT"];
				$ciphertext = bin2hex($ciphertext);
				$url = 'https://devapi.utm.my/epayutm/api/GetEpayPaymentStatus';
				$pgid = '?systemType='.$pgid;
				$ciphertext = '&ciphertext='.$ciphertext;
				$xml = file_get_contents($url.$pgid.$ciphertext);
				$xml = json_decode($xml);
				
			} else {
				// old api for USHOP
				$json = file_get_contents('https://utmfpx.utm.my/api/payment/get_ref.php?id='.$param, false, stream_context_create($arrContextOptions));
				$data = json_decode($json, true);
				$status = strtolower($data['records'][0]['transaction_status']);
			}

			// check if customer abondon external payment page
			if ($status != "success" || $status != "fail") { 
				$status = strtolower($xml->PaymentStatus);
				if ($status == '') {
					$status = 'failed.';
				}
			}

			// to debug payment received page, use this URL
			// https://penerbit.utm.my/checkout/order-received/44190/?key=wc_order_47J5pcUjn7a1F
			// $order_received_url = $order->get_checkout_order_received_url();

			if ($status == "success") {
				$transauthorised = true;
				$message = "Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be updating your order to you soon.";
				$messageclass = 'success-color woocommerce_message';
				
				$order -> payment_complete(); // this will change order status to processing
				$order -> add_order_note('Payment '.$status);

			} else {
				$paymentlink = bloginfo('url')."/checkout/order-pay/".$order_id."/?pay_for_order=true&key=".$_GET['key'];
				$messageclass = 'woocommerce_error';
				$message = 'Thank you for shopping with us. However, the transaction was <strong>'.$status.'</strong>. <a href="'.$paymentlink.'" class="button primary wc-backward">Click here to reattempt payment</a>';
				$order -> update_status('failed', 'Payment '.$status);
			}
			echo '<div class="box '.$messageclass.'-box">'.$message.'</div>'.$content;
			echo '<style>#main > div.cart-container.container.page-wrapper.page-checkout > div > div > div.large-5.col > div > p {display:none}</style>';
		} else {
			echo '<br>' . $order->get_payment_method();
			// exit('Not UTM FPX');
		}
		// echo '<br>' . $order->get_payment_method();
		// echo '<br>' . $order->get_order_number();
		// echo '<br>' . $order->get_id();
		// echo '<br>' . $order->get_order_key();
		// echo '<br>' . $order->get_status();
		// echo '<br>' . $order->status();
		// echo '<br>';
		// echo $status;		
    }
}

// function general_admin_notice()
// {
// 	global $pagenow, $woocommerce;
// 	$customer_orders = wc_get_orders(array(
//     'limit'    => -1,
//     'status'   => 'pending'
// 	));

//     if ($pagenow == 'edit.php'    ) {
// 		// echo '$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$$';

//         echo '<div class="notice">';
//         echo '<pre>';
// 		// var_dump($customer_orders[0]);
// 		// // Iterating through each Order with pending status
// 		foreach ($customer_orders as $order) {
// 			if($order->id != 44268){
// 				// var_dump($order->id);
// 				$order_received_url = $order->get_checkout_order_received_url();
// 				// echo $order_received_url;
// 				file_get_contents($order_received_url);
// 			}
			
			
// 			// break;
// 			// Going through each current customer order items
// 			// foreach ($order->get_items() as $item_id => $item_values) {
// 			// 	$product_id = $item_values['product_id']; // product ID
// 			// 	// echo '<br>' . $product_id;
				
// 			// 	// // Order Item meta data
// 			// 	// $item_meta_data = wc_get_order_item_meta($item_id);

// 			// 	// // Some output
// 			// 	// echo '<p>Line total for '.wc_get_order_item_meta($item_id, '_line_total', true).'</p><br>';
// 			// }
// 		}

//         echo '</div>';
//     }
// }
// add_action('admin_notices', 'general_admin_notice');


// ---- ---- ----
// A. Define a cron job interval if it doesn't exist
 
add_filter('cron_schedules', 'bbloomer_check_every_3_hours');
 
function bbloomer_check_every_3_hours($schedules)
{
    $schedules['every_three_hours'] = array(
        'interval' => 10800,
        'display'  => __('Every 3 hours'),
    );
    return $schedules;
}
 
// ---- ---- ----
// B. Schedule an event unless already scheduled
 
add_action('wp', 'bbloomer_custom_cron_job');
 
function bbloomer_custom_cron_job()
{
    if (! wp_next_scheduled('bbloomer_woocommerce_send_email_digest')) {
        wp_schedule_event(time(), 'twicedaily', 'bbloomer_woocommerce_send_email_digest');
    }
}
 
// ---- ---- ----
// C. Trigger email when hook runs
 
add_action('bbloomer_woocommerce_send_email_digest', 'bbloomer_check_pending_payment');
 
// ---- ---- ----
// D. Generate email content and send email if there are completed orders
 
function bbloomer_check_pending_payment()
{
	global $pagenow, $woocommerce;
	$pending_orders = array();
    $customer_orders = wc_get_orders(array(
		'limit'    => -1,
		'status'   => 'pending'
    ));

	// // Iterating through each Order with pending status
	foreach ($customer_orders as $order) {
		$order_received_url = $order->get_checkout_order_received_url();
		file_get_contents($order_received_url);
		array_push($pending_orders,$order->id);
	}

	if ($pending_orders) {
		$email_subject = "Pending Orders";
		$email_content = "Pending Order IDs: " . implode(" | ", $pending_orders);
		wp_mail('sharulhafiz@utm.my', $email_subject, $email_content);
	}
}