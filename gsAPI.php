<?php

/**
 * Grooveshark API Class
 * @author James Hartig
 * @copyright 2011
 * Released under GNU General Public License v3 (See LICENSE for license)
 */

/*

Recommended to be called like $gsapi = new gsapi(key,secret)
YOU MUST SET THE KEY,SECRET EITHER BELOW OR LIKE ABOVE!

Note: even if you are only using the static functions, calling $gsapi = new gsapi(key,secret) will set the key and secret for those as well

*/

class gsAPI{
	
	private static $api_host = "api.grooveshark.com/ws3.php"; //generally don't change this
	protected static $listen_host = "http://grooveshark.com/"; //change this to preview.grooveshark.com if you are with VIP //this could potentially automatically be done...
	private static $ws_key;
	private static $ws_secret;
	protected $session;
    protected $sessionUserid;
    protected $country;
    public static $headers;
    public static $lastError;
    
    private static $instance;
	
	/*	
	* Construct gsapi
	
	Requirements: none
	Static Function
	*/	
	function gsAPI($key=null,$secret=null){
		if	(!empty($key))
			self::$ws_key = $key;
		if	(!empty($secret))
			self::$ws_secret = $secret;
		
		if (empty(self::$ws_key) || empty(self::$ws_secret))
			trigger_error("gsapi class requires a valid key and secret.",E_USER_ERROR);

        if (!isset(self::$instance)) {
            self::$instance = $this;
        }
        self::$headers = array();        
	}
    
    static function getInstance() {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c;
        }        
        return self::$instance;
    }
    
    /*	
	* Ping Grooveshark to make sure Pickles is sleeping
	
	Requirements: none
	Static Function
	*/	
	public static function pingService(){
		return self::apiCall('pingService',array());
	}
	
	/*	
	* Start a new session
	
	Requirements: none
	Even though this function requires nothing, it is not static
	*/	
	public function startSession(){
		$return = self::apiCall('startSession', array(), true);

		if (isset($return['decoded']['result']['success']) && $return['decoded']['result']['success'] === true){
			$this->session = $return['decoded']['result']['sessionID'];
			return $this->session;
		} else {
		    gsAPI::$lastError = $return['raw'];
            return false;
		}			
	}
		
	/*	
	* Start a new session provided an existing session key
	
	Requirements: none
	Even though this function requires nothing, it is not static
	*/	
	public function setSession($session, $userid=false){
		$this->session = $session;
        $this->sessionUserid = $userid;
		return $session;
	}
	
	/*
	* Returns the current SessionID
	* It is highly recommended to store this instead of username/token
	
	Requirements: session	
	*/
	public function getSession(){
	   return $this->session;
	}
	
	/*
	* Ends the current session
	* Do this if you do not plan on using the user again
	
	Requirements: session	
	*/
	public function logout(){
		if (empty($this->session)){
			return false;
		}
		
		$return = self::apiCall('logout', array('sessionID'=>$this->session));
		if (isset($return['decoded']['result']['success'])) {
			return $return['decoded']['result']['success'];
		} else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
	
	/*
	* Authenticate user
    	
	Requirements: session
	*/
	public function authenticateUser(gsUser $user){
		if (!$this->session){
			if (!$this->startSession()) {
                return false;
			}
		}
        
        if ((!$user->getUsername() && !$user->getEmail()) || !$user->getToken()) {
            return false;
        }

		$return = self::apiCall('authenticate',array('login'=>($user->getUsername() ? $user->getUsername() :  $user->getEmail()), 
									'password'=>$user->getToken(), 'sessionID'=>$this->session), true);
		if (isset($return['decoded']['result']['UserID']) && $return['decoded']['result']['UserID'] > 0) {
            $user->importUserData($return['decoded']['result']);
            $this->sessionUserid = $user->getUserID();
            return $user;
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
    
    public function authenticate(gsUser $user){
        return $this->authenticateUser($user);
    }
    
	/*
	* Retrieves information from the given album
	
	Requirements: none
	Static function
	
	@param	integer	artistID
	*/
	public static function getArtistInfo($artistid){
		if (!is_numeric($artistid)){
			return false;
		}		
		
		$return = self::apiCall('getArtistInfo',array('artistID'=>$artistid));
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
		
	/*
	* Get songs on an album from the albumID
	
	Return: array { [n]=> array(6) { ["SongID"]=> int ["SongName"]=> string ["ArtistID"]=> int ["ArtistName"]=> string ["AlbumName"]=> string ["Sort"]=> int) }
	
	TODO: Make sure Sort returns sorted
	TODO: better checking of duplicates
	
	Requirements: none
	Static function	
	
	@param	integer	albumID
	@param	integer	limit, optional
	*/
	public static function getAlbumSongs($albumid, $limit=null){
		if (!is_numeric($albumid)){
			return false;
		}		
		
		$return = self::apiCall('getAlbumSongs',array('albumID'=>$albumid,'limit'=>$limit));
		if (isset($return['decoded']['result']['songs']) && count($return['decoded']['result']['songs'])>0 ){
			return $return['decoded']['result']['songs'];
		} else {
            gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* Return the user token before doing authorize
	
	Requirements: none
	Static function	
	*/	
	public static function getUserToken($username,$password){
		return md5($password);
	}
	
	/*
	* Returns userInfo from SessionID.
	* Information returned: IsPremium, UserID, 	Username
	
	Requirements: session
	*/	
	public function getUserInfo() {
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getUserInfo',array('sessionID'=>$this->session));

		if (isset($return['decoded']['result']['UserID']) && $return['decoded']['result']['UserID']) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
    
	/* 
	* Deprecated version of getUserPlaylistsEx
	
	Requirements: session

	@param	integer	limit, optional
	*/
	public function getUserPlaylists($limit=null){		
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getUserPlaylists',array('sessionID'=>$this->session, 'limit'=>$limit));
		if (isset($return['decoded']['result']['playlists'])) {
			return $return['decoded']['result']['playlists'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* Returns the playlists of the userID given.
	
	Requirements: none
	Static function
	
	@param	integer	userID
	@param	integer	limit, optional
	*/	
	public static function getUserPlaylistsByUserID($userid, $limit=null){
		if (!is_numeric($userid)){
			return false;
		}		
		
		$return = self::apiCall('getUserPlaylistsByUserID',array('userID'=>$userid, 'limit'=>$limit));
		if (isset($return['decoded']['result']['playlists'])) {
			return $return['decoded']['result']['playlists'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
    
    /* 
	* getUserLibrary
	
	Requirements: session

	@param	integer	limit, optional
	*/
	public function getUserLibrarySongs($limit=null){		
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getUserLibrarySongs',array('sessionID'=>$this->session, 'limit'=>$limit));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
    
    /*
	* Returns a list of favorites from the user.
	
	TODO: Sort by newest at the top.
	
	Requirements: session
	*/	
	public function getUserFavoriteSongs($limit=null){
		if (empty($this->session)){
			trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getUserFavoriteSongs',array('sessionID'=>$this->session, 'limit'=>$limit));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
	
	/*
	* Adds a song to the logged-in user's favorites
		
	* appears to only return a success parameter		

	Requirements: session
	
	@param	integer	songID
	*/
	public function addUserFavoriteSong($song){
		if (empty($this->session)){
			trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		if (!is_numeric($song)){
			return false;
		}
		
		$return = self::apiCall('addUserFavoriteSong',array('sessionID'=>$this->session, 'songID'=>$song));
		if (isset($return['decoded']['result']['success'])) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* Returns a songID from the Tinysong Base62		

	Requirements: none
	Static Session
	
	@param	string	base62 from tinysong
	*/
	public static function getSongIDFromTinysongBase62($base){		
		if (preg_match("/^[A-Za-z0-9]$/",$base)){
			trigger_error(__FUNCTION__." requires a valid base62 song.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getSongIDFromTinysongBase62',array('base62'=>$base));
		if (isset($return['decoded']['result']['songID']))
			return $return['decoded']['result']['songID'];
		else {
			gsAPI::$lastError = $return['raw'];
            return false;
        }
	}
	
	/*
	* Returns a songURL from the Tinysong Base62		

	Requirements: none
	Static Session
	
	@param	string	base62 from tinysong
	*/
	public static function getSongURLFromTinysongBase62($base){		
		if (preg_match("/^[A-Za-z0-9]$/",$base)){
			trigger_error(__FUNCTION__." requires a valid base62 song.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getSongURLFromTinysongBase62',array('base62'=>$base));
		if (isset($return['decoded']['result']['url']))
			return $return['decoded']['result']['url'];
		else {
		    gsAPI::$lastError = $return['raw'];
            return false;
		}			
	}
    
    /*
	* Returns a songURL from the SongID		

	Requirements: none
	Static Session
    Protected Method
	
	@param	int	songID
	*/
	public static function getSongURLFromSongID($songID){		
		if (!is_numeric($songID)){
			trigger_error(__FUNCTION__." requires a valid songID.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getSongURLFromSongID',array('songID'=>$songID));
		if (isset($return['decoded']['result']['url']))
			return $return['decoded']['result']['url'];
		else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
	
	/*
	* Returns any meta data about a song

	Requirements: none
	Static Session
	
	@param	integer	songID
	*/
	public static function getSongInfo($song){		
		if (!is_numeric($song)){
			return false;
		}
		
		$return = self::apiCall('getSongsInfo',array('songIDs'=>$song));
		if (isset($return['decoded']['result']['songs'][0]))
			return $return['decoded']['result']['songs'][0];
		else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
    
    /*
	* Returns any meta data about songs

	Requirements: none
	Static Session
	
	@param	array	songIDs
	*/
	public static function getSongsInfo($songs, $returnByIds=false){        
        if (!array($songs) || count($songs)<1){
			return false;
		}
		
		$return = self::apiCall('getSongsInfo',array('songIDs'=>self::formatSongIDs($songs)));
		if (isset($return['decoded']['result']['songs'])) {
            if ($returnByIds) {
                $songs = array();
    			foreach ($return['decoded']['result']['songs'] as $song) {
                    if (isset($song['SongID'])) {
                        $songs[$song['SongID']] = $song;
                    }
    			}
                return $songs;
            }
            return $return['decoded']['result']['songs'];
		} else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
	
	/*
	* Returns any meta data about an album

	Requirements: none
	Static Session
	
	@param	integer	albumID
	*/
	public static function getAlbumInfo($album){		
		if (!is_numeric($album)){
			return false;
		}
		
		$return = self::apiCall('getAlbumInfo',array('albumID'=>$song));
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
    
    /*
	* Returns songs in a playlist

	Requirements: none
	Static Session
	
	@param	integer	playlistID
	*/
    public static function getPlaylistSongs($playlistID, $limit=null){
		if (!is_numeric($playlistID)){
			return false;
		}
		
		$return = self::apiCall('getPlaylistSongs', array('playlistID'=>$playlistID, 'limit'=>$limit));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
        }
	}
	
	/*
	* Creates a playlist under the logged in user
	
	Requirements: session
	
	@param	string	playlistName (Unique)
	@param	array	songs, integer array of songIDs
	*/
	public function createPlaylist($name, $songs){
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		if (empty($name)){
			return false;
		}
		
		if (!array($songs) || count($songs)<1){
			return false;
		}
		$return = self::apiCall('createPlaylist',array('sessionID'=>$this->session, 'name'=>$name, 'songIDs'=>self::formatSongIDs($songs)));
		//var_dump($return);
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* Adds a song to the tail-end of a playlist
	
	Requirements: session
	
	@param	integer	playlistID
	@param	integer	songID
	*/
	public function addSongToPlaylist($playlist,$song){
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		if (!is_numeric($playlist)){
			return false;
		}
		
		if (!is_numeric($song)){
			return false;
		}
		
		//first we need to retrieve playlist songs then we need to set playlist songs
		$songs = self::getPlaylistSongs($playlist);
		if (!is_array($songs))
			return false; //we couldn't process the songs, look for getPlaylistSongs to return error

		$songs[] = $song;
		
		return $this->setPlaylistSongs($playlist, $songs);		
	}
	
	/*
	* Changes the Playlist songs
	
	Requirements: session
	
	@param	integer	playlistID
	@param	array	songs, integer array of songIDs
	*/
	public function setPlaylistSongs($playlist,$songs){
		if (empty($this->session)){
    		trigger_error(__FUNCTION__." requires a valid session. No session was found.",E_USER_ERROR);
			return false;
		}
		
		if (!is_numeric($playlist)){
			return false;
		}
		
		if (!array($songs) || count($songs)<1){
			return false;
		}

		$return = self::apiCall('setPlaylistSongs',array('sessionID'=>$this->session, 'playlistID'=>$playlist, 'songIDs'=>self::formatSongIDs($songs)));
		//var_dump($return);
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else {
		    gsAPI::$lastError = $return['raw'];
        	return false;
        }
	}
	
	/*
	* Returns whether a song exists or not.
	* This is commonly used internally by this class
	
	Requirements: none
	static function
	
	@param	integer	songID
	*/
	public static function getDoesSongExist($song){
		if (!is_numeric($song)) {
			return false;
		}
		
		$return = self::apiCall('getDoesSongExist',array('songID'=>$song));
		if (isset($return['decoded']['result'])) {
			return (boolean)$return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return -1;
		}
	}
	
	/*
	* Returns whether an artist exists or not.
	
	Requirements: none
	static function
	
	@param	integer	artistID
	*/
	public static function getDoesArtistExist($artist){
		if (!is_numeric($artist)) {
			return false;
		}
		
		$return = self::apiCall('getDoesArtistExist',array('artistID'=>$artist));
		if (isset($return['decoded']['result'])) {
			return (boolean)$return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return -1;
		}
	}

	/*
	* Returns whether an album exists or not.
	
	Requirements: none
	static function
	
	@param	integer	albumID
	*/
	public static function getDoesAlbumExist($album){
		if (!is_numeric($album)) {
			return false;
		}
		
		$return = self::apiCall('getDoesAlbumExist',array('albumID'=>$album));
		if (isset($return['decoded']['result'])) {
			return (boolean)$return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return -1;
		}
	}
	
	/*
	* Returns a list of an artist's albums
	
	Requirements: none
	static function
	
	Returns an array with pager subarray and songs subarray
	
	*/
	public static function getArtistAlbums($artist, $verified=false){
		if (!is_numeric($artist)){
			return false;
		}
		if ($verified) {
			$return = self::apiCall('getArtistVerifiedAlbums',array('artistID'=>$artist));
		} else {
			$return = self::apiCall('getArtistAlbums',array('artistID'=>$artist));
		}
		if (isset($return['decoded']['result']['albums'])) {
			return $return['decoded']['result'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	Alias class for getArtistAlbums with verified true
	*/
	public static function getArtistVerifiedAlbums($artist){
		return self::getArtistAlbums($artist, true);
	}
	
	/*
	* Returns the top 100 songs for an artist
	
	Requirements: none
	Static function
	*/	 
	public static function getArtistPopularSongs($artist){
		if (!is_numeric($artist)){
			return false;
		}
		
		$return = self::apiCall('getArtistPopularSongs',array('artistID'=>$artist));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* A list of Popular Songs from Today
	
	Requirements: none
	Static function
	*/	 
	public static function getPopularSongsToday($limit=null){	
		$return = self::apiCall('getPopularSongsToday',array('limit'=>$limit));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
    
    /*
    * A list of Popular Songs from Month
	
	Requirements: none
	Static function
	*/	 
	public static function getPopularSongsMonth($limit=null){	
		$return = self::apiCall('getPopularSongsMonth',array('limit'=>$limit));
		if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	/*
	* Returns the Country from the IP Address it was requested from
	
	Requirements: session, extended access
	*/
	public function getCountry($ip = false){
	   
       if (!$this->session) {
            trigger_error(__FUNCTION__." requires a valid session. No session was found.", E_USER_ERROR);
        }
        if (!$ip) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $return = self::apiCall('getCountry', array('sessionID'=>$this->session, 'ip'=>$ip));
		/* this method has not yet been tested for the result set */
		if (isset($return['decoded']['result'])){
            $this->country = $return['decoded']['result'];
            return $this->country;
		} else {
		    gsAPI::$lastError = $return['raw'];
			return false;
		}
	}
	
	
	public function setCountry($country) {
		if (!$country || !is_array($country)) {
			trigger_error(__FUNCTION__." requires a valid country. No country was found.", E_USER_ERROR);
		}
		$this->country = $country;
		return $country;
	}
	
	/*
	* Get search results for a song
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with songs subarray
	*/
	protected static function getSongSearchResults($query, $country, $limit=null, $page=null){
		if (empty($query)){
			return false;
		}
        
        if (!$country) {
            trigger_error(__FUNCTION__." requires a valid country. No country was found.", E_USER_ERROR);
        }
		
		$return = self::apiCall('getSongSearchResults',array('query'=>$query, 'limit'=>$limit, 'page'=>$page, 'country'=>$country));
        if (isset($return['decoded']['result']['songs'])) {
			return $return['decoded']['result']['songs'];
        } else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
	} 
	
	/*
	* Get search results for an artist name
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getArtistSearchResults($query, $limit=null, $page=null){
		if (empty($query)){
			return false;
		}
		
		$return = self::apiCall('getArtistSearchResults',array('query'=>$query, 'limit'=>$limit, 'page'=>$page));
        if (isset($return['decoded']['result']['artists'])) {
			return $return['decoded']['result']['artists'];
        } else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
	} 
	
	/*
	* Get search results for an album name
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getAlbumSearchResults($query, $limit=null, $page=null){
		if (empty($query)){
			return false;
		}
		
		$return = self::apiCall('getAlbumSearchResults',array('query'=>$query, 'limit'=>$limit, 'page'=>$page));
        if (isset($return['decoded']['result']['albums'])) {
			return $return['decoded']['result']['albums'];
        } else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
		
	} 
	
	/*
	* Get search results for an album name
	* This method is access controlled.
	* This method contains an additional parameter for the songs.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getAlbumSearchResultsWithSongs($query, $limit=null, $page=null){
		if (empty($query)){
			return false;
		}
		
		$return = self::apiCall('getAlbumSearchResults',array('query'=>$query, 'limit'=>$limit, 'page'=>$page));
		if (isset($return['decoded']['result']['albums'])){
			foreach($return['decoded']['result']['albums'] AS &$albm){
                $albm['Songs'] = self::getAlbumSongs($albm['AlbumID']);
				$albm['SongCount'] = count($albm['Songs']);
			} 
			return $return['decoded']['result']['albums'];
		} else {
		    gsAPI::$lastError = $return['raw'];
        	return false;
        }
		
	}
	
	/*
	Basically login is just an alias class for authenticateUser
	*/	
	public function login($username, $password){
		return $this->authenticateUser($username, $password);
	}
	
	/*
	Another alias class for logout
	*/
	public function endSession(){
		return $this->logout();
	}
    
    /*
    Gets a stream key if you have permissions
    */
    public function getStreamKeyStreamServer($songID, $lowBitrate=false) {
        if (!$songID) {
            trigger_error(__FUNCTION__." requires a valid songID.", E_USER_ERROR);
        }
        if (!$this->country) {
            trigger_error(__FUNCTION__." requires a valid country. No country was found. Call getCountry()", E_USER_ERROR);
        }
        if (!$this->session) {
            trigger_error(__FUNCTION__." requires a valid session. No session was found.", E_USER_ERROR);
        }
        $return = self::apiCall('getStreamKeyStreamServer',array('songID'=>$songID, 'country'=>$this->country, 'sessionID'=>$this->session, 'lowBitrate'=>$lowBitrate));
        if (isset($return['decoded']['result']['StreamKey'])) {
			$serverURL = parse_url($return['decoded']['result']['url']);
            $return['decoded']['result']['StreamServerHostname'] = $serverURL['host'];
            return $return['decoded']['result'];
        } else {
            gsAPI::$lastError = $return['raw'];
			return false;
        }
    }
	
	
	/* 
	* Private call to grooveshark API, this is where the magic happens!
	*/ 
	protected static function apiCall($method, $args=array(), $https=false){	
		
        $payload = array('method'=>$method, 'parameters'=>$args, 'header'=>array('wsKey'=>self::$ws_key));
        
        if (isset($payload['parameters']) && isset($payload['parameters']['sessionID']) && $payload['parameters']['sessionID']) {
            $payload['header']['sessionID'] = $payload['parameters']['sessionID'];
            unset($payload['parameters']['sessionID']);
        }
        
        $postData = json_encode($payload);
		$sig = self::createMessageSig($postData, self::$ws_secret);
        $query_str = "?sig=" . $sig;
		
	    $url = sprintf('%s://%s',($https === true ? "https" : "http"),self::$api_host.$query_str);
                
        $c = curl_init();
	    curl_setopt($c, CURLOPT_URL, $url);
	    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 4);
        if (self::$headers) {
            curl_setopt($c, CURLOPT_HTTPHEADER, self::$headers);
        }
            curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_TIMEOUT, 10);
	    curl_setopt($c, CURLOPT_USERAGENT, 'fastest963-GSAPI-PHP');
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postData);
	    $result = curl_exec($c);
	    $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
	    curl_close($c);
	    $decoded = json_decode($result, true);
	
	    return array('http'=>$httpCode,'raw'=>$result,'decoded'=>$decoded);
	}
	
	/*
	* Creates the message signature before sending to Grooveshark
	*/
	private static function createMessageSig($params, $secret){
	    return hash_hmac('md5', $params, $secret);
	}
	
	/*
	* Formats the songIDs for use with setPlaylistSongs and other functions.
	* Has been altered to strip everything but the SongID
	*/
	private static function formatSongIDs($songs){
		$final = array();
		foreach($songs AS $sng){
			if (is_array($sng) && isset($sng['SongID'])) {
				$final[] = $sng['SongID'];
			} elseif (is_array($sng)){
				foreach($sng AS $k => $v){ //check for if case is not SongID
					if (strtolower($k) == 'songid'){
						$final[] = $v;
						break;
					}						
				}
			} else {
				$final[] = $sng;
			}
		}		
		return $final; //be SURE TO put this under the arg songIDs
	}
    
}
?>