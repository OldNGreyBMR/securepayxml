<?php
/**
 * securepayxml.php
 * 
 * Implements the SecurePay XML API payment module for Zen Cart
 * Contains the securepayxml class, which handles SecurePay XML API credit-card transactions via the securepay_xml_transaction class (securepay_xml_api.php).
 * The bulk of this class is a wrapper for securepay_xml_transaction, to interface with the Zen Cart way of doing things. It also sets up some text, menus and database entries.
 *
 * @author Andrew Dubbeld (support@securepay.com.au)
 * @date 12-Oct-2009
 * @notes Partially derived from the linkpoint api module, which is:
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions Copyright 2003 Jason LeBaron
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @updated 2026-04-06 V1.6.1
 * @amaintainedby OldNGrey (BMH) since 2017
 */
// Modifications
// 2025-03-08 PHP8.3 & 8.4 declare all vars
// 2025-09-29 increase size of banktxnid from varchar(7) to varchar(16) in SQL create table
// 2025-09-30 add random suffix to transaction id to ensure uniqueness
// 2025-10-02 159a ln391 use of $oid and $api_order_id to identify diff 
// 2025-11-01 ln293 fix ?? [v1.5.9b]
// 2025-12-02 trim v1.5.9c
// 2025-12-10 1.5.9d redundant curl_close($ch) for PHP 8.0 to 8.5
// 2026-01-15 ln355, 357, 358, 359, 430 null coalesce ; transaction log enhancements; debug logging improvements
// 2026-01-16 1.6.0 ln419, 444 debug logging improvements;
//2026-04-06 1.6.1 ln943 replace str() with date() in log function to fix error in PHP 8.3 and above

// BMH @ini_set('error_reporting', E_STRICT);
//declare(strict_types=1);
if (!defined('VERSION_SECUREPAYXML')) { define('VERSION_SECUREPAYXML', '1.6.1'); }

define('SECUREPAY_CODE_DEBUG','FALSE'); // values = true OR false; BMH debug switch to write to transaction log file use for testing only

if (!defined('MODULE_PAYMENT_SECUREPAYXML_CODE_DEBUG')) define('MODULE_PAYMENT_SECUREPAYXML_CODE_DEBUG', ''); 
//check for debugging switch to allow debugging by specified IP address when down for maintenance // original code

// BMH check which zc version and preload language files if required.
// Language files may be required if this module is called directly eg from edit _orders
if (!defined('MODULE_PAYMENT_SECUREPAYXML_TEXT_ADMIN_TITLE')) {
    $filename = "securepayxml.php";
    $folder = "/modules/payment/";  // end with slash
    $old_langfile = DIR_FS_CATALOG . DIR_WS_LANGUAGES . $_SESSION['language'] . $folder .  $filename;
    $new_langfile =  DIR_WS_LANGUAGES . $_SESSION['language'] . $folder .  "lang." . $filename;

    if (file_exists($new_langfile)) {
        global $languageLoader;

        $languageLoader->loadExtraLanguageFiles(DIR_FS_CATALOG . DIR_WS_LANGUAGES,  $_SESSION['language'], $folder . $filename);
    } else if (file_exists($old_langfile)) {

        $tpl_old_langfile = DIR_WS_LANGUAGES . $_SESSION['language'] . $folder .  $template_dir . '/' . $filename;
        if (file_exists($tpl_old_langfile)) {
            $old_langfile = $tpl_old_langfile;
        }
        include_once ($old_langfile);
    }
}

if (!defined('TABLE_SECUREPAYXML')) define('TABLE_SECUREPAYXML', DB_PREFIX . 'securepayxml');

require_once(DIR_FS_CATALOG . DIR_WS_MODULES . 'payment/securepay_xml_api.php');

class securepayxml
{
	public $auth_code ; // BMH remove as zero refs
    public $amount;
	public $code;
	public $description;
	public $enabled;
	public $form_action_url;
	public $order_status;
	public $purchaseOrderId; 
	public $sort_order;
	public $title;
	public $transaction_id;
	public $cc_card_type; // Added to fix undefined property error
	public $cc_card_number; // Added to fix undefined property $cc_card_number error
	public $cc_expiry_month; // Added to fix undefined property $cc_expiry_month error
	public $cc_expiry_year; // Added to fix undefined property $cc_expiry_year error
	public $zone; // Added to fix undefined property $zone error
	public $code_debug; // Added to fix undefined property $code_debug error
	private $_logDir = DIR_FS_SQL_CACHE;
    private $_logtransDir = DIR_FS_SQL_CACHE;
	private $mode = SECUREPAY_GATEWAY_MODE_TEST;
	private $_check; // Added to fix undefined property $_check error

	function __construct()
	{
		global $order, $messageStack;

		$this->code = 'securepayxml';
		$this->enabled = ((MODULE_PAYMENT_SECUREPAYXML_STATUS == 'True') ? true : false); // Whether the module is installed or not
	    // BMH 2020-11-14 Undefined index: main_page bof
        //if (!isset($_GET['main_page'])) , $_GET['main_page'] = 'index';

/*if ($_GET['main_page'] != '' && !IS_ADMIN_FLAG === true)
		{
			$this->title = MODULE_PAYMENT_SECUREPAYXML_TEXT_CATALOG_TITLE; // Payment module title in Catalog
		}
		else
		{
			$this->title = MODULE_PAYMENT_SECUREPAYXML_TEXT_ADMIN_TITLE; // Payment module title in Admin
            $this->description = 'V' . VERSION_SECUREPAYXML . ' <br>' . MODULE_PAYMENT_SECUREPAYXML_TEXT_DESCRIPTION; // show version in admin panel

			if ($this->enabled && !function_exists('curl_init'))
			{
				$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_ERROR_CURL_NOT_FOUND, 'error');
			}
		}
            */
        if (IS_ADMIN_FLAG === true)
        {
            $this->title = MODULE_PAYMENT_SECUREPAYXML_TEXT_ADMIN_TITLE; // Payment module title in Admin
            $this->description = 'V' . VERSION_SECUREPAYXML . ' ' . MODULE_PAYMENT_SECUREPAYXML_TEXT_DESCRIPTION; // show version in admin panel

            if ($this->enabled && !function_exists('curl_init'))
            {
                $messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_ERROR_CURL_NOT_FOUND, 'error');
            }
        } else {
            $this->title = MODULE_PAYMENT_SECUREPAYXML_TEXT_CATALOG_TITLE; // Payment module title in Catalog
        }
	// BMH eof
		// BMH $this->description = MODULE_PAYMENT_SECUREPAYXML_TEXT_DESCRIPTION;	// Descriptive Info about module in Admin
		$this->sort_order = MODULE_PAYMENT_SECUREPAYXML_SORT_ORDER; // Sort Order of this payment option on the customer payment page
		$this->form_action_url = zen_href_link(FILENAME_CHECKOUT_PROCESS, '', 'SSL', false); // Page to go to upon submitting page info

		$this->order_status = (int)DEFAULT_ORDERS_STATUS_ID;

		$this->mode = SECUREPAY_GATEWAY_MODE_TEST;
		if (MODULE_PAYMENT_SECUREPAYXML_TEST == "No")
		{
			$this->mode = SECUREPAY_GATEWAY_MODE_LIVE;
		}

		if ((int)MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID > 0)
		{
			$this->order_status = MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID;
		}

		if (MODULE_PAYMENT_SECUREPAYXML_MODE == MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH && (int)MODULE_PAYMENT_SECUREPAYXML_PREAUTH_ORDER_STATUS_ID > 0)
		{
			$this->order_status = MODULE_PAYMENT_SECUREPAYXML_PREAUTH_ORDER_STATUS_ID;
		}

		$this->zone = (int)MODULE_PAYMENT_SECUREPAYXML_ZONE;

		if (is_object($order))
		{
			$this->update_status();
		}

        $this->code_debug = (MODULE_PAYMENT_SECUREPAYXML_CODE_DEBUG=='debug') ? true : false;

		// set error messages if misconfigured
		if (MODULE_PAYMENT_SECUREPAYXML_STATUS == 'True')
		{
			if (MODULE_PAYMENT_SECUREPAYXML_MERCHANTID == '')
			{
				$this->title .= MODULE_PAYMENT_SECUREPAYXML_TEXT_NOT_CONFIGURED;
			}
			elseif (MODULE_PAYMENT_SECUREPAYXML_TEST != 'No')
			{
				$this->title .= MODULE_PAYMENT_SECUREPAYXML_TEXT_TEST_MODE;
			}
		}
	}

	function update_status()
	{
		global $order, $db;

		if ( $this->enabled && $this->zone > 0 )
		{
			$check_flag = false;
			$sql = "SELECT zone_id
					FROM " . TABLE_ZONES_TO_GEO_ZONES . "
					WHERE geo_zone_id = :zoneId
					AND zone_country_id = :countryId
					ORDER BY zone_id";
			$sql = $db->bindVars($sql, ':zoneId', $this->zone, 'integer');
			$sql = $db->bindVars($sql, ':countryId', $order->billing['country']['id'], 'integer');
			$check = $db->Execute($sql);
			while (!$check->EOF)
			{
				if ($check->fields['zone_id'] < 1)
				{
					$check_flag = true;
					break;
				}
				elseif ($check->fields['zone_id'] == $order->billing['zone_id'])
				{
					$check_flag = true;
					break;
				}
				$check->MoveNext();
			}

			if (!$check_flag)
			{
				$this->enabled = false;
			}
		}
		// if in code-debug mode and IP address is in the down-for-maint list, enable the module (leaves it invisible to non-testers)
		if (strstr(EXCLUDE_ADMIN_IP_FOR_MAINTENANCE, $_SERVER['REMOTE_ADDR']))
		{
			if ($this->code_debug) $this->enabled=true;
		}
	}

	/* Included in the payment form. */
	function javascript_validation()
	{
		$js = 	'	if (payment_value == "' . $this->code . '") {' . "\n" .
				'		var cc_owner = document.checkout_payment.securepayxml_cc_owner.value;' . "\n" .
				'		var cc_number = document.checkout_payment.securepayxml_cc_number.value;' . "\n" .
				'		var cc_cvv = document.checkout_payment.securepayxml_cc_cvv.value;' . "\n" .
				'		if (cc_owner == "" || cc_owner.length < ' . CC_OWNER_MIN_LENGTH . ') {' . "\n" .
				'			error_message = error_message + "' . MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_OWNER . '";' . "\n" .
				'			error = 1;' . "\n" .
				'		}' . "\n" .
				'		if (cc_number == "" || cc_number.length < ' . CC_NUMBER_MIN_LENGTH . ') {' . "\n" .
				'			error_message = error_message + "' . MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_NUMBER . '";' . "\n" .
				'			error = 1;' . "\n" .
				'		}' . "\n" .
				'				 if (cc_cvv == "" || cc_cvv.length < "3" ) {' . "\n".
				'					 error_message = error_message + "' . MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_CVV . '";' . "\n" .
				'					 error = 1;' . "\n" .
				'				 }' . "\n" .
				'	}' . "\n";
                // BMR 2026-01-16 check cc_cvv length 3 or 4 - 4 is only used for Amex
		return $js;
	}

	/* This is called to generate the payment form, as part of the checkout process. */
	function selection()
	{
		global $order;
        global $ccnum; // BMH

		for ($i=1; $i<13; $i++)
		{
            $expires_month[] = array('id' => sprintf('%02d', $i), 'text' => date('F - (m)',mktime(0,0,0,$i,1,2000))); // BMH
		}

		$today = getdate();
		for ($i=$today['year']; $i < $today['year']+10; $i++)
		{
            $expires_year[] = array('id' => date('y',mktime(0,0,0,1,1,$i)), 'text' => date('Y',mktime(0,0,0,1,1,$i)));
		}

		$onFocus = ' onfocus="methodSelect(\'pmt-' . $this->code . '\')"';

		$selection = array(
			'id' => $this->code,
			'module' => $this->title,
			'fields' => array(
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_OWNER,
					'field' => zen_draw_input_field('securepayxml_cc_owner', $order->billing['firstname'] . ' ' . $order->billing['lastname'], 'id="'.$this->code.'-cc-owner"'. $onFocus),
					'tag' => $this->code.'-cc-owner'
				),
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_NUMBER,
					'field' => zen_draw_input_field('securepayxml_cc_number', $ccnum, ' autocomplete="off" id="'.$this->code.'-cc-number"' . $onFocus),
					'tag' => $this->code.'-cc-number'
				),
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_EXPIRES,
					'field' => zen_draw_pull_down_menu('securepayxml_cc_expires_month', $expires_month, '', 'id="'.$this->code.'-cc-expires-month"' . $onFocus) . '&nbsp;' . zen_draw_pull_down_menu('securepayxml_cc_expires_year', $expires_year, '', 'id="'.$this->code.'-cc-expires-year"' . $onFocus),
					'tag' => $this->code.'-cc-expires-month'
				),
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CVV,
					'field' => zen_draw_input_field('securepayxml_cc_cvv', '', 'size="4" maxlength="4"'. ' autocomplete="off" id="'.$this->code.'-cc-cvv"' . $onFocus) . ' ' . '<a href="javascript:popupWindow(\'' . zen_href_link(FILENAME_POPUP_CVV_HELP) . '\')">' . MODULE_PAYMENT_SECUREPAYXML_TEXT_POPUP_CVV_LINK . '</a>',
					'tag' => $this->code.'-cc-cvv'
				)
			)
		);

		return $selection;
	}


	/* Secondary credit-card detail check. */
	function pre_confirmation_check()
	{
		global $db, $messageStack;

		include(DIR_WS_CLASSES . 'cc_validation.php');

		$cc_validation = new cc_validation();
		$result = $cc_validation->validate(($_POST['securepayxml_cc_number'] ?? ''), ($_POST['securepayxml_cc_expires_month'] ?? ''), ($_POST['securepayxml_cc_expires_year']) ?? '');   //
		$error = '';
		switch ($result)
		{
			case -1:
				$error = sprintf(TEXT_CCVAL_ERROR_UNKNOWN_CARD, substr($cc_validation->cc_number, 0, 4));
				break;
			case -2:
			case -3:
			case -4:
				$error = TEXT_CCVAL_ERROR_INVALID_DATE;
				break;
			case false:
				$error = TEXT_CCVAL_ERROR_INVALID_NUMBER;
				break;
		}
		// if no error, continue with validated data:
		$this->cc_card_type = $cc_validation->cc_type;
		$this->cc_card_number = $cc_validation->cc_number;
		$this->cc_expiry_month = $cc_validation->cc_expiry_month;
		$this->cc_expiry_year = $cc_validation->cc_expiry_year;
	}

	/* Display Credit Card Information on the Checkout Confirmation Page */
	function confirmation()
	{
        global $zcDate; // BMH 2025-09-29
		$confirmation = array(
			'title' => $this->title . ': ' . $this->cc_card_type,
			'fields' => array(
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_OWNER,
					'field' => $_POST['securepayxml_cc_owner']
				),
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_NUMBER,
					'field' => str_repeat('X', (strlen($this->cc_card_number) - 4)) . substr($this->cc_card_number, -4)
				),
				array(
					'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_EXPIRES,
					// 
                    'field' => $zcDate->output('%B, %Y', mktime(0,0,0,$_POST['securepayxml_cc_expires_month'], 1, '20' . $_POST['securepayxml_cc_expires_year']))
				)
			)
		);

		return $confirmation;
	}

	/**
	 * Prepare the hidden fields comprising the parameters for the Submit button on the checkout confirmation page
	 *
	 * Not sure why this is the preferred method of passing CC details, but it is common to all payment modules, and over SSL it isn't too terrible.
	 */
	function process_button()
	{
		// These are hidden fields on the checkout confirmation page
		$process_button_string =
			zen_draw_hidden_field('cc_owner', $_POST['securepayxml_cc_owner']) .
			zen_draw_hidden_field('cc_expires', $this->cc_expiry_month ?? '' . substr($this->cc_expiry_year ?? '', -2)) .
			zen_draw_hidden_field('cc_expires_month', $this->cc_expiry_month ?? '') .
			zen_draw_hidden_field('cc_expires_year', substr($this->cc_expiry_year ?? '', -2)) .
			zen_draw_hidden_field('cc_type', $this->cc_card_type ?? '') .
			zen_draw_hidden_field('cc_number', $this->cc_card_number ?? '') .
			zen_draw_hidden_field('cc_cvv', $_POST['securepayxml_cc_cvv']) .
			zen_draw_hidden_field(zen_session_name(), zen_session_id());

		return $process_button_string;
	}

	/**
	 * Prepare and submit the authorization to the gateway
	 */
	function before_process()
	{
		global $order, $db, $messageStack, $_POST, $_SESSION;

		$preauth = "";
		$txnid = "";

		$sxml = new securepay_xml_transaction($this->mode,MODULE_PAYMENT_SECUREPAYXML_MERCHANTID,MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS);

		$customer_id = $_SESSION['customer_id'];

        $amount = round($order->info['total'], 2); // BMH
        
        // Create an order ID
		$last_order_id = $db->Execute("select orders_id from " . TABLE_ORDERS . " order by orders_id desc limit 1");
		$new_order_id = $last_order_id->fields['orders_id'];
		$new_order_id = ($new_order_id + 1);
        $oid = ($new_order_id);

         // BMH 159 add randomized suffix to order id to produce uniqueness ... since it's unwise to submit the same order-number twice to the CC clearance system
        $api_order_id = (string)$new_order_id . '-' . zen_create_random_value(3, 'chars'); // Ensure uniqueness
        
		$type = SECUREPAY_TXN_STANDARD;
		if(MODULE_PAYMENT_SECUREPAYXML_MODE == MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH)
		{
			$type = SECUREPAY_TXN_PREAUTH;
		}

		$cc_number = $_POST['cc_number'];
		$cc_month = $_POST['cc_expires_month'];
		$cc_year = $_POST['cc_expires_year'];
		$cvv = $_POST['cc_cvv'];

		if($type == SECUREPAY_TXN_PREAUTH)
		{
            $result = $sxml->processCreditPreauth($amount,$api_order_id,$cc_number,$cc_month,$cc_year,$cvv);
		}
		else
		{ // BMH debug
            $result = $sxml->processCreditStandard($amount,$api_order_id,$cc_number,$cc_month,$cc_year,$cvv);
		}

        //check error string
        $txnResultCodeText = '';

        if ($sxml->getErrorString() !== null) {           
             $txnResultCodeText = $sxml->getErrorString();
        
		    //$txnResultCodeText = $sxml->getErrorString();
	         
            $this->_log("ln" . __LINE__ . ' $txnResultCodeText=' . $txnResultCodeText ?? ''); // BMH DEBUG 2026-01-16 log transaction XML

            if (SECUREPAY_CODE_DEBUG == 'true') {
                $this->_logtrans("p","ln" . __LINE__ . ' ------------------------------------------------------' . "\r\n", ''); // BMH DEBUG 2026-01-16 log transaction record separator
                $this->_logtrans("p","ln" . __LINE__ . ' len($txnResultCodeText) = ' . strlen($txnResultCodeText) . ' : $txnResultCodeText = ', $txnResultCodeText); // BMH DEBUG 2026-01-16 log transaction XML
            } 
        }
        
		$approved = strtoupper($sxml->getResultByKeyName('approved'))=='YES'?true:false;
		$status = $sxml->getResultByKeyName('responseCode');

            if (SECUREPAY_CODE_DEBUG == 'true') {
                $this->_logtrans("p","ln" . __LINE__ . ' $status (response_code) : ' , $status); // BMH DEBUG 2026-01-16 log status = response code
            }
		$this->transaction_id = $result;

		//get purchaseOrderNo from response
            // but first check not empty response
     
        $res_xml = $sxml->getResultArray();
        if(is_array($res_xml) && count($res_xml) > 0) {
            
            if (SECUREPAY_CODE_DEBUG == 'true') {
               $this->_logtrans("p", "ln" . __LINE__ . ' $res_xml print_r: ' ."\r\n" , $res_xml); // BMH DEBUG 2026-01-16 log transaction XML
              // $this->_logtrans("d", "ln" . __LINE__ . ' $res_xml var_dump: ', $res_xml); // BMH DEBUG 2026-01-16 log transaction XML
            }
            $res_xml_string = html_entity_decode($res_xml['raw-response'] ?? ''); // 2026-01-15 null coalesce

            if (SECUREPAY_CODE_DEBUG == 'true') {
                $this->_logtrans("p", "ln" . __LINE__ . ' $res_xml_string print_r: ' ."\r\n" , $res_xml_string); // BMH DEBUG 2026-01-16 log transaction XML
            }

            $obj = simplexml_load_string($res_xml_string); // Parse XML
            $array = json_decode(json_encode($obj), true); // Convert to array
            $this->purchaseOrderId = $array['Payment']['TxnList']['Txn']['purchaseOrderNo'];

			//END get purchaseOrderNo from response
        } else {
             if (SECUREPAY_CODE_DEBUG == 'true') { 
                $this->_logtrans("p","ln" . __LINE__ . ' getResultArray() is not an array', ''); // BMH DEBUG 2026-01-16 log transaction XML
            }
        }

		if ($approved)
		{
			//Success
			if($type == SECUREPAY_TXN_PREAUTH)
			{
				$preauth = $result;
			}
			else
			{
				$txnid = $result;
			}
		}
		else
		{
			//Error
            $messageStack->add_session('checkout_payment',MODULE_PAYMENT_SECUREPAYXML_TEXT_DECLINED_MESSAGE . " " . $txnResultCodeText . " ", 'error');
            $customer_id = $_SESSION['customer_id'];

            if (SECUREPAY_CODE_DEBUG == 'true') {
                $this->_logtrans("p","ln" . __LINE__ . " Declined: $" . number_format(($amount/100),2). " #" .$oid . " Cust:". $customer_id . " Exp:" . $cc_month . "/" . $cc_year, ''); // BMH
             }

			$this->_log("ln" . __LINE__ . " " . $txnResultCodeText  . ": $" . number_format(($amount/100),2) . " #" .$oid . " Cust:". $customer_id . " Exp:" . $cc_month . "/" . $cc_year); // BMH
           
			zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', true, false));
		}

		//Store Transaction history in Database
		$paid = (MODULE_PAYMENT_SECUREPAYXML_MODE == MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH ? 0 : $amount);
		$sql_data_array = array(
			array('fieldName'=>'customer_id',	'value' => $customer_id, 		'type'=>'integer'), //Local: 	customer's id
			array('fieldName'=>'banktxnid', 	'value' => $txnid, 				'type'=>'string'), 	//Gateway: 	transaction id
			array('fieldName'=>'preauthid',		'value' => $preauth, 			'type'=>'string'), 	//Gateway: 	preauth id
			array('fieldName'=>'oid',			'value' => $oid, 				'type'=>'string'), 	//Local: 	transaction id
			array('fieldName'=>'response_code',	'value' => $status, 			'type'=>'string'), 	//Gateway: 	response code (000 - 999)
			array('fieldName'=>'time', 			'value' => $sxml->getGMTTimeStamp(),	'type'=>'string'), 	//Local: 	timestamp
			array('fieldName'=>'total', 		'value' => $amount, 			'type'=>'string'), 	//Local: 	Total $
			array('fieldName'=>'paid', 			'value' => $paid, 				'type'=>'string'), 	//Local: 	Total paid
			array('fieldName'=>'txntype', 		'value' => $type,			 	'type'=>'integer'), //Local: 	Txn Type. See securepay_xml_api.php
		);

		$db->perform(TABLE_SECUREPAYXML, $sql_data_array);
		return true;
	}

	function after_process()
	{
		global $insert_id, $db;
		$comments = (MODULE_PAYMENT_SECUREPAYXML_MODE == MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH ? MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH_DESC : MODULE_PAYMENT_SECUREPAYXML_MODE_STANDARD_DESC);

		switch (MODULE_PAYMENT_SECUREPAYXML_TEST)
		{
			case "No": $comments .= ''; break;
			case "Yes": $comments .= ' (Test-mode)'; break;
		}

		$db->Execute("insert into " . TABLE_ORDERS_STATUS_HISTORY . " (comments, orders_id, orders_status_id, date_added) values ('Credit Card payment. " . $comments . " " . $this->cc_card_type . " Transaction ID: " . $this->transaction_id . "' , '". (int)$insert_id . "','" . $this->order_status . "', now() )");
          // update purchaseOrderId for order
				// BMH DEBUG	 echo " BMH $this->purchaseOrderId=" . $this->purchaseOrderId . "line 403";
					// BMH 2019-01-09 purchaseOrderId does not exist in table
					//              $db->Execute("UPDATE ".TABLE_ORDERS." SET `purchaseOrderId` = '".$this->purchaseOrderId."' WHERE `orders_id` = ".(int)$insert_id);
													// END update purchaseOrderId for order
					// BMH end
		return false;
	}

	function after_order_create($zf_order_id)
	{
		return;
	}

	function admin_notification($oid)
	{
		global $db;

		if (MODULE_PAYMENT_SECUREPAYXML_STATUS=='False')
		{
			return '';
		}

		$output = '';
		$sql = "select total,paid,txntype from " . TABLE_SECUREPAYXML . " where oid = '" . $oid . "' order by time";
		$txn = $db->Execute($sql);

		if ($txn->RecordCount() > 0)
		{
			require(DIR_FS_CATALOG. DIR_WS_MODULES . 'payment/securepayxml/securepayxml_admin_notification.php');
		}
		else
		{
			$this->_log("ln {__LINE__} No results on order ". $oid ."\n");
		}
		return $output;
	}

	function get_error()
	{
		$error = array(
			'title' => MODULE_PAYMENT_SECUREPAYXML_TEXT_ERROR,
			'error' => stripslashes(urldecode($_GET['error']))
		);
		return $error;
	}

	function check()
	{
		global $db;
		if (!isset($this->_check))
		{
			$check_query = $db->Execute("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_SECUREPAYXML_STATUS'");
			$this->_check = $check_query->RecordCount();
		}
		return $this->_check;
	}

	function install()
	{
		global $db;
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Securepayxml Module', 'MODULE_PAYMENT_SECUREPAYXML_STATUS', 'True', 'Do you want to accept SecurePay credit card payments?', '6', 121, 'zen_cfg_select_option(array(\'True\', \'False\'), ', now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant ID', 'MODULE_PAYMENT_SECUREPAYXML_MERCHANTID', '', 'Enter your SecurePay Merchant ID.', '6', 121, now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Merchant Password', 'MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS', '', 'Enter your SecurePay Merchant Password.', '6', 121, now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Test Mode', 'MODULE_PAYMENT_SECUREPAYXML_TEST', 'Yes', '<strong>No:</strong> Use this setting for live stores. Funds will be transferred from the customer\'s account into your merchant account as expected.<br /><strong>Yes:</strong> Use this setting to test the module without transferring funds.', '6', 121, 'zen_cfg_select_option(array(\'No\', \'Yes\'), ', now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Transaction Type', 'MODULE_PAYMENT_SECUREPAYXML_MODE', 'Standard Payment', 'Do you want submitted credit card transactions to be authorized only, or immediately charged?<br />In most cases you will want to do a <strong>Standard Payment</strong> to collect payment immediately. In some situations, you may prefer to simply <strong>Preauthorise</strong> transactions, and then manually use Zen-Cart\'s order manager to formally complete the payments.', '6', 121, 'zen_cfg_select_option(array(\'Standard Payment\', \'Preauth/Advice\'), ', now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Set Order Status', 'MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID', 2, 'When this module is set to Standard Payment mode, which order-status do you want the purchase to be set to?<br />Recommended: <strong>Processing</strong>', '6', 121, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Preauth Order Status', 'MODULE_PAYMENT_SECUREPAYXML_PREAUTH_ORDER_STATUS_ID', 1, 'When this module is set to Preauth/Advice mode, which order-status do you want the purchase to be set to?<br />Recommended: <strong>Pending</strong>', '6', 121, 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Refund/Void Order Status', 'MODULE_PAYMENT_SECUREPAYXML_REFUNDED_ORDER_STATUS_ID', '1', 'When orders are refunded or reversed from this Admin area, which order-status do you want the transaction to be set to?<br />Recommended: <strong>Pending</strong> or cancelled/refunded', '6', '0', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_SECUREPAYXML_SORT_ORDER', '0', 'Any value greater than zero will cause this payment method to appear in the specified sort order on the checkout-payment page.', '6', 121, now());");
		$db->Execute("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone (restrict to)', 'MODULE_PAYMENT_SECUREPAYXML_ZONE', '0', 'If you want only customers from a particular zone to be able to use this payment module, select that zone here.', '6', 121, 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

		// Now do database-setup:
		global $sniffer;
		if (!$sniffer->table_exists(TABLE_SECUREPAYXML))
		{
			$sql = "CREATE TABLE " . TABLE_SECUREPAYXML . " (
				id int(11) unsigned NOT NULL auto_increment,
				customer_id varchar(11) NOT NULL default '',
				banktxnid varchar(16) NOT NULL default '',
				preauthid varchar(7) NOT NULL default '',
				oid int(11) NOT NULL default '0',
				response_code varchar(3) NOT NULL default '999',
				time varchar(25) NOT NULL default '',
				total decimal(15,2) NOT NULL default '0.0000',
				paid decimal(15,2) NOT NULL default '0.0000',
				txntype int(3) NOT NULL default '0',
				PRIMARY KEY	(id),
				KEY idx_customer_id_zen (customer_id)
			)";
			$db->Execute($sql);
		}
              
            //    $sql = "ALTER TABLE ".TABLE_ORDERS." ADD purchaseOrderId VARCHAR(200) NOT NULL";
            //    $db->Execute($sql);
            
                //   $sql = UPDATE securepayxml SET banktxnid = 'N/A' WHERE banktxnid IS NULL;

                $sql = "ALTER TABLE ".TABLE_SECUREPAYXML." CHANGE banktxnid VARCHAR(16) NOT NULL";
                $db->Execute($sql);
	}

	function remove()
	{
		global $db, $sniffer;
		$db->Execute("delete from " . TABLE_CONFIGURATION . " where configuration_key like 'MODULE\_PAYMENT\_SECUREPAYXML\_%'");
		// cleanup database if contains no data
		if ($sniffer->table_exists(TABLE_SECUREPAYXML))
		{
			$db->Execute("DROP TABLE " . TABLE_SECUREPAYXML);
		}
	}

	function keys()
	{
		$keys_list = array(
			'MODULE_PAYMENT_SECUREPAYXML_STATUS',
			'MODULE_PAYMENT_SECUREPAYXML_MERCHANTID',
			'MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS',
			'MODULE_PAYMENT_SECUREPAYXML_TEST',
			'MODULE_PAYMENT_SECUREPAYXML_MODE',
			'MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID',
			'MODULE_PAYMENT_SECUREPAYXML_PREAUTH_ORDER_STATUS_ID',
			'MODULE_PAYMENT_SECUREPAYXML_REFUNDED_ORDER_STATUS_ID',
			'MODULE_PAYMENT_SECUREPAYXML_SORT_ORDER',
			'MODULE_PAYMENT_SECUREPAYXML_ZONE',
		);

		return $keys_list;
	}

	
	/**
	 * Update order status and order status history based on admin changes sent to gateway
	 */
	function _updateOrderStatus($oID, $new_order_status, $comments)
	{
		global $db;
		$sql_data_array = array(
			array('fieldName'=>'orders_id', 'value' => $oID, 'type'=>'integer'),
			array('fieldName'=>'orders_status_id', 'value' => $new_order_status, 'type'=>'integer'),
			array('fieldName'=>'date_added', 'value' => 'now()', 'type'=>'noquotestring'),
			array('fieldName'=>'comments', 'value' => $comments, 'type'=>'string'),
			array('fieldName'=>'customer_notified', 'value' => 0, 'type'=>'integer')
		);
		$db->perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
		$db->Execute("update " . TABLE_ORDERS .
					" set orders_status = '" . (int)$new_order_status . "'" .
					" where orders_id = '" . (int)$oID . "'");
	}

	/**
	 * Update txn record
	 *
	 * Used to keep txn info current so that advice can only be called once, and refund can only be called on orders which have not been completely refunded.
	 *
	 * Use -1 values for fields you do not wish to update.
	 */
	function _updateTxn($oid, $amount=0, $txn=-1,$type=-1)
	{
		global $db;
		$query = "update " . TABLE_SECUREPAYXML . " set ";
		if($amount>=0)
		{
			$query .= "paid = '" . (int)$amount . "' ";
			if($txn>=0 || $type >=0)
			{
				$query .= ", ";
			}
		}

		if($txn>=0)
		{
			$query .= "banktxnid = '" . $txn . "' ";
			if($type>=0)
			{
				$query .= ", ";
			}
		}

		if($type>=0)
		{
			$query .= "txntype = '" . $type . "' ";
		}
		$query .= "where oid = '" . (int)$oid . "'";

		$db->Execute("".$query);
	}

	/**
	 * Used to submit a refund for a given transaction.
	 */
	function _doRefund($oID, $amount = 0)
	{
		global $db, $messageStack;

		$sql = "select banktxnid,total,paid from " . TABLE_SECUREPAYXML . " where oid = " . (int)$oID . " order by time DESC";
		$query = $db->Execute($sql);

		$new_order_status = (int)MODULE_PAYMENT_SECUREPAYXML_REFUNDED_ORDER_STATUS_ID;

		if ($new_order_status == 0)
		{
			$new_order_status = 1;
		}

		$refundNote = strip_tags(zen_db_input($_POST['refnote']));

		$amount = (int)$_POST['refamt'];
		$left = (int)$query->fields['paid'] - $amount;

		if (isset($_POST['refconfirm']) && $_POST['refconfirm'] != 'on')
		{
			$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_REFUND_CONFIRM_ERROR, 'error');
			return false;
		}

		if (isset($_POST['buttonrefund']) && $_POST['buttonrefund'] == MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_BUTTON_TEXT)
		{
			$new_order_status = (int)MODULE_PAYMENT_SECUREPAYXML_REFUNDED_ORDER_STATUS_ID;
			if ($amount == 0 || $left < 0)
			{
				$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_INVALID_AMOUNT." Requested $".$amount." from a total of $". $query->fields['paid'], 'error');
				return false;
			}
		}

		if ($query->RecordCount() < 1)
		{
			$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_NO_MATCHING_ORDER_FOUND, 'error');
			return false;
		}

		/**
		 * Submit refund request to gateway
		 */
		//Create the transaction object
		$sxml = new securepay_xml_transaction($this->mode,MODULE_PAYMENT_SECUREPAYXML_MERCHANTID,MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS);

		//Issue a refund transaction
                // BMH debug
			//	echo ' BMH 1 $purchaseOrderId=' . $purchaseOrderId . "line 640";
			//echo ' BMH 2 This->purchaseOrderId=' . $this->purchaseOrderId . "line 640";
        $sql_purchaseOrderId = 'select purchaseOrderId from ' . TABLE_ORDERS . " where orders_id = " . (int)$oID;
	    $query_purchaseOrderId = $db->Execute($sql_purchaseOrderId);
        $purchaseOrderId = $query_purchaseOrderId->fields['purchaseOrderId'];

		$bankTxnID = $sxml->processCreditRefund($amount,$purchaseOrderId,$query->fields['banktxnid']);

		//Check results
		$txnResultCodeText = $sxml->getErrorString();
		$approved = strtoupper($sxml->getResultByKeyName('approved'))=='YES'?true:false;
		$status = $sxml->getResultByKeyName('responseCode');

		if($approved==false)
		{
			//Gateway error
			$messageStack->add_session($txnResultCodeText, 'error');
			$this->_log("ln {__LINE__} Failed refund: " . $txnResultCodeText . ": $" . $amount . " #" .$oID);
			return false;
		}
		else
		{
			// Success, so save the results
			$this->_updateOrderStatus($oID, $new_order_status, 'Refunded $' . $amount . '. Trans ID: ' . $bankTxnID . "\n" . $refundNote);
			$this->_updateTxn($oID, $left, -1, SECUREPAY_TXN_REFUND);
			$messageStack->add_session(sprintf(MODULE_PAYMENT_SECUREPAYXML_TEXT_REFUND, $amount, $oID, $bankTxnID), 'success');
			return true;
		}
		// return false;  // BMH 159 unreachable code  what to return here?
	}

	/**
	 * Used to capture part or all of a given previously-authorized transaction. Can only be used once per preauth for an amount equal to or less than the preauth amount.
	 */
	function _doCapt($oID, $amt = 0, $currency = 'AUD')
	{
		global $db, $messageStack;
        global $left; // BMH 2025-09-29

		$new_order_status = (int)MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID;
		if ($new_order_status === 0) 		{
			$new_order_status = 1;
		}

		$sql = "select preauthid, total from " . TABLE_SECUREPAYXML . " where oid = " . (int)$oID . " order by time;";
		$query = $db->Execute($sql);

		if ($query->RecordCount() < 1) 		{
			$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_NO_MATCHING_ORDER_FOUND, 'error');
			$proceedToCapture = false;
		}

		$amount = (isset($_POST['captamt']) && $_POST['captamt'] != '') ? (float)strip_tags(zen_db_input($_POST['captamt'])) : $query->fields['total'];

		if (isset($_POST['btndocapture']) && $_POST['btndocapture'] == MODULE_PAYMENT_SECUREPAYXML_ENTRY_CAPTURE_BUTTON_TEXT)
		{
			if ($amount == 0 || $left < 0)
			{
				$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_INVALID_AMOUNT, 'error');
				return false;
			}
		}

		// Assign refundNote to avoid undefined variable error
		$refundNote = '';
		if (isset($_POST['capturenote'])) {
			$refundNote = strip_tags(zen_db_input($_POST['capturenote']));
		}

		/**
		 * Submit capture request to Gateway
		 */
		//Create the transaction object
		$sxml = new securepay_xml_transaction($this->mode,MODULE_PAYMENT_SECUREPAYXML_MERCHANTID,MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS);

		//Issue a refund transaction
		$bankTxnID = $sxml->processCreditAdvice($amount,$oID,$query->fields['preauthid']);

		//Check results
		$txnResultCodeText = $sxml->getErrorString();
		$approved = strtoupper($sxml->getResultByKeyName('approved'))=='YES'?true:false;
		$status = $sxml->getResultByKeyName('responseCode');

		if ($approved==false || $bankTxnID == false)
		{
			//Error
			$messageStack->add_session($txnResultCodeText, 'error');
		}
		else
		{
			// Success, so save the results
			$this->_updateOrderStatus($oID, $new_order_status, 'Payment completed for $' . $amount . "\n" . $refundNote);
			$this->_updateTxn($oID, $amount, $bankTxnID, SECUREPAY_TXN_ADVICE);
			$messageStack->add_session("Advice succeeded.", 'success');
			return true;
		}

		return false;
	}

	/**
	 * Used to void a completed transaction.
	 */
	function _doVoid($oID, $note = '')
	{
		global $db, $messageStack;

		$new_order_status = (int)MODULE_PAYMENT_SECUREPAYXML_REFUNDED_ORDER_STATUS_ID;

		if ($new_order_status == 0)
		{
			$new_order_status = 1;
		}

		$voidNote = strip_tags(zen_db_input($_POST['voidnote'] . $note));

		if ($_POST['voidconfirm'] != 'on')
		{
			$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_VOID_CONFIRM_ERROR, 'error');
			return false;
		}

		$sql = "select banktxnid,total from " . TABLE_SECUREPAYXML . " where oid = " . (int)$oID . " order by time";
		$query = $db->Execute($sql);

		$amount = $query->fields['total'];

		$this->_log("ln {__LINE__} TxnID: ". $query->fields['banktxnid']);

		if ($query->RecordCount() < 1)
		{
			$messageStack->add_session(MODULE_PAYMENT_SECUREPAYXML_TEXT_NO_MATCHING_ORDER_FOUND, 'error');
			return false;
		}

		/**
		 * Submit void request to Gateway
		 */
		//Create the transaction object
		$sxml = new securepay_xml_transaction($this->mode,MODULE_PAYMENT_SECUREPAYXML_MERCHANTID,MODULE_PAYMENT_SECUREPAYXML_MERCHANTPASS);

		//Issue a refund transaction
		$bankTxnID = $sxml->processCreditReverse($amount,$oID,$query->fields['banktxnid']);

		//Check results
		$txnResultCodeText = $sxml->getErrorString();
		$approved = strtoupper($sxml->getResultByKeyName('approved'))=='YES'?true:false;
		$status = $sxml->getResultByKeyName('responseCode');

		if ($approved==false)
		{
			//Bank error
			$messageStack->add_session($txnResultCodeText, 'error');
		}
		else
		{
			// Success, so save the results
			$this->_updateOrderStatus($oID, $new_order_status, 'Reversed order ' . $oID . ' with transaction ID ' . $bankTxnID . ": returned $" . $amount . "\n" . $voidNote);
			$this->_updateTxn($oID, 0, -1, SECUREPAY_TXN_REVERSE);
			$messageStack->add_session(sprintf(MODULE_PAYMENT_SECUREPAYXML_TEXT_VOID, $oID,$bankTxnID), 'success');
			return true;
		}

		return false;
	}
      /**  
     * Logging function 
     */
    function _log($msg, $suffix = '')
	{
		$file = $this->_logDir . '/' . 'Securepayxml.log';
		if ($fp = @fopen($file, 'a'))
		{
            //$today = date("Y-m-d_H:i:s");         // BMH does not include microseconds
                $microtime = microtime(true);
                $timestamp = (int)$microtime;
                $microseconds = (int)(($microtime - $timestamp) * 1000000);
                $dt = new DateTime();
                $dt->setTimestamp($timestamp);
                $dt->modify("+$microseconds microseconds");
            $today = $dt->format("Y-m-d_H:i:s:u"); 
			// BMH @fwrite($fp, "".time().": ". str($today) . "  " .$msg . " \r\n"); // stores epoch time + date
            @fwrite($fp, "".time().": ". date($today) . "  " .$msg . " \r\n"); // stores epoch time + date
            // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
			@fclose($fp);
		}
	} 
    function _logtrans($x, $msg, $dump)
	{
		$file = $this->_logtransDir . '/' . 'Securepaytrans.log';
		if ($fp = @fopen($file, 'a'))
		{
            //$today = date("Y-m-d_H:i:s");         // BMH does not include microseconds
                $microtime = microtime(true);
                $timestamp = (int)$microtime;
                $microseconds = (int)(($microtime - $timestamp) * 1000000);
                $dt = new DateTime();
                $dt->setTimestamp($timestamp);
                $dt->modify("+$microseconds microseconds");

            $today = $dt->format("Y-m-d_H:i:s:u");   
            switch ($x) {
                case "x":
                    break;
                case "d":
                    ob_start();
                    var_dump($dump);
                    //$output = ob_get_clean();
                    $output = ob_get_contents() ;
                    //$output1 = html_entity_decode($output);
                    @fwrite($fp, "".time().": ".$today . "  " . $msg . "var_dump \r\n" . $output ."\r\n"); // stores epoch time + date
                    @fclose($fp);
                    ob_end_clean() ;
                    break;
			
            case "p":
                    @fwrite($fp, "".time(). ": " . $today . " " . $msg . " " . html_entity_decode(print_r($dump,true)   ) ."\r\n"); // stores epoch time + date
                    //@fwrite($fp, "".time(). ": " . $today . " " . $msg . " " . print_r($dump,true) ."\r\n"); // stores epoch time + date
                    break;
            }
			//@fwrite($fp, "".time().": ".$today . "  " .$msg . " \r\n"); // stores epoch time + date
            // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
			//@fclose($fp);
		}
	} 
}
