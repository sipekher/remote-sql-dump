<?php 
require_once('dumper2.php');
if (PHP_VERSION_ID >= 70400) {
   error_reporting(E_ALL & ~E_DEPRECATED);
 } else {
   error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
 }

//wget -O - -q -t 1 --timeout=6000 https://contours.cz/db_backup_shuttle-export/dump.php
//jine reseni https://github.com/ifsnop/mysqldump-php


//header("Content-type: text/plain");

if(getenv('COMPUTERNAME')=='VIC-SIPEK' || getenv('COMPUTERNAME')=='VIC-50E4EE77B23'){
	if(!defined("DEVELOPER")) define("DEVELOPER",1);
}

// ----------------------------------------------------------------------------
require_once('functions.php');
require_once('controller.php');
require_once('view.php');



//is_cli ?
if(!isset($_SERVER['SERVER_SOFTWARE']) && (php_sapi_name() == 'cli' || (is_numeric($_SERVER['argc']) && $_SERVER['argc'] > 0))){
    $options = getopt('', ['url:', 'host:', 'username:', 'password:', 'database:', 'list_tables']);
    cliAction($options);    
}else{
    action(isset($_REQUEST['action']) ? $_REQUEST['action'] : null);
}


// ----------------------------------------------------------------------------

// ----------------------------------------------------------------------------

