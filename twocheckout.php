<?php

global $wpdb,$wp_query,$wp_rewrite,$blog_id,$eshopoptions;
$detailstable=$wpdb->prefix.'eshop_orders';
$derror=__('There appears to have been an error, please contact the site admin','eshop');

//sanitise
include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
$_POST=sanitise_array($_POST);

include_once (WP_PLUGIN_DIR.'/eshop/twocheckout/index.php');
// Setup class
require_once(WP_PLUGIN_DIR.'/eshop/twocheckout/eshop-twocheckout.class.php');  // include the class file
$p = new eshop_twocheckout_class;             // initiate an instance of the class
$p->twocheckout_url = 'https://www.2checkout.com/checkout/purchase';     // twocheckout url

if($eshopoptions['status']!='live'){
	$p->twocheckout_demo = 'Y';     // twocheckout demo
} else {
	$p->twocheckout_demo = 'N';
}

// setup a variable for this script (ie: 'http://www.micahcarrick.com/twocheckout.php')
//e.g. $this_script = 'http://'.$_SERVER['HTTP_HOST'].htmlentities($_SERVER['PHP_SELF']);
$this_script = site_url();
if($eshopoptions['checkout']!=''){
	$p->autoredirect=add_query_arg('eshopaction','redirect',get_permalink($eshopoptions['checkout']));
}else{
	die('<p>'.$derror.'</p>');
}

// if there is no action variable, set the default action of 'process'
if(!isset($wp_query->query_vars['eshopaction']))
	$eshopaction='process';
else
	$eshopaction=$wp_query->query_vars['eshopaction'];

switch ($eshopaction) {
    case 'redirect':
    	//auto-redirect bits
		header('Cache-Control: no-cache, no-store, must-revalidate'); //HTTP/1.1
		header('Expires: Sun, 01 Jul 2005 00:00:00 GMT');
		header('Pragma: no-cache'); //HTTP/1.0

		//enters all the data into the database
		$token = uniqid(md5($_SESSION['date'.$blog_id]), true);
		$pvalue = $_POST['total'];
		$eshopemailbus=$eshopoptions['twocheckout_sid'];
		$checkid=md5($eshopemailbus.$token.number_format($pvalue,2));

		//adding the cart identifier
		$_POST['cart_order_id']=$token;

		//state assignment fix
		if ($_POST['altstate']) {
			$_POST['state'] = $_POST['altstate'];
		}
		//ship state assignment fix
		if ($_POST['ship_altstate']) {
			$_POST['ship_state'] = $_POST['ship_altstate'];
		}
		
		//affiliates
		if(isset($_COOKIE['ap_id'])) $_POST['affiliate'] = $_COOKIE['ap_id'];
		orderhandle($_POST,$checkid);
		if(isset($_COOKIE['ap_id'])) unset($_POST['affiliate']);

		$p = new eshop_twocheckout_class; 

		$echoit.=$p->eshop_submit_twocheckout_post($_POST);
		//$p->dump_fields();      // for debugging, output a table of all the fields
		break;
        
   case 'process':      // Process and order...
   		//get the return path
		if($eshopoptions['cart_success']!=''){
			$slink=add_query_arg('eshopaction','twocheckoutipn',get_permalink($eshopoptions['cart_success']));
			$slink=apply_filters('eshop_twocheckout_return_link',$slink);
			$rlink=add_query_arg('eshopaction','success',get_permalink($eshopoptions['cart_success']));
			$rlink=apply_filters('eshop_twocheckout_return_link',$rlink);
		}else{
			die('<p>'.$derror.'</p>');
		}
		$p->add_field('x_receipt_link_url', $slink);
		$p->add_field('redirect_url', $rlink);

		//get states
		$sttable=$wpdb->prefix.'eshop_states';
		$getstate=$eshopoptions['shipping_state'];
		if($eshopoptions['show_allstates'] != '1'){
			$stateList=$wpdb->get_results("SELECT id,code,stateName FROM $sttable WHERE list='$getstate' ORDER BY stateName",ARRAY_A);
		}else{
			$stateList=$wpdb->get_results("SELECT id,code,stateName,list FROM $sttable ORDER BY list,stateName",ARRAY_A);
		}
		foreach($stateList as $code => $value){
			$eshopstatelist[$value['id']]=$value['code'];
		}
		foreach($_POST as $name=>$value){
			//have to do a discount code check here - otherwise things just don't work - but fine for free shipping codes
			if(strstr($name,'amount_')){
				
				if(isset($_SESSION['eshop_discount'.$blog_id]) && eshop_discount_codes_check()){
					$chkcode=valid_eshop_discount_code($_SESSION['eshop_discount'.$blog_id]);
					if($chkcode && apply_eshop_discount_code('discount')>0){
						$discount=apply_eshop_discount_code('discount')/100;
						$value = number_format(round($value-($value * $discount), 2),2);
						$vset='yes';
					}
				}
				if(is_discountable(calculate_total())!=0 && !isset($vset)){
					$discount=is_discountable(calculate_total())/100;
					$value = number_format(round($value-($value * $discount), 2),2);
				}
				//amending for discounts
				$_POST[$name]=$value;
			}
			if(sizeof($stateList)>0 && ($name=='state' || $name=='ship_state')){
				if($value!='')
					$value=$eshopstatelist[$value];
			}
			$p->add_field($name, $value);
		}
		//required for discounts to work -updating amount.
		$runningtotal=0;
		for ($i = 1; $i <= $_POST['numberofproducts']; $i++) {
			$runningtotal+=$_POST['quantity_'.$i]*$_POST['amount_'.$i];
		}

		//setup some 2CO params
		$pvalue = $runningtotal + eshopShipTaxAmt();
		$p->add_field('total',$pvalue);
		$p->add_field('sid',$eshopoptions['twocheckout_sid']);
		$p->add_field('street_address',$_POST['address1']);
		$p->add_field('street_address2',$_POST['address2']);
		$p->add_field('ship_street_address',$_POST['ship_address']);
		$p->add_field('ship_zip',$_POST['ship_postcode']);
		$p->add_field('demo',$p->twocheckout_demo);
		$p->add_field('phone',$_POST['phone']);
		
		//settings in twocheckout/index.php to change these
		$p->add_field('currency_code',$eshopoptions['currency']);

		if($eshopoptions['status']!='live' && is_user_logged_in() &&  current_user_can('eShop_admin')||$eshopoptions['status']=='live'){
			$echoit .= $p->submit_twocheckout_post(); // submit the fields to twocheckout
    	}
      	break;
      
   case 'success':      // Order was successful...

		break;
      
   case 'twocheckoutipn':
   		//validate MD5 hash
   		if ($p->validate_ipn()) {
			$chkamt= $_REQUEST['total'];

			$eshopemailbus=$eshopoptions['twocheckout_sid'];
			$token = $_REQUEST['cart_order_id'];

			$checkid=md5($eshopemailbus.$token.number_format($chkamt,2));
			
			if($eshopoptions['status']=='live'){
				$txn_id = $wpdb->escape($_REQUEST['cart_order_id']);
				$subject = __('twocheckout IPN -','eshop');
			}else{
				$txn_id = __("TEST-",'eshop').$wpdb->escape($_REQUEST['cart_order_id']);
				$subject = __('Testing: twocheckout IPN - ','eshop');
			}

			//update order record to completed
			eshop_mg_process_product($txn_id,$checkid);

			//send customer email
			include_once(WP_PLUGIN_DIR.'/eshop/cart-functions.php');
			eshop_send_customer_email($checkid, '1');

			//redirect to thankyou page
	      	wp_redirect($_REQUEST['redirect_url']);
		} else {
			echo "<h3>".__('There was a problem with validating your order details. Please contact the seller directly for assistance.','eshop')."</h3>";
		}
      	break;
 }     
?>