Change Log
securepay xml V161
==================
Changes for Version 161
-----------------------
PHP 8.5 compliant remove deprecated commands
reformat; PHP8.4 compliant; Zen Cart V2.2.2 compliant
add version number id Admin console; improve instructions

Changes for Version 1.6.1
-------------------------
2026-01-15 null coalesce ; transaction log enhancements; debug logging improvements
2026-01-16 1.6.0 debug logging improvements;
2026-04-06 1.6.1 ln943 replace str() with date() in log function to fix error in PHP 8.3 and above


Changes for Version 159e
-----------------------
1. change log file output
2. strftime deprecated so replace with date() 
3. declare all vars
4. increase size of banktxnid from varchar(7) to varchar(16) in SQL create table and head field name change in XML
5. add random suffix to transaction id to ensure uniqueness
1 /includes/modules/payments/includes/modules/payments/securepayxml.php and 
  /includes/modules/payments/includes/modules/payments/securepay_xml_api.php modified to only pass invoice enumber as txnid
2 new template file added /includes/templates/YOUR_TEMPLATE/templates/tpl_checkout_payment_default.php
3 icon changed to /images/icons/securepay_logo_rgb.png webp version is also included
4 icon size styled in added css file /includes/templates/YOUR_TEMPLATE/css/stylesheet_securepay_overide.css
5 SecurePay logos displayed on checkout page
// 2025-03-08 PHP8.3 & 8.4 declare all vars
// 2025-09-29 increase size of banktxnid from varchar(7) to varchar(16) in SQL create table
// 2025-09-30 add random suffix to transaction id to ensure uniqueness
// 2025-10-02 159a ln391 use of $oid and $api_order_id to identify diff 
// 2025-11-01 ln293 fix ?? [v1.5.9b]
// 2025-12-02 trim v1.5.9c
// 2025-12-10 1.5.9d redundant curl_close($ch) for PHP 8.0 to 8.5
// 2026-01-15 ln355, 357, 358, 359, 430 null coalesce ; transaction log enhancements; debug logging improvements
// 2026-01-16 1.6.0 ln419, 444 debug logging improvements;
// 2026-04-06 1.6.1 ln943 replace str() with date() in log function to fix error in PHP 8.3 and above
