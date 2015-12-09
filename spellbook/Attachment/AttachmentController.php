<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentController extends DefaultController
{
	/**
	 * @var array $dispatchers
	 */
	var $dispatchers = array();
	/**
	 * @param string $dispatcher
	 * @param array $actionlist
	 */
	function AttachmentController($dispatcher,$actionlist)
	{
		$this->init();
		$this->route($dispatcher,$actionlist);
	}
	/**
	 * Initializes the AttachmentDispatcher
	 */
	function init()
	{
		$this->DefaultController();
		// Make a connection with the database, this is used in dispatcher
		$this->db = $this->DBConnect();
		$this->dispatchers['attachment'] = new AttachmentDispatcher($this);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentDispatcher
{
	/**
	 * @var AttachmentController $controller
	 */
	var $controller;
	/**
	 * @var VoodooPrivileges $privs
	 */
	var $privs;
	/**
	 * @var array $conf
	 */
	var $conf;
	/**
	 * Controller of the attachment (the linked object: Wiki,Topic etc.)
	 * @var string $cont
	 */
	var $cont;
	/**
	 * The action of the linked object (WikiPageName,TopicId)
	 * @var int|string $action
	 */
	var $action;
	/**
	 * @param AttachmentController $controller
	 */
	function AttachmentDispatcher(&$controller)
	{
		$this->controller =& $controller;
		$this->privs = new VoodooPrivileges($controller);
		$this->conf = VoodooIni::load('attachment');
	}
	/**
	 * @param array $actionlist
	 * @return array(title,content)
	 */
	function dispatch($actionlist)
	{
		// Less than two params is NOT a valid attachment handler
		// Example of valids:
		//  * /attachment/Wiki/WikiAttachmentExample
		//  * /attachment/Wiki/WikiAttachmentExample/test.jpg 
		if(count($actionlist)<2)
			return array('Attachment Error', VoodooError::displayError('Incorrect SubController Actionlist'));
		
		// We default to not having an attachment set
		$attachment = false;
		if(count($actionlist)==3) // The third action from the actionlist is the attachment
			list($controller,$action,$attachment) = $actionlist;
		else
			list($controller,$action) = $actionlist;
		
		$this->cont = $controller;
		$this->action = $action;
		
		$lookup = $controller.($attachment?'.'.$attachment:'');	
		// We need at least view rights to continue
		if(!$this->privs->hasRights($_SESSION['access'],'view','attachment',$this->conf['privileges'],$lookup))
			return array('Attachment Error', VoodooError::displayError('Permission Denied'));
		
		require_once(ATTACHMENT_CLASSES.'Attachment.php');
		$class = 'AttachmentLink';
		// The controller is enabled.
		$uc = strtoupper($controller);
		// Lets see if the linked object has its own linked attachment object
		if(defined($uc.'_CLASSES'))
		{
			$conf = VoodooIni::load($controller);
			if(!isset($conf['attachment'])||!$conf['attachment']['attachment'])
				return array('Attachment Error', VoodooError::displayError('This Controller Doesnt Support Attachments'));
			if(isset($conf['attachment']['class']))
			{
				if(!is_file(constant($uc.'_CLASSES').$conf['attachment']['class']))
					return array('Attachment Error', VoodooError::displayError('This Controller Attachment Class doesnt exist.'));
				require_once(constant($uc.'_CLASSES').$conf['attachment']['class']);
				$class = ucfirst($controller).'Attachment';
			}
		}
		// Lets init a new link object
		$al = new $class($this->controller->DBConnect(),$uc);
		if(isset($_REQUEST['action']))
		{
			switch($_REQUEST['action'])
			{
				case 'download':
					$ad = new AttachmentDownload($this,$attachment,$al);
					return $ad->execute();
				break;
				case 'create':
					$ac = new AttachmentCreate($this,$attachment,$al);
					return $ac->execute();
				break;
				case 'delete':
					$ad = new AttachmentDelete($this,$attachment,$al);
					return $ad->execute();
				break;
				case 'modify':
				break;

			}
		}
		// Display the attachment information, dont auto download
		$av = new AttachmentView($this,$attachment,$al);
		return $av->execute();
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentObject
{
	/**
	 * @var AttachmentDispatcher $dispatcher
	 */
	var $dispatcher;
	/**
	 * @var string $attachment
	 */
	var $attachment;
	/**
	 * @var AttachmentLink &$al
	 */
	var $al;
	/**
	 * @param AttachmentDispatcher &$dispatcher
	 * @param string $attachment
	 * @param AttachmentLink &$al
	 */
	function AttachmentObject(&$dispatcher,$attachment,&$al)
	{
		$this->dispatcher =& $dispatcher;
		$this->attachment = $attachment;
		$this->al =& $al;
	}
	/**
	 * Wrapper function for calls to VoodooPrivileges
	 * @param int $access
	 * @param string $type
	 * @param string $attachment
	 * @return bool
	 */
	function hasRights($access,$type,$attachment='')
	{
		return $this->dispatcher->privs->hasRights($access,$type,'attachment',$this->dispatcher->conf['privileges'],$attachment);
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentDownload extends AttachmentObject
{
	/**
	 * We want to Download the attachment in its original form
	 * TODO: trigger always download, even for images/text 
	 */
	function execute()
	{
		$attachment = new Attachment($this->dispatcher->controller->DBConnect());
		$attachment->setByName($this->attachment);
		$type = $attachment->getFileType($attachment->name);
		$map = $attachment->getContentTypeMap();
		
		header("Content-type: ".$map[$type].'/'.$type);
		$fp = fopen($this->dispatcher->conf['settings']['upload_dir'].'/'.$attachment->name,'r');
		fpassthru($fp);
		exit();
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentCreate extends AttachmentObject
{
	function execute()
	{
		$error = '';
		if(!$this->dispatcher->privs->hasRights($_SESSION['access'],'create','attachment',$this->dispatcher->conf['privileges'],$this->dispatcher->cont))
			return array('Attachment Error', VoodooError::displayError('Permission Denied (1)'));
		
		$setup = $this->dispatcher->conf['settings'];
		
		if(isset($_POST['action'])&&!empty($_FILES['attachment']))
		{
			// Check for type. Filesize etc.
			$at = new Attachment($this->dispatcher->controller->DBConnect());
			$desc = htmlentities($_POST['description']);
			if($at->upload($desc,$setup,$_FILES['attachment']))
			{
				$this->al->attachment =& $at;
				$this->al->linked = (object)array('id'=>$this->dispatcher->action);
				$this->al->insert();
				header(sprintf('Location: %s/%s/%s',
					PATH_TO_DOCROOT,
					$this->dispatcher->cont,
					$this->dispatcher->action));
				exit();
			}
			else
				$error = $at->getError();
		}
		
		$args = array(
			'error'=>$error?VoodooError::displayError($error):'',
			'prepath'=>PATH_TO_DOCROOT,
			'action'=>$this->dispatcher->action,
			'controller'=>$this->dispatcher->cont,
			'formaction'=>'create'
			);
		$t =& VoodooTemplate::getInstance();
		$t->setDir(ATTACHMENT_TEMPLATES);
		return array('Create Attachment',$t->parse('create',$args));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentDelete extends AttachmentObject
{
	function execute()
	{
		if(!$this->hasRights($_SESSION['access'],'delete',$this->attachment))
			return array('Attachment Error',VoodooError::displayError('Permission Denied'));
		if(!isset($_REQUEST['confirm']))
		{
			$av = new AttachmentView($this->dispatcher,$this->attachment,$this->al);
			list(,$content) = $av->execute();
			
			$t =& VoodooTemplate::getInstance();
			$t->setDir(ATTACHMENT_TEMPLATES);
			
			$path = '/attachment/'.$this->dispatcher->cont.'/'.$this->dispatcher->action.'/'.$this->attachment;
			
			$buttons = '';
			$args = array('prepath'=>PATH_TO_DOCROOT);
			$args['button_action'] = $path.'?action=delete&confirm=true';
			$args['button'] = 'Yes, Delete Attachment';
			$args['class'] = 'buttonmargin';
			$buttons .= $t->parse('button',$args);
			$args['button_action'] = $path.'?action=view';
			$args['button'] = 'No';
			$buttons .= $t->parse('button',$args);
			
			
			$args = array(
				'prepath'=>PATH_TO_DOCROOT,
				'content'=>$content,
				'buttons'=>$buttons
				);
			return array('',$t->parse('delete',$args));
		}
		$attachment = new Attachment($this->dispatcher->controller->DBConnect());
		$attachment->setByName($this->attachment);
		
		$this->al->delete($attachment->id,$this->dispatcher->action);
		$attachment->delete();
		header(sprintf('Location: %s/%s/%s',PATH_TO_DOCROOT,strtolower($this->dispatcher->cont),$this->dispatcher->action));
		exit();
	}
}
/**
 * TODO: Implement
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentModify extends AttachmentObject
{
	function execute()
	{
		if(!$this->hasRights($_SESSION['access'],'modify',$this->attachment))
			return array('Attachment Error',VoodooError::displayError('Permission Denied'));
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentController
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentView extends AttachmentObject
{
	function execute()
	{
		if(!$this->hasRights($_SESSION['access'],'view',$this->attachment))
			return array('Attachment Error',VoodooError::displayError('Permission Denied'));
			
		if(!$this->attachment)
			return array('','');
		$this->al->linked = (object)array('id'=>$this->dispatcher->action);
		
		$attachment = new Attachment($this->dispatcher->controller->DBConnect());
		$attachment->setByName($this->attachment);
		if(!$attachment->isComplete())
			return array('Attachment Error',VoodooError::displayError('Attachment Does Not Exist'));
		$attachment->user->set();
				
		$t =& VoodooTemplate::getInstance();
		$t->setDir(ATTACHMENT_TEMPLATES);
		
		$defArgs = array('prepath'=>PATH_TO_DOCROOT);
		$buttons = '';
		if($this->hasRights($_SESSION['access'],'modify',$this->attachment))
		{
			$args = $defArgs;
			$args['button_action'] = '/attachment/'.$this->dispatcher->cont.'/'.$this->dispatcher->action.'/'.$this->attachment.'?action=modify';
			$args['button'] = 'Modify attachment';
			$args['class'] = 'buttonmargin';
			$buttons .= $t->parse('button',$args);
		}
		if($this->hasRights($_SESSION['access'],'delete',$this->attachment))
		{
			$args = $defArgs;
			$args['button_action'] = '/attachment/'.$this->dispatcher->cont.'/'.$this->dispatcher->action.'/'.$this->attachment.'?action=delete';
			$args['button'] = 'Delete attachment';
			$args['class'] = 'buttonmargin';
			$buttons .= $t->parse('button',$args);
		}
		
		$args = array(
			'prepath'=>PATH_TO_DOCROOT,
			'action'=>$this->dispatcher->action,
			'name'=>$this->attachment,
			'cont'=>$this->dispatcher->cont,
			'last_update'=>$attachment->lastupdate,
			'size'=>Attachment::prettyBytes($attachment->filesize),
			'user'=>$attachment->user->name,
			'desc'=>$attachment->description,
			'preview'=>$this->renderPreview($attachment),
			'buttons'=>$buttons
			);

		return array($this->dispatcher->action.' - '.$this->attachment,$t->parse('preview',$args));
	}
	
	function renderPreview($attachment)
	{
		$type = $attachment->getFileType($attachment->name);
		$map = $attachment->getContentTypeMap();
		switch($map[$type])
		{
			case 'image':
				// TODO: move this to an image template?
				return sprintf('<img src="%s/attachment/%s/%s/%s?action=download" alt="%s" />',
					PATH_TO_DOCROOT,
					$this->dispatcher->cont,
					$this->dispatcher->action,
					$attachment->name,
					$attachment->name
					);
			break;
			case 'text':
				if(!$this->dispatcher->conf['settings']['render_unsafe_content'])
					break;
				ob_start();
				$fp = fopen($this->dispatcher->conf['settings']['upload_dir'].'/'.$attachment->name,'r');
				fpassthru($fp);
				return nl2br(ob_get_clean());
			break;
		}
		return '';
	}
}
?>