<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
if (isset($_SESSION['user_id'])) {
    auditLog($_SESSION['user_id'], 'LOGOUT', "User logged out");
}
session_destroy();
header("Location: index.php");
exit;
?>

