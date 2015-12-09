<?php
require_once(CLASSES.'VoodooSetup.php');
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooSetup
 * @since 12-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class WikiSetup extends VoodooDefaultSetup
{
	var $potionTables = false;
	var $defTables = false;
	
	function verify()
	{
		$this->potionTables = $this->verifyPotions();
		$this->defTables = $this->verifyTable('TBL_WIKI');
		return ($this->potionTables||$this->defTables);
	}
	/**
	 * 
	 */
	function createTables()
	{
		$objects = array(
			'Wiki'=>array(WIKI_CLASSES,'Wiki.php'),
			'WikiRevision'=>array(WIKI_CLASSES,'Wiki.php')
			);
		$tables = $this->getCreateTablesFromAbstractObjects($objects);
		$this->execute($tables);
		$this->potionTables && $this->execute($this->potionTables);
	}
	/**
	 * Check for complex potions that include table definitions.
	 */
	function verifyPotions() {
		require_once(WIKI_CLASSES.'WikiPotion.php');
		// Loop all enabled potions.
		$conf = VoodooIni::load('wiki');
		$tables = array();
		foreach($conf['potions'] as $p => $enabled) {
			// If the potion is not enabled, we do not care for it.
			if(!$enabled) continue;
			
			$name = WikiPotionHandler::requirePotion($p);
			
			if(!$name) continue;			
			if(!class_exists($name.'Setup')) continue;
			
			$class = $name.'Setup';
			$s = new $class($this);
			// Merge the output with the already set tables.
			$tables = array_merge($tables, $s->getTables());
		}
		return $tables;
	}
}
?>