<?php
/**
 * @package linkpoint_api_payment_module
 * @copyright Copyright 2003-2026 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @copyright Portions Copyright 2003 Jason LeBaron 
 * @copyright Portions Copyright 2004 DevosC.com 
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: linkpoint_api_admin_notification.php 7314 2007-10-29 22:58:19Z drbyte $
 */ //
//  @maintainer OldNGrey (BMH) since 2017
//  BMH  2026-01-18 add version number in comment to match securepayxml.php; 
// Version 1.6.0

$outputStartBlock = '';
$outputMain = '';
$outputAuth = '';
$outputCapt = '';
$outputVoid = '';
$outputRefund = '';
$outputEndBlock = '';
$output = '';

// strip slashes in case they were added to handle apostrophes:
if (!is_array($txn->fields)) $txn->fields = array();
foreach ($txn->fields as $key=>$value)
{
	$txn->fields[$key] = stripslashes($value);
}

$outputStartBlock .= '<td><table class="noprint">'."\n";
$outputStartBlock .= '<tr>'."\n";
$outputEndBlock .= '</tr>'."\n";
$outputEndBlock .='</table></td>'."\n";

if (method_exists($this, '_doRefund'))
{
	$outputRefund .='<td><table class="noprint" style="background-color: #eee; border: 1px solid #bbb;">'."\n";
	$outputRefund .= '<tr>'."\n";
	$outputRefund .= 	'<td><b>' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_TITLE . '</b></td>'. "\n";
	$outputRefund .= '</tr>';
	$outputRefund .= '<tr><td>'."($".$txn->fields['paid']." remaining)".'</td></tr>';
	$outputRefund .= zen_draw_form('refund', FILENAME_ORDERS, 
		zen_get_all_get_params(array('action')) . 'action=doRefund', 'post', '', true) . zen_hide_session_id();
	$outputRefund .= '<tr>';
	$outputRefund .= 	'<td>' . "Amount:&nbsp;&nbsp;" . ' ' . zen_draw_input_field('refamt', '0', 'length="8" style="width: 6em;"') . '</td>';
	$outputRefund .= 	'<td></td><td align="right"><input type="submit" name="buttonrefund" value="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_BUTTON_TEXT . '" title="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_REFUND_BUTTON_TEXT . '" />' . '</td>';
	$outputRefund .= '</tr>';
	$outputRefund .= '</form>';
	$outputRefund .='</table></td>'."\n";
}

if (method_exists($this, '_doCapt'))
{
	$outputCapt .='<td><table class="noprint" style="background-color: #eee; border: 1px solid #bbb;">'."\n";
	$outputCapt .= '<tr>'."\n";
	$outputCapt .= 	'<td><b>' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_TITLE . '</b></td>'. "\n";
	$outputCapt .= '</tr>';
	$outputCapt .= '<tr><td>&nbsp;</td></tr>';
	$outputCapt .= zen_draw_form('advice', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doCapture', 'post', '', true) . zen_hide_session_id();
	$outputCapt .= '<tr>'."\n";
	$outputCapt .= 	'<td>' . "Amount:"  . '&nbsp;&nbsp;' . zen_draw_input_field('captamt', $txn->fields["total"], 'length="8"') . '</td>';
	$outputCapt .= 	'<td><input type="submit" name="btndocapture" value="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_BUTTON_TEXT . '" title="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_PREAUTH_BUTTON_TEXT . '" />' . '</td>';
	$outputCapt .= '</tr>';
	$outputCapt .= '</form>';
	$outputCapt .='</table></td>'."\n";
}

if (method_exists($this, '_doVoid'))
{
	$outputVoid .='<td><table class="noprint" style="background-color: #eee; border: 1px solid #bbb;">'."\n";
	$outputVoid .= '<tr>'."\n";
	$outputVoid .= '<td><b>' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_VOID_TITLE . '</b></td>'. "\n";
	$outputVoid .= '</tr>';
	$outputVoid .= '<tr><td>&nbsp;</td></tr>';
	$outputVoid .= zen_draw_form('void', FILENAME_ORDERS, zen_get_all_get_params(array('action')) . 'action=doVoid', 'post', '', true) . zen_hide_session_id();
	$outputVoid .= '<tr>'."\n";
	$outputVoid .= 	'<td>' . "Confirm:&nbsp;&nbsp;" . zen_draw_checkbox_field('voidconfirm', '', false);
	$outputVoid .= 	'<td><input type="submit" name="ordervoid" value="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_VOID_BUTTON_TEXT . '" title="' . MODULE_PAYMENT_SECUREPAYXML_ENTRY_VOID_BUTTON_TEXT . '" />' . '</td>';
	$outputVoid .= '</tr>';
	$outputVoid .= '</form>';
	$outputVoid .='</table></td>'."\n";
}

// prepare output based on suitable content components
$output .= $outputStartBlock;
$output .= $outputStartBlock;
$output .= $outputMain;
$output .= $outputEndBlock;

if (method_exists($this, '_doRefund') && $txn->fields['paid']>0)
{
	$output .= $outputStartBlock;
	$output .= $outputRefund;
	$output .= $outputEndBlock;
}

if (method_exists($this, '_doCapt') )
{
	if (method_exists($this, '_doCapt') && $txn->fields['txntype'] == SECUREPAY_TXN_PREAUTH && $txn->fields['paid']==0)
	{
		$output .= $outputStartBlock;
		$output .= $outputCapt;
		$output .= $outputEndBlock;
	}

	if (method_exists($this, '_doVoid') && $txn->fields['paid']==$txn->fields['total'])
	{
		$output .= $outputStartBlock;
		$output .= $outputVoid;
		$output .= $outputEndBlock;
	}
}

$output .= $outputEndBlock;
