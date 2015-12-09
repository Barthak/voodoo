<?php
/**
 * Parent object of all the WikiPotions
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 1-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class WikiPotion
{
	/**
	 * The arguments supplied to the potion
	 * @var array $args
	 */
	var $args;
	/**
	 * @var WikiFormatter $formatter
	 */
	var $formatter;
	/**
	 * @var bool|string $error
	 */
	var $error;
	/**
	 * @var string $display
	 */
	var $display = '';
	/**
	 * Contructor
	 * @param WikiFormatter &$formatter
	 * @param array $args
	 * @param bool|string $error
	 */
	function WikiPotion(&$formatter,$args=array(),$error = false)
	{
		$this->formatter =& $formatter;
		$this->args = $args;
		$error && $this->setError($error);
		$this->init();
	}
	/**
	 * Sets the error, in case and error happens.
	 * TODO: look through all the individual WikiPotions and fix the part where they throw errors themselves.
	 * @param string $error
	 */
	function setError($error)
	{
		$this->error = $error;
	}
	/**
	 * Override this function
	 */
	function init()
	{
	 	//
	}
	/**
	 * Returns the potions output for display purposes
	 * @return string
	 */
	function display()
	{
		// We have an error! Let it be known
		if($this->error)
			return VoodooError::displayError($this->error);
		return $this->display;
	}
	/**
	 * Override this function, this should contain the usage help of a potion
	 * @return string
	 */
	function help()
	{
		return '';
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 1-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class WikiPotionHandler
{
	/**
	 * Create a new WikiPotion object by the name supplied
	 * 
	 * <p>
	 * WikiPotion are enabled in the wiki.ini in the WikiController conf directory
	 * 
	 * WikiPotions are located in the Potions directory. Each other Controller can have their own 
	 * Potions directory. This function check by the name of the potion where to look for it.
	 * If the potion cant be found, it triggers a VoodooError
	 * 
	 * [[WikiLogin]] This potion will be found in the WikiController/Potions directory
	 * [[Chat_WikiLurkers]] This potion will be found in the ChatController/Potions directory 
	 * </p>
	 * 
	 * @param WikiFormatter &$formatter
	 * @param string $potion
	 * @param array $args
	 * @return WikiPotion
	 */
	function createPotion(&$formatter,$potion,$args=array())
	{
		$p = WikiPotionHandler::requirePotion($potion);
		if(!$p) {
			return new WikiPotion($formatter,$args,sprintf('Error, Potion `%s` (%s) could not be included.',$p,$potion.'.php'));
		}
		return new $p($formatter,$args);
	}
	/**
	 * @static
	 */
	function requirePotion($potion) {
		$path = explode('_',$potion);
		if(count($path)==1) {
			$path = SPELLBOOK.'Wiki/Potions/';
		} elseif(count($path)>2) {
			trigger_error('Incorrect WikiPotion definition',E_USER_ERROR);
			exit();
		} else {
			$registry =& VoodooRegistry::getInstance();
			$vc =& $registry->registry('VC',$this);
			
			$potion = $path[1];
			$path = $path[0];
			
			if(!array_key_exists(strtolower($path), $vc->conf['controllers']))
				return false;
			if(!$vc->conf['controllers'][strtolower($path)])
				return false;
			
			$path = SPELLBOOK.$path.'/Potions/';
		}
		// Does the potion actually exist?
		if(!is_file($path.$potion.'.php'))
			return false;
		
		// Yes it does, lets make a new instance of it
		require_once($path.$potion.'.php');
		return $potion;
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage Potion
 * @since 06-feb-2010
 * @license www.dalines.org/license
 * @copyright 2010, geopelepsis.complicated
 */
class WikiPotionSetup
{
	/**
	 * @var VoodooSetup $setup
	 */
	var $setup;
	/**
	 * @param VoodooSetup $setup
	 */
	function WikiPotionSetup(&$setup)
	{
		$this->setup =& $setup;
	}
	function getTables()
	{
		return array();
	}
}
?>
