<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AdminController
 * @subpackage AdminController
 * @since 18-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class AdminController extends DefaultController
{
	/**
	 * @var array $dispatchers
	 */
	var $dispatchers = array();
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @param string $dispatcher
	 * @param array $actionlist
	 * @param bool $route
	 */
	function AdminController($dispatcher,$actionlist,$route=true)
	{
		$this->init();
		$route && $this->route($dispatcher,$actionlist);
	}
	/**
	 * Initializes the AdminController and its dispatchers
	 */
	function init()
	{
		// Call the DefaultController
		$conf = VoodooIni::load('voodoo');
		$this->DefaultController($conf['engine']['site.setup']);
		
		$this->db = $this->DBConnect();
		$this->dispatchers['admin'] = new AdminDispatcher($this);
		$this->dispatchers['setup'] = new SetupDispatcher($this);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AdminController
 * @subpackage AdminController
 * @since 18-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class AdminDefaultDispatcher
{
	/**
	 * @var AdminController $controller
	 */
	var $controller;
	/**
	 * @var array $conf
	 */
	var $conf;
	/**
	 * @var VoodooPrivileges $privs
	 */
	var $privs;
	/**
	 * Contructor
	 * @param AdminController &$controller
	 */
	function AdminDefaultDispatcher(&$controller)
	{
		$this->controller =& $controller;
		$this->init();
		$this->privs = new VoodooPrivileges($controller);
	}
	/**
	 * Initialization...
	 * 
	 * Currently only loads the admin.ini configuration file
	 */
	function init()
	{
		$this->conf = VoodooIni::load('admin');
	}
	/**
	 * Calls the VoodooPrivileges to see if you have rights on given environment
	 * 
	 * @see VoodooPrivileges
	 * @param string $access // The users current accesslevel 
	 * @param string $action // admin,wiki,etc
	 * @param string $type // view,modify,create,delete
	 * @param string $subject
	 */
	function hasRights($access,$action,$type,$subject='')
	{
		$privs = $this->conf['privileges'];
		return $this->privs->hasRights($access,$type,$action,$privs,$subject);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AdminController
 * @subpackage AdminController
 * @since 18-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class AdminDispatcher extends AdminDefaultDispatcher
{
	/**
	 * @param array $actionlist
	 */
	function dispatch($actionlist)
	{
		if(!$this->hasRights($_SESSION['access'],'admin','view'))
			return VoodooPrivileges::displayError('Permission Denied');
		if(!count($actionlist))
			return $this->configuration();
		switch($actionlist[0])
		{
			case 'Sitemap.xml':
				$this->sitemaps();
			break;
			case 'users':
				require_once(CLASSES.'TableFactory.php');
				$user = new User($this->controller->db);
				$q = $user->getUserOverview();
				$tf = new TableFactory($q);
				$tf->setValueProcessor('USER_IMG','return substr(htmlentities($col),0,30)."...";');
				return array('User Overview',$tf->getXHTMLTable('list report'));
			break;
			case 'ControllerUnavailable':
				return array('Controller Unavailable',VoodooError::displayError('This Controller is unavailable.'));
			break;
			case 'config':
				return $this->configuration((isset($actionlist[1])?$actionlist[1]:null));
			break;
		}
	}
	
	function sitemaps() {
		header("Content-type: text/xml");
		
		// loop all controllers and get their sitemaps.
		exit();
	}
	
	/**
	 * 
	 */
	function configuration($controller=null)
	{
		$title = 'Configuration';
		$content = '';
		
		if($controller)
		{
			return array($title,$content);
		}
		
		$conf = VoodooIni::load('voodoo');
		$template =& VoodooTemplate::getInstance();
		$template->setDir(ADMIN_TEMPLATES);
		$vars = array(
			'prepath'=>PATH_TO_DOCROOT,
			'controllers'=>array());
		foreach($conf['controllers'] as $controller => $enabled)
			$vars['controllers'][$controller] = $this->getControllerInfo($controller,$enabled);
		
		$this->controller->addStyleSheet('admin/admin.css');
		return array($title,$template->parse('config',$vars));
	}
	/**
	 * Reads the PKG_INFO file
	 * 
	 * TODO: finish
	 */
	function getControllerInfo($controller,$enabled)
	{
		$controller = ucfirst($controller);

		$rv = array(
			'name'=>$controller,
			'enabled'=>($enabled?'Enabled':'Disabled')
			);
		
		if(is_file(SPELLBOOK.'/'.$controller.'/PKG_INFO'))
			$rv = array_merge($rv, $this->_packageInfo(SPELLBOOK.'/'.$controller.'/PKG_INFO'));
		
		return $rv;
	}
	
	function _packageInfo($file)
	{
		$fp = fopen($file,'r');
		$rv = array();
		while(!feof($fp))
		{
			list($index,$value) = preg_split('/ = /',fgets($fp,1024));
			$rv[$index] = $value;
		}
		fclose($fp);
		return $rv;
	}
}
/**
 * This dispatcher is used for installing new Controllers. 
 * 
 * It is also used for the initial setup of a Voodoo Project. 
 * Be careful with automated execution of any Controller Setup's.
 * Make sure you checked the code, and dont use a MySQL user 
 * that has access to every database in your system.
 * 
 * Preferably dont use the automated part of the setup but just copy 
 * the displayed SQL statements, this way you can double check what it's
 * doing and no hidden queries can be executed.
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package AdminController
 * @subpackage AdminController
 * @since 12-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class SetupDispatcher extends AdminDefaultDispatcher
{
	/**
	 * @param array $actionlist
	 */
	function dispatch($actionlist)
	{
		require_once(CLASSES.'VoodooSetup.php');
		if(!count($actionlist))
		{
			// Dont do anything
			return $this->login();
		}
		
		$args = array(
			'prepath'=>PATH_TO_DOCROOT
			);
		$showCredentials = (bool)$this->conf['setup']['insecure_sql_execution'];
		switch($actionlist[0])
		{
			// The first admin to be created is the God Admin
			case 'CreateAdmin':
				return $this->createAdmin();
			break;
			case 'Login':
				return $this->login();
			break;
			case 'conf':
				if(!$this->hasRights($_SESSION['access'],'conf','view'))
					return VoodooPrivileges::displayError('Permission Denied');
			
				$use_conf = '';
				if(isset($_REQUEST['conf']))
					$use_conf = $_REQUEST['conf'];
			
				$template =& VoodooTemplate::getInstance();
				$template->setDir(ADMIN_TEMPLATES);
			
				$conf = VoodooIni::load('voodoo');
				$vars = array(
					'prepath'=>PATH_TO_DOCROOT,
					'controllers'=>array());
				foreach($conf['controllers'] as $controller => $enabled)
					$enabled && $vars['controllers'][] = array('name'=>$controller, 'selected'=>($use_conf==$controller)?' selected="selected" ':'');
			
				if($conf['controllers'][$use_conf]) {
					$vars['conf'] = $use_conf;
					$vars['configuration'] = VoodooIni::getContent($use_conf);
					
					
					if($this->hasRights($_SESSION['access'],'conf','modify')) {
						$vars['buttons'] = '<input type="submit" name="save" value="Save Configuration" />';
					}
				}
			
				return array('Configuration files', $template->parse('conf.modify',$vars));
			break;
			case 'Init': 
				$complete = false;
				$cnames = array();
				$controllers = $this->controller->voodooConf['controllers'];
				foreach($controllers as $controller => $enabled)
					$enabled && $cnames[] = ucfirst($controller);
				if(!$showCredentials||!isset($_POST['dbcredentials']))
				{
					$args['action'] = 'Init';
					$template =& VoodooTemplate::getInstance();
					$template->setDir(ADMIN_TEMPLATES);
					$output = ($showCredentials?$template->parse('credentials',$args):'').'<strong>SQL Output</strong><pre class="MonospaceFormat">';
					if(!$this->controller->voodooConf['engine']['site.setup'])
					{
						$obj = new VoodooSetup(false,$this->controller->conf);
						$obj->setup();
						$output .= $obj->displaySQL();
					}
					foreach($cnames as $cname)
						$output .= $this->controllerSetup($cname);
					return array('SQL Output For VOODOO',$output.'</pre>');
				}
				if(!$this->controller->voodooConf['engine']['site.setup'])
				{
					$obj = new VoodooSetup($_POST['dbcredentials'],$this->controller->conf);
					$complete || $complete = $obj->setup();
				}
				foreach($cnames as $cname)
					$this->controllerSetup($cname,$_POST['dbcredentials']);
				
				header('Location: '.PATH_TO_DOCROOT.($complete?'/':'/setup/CreateAdmin'));
				exit();
			break;
			case 'Controller':
				if(count($actionlist)!=2)
					exit('Incorrect Setup Of Controller');
				
				$cname = ucfirst(strtolower($actionlist[1]));
				if(!$showCredentials||!isset($_POST['dbcredentials']))
				{
					$args['action'] = 'Init';
					$template =& VoodooTemplate::getInstance();
					$template->setDir(ADMIN_TEMPLATES);
					$output = ($showCredentials?$template->parse('credentials',$args):'').'<strong>SQL Output</strong><pre class="MonospaceFormat">';
					return array('SQL Output For '.$cname,$output.$this->controllerSetup($cname).'</pre>');
				}
				$this->controllerSetup($cname,$_POST['dbcredentials']);
				header('Location: '.PATH_TO_DOCROOT.'/');
				exit();
			break;
		}
	}
	/**
	 * @access private
	 * @param string $cname // Controller name
	 * @param array $credentials
	 * @return string $sql
	 */
	function controllerSetup($cname,$credentials=false)
	{
		if(!is_file(SPELLBOOK.$cname.'/'.$cname.'Setup.php'))
			return '';
		require_once(SPELLBOOK.$cname.'/'.$cname.'Setup.php');
		
		$class = $cname.'Setup';
		$obj = new $class($credentials,$this->controller->conf);
		$obj->setup();
		return $obj->displaySQL();
	}
	/**
	 * Create new Admin users. 
	 * 
	 * The first Admin user created will be a God user. 
	 * TODO: get the highest ranked user from the engine.ini file and use that as first user.
	 * TODO: the ADMIN_ACCESSLEVEL constant should be dynamically assigned in VoodooController
	 */
	function createAdmin()
	{
		$db = $this->controller->DBConnect();
		$sql = "SELECT USER_ID FROM TBL_USER WHERE USER_ACCESSLEVEL >= ??";
		$q = $db->query($sql);
		$q->bind_values(ADMIN_ACCESSLEVEL);
		$q->execute();
		$firstAdmin = !(bool)$q->rows();
		if(!$firstAdmin&&!$this->hasRights($_SESSION['access'],'admin','create'))
			return array('Error',VoodooError::displayError('No Permission'));
		$template =& VoodooTemplate::getInstance();
		$template->setDir(WIKI_TEMPLATES);
		$args = array(
			'prepath'=>PATH_TO_DOCROOT,
			'loginpath'=>'setup/CreateAdmin'
			);
		if(!empty($_POST['handle']))
		{
			$user = new User($db);
			if($_POST['passwd']!=$_POST['passwd_verify'])
				$args['message'] = VoodooError::displayError('Passwords dont match');
			elseif(!$user->checkEmail($_POST['email']))
				$args['message'] = VoodooError::displayError('Passwords dont match');
			else
			{
				$user->name = $_POST['handle'];
				$user->password = md5($_POST['passwd']);
				$user->email = $_POST['email'];
				$rv = $this->controller->convertAccessLevel($firstAdmin?'God':'Admin');
				$user->accesslevel = array_pop($rv); 
				$user->insert();
				header(sprintf('Location: %s/setup/Login',PATH_TO_DOCROOT));
				exit();
			}
		}
		
		return array('Create New Admin User',$template->parse('wiki.register',$args));
	}
	
	function login()
	{
		require_once(WIKI_CLASSES.'WikiPotion.php');
		require_once(WIKI_SPELLBOOK.'Potions/WikiLogin.php');
		$mockFormatter = (object)array('db'=>$this->controller->DBConnect(),'handler'=>'setup','action'=>'Login');
		$wl = new WikiLogin($mockFormatter);
		return array('Login',$wl->display());
	}
}

?>
