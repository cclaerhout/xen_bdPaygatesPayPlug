<?php

class bdPaygatePayPlug_bdPaygate_Model_Processor extends XFCP_bdPaygatePayPlug_bdPaygate_Model_Processor
{
	public function getProcessorNames()
	{
		$names = parent::getProcessorNames();
		$names['payplug'] = 'bdPaygatePayPlug_Processor_Common';
		
		return $names;
	}
}