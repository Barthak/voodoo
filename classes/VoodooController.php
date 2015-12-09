<?php
require_once(CLASSES.'User.php');
require_once(CLASSES.'VoodooRegistry.php');
require_once(CLASSES.'VoodooTemplate.php');
require_once(CLASSES.'VoodooIni.php');
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class VoodooController
{
	/**
	 * @var array $conf
	 */
	var $conf = array();
	/**
	 * @var array $spellbook
	 */
	var $spellbook = array();
	var $hooks = array();
	/**
	 * Constructor
	 * 
	 * <p>Adds the VoodooController to the Registry and initializes the 
	 * framework and routes to the specified controllers.</p>
	 */
	function VoodooController()
	{
		$this->init();

		$registry =& VoodooRegistry::getInstance();
		$registry->register('VC',$this);

		$this->route();
	}
	/**
	 * Initializes the framework.
	 * 
	 * <p>Parses the voodoo.ini configuration file and reads the available controllers.</p>
	 */
	function init()
	{
		$this->conf = VoodooIni::load('voodoo');
		$this->setTheme($this->conf['engine']['site.theme']);
		VoodooTemplate::getInstance($this->conf['template']);
		
		foreach($this->conf['controllers'] as $controller => $status)
			$status && $this->_initController($controller);
		$registry =& VoodooRegistry::getInstance();
		$registry->register('hooks',$this->hooks);
	}
	/**
	 * 
	 */
	function setTheme($theme)
	{
		if(!$theme)
		{
			define('THEME','default');
			define('THEME_NAME','default');
			define('DEFAULT_THEME_TEMPLATES',TEMPLATES);
			return;
		}
		define('THEME',THEMES.$theme.'/');
		define('THEME_NAME',$theme);
		define(strtoupper($theme).'_THEME_TEMPLATES',THEME);
	}
	/**
	 * Initializes an individual controller. 
	 * 
	 * <p>This function adds a few default defines for the controller. </p>
	 * @access protected
	 * @param string $controller The name of the Controller
	 */
	function _initController($controller)
	{
		$settings = isset($this->conf['controller.'.$controller])?$this->conf['controller.'.$controller]:false;
		$uc = ucfirst($controller);
		if(file_exists(SPELLBOOK.$uc.'/'.$uc.'Controller.php'))
		{
			define(strtoupper($uc).'_CLASSES',SPELLBOOK.$uc.'/classes/');
			define(strtoupper($uc).'_CONF',SPELLBOOK.$uc.'/conf/');
			define(strtoupper($uc).'_SPELLBOOK',SPELLBOOK.$uc.'/');
			define(strtoupper($uc).'_TEMPLATES',$uc);
			(THEME!='default') && define(strtoupper($uc).'_THEME_TEMPLATES',THEME.$uc.'/');
			define(strtoupper($uc).'_STYLE',SPELLBOOK.$uc.'/style/');
			define(strtoupper($uc).'_IMAGES',SPELLBOOK.$uc.'/images/');
			define(strtoupper($uc).'_SCRIPTS',SPELLBOOK.$uc.'/scripts/');
			$this->spellbook[$controller] = $uc;
			$alias = $settings?explode(',',$settings['alias']):array();
			foreach($alias as $name)
			{
				if(isset($this->spellbook[$name]))
					trigger_error('Duplicate Alias in SpellBook',E_USER_ERROR);
				$this->spellbook[$name] = $uc;
			}
			if(file_exists(SPELLBOOK.$uc.'/'.$uc.'Hooks.php'))
			{
				require_once(SPELLBOOK.$uc.'/'.$uc.'Hooks.php');
				$class = $uc.'Hooks';
				$this->hooks[$class] = new $class();
			}
		}
	}
	/**
	 * Routes to the correct controller and its dispatcher
	 * 
	 * <p>This function also outputs the site. The route is based on the requested URI.</p>
	 */
	function route()
	{
		$requested = str_replace(PATH_TO_DOCROOT,'',$_SERVER['REQUEST_URI']);
		$requested = preg_replace('/^\//','',$requested);
		$requested = preg_replace('/\?.*/','',$requested);
		$actionlist = explode('/',$requested);
		
		$dispatcher = array_shift($actionlist);
		if(empty($dispatcher))
		{
			header('Location: '.PATH_TO_DOCROOT.'/'.$this->conf['engine']['site.startpage']);
			exit();
		}
		if(!isset($this->spellbook[$dispatcher]))
		{
			header('Location: '.PATH_TO_DOCROOT.'/admin/ControllerUnavailable');
			exit();
		}
		$controller = $this->spellbook[$dispatcher];
		
		if(!$this->conf['engine']['site.setup'] && !in_array($controller,array('Admin','Basic')))
		{
			header('Location: '.PATH_TO_DOCROOT.'/setup/Init/');
			exit();
		}
		
		$registry =& VoodooRegistry::getInstance();
		$registry->register('controller',$controller);
		
		require_once(SPELLBOOK.$controller.'/'.$controller.'Controller.php');
		$obj = $this->spellbook[$dispatcher].'Controller';
			
		$controller = new $obj($dispatcher,$actionlist);
		echo $controller->display();
	}
}
/**
 * TODO: this should use a CONTEXT object (a context should have an owner and a parent?)
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooController
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class VoodooPrivileges
{
	/**
	 * @var *Controller &$controller
	 */
	var $controller;
	/**
	 * @param *Controller &$controller
	 */
	function VoodooPrivileges(&$controller)
	{
		$this->controller = $controller;
	}
	/**
	 * Checks the rights based on a few settings
	 * 
	 * <p>For example (wiki.ini):
	 * [privileges]
	 * wiki.view = Member
	 * wiki.PageName.view = Admin
	 * 
	 * When logged in as a regular User
	 * <?php
	 * echo VoodooPrivileges::hasRights($_SESSION['access'],'view','wiki',$wikiconf['privileges'])?'true':'false'; // Outputs true
	 * echo VoodooPrivileges::hasRights($_SESSION['access'],'view','wiki',$wikiconf['privileges'],'PageName')?'true':'false'; // Outputs false
	 * ?>
	 * </p>
	 */
	function hasRights($access,$type,$subject,$privs=array(),$item='',$obj=false)
	{
		$item && $item = '.'.$item;
		
		if($item && (!isset($privs[$subject.$item.'.'.$type])))
			$item = '';
			
		if(!isset($privs[$subject.$item.'.'.$type]))
			return false;
		// Converts from name (Admin,Member) to integer (60,30)
		$need = $this->controller->convertAccessLevel($privs[$subject.$item.'.'.$type]);
		
		if(count($need)==1)
		{
			$need = array_pop($need);
			if(($need=='Owner')&&$obj)
				return $this->checkOwner($obj);
			if($access>=$need)
				return true;
			return false;
		}

		if(in_array($access,$need))
			return true;
		
		if(in_array('Owner',$need)&&$obj)
			return $this->checkOwner($obj);

		return false;
	}
	function checkOwner($obj)
	{
		if($_SESSION['user_id']==$obj->user->id)
			return true;
		return false;
	}
	/**
	 * @static
	 * @param string $error
	 */
	function displayError($error)
	{
		return array('Error',VoodooError::displayError($error));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooController
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class DefaultController
{
	/**
	 * @var array $conf
	 */
	var $conf;
	/**
	 * @var string $script
	 */
	var $script;
	/**
	 * @var string $title
	 */
	var $title;
	/**
	 * @var string $content
	 */
	var $content;
	/**
	 * Contains all the different stylesheets used in the different controllers
	 * @var array $styles
	 */
	var $styles = array();
	var $style = '';
	/**
	 * @var array $siteArgs
	 */
	var $siteArgs = array();
	/**
	 * @var array $voodooConf
	 */
	var $voodooConf = array();
	/**
	 * 
	 */
	var $dispatchername = '';
	/**
	 * 
	 */
	var $dispatcherObject = false;
	/**
	 * Contructor
	 */
	function DefaultController($complete=true,$formatter=null)
	{
		$this->conf = VoodooIni::load('engine');
		$this->voodooConf = VoodooIni::load('voodoo');
		
		$db = $this->DBConnect();
		
		$this->addStyleSheet($this->voodooConf['engine']['site.style']);
		if($complete)
		{
			$formatter || $formatter = $this->voodooConf['engine']['site.formatter'];
			$this->setFormatter($db,$formatter);
			$this->initSession($db);
		}
		else
			$this->setFormatter($db,'VoodooFormatter');
	}
	/**
	 * @param Database $db
	 * @param string $formatter
	 */
	function setFormatter($db,$formatter=false)
	{
		$formatter || $formatter = 'VoodooFormatter';
		if(!preg_match('/^[A-Za-z0-9_]+$/',$formatter))
			exit('Incorrect Formatter'); // Error out
		$split = split('_',$formatter);
		require_once(CLASSES.'VoodooFormatter.php');
		if(count($split)==2)
		{
			list($dir,$formatter) = $split;
			if(is_file(SPELLBOOK.$dir.'/classes/'.$formatter.'.php'))
				require_once(SPELLBOOK.$dir.'/classes/'.$formatter.'.php');
			else
				$formatter = 'VoodooFormatter';
		}
		$f = new $formatter($db);
		VoodooFormatter::register($f); 
	}
	
	/**
	 * Connect to the database.
	 * 
	 * <p>Settings for the database are provided in the engine.ini file.</p>
	 */
	function DBConnect()
	{
		require_once(CLASSES.'Database.php');
		$settings = $this->conf['database'];
		$connstring = $settings['driver'].":".$settings['server'].":".$settings['name'];
		return new Database($connstring,$settings['user'],$settings['password'],true);
	}
	/**
	 * Converts the accesslevel from a groupname (Admin,Member) to an integer (60,30)
	 * @param string $type
	 * @return int
	 */
	function convertAccessLevel($type)
	{
		$rv = array();
		$type = explode(',',$type);
		foreach($type as $group)
		{
			if(isset($this->conf['usergroups'][$group]))
				$rv[] = $this->conf['usergroups'][$group];
			elseif($group == 'Owner')
				$rv[] = 'Owner';
			else
				$rv[] = 10000;
		}
		return $rv;
	}
	/**
	 * Initializes the VoodooSession
	 * @return VoodooSession $rv
	 */
	function initSession()
	{
		require_once(CLASSES.'VoodooSession.php');
		$rv = new VoodooSession($this->DBConnect());
		return $rv;
	}
	/**
	 * Example:
	 * 
	 * URI: /wiki/WikiFormatting
	 * $dispatcher = 'wiki';
	 * $actionlist = array(0=>'WikiFormatting')
	 * 
	 * @param string $dispatcher
	 * @param array $actionlist
	 */
	function route($dispatcher,$actionlist)
	{
		if(!isset($this->dispatchers[$dispatcher]))
			exit('Incorrect Dispatcher Supplied.');
		$this->dispatchername = $dispatcher;
		list($this->title,$this->content) = $this->dispatchers[$dispatcher]->dispatch($actionlist);
		$this->executePreDisplayHooks($actionlist);
	}
	/**
	 * Returns the output that was build by the controller and its dispatchers
	 * @return string
	 */
	function display($template=null,$dir=null)
	{
		$template || $template = THEME_NAME;
		$r =& VoodooRegistry::getInstance();
		$t =& VoodooTemplate::getInstance();
		$t->setDir($dir);
		$v =& $r->registry('VC');
		return $t->parse($template,
			array_merge($this->siteArgs,
				array(
					'site_title'=>$v->conf['engine']['site.title'],
					'script'=>$this->script,
					'styles'=>$this->styles,
					'style'=>$this->style,
					'title'=>$this->title,
					'prepath'=>PATH_TO_DOCROOT,
					'request_uri'=>preg_replace('/[\/]?\?(.*)/','',$_SERVER['REQUEST_URI']),
					'menu'=>$this->getMenu($v->conf['menu'],
								isset($v->conf['menu.privileges'])?$v->conf['menu.privileges']:array()),
					'content'=>$this->content)
				)
			);
	}
	/**
	 * Execute any hooks that should be completed before display
	 * @param array $actionlist
	 */
	function executePreDisplayHooks($actionlist)
	{
		$registry =& VoodooRegistry::getInstance();
		$controller = $registry->registry('controller');
		$hooks = $registry->registry('hooks');
		foreach($hooks as $class => $instance)
		{
			$instance->setDB($this->DBConnect());
			foreach($instance->preDisplayHooks() as $hook => $callback)
			{
				list($obj,$func) = $callback;
				$action = $this->dispatcherObject?array($this->dispatcherObject):$actionlist;
				$this->content = $obj->$func($controller,$action,$this->content);
			}
		}
	}
	/**
	 * 
	 */
	function addSiteArg($name,$value)
	{
		$this->siteArgs[$name] = $value;
	}
	/**
	 * 
	 */
	function getSiteArg($name)
	{
		return $this->siteArgs[$name];
	}
	/**
	 * Add a stylesheet to the list of stylesheets to parse
	 * 
	 * <p>In your template use a for loop to parse all of the stylesheets
	 * Also, make sure to add .css to the end of this string, else it will be invalid 
	 * and the BasicController wont be able to dispatch it.
	 * </p>
	 * 
	 * @see spellbook/Basic/*
	 * @param string $css
	 */
	function addStyleSheet($css)
	{
		$this->styles[$css] = $css;
	}
	/**
	 * Remove a stylesheet from the parse array.
	 * 
	 * <p>This is useful if you want to override/get rid of the default 
	 * stylesheet set in the voodoo.ini file in certain interfaces</p>
	 * 
	 * @param string $css
	 */
	function removeStyleSheet($css)
	{
		unset($this->styles[$css]);
	}
	/**
	 * Gets the menu listed in the conf file
	 * TODO: create nested menu's and mkpretty
	 */
	function getMenu($conf, $privs)
	{
		$p = new VoodooPrivileges($this);
		$rv = array();
		foreach($conf as $menu => $title)
		{
			if(!isset($privs['menu.'.$menu])||(!empty($_SESSION)&&$p->hasRights($_SESSION['access'],$menu,'menu',$privs)))
				$rv[] = array('link'=>$menu,'title'=>$title,'class'=>'');
		}
		return $rv;
	}
}
?>
