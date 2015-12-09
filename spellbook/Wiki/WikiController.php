<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiController extends DefaultController
{
	/**
	 * @var array $dispatchers
	 */
	var $dispatchers = array();
	/**
	 * Use this to override the site template
	 * @var bool|string $site_template
	 */
	var $site_template = false;
	/**
	 * @param string $dispatcher
	 * @param array $actionlist
	 */
	function WikiController($dispatcher,$actionlist)
	{
		$this->init();
		$this->route($dispatcher,$actionlist);
	}
	/**
	 * Initializes the WikiDispatcher
	 */
	function init()
	{
		$this->DefaultController();
		// Make a connection with the database, this is used in dispatcher
		$this->db = $this->DBConnect();
		$this->dispatchers['wiki'] = new WikiDispatcher($this);
	}
	/**
	 * 
	 */
	function display()
	{
		if(!$this->site_template)
			return parent::display();

		return parent::display($this->site_template);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiDispatcher
{
	/**
	 * A reference to the Controller (in this case it is the WikiController)
	 * @var *Controller &$controller
	 */
	var $controller;
	/**
	 * @var array $conf
	 */
	var $conf;
	/**
	 * This array contains all the available wikipages.
	 * 
	 * This is used to quickly determine whether or not a (CamelCase) link is valid or broken.
	 * 
	 * @var array $wikilist
	 */
	var $wikilist;
	/**
	 * 
	 */
	var $action = false;
	/**
	 * Contructor
	 * @param WikiController &$controller
	 */
	function WikiDispatcher(&$controller,$action=false)
	{
		$this->controller =& $controller;
		$this->action = $action?$action:(isset($_REQUEST['action'])?$_REQUEST['action']:false);
		$this->init();
	}
	/**
	 * Set the wiki configuration parameters and get the list of wiki pages.
	 */
	function init()
	{
		$this->conf = VoodooIni::load('wiki');
		$wf =& VoodooFormatter::getInstance();
		$this->wikilist = $wf->wikilist;
	}
	/**
	 * @param array $action
	 */
	function dispatch($action)
	{
		if(!$action)
		{
			header('Location: '.PATH_TO_DOCROOT.'/wiki/'.$this->conf['setup']['default']);
			exit;
		}
		$wiki = $action[0];
		$lookup = strtolower($wiki);
		// Wiki page Names can only include letters
		// TODO: allow underscores and numbers as well as colons?
		preg_match('/([a-z]+)/i',$wiki,$matches);
		if($matches[0]!==$wiki)
			return VoodooError::displayError('Permission Denied');
			
		// The Wiki doesn't exist yet, see if we can create it.
		if(!isset($this->wikilist[$lookup]))
		{
			$wc = new WikiCreate($this);
			return $wc->execute($wiki);
		}
		elseif($wiki!==$this->wikilist[$lookup]['handle'])
		{
			// In case the CamelCase varied from the stored version (eg. CameLcase instead of CamelCase)
			exit('Did you mean ``'.$this->wikilist[$lookup]['handle']);	
		}
		
		// Check for the action handler (eg. edit,delete,etc.)
		if(isset($this->action))
		{
			switch($this->action)
			{
				case 'edit': // Edit a page
					$wm = new WikiModify($this);
					return $wm->execute($this->wikilist[$lookup]['id']);
				break;
				case 'delete': // Delete a page
					$wd = new WikiDelete($this);
					return $wd->execute($this->wikilist[$lookup]['id']);
				break;
				case 'history': // View the page history (revisions)
					$wh = new WikiHistory($this);
					return $wh->execute($this->wikilist[$lookup]['id']);
				break;
				case 'source':
					$ws = new WikiSource($this);
					return $ws->execute($this->wikilist[$lookup]['id']);
				break;
			}
		}
		// No actions were supplied and the page is valid, lets display it
		$wv = new WikiView($this);
		return $wv->execute($this->wikilist[$lookup]['id']);
	}
}
/**
 * Wrapper class for the VoodooPrivileges
 * 
 * @see VoodooPrivileges
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiPrivileges
{
	/**
	 * @var WikiDispatcher @dispatcher
	 */
	var $dispatcher;
	/**
	 * These are arguments used in the templating system
	 * @var array $defaultArgs
	 */
	var $defaultArgs;
	/**
	 * @var VoodooTemplate $template
	 */
	var $template;
	/**
	 * @var Database $db
	 */
	var $db;
	/**
	 * @var VoodooPrivileges $privs
	 */
	var $privs;
	/**
	 * 
	 */
	var $siteArgs;
	/**
	 * Constructor
	 * 
	 * Set the default vars like template, db and dispatcher
	 * @param WikiDispatcher &$dispatcher
	 */
	function WikiPrivileges(&$dispatcher)
	{
		$this->dispatcher = $dispatcher;
		
		$config = isset($this->dispatcher->conf['template'])?$this->dispatcher->conf['template']:null;
		$this->template =& VoodooTemplate::getInstance($config);
		$this->template->setDir(WIKI_TEMPLATES);
		
		$this->db = $this->dispatcher->controller->DBConnect();
		$this->defaultArgs = array(
			'prepath'=>PATH_TO_DOCROOT
			);
		$this->privs = new VoodooPrivileges($this->dispatcher->controller);
		
		$this->siteArgs = array('view'=>array('name'=>'article','extra'=>'<span class="spacer" />'));
		
		if($this->hasRights($_SESSION['access'],'modify'))
			$this->siteArgs['edit'] = array('name'=>'edit this page');
		elseif($this->hasRights($_SESSION['access'],'source'))
			$this->siteArgs['source'] = array('name'=>'source');
		if($this->hasRights($_SESSION['access'],'history'))
			$this->siteArgs['history'] = array('name'=>'history');
	}
	/**
	 * @see VoodooPrivileges::hasRights()
	 * @param int $access
	 * @param string $type
	 * @param string $page
	 */
	function hasRights($access,$type,$page='')
	{
		return $this->privs->hasRights($access,$type,'wiki',$this->dispatcher->conf['privileges'],$page);
	}
	/**
	 * Verifies whether or not the supplied argument is valid CamelCase
	 * 
	 * TODO: Make work with colon,underscores and numbers
	 * 
	 * @param $handle
	 * @return boolean
	 */
	function isCamelCase($handle)
	{
		return preg_match('/(?<=^| )([\!]?[A-Z]{1}[a-z]+(?:[A-Z]{1}[a-z]{1}\w+)+)/',$handle);
	}
	
	function addSiteArgs()
	{
		$this->dispatcher->controller->addSiteArg('tabs',$this->siteArgs);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiCreate extends WikiPrivileges
{
	/**
	 * @param string $handle (Wiki name)
	 * @return array(title,content)
	 */
	function execute($handle)
	{
		// If it is not CamelCase, it is not a valid Voodoo Wiki
		if(!$this->isCamelCase($handle))
		{
			$wv = new WikiView($this->dispatcher);
			return $wv->execute(null,'Incorrect Wiki name.');
		}
		// Validate if the user has rights to create a new Wiki
		if(!$this->hasRights($_SESSION['access'],'create',$handle))
		{
			$wv = new WikiView($this->dispatcher);
			return $wv->execute(null,'This Page Is Currently Unavailable.');
		}
		$args = $this->defaultArgs;
		// We're supposed to save the page, do it.
		if(isset($_POST['save'])&&!empty($_POST['wikicontent']))
		{
			$wp = new Wiki($this->db);
			// HTMLEntities, no arbitrary code should be inserted.
			// TODO: use something better than htmlentities() 
			$wp->save($handle,htmlentities($_POST['wikicontent']));
			header('Location: '.PATH_TO_DOCROOT.'/wiki/'.$handle);
			exit;
		}
		// We want to see a preview of the page. Put the content back in the arguments for the template
		elseif(isset($_POST['preview'])&&!empty($_POST['wikicontent']))
		{
			$wv = new WikiView($this->dispatcher);
			list(,$args['preview_content']) = $wv->execute('',htmlentities($_POST['wikicontent']),false);
			$args['content'] = htmlentities($_POST['wikicontent']);
			$args['preview'] = $this->template->parse('preview',$args);
		}
		$args['handle'] = $handle;
		$args['formaction'] = '/wiki/'.$handle;
		$args['actiontype'] = 'Submit changes';
		
		return array($handle.' - WikiCreate',$this->template->parse('create',$args));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiModify extends WikiPrivileges
{
	/**
	 * We already knew beforehand what handle was requested and the ID was looked up.
	 * @param int $id
	 * @return array(title,content)
	 */
	function execute($id)
	{
		$w = new Wiki($this->db);
		$w->set($id);
		$this->dispatcher->controller->dispatcherObject = $w;
		$content = $w->revision->content;
		$handle = $w->handle;
		if(!$this->hasRights($_SESSION['access'],'modify',$handle))
			return array($handle.' - WikiCreate','No Permission');
			
		$args = $this->defaultArgs;
		$args['content'] = $content;
		// Lets save it.
		if(isset($_POST['save'])&&!empty($_POST['wikicontent']))
		{
			// Did it actually change?
			if(htmlentities($_POST['wikicontent'])!==$content)
			{
				$w->update(htmlentities($_POST['wikicontent']));
				header('Location: '.PATH_TO_DOCROOT.'/wiki/'.$handle);
				exit;
			}
		}
		// Lets preview it.
		elseif(isset($_POST['preview'])&&!empty($_POST['wikicontent']))
		{
			// But only if the content actually
			if(htmlentities($_POST['wikicontent'])!==$content)
			{
				$wv = new WikiView($this->dispatcher);
				list(,$args['preview_content']) = $wv->execute('',htmlentities($_POST['wikicontent']),false);
				$args['content'] = htmlentities($_POST['wikicontent']);
				$args['preview'] = $this->template->parse('preview',$args);
			}
		}
		$args['button_action'] = '/wiki/'.$handle;
		$args['button'] = 'Cancel';
		$args['class'] = 'buttonmargin';
		$buttons = $this->template->parse('button',$args);
		
		$args['handle'] = $handle;
		$args['formaction'] = '/wiki/'.$handle.'?action=edit';
		$args['actiontype'] = 'Submit changes';
		$args['buttons'] = $buttons;
		
		$this->siteArgs['edit']['active'] = 'active';
		$this->addSiteArgs();
		
		return array($handle.' - WikiModify',$this->template->parse('create',$args));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiView extends WikiPrivileges
{
	/**
	 * @param int $id
	 * @param string $content
	 * @param bool $actions
	 * @param bool|int $revision 
	 */
	function execute($id=null,$content='',$actions=true,$revision=false)
	{
		$handle = '';
		if($id)
		{
			$w = new Wiki($this->db);
			$w->set($id,$revision);
			$content = $w->revision->content;
			$handle = $w->handle;
			$this->dispatcher->controller->dispatcherObject = $w;
		}
		// We dont have rights to view wiki's (or this individual page)
		// @see VoodooPrivileges
		if(!$this->hasRights($_SESSION['access'],'view',$handle))
			return array($handle.' - WikiView','No Permission');
		
		$wf =& VoodooFormatter::getInstance();
		$wf->setWikiPotions($this->dispatcher->conf['potions']); // this assumes stuff
		
		if(isset($this->dispatcher->conf['templates'])&&isset($this->dispatcher->conf['templates']['template.'.$handle]))
			$this->dispatcher->controller->site_template = $this->dispatcher->conf['templates']['template.'.$handle];
		
		$content = $wf->parse($content,array('handler'=>'wiki','action'=>$handle));
		// Add simple template in case the rights are to modify/delete
		// We were told to show actions, so lets verify the rights
		$buttons = '';
		
		$attachments = (bool)(isset($this->dispatcher->conf['attachment'])&&$this->dispatcher->conf['attachment']&&defined('ATTACHMENT_CLASSES'));
	
		// We can modify the wiki page, show the button to do so
		if($actions && $this->hasRights($_SESSION['access'],'modify',$handle))
		{
			$args = $this->defaultArgs;
			$args['button_action'] = '/wiki/'.$handle.'?action=edit';
			$args['button'] = 'Edit this page';
			$args['class'] = 'buttonmargin';
			$buttons .= $this->template->parse('button',$args);
			
			// We can add attachments 
			// TODO: we dont want the wiki to know anything about attachments
			if($attachments)
			{
				$args = $this->defaultArgs;
				$args['button_action'] = '/attachment/wiki/'.$handle.'?action=create';
				$args['button'] = 'Attach file';
				$args['class'] = 'buttonmargin';
				$buttons .= $this->template->parse('button',$args);
			}
		}
		$buttons .= '<span class="spacer"></span>';
		// We can view the history of the wiki page, show the button
		if($actions && $this->hasRights($_SESSION['access'],'history',$handle))
		{
			$args = $this->defaultArgs;
			$args['button_action'] = '/wiki/'.$handle.'?action=history';
			$args['button'] = 'View page history';
			$args['class'] = 'buttonlargemargin';
			$buttons .= $this->template->parse('button',$args);
		}
		// We can delete the wiki page, show the button
		if($actions && $this->hasRights($_SESSION['access'],'delete',$handle))
		{
			$args = $this->defaultArgs;
			$args['button_action'] = '/wiki/'.$handle.'?action=delete';
			$args['button'] = 'Delete page';
			$args['class'] = 'buttonmargin';
			$buttons .= $this->template->parse('button',$args);
		}
		
		$this->siteArgs['view']['active'] = 'active';
		$this->addSiteArgs();
	
		return array($handle.' - WikiView',$this->template->parse('view',array('content'=>$content,'buttons'=>$buttons)));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiDelete extends WikiPrivileges
{
	/**
	 * @param int $id
	 */
	function execute($id)
	{
		$w = new Wiki($this->db);
		$w->set($id);
		$this->dispatcher->controller->dispatcherObject = $w;
		// Validate the rights to delete this wiki page
		if(!$this->hasRights($_SESSION['access'],'delete',$w->handle))
			return array($w->handle.' - WikiDelete','No Permission');
		// Make sure this is done in two steps - First confirm, then delete.
		if(!isset($_REQUEST['confirm']))
		{
			$wv = new WikiView($this->dispatcher);
			list(,$content) = $wv->execute($id,null,false);
			
			$buttons = '';
			$args = $this->defaultArgs;
			$args['button_action'] = '/wiki/'.$w->handle.'?action=delete&confirm=true';
			$args['button'] = 'Yes, Delete Page';
			$args['class'] = 'buttonmargin';
			$buttons .= $this->template->parse('button',$args);
			$args['button_action'] = '/wiki/'.$w->handle;
			$args['button'] = 'No';
			$buttons .= $this->template->parse('button',$args);
			
			$args = $this->defaultArgs;
			$args['buttons'] = $buttons;
			$args['content'] = $content;
			
			$this->siteArgs['view']['active'] = 'active';
			$this->addSiteArgs();
			
			return array($w->handle.' - WikiDelete',$this->template->parse('delete',$args));
		}
		// We did confirm, lets delete it
		$w->delete();
		
		header('Location: '.PATH_TO_DOCROOT);
		exit();
	}
}
/**
 * This show the history of the wiki page
 * 
 * TODO: Add Diff functionality
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiHistory extends WikiPrivileges
{
	/**
	 * @param int $id
	 * @return array(title,content)
	 */
	function execute($id)
	{
		$w = new Wiki($this->db);
		$w->set($id);
		$this->dispatcher->controller->dispatcherObject = $w;
		// Lets validate that we have permission to access the history of a wiki page
		if(!$this->hasRights($_SESSION['access'],'history',$w->handle))
			return array($w->handle.' - WikiHistory','No Permission');
		// Show a list of all revisions for this wiki page in case no individual revision was selected
		if(!isset($_REQUEST['revision']))
		{
			$user = new User($this->db);
			$args = $this->defaultArgs;
			$args['handle'] = $w->handle;
			$args['link'] = '/wiki/'.$w->handle.'?action=history';
			$rv = $this->template->parse('revisionrowheader',$args);

			$q = $w->getRevisions();
			while($r = $q->fetch())
			{
				$user->set($r->USER_ID);
				$args['author'] = $user->name;
				$args['revision_dt'] = $r->REVISION_DATETIME;
				$args['revid'] = $r->REVISION_ID;
				$rv .= $this->template->parse('revisionrow',$args);
			}
			
			$this->siteArgs['history']['active'] = 'active';
			$this->addSiteArgs();
			
			return array($w->handle.' - WikiHistory',$rv);
		}
		else
		{
			// A revision was selected, so lets show that revision with the WikiView dispatcher
			$rev = (int)$_REQUEST['revision'];
			$wv = new WikiView($this->dispatcher);
			list(,$content) = $wv->execute($id,null,false,$rev);
			
			$args = $this->defaultArgs;
			$args['content'] = $content;
			
			$this->siteArgs['history']['active'] = 'active';
			$this->addSiteArgs();
			
			return array($w->handle.' (Revision '.$rev.') - WikiHistory',$content);
		}
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiController
 * @since 18-oct-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiSource extends WikiPrivileges
{
	/**
	 * 
	 */
	function execute($id)
	{
		$w = new Wiki($this->db);
		$w->set($id);
		$this->dispatcher->controller->dispatcherObject = $w;
		if(!$this->hasRights($_SESSION['access'],'source',$w->handle))
			return array($w->handle.' - WikiSource','No Permission');
		
		if($this->hasRights($_SESSION['access'],'modify',$w->handle))
		{
			$wm = new WikiModify($this->dispatcher);
			return $wm->execute($id,null,false);
		}
		
		$this->siteArgs['source']['active'] = 'active';
		$this->addSiteArgs();
		
		return array($w->handle.' - WikiSource',$this->template->parse('source',array('content'=>$w->revision->content)));
	}
}
?>