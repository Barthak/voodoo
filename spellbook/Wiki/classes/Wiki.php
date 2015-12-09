<?php
require_once(CLASSES.'AbstractObject.php');
/**
 * TODO: add IP/Hostname
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage Wiki
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class Wiki extends AbstractObject
{
	/**
	 * @var int $id
	 */
	var $id;
	/**
	 * @var string $handle
	 */
	var $handle;
	/**
	 * @var User $owner
	 */
	var $owner;
	/**
	 * @var string $created
	 */
	var $created;
	/**
	 * @var WikiRevision $revision
	 */
	var $revision;
	/**
	 * @param int $id
	 * @param int $revision
	 * @return bool
	 */
	function set($id=null,$revision=null)
	{
		$id || $id = $this->id;
		$sql = "SELECT WIKI_HANDLE, OWNER_ID, CREATED, REVISION_ID
			FROM TBL_WIKI as WP
			INNER JOIN TBL_WIKI_REVISION as WPR
				ON WP.WIKI_ID = WPR.WIKI_ID
			WHERE WP.WIKI_ID = ?? ";
		if($revision)
			$sql .= " AND REVISION_ID = '".$revision."' ";
		else
			$sql .= " ORDER BY REVISION_ID DESC LIMIT 0,1";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		if(!$q->rows())
			return false;
		
		$r = $q->fetch();
		$this->revision = new WikiRevision($this->db);
		$this->revision->set($id,$r->REVISION_ID);
		$this->id = $id;
		$this->handle = $r->WIKI_HANDLE;
		$this->owner = new User($this->db,$r->OWNER_ID);
		$this->create = $r->CREATED;
			
		return $this->complete = true;
	}
	function setByName($handle=null)
	{
		$handle || $handle = $this->handle;
		$sql = "SELECT WIKI_ID FROM TBL_WIKI WHERE WIKI_HANDLE = ??";
		$q = $this->db->query($sql);
		$q->bind_values($handle);
		$q->execute();
		if(!$q->rows())
			return false;
		$r = $q->fetch();
		return $this->set($r->WIKI_ID);
	}
	/**
	 * Get all the revisions for given wikipage
	 * @param int $id
	 * @return ResultSet $q
	 */
	function getRevisions($id=null)
	{
		$id || $id = $this->id;
		$sql = "SELECT REVISION_ID, REVISION_DATETIME, USER_ID
			FROM TBL_WIKI_REVISION
			WHERE WIKI_ID = ??
			ORDER BY REVISION_DATETIME DESC";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		if(!$q->rows())
			return false;
		return $q;
	}
	/**
	 * Get all available Wikis
	 * @return array $rv
	 */
	function getWikis()
	{
		$sql = "SELECT WIKI_HANDLE,WIKI_ID 
			FROM TBL_WIKI 
			ORDER BY WIKI_HANDLE";
		$q = $this->db->query($sql);
		$q->execute();
		$rv = array();
		if(!$q->rows())
			return $rv;
		while($r=$q->fetch())
			$rv[strtolower($r->WIKI_HANDLE)] = array('id'=>$r->WIKI_ID,'handle'=>$r->WIKI_HANDLE);
		return $rv;
	}
	/**
	 * Saves the wiki page to a new revision
	 * @param string $handle
	 * @param string $content
	 */
	function save($handle, $content)
	{
		$q = $this->db->query();
		// Start a transaction
		$q->begin(); 
		$this->handle = $handle;
		$wpr = new WikiRevision($this->db);
		$wpr->wiki =& $this;
		$wpr->content = $content;
		$this->insert();
		$wpr->insert();
		// Commit it.
		$q->commit();
	}
	/**
	 * Inserts a new revision
	 * @param string $content
	 */
	function update($content)
	{
		$wpr = new WikiRevision($this->db);
		$wpr->wiki =& $this;
		$wpr->content = $content;
		$wpr->insert();
	}
	/**
	 * Insert the wikipage into the database
	 * 
	 * Automatically sets the current date as creation and adds the current logged in user as the owner.
	 */
	function insert()
	{
		$this->owner = new User($this->db,$_SESSION['user_id']);
		$this->created = date('Y-m-d H:i:s');
		parent::insert();
	}
	/**
	 * Delete a wiki page and all its revisions
	 * @param int $id
	 * @return bool
	 */
	function delete($id=null)
	{
		$id || $id = $this->id;
		
		$commit = $this->db->query();
		$commit->begin();
		
		// Delete all the revisions first (db contraints)
		$sql = "DELETE FROM TBL_WIKI_REVISION WHERE WIKI_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		
		// Delete the actual page entry
		$sql = "DELETE FROM TBL_WIKI WHERE WIKI_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		
		// Commit the database changes
		$commit->commit();
		return true;
	}
	/**
	 * @access protected
	 * Initalizes the object maps
	 */
	function _initObjectMaps()
	{
		$this->objectmap['dbtable'] = 'TBL_WIKI';
		$this->objectmap['primary']['id'] = 'WIKI_ID';
		$this->objectmap['properties']['handle'] = 'WIKI_HANDLE'; // CamelCase for lookup
		$this->objectmap['properties']['owner->id'] = 'OWNER_ID';
		$this->objectmap['properties']['created'] = 'CREATED';
		
		$this->sqltypemap['id'] = 'INT(11) NOT NULL AUTO_INCREMENT';
		$this->sqltypemap['handle'] = 'VARCHAR(32) NOT NULL';
		$this->sqltypemap['owner->id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['created'] = 'DATETIME NOT NULL';
		
		$this->sqlprops['unique'] = 'UNIQUE(`WIKI_HANDLE`)';
		$this->sqlprops['index'] = 'INDEX(`WIKI_HANDLE`)';
	}
}
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package WikiController
 * @subpackage Wiki
 * @since 2-feb-2007
 * @license www.dalines.org/license
 * @copyright 2007, Dalines Software Library
 */
class WikiRevision extends AbstractObject
{
	/**
	 * @var Wiki $wiki
	 */
	var $wiki;
	/**
	 * @var int $revision_id
	 */
	var $revision_id;
	/**
	 * User that saved the revision, this can be different from the owner.
	 * @var User $user 
	 */
	var $user;
	/**
	 * @var string $content
	 */
	var $content;
	/**
	 * @var string $revision_datetime
	 */
	var $revision_datetime;
	/**
	 * Set the revision
	 * @param int $wiki_id
	 * @param int $revision_id
	 * @return bool
	 */
	function set($wiki_id=null,$revision_id=null)
	{
		$wiki_id || $wiki_id = $this->wiki->id;
		$revision_id || $revision_id = $this->revision_id;
		
		$sql = "SELECT REVISION_DATETIME, USER_ID, WIKI_CONTENT
			FROM TBL_WIKI_REVISION
			WHERE WIKI_ID = ?? AND REVISION_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($wiki_id,$revision_id);
		$q->execute();
		if(!$q->rows())
			return false;
		$r = $q->fetch();
		
		$this->wiki = new Wiki($this->db,$wiki_id);
		$this->revision_id = $revision_id;
		$this->content = $r->WIKI_CONTENT;
		$this->revision_datetime = $r->REVISION_DATETIME;
		$this->user = new User($this->db,$r->USER_ID);
		
		return $this->complete = true;
	}
	/**
	 * Automatically adds the next revision id, the user who does the save and the current timestamp
	 */
	function insert()
	{
		$this->revision_id = $this->_getNextRevision();
		$this->user = new User($this->db,$_SESSION['user_id']);
		$this->revision_datetime = date('Y-m-d H:i:s');
		parent::insert();
	}
	/**
	 * Gets the next available revision id, return 1 in case this is a new revision
	 * @access private
	 * @param int $id
	 * @return int
	 */
	function _getNextRevision($id=null)
	{
		$id || $id = $this->wiki->id;
		$sql = "SELECT IFNULL((MAX(REVISION_ID)+1),1) as REV 
			FROM TBL_WIKI_REVISION
			WHERE WIKI_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		$q->execute();
		$r = $q->fetch();
		return $r->REV;
	}
	/**
	 * Initalizes the object maps 
	 */
	function _initObjectMaps()
	{
		$this->objectmap['dbtable'] = 'TBL_WIKI_REVISION';
		$this->objectmap['primary']['wiki->id'] = 'WIKI_ID';
		$this->objectmap['primary']['revision_id'] = 'REVISION_ID';
		$this->objectmap['properties']['revision_datetime'] = 'REVISION_DATETIME'; // CamelCase for lookup
		$this->objectmap['properties']['user->id'] = 'USER_ID';
		$this->objectmap['properties']['content'] = 'WIKI_CONTENT';
		
		$this->sqltypemap['wiki->id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['revision_id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['revision_datetime'] = 'DATETIME NOT NULL';
		$this->sqltypemap['user->id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['content'] = 'TEXT NOT NULL';
	}
}
/**

CREATE TABLE TBL_WIKI
(
	WIKI_ID INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	WIKI_HANDLE VARCHAR(32) NOT NULL,
	OWNER_ID INT(11) NOT NULL,
	CREATED DATETIME NOT NULL,
	
	INDEX(WIKI_HANDLE),
	UNIQUE(WIKI_HANDLE),
	PRIMARY KEY(WIKI_ID)
);

CREATE TABLE TBL_WIKI_REVISION
(
	WIKI_ID INT(11) UNSIGNED NOT NULL,
	REVISION_ID INT(11) UNSIGNED NOT NULL,
	REVISION_DATETIME DATETIME NOT NULL,
	WIKI_CONTENT TEXT NOT NULL,
	USER_ID INT(11) NOT NULL,
	
	PRIMARY KEY(WIKI_ID,REVISION_ID)
);


**/
?>
