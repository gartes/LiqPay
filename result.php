<?php



$_REQUEST['option']='com_virtuemart';
$_REQUEST['view']='pluginresponse';
$_REQUEST['task']='pluginnotification';
$_REQUEST['tmpl']='component';
$_REQUEST['format'] = 'raw';
$_REQUEST['pelement'] = 'kaznachey';
include('../../../index.php');










//http://mysite.com/index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=[paymentid]

/*$app = JFactory::getApplication();*/
/*$app->redirect(JRoute::_(JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pelement=kaznachey&order_number=' . $order_id));*/

/*$app->redirect(JRoute::_(JURI::root().'index.php?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=6'));*/