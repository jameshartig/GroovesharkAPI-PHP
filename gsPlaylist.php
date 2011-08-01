<?php

/**
 * @author Karen Zhu
 * @copyright 2011
 */

class gsPlaylist extends gsAPI{
    
    private $parent;
    private $playlistid;
    private $url;
    private $name;
    private $songs;
    private $tsmodified;
    private $user;
	
	function gsPlaylist(&$parent=null){
	   if (!$parent) {
	       $this->parent = gsAPI::getInstance();
       } else {
            $this->parent = $parent;
       }
	}
    // get all the info for a playlist
    //YOU MUST CAST BACK INTO gsPlaylist USING importPlaylistData
    public static function getPlaylistInfo($playlistID){
		if (!is_numeric($playlistID)){
			return false;
		}
		
		$return = parent::apiCall('getPlaylistInfo',array('playlistID'=>$playlistID));
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
			return false;
        }
	}
    
    function toArray() {
        $array = array();
        if ($this->getPlaylistID()) $array['PlaylistID'] = $this->getPlaylistID();
        if ($this->getName(false)) $array['Name'] = $this->getName(false);
        if ($this->getURL(false)) $array['Url'] = $this->getURL(false);
        if ($this->getSongs(false) !== null) $array['Songs'] = $this->getSongs(false);
        if ($this->getModified()) $array['ModifiedTime'] = $this->getModified();
        if ($this->getUser()) $array['User'] = $this->getUser();
        return $array;
    }
    
    public function setPlaylistID($id) {
        if (!is_numeric($id)){
			return false;
		} else {
            $this->playlistid = $id;
            return $id;
        }
    }
    
    public function getPlaylistID() {
        return $this->playlistid;
    }
    
    public function setName($name) {
        $this->name = $name;
        return $name;
    }
    
    public function getName($fetch=true) {
        if (!empty($this->name) || !$fetch) {
            return $this->name;
        }
        if ($this->checkEmpty($this->playlistid)) {
            $this->importPlaylistData(self::getPlaylistInfo($this->getPlaylistID()));
            return $this->tsmodified;
        }
        return null;
    }
        
    public function setModified($val) {
        if (is_numeric($val)) { //assume its Unix time
            $this->tsmodified = $val;
        } else {
            if (($time = strtotime($val)) === false) {
                return false;
            } else {
                $this->tsmodified = $time;
            }
        }
        return true;
    }
    
    public function getModified() {
        return $this->tsmodified;
        //need to work with skyler on getting last Modified included
        /*if ($this->checkEmpty($this->getPlaylistID())) {
            $this->importPlaylistData(self::getPlaylistInfo($this->getPlaylistID()));
            return $this->tsmodified;
        }
        return null;*/
    }
    
    public function setUser($user) {
        $this->user = $user;
        return true;
    }
    
    public function getUser() { //should we cast as gsUser?
        return $this->user;
    }
    
    private function setURL($url) {
        $this->url = $url;
        return $url;
    }
    
    public function getURL() {
        return self::getPlaylistURLService($this->playlistid);
    }
    
	public static function getPlaylistURLService($playlistID){
		if (!is_numeric($playlistID)){
			return false;
		}		
		
		$return = parent::apiCall('getPlaylistURLFromPlaylistID', array('playlistID' => $playlistID));
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
			return false;
        }
	}
    
    public function setSongs($songs) {
        if (is_array($songs)) {
            $this->songs = $songs;
        }
    }
    
    public function getSongs($fetch=true) {
        if ($this->songs || !$fetch) {
            return $this->songs;
        }
        $this->songs = parent::getPlaylistSongs($this->getPlaylistID());
        return $this->songs;
    }
        
    public function importPlaylistData($data) {
        if (is_array($data)) {
            if (isset($data['PlaylistID'])) {
                $this->setPlaylistID($data['PlaylistID']);
            }
            if (isset($data['Name'])) {
                $this->setName($data['Name']);
            }
            if (isset($data['TSModified'])) {
                $this->setModified($data['TSModified']);
            }
            if (isset($data['ModifiedTime'])) { //gsPlaylist custom field
                $this->setModified($data['ModifiedTime']);
            }
            if (isset($data['Songs'])) { //gsPlaylist custom field
                $this->setSongs($data['Songs']);
            }
            if (isset($data['User'])) { //gsPlaylist custom field
                $this->setUser($data['User']);
            }
            return true;
        } else {
            return false;
        }
    }
    
}

?>