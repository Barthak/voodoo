<?php
/**
 * auth/login
 * auth/logout
 * auth/signup
 * auth/lostpassword
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package AuthController
 * @subpackage AuthController
 * @since 5-nov-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AuthController extends DefaultController
{
	var $dispatchers = array();
	
	function AuthController($dispatcher,$actionlist,$route=true)
	{
		$this->init();
		$route && $this->route($dispatcher,$actionlist);
	}
	
	function init()
	{
		$this->DefaultController();
		
		$this->db = $this->DBConnect();
		$this->dispatchers['auth'] = new AuthDispatcher($this);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AuthController
 * @subpackage AuthController
 * @since 5-nov-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AuthDispatcher
{
	var $controller;
	function AuthDispatcher(&$controller)
	{
		$this->controller =& $controller;
	}
	
	function dispatch($actionlist=array())
	{
		$action = isset($actionlist[0])?$actionlist[0]:'login'; 
		switch($action)
		{
			case 'login':
				$au = new AuthLogin($this);
				return $au->execute();
			break;
			case 'logout':
				session_destroy();
				header(sprintf('Location: %s/',PATH_TO_DOCROOT));
				exit();
			break;
			case 'register':
				$as = new AuthRegister($this);
				return $as->execute();
			break;
			case 'lostpassword':
			break;
			case 'settings':
				$as = new AuthSettings($this);
				return $as->execute();
			break;
		}
		return array('Auth Error',VoodooError::displayError('Permission Denied, Incorrect Dispatcher Supplied'));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AuthController
 * @subpackage AuthController
 * @since 5-nov-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AuthObject
{
	var $dispatcher;
	var $template;
	
	function AuthObject(&$dispatcher)
	{
		$this->dispatcher =& $dispatcher;
		$this->db = $dispatcher->controller->DBConnect();
		$this->template =& VoodooTemplate::getInstance();
		$this->template->setDir(AUTH_TEMPLATES);
	}	
}
/**
 * Question: Should users who are logged in be able to relog in as a different (or the same) user?
 * 
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package AuthController
 * @subpackage AuthController
 * @since 5-nov-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AuthLogin extends AuthObject
{
	function execute()
	{
		$args = array('prepath'=>PATH_TO_DOCROOT);
		$logoutlink = sprintf('<a href="%s/auth/logout">logout</a>',PATH_TO_DOCROOT);
		$settingslink = sprintf('<a href="%s/auth/settings">settings</a>',PATH_TO_DOCROOT);
		
		$text = sprintf('You are already logged in. You can update your %s, or %s',
				$settingslink, $logoutlink);
		
		if($_SESSION['user_id']>0)
			return array('Error',VoodooError::displayError($text));
		if(isset($_POST['action'])&&($_POST['action']=='dologin')&&!empty($_POST['handle']))
		{	
			// Check success of the login action
			if($this->login($_POST['handle'],$_POST['passwd']))
				return array('Login Succesful', VoodooError::displayError('Succesfully Logged in.'));
			else
				$args['message'] = VoodooError::displayError('Incorrect Username and/or Password.');
		}
		return array('Login',$this->template->parse('login',$args));
	}
	/**
	 * Verify credentials and set the right session variables
	 * @param Database $db
	 * @param string $handle (user name)
	 * @param string $passwd
	 * @return bool
	 */
	function login($handle, $passwd)
	{
		require_once(CLASSES.'User.php');
		$user = new User($this->db);
		$handle = str_replace(' ','',$handle);
		$rv = false;
		if($user->login($handle,$passwd))
		{
			$_SESSION['user_pwd'] = $user->password;
			$rv = true;
		}
		else // Login failed, set the default user
			$user->set(0);
		$_SESSION['user_id'] = $user->id;
		$_SESSION['user_name'] = $user->name;
		$_SESSION['access'] = $user->accesslevel;
		return $rv;
	}
}

class AuthSettings extends AuthObject
{
	function execute()
	{
		$message = '';
		if($_SESSION['user_id']<=0)
			return array('Error',VoodooError::displayError('Please Register First'));
		if(isset($_POST['action']))
		{
			if(!($failure = $this->updateSettings()))
				$message = VoodooError::displayError('Succesfully Updated `'.$_POST['handle'].'`.');
			else
				$message = VoodooError::displayError(sprintf('Update failed: %s',$failure));
		}
		$user = new User($this->db, $_SESSION['user_id'], true);
		
		$args = array(
			'prepath'=>PATH_TO_DOCROOT,
			'username'=>$user->name,
			'email'=>$user->email,
			'link'=>$user->userlink,
			'color'=>$user->color,
			'avatar'=>$user->img,
			'message'=>$message
			);
		
		return array('User settings',$this->template->parse('settings',$args));
	}
	
	function updateSettings()
	{
		$user = new User($this->db, $_SESSION['user_id'], true);
		$handle = htmlentities($_POST['handle']);
		$handle = str_replace(' ','',$handle);
		$email = $_POST['email'];
		$passwd = $_POST['passwd'];
		if($user->password != md5($_POST['current_passwd']))
			return 'Incorrect Password supplied';
		if(!empty($passwd)&&($passwd != $_POST['passwd_verify']))
			return 'Your new password does not match verification password.';
		if($email && !$user->checkEmail($email))
			return 'Incorrect Email Address Supplied';
		if($handle!=$user->name)
		{
			$user->name = $handle;
			if(!$user->isUniqueName()) // The username should be unique
				return 'Unable to update ['.$handle.'], this handle is already registered';
		}
		$user->email = $email;
		if(!empty($passwd))
			$user->password = md5($passwd);
		$user->update();
		
		$_SESSION['user_id'] = $user->id;
		$_SESSION['user_name'] = $user->name;
		$_SESSION['user_pwd'] = $user->password;
		$_SESSION['access'] = $user->accesslevel;
		return false;
	}
}

class AuthRegister extends AuthObject
{
	function execute()
	{
		$args = array('prepath'=>PATH_TO_DOCROOT);
		if(isset($_POST['action'])&&($_POST['action']=='doregister')&&!empty($_POST['handle']))
		{	
			// We do not have a failure! Happy Time!
			if(!($failure = $this->registerSuccesful()))
				return array('Registration Succesful', VoodooError::displayError('Succesfully Registered `'.$_POST['handle'].'`.'));
			else
				$args['message'] = VoodooError::displayError(sprintf('Registration failed: %s',$failure));
		}
		$args['loginpath'] = 'auth/register';
		return array('Register Here',$this->template->parse('register',$args));
	}
	/**
	 * @param Database $db
	 * @return bool|string
	 */
	function registerSuccesful()
	{
		$handle = htmlentities($_POST['handle']);
		$handle = str_replace(' ','',$handle);
		$email = $_POST['email'];
		$passwd = $_POST['passwd'];
		if($passwd != $_POST['passwd_verify'])
			return 'Password does not match verification password.';
		
		$user = new User($this->db,array('name'=>$handle,'password'=>md5($passwd),'accesslevel'=>30,'email'=>$email));
		if($email && !$user->checkEmail($email))
			return 'Incorrect Email Address Supplied';
		if(!$user->isUniqueName()) // The username should be unique
			return 'Unable to register ['.$handle.'], this handle is already registered';
		
		$user->insert();
			
		$_SESSION['user_id'] = $user->id;
		$_SESSION['user_name'] = $user->name;
		$_SESSION['user_pwd'] = $user->password;
		$_SESSION['access'] = $user->accesslevel;
		return false;
	}
}
?>
