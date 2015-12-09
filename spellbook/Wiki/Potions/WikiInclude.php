<?php
/**
 * Include a .html file within the wiki context
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 8-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiInclude extends WikiPotion
{
	/**
	 * @return string
	 */
	function init()
	{
		$args = $this->args;
		if(!count($args)) // No arguments = error
			return $this->display = VoodooError::displayError('WikiInclude: Invalid number of Arguments supplied.');
		if(substr($args[0],-5)!='.html') // Not .html = error
			return $this->display = VoodooError::displayError('WikiInclude: Argument needs to be a .html filename.');
		if(substr($args[0],0,1)=='.'||substr($args[0],0,1)=='/') // start with / or a dot (.) = error
			return $this->display = VoodooError::displayError('WikiInclude: Invalid Argument supplied..');
		
		$template =& VoodooTemplate::getInstance();
		$template->setDir(WIKI_TEMPLATES);
		return $this->display = $template->parse(str_replace('.html','',$args[0]),array('prepath'=>PATH_TO_DOCROOT));
	}
}
?>
