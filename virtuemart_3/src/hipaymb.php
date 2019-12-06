<?php

defined ('_JEXEC') or die('Restricted access');

/**
 * payments using Hipay Comprafacil:
 * @author Diogo Ferreira
 * @version $Id: hipaymb.php 1111 2017-02-22 22:52:22Z $
 * @package VirtueMart
 * @subpackage payment
 * @copyright Copyright (c) 2017 Hi-Pay Portugal. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * VirtueMart is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
 *
 * http://virtuemart.net
 */
if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmPaymentHipaymb extends vmPSPlugin {

	var $mb_entity;
	var $mb_reference;
	var $mb_value;
	var $mb_val;

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// 		vmdebug('Plugin stuff',$subject, $config);
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id';
		$this->_tableId = 'id';
		$varsToPush = $this->getVarsToPush ();
		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);

	}


	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment Hipay Comprafacil Table');
	}

	/**
	 * Fields to create the payment table
	 *
	 * @return string SQL Fileds
	 */
	function getTableSQLFields () {

		$SQLfields = array(
			'id'                          => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
			'virtuemart_order_id'         => 'int(1) UNSIGNED',
			'order_number'                => 'char(64)',
			'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
			'payment_name'                => 'varchar(5000)',
			'payment_order_total'         => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
			'payment_currency'            => 'char(3)',
			'email_currency'              => 'char(3)',
			'cost_per_transaction'        => 'decimal(10,2)',
			'cost_percent_total'          => 'decimal(10,2)',
			'tax_id'                      => 'smallint(1)',
			'challenge'					  => 'varchar(125)',
			'status'					  => 'smallint(1)',
			'cancel_key'				  => 'varchar(10)',
			'reference'					  => 'varchar(21)',
			'entity'					  => 'varchar(10)',
			'days'					  	  => 'smallint(2)',
			'sandbox'					  => 'smallint(1)'
		);

		return $SQLfields;
	}

	/**
	 *
	 */
	function plgVmConfirmedOrder ($cart, $order) {


		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL; 
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		VmConfig::loadJLang('com_virtuemart',true);
		VmConfig::loadJLang('com_virtuemart_orders', TRUE);

		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		$this->_debug = $method->debug;
		$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists ('VirtueMartModelOrders')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		}

		if (!class_exists ('TableVendors')) {
			require(JPATH_VM_ADMINISTRATOR . DS . 'table' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel ('Vendor');
		$vendorModel->setId (1);
		$vendor = $vendorModel->getVendor ();
		$vendorModel->addImages ($vendor, 1);
		$this->getPaymentCurrency ($method);
		$email_currency = $this->getEmailCurrency ($method);
		$currency_code_3 = shopFunctions::getCurrencyByID ($method->payment_currency, 'currency_code_3');

		$paymentCurrency = CurrencyDisplay::getInstance ($method->payment_currency);
		$totalInPaymentCurrency = round ($paymentCurrency->convertCurrencyTo ($method->payment_currency, $order['details']['BT']->order_total, FALSE), 2);
		$cd = CurrencyDisplay::getInstance ($cart->pricesCurrency);

		$quantity = 0;
		foreach ($cart->products as $key => $product) {
			$quantity = $quantity + $product->quantity;
		}

		$dbValues['order_number'] = $order['details']['BT']->order_number;
		$dbValues['payment_name'] = $this->renderPluginName ($method, $order);
		$dbValues['virtuemart_paymentmethod_id'] = $cart->virtuemart_paymentmethod_id;
		$dbValues['paypal_custom'] = $return_context;
		$dbValues['cost_per_transaction'] = $method->cost_per_transaction;
		$dbValues['cost_percent_total'] = $method->cost_percent_total;
		$dbValues['payment_currency'] = $method->payment_currency;
		$dbValues['email_currency'] = $email_currency;
		$dbValues['payment_order_total'] = $totalInPaymentCurrency;
		$dbValues['tax_id'] = $method->tax_id;

		$key = uniqid();
		$key = substr($key,10);
		$dbValues['cancel_key'] = $key;

		//$this->storePSPluginInternalData ($dbValues);

		$account = $this->getPaymentAccount($method);		

		$payment_info='';
		if (!empty($method->payment_info)) {
			$lang = JFactory::getLanguage ();
			if ($lang->hasKey ($method->payment_info)) {
				$payment_info = vmText::_ ($method->payment_info);
			} else {
				$payment_info = $method->payment_info;
			}
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}
		$currency = CurrencyDisplay::getInstance ('', $order['details']['BT']->virtuemart_vendor_id);
		$hipay_base_url = JFactory::getDocument()->base;


		$webservice_url = "https://hm.comprafacil.pt/SIBSClick";
		if ($account["account_entity"] == "11249") $webservice_url .= "2";
		if ($account["account_sandbox"] == "1") $webservice_url .= "Teste";
		$webservice_url .= "/webservice/CompraFacilWS.asmx?WSDL";

		$sendEmailFromHCF = false;
		if ($account["account_sendemail"] == "1") $sendEmailFromHCF = true;

		$username = $account["account_username"];
		$password = $account["account_password"];
		$amount = number_format($dbValues['payment_order_total'],2);
		$additionalInfo = "";
		$name = "";
		$address = "";
		$postCode = "";
		$city = "";
		$NIC = "";
		$externalReference = $dbValues["order_number"];
		$contactPhone = "";
		$email = $order['details']['BT']->email;
		$IDUserBackoffice = -1; //-1 in most cases
		$timeLimitDays = $account["account_validity"]; //3, 30 or 90 days; only used for entity 11249 and ignored for entity 10241 (must be 0)
		$sendEmailBuyer = $sendEmailFromHCF;

		//variables do store the results to show to the user
		$reference="";
		$entity="";
		$value="";
		$error="";


	    $origin =$hipay_base_url.'?option=com_virtuemart&view=pluginresponse&task=pluginnotification&tmpl=hipaymb&id=' . $dbValues['order_number'];


		try 
		{
			
			$amount = str_replace(",", "", $amount);
			$parameters = array(
				"origin" => $origin,
				"username" => $username,
				"password" => $password,
				"amount" => $totalInPaymentCurrency,
				"additionalInfo" => $additionalInfo,
				"name" => $name,
				"address" => $address,
				"postCode" => $postCode,
				"city" => $city,
				"NIC" => $NIC,
				"externalReference" => $externalReference,
				"contactPhone" => $contactPhone,
				"email" => $email,
				"IDUserBackoffice" => $IDUserBackoffice,
				"timeLimitDays" => $timeLimitDays,
				"sendEmailBuyer" => $sendEmailBuyer		
			);
			
			$client = new SoapClient($webservice_url);
					
			$res = $client->getReferenceMB($parameters); 


			if ($res->getReferenceMBResult)
			{
				$entity = $res->entity;
				$value = number_format($res->amountOut, 2);
				$reference = $res->reference;

				$this->mb_entity = $entity;
				$this->mb_reference = $reference;
				$this->mb_value = $value;
				$this->mb_val = $timeLimitDays;


				$dbValues['reference'] = $reference;
				$dbValues['entity'] = $entity;
				$dbValues['days'] = $timeLimitDays;
				$dbValues['sandbox'] = $account["account_sandbox"];

				$this->storePSPluginInternalData ($dbValues);

				$error = "";
				
				$html = '<table cellpadding="6" cellspacing="2" style="width: 350px; height: 55px; margin: 10px 0 2px 0;border: 1px solid #ddd"><tr>
						<td style="background-color: #ccc;color:#313131;text-align:center;padding:1px 3px;" colspan="3">'.JText::_( 'VMPAYMENT_HIPAYMB_PAYMENT_DESCRIPTION' ) .'</td>
					</tr>
					<tr>
						<td rowspan="3" style="width:110px;padding: 0px 5px 0px 5px;vertical-align: middle;"><img src="'.JURI::root(true).'/images/stories/virtuemart/payment/multibanco_vertical.jpg" style="margin-bottom: 0px; margin-right: 0px;width:60px;"></td>
						<td style="width:100px;padding:1px 3px;" align="right">'.JText::_( 'VMPAYMENT_HIPAYMB_ENTITY' ).'   </td>
						<td style="font-weight:bold;padding:1px 3px;">'.$entity.'</td>
					</tr>
					<tr>
						<td align="right" style="padding:1px 3px;">'.JText::_( 'VMPAYMENT_HIPAYMB_REFERENCE' ).'   </td>
						<td style="font-weight:bold;padding:1px 3px;">'.$reference.'</td>
					</tr>
					<tr>
						<td align="right" style="padding:1px 3px;">'.JText::_( 'VMPAYMENT_HIPAYMB_AMOUNT' ).'   </td>
						<td style="font-weight:bold;padding:1px 3px;">'.$amount.' &euro;</td>
					</tr>'. "\n";
				$html .= '</table>' . "\n";

				$cart->_confirmDone = TRUE;
				$cart->_dataValidated = FALSE;
				$cart->setCartIntoSession ();

				$modelOrder = VmModel::getModel ('orders');
				$order['order_status'] = $this->getNewStatus ($method);
				$order['customer_notified'] = 1;
				$order['comments'] = '';
				$modelOrder->updateStatusForOneOrder ($order['details']['BT']->virtuemart_order_id, $order, TRUE);



				$cart->emptyCart ();
				JRequest::setVar ('html', $html);


				return true;
			}
			else
			{
				$error = $res->error;
				return false;
			}
		
		}
		catch (Exception $e){
			$error = $e->getMessage();
			return false;
		}


		return TRUE;
	}



	function getPaymentAccount($method){
		$payment_params = explode("|", $method->payment_params);
		foreach ($payment_params as $key => $value) {
			$value_temp = explode("=", $value);
			if ($value_temp[0]!="") $account[$value_temp[0]] = str_replace('"','',$value_temp[1]);
			// AAA $account[$value_temp[0]] = str_replace('"','',$value_temp[1]);
		}
		return $account;
	}


	/*
		 * Keep backwards compatibility
		 * a new parameter has been added in the xml file
		 */
	function getNewStatus ($method) {

		if (isset($method->status_pending) and $method->status_pending!="") {
			return $method->status_pending;
		} else {
			return 'P';
		}
	}

	/**
	 * Display stored payment data for an order
	 *
	 */
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $virtuemart_payment_id) {

		if (!$this->selectedThisByMethodId ($virtuemart_payment_id)) {
			return NULL; // Another method was selected, do nothing
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}
		VmConfig::loadJLang('com_virtuemart');

		$db = JFactory::getDBO();
		$q = "SELECT reference,entity,days,sandbox FROM " . $db->getPrefix() . "virtuemart_payment_plg_hipaymb WHERE virtuemart_order_id = ".$virtuemart_order_id." LIMIT 1";
		$db->setQuery($q);
		$payment_details = $db->loadObjectList();
		
		$html = '<table class="adminlist">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('HIPAYMB_TITLE_DESC', $paymentTable->payment_name);
		$html .= $this->getHtmlRowBE ('HIPAYMB_AMOUNT', number_format($paymentTable->payment_order_total,2));
		$html .= $this->getHtmlRowBE ('HIPAYMB_ENTITY', $payment_details[0]->entity);
		$html .= $this->getHtmlRowBE ('HIPAYMB_REFERENCE', $payment_details[0]->reference);
		$html .= $this->getHtmlRowBE ('HIPAYMB_VALIDITY_UNIT', $payment_details[0]->days);
		if ($payment_details[0]->sandbox == 1) $html .= $this->getHtmlRowBE ('HIPAYMB_SANDBOX', '');
		$html .= '</table>' . "\n";
		return $html;
	}

	/*	function getCosts (VirtueMartCart $cart, $method, $cart_prices) {

			if (preg_match ('/%$/', $method->cost_percent_total)) {
				$cost_percent_total = substr ($method->cost_percent_total, 0, -1);
			} else {
				$cost_percent_total = $method->cost_percent_total;
			}
			return ($method->cost_per_transaction + ($cart_prices['salesPrice'] * $cost_percent_total * 0.01));
		}
	*/
	/**
	 * Check if the payment conditions are fulfilled for this payment method
	 * @param $cart_prices: cart prices
	 * @param $payment
	 * @return true: if the conditions are fulfilled, false otherwise
	 *
	 */
	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert_condition_amount($method);
		$amount = $this->getCartAmount($cart_prices);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);


		//vmdebug('standard checkConditions',  $amount, $cart_prices['salesPrice'],  $cart_prices['salesPriceCoupon']);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));
		if (!$amount_cond) {
			return FALSE;
		}
		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (count ($countries) == 0 || in_array ($address['virtuemart_country_id'], $countries) ) {
			return TRUE;
		}

		return FALSE;
	}


	/*
* We must reimplement this triggers for joomla 1.7
*/

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.

	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not vlaid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart, &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for example
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on succes, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.

	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}

	/*
* plgVmonSelectedCalculatePricePayment
* Calculate the price (value, tax_id) of the selected method
* It is called by the calculator
* This function does NOT to be reimplemented. If not reimplemented, then the default values from this function are taken.
* @author Valerie Isaksen
* @cart: VirtueMartCart the current cart
* @cart_prices: array the new cart prices
* @return null if the method was not selected, false if the shiiping rate is not valid any more, true otherwise
*
*
*/

	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {
		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}
		$this->getPaymentCurrency ($method);

		$paymentCurrencyId = $method->payment_currency;
		return;
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {


		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise

	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}
	/**
	 * @param $orderDetails
	 * @param $data
	 * @return null
	 */

	function plgVmOnUserInvoice ($orderDetails, &$data) {
		

		if (!($method = $this->getVmPluginMethod ($orderDetails['virtuemart_paymentmethod_id']))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}
		//vmdebug('plgVmOnUserInvoice',$orderDetails, $method);

		if (!isset($method->send_invoice_on_order_null) or $method->send_invoice_on_order_null==1 	or $orderDetails['order_total'] > 0.00){
			return NULL;
		}

		if ($orderDetails['order_salesPrice']==0.00) {
			$data['invoice_number'] = 'reservedByPayment_' . $orderDetails['order_number']; // Nerver send the invoice via email
		}

	}


	function plgVmgetEmailCurrency($virtuemart_paymentmethod_id, $virtuemart_order_id, &$emailCurrencyId) {


		if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
			return NULL; // Another method was selected, do nothing
		}
		if (!$this->selectedThisElement($method->payment_element)) {
			return FALSE;
		}
		if (!($payments = $this->getDatasByOrderId($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		if (empty($payments[0]->email_currency)) {
			$vendorId = 1; //VirtueMartModelVendor::getLoggedVendor();
			$db = JFactory::getDBO();
			$q = 'SELECT   `vendor_currency` FROM `#__virtuemart_vendors` WHERE `virtuemart_vendor_id`=' . $vendorId;
			$db->setQuery($q);
			$emailCurrencyId = $db->loadResult();
		} else {
			$emailCurrencyId = $payments[0]->email_currency;
		}

	}
	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.

	public function plgVmOnCheckoutCheckDataPayment(  VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {
		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	function plgVmDeclarePluginParamsPaymentVM3( &$data) { 
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {
		return $this->setOnTablePluginParams ($name, $id, $table);
	}

	//Notice: We only need to add the events, which should work for the specific plugin, when an event is doing nothing, it should not be added

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}

	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array   $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.
	 *
	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}

	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnEditOrderLineBEPayment(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise
	 *
	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}

	/**
	 * This event is fired when the  method notifies you when an event occurs that affects the order.
	 * Typically,  the events  represents for payment authorizations, Fraud Management Filter actions and other actions,
	 * such as refunds, disputes, and chargebacks.
	 *
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param         $return_context: it was given and sent in the payment form. The notification should return it back.
	 * Used to know which cart should be emptied, in case it is still in the session.
	 * @param int     $virtuemart_order_id : payment  order id
	 * @param char    $new_status : new_status for this order id.
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	 *
	public function plgVmOnPaymentNotification() {
	return null;
	}

	/**
	 * plgVmOnPaymentResponseReceived
	 * This event is fired when the  method returns to the shop after the transaction
	 *
	 *  the method itself should send in the URL the parameters needed
	 * NOTE for Plugin developers:
	 *  If the plugin is NOT actually executed (not the selected payment method), this method must return NULL
	 *
	 * @param int     $virtuemart_order_id : should return the virtuemart_order_id
	 * @param text    $html: the html to display
	 * @return mixed Null when this method was not selected, otherwise the true or false
	 *
	function plgVmOnPaymentResponseReceived(, &$virtuemart_order_id, &$html) {
	return null;
	}
	 */


	public function plgVmOnPaymentNotification() {

		if (isset($_GET["id"])) {
			$idformerchant = $_GET["id"];	
			$ispaid= false;
			$reference = $_GET["ref"];
			$reference = str_replace("+", " ", $reference);
			
			
				//validate challenge and get virtuemart id
				$db = JFactory::getDBO();
				$q = "SELECT virtuemart_order_id,reference,virtuemart_paymentmethod_id FROM " . $db->getPrefix() . "virtuemart_payment_plg_hipaymb WHERE order_number = '".$idformerchant."' LIMIT 1";
				$db->setQuery($q);
				$payment = $db->loadObjectList();
				if (!$payment) return false;

				if ($reference != $payment[0]->reference) return false;

				if (!($method = $this->getVmPluginMethod ($payment[0]->virtuemart_paymentmethod_id))) {
					return NULL; // Another method was selected, do nothing
				}
				$account = $this->getPaymentAccount($method);


				$webservice_url = "https://hm.comprafacil.pt/SIBSClick";
				if ($account["account_entity"] == "11249") $webservice_url .= "2";
				if ($account["account_sandbox"] == "1") $webservice_url .= "Teste";
				$webservice_url .= "/webservice/CompraFacilWS.asmx?WSDL";


				$username = $account["account_username"];
				$password = $account["account_password"];

				try 
				{
					
					$parameters = array(
						"reference" => $reference,
						"username" => $username,
						"password" => $password
					);
					
					$client = new SoapClient($webservice_url);
							
					$res = $client->getInfoReference($parameters); 

					if ($res->getInfoReferenceResult)
					{
						$ispaid = $res->paid;
					}
					else
					{
						return false;
					}
				}	
				 catch (Exception $e) {
					return false;	
				}


				if ($ispaid === true) {	
		
					VmConfig::loadJLang('com_virtuemart',true);
					VmConfig::loadJLang('com_virtuemart_orders', TRUE);

					if (!class_exists ('VirtueMartModelOrders')) {
						require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
					}

					$modelOrder = VmModel::getModel ('orders');
					$order['customer_notified'] = 1;
				
					$order['order_status'] = 'C';
					$order['comments'] = '';				
					$modelOrder->updateStatusForOneOrder ($payment[0]->virtuemart_order_id, $order, TRUE);
				}


			return false;
		}	

		header("location: " . JFactory::getDocument()->base);




	}


}

// No closing tag



