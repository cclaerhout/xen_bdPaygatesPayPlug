<?php

class bdPaygatePayPlug_Listener
{
	protected static $_bdPaygatePayPlug_config_active;

	public static function load_class($class, array &$extend)
	{
		static $classes = array(
			'bdPaygate_Model_Processor',
			'XenForo_ControllerAdmin_UserUpgrade',
			'XenForo_Model_Option'
		);

		if(self::$_bdPaygatePayPlug_config_active == null)
		{
			//Only enable this Paygate once the needed data will have been setup
			self::$_bdPaygatePayPlug_config_active = bdPaygatePayPlug_Helper_Api::configIsReady();
		}

		if (in_array($class, $classes) && self::$_bdPaygatePayPlug_config_active)
		{
			$extend[] = 'bdPaygatePayPlug_' . $class;
		}
	}
	
	public static function file_health_check(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
	{
		$hashes += bdPaygatePayPlug_FileSums::getHashes();
	}

      	public static function option_config_generator(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
      	{
		$optionParsed = false;
		$payPlugConfig = null;
		$keys = array('private' => '', 'public' => '');

		if(bdPaygatePayPlug_Helper_Api::configIsReady())
		{
			$payPlugConfig = bdPaygatePayPlug_Helper_Api::getPayPlugConfig();

			$optionParsed = true;

			if(isset($payPlugConfig['payplugPublicKey']))
			{
				$keys['public'] = $payPlugConfig['payplugPublicKey'];
			}
			
			if(isset($payPlugConfig['privateKey']))
			{
				$keys['private'] = $payPlugConfig['privateKey'];
			}
		}

      		$editLink = $view->createTemplateObject('option_list_option_editlink', array(
      			'preparedOption' => $preparedOption,
      			'canEditOptionDefinition' => $canEdit
      		));

      		return $view->createTemplateObject('option_bdPaygatePayPlug_generator', array(
      			'fieldPrefix' => $fieldPrefix,
      			'listedFieldName' => $fieldPrefix . '_listed[]',
      			'preparedOption' => $preparedOption,
      			'formatParams' => $preparedOption['formatParams'],
      			'editLink' => $editLink,
      			'optionParsed' => $optionParsed,
      			'payPlugConfig' => $payPlugConfig,
      			'keys' => $keys
      		));
      	}

      	public static function option_config_validation(array &$data, XenForo_DataWriter $dw, $fieldName)
      	{
		/*Config was set but a reset request has been made*/
		if(!empty($data['raz']))
		{
			$data = array();
			bdPaygatePayPlug_Helper_Api::resetPayPlugConfig();
			return true;
		}

		if(empty($data))
		{
			/*Fresh install*/
			$data = array();
			return true;
		}

		if(!isset($data['login']))
		{
			/*Config is set*/
			$data = array('isSet' => true);
		}
		else
		{
			/*Config is not set*/
			if(!$data['login'] || !$data['password'])
			{
				//Let other options to be saved if needed
				$data = array();
				return true;
			}

			$isTest = (empty($data['isTest'])) ? false : true;
			list($validReturn, $message, $errorObj) = bdPaygatePayPlug_Helper_Api::initPayPlugConfig($data['login'], $data['password'], $isTest);
			if($validReturn)
			{
				$data = array('isSet' => true);
				return true;
			}
			else
			{
				if(!$message)
				{
					if($errorObj instanceof InvalidCredentialsException)
					{
						$message = 'Invalid login/password';
					}
					else
					{
						$message = 'Unkown error';
					}
				}
				
				$dw->error($message, 'bdPaygatePayPlug_config', true);
				return false;	
			}
		}

		return true;
      	}
}
//Zend_Debug::dump($abc);