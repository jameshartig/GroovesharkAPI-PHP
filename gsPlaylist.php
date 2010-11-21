<?php

/**
 * @author deVolf
 * @copyright 2010
 */

class gsPlaylist extends gsAPI{
    
    private $parent;
    private $playlistid;
    private $name;
    private $songs;
    private $tsmodified;
	
	function gsPlaylist($parent=null){
	   if (is_object($parent)) {
	       $this->parent = $parent;
	   }
	}
    
    protected function spawnAble(&$parent=null){
	   if (is_object($parent)) {
	       $this->parent = $parent;
	   }        
    }
    
    public function getModified() {
        if (!empty($this->tsmodified)) {
            return $this->tsmodified;
        }
        if ($this->checkEmpty($this->getPlaylistID())) {
            $this->importPlaylistData(parent::getPlaylistInfo($this->getPlaylistID()));
            return $this->tsmodified;
        }
        return null;
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
    
    protected function importPlaylistData($data) {
        if (is_array($data)) {
            if (isset($data['PlaylistID'])) {
                $this->setUserID($data['PlaylistID']);
            }
            if (isset($data['Name'])) {
                $this->setUsername($data['Name']);
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