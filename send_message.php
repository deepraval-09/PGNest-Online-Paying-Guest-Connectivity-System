<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    die("Access Denied");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sender_id = $_SESSION['user_id'];
    $receiver_id = intval($_POST['receiver_id']);
    $message = $conn->real_escape_string($_POST['message']);

    // FIXED: Use NULL if no property ID is provided
    // This tells the DB "This message isn't tied to a specific house"
    $prop_id = isset($_POST['prop_id']) ? intval($_POST['prop_id']) : "NULL";

    if (!empty($message)) {
        // FIXED QUERY: Inserting NULL correctly
        $sql = "INSERT INTO messages (sender_id, receiver_id, message, is_read, prop_id) 
                VALUES ($sender_id, $receiver_id, '$message', 0, $prop_id)";
        
        if ($conn->query($sql)) {
            
            // --- NEW: NOTIFICATION LOGIC ---
            // 1. Get Sender's Name (To show "New message from Deep")
            $sender_q = $conn->query("SELECT full_name FROM users WHERE user_id = $sender_id");
            $sender_name = "Someone";
            if ($sender_q && $sender_q->num_rows > 0) {
                $sender_name = $sender_q->fetch_assoc()['full_name'];
            }
            
            // 2. Create short preview of message
            $raw_msg = $_POST['message']; // Use raw message for clean text
            $msg_preview = substr($raw_msg, 0, 30) . (strlen($raw_msg) > 30 ? '...' : '');
            $notif_msg = "💬 New message from $sender_name: '$msg_preview'";
            
            // 3. Send Notification (Link redirects receiver to chat with sender)
            $link = "chat_view.php?user_id=" . $sender_id;
            
            if (function_exists('send_notification')) {
                send_notification($conn, $receiver_id, $notif_msg, "info", $link);
            }
            // --------------------------------

            // SUCCESS: Redirect back to the conversation
            header("Location: chat_view.php?user_id=" . $receiver_id); 
            exit;
        } else {
            echo "Error: " . $conn->error;
        }
    } else {
        header("Location: chat_view.php?user_id=" . $receiver_id);
        exit;
    }
} else {
    header("Location: inbox.php");
    exit;
}
?>