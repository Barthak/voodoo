<?php
require_once(CLASSES.'VoodooHooks.php');
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage AttachmentHooks
 * @since 21-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentHooks extends VoodooHooks
{
	/**
	 * We have one formatting hook to change attachment links
	 * @return array
	 */
	function formattingHooks()
	{
		return array('attachment'=>array($this,'attachmentFormatting'));
	}
	/**
	 * We have one predisplay hook to add a list of attachments to the controller output 
	 * 
	 * This replaces <!-- ATTACHMENTS --> in the templates with a list.
	 * 
	 * @return array
	 */
	function preDisplayHooks()
	{
		return array('attachmentlist'=>array($this,'attachmentList'));
	}
	/**
	 * @param string $dispatcher
	 * @param array $actionlist
	 * @param string $content
	 * @return string $content
	 */
	function attachmentList($dispatcher,$actionlist,$content)
	{
		$conf = VoodooIni::load($dispatcher);
		if(!isset($conf['attachment'])||!$conf['attachment']['attachment'])
			return $content;
		
		$content = str_replace('<!-- ATTACHMENTS -->',$this->getAttachments($dispatcher,$actionlist[0]),$content);
		return $content;
	}
	/**
	 * @param string $dispatcher
	 * @param string $handle
	 * @return string
	 */
	function getAttachments($dispatcher,$handle)
	{
		if(is_object($handle))
		{
			$action = $handle->handle;
			$handle = $handle->id;
		}
		else
			$action = $handle;
		require_once(ATTACHMENT_CLASSES.'Attachment.php');
		$t =& VoodooTemplate::getInstance();
		$t->setDir(ATTACHMENT_TEMPLATES);
		$al = new AttachmentLink($this->db,strtoupper($dispatcher));
		if(!($q = $al->getAttachmentsForLink($handle)))
			return '';
		if(!$q->rows())
			return '';
		$args = array();
		while($r = $q->fetch())
		{
			$args[] = array(
				'name'=>$r->NAME,
				'filesize'=>Attachment::prettyBytes($r->FILESIZE),
				'lastupdate'=>$r->LAST_UPDATE,
				'user'=>$r->USER_NAME,
				'desc'=>($r->DESCRIPTION?sprintf('<q>%s</q>, ',$r->DESCRIPTION):'') //this should be an if statement in the template
				);
		}
		return $t->parse('attachments',array('prepath'=>PATH_TO_DOCROOT,'cont'=>strtolower($dispatcher),'action'=>$action,'attachments'=>$args));
	}
	/**
	 * @param string $str
	 * @param VoodooFormatter &$formatter
	 * @return string
	 */
	function attachmentFormatting($str,&$formatter)
	{
		$this->formatter =& $formatter;
		return preg_replace_callback('/[\!]?\[attachment:([^\]].*)\]/siU',array(&$this,'formatAttachmentLink'),$str);
	}
	/**
	 * @param array $matches
	 * @return string
	 */
	function formatAttachmentLink($matches)
	{
		if($matches[0]{0}=='!')
			return substr($matches[0],1);
			
		$displayname = 'attachment:'.$matches[1];
		$link = $matches[1];
		if(preg_match('/ (.*)/',$matches[1],$match))
		{
			$link = str_replace($match[0], '', $matches[1]);
			$displayname = $match[1];
		}
		$subs = explode(':',$link);
		$link = PATH_TO_DOCROOT.'/attachment/';
		if(count($subs)==1)
			$link .= $this->formatter->handler.'/'.$this->formatter->action.'/'.$subs[0]; 
		else
			$link .= implode('/',$subs);
		$link .= '?action=view';
		return sprintf('<a href="%s">%s</a>',$link,$displayname);
	}
}
?>
