<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$success = "";
$error = "";

// --- NEW: CHECK CURRENT STATUS ---
$status_q = $conn->query("SELECT is_doc_verified, verification_doc FROM users WHERE user_id = $user_id");
$status_data = $status_q->fetch_assoc();
$is_doc_verified = isset($status_data['is_doc_verified']) ? $status_data['is_doc_verified'] : 0;
$has_doc = !empty($status_data['verification_doc']);
// ---------------------------------

// 2. HANDLE FILE UPLOAD
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["doc"]) && !$is_doc_verified && !$has_doc) {
    
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    // Generate unique name to prevent overwriting
    $file_extension = pathinfo($_FILES["doc"]["name"], PATHINFO_EXTENSION);
    $file_name = "verify_" . $user_id . "_" . time() . "." . $file_extension;
    $target_file = $target_dir . $file_name;

    // Check if it's an image
    $check = getimagesize($_FILES["doc"]["tmp_name"]);
    if($check !== false) {
        if (move_uploaded_file($_FILES["doc"]["tmp_name"], $target_file)) {
            
            // Update Database
            $sql = "UPDATE users SET verification_doc = '$file_name' WHERE user_id = $user_id";
            if ($conn->query($sql)) {
                $success = "Document uploaded successfully! Admin will verify it shortly.";
                $has_doc = true; // Update local variable so form hides
                
                // --- NOTIFY ADMIN ---
                if (function_exists('send_notification')) {
                    $admin_q = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
                    if ($admin_q && $admin_q->num_rows > 0) {
                        $admin_id = $admin_q->fetch_assoc()['user_id'];
                        
                        $u_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = $user_id");
                        $u_name = ($u_name_q && $u_name_q->num_rows > 0) ? $u_name_q->fetch_assoc()['full_name'] : "A user";
                        
                        $admin_msg = "🛡️ Verification Request: $u_name uploaded a document.";
                        send_notification($conn, $admin_id, $admin_msg, "info", "admin_panel.php");
                    }
                }
                // -------------------------

            } else {
                $error = "Database Error: " . $conn->error;
            }
        } else {
            $error = "Failed to upload file. Please try again.";
        }
    } else {
        $error = "File is not an image.";
    }
}

include 'header.php';
?>

<div class="container" style="padding: 60px 20px;">
    
    <div class="form-box" style="max-width: 600px; text-align: center;">
        
        <div style="font-size: 3rem; margin-bottom: 10px;">🛡️</div>
        <h2 style="color: var(--primary); margin-bottom: 10px;">Get Verified</h2>
        <p style="color: var(--text-light); margin-bottom: 30px;">
            Verified landlords get <strong>3x more calls</strong> and a trusted <span style="color: var(--success); font-weight: bold;">Blue Tick ✅</span> on their profile.
        </p>

        <?php if($success): ?>
            <div style="background: var(--bg-success-soft); color: var(--text-success-soft); padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid var(--success);">
                <?php echo $success; ?>
            </div>
            <a href="dashboard.php" class="btn btn-outline">Back to Dashboard</a>
        
        <?php elseif ($is_doc_verified == 1): ?>
             <div style="background: var(--bg-success-soft); color: var(--text-success-soft); padding: 20px; border-radius: 8px; border: 1px solid var(--success);">
                <h3 style="margin: 0 0 10px 0;">🎉 You are a Verified Owner!</h3>
                <p style="margin: 0;">Your identity has been verified by the Admin. The Verified Badge is now active on all your properties.</p>
            </div>
            <a href="dashboard.php" class="btn btn-primary" style="margin-top: 20px;">Go to Dashboard</a>

        <?php elseif ($has_doc): ?>
            <div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; border: 1px solid #ffeeba;">
                <h3 style="margin: 0 0 10px 0;">⏳ Verification Pending</h3>
                <p style="margin: 0;">You have already uploaded your document. Our admin team is reviewing it. Please check back later.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline" style="margin-top: 20px;">Back to Dashboard</a>

        <?php else: ?>

            <?php if($error): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 20px;">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" style="text-align: left;">
                
                <div class="form-group">
                    <label>Upload Document (Electricity Bill / Aadhaar)</label>
                    <div style="border: 2px dashed var(--border-color); padding: 30px; text-align: center; border-radius: 8px; background: var(--bg-input);">
                        <input type="file" name="doc" required accept="image/*" style="width: 100%; color: var(--text-main);">
                        <p style="font-size: 0.8rem; color: var(--text-light); margin-top: 10px;">Supports JPG, PNG (Max 2MB)</p>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; margin-top: 15px;">Submit for Verification</button>
            </form>
            
        <?php endif; ?>

    </div>
</div>

<?php include 'footer.php'; ?>