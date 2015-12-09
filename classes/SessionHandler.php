<?php
/**
 * Added for loadbalanced/multi webserver purposes 
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage SessionHandler
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class SessionHandler
{
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * Where is the session data stored
	 * @var string $tbl
	 */
	var $tbl;
	/**
	 * @var mixed $crc
	 */
	var $crc = false;
	/**
	 * @var mixed $expiry
	 */
	var $expiry = false;
	/**
	 * Constructor
	 * @param string $tbl TableName where the session data is stored/retrieved from
	 */
	function SessionHandler($tbl='TBL_SESSION_DATA')
	{
		$conf = parse_ini_file(CONF.'engine.ini',true);
		require_once(CLASSES.'Database.php');
		$settings = $conf['database'];
		$connstring = $settings['driver'].":".$settings['server'].":".$settings['name'];
		$this->db = new Database($connstring,$settings['user'],$settings['password'],true);
		$this->tbl = $tbl;
	}
	function isReady($tbl='TBL_SESSION_DATA')
	{
		$sql = sprintf("DESC %s",$tbl);
		$q = $this->db->query($sql);
		$q->execute(true);
		return ((bool)$q->rows());
	}
	/**
	 * Do nothing
	 */
	function open($save_path,$session_name)
	{
		return true;
	}
	/**
	 * Do nothing
	 */
	function close()
	{
		return true;
	}
	/**
	 * Retrieves the session from the database
	 * @param string $id
	 * @return string $data
	 */
	function read($id)
	{
		$sql = sprintf("SELECT SQL_NO_CACHE expiry, data FROM %s WHERE id = ?? AND expiry >= ??",$this->tbl);
		$q = $this->db->query($sql);
		$q->bind_values(md5($id),time());
		if(!$q->execute())
			return false;
		
		if(!($r = $q->fetch()))
			return false;
			
		$this->expiry = $r->expiry;
		$this->crc = strlen($r->data).crc32($r->data);
		return $r->data;
	}
	/**
	 * Save the session to the database, create a new one in case non exists
	 * @param string $id
	 * @param string $data
	 * @return boolean
	 */
	function write($id,$data)
	{
		// Nothing has changed, dont update the BLOB
		if(($this->crc !== false) && ($this->crc === strlen($data).crc32($data)))
		{
			if($this->expiry !== false && $this->expiry - time() < 960) //if we expire within 16 mins
			{
				$sql = sprintf("UPDATE %s SET expiry = ?? WHERE id = ??",$this->tbl);
				$q = $this->db->query($sql);
				$q->bind_values((time() + ini_get('session.gc_maxlifetime')),md5($id));
				if(!$q->execute())
					return false;
			}
			return true;
		}
		$sql = sprintf("SELECT SQL_NO_CACHE id FROM %s WHERE id = ??",$this->tbl);
		$q = $this->db->query($sql);
		$q->bind_values(md5($id));
		if(!$q->execute())
			return false;
		
		if($r = $q->fetch())
		{
			$sql = sprintf("UPDATE %s SET expiry = ??, data = ?? WHERE id = ??",$this->tbl);
			$q = $this->db->query($sql);
			$q->bind_values(
				(time() + ini_get('session.gc_maxlifetime')),
				$data,
				md5($id)
				);
			if(!$q->execute())
				return false;
			return true;
		}
		
		$sql = sprintf("INSERT INTO %s (id, expiry, data) VALUES (??,??,??)",$this->tbl);
		$q = $this->db->query($sql);
		//echo $q->tmp_sql;
		$q->bind_values(
				md5($id),
				(time() + ini_get('session.gc_maxlifetime')),
				$data
				);
		if(!$q->execute())
			return false;
		
		return true;
	}
	/**
	 * Destroy the session by deleting it from the session table
	 * @param string $id
	 * @return bool
	 */
	function destroy($id)
	{
		$sql = sprintf("DELETE FROM %s WHERE id = ??",$this->tbl);
		$q = $this->db->query($sql);
		$q->bind_values(md5($id));
		if(!$q->execute())
			return false;
		return true;
	}
	/**
	 * Garbage Collector
	 * @return bool
	 */
	function gc()
	{
		$sql = sprintf("DELETE FROM %s WHERE expiry < ??",$this->tbl);
		$q = $this->db->query($sql);
		$q->bind_values(time());
		if(!$q->execute())
			return false;
		
		$sql = sprintf("OPTIMIZE TABLE %s",$this->tbl);
		$q = $this->db->query($sql);
		if(!$q->execute())
			return false;
		
		return true;	
	}
}

$session = new SessionHandler();
if($session->isReady()){
	session_set_save_handler(array(&$session,"open"),
                         array(&$session,"close"),
                         array(&$session,"read"),
                         array(&$session,"write"),
                         array(&$session,"destroy"),
                         array(&$session,"gc"));
}
session_start();
/**
CREATE TABLE `TBL_SESSION_DATA` (
	`id` CHAR(32) NOT NULL,
	`expiry` INT UNSIGNED NOT NULL DEFAULT 0,
	`data` TEXT NOT NULL,
	PRIMARY KEY (`id`)
)
**/

?>