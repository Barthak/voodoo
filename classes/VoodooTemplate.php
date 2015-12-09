<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage VoodooTemplate
 * @since 22-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class VoodooTemplate
{
	var $engine;
	var $location;
	var $path;
	var $wrapper;
	var $template_dir = false;
	var $theme_dir = false;
	var $subdir = '';
	var $template = false;
	
	function VoodooTemplate($args)
	{
		$this->engine = $args['engine'];
		$this->location = $args['location'];
		$this->path = $args['template_path']?$args['template_path'].'/':'';
		$file = isset($args['file'])?$args['file']:$this->engine;
		require_once($this->location.$file.'.php');
		$this->wrapper = isset($args['wrapper'])?$args['wrapper']:$this->engine.'Wrapper';
	}
	
	function &getInstance($args=null)
	{
		static $instance;
		static $engine;
		
		if(is_array($args))
		{
			if(!isset($instance[$args['engine']]))
			{
				if(!$engine)
					$engine = $args['engine'];
				$instance[$args['engine']] = new VoodooTemplate($args);
			}
			return $instance[$args['engine']];
		}
		
		if(!$args)
			return $instance[$engine];
		
		if(!isset($instance[$args['engine']]))
			return $instance[$engine];
		
		return $instance[$args['engine']];
	}
	function parse($template,$args,$dir=null)
	{
		$dir && $this->setDir($dir);
		return $this->_parse($template,$args);
	}
	
	function setDir($dir)
	{
		$dir && $this->template_dir = SPELLBOOK.$dir.'/templates/';
		if(THEME!='default' && $dir)
			$this->theme_dir = constant(strtoupper($dir).'_THEME_TEMPLATES');
		elseif(THEME!='default')
			$this->theme_dir = constant(strtoupper(THEME_NAME).'_THEME_TEMPLATES');
		$dir || $this->template_dir = TEMPLATES;
	}
	function getDir()
	{
		return $this->template_dir;
	}
	
	function _parse($template,$args)
	{
		//echo $this->template_dir.$this->path.$template.'.html == '.$this->theme_dir.$this->path.$template.'.html'.'<br />';
		$dir = is_file($this->template_dir.$this->path.$template.'.html')?$this->template_dir.$this->path:false;
		if($this->theme_dir)
			is_file($this->theme_dir.$this->path.$template.'.html') && $dir = $this->theme_dir.$this->path;
		if(!$dir)
			return false;
		
		$func = $this->wrapper;
		
		if(!method_exists($this,$func))
			$func = 'defaultWrapper';
		
		return $this->$func($dir,$template,$args);
	}
	
	function defaultWrapper($dir,$filename,$args)
	{
		$class = $this->engine;
		$obj = new $class($dir);
		return $obj->parse($dir.$filename,$args);
	}
	
	function ShapeShifterWrapper($dir,$filename,$args)
	{
		$t = new ShapeShifter($args);

		$t->load($dir.$filename.'.html');
		$t->render();
		return $t->display();
	}
	
	function SmartyWrapper($dir,$filename,$args)
	{
		$smarty = new Smarty();
		$smarty->template_dir = $dir; 
		
		foreach($args as $index => $val)
			$smarty->assign($index,$val);
		
		return $smarty->fetch($filename.'.html');
	}
}
?>
