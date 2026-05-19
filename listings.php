<?php
include 'header.php';
include 'db.php';

// 1. INITIALIZE FILTERS
$city = isset($_GET['city']) ? $conn->real_escape_string($_GET['city']) : '';
$gender = isset($_GET['gender']) ? $conn->real_escape_string($_GET['gender']) : '';
$food = isset($_GET['food']) ? $conn->real_escape_string($_GET['food']) : '';
$budget = isset($_GET['budget']) ? $conn->real_escape_string($_GET['budget']) : '';

// --- FETCH USER FAVORITES IF LOGGED IN AS STUDENT ---
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
$favorites = [];

if ($user_id > 0 && $user_role == 'student') {
    $fav_q = $conn->query("SELECT prop_id FROM favorites WHERE user_id = $user_id");
    if ($fav_q) {
        while ($f = $fav_q->fetch_assoc()) {
            $favorites[] = $f['prop_id'];
        }
    }
}

// 2. BUILD SQL QUERY DYNAMICALLY (Added u.is_doc_verified)
$sql = "SELECT p.*, u.subscription_plan, u.subscription_expiry, u.is_verified, u.is_doc_verified 
        FROM properties p 
        JOIN users u ON p.landlord_id = u.user_id 
        WHERE p.availability_status = 1";

// Apply Filters
if (!empty($city)) {
    $sql .= " AND p.city LIKE '%$city%'";
}
if (!empty($gender)) {
    $sql .= " AND p.gender_type = '$gender'";
}
if (!empty($food)) {
    $sql .= " AND (p.food_type = '$food' OR p.food_type = 'Both')";
}
if (!empty($budget)) {
    $range = explode('-', $budget);
    if(count($range) == 2) {
        $min_budget = intval($range[0]);
        $max_budget = intval($range[1]);
        $sql .= " AND p.rent BETWEEN $min_budget AND $max_budget";
    } elseif ($budget == '20000+') {
        $sql .= " AND p.rent >= 20000";
    }
}

// 3. SORTING LOGIC
$sql .= " ORDER BY 
          (u.subscription_plan = 'premium' AND u.subscription_expiry >= CURDATE()) DESC, 
          p.created_at DESC";

$result = $conn->query($sql);
?>

<style>
    /* CSS for Interactive Clickable Card */
    .card {
        position: relative; /* Necessary for stretched link */
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    /* This makes the 'View' button link cover the whole card */
    .stretched-link::after {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1; /* Link is above the card content */
        content: "";
    }

    /* Favorite button MUST be above the stretched link so it stays clickable */
    .fav-btn {
        position: relative;
        z-index: 2; 
    }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
    
    <div style="background: var(--bg-card); padding: 25px; margin-bottom: 30px; border-radius: var(--radius); border: 1px solid var(--border-color); box-shadow: var(--shadow);">
        
        <form action="listings.php" method="GET" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: flex-end;">
            
            <div style="flex: 1; min-width: 200px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">City</label>
                <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>" placeholder="e.g. Pune" 
                       style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);">
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Type</label>
                <select name="gender" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);">
                    <option value="">All Types</option>
                    <option value="Boys" <?php if($gender == 'Boys') echo 'selected'; ?>>Boys PG</option>
                    <option value="Girls" <?php if($gender == 'Girls') echo 'selected'; ?>>Girls PG</option>
                    <option value="Family" <?php if($gender == 'Family') echo 'selected'; ?>>Family / Flat</option>
                </select>
            </div>

            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Food</label>
                <select name="food" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);">
                    <option value="">Any</option>
                    <option value="Veg" <?php if($food == 'Veg') echo 'selected'; ?>>Veg Only</option>
                    <option value="Non-Veg" <?php if($food == 'Non-Veg') echo 'selected'; ?>>Non-Veg Allowed</option>
                </select>
            </div>
            
            <div style="flex: 1; min-width: 150px;">
                <label style="font-weight: bold; margin-bottom: 5px; display: block;">Budget</label>
                <select name="budget" style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);">
                    <option value="">Any Budget</option>
                    <option value="0-5000" <?php if($budget == '0-5000') echo 'selected'; ?>>Under ₹5,000</option>
                    <option value="5000-10000" <?php if($budget == '5000-10000') echo 'selected'; ?>>₹5k - ₹10k</option>
                    <option value="10000-20000" <?php if($budget == '10000-20000') echo 'selected'; ?>>₹10k - ₹20k</option>
                    <option value="20000+" <?php if($budget == '20000+') echo 'selected'; ?>>Above ₹20k</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary" style="height: 46px; padding: 0 30px; margin-bottom: 2px;">Apply Filters</button>
        </form>
    </div>

    <h2 style="margin-bottom: 20px; color: var(--primary);">
        <?php echo $result->num_rows; ?> Properties Found
    </h2>

    <div class="grid-3">
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                
                <?php 
                    $is_premium = ($row['subscription_plan'] == 'premium' && $row['subscription_expiry'] >= date('Y-m-d'));
                    $card_class = $is_premium ? 'card premium-glow' : 'card';
                ?>

                <div class="<?php echo $card_class; ?>" style="display: flex; flex-direction: column; height: 100%;">
                    
                    <?php if($is_premium): ?>
                        <span class="badge badge-premium">🏆 Premium Host</span>
                    <?php endif; ?>
                    
                    <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" class="card-img" alt="PG Image" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    
                    <div class="card-body" style="display: flex; flex-direction: column; flex-grow: 1;">
                        <div class="card-title" style="font-size: 1.3rem;">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </div>
                        
                        <div class="card-info" style="margin-bottom: 10px;">
                            <p>📍 <?php echo htmlspecialchars($row['city']); ?></p>
                            <p style="margin-top: 5px;">
                                <?php if($row['gender_type'] == 'Boys') echo '👦 Boys'; ?>
                                <?php if($row['gender_type'] == 'Girls') echo '👧 Girls'; ?>
                                <?php if($row['gender_type'] == 'Family') echo '👨‍👩‍👧 Family'; ?>
                                
                                <?php if($row['is_doc_verified']): ?>
                                    <span class="badge badge-verified" style="margin-left: 10px;">✅ Verified</span>
                                <?php endif; ?>
                            </p>
                        </div>

                        <div style="margin-bottom: 15px; display: flex; gap: 10px;">
                            <span style="border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; color: var(--text-light);">
                                <?php echo $row['occupancy']; ?> Sharing
                            </span>
                            <span style="border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 4px; font-size: 0.85rem; color: var(--text-light);">
                                <?php echo $row['food_type']; ?> Food
                            </span>
                        </div>
                        
                        <div class="card-footer" style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                            <span class="price">₹<?php echo $row['rent']; ?>/mo</span>
                            
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <?php if($user_role == 'student'): ?>
                                    <?php $is_fav = in_array($row['prop_id'], $favorites); ?>
                                    <a href="toggle_favorite.php?id=<?php echo $row['prop_id']; ?>" 
                                       class="btn btn-outline fav-btn" 
                                       style="padding: 6px 12px; font-size: 1.2rem; line-height: 1; border-color: <?php echo $is_fav ? '#dc3545' : 'var(--border-color)'; ?>; text-decoration: none;" 
                                       title="Save to Favorites">
                                        <?php echo $is_fav ? '❤️' : '🤍'; ?>
                                    </a>
                                <?php endif; ?>
                                
                                <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-primary stretched-link">View</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px; background: var(--bg-card); border-radius: 8px;">
                <h3 style="color: var(--text-light);">No properties found.</h3>
                <p style="color: var(--text-light);">Try adjusting your filters to find more options.</p>
                <a href="listings.php" class="btn btn-outline" style="margin-top: 15px;">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="height: 60px;"></div> 
</div>

<?php include 'footer.php'; ?>