<?php
/**
 * Display a login form within a wiki context
 * 
 * @deprecated
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 11-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiLogin extends WikiPotion
{
	/**
	 * @return string
	 */
	function init()
	{
		$r =& VoodooRegistry::getInstance();
		$template =& VoodooTemplate::getInstance();
		$template->setDir(WIKI_TEMPLATES);
		$vc =& $r->registry('VC');
	
		$temp = 'wiki.login';
		$args = array(
			'prepath'=>PATH_TO_DOCROOT,
			'loginpath'=>$this->formatter->handler.'/'.$this->formatter->action
			);
	
		
		if(isset($_POST['action'])&&($_POST['action']=='dologin')&&!empty($_POST['handle']))
		{	
			// Check success of the login action
			if($this->login($this->formatter->db,$_POST['handle'],$_POST['passwd']))
				return $this->display = VoodooError::displayError('Succesfully Logged in.');
			else
				$args['message'] = VoodooError::displayError('Incorrect Username and/or Password.');
		}
		elseif(isset($_GET['action'])&&($_GET['action']=='logout'))
			$this->logout();
		// Hey! We're already logged in
		// TODO: mkpretty
		if(isset($_SESSION['user_id'])&&($_SESSION['user_id']>0))
		{
			return $this->display = sprintf('You are already logged in. <a href="%s/%s/%s?action=logout">Logout</a>',
				PATH_TO_DOCROOT,
				$this->formatter->handler,
				$this->formatter->action
				);
		}
		// Parse the login screen from the template
		return $this->display = $template->parse($temp,$args);
	}
	/**
	 * Verify credentials and set the right session variables
	 * @param Database $db
	 * @param string $handle (user name)
	 * @param string $passwd
	 * @return bool
	 */
	function login($db,$handle, $passwd)
	{
		require_once(CLASSES.'User.php');
		$user = new User($db);
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
	
	function logout()
	{
		session_destroy();
		header('Location: '.PATH_TO_DOCROOT.'/');
		exit();
	}
}

?>