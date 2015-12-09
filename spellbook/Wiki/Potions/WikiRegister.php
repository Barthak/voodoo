<?php
/**
 * @deprecated
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 23-jun-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiRegister extends WikiPotion
{
	/**
	 * 
	 */
	function init()
	{
		$r =& VoodooRegistry::getInstance();
		$template =& VoodooTemplate::getInstance();
		$template->setDir(WIKI_TEMPLATES);
		$vc =& $r->registry('VC');
		
		$temp = 'wiki.register';
		$args = array(
			'prepath'=>PATH_TO_DOCROOT,
			'loginpath'=>$this->formatter->handler.'/'.$this->formatter->action
			);
	
		if(isset($_POST['action'])&&($_POST['action']=='doregister')&&!empty($_POST['handle']))
		{	
			// We do not have a failure! Happy Time!
			if(!($failure = $this->register($this->formatter->db)))
				return $this->display = VoodooError::displayError('Succesfully Registered `'.$_POST['handle'].'`.');
			else
				$args['message'] = VoodooError::displayError(sprintf('Registration failed: %s',$failure));
		}
	
		if($_SESSION['user_id']>0)
			return $this->display = 'You are already registered.';
	
		return $this->display = $template->parse($temp,$args);
	}
	/**
	 * Returns false incase it went correct (?) pretty weird
	 * 
	 * TODO: make work with WikiPotion::setError();
	 * 
	 * @param Database $db
	 * @return bool|string
	 */
	function register($db)
	{
		$handle = htmlentities($_POST['handle']);
		$handle = str_replace(' ','',$handle);
		$email = $_POST['email'];
		$passwd = $_POST['passwd'];
		if($passwd != $_POST['passwd_verify'])
			return 'Password does not match verification password.';
		
		$user = new User($db,array('name'=>$handle,'password'=>md5($passwd),'accesslevel'=>30,'email'=>$email));
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
