<?php
/**
 * Displays a list of recent changes. 
 * Defaults to 30 days, but the optional argument of this function is an integer for the number of days to show
 * 
 * Note: This required MySQL >= 5.0
 * TODO: make work with MySQL < 5 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage WikiPotion
 * @since 4-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiRecentChanges extends WikiPotion
{
	/**
	 * Valid arguments [1] integer
	 */
	function init()
	{
		$args = $this->args;
		$numofdaysinpast = 30;
		if(isset($args[0])&&((int)$args[0]==$args[0]))
			$numofdaysinpast = (int)$args[0];
			
		$sql = "SELECT DISTINCT WIKI_HANDLE, 
				DAY(REVISION_DATETIME) as MAX_DAY, 
				MONTH(REVISION_DATETIME) as MONTH, 
				YEAR(REVISION_DATETIME) as YEAR
			FROM TBL_WIKI as WP
			INNER JOIN TBL_WIKI_REVISION as WPR
				ON WP.WIKI_ID = WPR.WIKI_ID
			WHERE DATE_SUB(CURDATE(),INTERVAL ".$numofdaysinpast." DAY) <= REVISION_DATETIME
			GROUP BY WIKI_HANDLE, REVISION_DATETIME
			ORDER BY MAX_DAY DESC,WIKI_HANDLE";
		$q = $this->formatter->db->query($sql);
		$q->execute();
		$currentDay = false;
		$rv = '';
		while($r = $q->fetch())
		{
			$pretime = '-'.$r->MONTH.'-'.$r->YEAR;
			if($currentDay != $r->MAX_DAY)
			{
				if($currentDay)
					$rv .= "</ul>";
				$rv .= sprintf("<h3>Changes on %s%s</h3><ul>",(($r->MAX_DAY < 10)?'0'.$r->MAX_DAY:$r->MAX_DAY), $pretime);
			}
			$rv .= sprintf('<li><a href="%s/wiki/%s">%s</a></li>',PATH_TO_DOCROOT,$r->WIKI_HANDLE,$r->WIKI_HANDLE);
			$currentDay = $r->MAX_DAY;
		}
		return $this->display = $rv.'</ul>';
	}
}
?>
