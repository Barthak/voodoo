<?php
require_once(CLASSES.'AbstractObject.php');
/**
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage Attachment
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class Attachment extends AbstractObject
{
	/**
	 * @var int $id
	 */
	var $id;
	/**
	 * @var User $user
	 */
	var $user;
	/**
	 * @var string $name
	 */
	var $name;
	/**
	 * @var string $description
	 */
	var $description;
	/**
	 * MD5 hash of the file (md5_file())
	 * @var string $checksum 
	 */
	var $checksum;
	/**
	 * @var string $created
	 */
	var $created;
	/**
	 * @var string $lastupdate
	 */
	var $lastupdate;
	/**
	 * @var string $error
	 */
	var $error;
	/**
	 * This is the filesize in bytes
	 * @var int $filesize
	 */
	var $filesize;
	/**
	 * Set the attachment vars by the id
	 * @param int $id
	 * @return bool
	 */
	function set($id=null)
	{
		$id || $id = $this->id;
		return $this->_set($id);
	}
	/**
	 * Set the attachment vars by the attachment name
	 * @param string $name
	 * @return bool
	 */
	function setByName($name=null)
	{
		$name || $name = $this->name;
		return $this->_set($name);
	}
	/**
	 * @param int|string $id
	 * @return bool
	 */
	function _set($id)
	{
		$field = is_numeric($id)?'ATTACHMENT_ID':'NAME';
		
		$sql = "SELECT ATTACHMENT_ID, USER_ID, `NAME`, DESCRIPTION, 
				CHECKSUM, FILESIZE, CREATED, LAST_UPDATE
			FROM TBL_ATTACHMENT 
			WHERE %s = ??";
		$q = $this->db->query(sprintf($sql,$field));
		$q->bind_values($id);
		$q->execute();
		if(!$q->rows())
			return false;
		
		$r = $q->fetch();
		$this->id = $r->ATTACHMENT_ID;
		$this->user = new User($this->db,$r->USER_ID);
		$this->name = $r->NAME;
		$this->description = $r->DESCRIPTION;
		$this->checksum = $r->CHECKSUM;
		$this->filesize = $r->FILESIZE;
		$this->created = $r->CREATED;
		$this->lastupdate = $r->LAST_UPDATE;
		
		return $this->complete = true;
	}
	/**
	 * Delete the attachment
	 * 
	 * Will also unlink the file if the second argument is true (default)
	 * 
	 * @param int $id
	 * @param bool $removefile
	 * @return bool
	 */
	function delete($id=null,$removefile=true)
	{
		$id || $id = $this->id;
		
		$this->isComplete() || $this->set($id);
		
		$conf = VoodooIni::load('attachment');
		$file = PATH_TO_DOCROOT.$conf['settings']['upload_dir'].'/'.$this->name;
		$removefile && unlink($file);
		
		$sql = "DELETE FROM TBL_ATTACHMENT WHERE ATTACHMENT_ID = ??";
		$q = $this->db->query($sql);
		$q->bind_values($id);
		return $q->execute();
	}
	/**
	 * Upload and move the file
	 * @param string $description
	 * @param array $setup (see the attachment.ini)
	 * @param array $file
	 * @param bool $overwrite
	 * @return bool
	 */
	function upload($description,$setup,$file,$overwrite=false)
	{
		if(!is_uploaded_file($file['tmp_name']))
		{
			$this->error = 'Possible attack';
			return false;
		}
		if($file['size']>$setup['max_filesize'])
		{
			$this->error = 'File is too large';
		}
		if(!($type = $this->getFileType($file['name'])))
		{
			$this->error = 'Incorrect filetype';
			return false;
		}
		$types = explode(',',$setup['allow_types']);
		if(!in_array($type,$types))
		{
			$this->error = 'Incorrect filetype';
			return false;
		}
		$fname = $overwrite?basename($file['name']):$this->getFileName($type,$_SERVER['DOCUMENT_ROOT'].PATH_TO_DOCROOT.'/'.$setup['upload_dir'].'/',basename($file['name']));
		$uploadfile = $_SERVER['DOCUMENT_ROOT'].PATH_TO_DOCROOT.'/'.$setup['upload_dir'].'/'.$fname;
		if(!move_uploaded_file($file['tmp_name'],$uploadfile))
		{
			$this->error = 'Could not move uploaded file (permissions?)';
			return false;
		}
		$this->setProperties(array(
			'user'=>new User($this->db,$_SESSION['user_id']),
			'description'=>$description,
			'name'=>$fname,
			'checksum'=>md5_file($uploadfile),
			'filesize'=>$file['size'],
			'created'=>date('Y-m-d H:i:s'),
			'lastupdate'=>date('Y-m-d H:i:s')));
		return $this->insert();
	}
	/**
	 * Get the filename to save it to.
	 * 
	 * If file.name.ext already exists, make the new name file.name.1.ext, etc. Recursive.
	 * 
	 * @param string $type
	 * @param string $path
	 * @param string $name
	 * @param int $i
	 * @return string $name
	 */
	function getFileName($type,$path,$name,$i='')
	{
		$newname = preg_replace('/'.($i?'\.'.$i:'').'\.'.$type.'/','.'.++$i.'.'.$type,$name);
		$uploadfile = $path.$name;
		if(is_file($uploadfile))
			return $this->getFileName($type,$path,$newname,$i);
		return $name;
	}
	/**
	 * Gets the filetype (extension) of a file
	 * @param string $fname
	 * @return bool|$string
	 */
	function getFileType($fname)
	{
		$info = pathinfo($fname);
		if(!isset($info['extension']))
			return false;
		return $info['extension'];
	}
	/**
	 * Get the error that was triggered during upload
	 * @return string
	 */
	function getError()
	{
		return $this->error;
	}
	/**
	 * Mapping between file extensions and their content type.
	 * @return array
	 */
	function getContentTypeMap()
	{
		 return array(
			'jpg'=>'image',
			'jpeg'=>'image',
			'png'=>'image',
			'gif'=>'image',
			'zip'=>'application',
			'gz'=>'application',
			'tar'=>'application',
			'rar'=>'application',
			'pdf'=>'application',
			'xls'=>'application',
			'swf'=>'application',
			'txt'=>'text',
			'csv'=>'text'
			);
	}
	/**
	 * Format the size in bytes to a nicer readable output
	 * @static 
	 * @param int $sizeInBytes
	 * @param int $precision
	 * @return string
	 */
	function prettyBytes($sizeInBytes,$precision=2)
	{
		return ($sizeInBytes < 1024)?"$sizeInBytes bytes":round(($sizeInBytes / pow(1024,floor(log($sizeInBytes,1024)))),$precision)." ".substr(" KMGT",log($sizeInBytes,1024),1)."b";
	}
	/**
	 * @access protected
	 */
	function _initObjectMaps()
	{
		$this->objectmap['dbtable'] = 'TBL_ATTACHMENT';
		$this->objectmap['primary']['id'] = 'ATTACHMENT_ID';
		$this->objectmap['properties']['user->id'] = 'USER_ID';
		$this->objectmap['properties']['name'] = 'NAME';
		$this->objectmap['properties']['description'] = 'DESCRIPTION';
		$this->objectmap['properties']['checksum'] = 'CHECKSUM';
		$this->objectmap['properties']['filesize'] = 'FILESIZE';
		$this->objectmap['properties']['created'] = 'CREATED';
		$this->objectmap['properties']['lastupdate'] = 'LAST_UPDATE';
		
		// This is used to do the createTables in the setup
		$this->sqltypemap['id'] = 'INT(11) NOT NULL AUTO_INCREMENT';
		$this->sqltypemap['user->id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['name'] = 'VARCHAR(255) NULL';
		$this->sqltypemap['description'] = 'VARCHAR(255) NULL';
		$this->sqltypemap['checksum'] = 'CHAR(32) NOT NULL';
		$this->sqltypemap['filesize'] = 'INT(11) NOT NULL';
		$this->sqltypemap['created'] = 'DATETIME NOT NULL';
		$this->sqltypemap['lastupdate'] = 'DATETIME NOT NULL';
		
		$this->sqlprops['unique'] = 'UNIQUE(`NAME`)';
		$this->sqlprops['index'] = 'INDEX(`NAME`)';
	}
}
/**
 * Default link object between Attachments and other controllers
 * 
 * $type = Wiki, Topic, Reply etc.
 * 
 * @author Marten Koedam <marten@dalines.net>
 * @package AttachmentController
 * @subpackage Attachment
 * @since 19-okt-2007
 * @license www.dalines.org/license
 * @copyright 2007, geopelepsis.complicated
 */
class AttachmentLink extends AbstractObject
{
	/**
	 * @var string $type
	 */
	var $type;
	/**
	 * @var Object $linked
	 */
	var $linked;
	/**
	 * @var Attachment $attachment
	 */
	var $attachment;
	/**
	 * @param Database $db
	 * @param string $type
	 * @param array $args
	 * @param bool $set
	 */
	function AttachmentLink(&$db,$type,$args=array(),$set=false)
	{
		$this->type = $type;
		$this->AbstractObject($db,$args,$set);
	}
	/**
	 * Delete the link between an attachment and $type
	 * @param int $id
	 * @param int $linked
	 * @return bool
	 */
	function delete($id=null,$linked=null)
	{
		$id || $id = $this->attachment->id;
		$linked || $linked = $this->linked->id;
		if(!is_numeric($linked))
			$linked = $this->setLinkedIdFromString($linked);
		
		$sql = sprintf("DELETE FROM %s WHERE ATTACHMENT_ID = ?? AND %s = ??",
			$this->objectmap['dbtable'],
			$this->objectmap['primary']['linked->id']);
		$q = $this->db->query($sql);
		$q->bind_values($id,$linked);
		return $q->execute();
	}
	/**
	 * Insert the link between $type and the Attachment
	 * @return bool
	 */
	function insert()
	{
		if(!is_numeric($this->linked->id))
			$this->setLinkedIdFromString($this->linked->id);
		return parent::insert();
	}
	/**
	 * If the provided id is a string, do a lookup by using default function setByName($id) on the object $type
	 * 
	 * If this doesnt work for your Controller+Attachment link, create your own Attachment$type object
	 * Please refer to the AttachmentController Wiki page for more information on this.
	 * 
	 * @param string $id
	 * @return bool|int
	 */
	function setLinkedIdFromString($id)
	{
		$class = ucfirst(strtolower($this->type));
		$dir = constant($this->type.'_CLASSES');
		if(!is_file($dir.$class.'.php'))
			trigger_error('(1) Incorrect usage of Attachment (possible controller specific class necessary?)',E_USER_ERROR);
		
		require_once($dir.$class.'.php');
		$obj = new $class($this->db);
		if(!$obj->setByName($id))
			return false;//trigger_error('(2) Incorrect usage of Attachment (possible controller specific class necessary?)',E_USER_ERROR);
		$this->linked = $obj;
		return $obj->id;
	}
	/**
	 * Get all the attachments (overview) for a linked object
	 * @param int|string $id
	 * @return ResultSet $q
	 */
	function getAttachmentsForLink($id=null)
	{
		$id || $id = $this->linked->id;
		if(!is_numeric($id))
			$id = $this->setLinkedIdFromString($id);
		if(!$id)
			return false;
		
		$sql = "SELECT A.`ATTACHMENT_ID`, A.`NAME`, U.`USER_NAME`, A.`LAST_UPDATE`, A.`DESCRIPTION`, A.`FILESIZE`
			FROM %s as AL
			INNER JOIN TBL_ATTACHMENT as A
				ON AL.ATTACHMENT_ID = A.ATTACHMENT_ID
			INNER JOIN TBL_USER as U
				ON A.USER_ID = U.USER_ID
			WHERE %s = ??";
		$q = $this->db->query(sprintf($sql,$this->objectmap['dbtable'],$this->objectmap['primary']['linked->id']));
		$q->bind_values($id);
		$q->execute();
		return $q;
	}
	/**
	 * @access protected
	 */
	function _initObjectMaps()
	{
		$this->objectmap['dbtable'] = 'TBL_'.$this->type.'_ATTACHMENT';
		$this->objectmap['primary']['linked->id'] = $this->type.'_ID';
		$this->objectmap['primary']['attachment->id'] = 'ATTACHMENT_ID';
		// This is used for createTables in the Setup
		$this->sqltypemap['link->id'] = 'INT(11) NOT NULL';
		$this->sqltypemap['attachment->id'] = 'INT(11) NOT NULL';
	}
}
?>
