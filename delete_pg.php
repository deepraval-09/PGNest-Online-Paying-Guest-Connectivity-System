<?php
session_start();
include 'db.php';

// 1. SECURITY: Login & Role Check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id'])) {
    $prop_id = intval($_GET['id']);
    $landlord_id = $_SESSION['user_id'];

    // 2. SECURITY: Verify Ownership before deleting
    $check_sql = "SELECT image_main FROM properties WHERE prop_id = $prop_id AND landlord_id = $landlord_id";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // 3. DELETE IMAGE FILE (Cleanup server space)
        $image_path = "uploads/" . $row['image_main'];
        if (file_exists($image_path)) {
            unlink($image_path);
        }

        // 4. DELETE FROM DATABASE
        $del_sql = "DELETE FROM properties WHERE prop_id = $prop_id";
        $conn->query($del_sql);
    }
}

// 5. REDIRECT BACK TO DASHBOARD
header("Location: dashboard.php");
exit;
?>