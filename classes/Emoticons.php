<?php
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package VoodooCore
 * @subpackage Emoticons
 * @since 31-dec-2006
 * @license www.dalines.org/license
 * @copyright 2006-2007, Dalines Software Library
 */
class Emoticons
{
	/**
	 * @var string $content
	 */
	var $content;
	/**
	 * @var string $location (yahoo,tweakers)
	 */
	var $location;
	/**
	 * The list with all available emoticons for a certain emoticon theme
	 * This is cached in the session of the user, so it doesn have to loop the emoticons directory on every request.
	 * 
	 * @var array $available
	 */
	var $available = array();
	/**
	 * Constructor
	 * 
	 * TODO: rename channel_id to something better (ContextIdentifier or something)
	 * 
	 * @param string $content
	 * @param string $location
	 * @param mixed $channel_id
	 */
	function Emoticons($content,$location,$channel_id)
	{
		$this->content = $content;
		$this->location = IMAGES.'emoticons.'.$location;
		// We dont have it cached yet. Load them
		if(!isset($_SESSION['emoticons'][$channel_id]))
			$this->available[$channel_id] = $this->loadEmoticons($location,$channel_id);
		else
			$this->available[$channel_id] = $_SESSION['emoticons'][$channel_id];
	}
	/**
	 * Where oh where do we look for the emoticons
	 * @param string $location
	 * @param mixed $channel_id
	 * @return array $available
	 */
	function loadEmoticons($location,$channel_id)
	{
		$location = IMAGES.'emoticons.'.$location;
		if(!is_dir($location))
			return;
		$r = opendir($location);
		$available = array();
		while (($file = readdir($r)) !== false)
		{
			if ($file == "." || $file == "..") 
				continue;
			if(preg_match('/(.*)\.gif/',$file, $matches))
				$available[] = $matches[1];
		}
		$_SESSION['emoticons'][$channel_id] = $available;
		return $available;
	}
	/**
	 * Returns the content where all the emoticons are replaced.
	 * @param mixed $channel_id
	 * @return string $content
	 */
	function getContent($channel_id)
	{
		$pattern = $this->getReplaceMap($channel_id);
		$content = preg_replace(array_keys($pattern), $pattern, $this->content);
		
		return $content;
	}
	/**
	 * TODO: mkpretty
	 * 
	 * @param mixed $channel_id
	 * @return array $pattern
	 */
	function getReplaceMap($channel_id)
	{
		$pattern = array( "/:P/"=>'s_tongue', 
			"/:p/"=>'s_tongue', 
			"/;;\)/"=>'s_batting',
			"/:\+/"=>'s_clown',
			"/\|:\(/"=>'s_frusty',
			"/:\(/"=>'s_pout', 
			"/:\?/"=>'s_question', 
			"/;\)/"=>'s_wink', 
			"/:z/"=>'s_sleeping',
			"/:Z/"=>'s_sleepey',  
			"/\&gt;:D\&lt;/"=>'s_hug',
			"/:D/"=>'s_bigsmile', 
			"/_\/-\\o_/"=>'s_worship', 
			"/_O_/"=>'s_worship',
			"/:Y\)/"=>'s_fork',
			"/}:O/"=>'s_cow',
			"/:'\(/"=>'s_cry',
			"/:O/"=>'s_yawn', 
			"/8\)/"=>'s_glasses', 
			"/:}/"=>'s_tongue_ani', 
			"/:E/"=>'s_halloween', 
			"/:S/"=>'s_worried', 
			"/}\)/"=>'s_devil', 
			"/O-\)/"=>'s_angel',
			"/:T/"=>'s_bonk',
			"/\*:\|\*/"=>'s_nocheer',
			"/\*:\)\*/"=>'s_cheer',
			"/:r/"=>'s_barf', 
			"/:W/"=>'s_bye',
			"/:N/"=>'s_no',
			"/_O-/"=>'s_rofl',
			"/:X/"=>'s_shutup',
			"/\^O\^/"=>'s_thumbs',
			"/:Y/"=>'s_yes',
			"/:\)P/"=>'s_drool',
			"/:\)/"=>'s_regular', 
			"/:-\)/"=>'s_regular',
			"/:o/"=>'s_eek',
			"/_O\^/"=>'s_rofl',
			"/:X/"=>'s_heart',
			"/O\+/"=>'s_heart',
			"/:'\(/"=>'s_cry',
			"/:9\~/"=>'s_drool',
			"/:9/"=>'s_yummie',
			"/:\*/"=>'s_puh',
			"/\^\)/"=>'s_marry',
			"/\*;/"=>'s_lovers',
			"/:\{w/"=>'s_wait',
			"/o\|O/"=>'s_bat',
			"/\~O\&gt;/"=>'s_whip',
			"/:%/"=>'s_push',
			"/c\_\//"=>'s_coffee',
			"/:borat:/"=>'s_borat',
			"/b\-\(/"=>'s_punch',
			"/&lt;:\-P/"=>'s_party',
			"/\@\-\)/"=>'s_hypnotize',
			"/:\&quot;\&gt;/"=>'s_blush',
			
			);
		// Ever heard of sprintf??
		$s = '<img src="'.PATH_TO_DOCROOT.'/'.$this->location.'/';
		$e = '" vspace="1" hspace="1" style="vertical-align: middle;" />';
		// Loop the patterns and add if available
		// This can be done more efficiently
		foreach($pattern as $emote => $name)
		{
			if(!is_array($this->available[$channel_id]))
				unset($pattern[$emote]);
			elseif(!in_array($name,$this->available[$channel_id]))
				unset($pattern[$emote]);
			else
				$pattern[$emote] = $s.$name.'.gif'.$e;
		}
		return $pattern;
	}
}
?>
