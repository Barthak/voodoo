<?php
/**
 * TODO: add proper {if} support
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage SimpleTemplate
 * @since 21-jan-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class SimpleTemplate
{
	/**
	 * @var array $replace
	 */
	var $replace;
	/**
	 * Used in the Foreach part
	 * TODO: mkpretty
	 * @var array $tmp_replace
	 */
	var $tmp_replace;
	/**
	 * @var string $template_dir
	 */
	var $template_dir = false;
	var $theme_dir = false;
	/**
	 * Contructor
	 * @param string $template_dir
	 */
	function SimpleTemplate()
	{
	}
	/**
	 * 
	 */
	function getTemplateDir()
	{
		return $this->template_dir;
	}
	/**
	 * Parses the template and replaces placeholders ${var} with the correct replacement from replacements.
	 * @see Smarty syntax
	 * @param string $template This is the name of the template without the .html
	 * @param array $replacements
	 * @return string
	 */
	function parse($template,$replacements)
	{
		$content = file_get_contents($template.'.html');
		
		$this->replace = $replacements;
		$content = preg_replace_callback('/{template ([\w\._\-\d]+)}/sU', array($this, '_nestedTemplate'),$content);
		$content = preg_replace_callback('/{foreach( name=\w+)?( item=\w+)? (from=\$\w+)}(.*){\/foreach}/sU',array($this,'_foreach'),$content);
		$content = preg_replace_callback('/{isset \$(\w+)}(.*){\/isset}/sU',array($this,'_isset'),$content);
		return preg_replace_callback('/\${(.*)}/U',array($this,'_replace'),$content);
	}
	
	function _isset($args) {
		if(empty($this->replace[$args[1]]))
			return '';
		
		return $args[2];
	}
	
	/**
	 * Note: Replaces the placeholder with an empty string in case the replacement can not be found.
	 * @param array $args (${varname},varname);
	 * @return string
	 */
	function _replace($args)
	{
		$varname = $args[1];
		return isset($this->replace[$varname])?$this->replace[$varname]:'';
	}
	
	function _nestedTemplate($args) {
		$template =& VoodooTemplate::getInstance();
		return $template->parse($args[1],$this->replace);
	}
	
	/**
	 * @see Smarty Syntax
	 */
	function _foreach($args)
	{
		$replace = array_pop($args);
		array_shift($args);
		$r_args = array();
		$item = false;
		$name = false;
		$from = false;
		foreach($args as $arg_type)
		{
			list($type,$varname) = explode('=',$arg_type);
			$type = trim($type);
			$$type = $varname;
		}
		
		$from = str_replace('$','',$from);
		if(!isset($this->replace[$from])||!is_array($this->replace[$from]))
			return '';
		
		$rv = '';
		foreach($this->replace[$from] as $indx => $val)
		{
			$this->tmp_replace = array_merge($this->replace,array($item=>$indx,$name=>$val));
			if(is_array($val))
			{
				foreach($val as $nm => $value)
					$this->tmp_replace[$item.'.'.$nm] = $value;
			}
			$rv .= preg_replace_callback('/\${(.*)}/U',array($this,'_foreach_replace'),$replace);
		}
		
		return $rv;
	}
	/**
	 * Replacement for within the foreach loops.
	 * Note: Replaces the placeholder with an empty string in case the replacement can not be found.
	 * @param array $args
	 * @return string
	 */
	function _foreach_replace($args)
	{
		$varname = $args[1];
		return isset($this->tmp_replace[$varname])?$this->tmp_replace[$varname]:'';
	}
}
?>