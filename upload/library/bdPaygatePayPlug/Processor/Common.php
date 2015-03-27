<?php
class bdPaygatePayPlug_Processor_Common extends bdPaygate_Processor_Abstract
{
	public function isAvailable()
	{
		return bdPaygatePayPlug_Helper_Api::configIsReady();
	}

	public function getSupportedCurrencies()
	{
		return array(
			bdPaygate_Processor_Abstract::CURRENCY_EUR,
		);
	}
	
	public function isRecurringSupported()
	{
		return false;
	}
	
	public function validateCallback(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId)
	{
		$amount = false;
		$currency = false;

		return $this->validateCallback2($request, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
	}

	public function validateCallback2(Zend_Controller_Request_Http $request, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		$signature = $request->getHeader('PAYPLUG-SIGNATURE');
		$body = $request->getRawBody();
		$ppConfig = new bdPaygatePayPlug_Helper_Config();
		
		//XenForo_Application::setSimpleCacheData('payplug_test', array($signature, $body));return;
		//XenForo_Application::setSimpleCacheData('payplug_test', false); return;
		//list($signature, $body) = XenForo_Application::getSimpleCacheData('payplug_test');

		/*Manage signature from header*/
		if(!$signature)
		{
			$this->_setError('IPN signature is missing');
			return false;
		}

		$signature = base64_decode($signature);
	        $publicKey = openssl_pkey_get_public($ppConfig->getPublicKey());
		$isSignatureValid = (openssl_verify($body , $signature, $publicKey, OPENSSL_ALGO_SHA1) === 1);

		if(!$isSignatureValid)
		{
			$this->_setError('IPN signature is not valid');
			return false;		
		}
		
		/*Manage data from body (json string)*/
		if(!$body)
		{
			$this->_setError('IPN body is empty - Check your board url in XenForo config - Check if you need to put or not "www" to match your .htaccess');
			return false;
		}
		
		$json = @json_decode($body, true);
		if (empty($json) OR empty($json['state']))
		{
			$this->_setError('Unable to parse JSON');
			return false;
		}

		/*Params map*/
		$pp_id_transaction = (int) $json['id_transaction'];
		$pp_amount = $json['amount'] = (int) $json['amount'];
		$pp_customerId = (int) $json['customer']; //string in default json / int in XenForo
		$pp_first_name = $json['first_name'];
		$pp_last_name = $json['last_name'];
		$pp_email = $json['email'];
		$pp_order = $json['order'];
		$pp_is_test = (bool) $json['is_test'];
		$pp_custom_data = $json['custom_data'] = json_decode($json['custom_data'], true);
		$pp_origin = $json['origin'];
		$pp_state = $json['state']; //'paid' or 'refunded'
		$pp_status = $json['status']; //undocumented, probably not ready yet

		/*Transaction Manager*/
		$transactionId = $pp_id_transaction;
		$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_OTHER;
		$transactionDetails = $json;
		
		if(!isset($pp_custom_data['itemId']))
		{
			$this->_setError('Item id is missing in response');
			return false; 
		}
		
		$itemId = $pp_custom_data['itemId'];
		$amount = bdPaygatePayPlug_Helper_Api::getAmountFromCent($pp_amount);
		$currency = (isset($pp_custom_data['currency']) && in_array(strtoupper($pp_custom_data['currency']), $this->getSupportedCurrencies())) ? strtoupper($pp_custom_data['currency']) : 'EUR';

		$return = true;

		switch ($pp_state)
		{
			case 'refunded':
				$return = $this->_validateChargeRefunded($json, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
				BREAK;
			case 'paid':
				$return = $this->_validateInvoicePaymentSucceeded($json, $transactionId, $paymentStatus, $transactionDetails, $itemId, $amount, $currency);
				BREAK;
			default:
				//Not managed currently - state can only be 'refunded' or 'paid'
				$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_REJECTED;
		}

		return $return;
	}

	protected function _validateChargeRefunded(array $json, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		$transactionId = 'payplug_refunded_' . $transactionId;
		$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_REJECTED;
		$transactionDetails[bdPaygate_Processor_Abstract::TRANSACTION_DETAILS_PARENT_TID] = 'payplug_' . $transactionId;

		return true;
	}

	protected function _validateInvoicePaymentSucceeded(array $json, &$transactionId, &$paymentStatus, &$transactionDetails, &$itemId, &$amount, &$currency)
	{
		$processorModel = $this->getModelFromCache('bdPaygate_Model_Processor');

		$log = $processorModel->getLogByTransactionId($transactionId);
		if (!empty($log))
		{
			$this->_setError("Transaction {$transactionId} has already been processed");
			return false;
		}

		$transactionId = 'payplug_' . $transactionId;
		$paymentStatus = bdPaygate_Processor_Abstract::PAYMENT_STATUS_ACCEPTED;
		
		return true;
	}
	
	public function generateFormData($amount, $currency, $itemName, $itemId, $recurringInterval = false, $recurringUnit = false, array $extraData = array())
	{
		$this->_assertAmount($amount);
		$this->_assertCurrency($currency);
		$this->_assertItem($itemName, $itemId);
		$this->_assertRecurring($recurringInterval, $recurringUnit);

		$xenOptions = XenForo_Application::getOptions();
		$callToAction = new XenForo_Phrase('bdpaygatePayPlug_call_to_action');

		$urlData = array(
			'returnUrl' => $this->_generateReturnUrl($extraData),
			'callbackUrl' => $this->_generateCallbackUrl($extraData)
		);

		$visitor = XenForo_Visitor::getInstance();
		$customerData = array();

		if($visitor->user_id)
		{
			$customerData['id'] = $visitor->user_id;
			$customerData['email'] = $visitor->email;
		}

		$customData = array(
			'itemId' => $itemId,
			'currency' => $currency
		);

		$paymentUrl = bdPaygatePayPlug_Helper_Api::getPaymentUrl(
			bdPaygatePayPlug_Helper_Api::getAmountInCent($amount), 
			$currency, 
			$urlData,
			$customerData,
			$customData,
			$this->_sandboxMode()
		);
		
		$form = <<<EOF
<form action="{$paymentUrl}" method="POST">
	<input type="submit" value="{$callToAction}" class="button" />
</form>
EOF;

		return $form;
	}
}
//Zend_Debug::dump($abc);