<?php
/**
 * The session handling object of Project Voodoo
 * 
 * The session handling object of Project Voodoo. Validates, sets and unset the session variables.
 * TODO: remove all ChatController (metatron) specific session variables.
 * 
 * @author Marten Koedam <marten@dalines.com>
 * @package VoodooCore
 * @subpackage VoodooSession
 * @license www.dalines.org/license
 * @copyright 2006-2007, Dalines Software Library
 */
class VoodooSession
{
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * Auto initializes the session 
	 * @param Database $db
	 */
	function VoodooSession($db)
	{
		$this->db = $db;
		$this->init();
	}
	/**
	 * Initializes the Session.
	 * 
	 * Initializes the Session. If the Session has already been started before, 
	 * the variables will be validated to prevent Session HiJacking
	 * @return bool
	 */
	function init()
	{
		if(!isset($_SESSION['user_id']))
			return $this->_setDefault();
		return $this->_validate();
	}
	/**#@+
	 * @access private
	 */
	/**
	 * Set the default session variables by selecting user with ID = 0, World User
	 * @return bool
	 */
	function _setDefault()
	{
		$q = $this->_selectUser(0);
		if(!$r = $q->fetch())
			return false;
		$this->_setSession($r);
		return true;
	}
	/**
	 * Validates the current variables
	 * @todo set all the variables that are not changable ($_SESSION['access'])
	 * @return bool
	 */
	function _validate()
	{
		return true;
		$q = $this->_selectUser($_SESSION['user_id']);
		if(!$r = $q->fetch())
			return $this->_setDefault();
		if($r->USER_PASSWORD != $_SESSION['user_pwd'])
			return $this->_setDefault();
		$this->_setSession($r);
		return true;
	}
	/**
	 * Selects the user information for $userId
	 * @param int $userId
	 * @return Resultset $q
	 */
	function _selectUser($userId)
	{
		$sql = "SELECT USER_ID, USER_NAME, USER_PASSWORD, 
			USER_ACCESSLEVEL, USER_COLOR, USER_IMG
			FROM TBL_USER 
			WHERE USER_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values(array($userId));
		$q->execute();
		return $q;
	}
	/**
	 * Sets session variables from a resultset
	 * @param Resultset $r
	 */
	function _setSession($r)
	{
		$_SESSION['user_id'] = $r->USER_ID;
		$_SESSION['user_name'] = $r->USER_NAME;
		$_SESSION['user_pwd'] = $r->USER_PASSWORD;
		$_SESSION['access'] = $r->USER_ACCESSLEVEL;
		$_SESSION['color'] = $r->USER_COLOR;
		$_SESSION['img'] = ($r->USER_IMG===0)?'':$r->USER_IMG;
		$_SESSION['emoticons'] = isset($_SESSION['emoticons'])?$_SESSION['emoticons']:false;
		// TODO: remove this entry
		$_SESSION['observations'] = isset($_SESSION['observations'])?$_SESSION['observations']:array();
	}
	/**#@-*/
}
?>