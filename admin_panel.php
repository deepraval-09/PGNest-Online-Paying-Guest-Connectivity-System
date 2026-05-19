<?php
session_start();
include 'db.php';

// 1. SECURITY: STRICTLY ADMIN ONLY
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    echo "<h2 style='text-align:center; margin-top:50px; color:red;'>⛔ Access Denied</h2>";
    echo "<p style='text-align:center;'>You do not have permission to view this page.</p>";
    echo "<div style='text-align:center;'><a href='login.php'>Login as Admin</a></div>";
    exit;
}

// --- HANDLE ACTIONS ---

// A. Approve Verification (User Docs)
if (isset($_GET['approve_user'])) {
    $uid = intval($_GET['approve_user']);
    $conn->query("UPDATE users SET is_doc_verified = 1 WHERE user_id = $uid");
    
    // Notify User
    if (function_exists('send_notification')) {
        send_notification($conn, $uid, "🎉 Your profile has been verified! You now have the Verified Badge.", "success", "dashboard.php");
    }

    echo "<script>alert('User Verified Successfully!'); window.location.href='admin_panel.php';</script>";
}

// A2. REJECT VERIFICATION
if (isset($_GET['reject_user'])) {
    $uid = intval($_GET['reject_user']);
    
    // Wipe the document so they can re-upload
    $conn->query("UPDATE users SET verification_doc = NULL WHERE user_id = $uid");
    
    // Notify User
    if (function_exists('send_notification')) {
        send_notification($conn, $uid, "❌ Your verification document was rejected. Please upload a clear, valid ID.", "danger", "verify_account.php");
    }

    echo "<script>alert('Document Rejected. The user has been notified to re-upload.'); window.location.href='admin_panel.php';</script>";
}

// --- NEW: A3. UNBAN LANDLORD (RESET STRIKES) ---
if (isset($_GET['unban_user'])) {
    $uid = intval($_GET['unban_user']);
    
    // 1. Reset strikes to 0
    $conn->query("UPDATE users SET strikes = 0 WHERE user_id = $uid");
    
    // 2. Reactivate all their properties
    $conn->query("UPDATE properties SET availability_status = 1 WHERE landlord_id = $uid");
    
    // 3. Notify the Landlord
    if (function_exists('send_notification')) {
        send_notification($conn, $uid, "🎉 Good news! Your account has been unbanned by the Admin and your properties are live again.", "success", "dashboard.php");
    }

    echo "<script>alert('Landlord successfully unbanned! Properties are live.'); window.location.href='admin_panel.php';</script>";
}
// -----------------------------------------------

// B. Delete Property
if (isset($_GET['delete_prop'])) {
    $pid = intval($_GET['delete_prop']);
    
    // Notify Landlord before deleting
    $p_res = $conn->query("SELECT landlord_id, title FROM properties WHERE prop_id = $pid");
    if($p_res->num_rows > 0) {
        $p_data = $p_res->fetch_assoc();
        if (function_exists('send_notification')) {
            send_notification($conn, $p_data['landlord_id'], "⚠️ Your property '{$p_data['title']}' was removed by Admin.", "danger", "dashboard.php");
        }
    }

    $conn->query("DELETE FROM properties WHERE prop_id = $pid");
    echo "<script>alert('Property Deleted.'); window.location.href='admin_panel.php';</script>";
}

// C. APPROVE PAYMENT
if (isset($_GET['approve_pay'])) {
    $txn_id = intval($_GET['approve_pay']);
    
    // Get Transaction Details
    $t_res = $conn->query("SELECT * FROM transactions WHERE id = $txn_id AND status='pending'");
    
    if ($t_res->num_rows > 0) {
        $txn = $t_res->fetch_assoc();
        $uid = $txn['user_id'];
        $desc = $txn['description'];

        // 1. Mark Transaction as Approved
        $conn->query("UPDATE transactions SET status='approved' WHERE id=$txn_id");

        // 2. Give Benefit to User
        if (strpos($desc, 'Nest Pass') !== false) {
            $conn->query("UPDATE users SET credits = credits + 10, subscription_plan = 'nest_pass', subscription_expiry = DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) WHERE user_id = $uid");
        } elseif (strpos($desc, 'Premium') !== false) {
            $conn->query("UPDATE users SET subscription_plan = 'premium', subscription_expiry = DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY) WHERE user_id = $uid");
        }

        // 3. Notify the User
        if (function_exists('send_notification')) {
            $role_check = $conn->query("SELECT role FROM users WHERE user_id = $uid")->fetch_assoc();
            $link = ($role_check && $role_check['role'] == 'landlord') ? 'dashboard.php' : 'my_account.php';
            $msg = "✅ Payment Approved! Your $desc has been successfully activated.";
            send_notification($conn, $uid, $msg, "success", $link);
        }

        echo "<script>alert('Payment Verified & Credits Added!'); window.location.href='admin_panel.php';</script>";
    }
}

// D. REJECT PAYMENT
if (isset($_GET['reject_pay'])) {
    $txn_id = intval($_GET['reject_pay']);
    
    // Fetch User ID to notify them
    $t_res = $conn->query("SELECT user_id FROM transactions WHERE id = $txn_id");
    if ($t_res->num_rows > 0) {
        $uid = $t_res->fetch_assoc()['user_id'];
        
        $conn->query("UPDATE transactions SET status='rejected' WHERE id=$txn_id");
        
        // Notify the user
        if (function_exists('send_notification')) {
            $msg = "❌ Payment Rejected. The UTR/Transaction ID did not match. Please try again.";
            send_notification($conn, $uid, $msg, "danger", "pricing.php");
        }
    } else {
        $conn->query("UPDATE transactions SET status='rejected' WHERE id=$txn_id");
    }
    
    echo "<script>alert('Payment Rejected. No credits were added.'); window.location.href='admin_panel.php';</script>";
}

// E. HANDLE REPORTS & STRIKES
if (isset($_GET['resolve_report']) && isset($_GET['action'])) {
    $report_id = intval($_GET['resolve_report']);
    $action = $_GET['action'];

    $r_res = $conn->query("SELECT r.*, p.landlord_id, p.title 
                           FROM reports r 
                           JOIN properties p ON r.prop_id = p.prop_id 
                           WHERE r.report_id = $report_id AND r.status = 'pending'");
    
    if (!$r_res) {
        $r_res = $conn->query("SELECT r.*, p.landlord_id, p.title 
                               FROM reports r 
                               JOIN properties p ON r.prop_id = p.prop_id 
                               WHERE r.id = $report_id AND r.status = 'pending'");
    }

    if ($r_res && $r_res->num_rows > 0) {
        $report = $r_res->fetch_assoc();
        $student_id = $report['student_id'];
        $landlord_id = $report['landlord_id'];
        $prop_title = $conn->real_escape_string($report['title']);
        $actual_pk = isset($report['report_id']) ? 'report_id' : 'id';

        if ($action == 'refund_strike') {
            $conn->query("UPDATE reports SET status = 'refunded' WHERE $actual_pk = $report_id");
            
            $conn->query("UPDATE users SET credits = credits + 1 WHERE user_id = $student_id");
            if (function_exists('send_notification')) {
                send_notification($conn, $student_id, "✅ Your report for '$prop_title' was approved. 1 Credit refunded.", "success", "my_account.php");
            }

            $conn->query("UPDATE users SET strikes = strikes + 1 WHERE user_id = $landlord_id");
            
            $l_res = $conn->query("SELECT strikes FROM users WHERE user_id = $landlord_id");
            $strikes = $l_res->fetch_assoc()['strikes'];

            if ($strikes >= 3) {
                $conn->query("UPDATE properties SET availability_status = 0 WHERE landlord_id = $landlord_id");
                if (function_exists('send_notification')) {
                    send_notification($conn, $landlord_id, "⛔ ACCOUNT RESTRICTED: You received 3 strikes for ignoring student contacts. Your properties are now hidden.", "danger", "dashboard.php");
                }
            } else {
                if (function_exists('send_notification')) {
                    send_notification($conn, $landlord_id, "⚠️ WARNING: A student reported your number for '$prop_title'. Strike $strikes/3.", "danger", "dashboard.php");
                }
            }
            echo "<script>alert('Report Resolved! Student refunded and Strike issued to landlord.'); window.location.href='admin_panel.php';</script>";

        } elseif ($action == 'dismiss') {
            $conn->query("UPDATE reports SET status = 'rejected' WHERE $actual_pk = $report_id");
            if (function_exists('send_notification')) {
                send_notification($conn, $student_id, "ℹ️ Your report for '$prop_title' was reviewed and dismissed.", "info", "pg_details.php?id=".$report['prop_id']);
            }
            echo "<script>alert('Report Dismissed.'); window.location.href='admin_panel.php';</script>";
        }
    }
}
// ----------------------------------------

// --- FETCH DATA ---

$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
$total_props = $conn->query("SELECT COUNT(*) as c FROM properties")->fetch_assoc()['c'];

// 1. Pending Documents
$pending_verifications = $conn->query("SELECT * FROM users WHERE verification_doc IS NOT NULL AND is_doc_verified = 0");

// 2. Pending Payments
$pending_pay = $conn->query("SELECT t.*, u.full_name, u.phone FROM transactions t JOIN users u ON t.user_id = u.user_id WHERE t.status = 'pending'");

// 3. Pending Reports
$pending_reports = $conn->query("SELECT r.*, u.full_name as student_name, p.title as prop_title, lu.full_name as landlord_name 
                                 FROM reports r 
                                 JOIN users u ON r.student_id = u.user_id 
                                 JOIN properties p ON r.prop_id = p.prop_id 
                                 JOIN users lu ON p.landlord_id = lu.user_id 
                                 WHERE r.status = 'pending' ORDER BY r.created_at DESC");

// --- NEW: 3.5 Banned Landlords ---
$banned_landlords = $conn->query("SELECT user_id, full_name, email, phone, strikes FROM users WHERE role = 'landlord' AND strikes >= 3");
// ---------------------------------

// 4. All Properties
$all_props = $conn->query("SELECT p.*, u.full_name FROM properties p JOIN users u ON p.landlord_id = u.user_id ORDER BY p.created_at DESC");

include 'header.php';
?>

<style>
    /* Admin Panel Styling Fixes for Dark Mode */
    .admin-table-row {
        border-bottom: 1px solid var(--border-color);
    }
    
    /* OVERRIDE GLOBAL TH for Disputes */
    .th-danger {
        background-color: var(--primary) !important;
        color: #ffffff !important;
    }

    /* Dispute Row Specific Colors */
    .dispute-row {
        background-color: #fff8f8;
        border-bottom: 1px solid var(--border-color);
        transition: background-color 0.3s;
    }
    
    body.dark-mode .dispute-row {
        background-color: rgba(15, 76, 129, 0.05); /* Soft Blue Tint for consistency */
    }

    /* ONLY target the property link, NOT the buttons */
    body.dark-mode .dispute-row a.prop-link {
        color: var(--primary) !important; 
    }

    /* Action Buttons Stack Layout */
    .action-stack {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    /* Action Buttons Row Layout (for Verification) */
    .action-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* Empty state styling */
    .empty-state {
        padding: 20px; 
        background: var(--bg-card); 
        color: var(--text-light); 
        border-radius: 8px; 
        text-align: center;
        border: 1px dashed var(--border-color);
    }

    /* --- CSS FOR PROFESSIONAL BUTTONS & ALIGNMENT --- */
    td, th {
        vertical-align: middle !important; /* Forces perfect vertical centering */
    }

    /* Force Table Layout for consistent column widths */
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed; /* Crucial for alignment */
    }

    .admin-action-btn {
        display: inline-block;
        padding: 8px 14px;
        font-size: 0.85rem;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        text-align: center;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        width: 100%; /* Make buttons fill the action stack nicely */
        box-sizing: border-box;
    }

    /* Green Positive Button */
    .btn-positive {
        background-color: #28a745;
        color: #ffffff !important;
        border: 1px solid #28a745;
    }
    .btn-positive:hover {
        background-color: #218838;
        border-color: #1e7e34;
        transform: translateY(-2px);
    }

    /* Red Negative Button */
    .btn-negative {
        background-color: #dc3545;
        color: #ffffff !important;
        border: 1px solid #dc3545;
    }
    .btn-negative:hover {
        background-color: #c82333;
        border-color: #bd2130;
        transform: translateY(-2px);
    }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">

    <div class="dashboard-header">
        <div>
            <h1 style="color: var(--primary); margin-bottom: 5px;">Admin Control Panel</h1>
            <p style="color: var(--text-light);">System Overview & Moderation</p>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <h2><?php echo $total_users; ?></h2>
            <p>Total Users</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--accent);">
            <h2><?php echo $total_props; ?></h2>
            <p>Total Properties</p>
        </div>
        <div class="stat-card" style="border-left-color: var(--success);">
            <h2><?php echo $pending_pay->num_rows; ?></h2>
            <p>Pending Payments</p>
        </div>
    </div>

    <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
         Payment Requests
    </h2>
    
    <?php if ($pending_pay->num_rows > 0): ?>
        <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">User</th>
                    <th style="padding: 12px; width: 15%; background-color: var(--primary); color: white;">Amount</th>
                    <th style="padding: 12px; width: 25%; background-color: var(--primary); color: white;">Transaction ID</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Purpose</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $pending_pay->fetch_assoc()): ?>
                    <tr class="admin-table-row">
                        <td style="padding: 12px; word-wrap: break-word;">
                            <strong><?php echo htmlspecialchars($row['full_name']); ?></strong><br>
                            <small style="color: var(--text-light);"><?php echo $row['phone']; ?></small>
                        </td>
                        <td style="padding: 12px; font-weight: bold;">₹<?php echo $row['amount']; ?></td>
                        <td style="padding: 12px; color: var(--primary); font-family: monospace; font-size: 1.1rem; word-break: break-all;">
                            <?php echo htmlspecialchars($row['utr_number']); ?>
                        </td>
                        <td style="padding: 12px; word-wrap: break-word;"><?php echo $row['description']; ?></td>
                        <td style="padding: 12px;">
                            <div class="action-stack">
                                <a href="admin_panel.php?approve_pay=<?php echo $row['id']; ?>" 
                                   onclick="return confirm('Confirm receipt of ₹<?php echo $row['amount']; ?>?')" 
                                   class="admin-action-btn btn-positive">
                                   Approve ✓
                                </a>

                                <a href="admin_panel.php?reject_pay=<?php echo $row['id']; ?>" 
                                   onclick="return confirm('Are you sure you want to REJECT this payment? No credits will be given.')" 
                                   class="admin-action-btn btn-negative">
                                   Reject ✕
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="empty-state">No new payment requests.</div>
    <?php endif; ?>


    <?php if ($banned_landlords && $banned_landlords->num_rows > 0): ?>
        <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            ⛔ Restricted Landlords (3+ Strikes)
        </h2>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="th-danger" style="padding: 12px; width: 25%;">Landlord Name</th>
                        <th class="th-danger" style="padding: 12px; width: 35%;">Contact Info</th>
                        <th class="th-danger" style="padding: 12px; width: 20%;">Strikes</th>
                        <th class="th-danger" style="padding: 12px; width: 20%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $banned_landlords->fetch_assoc()): ?>
                        <tr class="dispute-row">
                            <td style="padding: 12px; word-wrap: break-word;">
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            </td>
                            <td style="padding: 12px; word-wrap: break-word;">
                                <div style="font-size: 0.9rem;">
                                    📧 <?php echo htmlspecialchars($row['email']); ?><br>
                                    📞 <?php echo htmlspecialchars($row['phone']); ?>
                                </div>
                            </td>
                            <td style="padding: 12px;">
                                <span style="color: #ffffff; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 1rem; display: inline-block;">
                                    <?php echo $row['strikes']; ?>
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <a href="admin_panel.php?unban_user=<?php echo $row['user_id']; ?>" 
                                   onclick="return confirm('Are you sure you want to unban this landlord? This resets their strikes to 0 and reactivates all their properties.')" 
                                   class="admin-action-btn btn-positive">
                                   🔄 Unban & Reset
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>


    <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
         Dispute & Resolution Center
    </h2>
    
    <?php if ($pending_reports && $pending_reports->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th class="th-danger" style="padding: 12px; width: 25%;">Student</th>
                        <th class="th-danger" style="padding: 12px; width: 35%;">Reported Property</th>
                        <th class="th-danger" style="padding: 12px; width: 20%;">Issue Details</th>
                        <th class="th-danger" style="padding: 12px; width: 20%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $pending_reports->fetch_assoc()): ?>
                        <?php $pk = isset($row['report_id']) ? $row['report_id'] : $row['id']; ?>
                        <tr class="dispute-row">
                            <td style="padding: 12px; word-wrap: break-word;">
                                <strong><?php echo htmlspecialchars($row['student_name']); ?></strong>
                            </td>
                            <td style="padding: 12px; word-wrap: break-word;">
                                <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" target="_blank" class="prop-link" style="font-weight: bold; color: var(--primary);">
                                    <?php echo htmlspecialchars($row['prop_title']); ?>
                                </a><br>
                                <small style="color: var(--text-light);">Owner: <?php echo htmlspecialchars($row['landlord_name']); ?></small>
                            </td>
                            <td style="padding: 12px; word-wrap: break-word;">
                                <span style="background-color: #dc3545; color: #ffffff; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: bold; display: inline-block; margin-bottom: 5px;">
                                    <?php echo htmlspecialchars($row['issue_type']); ?>
                                </span><br>
                                <span style="font-size: 0.9rem; margin-top: 5px; display: inline-block; color: var(--text-main);"><?php echo htmlspecialchars($row['description']); ?></span>
                            </td>
                            <td style="padding: 12px;">
                                <div class="action-stack">
                                    <a href="admin_panel.php?resolve_report=<?php echo $pk; ?>&action=refund_strike" 
                                       onclick="return confirm('Refund 1 Credit to student AND issue a Strike to Landlord?')" 
                                       class="admin-action-btn btn-positive">
                                       Refund & Strike 🔨
                                    </a>

                                    <a href="admin_panel.php?resolve_report=<?php echo $pk; ?>&action=dismiss" 
                                       onclick="return confirm('Dismiss this report?')" 
                                       class="admin-action-btn btn-negative">
                                       Dismiss Report
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="empty-state">No pending disputes or reports.</div>
    <?php endif; ?>


    <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">Pending Verifications</h2>
    
    <?php if ($pending_verifications->num_rows > 0): ?>
        <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="padding: 12px; width: 25%; background-color: var(--primary); color: white;">User Name</th>
                    <th style="padding: 12px; width: 35%; background-color: var(--primary); color: white;">Email</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Document</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $pending_verifications->fetch_assoc()): ?>
                    <tr class="admin-table-row">
                        <td style="padding: 12px; word-wrap: break-word;"><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td style="padding: 12px; word-wrap: break-word;"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td style="padding: 12px;">
                            <a href="uploads/<?php echo htmlspecialchars($user['verification_doc']); ?>" target="_blank" style="color: var(--primary); text-decoration: underline;">
                                View Document 📄
                            </a>
                        </td>
                        <td style="padding: 12px;">
                            <div class="action-stack">
                                <a href="admin_panel.php?approve_user=<?php echo $user['user_id']; ?>" 
                                   class="admin-action-btn btn-positive">
                                   Approve ✓
                                </a>
                                <a href="admin_panel.php?reject_user=<?php echo $user['user_id']; ?>" 
                                   onclick="return confirm('Are you sure you want to REJECT this document? The user will be asked to re-upload.')" 
                                   class="admin-action-btn btn-negative">
                                   Reject ✕
                                </a>
                                </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    <?php else: ?>
        <div class="empty-state">
            All pending documents have been verified!
        </div>
    <?php endif; ?>


    <h2 style="color: var(--primary); margin-bottom: 20px; margin-top: 40px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">All Properties</h2>
    
    <div style="overflow-x: auto;">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="padding: 12px; width: 5%; background-color: var(--primary); color: white;">#</th> 
                    <th style="padding: 12px; width: 25%; background-color: var(--primary); color: white;">Property Title</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Landlord</th>
                    <th style="padding: 12px; width: 15%; background-color: var(--primary); color: white;">City</th>
                    <th style="padding: 12px; width: 15%; background-color: var(--primary); color: white;">Status</th>
                    <th style="padding: 12px; width: 20%; background-color: var(--primary); color: white;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                    $counter = 1;
                    while($row = $all_props->fetch_assoc()): 
                ?>
                    <tr class="admin-table-row">
                        <td style="padding: 12px;"><?php echo $counter++; ?></td> 
                        <td style="padding: 12px; word-wrap: break-word;">
                            <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" target="_blank" style="font-weight: bold; color: var(--primary);">
                                <?php echo htmlspecialchars($row['title']); ?>
                            </a>
                        </td>
                        <td style="padding: 12px; word-wrap: break-word;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td style="padding: 12px; word-wrap: break-word;"><?php echo htmlspecialchars($row['city']); ?></td>
                        <td style="padding: 12px;">
                            <?php echo ($row['availability_status'] == 1) ? '<span style="color:var(--success); font-weight:bold;">Active</span>' : '<span style="color:var(--danger); font-weight:bold;">Hidden</span>'; ?>
                        </td>
                        <td style="padding: 12px;">
                            <a href="admin_panel.php?delete_prop=<?php echo $row['prop_id']; ?>" 
                               onclick="return confirm('WARNING: Are you sure you want to permanently delete this property?');" 
                               class="admin-action-btn btn-negative" style="background-color: transparent; border: 1px solid #dc3545; color: #dc3545 !important;">
                               Delete 🗑️
                            </a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

</div>

<?php include 'footer.php'; ?>