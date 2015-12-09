<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage WikiPotions
 * @since 20-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class WikiAttachmentImage extends WikiPotion
{
	/**
	 * Usage: [[Attachment_WikiAttachmentImage(attachment.jpg)]]
	 * This assumes that the requested image is part of the current handler/action
	 * Requires one argument
	 */
	function init()
	{
		if(!isset($this->args[0]))
			return $this->display = VoodooError::displayError('Incorrect number of arguments supplied for WikiAttachmentImage');
		
		$name = $this->args[0];
		
		$this->display = sprintf('<img src="%s/attachment/%s/%s/%s?action=download" alt="%s" />',
			PATH_TO_DOCROOT,
			$this->formatter->handler,
			$this->formatter->action,
			$name,
			$name
			);
	}
}
?>
