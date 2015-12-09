<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooIni
 * @since 26-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooIni
{
	function VoodooIni()
	{
		
	}
	/**#@+
	 * @static
	 */
	/**
	 * 
	 */
	function &load($name)
	{
		$name = strtolower($name);
		$registry =& VoodooRegistry::getInstance();
		if(!($conf = $registry->registry('ini.'.$name)))
			return VoodooIni::register($registry,$name);
		return $conf;
	}
	/**
	 * 
	 */
	function &register(&$registry,$name)
	{
		if(is_file(CONF.$name.'.ini')) {
			$conf = parse_ini_file(CONF.$name.'.ini',true);
			$registry->register('ini.'.$name,$conf);
			return $conf;
		}
		
		if(!defined(strtoupper($name).'_CONF'))
			return false;
		
		$conf = parse_ini_file(constant(strtoupper($name).'_CONF').$name.'.ini',true);
		$registry->register('ini.'.$name,$conf);
		return $conf;
	}
	/**#@-*/
}
?>
