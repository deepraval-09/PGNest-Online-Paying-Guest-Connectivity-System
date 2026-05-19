<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("<h2>❌ Please Login as a Student first, then run this file.</h2>");
}

$uid = $_SESSION['user_id'];

echo "<h2>🔧 PGNest System Repair</h2>";

// 1. Fix Database Structure (Ensure credits column exists and is not NULL)
$conn->query("ALTER TABLE users MODIFY COLUMN credits INT DEFAULT 0");
$conn->query("UPDATE users SET credits = 0 WHERE credits IS NULL");

echo "✅ Database structure repaired.<br>";

// 2. Force-Add Credits to YOU (Compensate for the failed purchase)
$expiry = date('Y-m-d', strtotime('+30 days'));
$sql = "UPDATE users SET credits = 10, subscription_plan = 'nest_pass', subscription_expiry = '$expiry' WHERE user_id = $uid";

if ($conn->query($sql)) {
    echo "✅ <strong>Success!</strong> You have been given 10 Credits manually.<br>";
    echo "✅ Plan activated until: $expiry<br>";
    echo "<br><a href='my_account.php' style='font-size: 20px; font-weight: bold;'>Go to My Account & Check</a>";
} else {
    echo "❌ Error: " . $conn->error;
}
?>  