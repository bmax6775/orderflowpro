<?php
session_start();
require_once 'config.php';

if (isLoggedIn()) {
    // Log the logout activity
    logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    
    // Destroy session
    session_destroy();
}

// Redirect to login page
header('Location: login.php');
exit();
?>
