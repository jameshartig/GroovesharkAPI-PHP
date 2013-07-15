# Grooveshark Public API PHP Library
Officially maintained by James Hartig ([@jameshartig](http://twitter.com/jameshartig)). If you run into any problems, file a bug or contact support@grooveshark.com.

Documentation about the API can be found at [http://developers.grooveshark.com/docs/public_api/v3/](http://developers.grooveshark.com/docs/public_api/v3/).

**Version 2 was released on 7/15/13. For changes (including breaking ones), see [RELEASE_NOTES](/RELEASE_NOTES.md/).**

## Prerequisites
* You must have an official public API key/secret. If you do not have one, fill out the [request form](http://developers.grooveshark.com/api).
* Must be running PHP 5.2+

## Getting Started

Keep in mind some of the methods below are access controlled and not everyone has access by default to them.

```PHP
require("gsAPI.php");
$gs = new gsAPI("example", "1a79a4d60de6718e8e5b326e338ae533"); //note: you can also change the default key/secret in gsAPI.php
$sessionID = $gs->startSession();
$user = $gs->authenticate("test", "test");
if (empty($user) || $user['UserID'] < 1) {
    // Login failed. invalid username/password
    exit;
}
$playlists = $gs->getUserPlaylists(5);
if (!is_array($playlists)) {
    //something failed.
    exit;
}
foreach ($playlists as $playlist) {
    echo "Playlist: {$playlist['PlaylistName']}\n";
}
```

## Need help? Something broken?
If you have found a bug file it using Github Issues. Feel free to ping [James on Twitter](http://twitter.com/jameshartig) as well.

If you're running into problems with your key, if you're hitting rate limits, or if you're confused, contact support@grooveshark.com.
They'll be more than happy to help! Sometimes you'll even get a response from James.