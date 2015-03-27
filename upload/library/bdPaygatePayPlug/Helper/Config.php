<?php

class bdPaygatePayPlug_Helper_Config
{
	protected $_config;
	
	public function __construct()
	{
		$this->_config = (object) bdPaygatePayPlug_Helper_Api::getPayPlugConfig();
	}

	protected function _getFromConfig($key)
	{
		if(isset($this->_config->$key))
		{
			return $this->_config->$key;
		}
		
		return null;
	}

	public function getPaymentBaseUrl()
	{
		return $this->_getFromConfig('paymentBaseUrl');
	}

	public function getCurrencies()
	{
		return $this->_getFromConfig('currencies');
	}
	
	public function getMaxAmount()
	{
		return $this->_getFromConfig('maxAmount');
	}

	public function getMinAmount()
	{
		return $this->_getFromConfig('minAmount');
	}
	
	public function getPublicKey($keepComments = true)
	{
		//Comments are needed for SSL PHP FUNCTIONS !
		$key = $this->_getFromConfig('payplugPublicKey');

		if(!$keepComments)
		{
			$search = array(
				'-----BEGIN PUBLIC KEY-----',
				'-----END PUBLIC KEY-----'
			);
			$key = str_replace($search, '', $key);
		}
		
		return trim($key);
	}

	public function getPrivateKey($keepComments = true)
	{
		//Comments are needed for SSL PHP FUNCTIONS !
		$key = $this->_getFromConfig('privateKey');

		if(!$keepComments)
		{
				
			$search = array(
				'-----BEGIN RSA PRIVATE KEY-----',
				'-----END RSA PRIVATE KEY-----'
			);
			$key = str_replace($search, '', $key);
		}
		
		return trim($key);
	}
	
	public function isSandboxMode()
	{
		$XenData = $this->_getFromConfig('XenData');
		return $XenData['isTest'];
	}
}
//Zend_Debug::dump($abc);