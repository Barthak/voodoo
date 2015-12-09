<?php
/**
 * Displays a list of all available and enabled WikiPotions
 * 
 * TODO: Parse Help + author creation time etc.
 * @author Marten Koedam <marten@dalines.net>
 * @package
 * @subpackage
 * @since 6-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiPotions extends WikiPotion
{
	/**
	 * @return string
	 */
	function init()
	{
		$rv = '';
		// Parse ini
		$opts = VoodooIni::load('wiki');
		foreach($opts['potions'] as $potion => $enabled)
		{
			if($enabled) // The potion is enabled, list it.
				$rv .= '<h4>'.$potion.'</h4>';
		}
		return $this->display = $rv;
	}
}
?>