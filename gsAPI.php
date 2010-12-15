<?php

/**
 * Grooveshark API Class
 * @author James Hartig
 * @copyright 2010
 * Released under GNU General Public License v3 (See LICENSE for license)
 */

/*

Recommended to be called like $gsapi = new gsapi(key,secret)
YOU MUST SET THE KEY,SECRET EITHER BELOW OR LIKE ABOVE!

Note: even if you are only using the static functions, calling $gsapi = new gsapi(key,secret) will set the key and secret for those as well

*/

class gsAPI{
	
	private static $api_host = "api.grooveshark.com/ws/2.1/"; //generally don't change this
	protected static $listen_host = "http://listen.grooveshark.com/"; //change this to preview.grooveshark.com if you are with VIP //this could potentially automatically be done...
	private static $ws_key;
	private static $ws_secret;
	private $session;
    protected $sessionUserid;
    
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
	* Retrieve the forum profile URL for a Username	
	
	Requirements: none
	Static Function
	*/
	public static function getUserForumProfileUrlFromUsername($username){
		return sprintf("http://forums.grooveshark.com/profile/%s/",strtolower($username));
	}
	
	
	/*	
	* Start a new session
	
	Requirements: none
	Even though this function requires nothing, it is not static
	*/	
	public function startSession(){
		$return = self::apiCall('startSession',array());

		if (isset($return['decoded']['result']['success']) && $return['decoded']['result']['success'] === true){
			$this->session = $return['decoded']['result']['sessionID'];
			return $this->session;
		}else
			return false;
	}
		
	/*	
	* Start a new session provided an existing session key
	
	Requirements: none
	Even though this function requires nothing, it is not static
	*/	
	public function setSession($session){
		$this->session = $session;
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
        
        if (!$user->getUsername() || !$user->getToken()) {
            return false;
        }

		$return = self::apiCall('authenticateUser',array('username'=>$user->getUsername(), 'token'=>$user->getToken(), 'sessionID'=>$this->session));
		if (isset($return['decoded']['result']['UserID']) && $return['decoded']['result']['UserID'] > 0) {
            $user->importUserData($return['decoded']['result']);
            $this->sessionUserid = $user->getUserID();
            return $user;
		} else {
			return false;
        }
	}    
    
	/*
	* Retrieves information from the given album
	
	Requirements: none
	Static function
	
	@param	integer	artistID
	*/
	public static function getArtistInfo($artistid){
		if (!is_numeric($artistid) || self::getDoesArtistExist($artistid) === false){
			trigger_error(__FUNCTION__." requires a valid artistID. The artistID provided was invalid.",E_USER_ERROR);
			return false;
		}		
		
		$return = self::apiCall('getArtistInfo',array('artistID'=>$artistid));
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
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
	@param	bool	unique, whether we should attempt to filter out duplicate songs by their titles
	*/
	public static function getAlbumSongs($albumid, $limit=null, $unique=false){
		if (!is_numeric($albumid)){
			trigger_error(__FUNCTION__." requires a valid albumID. The albumID provided was invalid.",E_USER_ERROR);
			return false;
		}		
		
		$return = self::apiCall('getAlbumSongs',array('albumID'=>$albumid,'limit'=>$limit));
		if (isset($return['decoded']['result'][0]) && count($return['decoded']['result'][0])>0 ){
			$songs = array();			
			foreach($return['decoded']['result'][0] AS $k => &$song){
				if ($unique){
					if (!isset($songs[$song['SongName']]) || ($song['IsVerified']==1 && $songs[$song['SongName']][1]==0)){		
						if (isset($songs[$song['SongName']]))
							unset($return['decoded']['result'][0][$songs[$song['SongName']][0]]); //remove old result
							
						//if (!empty($song['CoverArtFilename']))
						//	$song['CoverArtLink'] = self::$pic_host.$song['CoverArtFilename']; //add filename with the actual url to the array
						$songs[$song['SongName']] = array($k,$song['IsVerified']);
					}else{
						unset($return['decoded']['result'][0][$k]);
					}
				}else{
					//if (!empty($song['CoverArtFilename']))
					//	$song['CoverArtLink'] = self::$pic_host.$song['CoverArtFilename']; //add filename with the actual url to the array
				}
			}
			return $return['decoded']['result'][0];
		}else
			return false;
	}
	
	/*
	* Return the user token before doing authorize
	
	Requirements: none
	Static function	
	*/	
	public static function getUserToken($username,$password){
		return md5($username.md5($password));
	}
	
	/*
	* Returns userInfo from SessionID.
	* Information returned: IsPremium, UserID, 	Username
	
	Requirements: session
	*/	
	public function getUserInfoFromSessionID() {
		if (empty($this->session)){
			return false;
		}
		
		$return = self::apiCall('getUserInfoFromSessionIDEx',array('sessionID'=>$this->session));

		if (isset($return['decoded']['result']['UserID']) && $return['decoded']['result']['UserID']) {
			return $return['decoded']['result'];
		} else {
			return false;
        }
	}
    
    public function getExtendedUserInfoFromSessionID() {
		if (empty($this->session)){
			return false;
		}
		
		$return = self::apiCall('getExtendedUserInfoFromSessionID',array('sessionID'=>$this->session));
		if (isset($return['decoded']['result']['UserID'])) {
			return $return['decoded']['result'];
		} else {
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
		//var_dump($return);
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
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
			trigger_error(__FUNCTION__." requires a valid userID. The userID provided was invalid.",E_USER_ERROR);
			return false;
		}		
		
		$return = self::apiCall('getUserPlaylistsByUserID',array('userID'=>$userid, 'limit'=>$limit));
		//var_dump($return);
		if (isset($return['decoded']['result']['playlists']))
			return $return['decoded']['result']['playlists'];
		else
			return false;
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
		
		if (!is_numeric($song) || self::getDoesSongExist($song) === false){
			trigger_error(__FUNCTION__." requires a songID. No valid songID was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('addUserFavoriteSong',array('sessionID'=>$this->session, 'songID'=>$song));
		if (isset($return['decoded']['result']['success']))
			return $return['decoded']['result'];
		else
			return false;
		
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
		else
			return false;
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
		else
			return false;
	}
	
	/*
	* Returns any meta data about a song

	Requirements: none
	Static Session
	
	@param	integer	songID
	*/
	public static function getSongInfo($song){		
		if (!is_numeric($song) || self::getDoesSongExist($song) === false){
			return false;
		}
		
		$return = self::apiCall('getSongInfoEx',array('songID'=>$song));
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
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
		if (!is_numeric($album) || self::getDoesAlbumExist($album) === false){
			trigger_error(__FUNCTION__." requires a albumID. No valid albumID was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getAlbumInfo',array('albumID'=>$song));
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
	}
	
	/*
	* Returns a URL to the songID provided		

	Requirements: none
	Static Session
	
	@param	integer	songID
	*/
	public static function getSongURLFromSongID($song){		
		if (!is_numeric($song) || self::getDoesSongExist($song) === false){
			trigger_error(__FUNCTION__." requires a songID. No valid songID was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getSongURLFromSongID',array('songID'=>$song));
		if (isset($return['decoded']['result']['url']))
			return $return['decoded']['result']['url'];
		else
			return false;
		
	}
	
	/*
	* Creates a playlist under the logged in user
	
	Requirements: session
	
	@param	string	playlistName (Unique)
	@param	array	songs, integer array of songIDs
	*/
	public function createPlaylist($name,$songs){
		if (empty($this->session)){
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
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
	}
	
	/*
	* Adds a song to the tail-end of a playlist
	
	TODO: add support for adding multiple songs (will most likely require a new class to maintain compatibility)
	TODO: if the song exists already, we will remove it and append it to the end
	
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
			trigger_error(__FUNCTION__." requires a playlistID. No valid playlistID was found.",E_USER_ERROR);
			return false;
		}
		
		if (!is_numeric($song) || self::getDoesSongExist($song) === false){
			trigger_error(__FUNCTION__." requires a songID. No valid songID was found.",E_USER_ERROR);
			return false;
		}
		
		//first we need to retrieve playlist songs then we need to set playlist songs
		$songs = self::getPlaylistSongs($playlist);
		if (!is_array($songs))
			return false; //we couldn't process the songs, look for getPlaylistSongs to return error

		$songs[] = $song;
		
		return $this->setPlaylistSongs($playlist,$songs);		
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
			trigger_error(__FUNCTION__." requires a name. No valid playlist name was found.",E_USER_ERROR);
			return false;
		}
		
		if (!array($songs) || count($songs)<1){
			trigger_error(__FUNCTION__." requires songIDs. No songIDs were sent. Be sure to send an array of songIDs.",E_USER_ERROR);
			return false;
		}

		$return = self::apiCall('setPlaylistSongs',array('sessionID'=>$this->session, 'playlistID'=>$playlist, 'songIDs'=>self::formatSongIDs($songs)));
		//var_dump($return);
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
	}
	
	/*
	* Returns whether a song exists or not.
	* This is commonly used internally by this class
	
	Requirements: none
	static function
	
	@param	integer	songID
	*/
	public static function getDoesSongExist($song){
		if (!is_numeric($song))
			return false;
		//since this method is commonly used to test a songID, we don't return an error if it is incorrect, just false
		
		$return = self::apiCall('getDoesSongExist',array('songID'=>$song));
		if (isset($return['decoded']['result'][0]))
			return (boolean)$return['decoded']['result'][0];
		else
			return false;
	}
	
	/*
	* Returns whether an artist exists or not.
	
	Requirements: none
	static function
	
	@param	integer	artistID
	*/
	public static function getDoesArtistExist($artist){
		if (!is_numeric($artist))
			return false;
		//since this method is commonly used to test a artistID, we don't return an error if it is incorrect, just false
		
		$return = self::apiCall('getDoesArtistExist',array('artistID'=>$artist));
		if (isset($return['decoded']['result'][0]))
			return (boolean)$return['decoded']['result'][0];
		else
			return false;
	}

	/*
	* Returns whether an album exists or not.
	
	Requirements: none
	static function
	
	@param	integer	albumID
	*/
	public static function getDoesAlbumExist($album){
		if (!is_numeric($album))
			return false;
		//since this method is commonly used to test a albumID, we don't return an error if it is incorrect, just false
		
		$return = self::apiCall('getDoesAlbumExist',array('albumID'=>$album));
		if (isset($return['decoded']['result'][0]))
			return (boolean)$return['decoded']['result'][0];
		else
			return false;
	}
	
	/*
	* Returns a list of an artist's albums
	
	Requirements: none
	static function
	
	Returns an array with pager subarray and songs subarray
	
	*/
	public static function getArtistAlbums($artist,$verified=false){
		if (!is_numeric($artist)){
			rigger_error(__FUNCTION__." requires artistID. No artistID was sent.",E_USER_ERROR);
			return false;
		}
		if ($verified)
			$return = self::apiCall('getArtistVerifiedAlbums',array('artistID'=>$artist));
		else
			$return = self::apiCall('getArtistAlbums',array('artistID'=>$artist));
		if (isset($return['decoded']['result'][0]['albums']))
			return $return['decoded']['result'][0];
		else
			return false;
	}
	
	/*
	Alias class for getArtistAlbums with verified true
	*/
	public static function getArtistVerifiedAlbums($artist){
		return self::getArtistAlbums($artist,true);
	}
	
	/*
	* Returns the top 100 songs for an artist
	
	Requirements: none
	Static function
	*/	 
	public static function getArtistPopularSongs($artist){
		if (!is_numeric($artist)){
			rigger_error(__FUNCTION__." requires artistID. No artistID was sent.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getArtistPopularSongs',array('artistID'=>$artist));
		if (isset($return['decoded']['result'][0]['songs']))
			return $return['decoded']['result'][0]['songs'];
		else
			return false;
	}
	
	/*
	* A list of Popular Songs from Today
	
	Requirements: none
	Static function
	*/	 
	public static function getPopularSongsToday($limit=null){	
		$return = self::apiCall('getPopularSongsToday',array('limit'=>$limit));
		if (isset($return['decoded']['result']['songs']))
			return $return['decoded']['result']['songs'];
		else
			return false;
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
		//var_dump($return);
		if (isset($return['decoded']['result'])) {
			return $return['decoded']['result'];
		} else {
			return false;
        }
	}
		
	/*
	* Returns the Country from the IP Address it was requested from
	
	Requirements: session, extended access
	*/
	public static function getCountry(){
		$return = self::apiCall('getCountry',array());
		/* this method has not yet been tested for the result set */
		if (isset($return['decoded']['result']))
			return $return['decoded']['result'];
		else
			return false;
	}
	
	/*
	* Returns the file URL from songID
	* Requires the country object
	
	Requirements: session, extended access, getCountry access
	*/
	public static function getFileURLFromSongID($song){
		if (!is_numeric($song) || self::getDoesSongExist($song) === false){
			trigger_error(__FUNCTION__." requires a songID. No valid songID was found.",E_USER_ERROR);
			return false;
		}
		
		$country = self::getCountry(); //we need to test this for the output
		
		$return = self::apiCall('getFileURLFromSongID',array('songID'=>$song,'country'=>$country));
		/* this method has not yet been tested for the result set */
		if (isset($return['decoded']['result']['url']))
			return $return['decoded']['result']['url'];
		else
			return false;
	}
	
	/*
	* Get search results for a song
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with songs subarray
	*/
	protected static function getSongSearchResults($query, $limit=null, $page=null){
		if (empty($query)){
			trigger_error(__FUNCTION__." requires a query. No query was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getSongSearchResultsEx',array('query'=>$query, 'limit'=>$limit, 'page'=>$page));
		if (isset($return['decoded']['result']['songs']))
			return $return['decoded']['result'];
		else
			return false;
		
	} 
	
	/*
	* Get search results for an artist name
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getArtistSearchResults($query,$limit=null,$page=null){
		if (empty($query)){
			trigger_error(__FUNCTION__." requires a query. No query was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getArtistSearchResults',array('query'=>$query,'limit'=>$limit,'page'=>$page));
		if (isset($return['decoded']['result']['artists'])){
			foreach($return['decoded']['result']['artists'] AS &$artst)
				$artst['GroovesharkLink'] = self::$listen_host."#/artist/".preg_replace("/[^\w]+/","+",$artst['ArtistName'])."/".$artst['ArtistID'];
			return $return['decoded']['result'];
		}else
			return false;
		
	} 
	
	/*
	* Get search results for an album name
	* This method is access controlled.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getAlbumSearchResults($query,$limit=null,$page=null){
		if (empty($query)){
			trigger_error(__FUNCTION__." requires a query. No query was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getAlbumSearchResults',array('query'=>$query,'limit'=>$limit,'page'=>$page));
		if (isset($return['decoded']['result']['albums'])){
			foreach($return['decoded']['result']['albums'] AS &$albm){
				if (!empty($albm['CoverArtFilename']))
					$albm['CoverArtLink'] = self::$pic_host.$albm['CoverArtFilename']; //add filename with the actual url to the array
				$albm['GroovesharkLink'] = self::$listen_host."#/album/".preg_replace("/[^\w]+/","+",$albm['AlbumName'])."/".$albm['AlbumID'];
			} 
			return $return['decoded']['result'];
		}else
			return false;
		
	} 
	
	/*
	* Get search results for an album name
	* This method is access controlled.
	* This method is version 2 and contains an additional parameter for the songs.
	
	Requirements: extended access
	Static Method
	
	Returns an array with pager subarray and songs subarray
	*/
	public static function getAlbumSearchResults2($query,$limit=null,$page=null){
		if (empty($query)){
			trigger_error(__FUNCTION__." requires a query. No query was found.",E_USER_ERROR);
			return false;
		}
		
		$return = self::apiCall('getAlbumSearchResults',array('query'=>$query,'limit'=>$limit,'page'=>$page));
		if (isset($return['decoded']['result']['albums'])){
			foreach($return['decoded']['result']['albums'] AS &$albm){
				if (!empty($albm['CoverArtFilename']))
					$albm['CoverArtLink'] = self::$pic_host.$albm['CoverArtFilename']; //add filename with the actual url to the array
				$albm['GroovesharkLink'] = self::$listen_host."#/album/".preg_replace("/[^\w]+/","+",$albm['AlbumName'])."/".$albm['AlbumID'];
				$albm['Songs'] = self::getAlbumSongs($albm['AlbumID']);
				$albm['SongCount'] = count($albm['Songs']);
			} 
			return $return['decoded']['result'];
		}else
			return false;
		
	}
	
	/*
	Basically login is just an alias class for authenticateUser
	*/	
	public function login($username, $password){
		return $this->authenticateUser($username,$password);
	}
	
	/*
	Another alias class for logout
	*/
	public function endSession(){
		return $this->logout();
	}
	
	
	/* 
	* Private call to grooveshark API, this is where the magic happens!
	*/ 
	protected static function apiCall($method,$args=array(),$https=false){	
			
		$args['sig'] = self::createMessageSig($method, $args, self::$ws_secret);
		$args['wsKey'] = self::$ws_key;
		$args['method'] = $method;
		$args['format'] = 'json';
		
		$query_str = "";
		//yes we could use http_build_query but it is PHP5 only, plus where's the fun in that?
        foreach ($args as $k => $v){
				if ($v !== null){
					if (is_array($v)){
						foreach($v AS $k2 => $v2)
							$query_str .= "&".urlencode($k).'['.$k2.']'.'='.urlencode($v2);
					}else
	           			$query_str .= "&".urlencode($k)."=".urlencode($v);
  				}
        }
        unset($args, $k, $v);
        $query_str = "?".substr($query_str,1); //remove beginning & and replace with a ?	
		
	    $url = sprintf('%s://%s',($https === true ? "https" : "http"),self::$api_host.$query_str);

	    $c = curl_init();
	    curl_setopt($c, CURLOPT_URL, $url);
	    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($c, CURLOPT_TIMEOUT, 10);
	    curl_setopt($c, CURLOPT_USERAGENT, 'fastest963-GSAPI-PHP');
	    $result = curl_exec($c);
	    $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
	    curl_close($c);
	    $decoded = json_decode($result, true);
	
	    return array('http'=>$httpCode,'raw'=>$result,'decoded'=>$decoded);
	}
	
	/*
	* Creates the message signature before sending to Grooveshark
	*/
	private static function createMessageSig($method, $params, $secret){
	    ksort($params);
	    $data = '';
	    foreach ($params as $key => $value){
	    	if ($value !== null){
	    		if (is_array($value)){
	    		$data .= $key;
		            foreach ($value as $k => $v)
		                $data .= $k.$v;
	    		}else
	        		$data .= $key.$value;
	        }
      	}

	    return hash_hmac('md5', $method.$data, $secret);

	}
	
	/*
	* Formats the songIDs for use with setPlaylistSongs and other functions.
	* Has been altered to strip everything but the SongID
	*/
	private static function formatSongIDs($songs){
		$final = array();
		foreach($songs AS $sng){
			if (isset($sng['SongID']))
				$final[] = $sng['SongID'];
			elseif (is_array($sng)){
				foreach($sng AS $k => $v){ //check for if case is not SongID
					if (strtolower($k) == 'songid'){
						$final[] = $v;
						break;
					}						
				}
			}else
				$final[] = $sng;
		}		
		return $final; //be SURE TO put this under the arg songIDs
	}
	
	/*
	* Instead of modifying the apiCall function we are using this little function to process TinySong queries
	*/
	protected static function httpCall($url,$ua='fastest963-GSAPI-PHP'){
		if (empty($url))
			return false;
			
	    $c = curl_init();
	    curl_setopt($c, CURLOPT_URL, $url);
	    curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($c, CURLOPT_TIMEOUT, 10);
	    curl_setopt($c, CURLOPT_USERAGENT, $ua);
	    $result = curl_exec($c);
	    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
	    $size = curl_getinfo($c, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
	    $type = curl_getinfo($c, CURLINFO_CONTENT_TYPE);
	    curl_close($c);	
	    return array('http'=>$code,'raw'=>$result,'size'=>$size,'type'=>$type);
	}
}
?>