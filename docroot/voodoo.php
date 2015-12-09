<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
//error_reporting(E_ALL);
date_default_timezone_set('Europe/Amsterdam'); 

function mt()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}
$s = mt();

require_once('../conf/const.php');
require_once(CLASSES.'SessionHandler.php');
require_once(CLASSES.'VoodooController.php');
// DO IT! DO IT!
$vc = new VoodooController();
echo '<br />parse time: '.(mt()-$s).'';
?>
