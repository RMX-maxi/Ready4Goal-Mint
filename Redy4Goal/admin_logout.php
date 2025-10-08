<?php
session_start();

// Destroy all admin session data
session_unset();
session_destroy();

// Redirect back to login page
header("Location: registration.php?message=You have been logged out successfully");
exit;
?>
