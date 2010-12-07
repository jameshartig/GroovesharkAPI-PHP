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
    
    public function setUsername($string) {        
        if (preg_match("/^([a-zA-Z0-9]+[\.\-_]?)+[a-zA-Z0-9]+$/",$string) === false){
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
        /*if ($this->checkEmpty($this->userid)) {
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
    		$return = parent::apiCall('getUserInfoFromUserID', array('UserID'=>$this->getUserID()));
    		if (isset($return['decoded']['result'][0]['UserID'])) {
                $this->importUserData($return['decoded']['result'][0]);
            	return $return['decoded']['result'][0];
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
        if ($this->checkEmpty($this->username)) {
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
        }
	}
    
    //1 = Plus, 2 = Anywhere
    protected function setLevel($int) {
        if (is_numeric($int) && $int < 2) { 
            $this->premium = $int;
            return true;
        }
        return false;
    }
    
    public function getLevel($fetch=true) {
        if (is_numeric($this->premium) || !$fetch) {
            return $this->premium;
        }
        if (is_object($this->parent) && $this->checkEmpty($this->parent->getSession())) {
            $this->importUserData($this->parent->getUserInfoFromSessionID());
            return $this->username;
        }
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
    
    public function getProfile() {
        if (!$this->profile) {
            $this->profile = $this->getUserProfileService();
            return $this->profile;
        } else {
            return $this->profile;
        }
    }
    
    //TODO: make the name optional (save an API call)
	private static function getUserProfileService(){
        if (!$this->getUserID() && !$this->getUsername()) {
            return null;
        }
		return sprintf(parent::$listen_host."#/user/%s/%u",($this->getUsername() ? $this->getUsername() : "~"),$this->getUserID());
	}
    
    public function getPlaylists($fetch=true) {
        if (is_array($this->playlists) || $fetch) {
            return $this->playlists;
        }
        if ($this->checkEmpty($this->getUserID())) {
            $playlists = $this->parent->getUserPlaylists();
            $aPlaylists = array();
            if ($playlists) {
                foreach($playlists as $playlist) {
                    $p = new gsPlaylist();
                    $p->importPlaylistData($playlist);
                    $aPlaylists[] = $p;
                }                
            }
            return $playlists;
        }
        return null;
    }
    
    protected function importUserData($data) {
        if (is_array($data)) {
            if (isset($data['UserID'])) {
                $this->setUserID($data['UserID']);
            }
            if (isset($data['Username'])) {
                $this->setUsername($data['Username']);
            }
            //if (isset($data['IsPremium'])) {
            //    $this->setPremium($data['IsPremium']);
            //}
            if (isset($data['IsAnywhere']) && $data['IsAnywhere']) {
                $this->setLevel(2);
            }
            if (isset($data['IsPlus']) && $data['IsPlus']) {
                $this->setLevel(1);
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
        } else {
            return false;
        }
    }
    
    public function setToken($token) {
        $this->token = $token;
        return $this->token;
    }
    
    public function setTokenFromPassword($password) {
        $this->token = md5($this->getUsername().md5($password));
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
    
    private function checkEmpty($var) {
        if ($var === false){
            return false;
        }
        if ($var === null) {
            return false;
        }
        if ($var === "") {
            return false;
        }
        if ($var === 0) {
            return false;
        }
        return true;
    }
       
}

?>