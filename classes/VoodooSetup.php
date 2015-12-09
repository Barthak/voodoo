<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooSetup
 * @since 12-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooDefaultSetup
{
	/**
	 * @var array $conf
	 */
	var $conf;
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @var bool $displayonly
	 */
	var $displayonly = true;
	/**
	 * @var string $display
	 */
	var $display = "";
	/**
	 * @param array $credentials (user,password)
	 * @param array $conf
	 */
	function VoodooDefaultSetup($credentials,$conf)
	{
		$this->conf = $conf;
		$this->db = $this->DBConnect($credentials);
		// We have credentials, turn of displayonly. Scary!!
		$credentials && $this->displayonly = false;
	}
	/**
	 * Connect to the database, use engine.ini for this.
	 * 
	 * We use the default (select,insert,update,delete privs) user from the engine.ini file.
	 * In case credentials are provided, we use the user from there (create,drop,alter privs)
	 * @param bool|array $credentials
	 * @return Database
	 */
	function DBConnect($credentials)
	{
		require_once(CLASSES.'Database.php');
		$settings = $this->conf['database'];
		$connstring = $settings['driver'].":".$settings['server'].":".$settings['name'];
		if($credentials) 
			return new Database($connstring,$credentials['user'],$credentials['password'],true);
		// Always use db connection
		return new Database($connstring,$settings['user'],$settings['password'],true);
	}
	/**
	 * Run the setup, create the tables and insert the default data.
	 * 
	 * If $defaultDataOnly
	 * 
	 * @param bool $defaultDataOnly
	 */
	function setup($defaultDataOnly=false)
	{
		//if($defaultDataOnly)
			//return $this->insertDefaultData();
		if(!$this->verify())
			return true;
		$this->createTables();
		$this->insertDefaultData();
		return false;
	}
	/**
	 * Verify if the createTables or the insertDefaultData have already ran before.
	 * @return bool
	 */
	function verify()
	{
		return true;
	}
	/**
	 * Checks to see whether or not a table already exists
	 * @param string $tbl
	 * @param bool|Database $db
	 * @return bool
	 */
	function verifyTable($tbl,$db=false)
	{
		$db || $db = $this->db;
		$sql = sprintf("DESC %s",$tbl);
		$q = $db->query($sql);
		$q->execute(true);
		return (!(bool)$q->rows());
	}
	/**
	 * Default function call, does nothing
	 */
	function createTables()
	{
		
	}
	/**
	 * Default function call, does nothing
	 */
	function insertDefaultData()
	{
		
	}
	/**
	 * Executes the queries provided, unless displayonly is true
	 * @param array $queries
	 */
	function execute($queries=array())
	{
		foreach($queries as $query)
		{
			$values = array();
			is_array($query) && list($query,$values) = $query;
			$q = $this->db->query($query);
			$values && $q->bind_values($values);
			$this->display .= "\n\r\n\r".$q->tmp_sql.';';
			$this->displayonly || $q->execute();
		}
	}
	/**
	 * Display all the SQL that was (or not) executed
	 * @return string
	 */
	function displaySQL()
	{
		return $this->display;
	}
	
	function getCreateTablesFromAbstractObjects($objects)
	{
		$tables = array();
		foreach($objects as $class => $values)
		{
			list($dir,$file) = $values;
			require_once($dir.$file);
			$obj = new $class($this->db);
			if($this->verifyTable($obj->getTable()))
				if($table = $obj->generateCreateTables())
					$tables[] = $table;
		}
		return $tables;
	}
	
	function update()
	{
		return false;
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooSetup
 * @since 12-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooSetup extends VoodooDefaultSetup
{
	function verify()
	{
		// If any table exists, we shouldnt execute the setup
		$sql = "SHOW TABLES";
		$q = $this->db->query($sql);
		$q->execute(true);
		return (!(bool)$q->rows());
	}
	/**
	 * 
	 */
	function createTables()
	{
		$objects = array('User'=>array(CLASSES,'User.php'));
		$tables = $this->getCreateTablesFromAbstractObjects($objects);
		// TBL_SESSION_DATA
		$tables[] = "CREATE TABLE `TBL_SESSION_DATA` (
				`id` CHAR(32) NOT NULL,
				`expiry` INT UNSIGNED NOT NULL DEFAULT 0,
				`data` TEXT NOT NULL,
				PRIMARY KEY (`id`)
			)";
		$this->execute($tables);
	}
	/**
	 * 
	 */
	function insertDefaultData()
	{
		$data = array();
		// Default system and no name users..
		// TODO: Make prettier than this.
		$data[] = "INSERT INTO `TBL_USER` VALUES (-1,'system','','',0,0,'000000',0,''),(0,'no name','','',0,0,'000000',0,'')";
		$this->execute($data);
	}
}
?>
