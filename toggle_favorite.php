<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$prop_id = intval($_GET['id']);

// Check if already favorite
$check = $conn->query("SELECT * FROM favorites WHERE user_id = $user_id AND prop_id = $prop_id");

if ($check->num_rows > 0) {
    // Remove
    $conn->query("DELETE FROM favorites WHERE user_id = $user_id AND prop_id = $prop_id");
} else {
    // Add
    $conn->query("INSERT INTO favorites (user_id, prop_id) VALUES ($user_id, $prop_id)");
}

// Redirect back to the previous page
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?> 