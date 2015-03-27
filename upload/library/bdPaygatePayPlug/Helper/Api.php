<?php

class bdPaygatePayPlug_Helper_Api
{
	const CACHE_KEY = "bdPaygatePayPlug";
	
	protected static $_sandboxUrl = 'https://www.payplug.fr/p/test/3tjg';

	public static function getPayPlugConfig()
	{
		$payPlugConfig = XenForo_Application::getSimpleCacheData(self::CACHE_KEY);
		return $payPlugConfig;
	}
	
	public static function configIsReady()
	{
		$cache = self::getPayPlugConfig();
		return (is_array($cache) && !empty($cache));
	}

	public static function resetPayPlugConfig()
	{
		XenForo_Application::setSimpleCacheData(self::CACHE_KEY, false);
	}

	public static function initPayPlugConfig($login, $password, $isTest = false)
	{
		self::loadLib();

		try {
			$payPLugConfig = Payplug::loadParameters($login, $password, $isTest);
			self::_setPayPlugConfig($payPLugConfig, $isTest);
			return array(true, null, null);
		} catch (Exception $e) {
			return array(false, $e->getMessage(), $e);
		}
	}

	protected static function _setPayPlugConfig(Parameters $payPLugConfig, $isTest = false)
	{
		$payPLugConfig->XenData = array(
			'timeStamp' => XenForo_Application::$time,
			'isTest' => $isTest
		);

		XenForo_Application::setSimpleCacheData(self::CACHE_KEY, (array) $payPLugConfig);
	}


	public static function checkCompatibility($silentMode = false)
	{
		$extensions = array(
			"curl" => "cURL",
			"openssl" => "OpenSSL"
		);
		
		$functions = array(
			"base64_decode",
			"base64_encode",
			"json_decode",
			"json_encode",
			"urlencode"
		);

		// Checks that all required extensions have been loaded
		$missingExtensions = array();
		foreach ($extensions as $name => $title) {
			if (!extension_loaded($name)) {
				$missingExtensions[] = $title;
			}
		}

		// Checks that all required functions exist
		$missingFunctions = array();
		foreach ($functions as $func) {
			if (!function_exists($func)) {
				$missingFunctions[] = $func;
			}
		}

		$errors = array();
		if(!empty($missingExtensions))
		{
			$missingExtensions = implode(', ', $missingExtensions);
			$errors[] = "This library needs the following extensions: $missingExtensions";
		}

		if(!empty($missingFunctions))
		{
			$missingFunctions = implode(', ', $missingFunctions);
			$errors[] = "This library needs the following functions: $missingFunctions";		
		}

		if(!empty($errors))
		{
			$errors = implode("\r\n", $errors);
			if($silentMode) {
				return $errors;
			} else {
				throw new XenForo_Exception($errors);
			}
		}
		
		return false;
	}


	/**
	 * @return int
	 */
	public static function getAmountInCent($amount, $currency = 'EUR')
	{
		if (function_exists('bcmul'))
		{
			return intval(bcmul(strval($amount), '100'));
		}
		else
		{
			return floor(doubleval($amount) * 100);
		}
	}

	/**
	 * @return double
	 */
	public static function getAmountFromCent($amount, $currency = 'EUR')
	{
		if (function_exists('bcdiv'))
		{
			return doubleval(bcdiv(strval($amount), '100', 2));
		}
		else
		{
			return intval($amount) / 100.0;
		}
	}

	public static function getPaymentUrl($amount, $currency = 'EUR', array $urls = array(), array $customerData = array(), array $customData = array(), $sandbox = false)
	{
		self::loadLibWithConfig($sandbox);
		$xenOptions = XenForo_Application::getOptions();
		
		$data = array(
		        'amount' => $amount, // in cents 4207 => 42,07€
			'currency' => $currency,
			'origin' => $xenOptions->boardUrl
		);

		if(!empty($urls))
		{
			if(!empty($urls['callbackUrl']))
			{
				$data['ipnUrl'] = $urls['callbackUrl'];
			}

			if(!empty($urls['returnUrl']))
			{
				$data['returnUrl'] = $urls['returnUrl'];
			}

			if(!empty($urls['cancelUrl']))
			{
				$data['cancelUrl'] = $urls['cancelUrl'];
			}
		}

		if(!empty($customerData))
		{
			//Note that if any of the fields email, firstName or lastName is left blank, the customer will be required to enter all three fields on the payment page.

			if(!empty($customerData['email']))
			{
				$data['email'] = $customerData['email'];
			}

			if(!empty($customerData['firstName']))
			{
				$data['firstName'] = $customerData['firstName'];
			}

			if(!empty($customerData['lastName']))
			{
				$data['lastName'] = $customerData['flastName'];
			}
			
			if(!empty($customerData['id']))
			{
				$data['customer'] = $customerData['id'];
			}	
		}

		if(!empty($customData))
		{
			$data['customData'] = json_encode($customData);
		}

		return PaymentUrl::generateUrl($data);
	}

	public static function loadLib()
	{
		require_once(dirname(dirname(__FILE__)) . '/3rdparty/lib/Payplug.php');
	}

	public static function loadLibWithConfig($sandbox = false)
	{
		require_once(dirname(dirname(__FILE__)) . '/3rdparty/lib/Payplug.php');
		
		$configObj = (object)self::getPayPlugConfig();

		if($sandbox && $configObj->paymentBaseUrl != self::$_sandboxUrl)
		{
			//To prevent any problems
			$configObj->paymentBaseUrl = self::$_sandboxUrl;
		}

		Payplug::setConfig($configObj);
	}	
}
