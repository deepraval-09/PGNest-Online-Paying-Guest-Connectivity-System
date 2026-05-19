<?php
session_start();
include 'db.php';

// 1. SECURITY: FORCE LOGIN
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=Please login to pay");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'student'; 
$plan = isset($_GET['plan']) ? $_GET['plan'] : 'nest_pass';
$amount = isset($_GET['amount']) ? $_GET['amount'] : 99;

// --- CONFIGURATION: YOUR UPI DETAILS ---
$my_upi_id = ""; 
$my_name = "PGNest Admin";

// Generate UPI Link (Standard Format)
$upi_link = "upi://pay?pa=$my_upi_id&pn=$my_name&am=$amount&cu=INR";

// Generate QR Code Image URL (using free API)
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($upi_link);

// 2. HANDLE FORM SUBMISSION
if (isset($_POST['submit_payment'])) {
    $utr = $conn->real_escape_string($_POST['utr_number']);
    
    if ($plan == 'premium') {
        $desc = "Premium Plan Upgrade";
    } else {
        $desc = "Nest Pass (10 Credits)";
    }

    $sql = "INSERT INTO transactions (user_id, amount, type, description, utr_number, status) 
            VALUES ($user_id, '$amount', 'debit', '$desc', '$utr', 'pending')";
    
    if ($conn->query($sql)) {
        $redirect_url = ($user_role == 'landlord') ? 'dashboard.php' : 'my_account.php';

        // --- NEW: NOTIFICATION LOGIC ---
        if (function_exists('send_notification')) {
            // NOTIFY THE ADMIN ONLY
            $admin_q = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
            if ($admin_q && $admin_q->num_rows > 0) {
                $admin_id = $admin_q->fetch_assoc()['user_id'];
                
                // Fetch user name for better admin alert
                $u_name_q = $conn->query("SELECT full_name FROM users WHERE user_id = $user_id");
                $u_name = ($u_name_q && $u_name_q->num_rows > 0) ? $u_name_q->fetch_assoc()['full_name'] : "A user";
                
                $admin_msg = "💰 Payment Request: $u_name submitted ₹$amount for $desc.";
                send_notification($conn, $admin_id, $admin_msg, "warning", "admin_panel.php");
            }
        }
        // --------------------------------
        
        echo "<script>
                alert('Payment Request Submitted! Admin will verify shortly.');
                window.location.href = '$redirect_url';
              </script>";
    } else {
        $error = "Error: " . $conn->error;
    }
}

include 'header.php';
?>

<div class="container" style="padding: 60px 20px;">
    <div class="form-box" style="max-width: 500px; text-align: center;">
        
        <h2 style="color: var(--primary);">Pay via UPI</h2>
        <p style="color: var(--text-main); margin-bottom: 20px;">
            Plan: <strong><?php echo ($plan == 'premium') ? 'Premium Host' : 'Nest Pass'; ?></strong><br>
            Amount: <strong style="font-size: 1.5rem; color: var(--primary);">₹<?php echo $amount; ?></strong>
        </p>

        <div style="margin: 0 auto 20px; border: 2px dashed #ddd; padding: 20px; display: inline-block; background: #ffffff; border-radius: 10px;">
            
            <img src="<?php echo $qr_url; ?>" alt="Scan to Pay" 
                 style="width: 100%; max-width: 250px; height: auto; display: block;">
            
            <p style="font-size: 0.9rem; margin-top: 15px; color: #333; font-weight: bold;">
                Scan with GPay / Paytm / PhonePe
            </p>
        </div>

        <form method="POST" style="text-align: left;">
            <div class="form-group">
                <label>Step 2: Enter Transaction ID / UTR</label>
                <input type="text" name="utr_number" placeholder="e.g. 302834920192" required 
                       style="font-size: 1.1rem; letter-spacing: 1px; font-family: monospace;">
                <small style="color: var(--text-light);">You must enter the UTR number from your payment app so we can verify it.</small>
            </div>

            <button type="submit" name="submit_payment" class="btn btn-primary" style="width: 100%; margin-top: 10px;">
                Submit for Verification
            </button>
        </form>

    </div>
</div>

<?php include 'footer.php'; ?>