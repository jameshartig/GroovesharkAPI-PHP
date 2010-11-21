<?php

/**
 * @author James Hartig
 * @copyright 2010
 */

class gsSearch extends gsAPI{
	
    private $parent;
	public $artist = null;
	public $album = null;
	public $title = null;
	private $listing;
	private $results;
	private $gsUserPass = null;
	
	function gsSearch($password=null){
		$this->gsUserPass = $password;
	}
    
    protected function spawnAble(&$parent=null){
	   if (is_object($parent)) {
	       $this->parent = $parent;
	   }        
    }
	
	public function setArtist($artist){
		$this->artist = $artist;
	}
	
	public function setTitle($title){
		$this->title = $title;
	}
	
	public function setAlbum($album){
		$this->album = $album;
	}
	
	public function clear(){ //clears all the above search params
		$this->album = null;
		$this->artist = null;
		$this->title = null;
		$this->listing = null;
		$this->results = null;
	}
	
	public function getSingleSongResult(){
		//build request
		$query_str = "";
		$total=0;
		if (!empty($this->title)){
			$total++;
			$query_str .= " song: ".$this->title;
		}
		if (!empty($this->artist)){
			$total++;
        	$query_str .= " artist: ".$this->artist;
 		}
		 if (!empty($this->album)){
			$total++;
        	$query_str .= " album: ".$this->album;
  		}
  		if (empty($query_str))
  			return false;
  		
  		$this->results=null;
  		$this->listing = array(array(),array());
  				
		$artist_parse = explode(" ",$this->artist);
		$album_parse = explode(" ",trim(substr($this->album,0,(($pos = strpos($this->album,"("))==0 ? $pos : (($pos = strpos($this->album,"["))==0 ? $pos  : strlen($this->album)) )))); //we want to keep everying up to ( or [ becasue we don't care about "Deluxe Edition" or "Single" or anything similar
		$title_parse = explode(" ",$this->title);
		
		$album_ranks = array(); //temporary storage to avoid calling calculate score a million times
		$artist_ranks = array(); //temporary storage to avoid calling calculate score a million times
  		
  		for($page=1;$page<=5;$page++){
			$songs = gsAPI::getSongSearchResults(trim($query_str),100,$page);
			if ($songs === false || !isset($songs['songs']) || count($songs['songs'])<1)
				break;

			$this->appendResults($songs['songs']);
			
			foreach ($songs['songs'] AS $song){
				if (!isset($this->listing[0][$song['SongID']])){
					$score = 0;
					if (!empty($this->title))
						$score += self::calculateScore(array($this->title,$title_parse),$song['SongName'],8/(9+$total),($total==1?true:false));
					if (!empty($this->artist)){
						if (!isset($artist_ranks[strtolower($song['ArtistName'])]))
							$artist_ranks[strtolower($song['ArtistName'])] = self::calculateScore(array($this->artist,$artist_parse),$song['ArtistName'],7/(9+$total),($total==1?true:false));
						$score += $artist_ranks[strtolower($song['ArtistName'])];					
					}
					if (!empty($this->album)){
						if (!isset($album_ranks[strtolower($song['AlbumName'])]))
							$album_ranks[strtolower($song['AlbumName'])] = self::calculateScore(array($this->album,$album_parse),$song['AlbumName'],4/(9+$total),($total==1?true:false));
						$score += $album_ranks[strtolower($song['AlbumName'])];					
					}
					if ($song['IsVerified'])
						$score *= 1.50; //50% boost for anything verified
					if ($score > .005){
						$this->listing[0][$song['SongID']] = $score;
						$this->listing[1][$song['SongID']] = $song;
					}
				}
			}
			if ($songs['pager']['hasNextPage'] == false || (count($this->listing[0]) && max($this->listing[0])>.70)) //if its greater than 70% we pretty much have a match
				break;
		}	
		
		if (count($this->listing[0])<1)
			return false;
		else{			
			arsort($this->listing[0]);
			if (reset($this->listing[0])<.01)
				return false; //this result is basically worthless
			return $this->listing[1][key($this->listing[0])];
		}
	}
	
	public function getSongResults(){}
	
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
	
	private function appendResults($results){
		if (!is_array($this->results))
			$this->results = $results;
		else{
			$start = count($this->results)-1;
			foreach($results AS $k => $v)
				$this->results[$k+$start] = $v;
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
	
}

?>