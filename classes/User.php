<?php
require_once(CLASSES.'AbstractObject.php');
/**
 * Manages users
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage User
 * @license www.dalines.org/license
 * @copyright 2006-2007, Dalines Software Library
 */
class User extends AbstractObject
{
	var $id;
	var $name;
	var $password;
	var $email;
	var $accesslevel;
	var $color;
	var $img;
	var $gender;
	var $userlink;
	
	function set($id=null)
	{
		$id || $id = $this->id;
		$sql = "SELECT USER_ID,USER_NAME, USER_EMAIL, USER_ACCESSLEVEL, USER_PASSWORD,
				USER_COLOR, USER_IMG, USER_GENDER, USER_LINK
			FROM TBL_USER WHERE USER_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		if(!$q->rows())
			return false;
			
		$r = $q->fetch();
		$this->id = $r->USER_ID;
		$this->name = $r->USER_NAME;
		$this->password = $r->USER_PASSWORD;
		$this->email = $r->USER_EMAIL;
		$this->accesslevel = $r->USER_ACCESSLEVEL;
		$this->color = $r->USER_COLOR;
		$this->img = $r->USER_IMG;
		$this->gender = $r->USER_GENDER;
		$this->userlink = $r->USER_LINK;
			
		return $this->complete = true;
	}
	
	function setUserByName($name)
	{
		$sql = "SELECT USER_ID,USER_NAME, USER_EMAIL, USER_ACCESSLEVEL, USER_PASSWORD,
				USER_COLOR, USER_IMG, USER_GENDER, USER_LINK
			FROM TBL_USER WHERE BINARY USER_NAME = ??";
		$q = $this->db->query($sql);
		$q->bind_values($name);
		$q->execute();
		if(!$q->rows())
			return false;
			
		$r = $q->fetch();
		$this->id = $r->USER_ID;
		$this->name = $r->USER_NAME;
		$this->password = $r->USER_PASSWORD;
		$this->email = $r->USER_EMAIL;
		$this->accesslevel = $r->USER_ACCESSLEVEL;
		$this->color = $r->USER_COLOR;
		$this->img = $r->USER_IMG;
		$this->gender = $r->USER_GENDER;
		$this->userlink = $r->USER_LINK;
			
		return $this->complete = true;
	}
	/**
	 * Login a user with $name and $passwd
	 * @param string $name
	 * @param string $passwd
	 * @return bool
	 */
	function login($name,$passwd)
	{
		$sql = "SELECT USER_ID, USER_PASSWORD as PWD 
			FROM TBL_USER 
			WHERE USER_NAME = ??
			ORDER BY USER_PASSWORD DESC";
		$q = $this->db->query($sql);
		$q->bind_values($name);
		$q->execute();
		if(!$q->rows())
			return false;
		$r = $q->fetch();
		if(empty($r->PWD))
			return false;
		if($r->PWD != md5($passwd))
			return false;
		return $this->set($r->USER_ID);
	}
	/**
	 * Inserts a new user
	 * @return int $id
	 */
	function insert()
	{
		$this->id = $this->_selectNewId();
		parent::insert();
		return $this->id;
	}
	/**
	 * Checks if the $name is unique
	 * @param string $name
	 * @return bool
	 */
	function isUniqueName($name='')
	{
		$name || $name = $this->name;
		$sql = "SELECT USER_PASSWORD FROM TBL_USER WHERE USER_NAME = ??";
		$q = $this->db->query($sql);
		$q->bind_values($name,'');
		$q->execute();
		if(!$q->rows())
			return true;
		$return = true;
		while($r = $q->fetch())
			if(!empty($r->USER_PASSWORD))
				$return = false;
		return $return;
	}
	/**#@+
	 * @access private
	 */
	/**
	 * Selects a new available ID for the user
	 * @return int
	 */
	function _selectNewId()
	{
		$sql = "SELECT MAX(USER_ID) as MAXID FROM TBL_USER";
		$q = $this->db->query($sql);
		$q->execute();
		if(!$q->rows())
			return 1;
		$r = $q->fetch();
		return (int) $r->MAXID+1;
	}
	/**
	 * Validates the email address
	 * TODO: mkpretty regex
	 * 
	 * @param string $email
	 * @return bool
	 */
	function checkEmail( $email )
	{
		if(!eregi('^[a-zA-Z0-9._-]+@[a-zA-Z0-9._-]+', $email))
			return false;
		return true;
	}
	/**
	 * 
	 */
	function getUserOverview()
	{
		$sql = "SELECT USER_ID, USER_NAME, USER_EMAIL, USER_ACCESSLEVEL, USER_COLOR, USER_IMG, USER_GENDER, USER_LINK
			FROM TBL_USER 
			ORDER BY USER_ID";
		$q = $this->db->query($sql);
		$q->execute();
		return $q;
	}
	/**
	 * Initializes the objectmap as used by AbstractObject
	 */
	function _initObjectMaps()
	{
		$this->objectmap['dbtable'] = 'TBL_USER';
		$this->objectmap['primary']['id'] = 'USER_ID';
		$this->objectmap['properties']['name'] = 'USER_NAME';
		$this->objectmap['properties']['password'] = 'USER_PASSWORD';
		$this->objectmap['properties']['email'] = 'USER_EMAIL';
		$this->objectmap['properties']['accesslevel'] = 'USER_ACCESSLEVEL';
		$this->objectmap['properties']['img'] = 'USER_IMG';
		$this->objectmap['properties']['color'] = 'USER_COLOR';
		$this->objectmap['properties']['gender'] = 'USER_GENDER';
		$this->objectmap['properties']['link'] = 'USER_LINK';
		
		$this->sqltypemap['id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['name'] = 'VARCHAR(64) NOT NULL';
		$this->sqltypemap['password'] = "VARCHAR(32) NOT NULL DEFAULT ''";
		$this->sqltypemap['email'] = 'VARCHAR(255) DEFAULT NULL';
		$this->sqltypemap['accesslevel'] = 'INT(11) NOT NULL';
		$this->sqltypemap['img'] = "VARCHAR(255) NOT NULL DEFAULT ''";
		$this->sqltypemap['color'] = "VARCHAR(255) NOT NULL DEFAULT ''";
		$this->sqltypemap['gender'] = "TINYINT(4) NOT NULL DEFAULT 0";
		$this->sqltypemap['link'] = "VARCHAR(255) NOT NULL DEFAULT ''";
		
		$this->sqlprops['index'] = 'INDEX(`USER_NAME`)';
	}
	/**#@-*/
}

define('USER_GENDER_UNDISCLOSED',0);
define('USER_GENDER_MALE',1);
define('USER_GENDER_FEMALE',2);
?>