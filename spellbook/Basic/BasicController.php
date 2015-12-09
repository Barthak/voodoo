<?php
/**
 * The BasicController is meant for parsing .css files
 * 
 * This was created to make styles per Controller work.
 * Possibly also for images and JavaScript files.
 * 
 * TODO: Make work with Themes
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package BasicController
 * @since 9-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class BasicController extends DefaultController
{
	/**
	 * @var array $dispatchers
	 */
	var $dispatchers = array();
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @param string $dispatcher
	 * @param array $actionlist
	 * @param bool $route
	 */
	function BasicController($dispatcher,$actionlist,$route=true)
	{
		$this->init();
		$route && $this->route($dispatcher,$actionlist);
	}
	/**
	 * Initialization
	 */
	function init()
	{
		$this->DefaultController($this->conf['engine']['site.setup']);//boot up the default controller
		
		$this->db = $this->DBConnect();
		$this->dispatchers['scripts'] = new ScriptDispatcher($this);
		$this->dispatchers['images'] = new ImageDispatcher($this);
		$this->dispatchers['style'] = new StyleDispatcher($this);
	}
	/**
	 * We dont use the DefaultControllers output (which is the default website)
	 */
	function display()
	{
		exit($this->content);
	}
}
/**
 * Output a .css file 
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package BasicController
 * @since 9-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class BasicDefaultDispatcher
{
	var $type;
	var $contentheader;
	/**
	 * @param array $actionlist
	 * @return array (bitbucket,content)
	 */
	function dispatch($actionlist)
	{
		// No Action, No Service
		if(!count($actionlist))
			exit();
		$dir = false;
		// Hey! It looks like this .css file is part of a Controller
		
		if(count($actionlist)==2&&($this->type!=$actionlist[0]))
		{
			// style within a Controller
			$dir = $actionlist[0];
			$define = strtoupper($dir).'_'.strtoupper($this->type);
			$dir .= '/';
			$usedir = defined($define)?constant($define):constant(strtoupper($this->type)).$dir;
			$file = $actionlist[1];
		}
		else
		{
			$dir = '';
			$usedir = constant(strtoupper($this->type));
			$file = $actionlist[0];
		}
		
		// check if it ends with .css
		if(!$this->validate($file))
			exit('Incorrect '.$this->type);
		
		// Look in the theme dir
		if(THEME)
		{
			$dir = defined(strtoupper(THEME_NAME).'_THEME_TEMPLATES')?
				constant(strtoupper(THEME_NAME).'_THEME_TEMPLATES').$dir:$usedir;
			
			if(is_file($dir.$file))
				$usedir = $dir;
		}
		$content = '';
		if(!is_file($usedir.$file))
		{
			header("HTTP/1.0 404 Not Found");
			exit();
		}
		$fp = fopen($usedir.$file,'r');
		header("Content-type: ".$this->contentheader);
		exit(fpassthru($fp));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package BasicController
 * @subpackage BasicController
 * @since 20-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class StyleDispatcher extends BasicDefaultDispatcher
{
	var $type = 'style';
	var $contentheader = 'text/css';
	var $def = '';
	function validate($file)
	{
		return (bool)(substr($file,-4)=='.css');	
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package BasicController
 * @subpackage BasicController
 * @since 20-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class ImageDispatcher extends BasicDefaultDispatcher
{
	var $type = 'images';
	var $contentheader = 'image/';
	var $def = '0.gif';
	function validate($file)
	{
		$info = pathinfo($file);
		if(in_array($info['extension'],array('jpg','gif','jpeg','png')))
		{
			$this->contentheader .= $info['extension'];
			return true;
		}
		if($info['extension'] == 'swf')
		{
			$this->contentheader = 'application/x-shockwave-flash';
			return true;
		}
		return false;
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package BasicController
 * @subpackage BasicController
 * @since 26-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class ScriptDispatcher extends BasicDefaultDispatcher
{
	var $type = 'scripts';
	var $contentheader = 'text/javascript';
	var $def = '';
	function validate($file)
	{
		return (bool)(substr($file,-3)=='.js');
	}
}
?>