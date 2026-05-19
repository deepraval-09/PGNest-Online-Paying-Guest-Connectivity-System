<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

// 2. HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = $conn->real_escape_string($_POST['full_name']);
    $new_phone = $conn->real_escape_string($_POST['phone']);
    $new_pass = $_POST['new_password'];

    // Update Name & Phone
    $update_sql = "UPDATE users SET full_name = '$new_name', phone = '$new_phone' WHERE user_id = $user_id";
    
    if ($conn->query($update_sql)) {
        $message = "Profile details updated successfully!";
        
        // Update Password Logic
        if (!empty($new_pass)) {
            if (strlen($new_pass) >= 6) {
                $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET password = '$hashed' WHERE user_id = $user_id");
                $message .= " Password was also changed.";
            } else {
                $error = "Name updated, but password failed: Must be 6+ chars.";
            }
        }
        
        // Update Session Name immediately so header updates
        $_SESSION['user_name'] = $new_name;

        // Redirect back to account after 1 second
        if (empty($error)) {
            echo "<script>alert('$message'); window.location.href='my_account.php';</script>";
            exit;
        }

    } else {
        $error = "Database Error: " . $conn->error;
    }
}

// 3. FETCH CURRENT DATA
$sql = "SELECT * FROM users WHERE user_id = $user_id";
$result = $conn->query($sql);
$user = $result->fetch_assoc();

include 'header.php';
?>

<style>
    /* Custom Cancel Button Style (Same as Logout) */
    .btn-cancel {
        border: 2px solid #dc3545;
        color: #dc3545;
        background: transparent;
        padding: 6px 15px;
        font-size: 0.9rem;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .btn-cancel:hover {
        background-color: #dc3545;
        color: #ffffff !important; /* Forces text to stay white */
        border-color: #dc3545;
    }
</style>

<div class="container" style="padding-top: 60px; padding-bottom: 60px;">
    
    <div class="form-box" style="max-width: 600px; margin: 0 auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="color: var(--primary); margin: 0;">Edit Profile</h2>
            <a href="my_account.php" class="btn-cancel">Cancel</a>
        </div>

        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            
            <div class="form-group">
                <label style="font-weight: bold; margin-bottom: 8px; display: block;">Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); border-radius: 6px; font-size: 1rem;">
            </div>

            <div class="form-group" style="margin-top: 20px;">
                <label style="font-weight: bold; margin-bottom: 8px; display: block;">Phone Number</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); border-radius: 6px; font-size: 1rem;">
                
                <?php if($user['phone'] == '0000000000'): ?>
                    <div style="color: #dc3545; font-size: 0.85rem; margin-top: 5px;">
                        ⚠️ Please update this dummy number to a real one.
                    </div>
                <?php endif; ?>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 30px 0;">

            <h4 style="color: var(--primary); margin-bottom: 15px;">Security Settings</h4>
            
            <div class="form-group">
                <label style="font-weight: bold; margin-bottom: 8px; display: block;">New Password</label>
                <input type="password" name="new_password" placeholder="Leave blank to keep current password"
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main); border-radius: 6px; font-size: 1rem;">
                <p style="font-size: 0.85rem; color: var(--text-light); margin-top: 5px;">
                    Only enter a value here if you want to change your password.
                </p>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; margin-top: 30px; padding: 12px;">
                Save Changes
            </button>

        </form>
    </div>

</div>

<?php include 'footer.php'; ?>