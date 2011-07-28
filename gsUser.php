<?php

/**
 * @author deVolf
 * @copyright 2010
 */

class gsUser extends gsAPI{
    
    private $username;
    private $userid;
    private $token;
    private $name;
    private $email;
    private $sex;
    private $dob;
    private $picture;
    private $premium; //1 = plus, 2 = anywhere
    private $profile;
    private $parent;
    private $favorites;
    private $library;
    private $playlists;
	
	function gsUser(&$parent=null){
	   if (!$parent) {
	       $this->parent = gsAPI::getInstance();
       } else {
            $this->parent = $parent;
       }
	}
    
    function toArray() {
        $array = array();
        if ($this->getUsername(false)) $array['Username'] = $this->getUsername(false);
        if ($this->getUserID(false)) $array['UserID'] = $this->getUserID(false);
        if ($this->getName()) $array['Name'] = $this->getName();
        if ($this->getEmail()) $array['Email'] = $this->getEmail();
        if ($this->getSex()) $array['Sex'] = $this->getSex();
        if ($this->getDOB()) $array['DOB'] = $this->getDOB();
        if ($this->getPicture()) $array['Picture'] = $this->getPicture();
        if ($this->getLevel(false) == 2) { $array['IsAnywhere'] = true; $array['IsPlus'] = true; }
        if ($this->getLevel(false) == 1) { $array['IsPlus'] = true; }
        if ($this->getLevel(false) === 0) { $array['IsPlus'] = false; $array['IsAnywhere'] = false; }
        if ($this->getProfile(false)) $array['Profile'] = $this->getProfile(false);
        if ($this->getFavorites(false) !== null) $array['Favorites'] = $this->getFavorites(false);
        if ($this->getLibrary()) $array['Library'] = $this->getLibrary(false);
        if ($this->getPlaylists(false) !== null) $array['Playlists'] = $this->getPlaylists(false);
        return $array;
    }
    
    public function setUsername($string) {        
        if (preg_match("/^([a-zA-Z0-9]+[\.\-_]?)+[a-zA-Z0-9]+$/",$string) === false){
		
			if (strpos($string, "@") !== false) {
				$this->setEmail($string);
				return $string;
			}
		
			return false;
		} else {
            $this->username = $string;
            return $string;
        }        
    }
    
    public function getUsername($fetch=true) {
        if (!empty($this->username) || !$fetch) {
            return $this->username;
        }
        /*if ($this->userid) {
           if ($this->getUserInfoFromUserID()) {
                return $this->username;
           }
        }*/
        if (is_object($this->parent) && $this->checkEmpty($this->parent->getSession())) {
            $this->importUserData($this->parent->getUserInfoFromSessionID());
            return $this->username;
        }
        return null;
    }
        
    
    //this method is access controlled
    public function getUserInfoFromUserID() {
        if ($this->getUserID()) {
    		$return = parent::apiCall('getUserInfoFromUserID', array('userID'=>$this->getUserID()));
    		if (isset($return['decoded']['result']['UserID'])) {
                $this->importUserData($return['decoded']['result']);
            	return $return['decoded']['result'];
    		} else {
    			return false;
            }
        }
    }
    
    public function setUserID($int) {        
        if (!$int || preg_match("/^([0-9]){1,10}$/",$int) === false){
			return false;
		} else {
            $this->userid = $int;
            return $int;
        }        
    }
    
    public function getUserID($fetch=true) {
        if (is_numeric($this->userid) || !$fetch) {
            return $this->userid;
        }
        if ($this->username) {
            return $this->getUserIDFromUsername($this->getUsername());
        }
        return null;
    }
    
	private function getUserIDFromUsername($username = null){
        if ($username) {
           $this->setUsername($username);
        }
        if ($this->getUsername()) {
    		$return = parent::apiCall('getUserIDFromUsername',array('username'=>$this->getUsername()));
    		if (isset($return['decoded']['result']['UserID'])) {
                $this->setUserID((int)$return['decoded']['result']['UserID']);
            	return $this->userid;
    		} else {
    			return false;
            }
        } else {
            return false;
        }
	}
    
    //1 = Plus, 2 = Anywhere
    public function setLevel($int) {
        if (is_numeric($int) && $int <= 2) { 
            $this->premium = (int)$int;
            return true;
        }
        return false;
    }
    
    public function getLevel($fetch=true) {
        if (is_numeric($this->premium) || !$fetch) {
            return $this->premium;
        }
        if (is_object($this->parent) && $this->getUserID(false) == $this->parent->sessionUserid && $this->parent->getSession()) {
            $this->importUserData($this->parent->getUserInfoFromSessionID());
            return $this->premium;
        }
        return false;
    }
    
    public function setFavorites($array) {
        if (is_array($array)) { 
            $this->favorites = $array;
            return true;
        }
        return false;
    }
    
    public function getFavorites($fetch=true) {
        if (is_array($this->favorites) || !$fetch) {
            return $this->favorites;
        }
        if (is_object($this->parent) && $this->getUserID(false) == $this->parent->sessionUserid && $this->parent->getSession()) {
            $this->favorites = $this->parent->getUserFavoriteSongs();
            return $this->favorites;
        }
        return false;
    }
    
    private function setPicture($file) {
        $this->picture = $file;
        return $file;
    }
    
    public function getPicture() {
        return $this->picture;
    }
    
    private function setDOB($dob) {
        $this->dob = $dob;
        return $dob;
    }
    
    public function getDOB() {
        return $this->dob;
    }
    
    private function setSex($sex) {
        $this->sex = $sex;
        return $sex;
    }
    
    public function getSex() {
        return $this->sex;
    }
    
    public function setName($name) {
        $this->name = $name;
        return $name;
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function setEmail($email) {
        $this->email = $email;
        return $email;
    }
    
    public function getEmail() {
        return $this->email;
    }
    
    public function getProfile($fetch=true) {
        if (!$this->profile && $fetch) {
            $this->profile = $this->getUserProfileService();
            return $this->profile;
        } else {            
            if ($this->getUserID(false)) { //if we already have the userID then no api call needed ;)
                $this->profile = $this->getUserProfileService();
            }            
            return $this->profile;
        }
    }
    
	private function getUserProfileService(){
        if (!$this->getUserID()) {
            return null;
        }
		return sprintf(parent::$listen_host."#/user/%s/%u",($this->getUsername(false) ? $this->getUsername(false) : "-"),$this->getUserID());
	}
    
    public function setPlaylists($array) {
        if (is_array($array)) {
            $this->playlists = $array;
            return true;
        }
        return false;
    }
    
    public function getPlaylists($fetch=true) {
        if (is_array($this->playlists) || !$fetch) {
            return $this->playlists;
        }
        if (is_object($this->parent) && $this->getUserID(false) == $this->parent->sessionUserid && $this->parent->getSession()) {
            $playlists = $this->parent->getUserPlaylists();
            $aPlaylists = array();
            if (is_array($playlists)) {
                foreach($playlists as $playlist) {
                    $p = new gsPlaylist();
                    $p->importPlaylistData($playlist);
                    $aPlaylists[] = $p;
                }
                $this->playlists = $aPlaylists;
            }
            return $this->playlists;
        }
        return null;
    }
    
    //this method is access protected on Grooveshark's end
    public function getPlaylistsFromID($fetch=true) {
        if (is_array($this->playlists) || !$fetch) {
            return $this->playlists;
        }
        if (!$this->getUserID()) {
            $playlists = $this->parent->getUserPlaylistsByUserID($this->userid);
            $aPlaylists = array();
            if (is_array($playlists)) {
                foreach($playlists as $playlist) {
                    $p = new gsPlaylist();
                    $p->importPlaylistData($playlist);
                    $aPlaylists[] = $p;
                }
                $this->playlists = $aPlaylists;
            }
            return $this->playlists;
        }
        return null;
    }
    
    public function setLibrary($array) {
        if (is_array($array)) {
            $this->library = $array;
            return true;
        }
        return false;
    }
    
    public function getLibrary($fetch=true) {
        if (is_array($this->library) || !$fetch) {
            return $this->library;
        }
        if (is_object($this->parent) && $this->getUserID(false) == $this->parent->sessionUserid && $this->parent->getSession()) {
            $library = $this->parent->getUserLibrarySongs();
            $this->library = $library;
            return $this->library;
        }
        return null;
    }
    
    public function importUserData($data) {
        if (is_array($data)) {
            if (isset($data['UserID'])) {
                $this->setUserID($data['UserID']);
            }
            if (isset($data['Username'])) {
                $this->setUsername($data['Username']);
            }
            if (isset($data['IsPremium'])) {
                if ($data['IsPremium']) {
                    $this->setLevel(2);
                } else {
                    $this->setLevel(0);
                }
            }
            if (isset($data['IsPlus']) && $data['IsPlus']) {
                $this->setLevel(1);
            }
            if (isset($data['IsAnywhere']) && $data['IsAnywhere']) {
                $this->setLevel(2);
            }
            if ((!isset($data['IsAnywhere']) || !$data['IsAnywhere']) && (!isset($data['IsPlus']) || !$data['IsPlus'])) {
                $this->setLevel(0);
            }
            if (isset($data['Level'])) { //custom gsUser field
                $this->setLevel($data['Level']);
            }
            if (isset($data['FName'])) {
                $this->setName($data['FName']);
            }
            if (isset($data['TSDOB'])) {
                $this->setDOB($data['TSDOB']);
            }
            if (isset($data['Picture'])) {
                $this->setPicture($data['Picture']);
            }
            if (isset($data['Email'])) {
                $this->setEmail($data['Email']);
            }
            if (isset($data['Sex'])) {
                $this->setSex($data['Sex']);
            }
            if (isset($data['Favorites'])) {
                $this->setFavorites($data['Favorites']);
            }            
            if (isset($data['Playlists'])) {
                $this->setPlaylists($data['Playlists']);
            }
            if (isset($data['Library'])) {
                $this->setLibrary($data['Library']);
            }
            return true;
        } else {
            return false;
        }
    }
    
    public function setToken($token) {
        $this->token = $token;
        return $this->token;
    }
    
    public function setTokenFromPassword($password) {
        $this->token = md5($password);
        return $this->token;
    }
    
    public function getToken() {
        return $this->token;
    }
    
    public function authenticate() {
        if ($this->parent->authenticateUser($this) === false) {
            return false;
        } else {
            return true;
        }
    }
    
    public function getSession() {
        return $this->parent->session;
    }
       
}

?>