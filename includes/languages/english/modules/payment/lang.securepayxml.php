<?php
/**
 * @package securepayxml_payment_module
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: securepayxml.php 2025-07-20  BMH
 * BMH 2022-08-20 modify for zc158
 * // BMH 2025-02-15 modify for zc210 (!defined('MODULE_PAYMENT_SECUREPAYXML_STATUS')
 * //     2025-03-08  MODULE_PAYMENT_SECUREPAYXML_TEXT_REFUND_CONFIRM_ERROR
 * //     2025-12-10  add MODULE_PAYMENT_SECUREPAYXML_ENTRY_CAPTURE_BUTTON_TEXT
 * //     2026-04-06 clean up commented old constants
 */
 
 $define = [
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_ADMIN_TITLE' => 'SecurePay XML API (AU)',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CATALOG_TITLE' => 'Credit Card',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_DESCRIPTION' => '<b>SecurePay XML API (AU)</b><br>Receive credit-card payments via the SecurePay Gateway<br>Configuration Instructions<br>1. Click "Install"<br>2. Enter your SecurePay merchant id and password (obtained from SecurePay support)<br><br><b>Requirements:</b><br>PHP cURL<br>A <a href="http://securepay.com.au">SecurePay</a> merchant account.',
    'MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH' =>'Preauth/Advice',
    'MODULE_PAYMENT_SECUREPAYXML_MODE_STANDARD' =>'Standard Payment',
    
    'MODULE_PAYMENT_SECUREPAYXML_MODE_PREAUTH_DESC' => 'Preauth only',
    'MODULE_PAYMENT_SECUREPAYXML_MODE_STANDARD_DESC' => 'Standard Payment',
    
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_TYPE' => 'Credit Card Type:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_OWNER' => 'Credit Card Owner:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_NUMBER' => 'Credit Card Number:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CVV' => 'CVV Number:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_CREDIT_CARD_EXPIRES' => 'Credit Card Expiry Date:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_OWNER' => 'The cardholder name must be at least ' . CC_OWNER_MIN_LENGTH . ' characters.\n',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_NUMBER' => 'The credit card number must be at least ' . CC_NUMBER_MIN_LENGTH . ' characters.\n',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_JS_CC_CVV' => 'You must enter the 3 or 4 digit CVV on the back of your credit card',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_ERROR' => 'Credit Card Error!',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_DECLINED_MESSAGE' => 'Your card has been declined.  Please re-enter your card information, try another card, or contact the store owner for assistance.',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_DECLINED_AVS_MESSAGE' => 'Invalid Billing Address.  Please re-enter your card information, try another card, or contact the store owner for assistance.',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_DECLINED_GENERAL_MESSAGE' => 'Your card has been declined.  Please re-enter your card information, try another card, or contact the store owner for assistance.',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_POPUP_CVV_LINK' => 'What\'s this?',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_NOT_CONFIGURED' => '<span class="alert">&nbsp;(NOTE: Module is not configured yet)</span>',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_PEMFILE_MISSING' => '<span class="alert">&nbsp;The xyzxyz.pem certificate file cannot be found.</span>',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_ERROR_CURL_NOT_FOUND' => 'CURL functions not found - required for SecurePayXML payment module',
    
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_GENERAL_ERROR' => 'We are sorry. There was a system error while processing your card. Your information is safe. Please notify the Store Owner to arrange alternate payment options.',
    
    'MODULE_PAYMENT_SECUREPAYXML_MESSAGE' => 'Response Message:',
    'MODULE_PAYMENT_SECUREPAYXML_APPROVAL_CODE' => 'Approval Code:',
    'MODULE_PAYMENT_SECUREPAYXML_TRANSACTION_REFERENCE_NUMBER' => 'Reference Number:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_TEST_MODE' => '<span class="alert">&nbsp;(NOTE: Module is in testing mode)</span>',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_ORDERTYPE' => 'Order Type:',
    
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_NO_MATCHING_ORDER_FOUND' => 'Error: Could not find transaction details for the record specified.',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_INVALID_AMOUNT' => 'Error: You did not enter a valid amount.',
    
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_TITLE' => '<b>Issue Refund:</b>',
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_AMOUNT_TEXT' => 'Amount:',
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_BUTTON_TEXT' => 'Refund',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_REFUND' => 'Refunded $%s, on order %s with transaction id %s',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_REFUND_CONFIRM_ERROR' => '',
    
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_TITLE' => '<b>Complete preauthorised transaction</b>',
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_AMOUNT_TEXT' => 'Amount: ',
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_BUTTON_TEXT' => 'Complete',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_PREAUTH' => 'Funds Capture initiated. Amount: %s.  Transaction ID: %s - AuthCode: %s',
    
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_VOID_TITLE' => '<b>Reverse transaction:</b>',
    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_VOID_BUTTON_TEXT' => 'Reverse',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_VOID_CONFIRM_CHECK' => 'Confirm:',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_VOID_CONFIRM_ERROR' => 'Error: You did not check the confirm box.',
    'MODULE_PAYMENT_SECUREPAYXML_TEXT_VOID' => 'Reversed order: %s with transaction: %s.',

    'MODULE_PAYMENT_SECUREPAYXML_ENTRY_CAPTURE_BUTTON_TEXT' => 'Do Capture',
    ];

  /* if (defined('MODULE_PAYMENT_SECUREPAYXML_STATUS') && MODULE_PAYMENT_SECUREPAYXML_STATUS == 'True') {
    define('MODULE_PAYMENT_SECUREPAYXML_TEXT_DESCRIPTION', '');
  } 
    */
  if (!defined('MODULE_PAYMENT_SECUREPAYXML_STATUS')) {
      define('MODULE_PAYMENT_SECUREPAYXML_STATUS', '');
      define('MODULE_PAYMENT_SECUREPAYXML_SORT_ORDER', '');
      define('MODULE_PAYMENT_SECUREPAYXML_TEST', '');
      define('MODULE_PAYMENT_SECUREPAYXML_ORDER_STATUS_ID', '');
      define('MODULE_PAYMENT_SECUREPAYXML_MODE', '');
      define('MODULE_PAYMENT_SECUREPAYXML_ZONE', '');
  }      //BMH 2025-02-15 for uninstall
  
  return $define;