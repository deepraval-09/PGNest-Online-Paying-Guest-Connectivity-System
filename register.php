<?php
session_start();
include 'db.php';

// Load PHPMailer Library
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$error = "";
$success = "";

// 1. HANDLE REGISTRATION LOGIC
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = htmlspecialchars($_POST['full_name']);
    $email = htmlspecialchars($_POST['email']);
    $phone = htmlspecialchars($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password']; // New Field
    $role = $_POST['role']; 

    // --- VALIDATION CHECKS ---
    
    // Check 1: Password Length
    if (strlen($password) < 6) {
        $error = "Password is too short. Please use at least 6 characters.";
    } 
    // Check 2: Passwords Match (New)
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match!";
    } 
    else {
        // Check if Email already exists
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $error = "This email is already registered. Please login.";
        } else {
            // Generate OTP
            $otp = rand(100000, 999999);
            $otp_expiry = date("Y-m-d H:i:s", strtotime("+10 minutes")); // Expires in 10 mins

            // Secure Password Hashing
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert New User
            $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role, is_verified, otp_code, otp_expiry) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
            $stmt->bind_param("sssssss", $full_name, $email, $phone, $hashed, $role, $otp, $otp_expiry);
            
            if ($stmt->execute()) {
                
                // --- SEND OTP EMAIL ---
                $mail = new PHPMailer(true);

                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    
                    // ============================================================
                    // YOUR UPDATED GMAIL CREDENTIALS
                    // ============================================================
                    $mail->Username   = ''; 
                    $mail->Password   = ''; 
                    // ============================================================

                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;

                    // --- FIX: BYPASS LOCALHOST SSL VERIFICATION ---
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );
                    // ----------------------------------------------

                    // Recipients
                    $mail->setFrom('pgnest.official@gmail.com', 'PGNest Verification');
                    $mail->addAddress($email, $full_name);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Verify Your PGNest Account';
                    $mail->Body    = "
                        <div style='font-family: Arial, sans-serif; color: #333;'>
                            <h2 style='color: #0f4c81;'>Welcome to PGNest!</h2>
                            <p>Hi $full_name,</p>
                            <p>Thank you for registering. Please use the One-Time Password (OTP) below to verify your email address:</p>
                            <div style='background: #f4f4f4; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;'>
                                <span style='font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #0f4c81;'>$otp</span>
                            </div>
                            <p>This code is valid for 10 minutes.</p>
                        </div>";

                    $mail->send();
                    
                    // Redirect to OTP Verification Page
                    $_SESSION['verify_email'] = $email; 
                    header("Location: verify_otp.php");
                    exit;

                } catch (Exception $e) {
                    // Delete the user if email completely fails so they aren't stuck in limbo
                    $conn->query("DELETE FROM users WHERE email = '$email'");
                    $error = "Account created, but email failed to send. Error: {$mail->ErrorInfo}";
                }

            } else {
                $error = "System Error: " . $conn->error;
            }
        }
    }
}

include 'header.php';
?>

<div class="container" style="padding: 60px 20px;">
    
    <div class="form-box">
        
        <img src="l2.png" alt="PGNest" class="auth-logo" onerror="this.onerror=null; this.src='l2.jpg';">
        
        <h2 style="text-align: center; color: var(--primary); margin-bottom: 20px;">Create Account</h2>
        
        <?php if(!empty($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="regForm">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="full_name" placeholder="Enter your name" required value="<?php echo isset($_POST['full_name']) ? $_POST['full_name'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="Enter your email" required value="<?php echo isset($_POST['email']) ? $_POST['email'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" placeholder="Enter your phone number" required value="<?php echo isset($_POST['phone']) ? $_POST['phone'] : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" id="password" placeholder="Create a password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Re-enter password" required>
            </div>

            <div style="margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" id="showPassToggle" style="width: auto; margin: 0;">
                <label for="showPassToggle" style="margin: 0; font-size: 0.9rem; cursor: pointer; user-select: none;">Show Password</label>
            </div>
            
            <div class="form-group">
                <label>I am a...</label>
                <select name="role">
                    <option value="student">Student (Looking for PG)</option>
                    <option value="landlord">Landlord (Listing Property)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem;">Sign Up & Verify</button>
        </form>
        
        <div style="text-align: center; margin-top: 20px; font-size: 0.9rem;">
            <p>Already have an account? <a href="login.php" style="color: var(--primary); font-weight: bold;">Login here</a></p>
        </div>

    </div>
</div>

<script>
// 1. Show Password Toggle Logic
document.getElementById('showPassToggle').addEventListener('change', function() {
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('confirm_password');
    
    if (this.checked) {
        passwordField.type = "text";
        confirmField.type = "text";
    } else {
        passwordField.type = "password";
        confirmField.type = "password";
    }
});

// 2. Client-side Validation (Length + Match Check)
document.getElementById('regForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    // Check Length
    if (password.length < 6) {
        e.preventDefault();
        alert("Password is too short! Please use at least 6 characters.");
        return;
    }

    // Check Match
    if (password !== confirm) {
        e.preventDefault();
        alert("Passwords do not match! Please re-enter.");
    }
});
</script>

<?php include 'footer.php'; ?>