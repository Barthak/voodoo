<?php
require_once(ATTACHMENT_CLASSES.'Attachment.php');
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentSetup
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentSetup extends VoodooDefaultSetup
{
	/**
	 * We verify on individual tables, since other controllers might have had their Attachments enabled.
	 */
	function verify()
	{
		return true;
	}
	/**
	 * We need a table for the attachments if it doesnt exist TBL_ATTACHMENT
	 */
	function createTables()
	{
		$tables = array();
		if($this->verifyTable('TBL_ATTACHMENT'))
		{
			$obj = new Attachment($this->db);
			$tables[] = $obj->generateCreateTables();
		}
		$tables = $this->addLinkTables($tables);
		$this->execute($tables);
	}
	/**
	 * Add a link table for every controller that has Attachments enabled
	 * @param array $tables
	 * @return array $tables
	 */
	function addLinkTables($tables)
	{
		// get the voodoo configuration for all available controllers
		$ini = VoodooIni::load('voodoo');
		foreach($ini['controllers'] as $controller => $enabled)
		{
			$class = 'AttachmentLink'; // Default object
			if(!$enabled) // The Controller is not enabled, skip.
				continue;
			$uc = strtoupper($controller);
			if(defined($uc.'_CLASSES')) // There is a classes directory defined for the controller.
			{
				// If there is not a configuration file for the controller, we skip
				if(!is_file(constant($uc.'_CONF').$controller.'.ini'))
					continue;
				$conf = VoodooIni::load($controller);
				// Attachments are not enabled for this controller, skip
				if(!isset($conf['attachment'])||!$conf['attachment']['attachment'])
					continue;
				// Hey, this controller has a seperate attachment link object, lets require it
				if(isset($conf['attachment']['class']))
				{
					// heh, the class was defined in the ini, but doesnt exist. Skip.
					if(!is_file(constant($uc.'_CLASSES').$conf['attachment']['class']))
						continue;
					require_once(constant($uc.'_CLASSES').$conf['attachment']['class']);
					$class = ucfirst($controller).'Attachment';
				}
			}
			// Use the abstract object functionality to parse the create tables
			$obj = new $class($this->db,$uc);
			if($this->verifyTable($obj->getTable()))
				$tables[] = $obj->generateCreateTables();
		}
		return $tables;
	}
}
?>