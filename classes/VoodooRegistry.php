<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooRegistry
 * @since 21-apr-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class VoodooRegistry
{
	/**
	 * Array containing references to different php Objects
	 * @var array $registry
	 */
	var $registry = array();
	/**
	 * Singleton
	 * @return $instance
	 */
	function &getInstance() 
	{
		static $instance;
		if(!$instance)
			$instance = array(new VoodooRegistry);
		return $instance[0];
	}
	/**
	 * Registers a new Object and adds it to the static registry
	 * @param string $name This is used for looking up saved registry Objects
	 * @param Object &$var
	 */
	function register($name,&$var)
	{
		$this->registry[$name] =& $var;
	}
	/**
	 * Retrieves the Object from the registry for usage
	 * @param string $name
	 * @return Object 
	 */
	function &registry($name)
	{
		$val = false;
		if(!isset($this->registry[$name]))
			return $val;
		return $this->registry[$name];
	}
}
?>