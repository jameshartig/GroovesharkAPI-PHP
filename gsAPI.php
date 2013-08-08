<?php

/**
 * Grooveshark API Class
 * @author James Hartig
 * @copyright 2013
 * Released under GNU General Public License v3
 */

class gsAPI {

    /**
     * Your key and secret will be provided by Grooveshark. Fill them in below or pass them to the constructor.
     */
    private static $wsKey = "example";
    private static $wsSecret = "1a79a4d60de6718e8e5b326e338ae533";

    const API_HOST = "api.grooveshark.com";
    const API_ENDPOINT = "/ws3.php";

    private static $instance;

    public static $usePHPDNS = false; //if curl dns resolution is failing, set this to true and we will do dns lookup in php
    private static $cachedHostIP;

    public $sessionID = null;
    public $country;
    public static $headers;

    function __construct($key = null, $secret = null, $sessionID = null, $country = null)
    {
        if (!empty($key)) {
            self::$wsKey = $key;
        }
        if (!empty($secret)) {
            self::$wsSecret = $secret;
        }
        if (!empty($sessionID)) {
            $this->sessionID = $sessionID;
        }
        if (!empty($country)) {
            $this->country = $country;
        }
        
        if (empty(self::$wsKey) || empty(self::$wsSecret)) {
            trigger_error("gsAPI class requires a valid key and secret.", E_USER_ERROR);
        }

        self::$instance = $this;
        self::$headers = array();        
    }
    
    public static function getInstance($key = null, $secret = null, $sessionID = null, $country = null)
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c($key, $secret, $sessionID, $country);
        }        
        return self::$instance;
    }
    
    /*    
     * Ping Grooveshark to make sure Pickles is sleeping
     */
    public static function pingService()
    {
        return self::makeCall('pingService', array());
    }

    /**
     * Methods related specifically to sessions
     * Calls require special access.
     */

    /*
     * Start a new session
     * This will save the session as a static variable on the gsAPI class to simplify other methods
     */
    public function startSession()
    {
        $result = self::makeCall('startSession', array(), 'sessionID', true);
        if (empty($result)) {
            return $result;
        }
        $this->sessionID = $result;
        return $result;
    }

    /*
     * Set the current session for use with methods
    */
    public function setSession($sessionID)
    {
       $this->sessionID = $sessionID;
    }

    /*
     * Returns the current SessionID
     * This should be stored instead of username/token
     * @deprecated
    */
    public function getSession()
    {
       return $this->sessionID;
    }

    /*
     * Logs out any authenticated user from the current session
     * This requires a valid sessionID, either statically or as a parameter
     * Can be called statically or dynamically
     */
    public function logout($sessionID = null)
    {
        if ((!isset($this) || empty($this->sessionID)) && empty($sessionID)) {
            return false;
        }
        if (empty($sessionID)) {
            $sessionID = $this->sessionID;
        }

        $result = self::makeCall('logout', array(), 'success', false, $sessionID);
        if (empty($result)) {
            return false;
        }
        return $result;
    }
    //backwards-compatible
    //@deprecated
    public function endSession()
    {
        return $this->logout();
    }

    /*
    * Returns information about the logged-in user based on the current sessionID
    */
    public function getUserInfo()
    {
        return self::makeCall('getUserInfo', array(), null, false, $this->sessionID);
    }

    /*
     * Set the current country for use with methods
    */
    public function setCountry($country)
    {
       $this->country = $country;
    }

    /*
     * Returns a country object for the given IP.
     * This should be cached since it won't change.
     * Call requires session access.
     * This can be called statically or dynamically
     * todo: this doesn't match getSession but somehow should...
     */
    public function getCountry($ip = null)
    {
        //filter_var is 5.2+ only
        if (!empty($ip) && !filter_var($ip, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            trigger_error("Invalid IP sent to getCountry! Sent: $ip", E_USER_ERROR);
            return false;
        }
        $args = array();
        if (!empty($ip)) {
            $args['ip'] = $ip;
        }
        $country = self::makeCall('getCountry', $args);
        if (isset($this) && !empty($country)) {
            $this->country = $country;
        }
        return $country;
    }

    /**
     * Methods relating to the logged-in user
     * Calls require session access.
     */

    /*
     * Authenticate a user
     * Username can be the user's email or username.
     * Password should be sent unmodified to this method.
     */
    public function authenticate($username, $password)
    {
        if (empty($username) || empty($password)) {
            return array();
        }
        $args = array('login' => $username,
                      'password' => md5($password),
                      );
        $result = self::makeCall('authenticate', $args, null, true, $this->sessionID);
        if (empty($result['UserID'])) {
            return array();
        }
        return $result;
    }
    //backwards-compatible
    public function login($username, $password)
    {
        return $this->authenticate($username, $password);
    }

    /*
     * Get the logged-in user's playlists
     * Requires a valid sessionID and authenticated user
     */
    public function getUserPlaylists($limit = null)
    {
        $args = array();
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }
        return self::makeCall('getUserPlaylists', $args, 'playlists', false, $this->sessionID);
    }

    /*
     * Returns the playlists owned by the given userID
     */
    public function getUserPlaylistsByUserID($userID, $limit = null)
    {
        if (!is_numeric($userID)){
            return false;
        }
        $args = array('userID' => (int)$userID);
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getUserPlaylistsByUserID', $args, 'playlists', false, $sessionID);
    }

    /*
     * Get the logged-in user's library
     * Requires a valid sessionID and authenticated user
     */
    public function getUserLibrary($limit = null)
    {
        $args = array();
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }
        return self::makeCall('getUserLibrarySongs', $args, 'songs', false, $this->sessionID);
    }
    // backwards-compatible version
    public function getUserLibrarySongs($limit = null)
    {
        return self::getUserLibrary($limit);
    }

    /*
     * Get the logged-in user's favorites
     * Requires a valid sessionID and authenticated user
     */
    public function getUserFavorites($limit = null)
    {
        $args = array();
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }
        return self::makeCall('getUserFavoriteSongs', $args, 'songs', false, $this->sessionID);
    }
    // backwards-compatible version
    public function getUserFavoriteSongs($limit = null)
    {
        return self::getUserFavoriteSongs($limit);
    }

    /*
     * Adds a song to the logged-in user's favorites
     * Requires a valid sessionID and authenticated user
     */
    public function addUserFavoriteSong($songID)
    {
        if (!is_numeric($songID)) {
            return false;
        }

        return self::makeCall('addUserFavoriteSong', array('songID' => (int)$songID), 'success', false, $this->sessionID);
    }

    /*
     * Adds a song to the logged-in user's library
     * Requires a valid sessionID and authenticated user
     * Songs should be an array of objects each like (songID => 2341, artistID => 124445, albumID => 993284)
     */
    public function addUserLibrarySongs($songs)
    {
        if (!is_array($songs)) {
            return false;
        }

        return self::makeCall('addUserLibrarySongsEx', array('songs' => $songs), 'success', false, $this->sessionID);
    }

    /*
     * Creates a playlist for the logged-in user
     */
    public function createPlaylist($name, $songIDs = null)
    {
        if (empty($name)) {
            return array();
        }
        if (is_null($songIDs)) {
            $songIDs = array();
        }
        $args = array('name' => $name,
                      'songIDs' => $songIDs,
                      );
        return self::makeCall('createPlaylist', $args, null, false, $this->sessionID);
    }

    /*
     * Adds a song to the end of a playlist
     */
    public function addSongToPlaylist($playlistID, $songID)
    {
        if (!is_numeric($playlistID) || !is_numeric($songID)){
            return false;
        }

        //first we need to retrieve playlist songs then we need to set playlist songs
        $songs = self::getPlaylistSongs($playlistID);
        if (!is_array($songs)) {
            return false; //we couldn't process the songs, look for getPlaylistSongs to return error
        }
        $songs[] = $songID;

        return self::setPlaylistSongs($playlistID, $songs, null, false, $this->sessionID);
    }

    /*
     * Changes a playlist's songs owned by the logged-in user
     * returns array('success' => boolean)
     */
    public function setPlaylistSongs($playlistID, $songIDs)
    {
        if (!is_numeric($playlistID) || !is_array($songIDs)){
            return array('success' => false);
        }

        $args = array('playlistID' => (int)$playlistID,
                      'songIDs' => $songIDs,
                      );
        return self::makeCall('setPlaylistSongs', $args, null, false, $this->sessionID);
    }

    /**
     * Methods relating to artists/albums/songs
     */

    /*
     * Retrieves information for the given artistID
     * Can be called statically or dynamically
     */
    public function getArtistInfo($artistID)
    {
        if (empty($artistID)){
            return false;
        }

        if (isset($this)) {
            $result = $this->getArtistsInfo(array($artistID));
        } else {
            //note: generates a strict warning
            $result = self::getArtistsInfo(array($artistID));
        }
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }

    /*
     * Retrieves information for the given artistIDs
     * Note: not guaranteed to come back in the same order
     * Can be called statically or dynamically
     */
    public function getArtistsInfo($artistIDs, $returnByIDs = false)
    {
        if (empty($artistIDs)){
            return array();
        }
        if (!is_array($artistIDs)) {
            $artistIDs = array($artistIDs);
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getArtistsInfo', array('artistIDs' => $artistIDs), 'artists', false, $sessionID);
        if ($returnByIDs) {
            $artistsKeyed = array();
            foreach ($result as $artist) {
                if (!empty($artist['ArtistID'])) {
                    $artistsKeyed[$artist['ArtistID']] = $artist;
                }
            }
            return $artistsKeyed;
        }
        return $result;
    }

    /*
     * Returns a songID from the Tinysong Base62
     * Requires special access.
     * Can be called statically or dynamically
     */
    public function getSongIDFromTinysongBase62($base)
    {
        if (!preg_match("/^[A-Za-z0-9]+$/", $base)) {
            return false;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getSongIDFromTinysongBase62', array('base62' => $base), 'songID', false, $sessionID);
    }

    /*
     * Returns the Grooveshark URL for a Tinysong Base62
     * Requires special access.
     * Can be called statically or dynamically
     */
    public function getSongURLFromTinysongBase62($base)
    {
        if (!preg_match("/^[A-Za-z0-9]+$/", $base)) {
            return false;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getSongURLFromTinysongBase62',array('base62' => $base), 'url', false, $sessionID);
    }

    /*
     * Returns a Grooveshark URL for the given SongID
     * Requires special access.
     * Can be called statically or dynamically
     */
    public function getSongURLFromSongID($songID)
    {
        if (!is_numeric($songID)){
            return false;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getSongURLFromSongID', array('songID' => (int)$songID), 'url', false, $sessionID);
    }

    /*
    * Returns metadata about the given songID
    */
    public static function getSongInfo($songID)
    {
        if (!is_numeric($songID)) {
            return array();
        }

        $result = self::getSongsInfo(array($songID));
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }

    /*
     * Returns metadata about multiple songIDs
     * Note: not guaranteed to come back in the same order
     * if returnByIDs is true, the songs are returned in a array keyed by songID
     */
    public function getSongsInfo($songIDs, $returnByIDs = false)
    {
        if (empty($songIDs)) {
            return array();
        }
        if (!is_array($songIDs)) {
            $songIDs = array($songIDs);
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getSongsInfo', array('songIDs' => $songIDs), 'songs', false, $sessionID);
        if (empty($result)) {
            return $result;
        }
        if ($returnByIDs) {
            $songsKeyed = array();
            foreach ($result as $song) {
                if (!empty($song['SongID'])) {
                    $songsKeyed[$song['SongID']] = $song;
                }
            }
            return $songsKeyed;
        }
        return $result;
    }

    /*
     * Returns metadata about the given albumID
     */
    public function getAlbumInfo($albumID)
    {
        if (!is_numeric($albumID)) {
            return array();
        }

        if (isset($this)) {
            $result = $this->getAlbumsInfo(array($albumID));
        } else {
            $result = self::getAlbumsInfo(array($albumID));
        }
        if (empty($result)) {
            return $result;
        }
        return $result[0];
    }

    /*
     * Returns metadata about multiple albumIDs
     * Note: not guaranteed to come back in the same order
     * if returnByIDs is true, the songs are returned in a array keyed by AlbumID
     */
    public function getAlbumsInfo($albumIDs, $returnByIDs = false)
    {
        if (empty($albumIDs)) {
            return array();
        }
        if (!is_array($albumIDs)) {
            $albumIDs = array($albumIDs);
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getAlbumsInfo', array('albumIDs' => $albumIDs), 'albums', false, $sessionID);
        if (empty($result)) {
            return $result;
        }
        if ($returnByIDs) {
            $albumsKeyed = array();
            foreach ($result as $album) {
                if (!empty($album['AlbumID'])) {
                    $albumsKeyed[$album['AlbumID']] = $album;
                }
            }
            return $albumsKeyed;
        }
        return $result;
    }

    /*
     * Get songs on a given albumID
     */
    public function getAlbumSongs($albumID, $limit = null)
    {
        if (!is_numeric($albumID)) {
            return array();
        }

        $args = array('albumID' => (int)$albumID);
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getAlbumSongs', $args, 'songs', false, $sessionID);
    }

    /*
     * Get songs on a given playlistID
     */
    public function getPlaylistSongs($playlistID, $limit = null)
    {
        if (!is_numeric($playlistID)) {
            return array();
        }

        $args = array('playlistID' => (int)$playlistID);
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getPlaylistSongs', $args, 'songs', false, $sessionID);
    }

    /*
     * Returns whether a given songID exists or not.
     * Returns array('exists' => boolean)
     */
    public function getDoesSongExist($songID)
    {
        $return = array('exists' => false);
        if (!is_numeric($songID)) {
            return $return;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getDoesSongExist', array('songID' => $songID), false, false, $sessionID);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }

    /*
     * Returns whether a given artistID exists or not.
     * Returns array('exists' => boolean)
     */
    public function getDoesArtistExist($artistID)
    {
        $return = array('exists' => false);
        if (!is_numeric($artistID)) {
            return $return;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getDoesArtistExist', array('artistID' => $artistID), false, false, $sessionID);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }

    /*
     * Returns whether a given albumID exists or not.
     * Returns array('exists' => boolean)
     */
    public function getDoesAlbumExist($albumID)
    {
        $return = array('exists' => false);
        if (!is_numeric($albumID)) {
            return $return;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        $result = self::makeCall('getDoesAlbumExist', array('albumID' => $albumID), false, false, $sessionID);
        if (isset($result['result'])) {
            $return['exists'] = $result['result'];
        }
        return $return;
    }

    /*
     * Returns a list of an artistID's albums
     * Optionally allows you to get only the verified albums
     */
    public function getArtistAlbums($artistID, $verified = false)
    {
        if (!is_numeric($artistID)){
            return false;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }

        $args = array('artistID' => (int)$artistID);
        if ($verified) {
            $result = self::makeCall('getArtistVerifiedAlbums', $args, 'albums', false, $sessionID);
        } else {
            $result = self::makeCall('getArtistAlbums', $args, 'albums', false, $sessionID);
        }
        return $result;
    }

    /*
     * Alias class for getArtistAlbums with verified true
     */
    public function getArtistVerifiedAlbums($artistID)
    {
        if (isset($this)) {
            return $this->getArtistAlbums($artistID, true);
        } else {
            return self::getArtistAlbums($artistID, true);
        }
    }

    /*
     * Returns the top 100 songs for an artistID
     */
    public function getArtistPopularSongs($artistID)
    {
        if (!is_numeric($artistID)){
            return false;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getArtistPopularSongs', array('artistID' => (int)$artistID), 'songs', false, $sessionID);
    }

    /*
     * Returns a list of today's popular songs
     */
    public function getPopularSongsToday($limit = null)
    {
        $args = array();
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getPopularSongsToday', $args, 'songs', false, $sessionID);
    }

    /*
     * Returns a list of today's popular songs
     */
    public function getPopularSongsMonth($limit = null)
    {
        $args = array();
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getPopularSongsMonth', $args, 'songs', false, $sessionID);
    }

    /*
     * Get search results for a song
     * This method is access controlled.
     */
    public function getSongSearchResults($query, $country = null, $limit = null, $page = null)
    {
        if (empty($query)){
            return array();
        }
        //todo: remove isset($this) check
        if ((!isset($this) || empty($this->country)) && empty($country)) {
            trigger_error("getSongSearchResults requires a country. Make sure you call getCountry or setCountry!", E_USER_ERROR);
            return array();
        }
        if (empty($country)) {
            $country = $this->country;
        }

        $args = array('query' => $query,
                      'country' => $country,
                      );
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }
        if (!empty($page)) {
            $page = (int)$page;
            if (isset($limit)) {
                $offset = ($page - 1) * (int)$limit;
            } else {
                $offset = ($page - 1) * 100;
            }
            if ($offset > 0) {
                $args['offset'] = $offset;
            }
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getSongSearchResults', $args, 'songs', false, $sessionID);
    }

    /*
     * Get search results for an artist name
     * This method is access controlled.
     */
    public function getArtistSearchResults($query, $limit = null)
    {
        if (empty($query)){
            return array();
        }

        $args = array('query' => $query);
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getArtistSearchResults', $args, 'artists', false, $sessionID);
    }

    /*
     * Get search results for an album name
     * This method is access controlled.
     */
    public function getAlbumSearchResults($query, $limit = null)
    {
        if (empty($query)){
            return array();
        }

        $args = array('query' => $query);
        if (!empty($limit)) {
            $args['limit'] = (int)$limit;
        }

        //todo: remove this once we everything is forced dynamically
        $sessionID = false;
        if (isset($this) && !empty($this->sessionID)) {
            $sessionID = $this->sessionID;
        }
        return self::makeCall('getAlbumSearchResults', $args, 'albums', false, $sessionID);
    }

    /*
     * Get a stream mp3 url for a given songID. This can be used to stream the song once to an mp3-compatible player.
     * This method is access controlled.
     */
    public function getStreamKeyStreamServer($songID, $lowBitrate = false)
    {
        if (empty($songID)) {
            return array();
        }
        if (!isset($this) || empty($this->country)) {
            trigger_error("getStreamKeyStreamServer requires a country. Make sure you call getCountry or setCountry!", E_USER_ERROR);
            return array();
        }
        $args = array('songID' => (int)$songID,
                      'country' => $this->country,
                      );
        if ($lowBitrate) {
            $args['lowBitrate'] = true;
        }
        $result = self::makeCall('getStreamKeyStreamServer', $args, null, false, $this->sessionID);
        if (empty($result) || empty($result['StreamKey'])) {
            return array();
        }
        $serverURL = parse_url($result['url']);
        $result['StreamServerHostname'] = $serverURL['host'];
        return $result;
    }

    /*
     * Get a stream mp3 url for a given songID. This can be used to stream the song once to an mp3-compatible player.
     * Anywhere-only or trials
     * This method is access controlled.
     * $trialUniqueID is the uniqueID from a trial
     */
    public function getSubscriberStreamKey($songID, $lowBitrate = false, $trialUniqueID = null)
    {
        if (empty($songID)) {
            return array();
        }
        if (!isset($this) || empty($this->country)) {
            trigger_error("getSubscriberStreamKey requires a country. Make sure you call getCountry or setCountry!", E_USER_ERROR);
            return array();
        }
        $args = array('songID' => (int)$songID,
                      'country' => $this->country,
                      );
        if ($lowBitrate) {
            $args['lowBitrate'] = true;
        }
        if (!empty($trialUniqueID)) {
            $args['uniqueID'] = $trialUniqueID;
        }
        $result = self::makeCall('getSubscriberStreamKey', $args, null, false, $this->sessionID);
        if (empty($result) || empty($result['StreamKey'])) {
            return array();
        }
        $serverURL = parse_url($result['url']);
        $result['StreamServerHostname'] = $serverURL['host'];
        return $result;
    }

    /*
     * Mark an existing streamKey/streamServerID as being played for >30 seconds
     * This should be called after 30 seconds of listening, not just at the 30 seconds mark.
     * returns array('success' => boolean)
     */
    public function markStreamKeyOver30Secs($streamKey, $streamServerID)
    {
        if (empty($streamKey) || empty($streamServerID)) {
            return array('success' => false);
        }
        $args = array('streamKey' => $streamKey,
                      'streamServerID' => $streamServerID,
                      );
        return self::makeCall('markStreamKeyOver30Secs', $args, null, false, $this->sessionID);
    }


    /*
     * Marks an song stream as completed
     * Complete is defined as: Played for greater than or equal to 30 seconds, and having reached the last second either through seeking or normal playback.
     * returns array('success' => boolean)
     */
    public function markSongComplete($songID, $streamKey, $streamServerID)
    {
        if (empty($songID) || empty($streamKey) || empty($streamServerID)) {
            return false;
        }
        $args = array('songID' => (int)$songID,
                      'streamKey' => $streamKey,
                      'streamServerID' => $streamServerID,
                      );
        return self::makeCall('markSongComplete', $args, null, false, $this->sessionID);
    }
    
    
    /* 
     * Make a call to the Grooveshark API
     */
    private static function makeCall($method, $args = array(), $resultKey = null, $https = false, $sessionID = false){

        $payload = array('method' => $method,
                         'parameters' => $args,
                         'header' => array('wsKey' => self::$wsKey),
                         );

        if (!empty($sessionID)) {
            $payload['header']['sessionID'] = $sessionID;
        } else if ($sessionID !== false) {
            trigger_error("$method requires a valid sessionID.", E_USER_ERROR);
            return false;
        }

        $c = curl_init();
        $postData = json_encode($payload);
        curl_setopt($c, CURLOPT_POST, 1);
        curl_setopt($c, CURLOPT_POSTFIELDS, $postData);

        $headers = self::$headers;
        $host = self::API_HOST;
        if (self::$usePHPDNS) {
            if (empty(self::$cachedHostIP)) {
                $records = dns_get_record($host, DNS_A);
                if (empty($records) || empty($records[0]['ip'])) {
                    trigger_error("Failed to fetch IP address for $host.", E_USER_ERROR);
                    return false;
                }
                self::$cachedHostIP = $records[0]['ip'];
            }
            $headers[] = "Host: $host"; //make sure we sent the host param since we switching to ip-based host
            $host = self::$cachedHostIP;
        }
        if (!empty($headers)) {
            curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
        }

        if ($https) {
            $scheme = "https://";
        } else {
            $scheme = "http://";
        }
        $sig = self::createMessageSig($postData, self::$wsSecret);
        $url = $scheme . $host . self::API_ENDPOINT . "?sig=$sig";
        curl_setopt($c, CURLOPT_URL, $url);

        curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($c, CURLOPT_TIMEOUT, 6);
        curl_setopt($c, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($c, CURLOPT_USERAGENT, 'fastest963-GroovesharkAPI-PHP-' . self::$wsKey);
        $return = curl_exec($c);
        $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        if ($httpCode != 200) {
            trigger_error("Unexpected return code from Grooveshark API. Code $httpCode.", E_USER_ERROR);
            return false;
        }

        $result = json_decode($return, true);
        if (is_null($result) || empty($result['result'])) {
            if (!empty($result['errors'])) {
                trigger_error("Errors in result from server. Errors: " . print_r($result['errors'], true), E_USER_ERROR);
            }
            return false;
        } else if (!empty($resultKey)) {
            if (!isset($result['result'][$resultKey])) {
                return false;
            }
            $result = $result['result'][$resultKey];
        } else if ($resultKey !== false) {
            $result = $result['result'];
        }
        return $result;
    }
    
    /*
     * Creates the message signature before sending to Grooveshark
     */
    private static function createMessageSig($params, $secret){
        return hash_hmac('md5', $params, $secret);
    }

    /*
     * Add X-Client-IP to all requests
     * Whitelisted API keys only
     */
    public static function addClientIP($ip = null) {
        if (empty($ip)) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        if (empty(self::$headers)) {
            self::$headers = array("X-Client-IP: " . $ip);
        } else {
            $newHeaders = array();
            foreach (self::$headers as $header) {
                if (strpos($header, 'X-Client-IP:') !== 0) {
                    $newHeaders[] = $header;
                }
            }
            $newHeaders[] = "X-Client-IP: " . $ip;
            self::$headers = $newHeaders;
        }
    }
    
}
?>
