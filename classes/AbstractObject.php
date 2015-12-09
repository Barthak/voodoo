<?php
/**
 * Abstract VoodooCore Class
 * Use for DB related Objects.
 * @package VoodooCore
 * @subpackage AbstractObject
 * @author Chris Wesseling <chris.wesseling@xs4all.nl>
 * @author Marten Koedam <marten@dalines.net>
 * @abstract
 */
class AbstractObject
{
	/**
	 * @var Query last (failed) Query executed. Inspect $lastQuery->exception and $lastQuery->tmp_sql for details.
	 */
	var $lastQuery = null;
	var $lastError = null;
	
	/**
	 * Constructor
	 * @param Database $db
	 * @param mixed $preset int (id) or array ('property'=> value);
	 * @param boolean $set call {@link set()}?
	 */
	function AbstractObject(&$db, $preset=null, $set=false)
	{
		$this->db = &$db;
		$this->complete = false;
		if ( is_numeric($preset) )
			$this->id = (int) $preset;
		elseif ( is_array($preset) )
			$this->_preset($preset);
		$this->_initObjectMaps();
		if ( $set )
			$this->set();
	}
	/**
	 * Set the object from DB
	 * @param mixed $key int (id) or array ('property'=> value);
	 */
	function set($key=null)
	{
		if ( is_numeric($key) )
			$this->id = (int) $key;
		elseif ( is_array($key) )
			$this->_preset($key);

		$values = array();
		$sql = "SELECT * FROM `".$this->objectmap['dbtable']."`";
		$this->_appendWhereAllPrimaries($sql, $values);
		$q = $this->db->query($sql);
		$q->bind_values($values);
		$q->execute();
		if ( $r = $q->fetch() )
		{
			foreach ( array('primary', 'properties') as $proptype )
				foreach ( $this->objectmap[$proptype] as $prop=>$field )
				{
					$this->$prop = $r->$field;
					if ( isset($this->typemap[$prop]) && !is_null($this->$prop) )
						settype($this->$prop, $this->typemap[$prop]);
				}
			return $this->complete = true;
		}
		return false;
	}
	/**
	 * Alter the object properties with stuff from the array/object
	 *
	 * You can't unset properties this way, but you can set them to null or '';
	 * @param mixed array('property'=>value) or object
	 */
	function alter($settings)
	{
		$this->_preset($settings);
	}
	/**
	 * Insert the object in the DB as a new
	 * @param bool $graceful Don't die with a Fatal, but return false and set {@link $this->lastQuery}
	 * @return bool
	 */
	function insert($graceful=false)
	{
		do
		{
			isset($this->objectmap['inserttype']) || $unsetInsertType = $this->objectmap['inserttype'] = 'INSERT'; //could be set to REPLACE

			if ( !(@list($q, $id) = $this->_buildIntoSetQuery($this->objectmap['inserttype'])) )
				continue; //CONSTRAINT broken _nextTable

			if ( $q->execute($graceful) )
			{
				if ( $id && empty($this->$id) && $q->lastid() )
					$this->$id = $q->lastid();
				$this->complete = true;
			}
			else
			{
				$this->lastQuery = &$q;
				$this->setLastError($q->exception);
				return false; //if _nextTable.. DB is in a filthy state!! But hey, isn't that the fun of MySQL
			}
		} while ( $this->_nextTable() );
		//reset state to original. so Unittests get what they expect...
		if ( isset($unsetInsertType) )
			unset($this->objectmap['inserttype']);
		unset($this->objectmap0);
		return $this->complete;
	}
	/**
	 * Updates the database 
	 */
	function update()
	{
		$rv = true;
		do
		{
			$values = array();
			if ( isset($this->objectmap['updatetype']) && $this->objectmap['updatetype'] == 'REPLACE' )
			{
				//TRY BUILD REPLACE QUERY
				if ( !(@list($q, $id) = $this->_buildIntoSetQuery('REPLACE')) )
					continue; //CONSTRAINT broken _nextTable
			}
			else
			{ // BUILD UPDATE QUERY
				$sql = 'UPDATE `'.$this->objectmap['dbtable'].'`';
				$values = array();
				if ( !$this->_appendSet($sql, $values) )
					continue; //constraint broken or no values set
				$this->_appendWhereAllPrimaries($sql, $values);
				$q = $this->db->query($sql);
				$q->bind_values($values);
			}
			// EXECUTE
			$this->lastQuery =& $q;
			$rv &= $q->execute();
		} while ( $this->_nextTable() );
		unset($this->objectmap0); //return state to same before call. for Unittests
		return $rv;
	}
	/**
	 * Deletes element from Database
	 * @return boolean
	 */
	function delete()
	{
		$refs = array();
		if ( !empty($this->referencemap) )
			$refs = $this->_checkReferences();
		if ( $refs === false )
			return false;
		//DELETE direct object stuff
		do
		{
			$values = array();
			$sql = 'DELETE FROM `'.$this->objectmap['dbtable'].'`';
			$this->_appendWhereAllPrimaries($sql, $values);
			$q = $this->db->query($sql);
			$this->lastQuery =& $q;
			$q->bind_values($values);
			$rv = $q->execute();
		} while ( $this->_nextTable() );

		//delete ondelete cascade references
		foreach ( $refs as $sql )
		{
			$q = $this->db->query($sql);
			$q->execute();
		}

		return $rv;
	}
	/**
	 * @param object
	 * @return string Property which contains the parent represented by $parent
	 */
	function getParentProperty($parent)
	{
		if ( isset($this->familymap['parent'][get_class($parent)]) )
			return $this->familymap['parent'][get_class($parent)];
	}
	/**
	 * @return bool
	 */
	function isComplete()
	{
		return $this->complete;
	}
	/**
	 * Set properties from array
	 * @param array $props ('property' => value)
	 */
	function setProperties($props)
	{
		return $this->_preset($props);
	}
	/**
	 * @access protected
	 */
	function getLastError()
	{
		return $this->lastError;
	}	 
	/**
	 * @access protected
	 */
	function setLastError($sErr)
	{
		$this->lastError = $sErr;
	}
	/**#@+
	 * @access private
	 */
	/**
	 * Set properties from array
	 * @param array $props ('property' => value)
	 */
	function _preset($props)
	{
		foreach ( $props as $prop=>$val )
			eval('$this->'.$prop.' = $val;');
	}
	/**
	 * Set map to next table if appliccable
	 * 
	 * You should always cycle to call this function untill it returns false (and sets the first map again).
	 * Call this with reset true.
	 * Or it will render the map in an unpredictable state. For possible future calls.
	 * It uses seperate static counters per maptype, so mixed calling can take place.
	 * 
	 * @staticvar int $objectmapi Next objectmap postfix
	 * @staticvar int $referencemapi Next referencemap postfix
	 * @param string $maptype 'objectmap'|'referencemap'
	 * @param bool $reset reset d
	 * @return bool true if a new map has been set, false if previous map was the last (and the first is set again)
	 */
	function _nextTable($maptype='objectmap', $reset=false)
	{
		static $objectmapi = 1; //seems statics can't be variable variables
		static $referencemapi = 1; 
		if ( !$reset )
		{
			if ( !in_array($maptype, array('objectmap','referencemap')) )
				trigger_error('AbstractObject::_nextTable() : Unknown maptype.', USER_ERROR); //Fatal
			if ( ${$maptype.'i'} == 1 )
			{
				$this->{$maptype.'0'} = $this->$maptype;
			}
			if ( isset($this->{$maptype.${$maptype.'i'}}) )
			{
				$this->$maptype = $this->{$maptype.${$maptype.'i'}};
				${$maptype.'i'}++;
				return true; 
			}
		}
		if ( ${$maptype.'i'} != 1 )
		{
			${$maptype.'i'} = 1;
			$this->$maptype = $this->{$maptype.'0'};
		}
		return false;
	}

	/**
	 * @param String $command INSERT|REPLACE
	 * @return Array ((Query) $q, (string) IdProperty)
	 */
	function _buildIntoSetQuery($command)
	{
		$lastid = '';
		$values = array();
		$sql = $command.' INTO `'.$this->objectmap['dbtable'].'`';

		$appended = $this->_appendSet($sql, $values);
	   	if ( $appended === false )
			return false;
		if ( $appended )
			$sql .= ',';
		
		foreach ( $this->objectmap['primary'] as $property => $field )
		{
			if ( eval('return isset($this->'.$property.');') )
			{
				$sql .= '`'.$field.'` = ??,';
				eval('$values[] = $this->'.$property.';');
			}
			else
				$lastid = $property;
		}
		$sql = substr($sql, 0, -1);
		$q = $this->db->query($sql);
		$q->bind_values($values);
		return array($q, $lastid);
	}
	/**
	 * Append SET ...=... to sql statement
	 * @return mixed false iff contraint broken or count(values)
	 */
	function _appendSet(&$sql, &$values)
	{
		$sql .= ' SET ';
		foreach ( $this->objectmap['properties'] as $property => $field )
		{
			//use eval so maps like district->id work;
			if ( ($bitbucket = eval('return preg_match("/^\\w+\\(.*\\)$/", $property)?1:0;')) //functioncall 
					||	($bitbucket = eval('return isset($this->'.$property.')?1:0;')) ) // || property isset
			{
				
				$sql .= '`'.$field.'` = ??,';
				eval('
					if ( is_bool($this->'.$property.') )
						$values[] = (int) $this->'.$property.';
					elseif ($this->'.$property.' === DB_NULL)
						$values[] = null;
					else
						$values[] = $this->'.$property.';');
			}
		}
		$sql = substr($sql, 0, -1);
		return count($values);
	}
	/**
	 * Append the where statement based on primary part of objectmap
	 * and fill values
	 */
	function _appendWhereAllPrimaries(&$sql, &$values)
	{
		$sql .= ' WHERE ';
		foreach ( $this->objectmap['primary'] as $property => $field )
		{
			if ( eval('return is_null($this->'.$property.');') )
				$sql .= 'ISNULL(`'.$field.'`) AND ';
			else
			{
				$sql .= '`'.$field.'` = ?? AND ';
				eval('$values[] = $this->'.$property.';');
			}
		}
		$sql = substr($sql, 0, -5);
	}
	/**
	 * Checks references defined in the {@link referencemap}(s)
	 *
	 * This is madness ofcourse, and should be handled by the DBMS
	 * AARGH this can in no way be made complete..... It only works one "FOREIGN KEY" deep....
	 * You could endlessly define referencemaps ofcourse..... ;(( To get the same effect...
	 * @returns array of string (each string is a delete query which should be executed if no RESTRICT is violated, in which case it returns false)
	 */
	function _checkReferences()
	{
		$rv = array();
		do
		{
			$values = array();
			$sql = 'SELECT COUNT(*) as ROWS FROM '.$this->referencemap['dbtable'].' WHERE '; //NO backticks so JOINS work
			foreach ( $this->referencemap['fields'] as $property => $field )
			{
				$sql .= '`'.$field.'` = ?? AND ';
				eval('$values[] = $this->'.$property.';');
			}
			$sql = substr($sql, 0, -5);
			$q = $this->db->query($sql); //could use some tuning, but this works
			$q->bind_values($values);
			$q->execute();
			$r = $q->fetch();
			if ( $r->{'ROWS'} )
			{
				if ( !$this->referencemap['ondelete'] ) //IC_REF_RESTRICT === false
				{
					$this->_nextTable('referencemap', true); // reset map to first
					return false;
				}
				else //IC_REF_CASCADE
					$rv[] = preg_replace('/(^[^F]*)/', 'DELETE ', $q->tmp_sql);
			}
		} while ( $this->_nextTable('referencemap') );
		return $rv;
	}
	/**
	 * 
	 */
	function generateCreateTables()
	{
		if(!isset($this->objectmap['dbtable']))
			return '';
		$primaryKey = '';
		$fields = "";
		foreach($this->objectmap['primary'] as $property => $field)
		{
			$type = isset($this->sqltypemap[$property])?$this->sqltypemap[$property]:'INT(11) NOT NULL';
			$fields .= sprintf("\t`%s` %s,\n",$field,$type);
			$primaryKey .= sprintf('`%s`, ',$field);
		}
		$sql = sprintf("CREATE TABLE `%s` (\n%s",$this->objectmap['dbtable'],$fields);
		if(isset($this->objectmap['properties']))
			foreach($this->objectmap['properties'] as $property => $field)
			{
				$type = isset($this->sqltypemap[$property])?$this->sqltypemap[$property]:'INT(11) NOT NULL';
				$sql .= sprintf("\t`%s` %s,\n",$field,$type);
			}
		if(isset($this->sqlprops)) // mkpretty
			foreach($this->sqlprops as $type => $val)
				$sql .= sprintf("\t%s,\n",$val);
		$sql .= sprintf("\tPRIMARY KEY(%s)\n",substr($primaryKey,0,-2));
		$sql .= ')';
		
		return $sql;
	}
	/**
	 * 
	 */
	function getTable()
	{
		return $this->objectmap['dbtable'];
	}
	/**
	 * Initialize objectmap(s) and familymap
	 *
	 * Called in the Constructor children must either override the Constructor or this method.
	 */
	function _initObjectMaps()
	{
	}
	/**
	 * @var array Objectmap multidimensional 'dbtable' => tablename, 'properties' => property => tablefieldname, 'primary' => property => tablefieldname
	 */
	var $objectmap;
	/**
	 * 
	 */
	var $sqltypemap;
	/**
	 * 
	 */
	var $sqlprops;
	/**
	 * Used for typecasting in {@link set()}.
	 * MySQLDriver returns NULL or String, this will enable you to cast it to bool or int etc.
	 * @var array typemap ('property'=>'type')
	 */
	var $typemap;
	/**
	 * @var array multidimensional ('parent'=>parentclassname=>property, 'children'=>ChildClass=>array('property'=>property, 'classfile'=>classpath, 'dbtable'=>linktablename, 'key'=>array(property=>linktablefieldname), 'properties'=>array(childproperty=>linktablefieldname)))
	 * <code>
	 * //From {@link IntercleanFloor::_initObjectMaps()}
 	 * $this->familymap['children']['IntercleanSpace']['property'] = 'spaces';
 	 * $this->familymap['children']['IntercleanSpace']['classfile'] = INTERCLEAN.'interclean_space.php';
 	 * $this->familymap['children']['IntercleanSpace']['dbtable'] = 'TBL_SPACE';
 	 * $this->familymap['children']['IntercleanSpace']['key']['id'] = 'FLOOR_ID';
 	 * $this->familymap['children']['IntercleanSpace']['properties']['id'] = 'SPACE_ID';
 	 * $this->familymap['parent']['intercleanbuilding'] = 'building';
	 * </code>
	 */
	var $familymap;
	/**
	 * @var array Referencemap (1 foreach table referencing this) multidimensional ('dbtable' => tablename, 'field' => property => tablefieldname)
	 */
	var $referencemap;
	/**
	 * @var bool Flag if Object isset;
	 */
	var $complete;
	/**
	 * @var bool Flag if Object is filled;
	 */
	var $filled = false;
	/**
	 * @var Database
	 */
	var $db;
	/**#@-*/
}

define('DB_NULL', '___NULL___');

?>
