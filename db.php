<?php
// 1. ENABLE ERROR REPORTING (Crucial for debugging)
// This ensures you never get a "Blank White Screen" of death.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 2. DATABASE CREDENTIALS
$servername = "";
$username = "";
$password = ""; 
$dbname = "";

// 3. CREATE CONNECTION
$conn = new mysqli($servername, $username, $password, $dbname);

// 4. CHECK CONNECTION
if ($conn->connect_error) {
    // If connection fails, stop everything and show the error
    die("❌ Database Connection Failed: " . $conn->connect_error);
}

// 5. SET CHARACTER SET (Supports Emojis and special characters)
$conn->set_charset("utf8mb4");

// --- HELPER FUNCTION: SEND NOTIFICATION ---
// We use this function everywhere to send alerts easily
if (!function_exists('send_notification')) {
    function send_notification($conn, $user_id, $message, $type, $link) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, link) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isss", $user_id, $message, $type, $link);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>