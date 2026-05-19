<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

// 2. HANDLE FORM SUBMISSION
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $landlord_id = $_SESSION['user_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $city = $conn->real_escape_string($_POST['city']);
    $address = $conn->real_escape_string($_POST['address']);
    $rent = $_POST['rent'];
    $gender = $_POST['gender_type'];
    $food = $_POST['food_type'];
    $occupancy = $_POST['occupancy'];
    $live_in = isset($_POST['live_in_landlord']) ? 1 : 0;
    $amenities = $conn->real_escape_string($_POST['amenities']);

    $target_dir = "uploads/";
    if (!file_exists($target_dir)) { mkdir($target_dir, 0777, true); }
    
    // --- SECURITY PATCH: STRICT FILE UPLOAD VALIDATION ---
    $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "webp");
    
    // Validate extension AND verify the file contains actual image data
    if (!in_array($file_extension, $allowed_extensions) || getimagesize($_FILES["image"]["tmp_name"]) === false) {
        $error = "⛔ Security Alert: Invalid file type. Only real JPG, PNG, and WEBP images are allowed.";
    } else {
        $file_name = "img_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $file_name;

        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $sql = "INSERT INTO properties (landlord_id, title, city, address, rent, gender_type, food_type, occupancy, live_in_landlord, amenities, image_main, availability_status) 
                    VALUES ('$landlord_id', '$title', '$city', '$address', '$rent', '$gender', '$food', '$occupancy', '$live_in', '$amenities', '$file_name', 1)";
            
            if ($conn->query($sql)) {
                
                // --- NEW: NOTIFY ADMIN ---
                if (function_exists('send_notification')) {
                    $admin_q = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
                    if ($admin_q && $admin_q->num_rows > 0) {
                        $admin_id = $admin_q->fetch_assoc()['user_id'];
                        $admin_msg = "🏠 New Listing: A property named '$title' was just added in $city.";
                        send_notification($conn, $admin_id, $admin_msg, "success", "admin_panel.php");
                    }
                }
                // -------------------------

                echo "<script>alert('Property Listed Successfully!'); window.location.href='dashboard.php';</script>";
            } else {
                $error = "Database Error: " . $conn->error;
            }
        } else {
            $error = "Failed to upload image.";
        }
    }
}

include 'header.php';
?>

<style>
    .file-input-styled {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--border-color);
        border-radius: 6px;
        background: #f9f9f9; 
        color: #333;
        transition: all 0.3s ease;
    }
    .safety-box {
        background: #e6fffa; 
        padding: 15px; 
        border-radius: 8px; 
        border: 1px solid #b2f5ea;
        margin-top: 10px;
        transition: all 0.3s ease;
    }
    .safety-text {
        font-size: 0.85rem; 
        color: #006666; 
        margin-top: 5px; 
        margin-left: 30px;
    }

    body.dark-mode .file-input-styled {
        background: #2d2d2d; 
        color: #fff; 
        border-color: #444;
    }
    body.dark-mode .safety-box {
        background: rgba(0, 128, 128, 0.15); 
        border-color: #004d4d;
        color: #e0e0e0;
    }
    body.dark-mode .safety-text {
        color: #80cbc4; 
    }
</style>

<div class="container" style="padding: 40px 20px;">
    
    <div class="form-box" style="max-width: 800px;">
        <h2 style="color: var(--primary); margin-bottom: 30px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">List New Property</h2>

        <?php if(isset($error)): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Property Title (e.g. Sharma Boys PG)</label>
                    <input type="text" name="title" required>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" placeholder="e.g. Pune" required>
                </div>
            </div>

            <div class="form-group">
                <label>Full Address</label>
                <textarea name="address" rows="2" required></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Monthly Rent (₹)</label>
                    <input type="number" name="rent" required>
                </div>
                <div class="form-group">
                    <label>Occupancy Type</label>
                    <select name="occupancy">
                        <option value="Single">Single Room</option>
                        <option value="Double" selected>Double Sharing</option>
                        <option value="Triple">Triple Sharing</option>
                        <option value="Dorm">Dormitory</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Tenant Preference</label>
                    <select name="gender_type">
                        <option value="Boys">Boys Only</option>
                        <option value="Girls">Girls Only</option>
                        <option value="Family">Family / Couples</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Food Availability</label>
                    <select name="food_type">
                        <option value="Veg">Veg Only</option>
                        <option value="Non-Veg">Non-Veg Allowed</option>
                        <option value="Both">Both Available</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Amenities (Comma separated)</label>
                <input type="text" name="amenities" placeholder="e.g. WiFi, AC, Geyser, RO Water">
            </div>

            <div class="form-group">
                <label>Property Image</label>
                <input type="file" name="image" accept="image/*" required class="file-input-styled">
            </div>

            <div class="form-group safety-box">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="live_in_landlord" value="1" style="width: 20px; height: 20px; accent-color: var(--primary);">
                    <span><strong>Safety Feature:</strong> I live in this property (Live-in Landlord)</span>
                </label>
                <p class="safety-text">
                    This adds a "Safe" tag for students.
                </p>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; margin-top: 20px;">Publish Listing</button>

        </form>
    </div>
</div>

<?php include 'footer.php'; ?>