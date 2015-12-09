<?php
/**
 * Displays the table of contents of a wiki context object
 * 
 * This uses all the (=,==,etc) header tags
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 3-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiToc extends WikiPotion
{
	/**
	 * 
	 */
	function init()
	{
		// No headers are set for the current context, there is nothing to display
		if(!isset($this->formatter->headers))
			return;
		
		// No headers are set for the current context, there is nothing to display
		if(!count($this->formatter->headers))
			return;
		
		// Optional argument can be supplied to override the title of this div.
		$title = 'Table of Contents';
		if(isset($this->args[0]))
			$title = $this->args[0];
		
		$rv = sprintf('<div class="toc"><strong>%s</strong><br />',$title);
		$url = $_SERVER['REQUEST_URI']; // TODO: use something better than this ?
		foreach($this->formatter->headers as $values)
		{
			list($type,$title) = $values;
			$indent = str_repeat('&nbsp;&nbsp;',($type-1));
			$rv .= sprintf('%s<a href="%s#%s">%s</a><br />',$indent,$url,str_replace(' ','',$title),$title);
		}
		$rv .= '</div>';
		return $this->display = $rv;
	}
}
?>
