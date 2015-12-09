<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiFormatter
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiFormatter extends VoodooFormatter
{
	/**
	 * @var Database &$db
	 */
	var $db;
	/**
	 * This array contains all the available wikipages.
	 * 
	 * This is used to quickly determine whether or not a (CamelCase) link is valid or broken.
	 * 
	 * @var array $wikilist
	 */
	var $wikilist = array();
	/**
	 * @access private
	 * @var array $_monospaceBlock
	 */
	var $_monospaceBlock = array();
	/**
	 * List of enabled wikipotions
	 * @access private
	 * @var array $wikipotions
	 */
	var $wikipotions = array();
	/**
	 * This contains a list of all the headers found in the context.
	 * 
	 * The list can be used to create indexes of pages for better navigation
	 * @access private
	 * @var array $headers
	 */
	var $headers = array();
	/**
	 * Used for closing of p-tags
	 * @var boolean $hasOpenParagraph
	 */
	var $hasOpenParagraph = false;
	var $handler = 'wiki';
	var $action = '';
	/**
	 * 
	 */
	function setup()
	{
		// set wikilist
		require_once(WIKI_CLASSES.'Wiki.php');
		$wikipage = new Wiki($this->db);
		$this->wikilist = $wikipage->getWikis();
	}
	/**
	 * Parse the context content and replace wikiformatting
	 * @return string $content
	 */
	function parse($txt,$args=array())
	{
		if($args)
			foreach($args as $index => $value)
				$this->$index = $value;
		$txt = preg_replace_callback('/{{{(.*)}}}/siU', array(&$this,'__callbackMonospace'),$txt);
		$txt = preg_replace_callback("/^(={1,3})(.*)(={1,3})/m", array(&$this,'__callbackHeading'), $txt);
		$txt = preg_replace_callback('/(?<=^|\n| )([\!]?[A-Z]{1}[a-z]+([A-Z]{1}[a-z]+)+)/',array(&$this,'__callbackCamelCase'),$txt);
		// Underline
		$txt = preg_replace('/__(.*)__/iU','<u>\\1</u>',$txt);
		// Deleting, strike through
		$txt = preg_replace('/~~(.*)~~/iU','<del>\\1</del>',$txt);
		// Super text
		$txt = preg_replace('/\^(.*)\^/iU','<sup>\\1</sup>',$txt);
		// Sub text
		$txt = preg_replace('/,,(.*),,/iU','<sub>\\1</sub>',$txt);
		// Bold-Italic text
		$txt = preg_replace('/\'\'\'\'(.*)\'\'\'\'/iU','<strong><em>\\1</em></strong>',$txt);
		// Bold text
		$txt = preg_replace('/\'\'\'(.*)\'\'\'/iU','<strong>\\1</strong>',$txt);
		// Italic text
		$txt = preg_replace('/\'\'(.*)\'\'/iU','<em>\\1</em>',$txt);
		$txt = preg_replace_callback("/\[(http[s]?:\/\/.*)\]/siU", array(&$this,'__callbackLink'), $txt);
		$txt = preg_replace_callback("/\[img:(http[s]?:\/\/.*)\]/siU", array(&$this,'__callbackImage'), $txt);
		$txt = preg_replace_callback("/\[wiki:(.*)\]/siU", array(&$this,'__callbackWiki'), $txt);
		// Horizontal Ruler
		$txt = preg_replace('/----/iU','<hr size="1">',$txt);
//		$txt = preg_replace_callback("/[\!]?\{([0-9]+)\}|[\!]?report:([0-9]+)/siU", array(&$this,'__replaceReportLink'), $txt);
	//	$txt = preg_replace_callback("/[\!]?#([0-9]+)|[\!]?topic:([0-9]+)/siU", array(&$this,'__replaceTopicLink'), $txt);
		// Line end. Hard enter
		$txt = preg_replace("/\[\[BR\]\]/siU", '<br />', $txt);
		$txt = preg_replace_callback("/(\!)?\[\[([a-z0-9_]+)(\(.*\))?\]\]/siU", array(&$this,'__callbackPotion'), $txt);
		
		if(preg_match('/^([ ]+)\*.*$/m',$txt))
			$txt = $this->__callbackUL($txt);

		if(preg_match('/^([ ]+)(\d+|a|i)\..*$/m',$txt))
			$txt = $this->__callbackOL($txt);
		
		return parent::parse($txt,array());
	}
	
	function postHooksFormatting($txt)
	{
		$txt = preg_replace_callback("/\n\r|\n\n|\r\r/sU", array(&$this,'__callbackParagraph'), $txt);
		
		if(sizeof($this->_monospaceBlock)>0)
			$txt = $this->__replaceMonospace($txt);
		
		if($this->hasOpenParagraph)
			$txt.='</p>';
		
		return $txt;
	}
	
	/**#@+
	 * @access protected
	 * @param array $matches
	 * @return string
	 */
	/**
	 * 
	 */
	function __replaceTopicLink($matches)
	{
		if($matches[0]{0}=='!')
			return substr($matches[0],1);
		$topic_id = empty($matches[1])?$matches[2]:$matches[1];
		return sprintf('<a href="'.PATH_TO_DOCROOT.'/topic/%s">%s</a>',$topic_id,$matches[0]);
	}
	/**
	 * 
	 */
	function __replaceReportLink($matches)
	{
		if($matches[0]{0}=='!')
			return substr($matches[0],1);
		$report_id = empty($matches[1])?$matches[2]:$matches[1];
		return sprintf('<a href="'.PATH_TO_DOCROOT.'/report/%s">%s</a>',$report_id,$matches[0]);
	}
	/**
	 * 
	 */
	function __callbackParagraph($matches)
	{
		if($this->hasOpenParagraph)
			return '</p><p>';
		$this->hasOpenParagraph = true;
		return '<p>';
	}
	/**
	 * 
	 */
	function __callbackHeading($matches)
	{
		$h = strlen($matches[1]);
		$header = trim(str_replace('=','',strip_tags($matches[2])));
		$this->headers[] = array($h,$header);
		return '<h'.$h.'><a class="headertitle" name="'.str_replace(' ','',$header).'">'.$header.'</a></h'.$h.'>';
	}
	/**
	 * 
	 */
	function __callbackLink($matches)
	{
		$displayname = $toPage = $matches[1];
		if(preg_match('/ (.*)/',$matches[1],$match))
		{
			$toPage = str_replace($match[0], '', $matches[1]);
			$displayname = $match[1];
		}
		return '<a href="'.$toPage.'">'.$displayname.'</a>';
	}
	/**
	 * 
	 */
	function __callbackImage($matches)
	{
		$displayname = $toPage = $matches[1];
		if(preg_match('/ (.*)/',$matches[1],$match))
		{
			$toPage = str_replace($match[0], '', $matches[1]);
			$displayname = $match[1];
		}
		return '<img src="'.$toPage.'" alt="'.$displayname.'" />';
	}
	/**
	 * 
	 */
	function __callbackWiki($matches)
	{
		$displayname = $toPage = $matches[1];
		if(preg_match('/ (.*)/',$matches[1],$match))
		{
			$toPage = str_replace($match[0], '', $matches[1]);
			$displayname = $match[1];
		}
		return '<a href="'.PATH_TO_DOCROOT.'/wiki/'.$toPage.'">'.$displayname.'</a>';
	}
	/**
	 * 
	 */
	function __replaceMonospace($txt)
	{
		while(preg_match('/{{{(.*)}}}/siU',$txt))
			$txt = preg_replace('/{{{(.*)}}}/siU',array_shift($this->_monospaceBlock),$txt,1);
		return $txt;
	}
	/**
	 * 
	 */
	function __callbackMonospace($matches)
	{
		$this->_monospaceBlock[] = '<pre class="MonospaceFormat">'.trim($matches[1]).'</pre>';
		
		$txt = $matches[0];
		return $txt;
	}
	/**
	 * Unordered lists
	 */
	function __callbackUL($txt)
	{
		$lines = explode("\n",$txt);
		$numlines = sizeof($lines);
		$lastLI = false;
		$indentDepth = 0;
		for($i=0;$i<$numlines;$i++)
		{
			if(preg_match('/^([ ]+)\*.*/',$lines[$i],$matches))
			{
				$depth = ceil(strlen($matches[1])/2);
				$newline = '';
				if(($i-1)!==$lastLI)
				{
					for($j=0;$j<$depth;$j++)
						$newline .= "<ul>";
				}
				elseif($depth<$indentDepth)
				{
					for($j=$indentDepth;$j>$depth;$j--)
						$newline .= "</ul>";
				}
				elseif($depth>$indentDepth)
				{
					for($j=$indentDepth;$j<$depth;$j++)
						$newline .= "<ul>";
				}
				$newline .= str_replace(' *','<li>',$lines[$i]).'</li>';
				$lines[$i] = $newline;
				$lastLI = $i;
				$indentDepth = $depth;
			}
			elseif($lastLI==($i-1))
			{
				while($indentDepth)
				{
					$lines[($i-1)] .= '</ul>';
					$indentDepth--;
				}
			}
		}
		$txt = implode("\n",$lines);
		return $txt;
	}
	/**
	 * Ordered List
	 */
	function __callbackOL($txt)
	{
		$lines = explode("\n",$txt);
		$numlines = sizeof($lines);
		$lastLI = false;
		$lastType = false;
		$indentDepth = 0;
		for($i=0;$i<$numlines;$i++)
		{
			if(preg_match('/^([ ]+)(\d+|a|A|i|I).*/',$lines[$i],$matches))
			{
				$depth = ceil(strlen($matches[1])/2);
				$type = '';
				switch($matches[2])
				{
					case 'a':
						$type='loweralpha';
					break;
					case 'A':
						$type='upperalpha';
					break;
					case 'i':
						$type='lowerroman';
					break;
					case 'I':
						$type='upperroman';
					break;
					default:
						$type='';
				}
				$newline = '';
				if(($i-1)!==$lastLI)
				{
					for($j=0;$j<$depth;$j++)
						$newline .= "<ol class=\"".$type."\">";
				}
				elseif($depth<$indentDepth)
				{
					for($j=$indentDepth;$j>$depth;$j--)
						$newline .= "</ol>";
				}
				elseif($depth>$indentDepth)
				{
					for($j=$indentDepth;$j<$depth;$j++)
						$newline .= "<ol class=\"".$type."\">";
				}
				elseif($lastType!=$type)
				{
					$newline .= "</ol><ol class=\"".$type."\">";
				}
				$newline .= preg_replace('/^([ ]+)(\d+|a|A|i|I)\./','<li>',$lines[$i]).'</li>';
				$lines[$i] = $newline;
				$lastLI = $i;
				$indentDepth = $depth;
				$lastType = $type;
			}
			elseif($lastLI==($i-1))
			{
				while($indentDepth)
				{
					$lines[($i-1)] .= '</ol>';
					$indentDepth--;
				}
			}
		}
		$txt = implode("\n",$lines);
		return $txt;
	}
	
	/**
	 * 
	 */
	function __callbackCamelCase($args)
	{
		if($args[0]{0}=='!')
			return substr($args[0],1);
		
		$class = '';
		$show = $org = $args[0];
		if(!isset($this->wikilist[strtolower($args[0])]))
		{
			$class = ' class="notexists"';
			$show .= '?';
		}
			
		return '<a'.$class.' href="'.PATH_TO_DOCROOT.'/wiki/'.$org.'">'.$show.'</a>';
	}
	/**
	 * 
	 */
	function __callbackPotion($matches)
	{
		if($matches[1]=='!')
			return substr($matches[0],1);
		$potion = $matches[2];
		$args = isset($matches[3])?explode(',',str_replace('(','',str_replace(')','',$matches[3]))):array();
		
		if(!isset($this->wikipotions[$potion])||!$this->wikipotions[$potion])
			return $this->__disabledPotion($potion);
		require_once(WIKI_CLASSES.'WikiPotion.php');
		
		$potionObj = WikiPotionHandler::createPotion($this,$potion,$args);
		if(is_numeric($potionObj))
		{
			return $this->__notinstalledPotion($potion);
		}
		return $potionObj->display();
	}
	/**#@-*/
	/**
	 * @access protected
	 * @param string $potion
	 * @return string
	 */
	function __notinstalledPotion($potion)
	{
		$error = sprintf('Error, Potion `%s` (%s) could not be included.',$potion,SPELLBOOK.'Wiki/Potions/'.$potion.'.php');
		return VoodooError::displayError($error);
	}
	/**
	 * @access protected
	 * @param string $potion
	 * @return string
	 */
	function __disabledPotion($potion)
	{
		$error = sprintf('Error, Potion `%s` is not enabled. Please refer to your conf/wiki.ini to enable it.',$potion);
		return VoodooError::displayError($error);
	}
	/**
	 * Sets which wikipotions are available
	 * @param array $potions
	 */
	function setWikiPotions($potions)
	{
		$this->wikipotions = $potions;
	}
}
?>
