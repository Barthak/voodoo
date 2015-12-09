<?php
/**
 * TODO: make work with other DB backends.
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage Database
 * @since 18-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class Database
{
	/**
	 * What Database engine are we using
	 * @var string $driver
	 */
	var $driver;
	/**
	 * @var string $host
	 */
	var $host;
	/**
	 * The name of the database to which we connect.
	 * @var string $name
	 */
	var $name;
	/**
	 * The Database driver object
	 * @var Driver $dbd
	 */
	var $dbd;
	/**
	 * The connection to the database
	 * @var Object $connection
	 */
	var $connection;
	/**
	 * Constructor
	 * @param string $connstr (driver:host:dbname)
	 * @param string $user
	 * @param string $passwd
	 * @param bool $persistent
	 */
	function Database($connstr='::',$user='',$passwd='',$persistent=true)
	{
		$this->_init($connstr);
		$driver = $this->driver.'Driver';
		$this->dbd = new $driver($this->name,$this->host,$user,$passwd,$persistent);
		$this->connection =& $this->dbd->connection;
	}
	/**
	 * Gets the different types of info from the connection string
	 */
	function _init($connstr)
	{
		if (ereg ("^([^:]+):([^:]*):(.+)$", $connstr, $regs))
		{
			$this->driver = $regs[1];
			$this->host = str_replace("|",":",$regs[2]);
			$this->name = $regs[3];
		}
		else
			trigger_error('Not a valid Connection String', E_USER_ERROR);
	}
	/**
	 * Creates a new Query object and returns it for usage.
	 * @param string $sql
	 * @return Query $q
	 */
	function query($sql='')
	{
		$classname = $this->driver."Query";
		$q = new $classname($sql, $this);
		return $q;
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage Database
 * @since 18-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class Query
{
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @var string $sql
	 */
	var $sql;
	/**
	 * @var Resource $result
	 */
	var $result;
	/**
	 * @param string $sql
	 * @param Database &$db
	 */
	function Query($sql,&$db)
	{
		$this->sql = $sql;
		$this->tmp_sql = $sql;
		$this->db =& $db;
		$this->connection = $db->connection;
	}
	/**
	 * Replaces ?? in sql statements with supplied arguments. 
	 * @param mixed $args
	 */
	function bind_values()
	{
		if (func_num_args() == 0)
            trigger_error('SQL::addWhereText() - Need atleast one argument (array with values or string1, string2, ...) ', E_USER_ERROR);

        $this->tmp_sql = $this->sql;
        $replacements = array();

		// if it is an array, loop through all the arguments 
		// (backwards compatibility with the Simian Systems database object)
        if(is_array(func_get_arg(0)))
            foreach(func_get_arg(0) as $el)
				$replacements[] = "'" . mysql_escape_string($el) . "'"; // escape the string
        else 
            for($i=0; $i < func_num_args(); $i++) // loop all arguments
				$replacements[] = "'" . mysql_escape_string(func_get_arg($i)) . "'";

        $offset = 0; // Loop through the SQL and replace all the ?? with their escaped values.
        foreach($replacements as $replacement)
        {
			$pos = strpos($this->tmp_sql, '??', $offset);
			if ($pos === false)
			        break;
			
			$this->tmp_sql = substr_replace($this->tmp_sql, $replacement, $pos, 2); // 2 = strlen('??')
			$offset = $pos + strlen($replacement);
        }
        return $this->result;
	}
	/**
	 * Reset the internal variables
	 */
	function clear()
	{
		$this->sql = ""; 
		$this->result = ""; 
		$this->tmp_sql = ""; 
		$this->field = ""; 
	}
	
	function begin()
	{
		
	}
	function commit()
	{
		
	}
	function rollback()
	{
		
	}
}
/**
 * The MySQL database driver
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage Database
 * @since 18-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class MySQLDriver
{
	/**
	 * The Database name
	 * @var string $name
	 */
	var $name;
	/**
	 * @var string $host
	 */
	var $host;
	/**
	 * @var string $user
	 */
	var $user;
	/**
	 * @var string $passwd
	 */
	var $passwd;
	/**
	 * @var bool $persistent
	 */
	var $persistent;
	/**
	 * @param string $name
	 * @param string $host
	 * @param string $user
	 * @param string $passwd
	 * @param bool $persistent 
	 */
	function MySQLDriver($name,$host,$user,$passwd,$persistent)
	{
		$this->name = $name;
		$this->host = $host;
		$this->user = $user;
		$this->passwd = $passwd;
		$this->persistent = $persistent;
		$this->connect();
	}
	/**
	 * Set up the connection and select the database
	 */
	function connect()
	{
		$connect = 'mysql_'.($this->persistent?'p':'').'connect';
		if(!($this->connection = @$connect($this->host, $this->user, $this->passwd, true)))
			exit('Unable to connect to MySQL server');
		if(!@mysql_select_db($this->name, $this->connection))
			exit('Unable to select the MySQL database');
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage Database
 * @since 18-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class MySQLQuery extends Query
{
	/**
	 * Execute the query and returns the result.
	 * @param bool $dontDieOnMe
	 */
	function execute($dontDieOnMe=false)
	{
		if(!($this->result = mysql_query($this->tmp_sql, $this->connection)))
		{
			$error = mysql_error($this->connection);
			$errno = mysql_errno($this->connection);
			if($dontDieOnMe)
				return false;
			else
				trigger_error($error.' ('.$errno.')'." : ".$this->tmp_sql . ' ; on '.$this->db->name." at " .  getenv("REQUEST_URI"), E_USER_ERROR);
		}
		return $this->result;
	}
	function affected_rows() {
		return mysql_affected_rows($this->connection);
	}
	/**
	 * Returns the number of rows included in the query (select)
	 * @return int
	 */
	function rows()
	{
		if($this->result)
			return mysql_num_rows($this->result);
		return 0;
	}
	/**
	 * Fetches the data and puts it in an associative array
	 * @return int|array 
	 */
	function fetchAssoc()
	{
		if($this->result)
			return mysql_fetch_assoc($this->result);
		return 0;
	}
	/**
	 * Fetches the data and puts it in an object
	 * @return int|object
	 */
	function fetch()
	{
		if($this->result)
			return mysql_fetch_object($this->result);
		return 0;
	}
	/**
	 * Fetches the data and puts it in an array
	 * @return int|array
	 */
	function fetch_array()
	{
		if($this->result)
			return mysql_fetch_array($this->result);
		return 0;
	}
	/**
	 * Fetches the last insert id (AUTO_INCREMENT fields)
	 * @return int
	 */
	function lastid()
	{
		if($this->result)
			return mysql_insert_id($this->connection);
		return 0;
	}
}
?>