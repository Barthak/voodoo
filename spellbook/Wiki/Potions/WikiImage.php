<?php
/**
 * This can display an image within a wiki context
 * 
 * <p>Usage:
 * 
 * [[WikiImage(/images/chatzone2.gif,border:0;wiki:TheChatZone)]]
 * 
 * This will display the chatzone2.gif image located in /images/
 * Options set are a 0px border and a link to the wiki page TheChatZone
 * 
 * Available options are:
 *  * Width
 *  * Height
 *  * Style
 *  * Class
 *  * Border
 *  * Align
 *  * Wiki
 *  * Link
 * </p>
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 26-apr-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiImage extends WikiPotion
{
	/**
	 * Initalize
	 * @return string
	 */
	function init()
	{
		$args = $this->args;
		if((!count($args))||(count($args)>2))
			return $this->display = VoodooError::displayError('WikiImage: Invalid number of Arguments supplied.');
	
		// TODO: mkpretty regular expression to check for file type
		
		$opts = '';
		$allowedOpts = array('width','height','style','class','border','align');
		
		$replace = '%s';
		
		if(isset($args[1]))
		{
			$options = explode(';',$args[1]);
			// Loop through the options
			foreach($options as $opt)
			{
				list($var,$val) = explode(':',$opt);
				if(in_array($var,$allowedOpts))
					$opts .= sprintf(' %s="%s"',$var,$val);
				elseif($var=='wiki')
					$replace = sprintf('<a href="%s/wiki/%s">',PATH_TO_DOCROOT,$val).'%s</a>';
				elseif($var=='link')
					$replace = sprintf('<a href="%s">',$val).'%s</a>';
			}
		}
		
		return $this->display = sprintf($replace,sprintf('<img src="%s"%s />',$args[0],$opts));
	}
}
?>