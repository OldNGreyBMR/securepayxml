Date Submitted: 2026-04-06
Version 1.6.1
Author:         OldNGrey (BMH)

"SecurePay XML API" Installation Guide for Zen Cart 1.5.8a to 2.2.2
===========================================================
This plugin enables support for credit-card transactions via the SecurePay (AU)(now owned by FatZebra) payment gateway.

BMH  changes
============
Amendments updated to be compatible with Zen Cart 158+, 2.0.0, 2.1.0, 2.2.0, 2.2.2 and PHP 8.3 & 8.4 & 8.5
see file changelog.md for all changes

Dependencies:
--------------
Zen Cart 1.5.8a or greater to 2.2.2
PHP 8.2 to 8.5
cURL

"SecurePay XML API" Installation Guide for Zen Cart 1.5.8+
===========================================================
WARNING:
If installing this for the first time proceed as detailed below.

IF UPDATING you must change the size of the banktxnid field in the securepayxml table.
READ the "securepayxml-update-datebase-sql-script.txt" file.

Plugin Details:
-----------------------------------------------------------
This plugin enables support for credit-card transactions via the SecurePay (AU) payment gateway.

It supports the following kinds of transactions:
	Standard Credit
	Preauthorise
	Advice (complete)
	Refund
	Reverse (Void)

Manual Installation instructions:
=================================
These instructions assume that you already have Zen Cart installed, configured and operational.

1. Extract the Installer archive to a temporary location.

2. Create a new folder named "securepayxml" in your zen-cart payment modules folder:
	 /zencart_path/includes/modules/payment/securepayxml
	
	(Substitute "/zencart_path" for the path to your Zen Cart installation)

3. Copy the files under the "includes" directory into their respective paths in your Zen Cart installation:
	[	(Substitute "/zencart_path" for the path to your Zen Cart installation)]
    
	"includes/modules/payment/securepayxml.php" to "/zencart_path/includes/modules/payment/"
	"includes/modules/payment/securepay_xml_api.php" to "/zencart_path/includes/modules/payment/"
	"includes/modules/payment/securepayxml/securepayxml_admin_notification.php" to "/zencart_path/includes/modules/payment/securepayxml"
	"includes/languages/english/modules/payment/securepayxml.php" to "/zencart_path/includes/languages/english/modules/payment/"
	"includes/languages/english/modules/payment/securepay_xml_api.php" to "/zencart_path/includes/languages/english/modules/payment/"
		
	[ (Change YOUR_TEMPLATE to the name of your template folder) ]
    
    "includes/templates/YOUR_TEMPLATE/css"
    "includes/templates/YOUR_TEMPLATE/images"
    "includes/templates/YOUR_TEMPLATE/css"
    and copy the three folders (css, images and templates) to YOUR_TEMPLATE folder
    
    
4. Configure the module via the Zen Cart admin interface.
	-Log-in
	-Navigate to Modules->Payment
	-Select "SecurePay XML API (AU)", click "Install"
	-Select "Configure", enter your SecurePay merchant identifier and password, configure as desired, then click "update"
	
5. Test the module to ensure that everything is working correctly (set it to "Test" and try a transaction. See "Test Gateway Operation" for more details).

6. The SecurePay XML API payment module is now installed. Set it to "Live" when your account is activated, and you are ready to receive payments through SecurePay.

Test Gateway Operation
---------------------------------------------------------------------------------------------------
In test mode, your transactions will be sent to the the SecurePay Test Gateway. The Merchant ID and
Password for the test gateway are NOT the same as the Merchant ID and Password for your live 
Merchant Account. Please contact your SecurePay Payment Gateway Reseller or SecurePay Support if 
you need a test Merchant ID and Transaction password.

When processing transactions, if the transaction is would otherwise give a 000 "Approved" response
code, the Test gateway will return the cents portion of the transaction amount as the response code
(i.e. a transaction amount of $1.05 will result in a "(05): Do Not Honour" response).

To achieve an "approved" test transaction, ensure the total transaction amount (including tax and
shipping costs) is a round dollar value (i.e. $1.00)

Support Contact
---------------------------------------------------------------------------------------------------
Please visit the SecurePay website (http://www.securepay.com.au/) for our support contact details.

SecurePay Documentation
=======================
See https://auspost.com.au/payments/docs/securepay/?javascript#other-integration-methods and select XML API Integration to download the latest guide.
