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
<?php
/**
 * Here's an example file that shows how to authenticate to the API and get a list of the user's playlists
 * A live demo can be found at: http://togrooveshark.com/example.php
 */
session_start();
require("gsAPI.php");
$gs = new gsAPI("example", "1a79a4d60de6718e8e5b326e338ae533"); //note: you can also change the default key/secret in gsAPI.php
$user = null;
if (!empty($_SESSION['gsSessionID'])) {
    //since we already have the gsSessionID lets restore that and see if were logged in already to Grooveshark
    $gs->setSession($_SESSION['gsSessionID']);
    if (!empty($_GET['token'])) {
        //we must've gotten back from Grooveshark after the user authenticated
        $user = $gs->authenticateToken($_GET['token']);
        //the logged in user is saved in gsSessionID and you don't need to store anything else on your end
        //when the user refreshes we will restore the gsSessionID and get the user again
    } else { 
        $user = $gs->getUserInfo();
    }
    if (empty($user['UserID'])) {
        //not logged in
        $user = null;
    }
} else {
    //since we didn't already have a gsSessionID, start one with Grooveshark and store it  
    $sessionID = $gs->startSession();
    if (empty($sessionID)) {
        //something failed
        exit;
    }
    $_SESSION['gsSessionID'] = $sessionID;
}

//if were already logged in then $user would not be null, and so don't authenticate
if (is_null($user)) {
    //user is not logged in so we must redirect to Grooveshark to get a token to authenticate the user
    //the auth page will ask for the user to approve your app and give permission, upon approval, it'll redirect back
    header("Location: https://grooveshark.com/auth/?app=example&callback=http://example.com/example.php", true, 307);
    exit;
}

$playlists = $gs->getUserPlaylists();
if (!is_array($playlists)) {
    //something failed.
    exit;
}
foreach ($playlists as $playlist) {
    echo "<p>Playlist: " . htmlentities($playlist['PlaylistName']) . "</p>";
}
?>
```

## Need help? Something broken?
If you have found a bug file it using Github Issues. Feel free to ping [James on Twitter](http://twitter.com/jameshartig) as well.

If you're running into problems with your key, if you're hitting rate limits, or if you're confused, contact support@grooveshark.com.
They'll be more than happy to help! Sometimes you'll even get a response from James.