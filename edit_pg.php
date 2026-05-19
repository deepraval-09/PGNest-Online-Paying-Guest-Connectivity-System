<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'landlord') {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit;
}

$prop_id = intval($_GET['id']);
$landlord_id = $_SESSION['user_id'];

// 2. FETCH EXISTING DETAILS
$sql = "SELECT * FROM properties WHERE prop_id = $prop_id AND landlord_id = $landlord_id";
$result = $conn->query($sql);

if ($result->num_rows == 0) {
    die("Property not found or access denied.");
}

$row = $result->fetch_assoc();
$error = "";

// 3. HANDLE UPDATE FORM
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $conn->real_escape_string($_POST['title']);
    $city = $conn->real_escape_string($_POST['city']);
    $address = $conn->real_escape_string($_POST['address']);
    $rent = $_POST['rent'];
    $gender = $_POST['gender_type'];
    $food = $_POST['food_type'];
    $occupancy = $_POST['occupancy'];
    $amenities = $conn->real_escape_string($_POST['amenities']);
    
    // Default to keeping old image
    $file_name = $row['image_main']; 

    // Check if NEW image is uploaded
    if (!empty($_FILES["image"]["name"])) {
        $target_dir = "uploads/";
        $new_file_name = "img_" . uniqid() . "." . pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . $new_file_name;
        
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            $file_name = $new_file_name; 
            if (file_exists("uploads/" . $row['image_main'])) {
                unlink("uploads/" . $row['image_main']);
            }
        } else {
            $error = "Failed to upload new image.";
        }
    }

    if (empty($error)) {
        $update_sql = "UPDATE properties SET 
                       title='$title', city='$city', address='$address', rent='$rent', 
                       gender_type='$gender', food_type='$food', occupancy='$occupancy', 
                       amenities='$amenities', image_main='$file_name' 
                       WHERE prop_id=$prop_id AND landlord_id=$landlord_id";
        
        if ($conn->query($update_sql)) {
            echo "<script>alert('Property Updated Successfully!'); window.location.href='dashboard.php';</script>";
        } else {
            $error = "Database Error: " . $conn->error;
        }
    }
}

include 'header.php';
?>

<div class="container" style="padding: 40px 20px;">
    
    <div class="form-box" style="max-width: 800px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="color: var(--primary); margin: 0;">Edit Property</h2>
            <a href="dashboard.php" class="btn btn-outline" style="padding: 5px 15px; font-size: 0.9rem;">Cancel</a>
        </div>

        <?php if($error): ?>
            <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Property Title</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($row['city']); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>Full Address</label>
                <textarea name="address" rows="2" required><?php echo htmlspecialchars($row['address']); ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Monthly Rent (₹)</label>
                    <input type="number" name="rent" value="<?php echo $row['rent']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Occupancy Type</label>
                    <select name="occupancy">
                        <option value="Single" <?php if($row['occupancy']=='Single') echo 'selected'; ?>>Single Room</option>
                        <option value="Double" <?php if($row['occupancy']=='Double') echo 'selected'; ?>>Double Sharing</option>
                        <option value="Triple" <?php if($row['occupancy']=='Triple') echo 'selected'; ?>>Triple Sharing</option>
                        <option value="Dorm" <?php if($row['occupancy']=='Dorm') echo 'selected'; ?>>Dormitory</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label>Tenant Preference</label>
                    <select name="gender_type">
                        <option value="Boys" <?php if($row['gender_type']=='Boys') echo 'selected'; ?>>Boys Only</option>
                        <option value="Girls" <?php if($row['gender_type']=='Girls') echo 'selected'; ?>>Girls Only</option>
                        <option value="Family" <?php if($row['gender_type']=='Family') echo 'selected'; ?>>Family / Couples</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Food Availability</label>
                    <select name="food_type">
                        <option value="Veg" <?php if($row['food_type']=='Veg') echo 'selected'; ?>>Veg Only</option>
                        <option value="Non-Veg" <?php if($row['food_type']=='Non-Veg') echo 'selected'; ?>>Non-Veg Allowed</option>
                        <option value="Both" <?php if($row['food_type']=='Both') echo 'selected'; ?>>Both Available</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Amenities</label>
                <input type="text" name="amenities" value="<?php echo htmlspecialchars($row['amenities']); ?>">
            </div>

            <div class="form-group">
                <label>Update Image (Optional)</label>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <img src="uploads/<?php echo $row['image_main']; ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd;">
                    
                    <input type="file" name="image" accept="image/*" 
                           style="padding: 10px; background: var(--bg-input); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 4px; flex: 1;">
                </div>
                <p style="font-size: 0.8rem; opacity: 0.7; margin-top: 5px;">Leave empty to keep current image.</p>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1.1rem; margin-top: 20px;">Save Changes</button>

        </form>
    </div>
</div>

<?php include 'footer.php'; ?>