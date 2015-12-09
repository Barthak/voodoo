<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package
 * @subpackage
 * @since 5-nov-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class DBModel
{
	function DBModel()
	{
		
	}
	
	function &Table($name,$objectmap=null)
	{
		$table = new DBTable($name,$objectmap);
		
		return $table;
	}
}

class DBTable
{
	var $name;
	function DBTable($name)
	{
		$this->name = $name;
	}
	function addField($field,$prop,$obj)
	{
		$this->$field = $obj;
		$this->$prop = $field;
	}
}

class DBField
{
	function DBField()
	{
		$args = func_get_args();
		print_r($args);
	}
	
	function getCreateStatement()
	{
		
	}
}

class IntegerField extends DBField
{
	
}

class DateTimeField extends DBField
{
	
}

class VarcharField extends DBField
{
	
}

class TextField extends DBField
{
	
}
?>
