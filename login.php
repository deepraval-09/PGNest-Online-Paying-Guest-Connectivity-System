<?php
session_start();
include 'db.php';
require_once 'google_config.php'; // Load Google Config

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // --- SECURITY PATCH: PREPARED STATEMENT FOR SQL INJECTION PREVENTION ---
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    // ---------------------------------------------------------------------

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            if ($row['is_verified'] == 1) {
                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['user_name'] = $row['full_name'];
                $_SESSION['user_role'] = $row['role'];

                if ($row['role'] == 'landlord') {
                    header("Location: dashboard.php");
                } elseif ($row['role'] == 'admin') {
                    header("Location: admin_panel.php");
                } else {
                    header("Location: index.php");
                }
                exit; // Good practice to exit after header redirect
            } else {
                // --- PHASE 2 FIX: LOST USER RESCUE ---
                // Instead of a dead end, set the session and route them to the OTP page
                $_SESSION['verify_email'] = $email;
                echo "<script>alert('Please complete your email verification to continue.'); window.location.href='verify_otp.php';</script>";
                exit;
                // -------------------------------------
            }
        } else {
            $error = "Invalid Password.";
        }
    } else {
        $error = "No account found with this email.";
    }
}
include 'header.php';
?>

<div class="container" style="padding: 60px 20px;">
    <div class="form-box">
        
        <div style="text-align: center; margin-bottom: 20px;">
            <img src="l2.png" alt="Logo" style="width: 150px; display: block; margin: 0 auto;" onerror="this.onerror=null; this.src='l2.jpg';">
            <h2 style="color: var(--primary); margin-top: 10px;">Welcome Back</h2>
        </div>

        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem;">Login</button>
        </form>

        <div style="display: flex; align-items: center; margin: 20px 0;">
            <div style="flex: 1; height: 1px; background: #ddd;"></div>
            <span style="padding: 0 10px; color: #888; font-size: 0.9rem;">OR</span>
            <div style="flex: 1; height: 1px; background: #ddd;"></div>
        </div>

        <a href="<?php echo $google_login_url; ?>" style="
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 10px; 
            background: #fff; 
            border: 1px solid #ccc; 
            color: #555; 
            padding: 12px; 
            border-radius: 6px; 
            text-decoration: none; 
            font-weight: 600; 
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
            
            <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" style="width: 20px; height: 20px;">
            Sign in with Google
        </a>

        <div style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
            <p>Don't have an account? <a href="register.php" style="color: var(--primary); font-weight: bold;">Create Account</a></p>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>