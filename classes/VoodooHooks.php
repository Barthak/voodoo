<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooHooks
 * @since 21-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooHooks
{
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @var VoodooFormatter $formatter
	 */
	var $formatter;
	/**
	 * @param Database $db
	 */
	function VoodooHooks($db=null)
	{
		$db && $this->setDB($db);
	}
	/**
	 * @param Database $db
	 */
	function setDB($db)
	{
		$this->db = $db;
	}
	/**
	 * @return array('hookname'=>array(callbackObj,callbackFunc))
	 */
	function preDisplayHooks()
	{
		return array();
	}
	/**
	 * @return array('hookname'=>array(callbackObj,callbackFunc))
	 */
	function formattingHooks()
	{
		return array();
	}
}
?>
