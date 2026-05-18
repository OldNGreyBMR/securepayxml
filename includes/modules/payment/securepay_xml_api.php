<?php
/**
 * securepay_xml_api.php:
 *
 * Contains a class for sending transaction requests to SecurePay,
 * and receiving responses from the SecurePay via the XML API
 *
 * This class requires cURL to be available to PHP
 *
 * @author Andrew Dubbeld (support@securepay.com.au)
 * @date 19-Oct-2009
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @maintainer OldNGrey (BMH) since 2017
 */
// BMH  2025-09-29 add version number in comment to match securepayxml.php; 
// Version 1.5.9d
// 2025-10-02 159a use of $oid and $api_order_id to identify diff 
// 2025-12-02 159c trim() Using the null coalescing operator (??): Provide a default empty string if the value is null.
// 2025-12-10 159d redundant curl_close($ch) for PHP 8.0 to 8.5; xml_parder_free deprecated in PHP 8.5
// 2026-01-18 160 fix null coalesce in getTxnReference(); debug log improvements; debug switch off by default
// 2026-04-06 1.6.1 to match securepayxml.php

/* switches */
if (!defined('VERSION_SECUREPAYXMLAPI')) { define('VERSION_SECUREPAYXMLAPI', '1.6.1'); }
// debugging
define('SECUREPAYAPI_CODE_DEBUG','false'); // values = true OR false; BMH debug switch to write to transaction log file use for testing only

/* Modes */
define( 'SECUREPAY_GATEWAY_MODE_TEST',		  1);
define( 'SECUREPAY_GATEWAY_MODE_LIVE',		  2);
define( 'SECUREPAY_GATEWAY_MODE_PERIODIC_TEST',3);
define( 'SECUREPAY_GATEWAY_MODE_PERIODIC_LIVE',4);
define( 'SECUREPAY_GATEWAY_MODE_FRAUD_TEST',	  5);
define( 'SECUREPAY_GATEWAY_MODE_FRAUD_LIVE',	  6);

/* Server URLs */
define('SECUREPAY_URL_TEST', 'https://test.api.securepay.com.au/xmlapi/payment');
define('SECUREPAY_URL_LIVE', 'https://api.securepay.com.au/xmlapi/payment');
define('SECUREPAY_URL_PERIODIC_TEST', 'https://test.api.securepay.com.au/xmlapi/periodic');
define('SECUREPAY_URL_PERIODIC_LIVE', 'https://api.securepay.com.au/xmlapi/periodic');
define('SECUREPAY_URL_FRAUD_TEST', 'https://test.api.securepay.com.au/antifraud/payment');
define('SECUREPAY_URL_FRAUD_LIVE', 'https://test.api.securepay.com.au/antifraud/payment');

/* Transaction types. */
define( 'SECUREPAY_TXN_STANDARD',		 0);
define( 'SECUREPAY_TXN_REFUND',			 4);
define( 'SECUREPAY_TXN_REVERSE',		     6);
define( 'SECUREPAY_TXN_PREAUTH', 		10);
define( 'SECUREPAY_TXN_ADVICE', 		    11);
define( 'SECUREPAY_TXN_DIRECTDEBIT',	    15);
define( 'SECUREPAY_TXN_DIRECTCREDIT', 	17);
define( 'SECUREPAY_TXN_ANTIFRAUD_PAY', 	21);
define( 'SECUREPAY_TXN_ANTIFRAUD_CHECK', 22);

/* Request types */
define( 'SECUREPAY_REQ_ECHO', 		  	'Echo');
define( 'SECUREPAY_REQ_PAYMENT',   		'Payment');
define( 'SECUREPAY_REQ_PERIODIC', 		'Periodic');
define( 'SECUREPAY_CURRENCY_DEFAULT',	'AUD');

/**
 * securepay_xml_transaction
 *
 * This class handles XML SecurePay transactions
 *
 * It supports the following tranactions:
 * 		Credit Payment (standard)
 *		Credit Refund
 *		Credit Reversal
 *		Credit Preauthorisation
 *		Credit Preauthorised completion (Advice)
 *
 * It partially supports the following transactions (which are not yet required):
 *		Direct Entry Credit
 *		Direct Entry Debit
 *
 * It can support the following transactions in future:
 * 		Add Trigger/Peridic Payment
 *		Delete Trigger/Periodic Payment
 *		Trigger Triggered payment
 *
 * @param int mode - The kind of transaction object you would like to open. i.e. SECUREPAY_GATEWAY_MODE_TEST See top of this file for definitions.
 * @param string merchantID - The merchant's login ID, received from SecurePay
 * @param string merchantPW - The merchant's login password
 * @param string identifier - Support identifier
 */

class securepay_xml_transaction
{
	const TIMEOUT="60";

	const GATEWAY_ERROR_OBJECT_INVALID = "The Gateway Object is invalid";
	const GATEWAY_ERROR_CURL_ERROR = "CURL failed and reported the following error";
	const GATEWAY_ERROR_INVALID_CCNUMBER = "Parameter Check failure: Invalid credit card number";
	const GATEWAY_ERROR_INVALID_CCEXPIRY = "Parameter Check failure: Invalid credit card expiry date";
	const GATEWAY_ERROR_INVALID_CC_CVC = "Parameter Check failure: Invalid credit card verification code";
	const GATEWAY_ERROR_INVALID_TXN_AMT = "Parameter Check failure: Invalid transaction amount";
	const GATEWAY_ERROR_INVALID_REF_ID = "Parameter Check failure: Invalid transaction reference number";
	const GATEWAY_ERROR_INVALID_ACCOUNTNUMBER = "Parameter Check failure: Invalid account number";
	const GATEWAY_ERROR_INVALID_ACCOUNTNAME = "Parameter Check failure: Invalid account name";
	const GATEWAY_ERROR_INVALID_ACCOUNTBSB = "Parameter Check failure: Invalid BSB";
	const GATEWAY_ERROR_INVALID_BSB = "Parameter Check failure: Invalid BSB";
	const GATEWAY_ERROR_RESPONSE_ERROR = "A general response error was detected";
	const GATEWAY_ERROR_RESPONSE_INVALID = "A unspecified error was detected in the response content";
	const GATEWAY_ERROR_XML_PARSE_FAILED = "The response message could not be parsed (invalid XML?)";
	const GATEWAY_ERROR_RESPONSE_XML_MESSAGE_ERROR = "An unspecified error was found in the response message (missing field?)";
	const GATEWAY_ERROR_SECUREPAY_STATUS = "The remote Gateway reported the following status error";
	const GATEWAY_ERROR_TXN_DECLINED = "Transaction Declined";

	private $errorString;
	private $gatewayObjectValid = true;
	private $gatewayURL, $merchantID, $merchantPW;
	private $responseArray = array();
	private $txnType = 0;
	public $customer_id;

    public $ccNumber; 
    public $cc_month;
    public $cc_year;
    public $ccVerify;
    public $ccExpiryMonth;
    public $ccExpiryYear;

	private $accNumber, $accBSB, $accName;

	private $txnReference;
    public $amount;
	private $preauthID;
	private $currency=SECUREPAY_CURRENCY_DEFAULT;

	private $requestType, $periodicType, $periodicInterval;

	private $bankTxnID = 0;
    public $_logapiDir = DIR_FS_SQL_CACHE;
  
    public $purchaseOrderId; 
    public $oid;

	//fraudguard
	private $fraudGuard = 0;
	private $fgFirstName = "";
	private $fgLastName = "";
	private $fgPostCode = "";
	private $fgTown = "";
	private $fgCountryB = "";
	private $fgCountryD = "";
	private $fgEmail = "";
	private $fgIP = "";

	//Support Identifier. re: Richard
	private $identifier="";

	/**
	 * __construct
	 *
	 * @param integer $gatewaymode
	 * @param string $setup_merchantID
	 * @param string $setup_merchantPW
	 *
	 */
	public function __construct( $gatewaymode, $setup_merchantID, $setup_merchantPW, $identifier="")
	{

		switch ( $gatewaymode )
		{
			case SECUREPAY_GATEWAY_MODE_TEST:
				$this->gatewayURL = SECUREPAY_URL_TEST;
				break;

			case SECUREPAY_GATEWAY_MODE_LIVE:
				$this->gatewayURL = SECUREPAY_URL_LIVE;
				break;

			case SECUREPAY_GATEWAY_MODE_PERIODIC_TEST:
				$this->gatewayURL = SECUREPAY_URL_PERIODIC_TEST;
				break;

			case SECUREPAY_GATEWAY_MODE_PERIODIC_LIVE:
				$this->gatewayURL = SECUREPAY_URL_PERIODIC_LIVE;
				break;

			case SECUREPAY_GATEWAY_MODE_FRAUD_TEST:
				$this->gatewayURL = SECUREPAY_URL_FRAUD_TEST;
				break;
			case SECUREPAY_GATEWAY_MODE_FRAUD_LIVE:
				$this->gatewayURL = SECUREPAY_URL_FRAUD_LIVE;
				break;

			default:
				$this->gatewayObjectValid = false;
				return;
		}

		$this->setIdentifier($identifier);

		if ( strlen( $setup_merchantID ) == 0 || strlen( $setup_merchantPW ) == 0 )
		{
			$this->gatewayObjectValid = false;
			return;
		}
		// BMH DEBUG var_dump($setup_merchantID) . "line" . __LINE__ .  "<br>"; // BMH
		$this->setAuth($setup_merchantID,$setup_merchantPW);
	}

	public function getIdentifier() { return $this->identifier; }
	public function setIdentifier($id) { $this->identifier = $id; }

	/**
	 * reset
	 *
	 * Clears response variables, preventing mismatched results in certain failure cases.
	 * This is called before each transaction, so be sure to check these values between transactions.
	 */
	public function reset()
	{
		$this->errorString = NULL;
		$this->responseArray = array();
		$this->bankTxnID = 0;
		$this->txnType = 0;
	}

	public function isGatewayObjectValid() { return $this->gatewayObjectValid; }

	// Amount is stored as integer cents
	public function getAmount(): int { return (int)(isset($this->amount) ? $this->amount : 0); }
	/**
	 * setAmount
	 *
	 * Takes amount as a float; requires currency to be set
	 *
	 * @param float amount
	 */
	public function setAmount($amount)
	{
		if($this->getCurrency() == 'JPY')
		{
			$this->amount = $amount;
		}
		else
		{
			$this->amount = round($amount*100,0);
		}
		return;
	}

	public function getCurrency() { return $this->currency; }
	public function setCurrency($cur) { $this->currency = $cur; }

	public function getTxnReference()
        { 
            // BMH 2025-10-02 159a return isset($this->txnReference) ? $this->$txnReference : 0; 
            return isset($this->txnReference) ? $this->txnReference : 0;
        }
	public function setTxnReference($ref) { $this->txnReference = $ref; }

	public function getTxnType() { return $this->txnType; }
	public function setTxnType($type) { $this->txnType = $type; }

	public function getPreauthID() { return $this->preauthID; }
	public function setPreauthID($id) { $this->preauthID = $id; }

	public function getAccBSB() { return $this->accBSB; }
	public function setAccBSB($bsb) { $this->accBSB = $bsb; }

	public function getAccNumber() { return $this->accNumber; }
	public function setAccNumber($Number) { $this->accNumber = $Number; }

	public function getAccName() { return $this->accName; }
	public function setAccName($name) { $this->accName = $name; }

	public function getCCNumber() { return $this->ccNumber; }
	public function setCCNumber($ccNumber) { $this->ccNumber = $ccNumber; }

	public function getCCVerify() { return $this->ccVerify; }
	public function setCCVerify($ver) { $this->ccVerify = $ver; }

	/* @return string month MM*/
	public function getCCExpiryMonth() { return $this->ccExpiryMonth; }

	/* @return string year YY*/
	public function getCCExpiryYear() { return $this->ccExpiryYear; }

	/* @param string/int month MM or month M - If there are leading zeros, type needs to be string*/
	public function setCCExpiryMonth($month)
	{
		$l = strlen(trim($month) ?? ''); // BMH 2025-12-02
		if($l == 1)
		{
			$this->ccExpiryMonth = sprintf("%02d",ltrim($month,'0') ?? ''); // BMH 2025-12-02
		}
		else
		{
			$this->ccExpiryMonth = $month;
		}

		return;
	}

	/* @param string/int year YY or year Y or year YYYY - If there are leading zeros, type needs to be string*/
	public function setCCExpiryYear($year)
	{
		$y = ltrim(trim((string)$year ?? ''),"0"); // BMH 2025-12-02
		$l = strlen($y);
		if($l==4)
		{
			$this->ccExpiryYear = substr($y,2);
		}
		else if($l>=5)
		{
			$this->ccExpiryYear = 0;
		}
		else if($l==1)
		{
			$this->ccExpiryYear = sprintf("%02d",$y);
		}
		else
		{
			$this->ccExpiryYear = $year;
		}
		return;
	}

	public function getClearCCNumber()
	{
		$t = (string)$this->getCCNumber();
		$this->setCCNumber("0");
		return $t;
	}

	public function getClearCCVerify()
	{
		$t = (string)$this->getCCVerify();
		$this->setCCVerify(0);
		return $t;
	}

	public function getMerchantID() { return $this->merchantID; }
	public function setMerchantID($id) { $this->merchantID = $id; }

	public function getMerchantPW () { return $this->merchantPW; }
	public function setMerchantPW ($pw) { $this->merchantPW = $pw; }

	public function getBankTxnID () { return $this->bankTxnID; }
	public function setBankTxnID ($id) { $this->bankTxnID = $id; }

	public function getRequestType () { return $this->requestType; }
	public function setRequestType ($t) { $this->requestType = $t; }

	public function getPeriodicType () { return $this->periodicType; }
	public function setPeriodicType ($t) { $this->periodicType = $t; }

	public function getPeriodicInterval () { return $this->periodicInterval; }
	public function setPeriodicInterval ($t) { $this->periodicInterval = $t; }

	public function getErrorString () { return $this->errorString; }

	public function getResultArray () { return $this->responseArray; }

	public function getResultByKeyName ( $keyName)
	{
		if ( array_key_exists( $keyName, $this->responseArray) === true )
		{
			return $this->responseArray[$keyName];
		}
		return false;
	}

	public function getTxnWasSuccesful()
	{
		if (array_key_exists( "txnResult", $this->responseArray) === true
			&& 	$this->responseArray["txnResult"] === true )
		{
			return true;
		}
		return false;
	}

	public function setAuth($id, $pw)
	{
		$this->setMerchantID($id);
		$this->setMerchantPW($pw);
		return;
	}

	public function setFraudGuard($val)
	{
		if($val)
		{
			$this->fraudGuard = 1;
		}
		else
		{
			$this->fraudGuard = 0;
		}

		return;
	}

	public function isFraudGuard() { return $this->fraudGuard; }

	public function setFirstName($i) { $this->fgFirstName = $i; }
	public function getFirstName() { return $this->fgFirstName; }

	public function setLastName($i) { $this->fgLastName = $i; }
	public function getLastName() { return $this->fgLastName; }

	public function setPostCode($i) { $this->fgPostCode = $i; }
	public function getPostCode() { return $this->fgPostCode; }

	public function setTown($i) { $this->fgTown = $i; }
	public function getTown() { return $this->fgTown; }

	public function setCountryB($i) { $this->fgCountryB = $i; }
	public function getCountryB() { return $this->fgCountryB; }

	public function setCountryD($i) { $this->fgCountryD = $i; }
	public function getCountryD() { return $this->fgCountryD; }

	public function setEmail($i) { $this->fgEmail = $i; }
	public function getEmail() { return $this->fgEmail; }

	public function setIP($i) { $this->fgIP = $i; }
	public function getIP() { return $this->fgIP; }

	public function getFraudGuardCode()
	{
		if (array_key_exists("fraudGuardCode", $this->responseArray) === true)
		{
			// BMH 2025-09-29 return $this->$responseArray["fraudGuardCode"];
            return $this->responseArray["fraudGuardCode"];
		}
		
		return false;
	}
	public function getFraudGuardText()
	{
		if (array_key_exists("fraudGuardText", $this->responseArray) === true)
		{
			// BMH 2025-09-29 return $this->$responseArray["fraudGuardText"];
            return $this->responseArray["fraudGuardText"];
		}
		
		return false;
	}

	/**
	 * clearFraudGuard
	 *
	 * Clears Fraud-Guard details. Useful for reusing class instances in unit tests.
	 */
	public function clearFraudGuard()
	{
		$this->setFraudGuard(0);
		$this->setFirstName("");
		$this->setLastName("");
		$this->setPostCode("");
		$this->setTown("");
		$this->setCountryB("");
		$this->setCountryD("");
		$this->setEmail("");
		$this->setIP("");

		return;
	}

	public function initFraudGuard($first, $last, $post, $town, $country_bill, $country_delivery, $email, $ip)
	{
		$this->setFraudGuard(1);
		$this->setFirstName($first);
		$this->setLastName($last);
		$this->setPostCode($post);
		$this->setTown($town);
		$this->setCountryB($country_bill);
		$this->setCountryD($country_delivery);
		$this->setEmail($email);
		$this->setIP($ip);

		return;
	}

	/**
	 * processCreditStandard:
	 *
	 * Process a standard credit card payment
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID
	 * @param int cardNumber - 12-18 digit credit-card number
	 * @param int cardMonth - 2 digit month
	 * @param int cardYear - 2 or 4 digit year
	 * @param int cardVerify - 3 or 4 digit CVV (optional)
	 * @param string currency - Exactly three characters. See SecurePay documentation for list of valid currencies. (optional)
	 *
	 * @return string txnID - Bank's unique transaction ID (use for reversal or refund), or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processCreditStandard($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify=0, $currency=SECUREPAY_CURRENCY_DEFAULT)
	{
		$this->reset();

		if(!$this->getTxnType()) //So that we can simplify fraudguard payments
		{
			$this->setTxnType(SECUREPAY_TXN_STANDARD);
		}

		if($currency)
		{
			$this->setCurrency($currency);
		}

		$this->setAmount($amount);

		$this->setTxnReference($txnReference);
		$this->setCCNumber($cardNumber);
		if($cardVerify == true && strlen($cardVerify)!=0)
		{
			$this->setCCVerify($cardVerify);
		}
		$this->setCCExpiryYear($cardYear);
		$this->setCCExpiryMonth($cardMonth);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	/**
	 * processCreditRefund:
	 *
	 * Refund a standard credit card payment. $amount can be less than the original transaction.
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID: must be same as in initial transaction
	 * @param int txnID - Result of original transaction
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processCreditRefund($amount, $txnReference, $txnID)
	{
		$this->reset();

		$this->setTxnType(SECUREPAY_TXN_REFUND);

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);

		$this->setBankTxnID($txnID);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	/**
	 * processCreditReverse:
	 *
	 * Reverse a standard credit card payment. $amount should be same as in original transaction.
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID: must be same as in initial transaction
	 * @param int txnID - Result of original transaction
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processCreditReverse($amount, $txnReference, $txnID)
	{
		$this->reset();

		$this->setTxnType(SECUREPAY_TXN_REVERSE);

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);

		$this->setBankTxnID($txnID);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	/**
	 * processCreditPreauth:
	 *
	 * Preauthorise a credit card payment
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID
	 * @param int cardNumber - 12-18 digit credit-card number
	 * @param int cardMonth - 2 digit month
	 * @param int cardYear - 2 or 4 digit year
	 * @param int cardVerify - 3 or 4 digit CVV (optional)
	 * @param string currency - Exactly three characters. See SecurePay documentation for list of valid currencies. (optional)
	 *
	 * @return string preauthID - preauthorisation ID (use to execute transaction later (processCreditAdvice)), or FALSE (check $this->getErrorText() afterwards).
	 */
	public function processCreditPreauth($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify=0, $currency=SECUREPAY_CURRENCY_DEFAULT)
	{
		$this->reset();

		$this->setTxnType(SECUREPAY_TXN_PREAUTH);

		if($currency)
		{
			$this->setCurrency($currency);
		}

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);
		$this->setCCNumber($cardNumber);
		if(strlen($cardVerify)!=0)
		{
			$this->setCCVerify($cardVerify);
		}
		$this->setCCExpiryYear($cardYear);
		$this->setCCExpiryMonth($cardMonth);

		if($this->processTransaction())
		{
			if(array_key_exists('preauthID',$this->responseArray))
			{
				return $this->responseArray['preauthID'];
			}
		}
		return false;
	}

	/**
	 * processCreditAdvice:
	 *
	 * Execute a preauthorised transaction
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators. Should be same as preauthorised amount.
	 * @param string txnReference - Merchant's unique transaction ID: must be same as in initial transaction
	 * @param string preauthID - Preauthorisation code which was returned from processCreditPreauth
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processCreditAdvice($amount, $txnReference, $preauthID)
	{
		$this->reset();

		$this->setTxnType(SECUREPAY_TXN_ADVICE);

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);
		$this->setPreauthID($preauthID);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	//Not used/tested yet
	/**
	 * processDirectCredit:
	 *
	 * Execute a Direct Entry/Credit transaction
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators. Should be same as preauthorised amount.
	 * @param string txnReference - Merchant's unique transaction ID: must be same as in initial transaction
	 * @param string accName - Account name
	 * @param string accBSB - Account BSB: 6 digits
	 * @param string accNumber - Account number. Digits only
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processDirectCredit($amount, $txnReference, $accName, $accBSB, $accNumber)
	{
		$this->reset();

		$this->setTxnType(SECUREPAY_TXN_DIRECTCREDIT);

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);
		$this->setAccName($accName);
		$this->setAccNumber($accNumber);
		$this->setAccBSB($accBSB);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	//Not used/tested yet
	/**
	 * processDirectDebit:
	 *
	 * Execute a Direct Entry/Debit transaction
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators. Should be same as preauthorised amount.
	 * @param string txnReference - Merchant's unique transaction ID: must be same as in initial transaction
	 * @param string accName - Account name
	 * @param string accBSB - Account BSB: 6 digits
	 * @param string accNumber - Account number. Digits only
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 */
	public function processDirectDebit($amount, $txnReference, $accName, $accBSB, $accNumber)
	{
		$this->reset();
		$this->setTxnType(SECUREPAY_TXN_DIRECTDEBIT);

		$this->setAmount($amount);
		$this->setTxnReference($txnReference);
		$this->setAccName($accName);
		$this->setAccNumber($accNumber);
		$this->setAccBSB($accBSB);

		if($this->processTransaction())
		{
			if(array_key_exists('banktxnID',$this->responseArray))
			{
				return $this->responseArray['banktxnID'];
			}
		}
		return false;
	}

	//Not used/tested yet
	/**
	 * processFraudGuard:
	 *
	 * Execute a FraudGuard/Credit transaction
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID
	 * @param int cardNumber - 12-18 digit credit-card number
	 * @param int cardMonth - 2 digit month
	 * @param int cardYear - 2 or 4 digit year
	 * @param int cardVerify - 3 or 4 digit CVV (optional)
	 * @param string currency - Exactly three characters. See SecurePay documentation for list of valid currencies. (optional)
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 *
	 * @notes Check $this->getFraudGuardCode() and $this->getFraudGuardText() afterwards for details.
	 */
	public function processFraudGuard($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify=0, $currency=SECUREPAY_CURRENCY_DEFAULT)
	{
		$this->reset();
		$this->setTxnType(SECUREPAY_TXN_ANTIFRAUD_PAY);

		return $this->processCreditStandard($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify, $currency);
	}

	//Not used/tested yet
	/**
	 * processFraudGuardCheck:
	 *
	 * Execute a FraudGuard/Check transaction
	 *
	 * @param float amount - Numeric and decimal only: no thousand separators
	 * @param string txnReference - Merchant's unique transaction ID
	 * @param int cardNumber - 12-18 digit credit-card number
	 * @param int cardMonth - 2 digit month
	 * @param int cardYear - 2 or 4 digit year
	 * @param int cardVerify - 3 or 4 digit CVV (optional)
	 * @param string currency - Exactly three characters. See SecurePay documentation for list of valid currencies. (optional)
	 *
	 * @return string txnID - Bank's unique transaction ID, or FALSE in case of failure (check $this->getErrorText() afterwards).
	 *
	 * @notes Check $this->getFraudGuardCode() and $this->getFraudGuardText() afterwards for details.
	 */
	public function processFraudGuardCheck($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify=0, $currency=SECUREPAY_CURRENCY_DEFAULT)
	{
		$this->reset();
		$this->setTxnType(SECUREPAY_TXN_ANTIFRAUD_CHECK);

		return $this->processCreditStandard($amount, $txnReference, $cardNumber, $cardMonth, $cardYear, $cardVerify, $currency);
	}

	/**
	 * processTransaction:
	 *
	 * Attempts to process the transaction using the supplied details
	 *
	 * @return boolean Returns true for succesful (approved) transaction / false for failure (declined) or error
	 */
	private function processTransaction ()
	{
		// check that self is a valid gateway object
		if ( !$this->gatewayObjectValid )
		{
			$this->errorString = self::GATEWAY_ERROR_OBJECT_INVALID;
			return false;
		}

		// check parameters
		if( $this->getTxnType()==SECUREPAY_TXN_STANDARD ||
			$this->getTxnType()==SECUREPAY_TXN_PREAUTH ||
			$this->getTxnType()==SECUREPAY_TXN_ANTIFRAUD_PAY ||
			$this->getTxnType()==SECUREPAY_TXN_ANTIFRAUD_CHECK)
		{
			if ($this->checkCCParameters() == false)
			{
				return false;
			}
		}
		else if ( $this->getTxnType()==SECUREPAY_TXN_DIRECTDEBIT ||
				  $this->getTxnType()==SECUREPAY_TXN_DIRECTCREDIT )
		{
			if ($this->checkDirectParameters() == false)
			{
				return false;
			}
		}
		if ($this->checkTxnParameters() == false)
		{
			return false;
		}

		//Create request message. Destroys CC/CCV values
		$requestMessage = $this->createXMLTransactionRequestString();
		//Send request
		$response = $this->sendRequest( $this->gatewayURL, $requestMessage );

		unset($requestMessage);

		// BMH $this->responseArray["raw-response"] = htmlentities($response);
        $this->responseArray["raw-response"] = htmlentities($response);

		if ( $response === false )
		{
			if ( strlen( $this->errorString ) == 0 )
			{
				$this->errorString = self::GATEWAY_ERROR_RESPONSE_ERROR;
			}
			return false;
		}
        // BMH DEBUG  
        if(SECUREPAYAPI_CODE_DEBUG == 'true') {     
            $this->_logapi("p"," ln" . __LINE__ . ' print_r $response ', $response) ; // BMH DEBUG 
        }

		//Process response for validity
		if ( $this->processTransactionResponseMessageIntoResponseArray(  $response ) === false )
		{
			if ( strlen( $this->errorString ) == 0 )
			{
				$this->errorString = self::GATEWAY_ERROR_RESPONSE_INVALID;
			}
			return false;
		}

		// if we get this far, the transaction is succesful and "approved"
		$this->responseArray["txnResult"] = true;

		return true;
	}

	/**
	 * checkCCParameters
	 * Check the input parameters are valid for a credit card transaction
	 * @return boolean Return TRUE for all checks passed OK, or FALSE if an error is detected
	 */
	private function checkCCParameters()
	{
		// the string ccNumber must be all numeric, and between 12 and 19 digits long
		if (strlen( $this->getCCNumber() ) < 12 ||
			strlen( $this->getCCNumber() ) > 19 ||
			preg_match("/\D/",$this->getCCNumber()) )//Match non-digit
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_CCNUMBER;
			return false;
		}

		// the string $ccExpiryMonth must be all numeric with value between 1 and 12
		if (preg_match("/\D/", $this->getCCExpiryMonth()) ||
			(int) $this->getCCExpiryMonth() < 1 ||
			(int) $this->getCCExpiryMonth() > 12 )
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_CCEXPIRY;
			return false;
		}

		// the string $ccExpiryYear is in YY format, and must be between now and (now+12)
		if (preg_match( "/\D/", $this->getCCExpiryYear()) ||
			(strlen($this->getCCExpiryYear()) != 2) || //YY form
			(int) $this->getCCExpiryYear() < (int) substr(date("Y"),2) || // Between now and now + 12
			(int) $this->getCCExpiryYear() > ( (int) substr(date("Y"),2) + 12 ) )
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_CCEXPIRY;
			return false;
		}

		// The CVV is optional
		if ($this->getCCVerify() != false)
		{
			// the string $ccVericationNumber must be all numeric with value between 000 and 9999
			if (preg_match( "/\D/", $this->getCCVerify() ) || // REGEXP: true if "any match for non-numeral"
				strlen( $this->getCCVerify() ) < 3 ||
				strlen( $this->getCCVerify() ) > 4 ||
				(int) $this->getCCVerify() < 0 ||
				(int) $this->getCCVerify() > 9999 )
			{
				$this->errorString = self::GATEWAY_ERROR_INVALID_CC_CVC;
				return false;
			}
		}
		return true;
	}

	//Not used/tested yet
	/**
	 * checkDirectParameters
	 *
	 * Check the input parameters are valid for a direct entry transaction
	 *
	 * @return boolean Return TRUE for all checks passed OK, or FALSE if an error is detected
	 */
	private function checkDirectParameters()
	{
		// the string accNumber must be all numeric, and between 12 and 19 digits long
		if (preg_match( "/\D/", $this->getAccNumber() ) )// REGEXP: true if "any match for non-numeral"
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_ACCOUNTNUMBER;
			return false;
		}

		if (!preg_match( "/[a-zA-Z0-9 _]+/", $this->getAccName() )) // REGEXP: Match alpha-numeric characters + space, underscore
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_ACCOUNTNAME;
			return false;
		}
		// the string $accBSB must be all numeric with value between 000000 and 999999
		if (preg_match( "/\D/", $this->getAccBSB())) // REGEXP: true if "any match for non-numeral"
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_BSB;
			return false;
		}

		return true;
	}

	/* *
	 * checkTxnParameters
	 *
	 * Check that the common values are within requirements
	 *
	 * @param string $txnAmount
	 * @param string $txnReference
	 *
	 * @return TRUE for pass, FALSE for fail
	 */
	private function checkTxnParameters ()
	{
		$amount = (string)$this->getAmount();
		// Ensure amount is a string of digits (cents) and non-negative
		if (!preg_match('/^\d+$/', $amount) || (int) $amount < 0 )
		{
			$this->errorString = self::GATEWAY_ERROR_INVALID_TXN_AMT;
			return false;
		}

		$ref = $this->getTxnReference();
		if ( $this->getTxnType()==SECUREPAY_TXN_DIRECTDEBIT ||
			 $this->getTxnType()==SECUREPAY_TXN_DIRECTCREDIT )
		{
			// Direct Entry Payment References need to conform to EBCDIC, and should be <= 18 characters
			if (strlen($ref) == 0 || strlen($ref)>18 ||
				preg_match('/[^0-9a-zA-Z*\.&\/-_\']/', $ref)) // REGEXP: match any non-EBCDIC character
			{
				$this->errorString = self::GATEWAY_ERROR_INVALID_REF_ID;
				return false;
			}
		}
		else
		{
			// Credit Txn References can have any character except space and single quote and need to be less than 60 characters
			if (strlen($ref) == 0 || strlen($ref)>60 ||
				preg_match('/[^ \']/', $ref)==false) // REGEXP: match invalid characters
			{
				$this->errorString = self::GATEWAY_ERROR_INVALID_REF_ID;
				return false;
			}
		}
		return true;
	}

    /**
	 * createXMLTransactionRequestString:
	 * Creates the XML request string for a transaction request message. Destroys CC/CCV values.
	 *
	 * @return string xml_transaction
     */
	private function createXMLTransactionRequestString()
	{
        $appenNumber = '';
        if(SECUREPAY_GATEWAY_MODE_TEST){
	        // BMH DEBUG	echo 'BMH SECUREPAY_GATEWAY_MODE_TEST =' . SECUREPAY_GATEWAY_MODE_TEST . 'line 980 securepay_xml_api'; // BMH
                  // BMH  $appenNumber =  '520-';
                }
		$x =
		"<?xml version=\"1.0\" encoding=\"UTF-8\"?>". /* BMH 2019 from UTF-8 to utf8mb4 */
		"<SecurePayMessage>" .
			"<MessageInfo>" .
				"<messageID>".htmlentities($this->getTxnReference().date("his").current(explode(' ',microtime( ))))."</messageID>".
				"<messageTimestamp>".htmlentities($this->getGMTTimeStamp())."</messageTimestamp>".
				"<timeoutValue>".htmlentities(self::TIMEOUT)."</timeoutValue>".
				"<apiVersion>xml-4.2</apiVersion>" .
			"</MessageInfo>".
			"<MerchantInfo>".
				"<merchantID>".htmlentities($this->getMerchantID())."</merchantID>" .
				"<password>".htmlentities($this->getMerchantPW())."</password>" .
			"</MerchantInfo>".
			"<RequestType>Payment</RequestType>".
			"<Payment>".
				"<TxnList count=\"1\">".
					"<Txn ID=\"1\">".
						"<txnType>".htmlentities($this->getTxnType())."</txnType>".
						"<txnSource>23</txnSource>". //23 is the XML API
						"<amount>".htmlentities($this->getAmount())."</amount>";

// BMH boc
			$x .=		"<currency>".htmlentities($this->getCurrency())."</currency>";
      if($this->getTxnType()==SECUREPAY_TXN_REFUND){
         $x .=		"<purchaseOrderNo>".htmlentities($this->getTxnReference())."</purchaseOrderNo>";
         }else{
        // BMH remove time stamp from transaction id $x .=		"<purchaseOrderNo>".htmlentities($appenNumber.$this->getTxnReference().'-'.time())."</purchaseOrderNo>";
         $x .=		"<purchaseOrderNo>".htmlentities($this->getTxnReference())."</purchaseOrderNo>";
      // BMH eoc
        }
		if(	$this->getTxnType()==SECUREPAY_TXN_ADVICE)
		{
			$x .=		"<preauthID>".htmlentities($this->getPreauthID())."</preauthID>";
		}
		if(	$this->getTxnType()==SECUREPAY_TXN_REFUND ||
			$this->getTxnType()==SECUREPAY_TXN_REVERSE &&
			$this->getBankTxnID() != 0)
		{
			$x .=		"<txnID>".htmlentities($this->getBankTxnID())."</txnID>";
		}

		if(	$this->getTxnType()==SECUREPAY_TXN_STANDARD	||
			$this->getTxnType()==SECUREPAY_TXN_PREAUTH )
		{
			$x .=		"<CreditCardInfo>".
							"<cardNumber>".htmlentities($this->getClearCCNumber())."</cardNumber>";
			if (trim($this->getCCVerify()) != false)
			{
				$x .=		"<cvv>".htmlentities($this->getClearCCVerify())."</cvv>";
			}
			$x .=			"<expiryDate>".htmlentities(sprintf("%02d",$this->getCCExpiryMonth())."/".sprintf("%02d",$this->getCCExpiryYear()))."</expiryDate>".
						"</CreditCardInfo>";
		}
		else if ( $this->getTxnType()==SECUREPAY_TXN_DIRECTDEBIT ||
				  $this->getTxnType()==SECUREPAY_TXN_DIRECTCREDIT )
		{
			$x .=		"<DirectEntryInfo>".
							"<bsbNumber>".htmlentities($this->getAccBSB())."</bsbNumber>".
							"<accountNumber>".htmlentities($this->getAccNumber())."</accountNumber>".
							"<accountName>".htmlentities($this->getAccName())."</accountName>".
						"</DirectEntryInfo>";
		}
		if ($this->isFraudGuard())
		{
			$x .=		"<BuyerInfo>".
							"<firstName>".htmlentities($this->getFirstName())."</firstName>".
							"<lastName>".htmlentities($this->getLastName())."</lastName>".
							"<zipCode>".htmlentities($this->getPostCode())."</zipCode>".
							"<town>".htmlentities($this->getTown())."</town>".
							"<billingCountry>".htmlentities($this->getCountryB())."</billingCountry>".
							"<deliveryCountry>".htmlentities($this->getCountryD())."</deliveryCountry>".
							"<emailAddress>".htmlentities($this->getEmail())."</emailAddress>".
							"<ip>".htmlentities($this->getIP())."</ip>".
						"</BuyerInfo>";
		}
			$x .=	"</Txn>".
				"</TxnList>".
			"</Payment>";
		if ($this->getIdentifier())
		{
	$x .=	"<identifier>".htmlentities($this->getIdentifier())."</identifier>";
		}
$x .=	"</SecurePayMessage>";

		return $x;
	}

	/**
	 * getGMTTimeStamp:
	 *
	 * this function creates a timestamp formatted as per requirement in the
	 * SecureXML documentation
	 *
	 * @return string The formatted timestamp
	 */
	public function getGMTTimeStamp()
	{
		/* Format: YYYYDDMMHHNNSSKKK000sOOO
			YYYY is a 4-digit year
			DD is a 2-digit zero-padded day of month
			MM is a 2-digit zero-padded month of year (January = 01)
			HH is a 2-digit zero-padded hour of day in 24-hour clock format (midnight =0)
			NN is a 2-digit zero-padded minute of hour
			SS is a 2-digit zero-padded second of minute
			KKK is a 3-digit zero-padded millisecond of second
			000 is a Static 0 characters, as SecurePay does not store nanoseconds
			sOOO is a Time zone offset, where s is �+� or �-�, and OOO = minutes, from GMT.
		*/

		$val = date("Z") / 60;
		if ($val >= 0)
		{
			$val = "+" . strval($val);
		}

		$stamp = date("YdmGis000000") . $val;

		return $stamp;
	}

        /**
     * sendRequest:
     * uses cURL to open a Secure Socket connection to the gateway,
     * sends the transaction request and then returns the response
     * data
     *
     * @param $postURL The URL of the remote gateway to which the request is sent
     * @param $requestMessage
     */
    private function sendRequest($postURL, $requestMessage) {
        $ch = curl_init();
        global $oid;
        //global $customer_id;
        global $amount;

        // Set up curl parameters
        curl_setopt($ch, CURLOPT_URL, $postURL);   // set remote address
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // Make CURL pass the response as a curl_exec return value instead of outputting to STDOUT
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);     // Activate the POST method
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $requestMessage); // add the request message itself
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "xmlRequest=" . $requestMessage);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: text/xml;charset=UTF-8', 'Content-length: ' .

        strlen($requestMessage)));

        $result = curl_exec($ch);

        $debugoutput = curl_getinfo($ch);
        $curl_error_message = curl_error($ch); // must retrieve an error message (if any) before closing the curl object

        // PHP 8.0 to 8.5 redundant curl_close($ch);

        if ($result === false) {
            $this->errorString = self::GATEWAY_ERROR_CURL_ERROR . ': ' . $curl_error_message;
            return false;
        }

        // we do not need the header part of the response, trim it off the result

        $pos = strstr($result, "\n");
        // BMH $result = substr($result, $pos);
         if ($pos !== false ) {
            $result = substr($result, (int)$pos);
        } // BMH 2025-09-27
         
        return $result;
    }

	/**
	 * processTransactionResponseMessageIntoResponseArray:
	 * converts the response XML message into a nested array structure and then
	 * pulls out the relevant data into a simplified result array
	 *
	 * @param string $responseMessage - An XML response from the gateway
	 * @return boolean True to indicate succesful decoding of response message AND succesful txn result, false to indicate an error or declined result
	 */
	private function processTransactionResponseMessageIntoResponseArray ( string $responseMessage ) : bool
	{
    global $oid;
    global $customer_id; 
    global $cc_month; 
    global $cc_year; 

		$xmlres = array();
		$xmlres = $this->convertXMLToNestedArray( $responseMessage );

        // BMH DEBUG   
        if(SECUREPAYAPI_CODE_DEBUG == 'true') { 
            $this->_logapi("p", ' ln' . __LINE__ . ' $xmlres= print_r' . "\r\n : $" . number_format(($this->amount/100),2) 
            . " #" . $this->oid . " Cust:". $this->customer_id . " Exp:" . $this->cc_month . "/" . $this->cc_year, $xmlres); // BMH

            $msg =  ' ln' . __LINE__ . ' var_dump $xmlres=  ' . number_format(($this->amount/100),2) . ' #' . $oid . ' Cust:' . $customer_id . ' Exp:' . $cc_month . '/' . $cc_year; // BMH
            $this->_logapi("d", $msg ,$xmlres  ); // BMH
               
            $this->_logapi("p",   ' ln' . __LINE__ . ' print_r $xmlres=  ' .'Amt:' .number_format(($this->amount/100),2) . ' #' . $this->oid . ' Cust:' . $this->customer_id . ' Exp:' . $this->cc_month . '/' . $this->cc_year , $xmlres); // BMH
        }
        
		if ( $xmlres === FALSE ) // XML parsing error
		{
			if ( strlen( $this->errorString ) == 0 )
			{
				$this->errorString = self::GATEWAY_ERROR_RESPONSE_XML_MESSAGE_ERROR;
			}
			return false;
		}
		
		// BMH $responseArray["raw-XML-response"] = htmlentities($responseMessage);
        $responseArray["raw-response"] = htmlentities($responseMessage);

		$statusCode = trim( $xmlres['SecurePayMessage']['Status']['statusCode'] ?? ''); // BMH 2025-12-02
		$statusDescription = trim($xmlres['SecurePayMessage']['Status']['statusDescription'] ?? ''); // BMH 2025-12-02

		$responseArray["statusCode"] = $statusCode;
		$responseArray["statusDescription"] = $statusDescription;

		// Three digit codes indicate a repsonse from the Securepay gateway (error detected by gateway)
		if ( strcmp( $statusCode, '000' ) != 0 )
		{
			$this->errorString = self::GATEWAY_ERROR_SECUREPAY_STATUS." : ".$statusCode." ".$statusDescription;
			return false;
		}

		$responseArray["messageID"] = trim($xmlres['SecurePayMessage']['MessageInfo']['messageID'] ?? ''); // BMH 2025-12-02
		$responseArray["messageTimestamp"] = trim($xmlres['SecurePayMessage']['MessageInfo']['messageTimestamp'] ?? ''); // BMH 2025-12-02
		$responseArray["apiVersion"] = trim($xmlres['SecurePayMessage']['MessageInfo']['apiVersion'] ?? ''); // BMH 2025-12-02
		$responseArray["RequestType"] =  trim($xmlres['SecurePayMessage']['RequestType'] ?? ''); // BMH 2025-12-02
		$responseArray["merchantID"] = trim($xmlres['SecurePayMessage']['MerchantInfo']['merchantID'] ?? ''); // BMH 2025-12-02
		$responseArray["txnType"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['txnType'] ?? ''); // BMH 2025-12-02
		$responseArray["txnSource"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['txnSource'] ?? ''); // BMH 2025-12-02
		$responseArray["amount"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['amount'] ?? ''); // BMH 2025-12-02
		$responseArray["approved"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['approved'] ?? ''); // BMH 2025-12-02 
		$responseArray["responseCode"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['responseCode'] ?? ''); // BMH 2025-12-02
		$responseArray["responseText"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['responseText'] ?? ''); // BMH 2025-12-02
		$responseArray["banktxnID"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['txnID'] ?? ''); // BMH 2025-12-02
		$responseArray["settlementDate"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['settlementDate'] ?? ''); // BMH 2025-12-02

		if($this->isFraudGuard())
		{
			$responseArray["fraudGuardCode"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['antiFraudResponseCode'] ?? ''); // BMH 2025-12-02
			$responseArray["fraudGuardText"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['antiFraudResponseText'] ?? ''); // BMH 2025-12-02
		}

		if( $this->getTxnType()==SECUREPAY_TXN_PREAUTH && array_key_exists('preauthID',$xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']))
		{
			$responseArray["preauthID"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['preauthID'] ?? ''); // BMH 2025-12-02
		}

		if($this->getRequestType() == SECUREPAY_REQ_PERIODIC)
		{
			if(	$this->getTxnType()==SECUREPAY_TXN_STANDARD	||
				$this->getTxnType()==SECUREPAY_TXN_PREAUTH )
			{
				$responseArray["creditCardPAN"] = trim($xmlres['SecurePayMessage']['Periodic']['PeriodicList']['PeriodicItem']['CreditCardInfo']['pan'] ?? ''); // BMH 2025-12-02
				$responseArray["expiryDate"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['CreditCardInfo']['expiryDate']) ?? ''; // BMH 2025-12-02
			}
		}
		else if (strtoupper($responseArray['approved']) == 'YES' &&
				($this->getTxnType()==SECUREPAY_TXN_DIRECTDEBIT ||
				 $this->getTxnType()==SECUREPAY_TXN_DIRECTCREDIT) )
		{
			$responseArray["bsbNumber"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['DirectEntryInfo']['bsbNumber'] ?? ''); // BMH 2025-12-02
			$responseArray["accountNumber"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['DirectEntryInfo']['accountNumber'] ?? ''); // BMH 2025-12-02
			$responseArray["accountName"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['DirectEntryInfo']['accountName'] ?? ''); // BMH 2025-12-02
		}
		else if ($this->getRequestType() == SECUREPAY_REQ_PAYMENT)
		{
			$responseArray["creditCardPAN"] = trim($xmlres['SecurePayMessage']['Periodic']['PeriodicList']['PeriodicItem']['CreditCardInfo']['pan'] ?? ''); // BMH 2025-12-02
			$responseArray["expiryDate"] = trim($xmlres['SecurePayMessage']['Payment']['TxnList']['Txn']['CreditCardInfo']['expiryDate'] ?? ''); // BMH 2025-12-02
		}

		$this->responseArray = $responseArray;

		/* field "successful" = "Yes" means "triggered transaction successfully registered", anything else is failure */
		/* responseCodes:
			"00" indicates approved,
			"08" is Honor with ID (approved) and
			"77" is Approved (ANZ only).
			Any other 2 digit code is a decline or error from the bank. */

		if ((strcasecmp( $responseArray["approved"], "Yes" ) == 0) &&
			(strcmp( $responseArray["responseCode"], "00" ) === 0 ||
			 strcmp( $responseArray["responseCode"], "08" ) === 0 ||
			 strcmp( $responseArray["responseCode"], "77" ) === 0 ) )
		{
			return true;
		}
		else
		{
			$this->errorString = self::GATEWAY_ERROR_TXN_DECLINED." (".trim($responseArray["responseCode"] ?? '')."): ".trim($responseArray["responseText"] ?? ''); // BMH 2025-12-02
			return false;
		}
	}

	/**
	 * convertXMLToNestedArray:
	 * converts an XML document into a nested array structure
	 *
	 * @param string $XMLDocument An XML document
	 * @return boolean True to indicate succesful conversion of document, false to indicate an error
	 */
	private function convertXMLToNestedArray (string $XMLDocument ): array|false
	{
		$output = array();
		$parser = xml_parser_create();

		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		$parse_result = xml_parse_into_struct($parser, $XMLDocument, $values);

		if ( $parse_result === 0)
		{
			$this->errorString = self::GATEWAY_ERROR_XML_PARSE_FAILED.": ".xml_get_error_code ( $parser )." ".xml_error_string (xml_get_error_code ( $parser ) );
			// PHP8.5 deprecated xml_parser_free($parser);
			return false;
		}

		// PHP 8.0 to 8.5 xml_parser_free($parser);

		$hash_stack = array();

		foreach ($values as $val)
		{
			switch ($val['type'])
			{
				case 'open':
					array_push($hash_stack, $val['tag']);
					break;

				case 'close':
					array_pop($hash_stack);
					break;

				case 'complete':
					array_push($hash_stack, $val['tag']);
					if ( array_key_exists('value', $val) )
					{
					//	eval("\$output['" . implode($hash_stack, "']['") . "'] = \"{$val['value']}\";"); // BMH
                    eval("\$output['" . implode( "']['",$hash_stack) . "'] = \"{$val['value']}\";");
					}
					else // to handle empty self closing tags i.e. <paymentInterval/>
					{
						//eval("\$output['" . implode($hash_stack, "']['") . "'] = null;"); // BMH
                        eval("\$output['" . implode( "']['",$hash_stack) . "'] = null;");
					}
					array_pop($hash_stack);
					break;
			}
		}
		return $output;
	}

    function _logapi($x, $msg, $dump)
	{ 
        global $purchaseOrderId;
        //global $_logapiDir;

		$file = $this->_logapiDir . '/' . 'Securepayxmlapi.log';
		if ($fp = @fopen($file, 'a'))
		{
        //$today = date("Y-m-d_H:i:s");         // BMH does not show microseconds
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
                @fwrite($fp, "".time().": ".$today . $msg . " \r\n" . var_dump($dump) ."\r\n"); 
                // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
                break;
            case "p":
                @fwrite($fp, "".time().": ".$today . $msg . " \r\n" . print_r($dump, true) ."\r\n"); 
                // @fwrite($fp, "".time().": ".$today . ": "  . 'OrderId: ' . $purchaseOrderId  . '<br>'. print_r($dump, true) ."\r\n"); // stores epoch time + date
                // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
                break;
        }
			//@fwrite($fp, "".time().": ".$today . ": " .$msg . " " . $purchaseOrderId ."\r\n"); // stores epoch time + date
            // BMH @fwrite($fp, "".time().": ".$msg); // stores time as epoch time
			//@fclose($fp);
		}
	}
}
