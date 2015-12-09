<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage TableFactory
 * @since 10-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class TableFactory
{
	/**
	 * HTML id="" for the table
	 * TODO: make this actually do something
	 * @var string $id
	 */
	var $id;
	/**
	 * A two dimensional (table) array with data
	 * @var array $ds
	 */
	var $ds;
	/**
	 * Whether or not to show the <th> in the table
	 * @var bool $show_header
	 */
	var $show_header = true;
	/**
	 * The list of value processor callback functions
	 * @var array $vproc 
	 */
	var $vproc = array();
	/**
	 * The list of columns that shouldnt be outputted to the screen
	 * @var array $hidden
	 */
	var $hidden = array();
	/**
	 * Constructor
	 * @param mixed $ds
	 */
	function TableFactory($ds='')
	{
		$ds && $this->setData($ds);
	}
	/**
	 * Get the XHTML Table based on the $this->ds variable
	 * @param string $class CSS class name
	 * @return string
	 */
	function getXHTMLTable($class)
	{
		if(!$this->ds)
			return false;
			
		$rv = '<table class="'.$class.'" cellspacing="0"><tbody>';
		$head = '<thead>';
		$odd = 'even';
		
		foreach($this->ds as $row_i => $row)
		{
			$odd = ($odd=='odd')?'even':'odd';
			$rv .= '<tr>';
			foreach($row as $orghead => $col)
			{
				if(in_array($orghead,$this->hidden))
					continue;
				if($this->show_header)
					$head .= '<th>'.$orghead.'</th>';
				if(isset($this->vproc[$orghead]))
				{
					if(is_array($this->vproc[$orghead]))
					{
						list($obj,$func) = $this->vproc[$orghead];
						$col = $obj->$func(array('head'=>$orghead,'value'=>$col,'row'=>$row));
					}
					else
						$col = eval($this->vproc[$orghead]);
				}
				$rv .= '<td class="'.$odd.'">'.$col.'</td>';
			}
			$rv .= '</tr>';
			$this->show_header = false;
		}
		
		$rv = $rv.'</tbody>'.$head.'</thead></table>';
		return $rv;
	}
	
	/**
	 * Set Data
	 *
	 * Sets new Data. Unsets caption and footer, but leaves Language_Interface and Processors intact.
	 * @param mixed $dataset 2d array or Query object 
	 * @param bool $show_empty Show emty head on a empty resultset. 
	 * @see Query
	 * TODO: strtolower(substr(get_class($dataset), -5)) == 'query' is a hack, should be is_a($dataset, 'query') but needs newer PHP
	 */
	function setData($dataset, $show_empty=false)
	{
		$this->ds = array();
		$this->show_header = true;
		$this->id = null;
		if ( is_array($dataset) )
			$this->ds = $dataset;
		elseif ( strtolower(substr(get_class($dataset), -5)) == 'query' )
		{
			while ( $row = $dataset->fetch() )
				$this->ds[] = (array) $row;
			if ( empty($this->ds) && $show_empty && strtolower(get_class($dataset)) == 'mysql_query' )
				while ( $field = mysql_fetch_field($dataset->result) ) 
					$this->ds[0][$field->name] = '';
		}
		else
			$this->ds = (array) $dataset;
	}
	/**
	 * @param mixed $fields 
	 * @param mixed $callback
	 */
	function setValueProcessor($fields,$callback)
	{
		$fields = (array)$fields;
		foreach($fields as $column)
			$this->vproc[$column] = $callback;
	}
	/**
	 * @param mixed $fields
	 */
	function setHiddenField($fields)
	{
		$fields = (array)$fields;
		foreach($fields as $field)
			$this->hidden[$field]=$field;
	}
}
?>
