<?php
if ('eshop-twocheckout.class.php' == basename($_SERVER['SCRIPT_FILENAME']))
     die ('<h2>Direct File Access Prohibited</h2>');

class eshop_twocheckout_class {
    
   var $last_error;                 // holds the last error encountered
   
   var $ipn_log;                    // bool: log IPN results to text file?
   var $ipn_log_file;               // filename of the IPN log
   var $ipn_response;               // holds the IPN response from twocheckout   
   var $ipn_data = array();         // array contains the POST values for IPN
   
   var $fields = array();           // array holds the fields to submit to twocheckout

   
   function eshop_twocheckout_class() {
      $this->last_error = '';
      $this->ipn_log_file = 'ipn_log.txt';
      $this->ipn_log = false;
      $this->ipn_response = '';
      
   }
   
   function add_field($field, $value) {
      
      // adds a key=>value pair to the fields array, which is what will be 
      // sent to twocheckout as POST variables.  If the value is already in the 
      // array, it will be overwritten.
      
      $this->fields["$field"] = $value;
   }

   function submit_twocheckout_post() {
      $echo= "<form method=\"post\" class=\"eshop eshop-confirm\" action=\"".$this->autoredirect."\"><div>\n";

      foreach ($this->fields as $name => $value) {
        $pos = strpos($name, 'amount');
		if ($pos === false) {
			$value=stripslashes($value);
		   $echo.= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
		}else{
			$echo .= eshopTaxCartFields($name,$value);
		}
      }
      $echo .= apply_filters('eshoptwocheckoutextra','');
      $echo.='<label for="ppsubmit" class="finalize"><small>'.__('<strong>Note:</strong> Submit to finalize order at twocheckout.','eshop').'</small><br />
      <input class="button submit2" type="submit" id="ppsubmit" name="ppsubmit" value="'.__('Proceed to Checkout &raquo;','eshop').'" /></label>';
	  $echo.="</div></form>\n";
      
      return $echo;
   }
	function eshop_submit_twocheckout_post($espost) {
		$rtnecho ='
       <div id="process">
         <p><strong>'.__('Please wait, your order is being processed&#8230;','eshop').'</strong></p>
	     <p>'. __('If you are not automatically redirected to twocheckout, please use the <em>Proceed to 2Checkout</em> button.','eshop').'</p>
         <form method="post" class="eshop" id="eshopgateway" action="https://www.2checkout.com/checkout/purchase">
          <p>';
		  foreach ($espost as $name => $value) {
			if($name!='submit' && $name!='ppsubmit'){			
				if($name!='return' && $name!='cancel_return' && $name!='notify_url'){
					$value=stripslashes($value);
					$replace = array("&#039;","'", "\"","&quot;","&amp;","&");
					$value = str_replace($replace, " ", $value);
				}
				$rtnecho .= "<input type=\"hidden\" name=\"$name\" value=\"$value\" />\n";
			 }

		  }
      	$rtnecho .= '<input class="button" type="submit" id="ppsubmit" name="ppsubmit" value="'. __('Proceed to 2Checkout &raquo;','eshop').'" /></p>
	     </form>
	  </div>';
         $rtnecho .= '<script src="https://www.2checkout.com/static/checkout/javascript/direct.min.js"></script>';
      	global $eshopoptions;
      	if($eshopoptions['status']!='live'){
	  		$rtnecho .= "<p class=\"testing\"><strong>".__('Test Mode &#8212; No money will be collected. This page will not auto redirect in test mode.','eshop')."</strong></p>\n";
	  	}
		return $rtnecho;
   }   
   function validate_ipn() {
      global $eshopoptions;
      $seller_id = $eshopoptions['twocheckout_sid'];
      $secret_word = $eshopoptions['twocheckout_secret'];
      $transaction_id = $_REQUEST['order_number'];
      if ($_REQUEST['demo'] == 'Y') {
                        $transaction_id = 1;
      }
      $compare_string = $secret_word . $seller_id . $transaction_id . $_REQUEST['total'];
      $compare_hash1 = strtoupper(md5($compare_string));
      $compare_hash2 = $_REQUEST['key'];
      if ($compare_hash1 != $compare_hash2) {
         return false;
      } else {
         return true;
      }
      
   }
   
   function log_ipn_results($success) {
       
      if (!$this->ipn_log) return;  // is logging turned off?
      
      // Timestamp
      $text = '['.date('m/d/Y g:i A').'] - '; 
      
      // Success or failure being logged?
      if ($success) $text .= "SUCCESS!\n";
      else $text .= 'FAIL: '.$this->last_error."\n";
      
      // Log the POST variables
      $text .= "IPN POST Vars from twocheckout:\n";
      foreach ($this->ipn_data as $key=>$value) {
         $text .= "$key=$value, ";
      }
 
      // Log the response from the twocheckout server
      $text .= "\nIPN Response from twocheckout Server:\n ".$this->ipn_response;
      // Write to log
      
      $fp=fopen($this->ipn_log_file,'a');
      fwrite($fp, $text . "\n\n"); 

      fclose($fp);  // close file
      
      /* or use this 
      mail('YOUR ADDRESS','twocheckout',$text);
      */
                
   }

   function dump_fields() {
 
      // Used for debugging, this function will output all the field/value pairs
      // that are currently defined in the instance of the class using the
      // add_field() function.
      
      echo "<h3>eshop_twocheckout_class->dump_fields() Output:</h3>";
      echo "<table width=\"95%\" border=\"1\" cellpadding=\"2\" cellspacing=\"0\">
            <tr>
               <td bgcolor=\"black\"><b><font color=\"white\">Field Name</font></b></td>
               <td bgcolor=\"black\"><b><font color=\"white\">Value</font></b></td>
            </tr>"; 
      
      ksort($this->fields);
      foreach ($this->fields as $key => $value) {
         echo "<tr><td>$key</td><td>".urldecode($value)."&nbsp;</td></tr>";
      }
 
      echo "</table><br>"; 
   }
}   