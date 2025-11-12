<?php
 /*
  * Create single file version 
  */

$content = '<?php';
$content .= substr(file_get_contents(dirname(__FILE__) . "/dumper2.php"), 5);
$content .= substr(file_get_contents(dirname(__FILE__) . "/functions.php"), 5);
$content .= substr(file_get_contents(dirname(__FILE__) . "/controller.php"), 5);
$content .= substr(file_get_contents(dirname(__FILE__) . "/view.php"), 5);
$content .= substr(file_get_contents(dirname(__FILE__) . "/dump.php"), 5);

$content = preg_replace('/require_once\(.*\);/', '', $content);



$filename = dirname(__FILE__). '/remote.sql.dump.php';
file_put_contents($filename, $content);        
echo 'Created file: '.$filename;
//error_reporting(E_ALL ^ E_NOTICE ^ E_DEPRECATED ^ E_STRICT);

