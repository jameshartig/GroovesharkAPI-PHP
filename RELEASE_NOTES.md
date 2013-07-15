# Release Notes (7/15/13)

## Changes in V2
* Methods are all dynamic now (with backwards-compatible support for static in some cases)
* SessionID is sent with all requests (fixes #6)
* Methods updated to match latest API changes
* Supports PHP-based DNS (instead of passing host to curl) via gsAPI::$usePHPDNS
* Returns updated to be more clear and straightforward
* Code is following actual standards and is much more readable
* Removed gsUser/gsSearch/gsPlaylist bogus classes
* getAlbumsInfo/getSongsInfo/getArtistInfo now have returnByIDs param to get results as a keyed array

## Changes that break backwards-compatibility
* All static methods are now dynamic. PHP will throw a E_STRICT notice if you are still calling them statically, though they will still work for now.
* PHP 5.2+ supported
* ws_key and ws_secret are now wsKey and wsSecret (These were private anyways)
* gsAPI::$lastError is removed in favor of trigger_error's ($lastError was unreliable)
* gsUser/gsSearch/gsPlaylist classes are gone (They were not maintained and were extraneous)
* authenticateUser is removed (There is no more gsUser. Use authenticate instead.)
* getUserToken is removed (The param is no longer "token", so this is useless and confusing)
* getArtistInfo, getSongInfo, getAlbumInfo now return just the artist/song/album specified instead of an array with 1 element
* getDoesSongExist/getDoesArtistExist/getDoesAlbumExist now returns array('exists' => boolean)
* getArtistAlbums returns just the array of albums now instead of an array containing another array of albums
* getArtistVerifiedAlbums returns just the array of verified albums now instead of an array containing another array of albums
* getCountry no longer defaults to $_SERVER['REMOTE_ADDR']. It defaults to null and so Grooveshark's end will default to caller's IP.
* getAlbumSearchResultsWithSongs is removed (It was gross and you should only get songs for albums you actually care about)
