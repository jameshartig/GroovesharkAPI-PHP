<?php

/**
 * @author deVolf
 * @copyright 2010
 */

class gsPlaylist extends gsAPI{
    
    private $parent;
    private $playlistid;
    private $url;
    private $name;
    private $songs;
    private $tsmodified;
	
	function gsPlaylist(&$parent=null){
	   if (is_object($parent)) {
	       $this->parent = $parent;
	   }
	}
    
    protected function spawnAble(&$parent=null){
	   if (is_object($parent)) {
	       $this->parent = $parent;
	   }        
    }
    
    public function setPlaylistID($id) {
        if (preg_match("/^([0-9]){1,16}$/",$id) === false){
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
    
    public function getName() {
        if (!empty($this->name)) {
            return $this->name;
        }
        if ($this->checkEmpty($this->playlistid)) {
            $this->importPlaylistData($this->getPlaylistInfo($this->getPlaylistID()));
            return $this->tsmodified;
        }
        return null;
    }
    
    public static function getPlaylistInfo($playlistID){
		if (!is_numeric($playlistID)){
			return false;
		}		
		
		$return = parent::apiCall('getPlaylistInfo',array('playlistID'=>$playlistID));
		if (isset($return['decoded']['result'])) {
            $this->importPlaylistData($return['decoded']['result']);
			return $return['decoded']['result'];
		} else {
			return false;
        }
	}
    
    private function setModified($val) {
        if (is_numeric($val)) { //assume its Unix time
            $this->tsmodified;
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
        /*if ($this->checkEmpty($this->getPlaylistID())) {
            $this->importPlaylistData(parent::getPlaylistInfo($this->getPlaylistID()));
            return $this->tsmodified;
        }
        return null;*/
    }
    
    private function setURL($url) {
        $this->url = $url;
        return $url;
    }
    
    //TODO: make the name optional (save an API call)
    public function getURL() {
        if ($this->getPlaylistID()) {
            return sprintf(parent::$listen_host."#/playlist/%s/%u",($this->getName() ? $this->getName() : "~"),$this->getPlaylistID());
        } else {
            return null;
        }
    }
    	
	/*
    Not needed.
	*/
	public static function getPlaylistURLService($playlistID){
		if (!is_numeric($playlistID)){
			trigger_error(__FUNCTION__." requires a valid playlistID. The playlistID provided was invalid.",E_USER_ERROR);
			return false;
		}		
		
		$return = parent::apiCall('getPlaylistURLFromPlaylistID',array('playlistID'=>$playlistID));
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
	}
    
    protected function importPlaylistData($data) {
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
        } else {
            return false;
        }
    }
    
}

?>