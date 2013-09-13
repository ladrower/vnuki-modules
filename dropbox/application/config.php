<?php namespace Vnuki\Dropbox;

	# DROPBOX MODULE CONFIGURATION FILE

	# Defining main constants
	define(__NAMESPACE__ . '\APP_KEY', 						'fake_key');
	define(__NAMESPACE__ . '\APP_SECRET', 						'fake_secret');
	define(__NAMESPACE__ . '\APP_ACCESS_TYPE', 					'app_folder');
	define(__NAMESPACE__ . '\APP_FOLDER', 						'Vnuki');
	define(__NAMESPACE__ . '\DS', 							DIRECTORY_SEPARATOR);
	define(__NAMESPACE__ . '\MODULE_APPLICATIONPATH',				dirname(__FILE__));
	define(__NAMESPACE__ . '\MODULE_ROOTPATH',					dirname(MODULE_APPLICATIONPATH));
	define(__NAMESPACE__ . '\MODULE_LIBPATH',					MODULE_ROOTPATH . DS . 'library');
	define(__NAMESPACE__ . '\MODULE_DATAPATH',					MODULE_ROOTPATH . DS . 'data');
	define(__NAMESPACE__ . '\MODULE_LOGPATH',					MODULE_ROOTPATH . DS . 'logs');
	define(__NAMESPACE__ . '\MODULE_WEBROOT',					((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/dropbox/');


	//error_reporting(-1);		
?>
