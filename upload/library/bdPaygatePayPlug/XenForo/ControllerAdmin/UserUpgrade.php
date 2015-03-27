<?php

class bdPaygatePayPlug_XenForo_ControllerAdmin_UserUpgrade extends XFCP_bdPaygatePayPlug_XenForo_ControllerAdmin_UserUpgrade
{
	public function actionIndex()
	{
		$optionModel = $this->getModelFromCache('XenForo_Model_Option');
		$optionModel->bdPaygatePayPlug_hijackOptions();
		
		return parent::actionIndex();
	}
}