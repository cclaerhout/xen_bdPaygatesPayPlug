<?php

class bdPaygatePayPlug_XenForo_Model_Option extends XFCP_bdPaygatePayPlug_XenForo_Model_Option
{
	// this property must be static because XenForo_ControllerAdmin_UserUpgrade::actionIndex
	// for no apparent reason use XenForo_Model::create to create the optionModel
	// (instead of using XenForo_Controller::getModelFromCache)
	private static $_bdPaygatePayPlug_hijackOptions = false;
	
	public function getOptionsByIds(array $optionIds, array $fetchOptions = array())
	{
		if (self::$_bdPaygatePayPlug_hijackOptions === true)
		{
			$optionIds[] = 'bdPaygatePayPlug_config';
			//Do not display the option in the user upgrades configuration: once the config reset, the fields will not display
			$optionIds = array();	
		}
		
		$options = parent::getOptionsByIds($optionIds, $fetchOptions);
		
		self::$_bdPaygatePayPlug_hijackOptions = false;

		return $options;
	}
	
	public function bdPaygatePayPlug_hijackOptions()
	{
		self::$_bdPaygatePayPlug_hijackOptions = true;
	}
}