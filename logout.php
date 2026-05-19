<?php
session_start();

// 1. DESTROY ALL SESSION DATA
session_unset();
session_destroy();

// 2. REDIRECT TO LOGIN
header("Location: login.php?message=Logged out successfully");
exit;
?>