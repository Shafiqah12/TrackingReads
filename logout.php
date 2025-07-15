<?php
// logout.php
// This file handles logging out a user by destroying their session.

// Start the PHP session. This is crucial to access and manipulate session variables.
session_start();

// Unset all of the session variables.
// This removes all data stored in the current session.
$_SESSION = array();

// Destroy the session.
// This completely removes the session from the server.
session_destroy();

// Redirect to the login page after logging out.
// Adjust the path if your login.php is in a different directory relative to logout.php.
// If logout.php is in the root and login.php is in the root, then "login.php" is correct.
header("location: login.php");

// Ensure that no further code is executed after the redirect.
exit;
?>
