<?php
/**
 * Admin Logout
 */

require_once '../config/database.php';

// Clear admin session
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);
unset($_SESSION['admin_name']);

// Redirect to admin login
header('Location: login.php');
exit;
?>
