<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooFormatter
 * @since 17-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooFormatter 
{
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @param $db
	 */
	function VoodooFormatter(&$db)
	{
		$this->db =& $db;
		$this->setup();
	}
	/**
	 * Do the initial setup of the formatter (parse ini file or something)
	 */
	function setup()
	{
		// nothing
	}
	/**
	 * Format the text
	 * @param string $text
	 * @param mixed $args
	 * @return string $text
	 */
	function parse($text,$args)
	{
		$registry =& VoodooRegistry::getInstance();
		//$controller = $registry->registry('controller');
		$hooks = $registry->registry('hooks');
		
		foreach($hooks as $class => $instance)
		{
			foreach($instance->formattingHooks() as $hook => $callback)
			{
				list($obj,$func) = $callback;
				$text = $obj->$func($text,$this);
			}
		}
		
		return $this->postHooksFormatting($text);
	}
	
	function postHooksFormatting($text)
	{
		return $text;
	}
	
	/**#@+
	 * @static
	 */
	/**
	 * @param string $text
	 * @param mixed $args
	 * @return string
	 */
	function format($text,$args=array())
	{
		$formatter =& VoodooFormatter::getInstance();
		return $formatter->parse($text,$args);
	}
	/**
	 * @param VoodooFormatter $formatter
	 */
	function register(&$formatter)
	{
		$registry =& VoodooRegistry::getInstance();
		$registry->register('formatter',$formatter);
	}
	/**
	 * @return VoodooFormatter 
	 */
	function &getInstance()
	{
		$registry =& VoodooRegistry::getInstance();
		return $registry->registry('formatter');
	}
	/**#@-*/
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooFormatter
 * @since 17-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooError
{
	/**
	 * @static
	 */
	function displayError($error)
	{
		return '<div class="voodooerror">'.$error.'</div>';
	}
}
?>
