<?php
/**
 * Takes up to three arguments
 * argument 1: Only Wikis starting with this
 * argument 2: Display (inline or pullout)
 * argument 3: Title
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 4-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiTitleIndex extends WikiPotion
{
	/**
	 * 
	 */
	function init()
	{
		$args = $this->args;
		$where = '';
		$display = 'inline';
		$title = '';
		
		// VALIDATE ALL THE INPUT FROM THE ARGUMENTS!!!
		if(isset($args[0])&&preg_match('/^[A-Z]{1}[a-zA-Z]*$/',$args[0])) // Change the where but only if valid string
			$where = " WHERE WIKI_HANDLE LIKE '".$args[0]."%'";
		if(isset($args[1])&&in_array($args[1],array('inline','pullout'))) // Change the display type
			$display = $args[1];
		if(!empty($args[2])) // show the title if supplied.
			$title = sprintf('<strong>%s</strong>',$args[2]);
		
		// Get the wikihandles
		$sql = sprintf("SELECT WIKI_HANDLE FROM TBL_WIKI %s ORDER BY WIKI_HANDLE",$where);
		$q = $this->formatter->db->query($sql);
		$q->execute();
		
		
		$inline = $title.'<ul>';
		$pullout = '<div class="toc">'.$title;
		while($r = $q->fetch())
		{
			$link = sprintf('<a href="%s/wiki/%s">%s</a>',PATH_TO_DOCROOT,$r->WIKI_HANDLE,$r->WIKI_HANDLE);
			$inline .= sprintf('<li>%s</li>',$link);
			if($this->formatter->action == $r->WIKI_HANDLE)
				$link = sprintf('<div class="selectedItem">%s</div>',$r->WIKI_HANDLE);
			$pullout .= sprintf('<div>%s</div>',$link);
		}
		$inline .= '</ul>';
		$pullout .= '</div>';
		return $this->display = $$display;
	}
}
?>
