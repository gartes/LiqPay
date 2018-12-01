<?php defined ('_JEXEC') or die('Restricted access');




if (!class_exists ('vmPSPlugin')) {
	require(JPATH_VM_PLUGINS . DS . 'vmpsplugin.php');
}

class plgVmpaymentKaznachey extends vmPSPlugin {
	//  public $paymentKaznacheyUrl = "http://payment.kaznachey.net/api/PaymentInterface/";
	
	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// unique filelanguage for all KAZNACHEY methods
		$jlang = JFactory::getLanguage ();
		$jlang->load ('plg_vmpayment_kaznachey', JPATH_ADMINISTRATOR, NULL, TRUE);
		$this->_debug = TRUE;
		
		$this->_loggable = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id'; //virtuemart_KAZNACHEY_id';
		$this->_tableId = 'id'; //'virtuemart_KAZNACHEY_id';

		$varsToPush = array(
							'payment_sandbox'  => array(0,'int'),
							'status_sandbox'   => array('','char'),
							'merchant_id'      => array('','string'),
							'secret_key'       => array('','string'),
							'status_success'   => array('','char'),
							'status_pending'   => array('','char'),
							'status_wait_secure'=>array('','char'), 
							'status_canceled'  => array('','char'),
							'payment_currency' => array(0,'int'),
							'payment_language' => array('','char'),
							);

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
	}

	public function getVmPluginCreateTableSQL () {
		return $this->createTableSQL ('Payment KAZNACHEY Table');
	}

	function _getPaymentResponseHtml ($paymentTable, $payment_name) {
	//$this->rf('  _getPaymentResponseHtml ');
		
		
		
		VmConfig::loadJLang('com_virtuemart');

		$html = '<table>' . "\n";
		$html .= $this->getHtmlRow ('COM_VIRTUEMART_PAYMENT_NAME', $payment_name);
		if (!empty($paymentTable)) {
			$html .= $this->getHtmlRow ('KAZNACHEY_ORDER_NUMBER', $paymentTable->order_number);
		}
		$html .= '</table>' . "\n";

		return $html;
	}

	function _getInternalData ($virtuemart_order_id, $order_number = '') {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		if ($order_number) {
			$q .= " `order_number` = '" . $order_number . "'";
		} else {
			$q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
		}

		$db->setQuery ($q);
		if (!($paymentTable = $db->loadObject ())) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $paymentTable;
	}

	function _storeInternalData ($method, $mb_data, $virtuemart_order_id) {
		// get all know columns of the table
		$db = JFactory::getDBO ();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery ($query);
		$columns = $db->loadColumn (0);

		$post_msg = '';
		foreach ($mb_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'mb_' . $key;
			if (in_array ($table_key, $columns)) {
				$response_fields[$table_key] = $value;
			}
		}

		$response_fields['payment_name'] = $this->renderPluginName ($method);
		$response_fields['mbresponse_raw'] = $post_msg;
		$response_fields['order_number'] = $mb_data['transaction_id'];
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$this->storePSPluginInternalData ($response_fields, 'virtuemart_order_id', TRUE);
	}

	function _parse_response ($response) {
$this->rf('  _parse_response  ');
		
		
		
		$matches = array();
		$rlines = explode ("\r\n", $response);

		foreach ($rlines as $line) {
			if (preg_match ('/([^:]+): (.*)/im', $line, $matches)) {
				continue;
			}

			if (preg_match ('/([0-9a-f]{32})/im', $line, $matches)) {
				return $matches;
			}
		}

		return $matches;
	}

	function getTableSQLFields () {
		$SQLfields = array('id'                     => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		                   'virtuemart_order_id'    => 'int(1) UNSIGNED',
		                   'order_number'           => ' char(64)',
		                   'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
		                   'payment_name'            => 'varchar(5000)',
		                   'payment_order_total'     => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
		                   'payment_currency'        => 'char(3) ',
		                   'cost_per_transaction'    => 'decimal(10,2)',
		                   'cost_percent_total'      => 'decimal(10,2)',
		                   'tax_id'                  => 'smallint(1)',

		                   'user_session'            => 'varchar(255)',

			// status report data returned by KAZNACHEY to the merchant
							'mb_merchant_id'        => 'varchar(50)',
							'mb_secret_key'         => 'varchar(50)',
							'mb_status_pending'		=> 'varchar(1)',
							'mb_status_success'		=> 'varchar(1)',
							'mb_status_canceled'	=> 'varchar(1)',
							'mb_payment_currency'	=> 'varchar(50)',
							'mb_payment_language'	=> 'varchar(50)',
						   );

		return $SQLfields;
	}





	//  Срабатывает после того как заказ подтвержден.
	//  Выводит кнопку для перехода на оплату в систему LiqPay
	//  Ставит заказу статус ожидающего оплаты
	function plgVmConfirmedOrder($cart, $order)
    {
     	$this->rf(' kaznachey.php  function plgVmConfirmedOrder');
		
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
		$lang     = JFactory::getLanguage();
        $filename = 'com_virtuemart';
		$lang->load($filename, JPATH_ADMINISTRATOR);
        $vendorId = 0;
		$session        = JFactory::getSession();
		$return_context = $session->getId();
        
		
		$this->logInfo('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');
      			
		
		$html = "";
	  	if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
        if (!$method->payment_currency)
            $this->getPaymentCurrency($method);
	  	// END printing out HTML Form code (Payment Extra Info)
        $q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' . $method->payment_currency . '" ';
        $db =& JFactory::getDBO();
        $db->setQuery($q);
	  	// Валюта Платежа
		$currency = strtoupper($db->loadResult());
	   	//  Сумма Платежа
		$amount = ceil($order['details']['BT']->order_total*100)/100;
	   	// ID Платежа
		$order_id    = VirtueMartModelOrders::getOrderIdByOrderNumber($order['details']['BT']->order_number);
	   	//  Описание платежа
	   	$desc = $order['details']['BT']->order_number;
	
		
		// url Для получения Ответа LIQPAY
		$statusUrl = JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pelement=kaznachey&order_number=' . $order_id);
	   
	     
	   	//  URL куда перенаправить клиента после оплаты
	 	$returnUrl = JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=orders&layout=details&order_number=' . $order['details']['BT']->order_number . '&order_pass=' . $order['details']['BT']->order_pass);
		
        
		
		$fail_url = JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginUserPaymentCancel&on=' . $order['details']['BT']->order_number . '&pm=' . $order['details']['BT']->virtuemart_paymentmethod_id);

			   
        $this->_virtuemart_paymentmethod_id      = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['payment_name']                = $this->renderPluginName($method);
        $dbValues['order_number']                = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $this->_virtuemart_paymentmethod_id;
        $dbValues['payment_currency']            = $currency;
        $dbValues['payment_order_total']         = $amount;
        
        
		
		if (!class_exists ('LiqPay')) {
			require(JPATH_ROOT.DS.'plugins'.DS.'vmpayment'.DS.'kaznachey'.DS.'class'.DS .'LiqPay.php');
		}
		$public_key = $method->merchant_id;
		$private_key = $method->secret_key;
		$liqpay = new LiqPay($public_key, $private_key);
		
		$html = $liqpay->cnb_form(array(
		  'version'        => '3',
		  'pay_way'        =>'card',
		  'amount'         => $amount,
		  'currency'       => $currency,
		  'description'    => $desc,
		  'order_id'       => $order_id,
		  'server_url'     => $statusUrl,
		  'result_url'     => $returnUrl,
		  'sandbox'        => $method->payment_sandbox
		 ));

$html.'plgVmConfirmedOrder';

		
		//  $dbValues['mb_secret_key']         = base64_encode( sha1( $private_key . $html . $private_key ) );
		$this->storePSPluginInternalData($dbValues);
		
		/*$user_email = $order['details']['BT']->email;
		$phone_1 = $order['details']['BT']->phone_1;
		$virtuemart_user_id = $order['details']['BT']->virtuemart_user_id;
		
		$cc_type = JRequest::getVar('cc_type', '');
		$request["MerchantGuid"] = $method->merchant_id;
		$request['SelectedPaySystemId'] = isset($cc_type) ? $cc_type : $this->GetMerchnatInfo(false, true);
		$request['Currency'] = $currency;
		$request['Language'] = $this->payment_language;
		
		$sum=$qty=0;
		foreach ($order['items'] as $key=>$product)
		{
			$request['Products'][] = array(
				"ProductId" => $product->order_item_sku,
				"ProductName" => $product->order_item_name,
				"ProductPrice" => $product->product_final_price,
				"ProductItemsNum" => $product->product_quantity,
				"ImageUrl" => '',
			);
			$sum += $product->product_final_price * $product->product_quantity;
			$qty += $product->product_quantity;
		}
		
		if($sum != $amount){
		$sum += $order_info_total = (int) ($amount - $sum);
		$request['Products'][] = array(
			"ProductId" => '1',
			"ProductName" => 'Delivery',
			"ProductPrice" => $order_info_total,
			"ProductItemsNum" => 1,
			"ImageUrl" => '',
		);
		$qty++;
		}

	$BuyerCountry = $order['details']['BT']->virtuemart_country_id;
	$BuyerFirstname = $order['details']['BT']->first_name;
	$BuyerLastname = $order['details']['BT']->last_name;
	$BuyerStreet = $order['details']['BT']->address_1;
	$BuyerCity = $order['details']['BT']->city;
	
    $request['PaymentDetails'] = array(
       "MerchantInternalPaymentId"=>"$order_id",
       "MerchantInternalUserId"=>"$virtuemart_user_id",
       "EMail"=>"$user_email",
       "PhoneNumber"=>"$phone_1",
       "CustomMerchantInfo"=>"",
       "StatusUrl"=>"$statusUrl",
       "ReturnUrl"=>"$returnUrl",
       "BuyerCountry"=>"$BuyerCountry",
       "BuyerFirstname"=>"$BuyerFirstname",
       "BuyerPatronymic"=>"",
       "BuyerLastname"=>"$BuyerLastname",
       "BuyerStreet"=>"$BuyerStreet",
       "BuyerZone"=>"",
       "BuyerZip"=>"",
       "BuyerCity"=>"$BuyerCity",

       "DeliveryFirstname"=>"$BuyerFirstname",
       "DeliveryLastname"=>"$BuyerLastname",
       "DeliveryZip"=>"",
       "DeliveryCountry"=>"$BuyerCountry",
       "DeliveryPatronymic"=>"",
       "DeliveryStreet"=>"$BuyerStreet",
       "DeliveryCity"=>"$BuyerCity",
       "DeliveryZone"=>"",
    );
	
	$request["Signature"] = md5(strtoupper($request["MerchantGuid"]) .
		number_format($sum, 2, ".", "") . 
		$request["SelectedPaySystemId"] . 
		$request["PaymentDetails"]["EMail"] . 
		$request["PaymentDetails"]["PhoneNumber"] . 
		$request["PaymentDetails"]["MerchantInternalUserId"] . 
		$request["PaymentDetails"]["MerchantInternalPaymentId"] . 
		strtoupper($request["Language"]) . 
		strtoupper($request["Currency"]) . 
		strtoupper($method->secret_key));
		
		$response = $this->sendRequestKaznachey(json_encode($request), "CreatePaymentEx");
		$result = json_decode($response, true);
		
		if($result['ErrorCode'] != 0)
		{
  			JController::setRedirect($fail_url, 'Ошибка транзакции' );
			JController::redirect(); 
		}else{
			$html = base64_decode($result["ExternalForm"]);
		}*/
		
        return $this->processConfirmedOrderPaymentResponse(true, $cart, $order, $html, $this->renderPluginName($method, $order), $method->status_pending);
    }
	
//  ===========================================================================================================	
	

//-----------------
	function plgVmOnPaymentResponseReceived (&$html) {

	$this->rf(' kaznachey.php function plgVmOnPaymentResponseReceived  ');		
		
		
		
		if (!class_exists ('VirtueMartCart')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'cart.php');
		}
		if (!class_exists ('shopFunctionsF')) {
			require(VMPATH_SITE . DS . 'helpers' . DS . 'shopfunctionsf.php');
		}
		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		VmConfig::loadJLang('com_virtuemart_orders', TRUE);
		$mb_data = vRequest::getPost();


		// the payment itself should send the parameter needed.
		$virtuemart_paymentmethod_id = vRequest::getInt ('pm', 0);
		$order_number = vRequest::getString ('on', 0);
		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		VmConfig::loadJLang('com_virtuemart');
		$orderModel = VmModel::getModel('orders');
		$order = $orderModel->getOrder($virtuemart_order_id);

		//vmdebug ('KAZNACHEY plgVmOnPaymentResponseReceived', $mb_data);
		$payment_name = $this->renderPluginName ($method);
		$html = $this->_getPaymentResponseHtml ($paymentTable, $payment_name);
		$link=	JRoute::_("index.php?option=com_virtuemart&view=orders&layout=details&order_number=".$order['details']['BT']->order_number."&order_pass=".$order['details']['BT']->order_pass, false) ;

		$html .='<br />
		<a class="vm-button-correct" href="'.$link.'">'.vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER').'</a>';

		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();
		return TRUE;
	}

	function plgVmOnUserPaymentCancel () {
	
	$this -> rf(  'kaznachey.php  function plgVmOnUserPaymentCancel'  );		
		
		
		
		
		
		
		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}

		$order_number = vRequest::getString ('on', '');
		$virtuemart_paymentmethod_id = vRequest::getInt ('pm', '');
		if (empty($order_number) or
			empty($virtuemart_paymentmethod_id) or
			!$this->selectedThisByMethodId ($virtuemart_paymentmethod_id)
		) {
			return NULL;
		}

		if (!($virtuemart_order_id = VirtueMartModelOrders::getOrderIdByOrderNumber ($order_number))) {
			return NULL;
		}

		if (!($paymentTable = $this->getDataByOrderId ($virtuemart_order_id))) {
			return NULL;
		}

		VmInfo (vmText::_ ('VMPAYMENT_KAZNACHEY_PAYMENT_CANCELLED'));
		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		if (strcmp ($paymentTable->user_session, $return_context) === 0) {
			$this->handlePaymentUserCancel ($virtuemart_order_id);
		}

		return TRUE;
	}

	//  Получает ответ от сервера LiqPay C данными оплата да или нет
	//  определяет оплачен заказ или нет и меняет на соответствующий статус заказа
	public function plgVmOnPaymentNotification(){
		$this -> rf(    );
		$this -> rf(  ' kaznachey.php   public function plgVmOnPaymentNotification()'  );
		$this -> rf(  '-------------------------------------------------------------'  );
		//  foreach (   $_REQUEST as  $k => $v ){ $this -> rf(  $k .' => '. $v ); }
		
		
		
		if (JRequest::getVar('pelement') != 'kaznachey')
			return null;
		if (!class_exists('VirtueMartModelOrders'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		$ordermodel = new VirtueMartModelOrders();
		// id заказа
		$order_id = JRequest::getVar('order_number');
		//  Номер заказа
		$order    = $ordermodel->getOrder($order_id);
		$method   = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);
		
		if (!class_exists ('LiqPay'))
			require(JPATH_ROOT.DS.'plugins'.DS.'vmpayment'.DS.'kaznachey'.DS.'class'.DS .'LiqPay.php');
		$public_key = $method->merchant_id;
		$private_key = $method->secret_key;
		$sign = base64_encode( sha1($private_key.$_REQUEST['data'].$private_key , 1 ));
		$request = json_decode(base64_decode( $_REQUEST['data'] ), true);
		
		
		
		$this -> rf(    );
		$this -> rf(  '------------------------    $request   -------------------------------------'  );
		
		$this -> rf(  '   '  );
		foreach ( $request  as $k => $v){
			$this -> rf(  $k.' =>  '. $v  );
		}
		
		
		$this -> rf(  '-------------------------------------------------------------'  );
		$this -> rf(    );
		
		$error = false;
		if ($method){
			$idTRA = $request['transaction_id'];
			$order['virtuemart_order_id'] = $order_id;
			
			switch ( $request['status']) {
				case 'success':
					$order['order_status'] = $method->status_success;
					$order['comments']     = JTExt::sprintf('VMPAYMENT_KAZNACHEY_PAYMENT_CONFIRMED',' ID-Платежа-'.$idTRA, $order_id);
					
				break;	
				case 'sandbox':
					$order['order_status'] = $method->status_sandbox;
					$order['comments']     = JTExt::sprintf('VMPAYMENT_KAZNACHEY_PAYMENT_SANDBOX_COMMENTS',' ID-Платежа-'.$idTRA, $order_id);
				break;
				case 'wait_accept'://Деньги с клиента списаны, но магазин еще не прошел проверку
					$order['order_status'] = $method->status_success;
					$order['comments']     = JTExt::sprintf('VMPAYMENT_KAZNACHEY_WAIT_ACCEPT_COMMENTS',' ID-Платежа-'.$idTRA, $order_id);
				break;
				
				case 'wait_secure'://платеж на проверке
				case 'processing': //Платеж обрабатывается.
					$order['order_status'] = $method->status_pending;
					$order['comments']     = JTExt::sprintf('VMPAYMENT_KAZNACHEY_WAIT_SECURE_OR_PROCESSING_COMMENTS',' ID-Платежа-'.$idTRA, $order_id);
				break;
				
				case 'error':
				case 'failure': //неуспешный платеж
					$error = "status : failure";
					//  var_dump($error);
					$order['order_status'] = $method->status_canceled;
					$order['comments']     = JTExt::sprintf("VMPAYMENT_KAZNACHEY_PAYMENT_ERROR_FAILURE: $error", $order_id);
				break;	
				//default:
			}
			$order['customer_notified']=1; // Статус заказа виден для клиента
			if (!class_exists('VirtueMartModelOrders'))
				require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
			$modelOrder = new VirtueMartModelOrders();
			ob_start();
			$modelOrder->updateStatusForOneOrder($order_id, $order, true);
			ob_end_clean();
		}
     
        exit;
        return null;
    }
	
	
	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {



		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		$this->getPaymentCurrency ($method);
		$paymentCurrencyId = $method->payment_currency;
	}
	
	//  Отображение страницы заказа на стороне АДМИНА
	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $payment_method_id) {
	$this->rf(' plgVmOnShowOrderBEPayment  ');
		if (!$this->selectedThisByMethodId ($payment_method_id)) {
			return NULL;
		} // Another method was selected, do nothing

		if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$this->getPaymentCurrency ($paymentTable);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$paymentTable->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('PAYMENT_NAME', $paymentTable->payment_name);

		$code = "mb_";
		foreach ($paymentTable as $key => $value) {
			if (substr ($key, 0, strlen ($code)) == $code) {
				$html .= $this->getHtmlRowBE ($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	
	
	
	
	
	
	
	
	// Срабатывает при выводе бланка заказа на сторане КЛИЕНТА
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {
		
		 $this->rf(' kaznachey.php  function plgVmOnShowOrderFEPayment');	
		
		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
			//echo $payment_name ;
			
				
		if (!class_exists('VirtueMartModelPaymentmethod'))
			require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'paymentmethod.php');
		$vmModelPaymentMethod = new VirtueMartModelPaymentmethod();
		$method    = $vmModelPaymentMethod->getPayment($virtuemart_paymentmethod_id);
		if( $method->payment_element!='kaznachey' )
			return NULL;
			
		$pendingPaymentOrderStatus = $method->status_pending;
		$sandboxPaymentOrderStatus = $method->status_sandbox;
		$successPaymentOrderStatus = $method->status_success;
				
		if (!class_exists('VirtueMartModelCurrency'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'currency.php');
		$vmModelCurrency = new VirtueMartModelCurrency();
		$currency = $vmModelCurrency->getCurrency($method->payment_currency);
						
		if (!class_exists('VirtueMartModelOrders'))
            require(JPATH_VM_ADMINISTRATOR . DS . 'models' . DS . 'orders.php');
		$vmModelOrder = new VirtueMartModelOrders();
		
		$order    = $vmModelOrder->getOrder($virtuemart_order_id) ;
		
		$orderDetails= $order['details'];
		
		
		
		
		
		$ind = 0;
		
		foreach ( $orderDetails['history']  as  $k => $v ){
			if($v->order_status_code==$pendingPaymentOrderStatus&&$ind==0){$ind=1;}
			if($v->order_status_code==$successPaymentOrderStatus||$v->order_status_code == $sandboxPaymentOrderStatus){$ind=0;}
		}
		if($ind==1){
			$oD = $orderDetails['details']['BT'];
			// url Для получения Ответа LIQPAY
			$statusUrl = JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pelement=kaznachey&order_number='.$oD->virtuemart_order_id);
			//  URL куда перенаправить клиента после оплаты
	 		$returnUrl = JROUTE::_(JURI::root().'index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$oD->order_number.'&order_pass='.$oD->order_pass);	
			
			if (!class_exists ('LiqPay')) 
				require(JPATH_ROOT.DS.'plugins'.DS.'vmpayment'.DS.'kaznachey'.DS.'class'.DS .'LiqPay.php');
			$public_key = $method->merchant_id;
			$private_key = $method->secret_key;
			$liqpay = new LiqPay($public_key, $private_key);
			
			$html = $liqpay->cnb_form(array(
			  'version'        => '3',
			  'pay_way'        =>'card',
			  'amount'         => ceil($oD->order_total*100)/100,
			  'currency'       => $currency->currency_code_3,
			  'description'    => $oD->order_number,
			  'order_id'       => $oD->virtuemart_order_id ,
			  'server_url'     => $statusUrl,
			  'result_url'     => $returnUrl,
			  'sandbox'        => $method->payment_sandbox
			 ));
			 
			 
			 $uri = &JFactory::getURI();
			 $url = $uri->toString(array('path', 'query', 'fragment'));
			//echo '<pre>'; print_r ($url); echo '</pre>';
			
			
			$str_find = "/cart/iU";
			if (preg_match($str_find, $url))
			{
   		 		//echo "Найдена.";
			} else {
    			echo $html;
			}
			
			
			
			} // end if		
	}// end function plgVmOnShowOrderFEPayment
	


	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author kaznachey
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}


	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	

	
	
	
	
	
	//   Вывод выбора плагина оплаты
	protected function renderPluginName ($plugin) {
		//$this->rf(' function renderPluginName  ');
		
		$return = '';
		$plugin_name = $this->_psType . '_name';
		$plugin_desc = $this->_psType . '_desc';
		/*$return = $this->displayLogos ('kaznachey.png') . ' ';*/
		//  $pay_title = 'Кредитная карта Visa/MC, Webmoney, Liqpay, Qiwi...';
		$pay_title = 'Оплата Liqpay';
		$cc_types = $this->GetMerchnatInfo();
		if($cc_types){
			/*$select = '<br><select name="cc_type" id="cc_type">';
			foreach ($cc_types["PaySystems"] as $paysystem)
			{
				$select .= '<option value="'.$paysystem['Id'].'">'.$paysystem['PaySystemName'].'</option>';
			}
			$select .= '</select>';*/
			
			/*$cc_agreed = "<br><input type='checkbox' class='form-checkbox' name='cc_agreed' id='cc_agreed' checked><label for='edit-panes-payment-details-cc-agreed'><a href='$cc_types[TermToUse]' target='_blank'>Согласен с условиями использования</a></label>";*/
			
	 
			
		}
		$pluginName = $return . '<span class="' . $this->_type . '_name">' . $pay_title . '</span>' . $select . $cc_agreed . $html;
		return $pluginName;
	}
	// Отключен вывод логотипа
	protected function displayLogos($logo_list)
    {
        $img = "";
        
        if (!(empty($logo_list))) {
            $url = JURI::root() . str_replace('\\', '/', str_replace(JPATH_ROOT, '', dirname(__FILE__))) . '/';
            if (!is_array($logo_list))
                $logo_list = (array) $logo_list;
            foreach ($logo_list as $logo) {
                $alt_text = substr($logo, 0, strpos($logo, '.'));
                $img .= '<img align="middle" src="' . $url . $logo . '"  alt="' . $alt_text . '" /> ';
            }
        }
        //return $img;
    }

		
	




	//  Проверка на соотвтствие заказа для обработки этим плагином
	protected function checkConditions ($cart, $method, $cart_prices) {
		/*var_dump($method); die();*/
		
		
		
		$this->convert_condition_amount($method);
		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);
		$amount = $this->getCartAmount($cart_prices);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));

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
		if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}

		return FALSE;
	}
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}
	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}
	function GetMerchnatInfo($id = false, $first = false){
			
		if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
		$requestMerchantInfo = Array(
			"MerchantGuid"=>$method->merchant_id,
			"Signature" => md5(strtoupper($method->merchant_id) . strtoupper($method->secret_key))
		);
	
		//  $resMerchantInfo = json_decode($this->sendRequestKaznachey(json_encode($requestMerchantInfo), 'GetMerchatInformation'),true); 
		if($first){
			return $resMerchantInfo["PaySystems"][0]['Id'];
		}elseif($id)
		{
			foreach ($resMerchantInfo["PaySystems"] as $key=>$paysystem)
			{
				if($paysystem['Id'] == $id){
					return $paysystem;
				}
			}
		}else{
			return $resMerchantInfo;
		}
	}
	protected function sendRequestKaznachey($jsonData, $method){
	/*$this->rf(' sendRequestKaznachey  ');		
		$curl = curl_init();
		if (!$curl)
			return false;

		curl_setopt($curl, CURLOPT_URL, $this->paymentKaznacheyUrl . $method);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER,
			array("Expect: ", "Content-Type: application/json; charset=UTF-8", 'Content-Length: '
				. strlen($jsonData)));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonData);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, True);
		$response = curl_exec($curl);
		curl_close($curl);

		return $response;*/
	}
//  -------------------------------------------------------------------------------------------------------------	
function rf($text){
	$file = JPATH_ROOT.DS."file.txt";

// Открываем файл для получения существующего содержимого
$current = file_get_contents($file);
// Добавляем нового человека в файл
$current .= $text."\n";
// Пишем содержимое обратно в файл
file_put_contents($file, $current);
	
	}

}


