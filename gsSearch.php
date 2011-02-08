<?php

/**
 * @author James Hartig
 * @copyright 2010
 */

class gsSearch extends gsAPI{
    
    const MAX_PARALLEL_SEARCHES = 10;
	
    private $parent;
    private $id = null;
	private $artist = null;
	private $album = null;
	private $title = null;
    private $changed = true;
	private $exact;
	private $results = null;
	private $gsUserPass = null;
	
    function gsSearch(&$parent=null){
	   if (!$parent) {
	       $this->parent = gsAPI::getInstance();
       } else {
            $this->parent = $parent;
       }
	}
    
    public function setAPISharkPass($password=null) {
        $this->gsUserPass = $password;
    }
	
	public function setArtist($artist){
        $this->changed = true;
		$this->artist = $artist;
	}
	
	public function setTitle($title){
        $this->changed = true;
		$this->title = $title;
	}
	
	public function setAlbum($album){
        $this->changed = true;
		$this->album = $album;
	}
    
    public function setResults($results) {
        $this->results = $results;
        $this->changed = false;
    }
	
	public function clear(){ //clears all the above search params
		$this->album = null;
		$this->artist = null;
		$this->title = null;
		$this->listing = null;
		$this->results = null;
        $this->changed = true;
	}
    
    //normalizes search and gets an ID
    public function getUniqueID() {
        if (!$this->id || $this->changed) {            
            $query_str = "";
    		if (!empty($this->title)){
    			$query_str .= " s:".$this->title;
    		}
    		if (!empty($this->artist)){
            	$query_str .= " a:".$this->artist;
     		}
    		 if (!empty($this->album)){
            	$query_str .= " l:".$this->album;
      		}
            $query_str =         
            preg_replace("/([\!\?\.\,])[\!\?\.\,]+/", "{1}", //remove multiple !?,. characters
                str_replace(array("{", "}", "<", ">", "@", "$", "%", "~", "#", "*", "|", "/", "_", ";", "^"), "",//remove stupid characters
                    preg_replace("/[\s]{2,}/", " ", //replace multiple spaces
                        strtolower( //lowercase 
                            trim($query_str) //trim duh
                        )
                    )
                )
            );
            $this->id = md5($query_str);
        }
        return $this->id;
    }
	
    public function singleSongSearch() {
        //todo: this
        if (count($songs['songs'])==1 && $page==1){
            return $songs['songs'][0];
        }
		
        if (!$this->exact) {
			foreach ($songs['songs'] AS $song){
			     //check for exact match
                if (!empty($this->title) && !empty($this->artist) && !empty($this->album)) {
                    if ((strtolower($this->title) === strtolower($song['SongName']) || ((int)$this->title && (int)$this->title === (int)$song['SongID'])) 
                        && (strtolower($this->album) === strtolower($song['AlbumName']) || ((int)$this->album && (int)$this->album === (int)$song['AlbumID'])) 
                        && (strtolower($this->artist) === strtolower($song['ArtistName']) || ((int)$this->artist && (int)$this->artist === (int)$song['ArtistID']))) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->title) && !empty($this->artist)) {
                    if ((strtolower($this->title) === strtolower($song['SongName']) || ((int)$this->title && (int)$this->title === (int)$song['SongID']))
                        && (strtolower($this->artist) === strtolower($song['ArtistName']) || ((int)$this->artist && (int)$this->artist === (int)$song['ArtistID']))) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->title) && !empty($this->album)) {
                    if ((strtolower($this->title) === strtolower($song['SongName']) || ((int)$this->title && (int)$this->title === (int)$song['SongID'])) 
                        && (strtolower($this->album) === strtolower($song['AlbumName']) || ((int)$this->album && (int)$this->album === (int)$song['AlbumID']))) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->artist) && !empty($this->album)) {
                    if ((strtolower($this->album) === strtolower($song['AlbumName']) || ((int)$this->album && (int)$this->album === (int)$song['AlbumID'])) 
                        && (strtolower($this->artist) === strtolower($song['ArtistName']) || ((int)$this->artist && (int)$this->artist === (int)$song['ArtistID']))) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->title)) {
                    if (strtolower($this->title) === strtolower($song['SongName']) || ((int)$this->title && (int)$this->title === (int)$song['SongID'])) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->artist)) {
                    if (strtolower($this->artist) === strtolower($song['ArtistName']) || ((int)$this->artist && (int)$this->artist === (int)$song['ArtistID'])) {
                        $this->exact = $song;
                        break;
                    }
                } elseif (!empty($this->album)) {
                    if (strtolower($this->album) === strtolower($song['AlbumName']) || ((int)$this->album && (int)$this->album === (int)$song['AlbumID'])) {
                        $this->exact = $song;
                        break;
                    }
                }
			}
        }
    }
    
	private static function performSongSearch($query, $max=null){        
        $results = array();
  		for($page=1;$page<=2;$page++){
			$songs = parent::getSongSearchResults($query, ($max ? $max : 91), ($page-1)*90);
			if ($songs === false || !isset($songs['songs']) || count($songs['songs'])<1) {
				break;
            }                

            if (count($songs['songs']) > 90 && (!$max || $max > 100)){
                array_pop($songs['songs']); //we need to check if there are more results
            }
            
            self::appendResults($songs['songs'], $results);
            
			if (count($songs['songs']) < 90 || ($max && count($results) > $max)) {
				break;
            }
        }
        if ($max) {
            return array_slice($results, 0, $max, true);
        } else {
            return $results;
        }
	}
    
    //strict limit of 95 when doing this.
    private static function getURLForSongSearchMulti($query, $max=null){
        if ($max > 95) {
            $max = 95;
        }
        $url = parent::getSongSearchResults($query, $max, null, true);
        return $url;
    }
    
    
    //send an array of gsSearch's
    //returns array of results
    //searches are also updated
    public static function performSongSearchMulti(&$searches, $max=null){        
        $URLs = array(array());
        $u = 0;
        $results = array();
        foreach ($searches as $i => $search) {
            //we always get at least 50
            if ($search->changed || (count($search->results) >= 50 && $max > count($search->results))) {
                if (count($URLs[$u]) >= self::MAX_PARALLEL_SEARCHES) {
                    $u++;
                    $URLs[$u] = array();
                }
                $URLs[$u][$i] = $search->songSearchResults($max, true);
                
                $results[$i] = null;
            } else {
                $results[$i] = $search->songSearchResults($max);
            }
        }
        if ($URLs && $URLs[0]) {            
            foreach ($URLs AS $URLsSet) {
                $resultsP = self::parallelCalls($URLsSet);
                if ($resultsP) {
                    foreach ($resultsP as $i => $result) {
                        try {
                            $resultD = json_decode($result, true);
                            if ($resultD && is_array($resultD) && isset($resultD['result']) && isset($resultD['result']['songs'])) {
                                if ($max) {
                                    if (isset($searches[$i])) $searches[$i]->setResults($resultD['result']['songs']);
                                    $results[$i] = array_slice($resultD['result']['songs'], 0, $max, true);
                                } else {
                                    if (isset($searches[$i])) $searches[$i]->setResults($resultD['result']['songs']);
                                    $results[$i] = $resultD['result']['songs'];
                                }                        
                            } else {
                                $results[$i] = null;
                            }
                        } catch (Exception $e) {
                            $results[$i] = $result; 
                        }                
                    }
                }
            }
        }
  		return $results;
	}
    	
	public function songSearchResults($max = null, $returnURLForMulti = false){	   
        //build request
		$query_str = "";
		if (!empty($this->title)){
			$query_str .= " song:".$this->title;
		}
		if (!empty($this->artist)){
        	$query_str .= " artist:".$this->artist;
 		}
		 if (!empty($this->album)){
        	$query_str .= " album:".$this->album;
  		}
        $query_str = trim($query_str);
        
  		if (empty($query_str)) {
  			return array();
        }
        
        //we always get at least 50
        if ($this->changed || (count($this->results) >= 50 && $max > count($this->results))) {
            $this->results = null;
            $this->changed = false;
            if ($returnURLForMulti) {
	           return self::getURLForSongSearchMulti($query_str, max($max, 50));
            } else {
                $this->results = self::performSongSearch($query_str, max($max, 50));
                return array_slice($this->results, 0, $max, true);
            }
        } else {
            if ($returnURLForMulti) {
	           return self::getURLForSongSearchMulti($query_str, max($max, 50));
            } else {
                if ($max) {
                    return array_slice($this->results, 0, $max, true);
                } else {
                    return $this->results;
                }
            }
        }        
	}
	
	public function getGSUserSongResult(){
		//build request
		
		if (empty($this->gsUserPass) || !preg_match('/^[A-Za-z0-9]{8}$/',$this->gsUserPass)){
        	trigger_error(__FUNCTION__." requires a GSUser password. ".$this->gsUserPass." You provided an invalid password.",E_USER_ERROR);
			return false;
		}
		
		$url = "http://gsuser.com/searchSongEx/".urlencode($this->title)."/".urlencode($this->artist)."/".urlencode($this->album)."/?password=".$this->gsUserPass;
		$result = gsapi::httpCall($url,'gsSearch-'.$this->gsUserPass);
		if ($result['http'] == 403){
			trigger_error(__FUNCTION__." invalid password sent.",E_USER_ERROR);
			return false;
		}elseif($result['http'] == 404){
			return false;
		}elseif($result['http'] == 200){
			return json_decode($result['raw'],true);
		}else{
			return $result['raw'];
		}
	}
	
	public function getSingleArtistResult(){
		//build request
		if (empty($this->artist))
			return false;
		$this->results=null;		  		
  		$this->listing = array(array(),array());
  				
		$artist_parse = explode(" ",$this->artist);
  		
  		for($page=1;$page<=5;$page++){
			$results = gsapi::getArtistSearchResults($this->artist,200,$page);
			if ($results === false || !isset($results['artists']) || count($results['artists'])<1)
				break;	
			
			$this->appendResults($results['artists']);
			
			foreach ($results['artists'] AS $arst){
				if (!isset($this->listing[0][$arst['ArtistID']])){
					$score = 0;
					if (!empty($this->artist)){
						if (!isset($artist_ranks[strtolower($arst['ArtistName'])]))
							$artist_ranks[strtolower($arst['ArtistName'])] = self::calculateScore(array($this->artist,$artist_parse),$arst['ArtistName'],1,true);
						$score += $artist_ranks[strtolower($arst['ArtistName'])];					
					}
					if ($arst['IsVerified'])
						$score *= 1.50; //50% boost for anything verified

					if ($score > .001){	
						$this->listing[0][$arst['ArtistID']] = $score;
						$this->listing[1][$arst['ArtistID']] = $arst;
					}
				}
			}
			if ($results['pager']['hasNextPage'] == false || (count($this->listing[0]) && max($this->listing[0])>.70)) //if its greater than 70% we pretty much have a match
				break;
		}
		if (count($this->listing[0])<1)
			return false;
		else{			
			arsort($this->listing[0]);
			if (reset($this->listing[0])<.005)
				return false; //this result is basically worthless
			return $this->listing[1][key($this->listing[0])];
		}
	}
	
	public function getSingleAlbumResult($version=1){
		//build request
		if (empty($this->album))
			return false;
		$this->results=null;		  		
  		$this->listing = array(array(),array());
  				
		$album_parse = explode(" ",trim(substr($this->album,0,(($pos = strpos($this->album,"("))==0 ? $pos : strlen($this->album))))); //we want to keep everying up to ( becasue we don't care about "Deluxe Edition" or "Single" or anything similar
  		
  		for($page=1;$page<=5;$page++){
  			if ($version==2)
  				$results = gsapi::getAlbumSearchResults2($this->album,200,$page);
  			else
				$results = gsapi::getAlbumSearchResults($this->album,200,$page);
			if ($results === false || !isset($results['albums']) || count($results['albums'])<1)
				break;	
			
			$this->appendResults($results['albums']);
			
			foreach ($results['albums'] AS $albm){
				if (!isset($this->listing[0][$albm['AlbumID']])){
					$score = 0;
					
					if (!isset($album_ranks[strtolower($albm['AlbumName'])]))
						$album_ranks[strtolower($albm['AlbumName'])] = self::calculateScore(array($this->album,$album_parse),$albm['AlbumName'],1,true);
					$score += $album_ranks[strtolower($albm['AlbumName'])]*(count($album_parse)>1?.6:1);
					
					if (count($album_parse)>1){		
						if (!isset($album_ranks[strtolower($albm['ArtistName'].$albm['AlbumName'])]))
							$album_ranks[strtolower($albm['ArtistName'].$albm['AlbumName'])] = self::calculateScore(array($this->album,$album_parse),$albm['ArtistName'].$albm['AlbumName'],1,true);
						$score += $album_ranks[strtolower($albm['ArtistName'].$albm['AlbumName'])]*.4;
					}
									
					if ($albm['IsVerified'])
						$score *= 1.50; //50% boost for anything verified
					if ($score > .001){	
						$this->listing[0][$albm['AlbumID']] = $score;
						$this->listing[1][$albm['AlbumID']] = $albm;
					}
				}
			}
			if ($results['pager']['hasNextPage'] == false || (count($this->listing[0]) && max($this->listing[0])>.70)) //if its greater than 70% we pretty much have a match
				break;
		}
		if (count($this->listing[0])<1)
			return false;
		else{			
			arsort($this->listing[0]);
			if (reset($this->listing[0])<.005)
				return false; //this result is basically worthless
			return $this->listing[1][key($this->listing[0])];
		}
	}
	
	private static function appendResults($results, &$toResults){
	    if (!is_array($toResults)) {
	       $toResults = $results;
		} else {
			$start = count($toResults);
            $i = 0;
			foreach($results AS $v) {
				$toResults[($i++)+$start] = $v;
            }
		}		
	}
	
	//todo build support for >255 chars
	private static function calculateScore($search,$result,$rank=1,$lesssub=false){
		$len = strlen($search[0]);
		$words = count($search[1]);
		if (isset($result)){
			$dist = (($dist = levenshtein(strtolower($result),strtolower($search[0])))==0 ? 1 : ($dist>1?(($len*1.3-$dist)/$len):0));
			if ($words > 1){
				foreach($search[1] AS $word){
					if (strlen($word) > 2){
						$imp = (($len/$len)+(1/$words)); //importance of word
						if(strpos($result,$word)!==false){
							if (!$lesssub)
								$dist += $imp*.1;
							else
								$dist += $imp*.2;
						}else{
							if (!$lesssub)
								$dist -= $imp*.3;
							else
								$dist -= $imp*.15;
						}
					}
				}
			}
			return $dist*$rank;			
		}
		return 0;
	}
    
    public static function parallelCalls($urls) {
        // Create get requests for each URL
        $mh = curl_multi_init();
        foreach($urls as $i => $url)
        {
            if ($url) {
                $ch[$i] = curl_init($url);
                curl_setopt($ch[$i], CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch[$i], CURLOPT_CONNECTTIMEOUT, 6);
                curl_setopt($ch[$i], CURLOPT_TIMEOUT, 10);
                curl_multi_add_handle($mh, $ch[$i]);
            }
        }
    
        // Start performing the request
        do {
            $execReturnValue = curl_multi_exec($mh, $runningHandles);
        } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
        // Loop and continue processing the request
        while ($runningHandles && $execReturnValue == CURLM_OK) {
            // Wait forever for network
            $numberReady = curl_multi_select($mh);
            if ($numberReady != -1) {
                // Pull in any new data, or at least handle timeouts
                do {
                    $execReturnValue = curl_multi_exec($mh, $runningHandles);
                } while ($execReturnValue == CURLM_CALL_MULTI_PERFORM);
            }
        }
    
        // Check for any errors
        if ($execReturnValue != CURLM_OK) {
            error_log("Curl multi read error $execReturnValue\n", E_USER_WARNING);
        }
    
        // Extract the content
        foreach($urls as $i => $url) {
            // Check for errors
            if ($url && $ch[$i]) {                
                $curlError = curl_error($ch[$i]);
                if($curlError == "") {
                    $res[$i] = curl_multi_getcontent($ch[$i]);
                } else {
                    $res[$i] = null;
                }
                // Remove and close the handle
                curl_multi_remove_handle($mh, $ch[$i]);
                curl_close($ch[$i]);
            }
        }
        // Clean up the curl_multi handle
        curl_multi_close($mh);
        
        return $res;
    }
	
}

?>