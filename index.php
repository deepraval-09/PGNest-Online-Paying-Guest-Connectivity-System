<?php 
session_start();

// --- SMART REDIRECT LOGIC ---
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['user_role'] == 'admin') {
        header("Location: admin_panel.php");
        exit;
    } elseif ($_SESSION['user_role'] == 'landlord') {
        header("Location: dashboard.php");
        exit;
    }
}

// 1. INCLUDE COMMON FILES
include 'db.php';
include 'header.php'; 

// 2. FETCH LATEST 3 AVAILABLE PROPERTIES
$sql = "SELECT p.*, u.subscription_plan, u.subscription_expiry, u.is_verified 
        FROM properties p 
        JOIN users u ON p.landlord_id = u.user_id 
        WHERE p.availability_status = 1 
        ORDER BY p.created_at DESC LIMIT 3";
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
</style>

<section class="hero">
    <div class="container">
        <h1>Find Your Home Away From Home</h1>
        <p>Zero Brokerage. Verified Owners. Safe & Secure PGs for Students.</p>
        
        <form action="listings.php" method="GET" class="search-bar">
            <input type="text" name="city" placeholder="Enter City (e.g. Pune, Kota)" required>
            
            <select name="budget">
                <option value="">Any Budget</option>
                <option value="0-5000">Under ₹5,000</option>
                <option value="5000-10000">₹5k - ₹10k</option>
                <option value="10000-20000">Above ₹10k</option>
            </select>
            
            <button type="submit">Search</button>
        </form>
    </div>
</section>

<div class="container" style="padding-top: 40px; padding-bottom: 40px;">
    <div style="text-align: center; margin-bottom: 50px;">
        <h2 style="color: var(--primary); font-size: 2rem; margin-bottom: 10px;">How PGNest Works</h2>
        <p style="color: var(--text-light);">Three simple steps to find your perfect stay.</p>
    </div>

    <div class="grid-3" style="text-align: center;">
        <div style="padding: 20px;">
            <div style="font-size: 3rem; margin-bottom: 15px;">🔍</div>
            <h3 style="margin-bottom: 10px;">1. Search</h3>
            <p style="color: var(--text-light);">Filter by City, Price, and Amenities like "Veg Food" or "AC".</p>
        </div>
        
        <div style="padding: 20px;">
            <div style="font-size: 3rem; margin-bottom: 15px;">🛡️</div>
            <h3 style="margin-bottom: 10px;">2. Verify</h3>
            <p style="color: var(--text-light);">We check Electricity Bills to ensure every Landlord is genuine.</p>
        </div>
        
        <div style="padding: 20px;">
            <div style="font-size: 3rem; margin-bottom: 15px;">🔑</div>
            <h3 style="margin-bottom: 10px;">3. Unlock</h3>
            <p style="color: var(--text-light);">Use <strong>Nest Pass</strong> to unlock verified contacts instantly.</p>
        </div>
    </div>
</div>

<div class="container" style="margin-bottom: 60px;">
    <h2 style="color: var(--primary); margin-bottom: 25px; border-left: 5px solid var(--accent); padding-left: 15px;">
        Freshly Listed PGs
    </h2>
    
    <div class="grid-3">
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                
                <?php 
                    $is_premium = ($row['subscription_plan'] == 'premium' && $row['subscription_expiry'] >= date('Y-m-d'));
                    $card_class = $is_premium ? 'card premium-glow' : 'card';
                ?>

                <div class="<?php echo $card_class; ?>">
                    
                    <?php if($is_premium): ?>
                        <span class="badge badge-premium">🏆 Premium Host</span>
                    <?php endif; ?>

                    <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" class="card-img" alt="PG Image" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        
                        <div class="card-info">
                            📍 <?php echo htmlspecialchars($row['city']); ?> • <?php echo $row['gender_type']; ?> Only
                            
                            <?php if($row['is_verified']): ?>
                                <span style="color: var(--success); font-weight: bold; font-size: 0.8rem; margin-left: 5px;">✅ Verified</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer" style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="price">₹<?php echo $row['rent']; ?>/mo</span>
                            <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-primary stretched-link">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: var(--bg-card); border-radius: 8px; border: 1px dashed var(--border-color);">
                <h3 style="color: var(--text-light);">No properties listed yet.</h3>
                <p>Be the first to list a property!</p>
            </div>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 40px;">
        <a href="listings.php" class="btn btn-outline" style="border-width: 2px;">View All Listings &rarr;</a>
    </div>
</div>

<?php if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] == 'student'): ?>
<div class="container" style="margin-bottom: 60px;">
    <div style="background: var(--primary); color: white; padding: 60px; border-radius: 12px; text-align: center; box-shadow: 0 10px 30px rgba(0, 86, 179, 0.2);">
        <h2 style="margin-top: 0; font-size: 2.2rem;">Are you a Property Owner?</h2>
        <p style="opacity: 0.9; font-size: 1.2rem; margin-bottom: 30px; margin-top: 10px;">
            List your property in 2 minutes and find verified students without paying brokerage.
        </p>
        <a href="register.php" class="btn btn-accent" style="font-size: 1.1rem; padding: 15px 40px;">List Your Property Free</a>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>