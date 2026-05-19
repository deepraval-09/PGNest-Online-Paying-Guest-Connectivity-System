<?php
session_start();
include 'db.php';

// Load PHPMailer Library for Resend feature
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// If no email in session, redirect to register
if (!isset($_SESSION['verify_email'])) {
    header("Location: register.php");
    exit;
}

$email = $_SESSION['verify_email'];
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- NEW: RESEND OTP LOGIC ---
    if (isset($_POST['resend_otp'])) {
        $new_otp = sprintf("%06d", mt_rand(1, 999999));
        
        $update = $conn->prepare("UPDATE users SET otp_code = ? WHERE email = ?");
        $update->bind_param("ss", $new_otp, $email);
        
        if ($update->execute()) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                
                
                $mail->Username   = ''; 
                $mail->Password   = ''; 
                
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom($mail->Username, 'PGNest');
                $mail->addAddress($email);

                $mail->isHTML(true);
                $mail->Subject = 'Your New PGNest Verification Code';
                $mail->Body    = "Your new verification code is: <b>$new_otp</b>";

                $mail->send();
                $success = "A new 6-digit code has been sent to your email!";
            } catch (Exception $e) {
                $error = "Failed to send new OTP. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "Failed to generate new OTP.";
        }
    } 
    // --- EXISTING: VERIFY OTP LOGIC ---
    elseif (isset($_POST['verify_otp'])) {
        $user_otp = $_POST['otp'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND otp_code = ?");
        $stmt->bind_param("ss", $email, $user_otp);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            $update = $conn->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expiry = NULL WHERE email = ?");
            $update->bind_param("s", $email);
            
            if ($update->execute()) {
                unset($_SESSION['verify_email']);
                $_SESSION['message'] = "Email Verified Successfully! You can now Login.";
                header("Location: login.php");
                exit;
            } else {
                $error = "Database update failed.";
            }
        } else {
            $error = "Invalid OTP. Please try again.";
        }
    }
}

include 'header.php';
?>

<div class="container" style="padding: 60px 20px;">
    <div class="form-box" style="text-align: center;">
        
        <h2 style="color: var(--primary); margin-bottom: 20px;">Verify Your Email</h2>
        <p style="margin-bottom: 20px;">We sent a 6-digit code to <strong><?php echo htmlspecialchars($email); ?></strong></p>

        <?php if(!empty($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #f5c6cb;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 6px; margin-bottom: 15px; border: 1px solid #c3e6cb;">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="text" name="otp" placeholder="Enter 6-Digit OTP" required 
                       style="text-align: center; font-size: 1.5rem; letter-spacing: 5px; width: 100%; max-width: 200px; margin: 0 auto; display: block;" maxlength="6">
            </div>
            
            <button type="submit" name="verify_otp" class="btn btn-primary" style="width: 100%; margin-top: 10px;">Verify & Login</button>
        </form>
        
        <form method="POST" style="margin-top: 15px;">
            <button type="submit" name="resend_otp" style="background: none; border: none; color: var(--primary); font-weight: bold; cursor: pointer; text-decoration: underline; font-size: 0.95rem;">
                Didn't receive the code? Resend OTP
            </button>
        </form>
        
        <div style="margin-top: 25px;">
            <a href="register.php" style="color: #666; font-size: 0.9rem;">Wrong Email? Register Again</a>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>