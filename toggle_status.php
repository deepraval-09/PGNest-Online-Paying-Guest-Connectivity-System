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

    // 2. SECURITY: Verify Ownership
    // We strictly check 'AND landlord_id = $landlord_id' to prevent hacking
    $check_sql = "SELECT availability_status FROM properties WHERE prop_id = $prop_id AND landlord_id = $landlord_id";
    $result = $conn->query($check_sql);

    if ($result->num_rows > 0) {
        // 3. DETERMINE NEW STATUS
        if (isset($_GET['status'])) {
            // If a specific status was requested (e.g. from the specific buttons)
            $new_status = intval($_GET['status']);
        } else {
            // Smart Toggle: If no status sent, just flip the current one
            $row = $result->fetch_assoc();
            $new_status = ($row['availability_status'] == 1) ? 0 : 1;
        }

        // 4. UPDATE DATABASE
        $update_sql = "UPDATE properties SET availability_status = $new_status WHERE prop_id = $prop_id";
        $conn->query($update_sql);
    }
}

// 5. REDIRECT BACK TO DASHBOARD
header("Location: dashboard.php");
exit;
?> 