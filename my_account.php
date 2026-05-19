<?php
session_start();
include 'db.php';

// 1. SECURITY: STRICTLY STUDENT ONLY
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'student') {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['user_id'];

// 2. FETCH STUDENT DETAILS
$user_sql = "SELECT * FROM users WHERE user_id = $student_id";
$user_result = $conn->query($user_sql);
$user = $user_result->fetch_assoc();

// 3. FETCH UNLOCKED CONTACTS HISTORY
$history_sql = "SELECT p.prop_id, p.title, p.city, p.rent, u.full_name, u.phone, un.unlocked_at 
                FROM unlocks un
                JOIN properties p ON un.prop_id = p.prop_id
                JOIN users u ON p.landlord_id = u.user_id
                WHERE un.student_id = $student_id
                ORDER BY un.unlocked_at DESC";
$history_result = $conn->query($history_sql);

// --- NEW: FETCH SAVED FAVORITE PROPERTIES ---
$fav_sql = "SELECT p.* FROM properties p 
            JOIN favorites f ON p.prop_id = f.prop_id 
            WHERE f.user_id = $student_id 
            ORDER BY f.created_at DESC";
$fav_result = $conn->query($fav_sql);
// --------------------------------------------

include 'header.php'; 
?>

<style>
    /* CUSTOM STYLES FOR DASHBOARD CARDS */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }

    .dash-card {
        background: var(--bg-card);
        border-radius: 10px;
        padding: 25px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column; /* Stacks content vertically */
        height: 100%; /* Forces equal height */
        text-align: left; /* Left align everything */
        border: 1px solid var(--border-color);
        transition: transform 0.2s ease;
    }

    .dash-card:hover {
        transform: translateY(-2px);
    }

    /* Property Card interactive upgrades */
    .card {
        position: relative; /* Necessary for stretched link */
        display: flex;
        flex-direction: column;
        height: 100%;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .stretched-link::after {
        position: absolute;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 1;
        content: "";
    }

    /* Keep functional buttons/links above the stretched area */
    .fav-btn-action, .contact-tel-link {
        position: relative;
        z-index: 2;
    }

    /* Content Area pushes button to bottom */
    .dash-content {
        flex-grow: 1; 
        margin-bottom: 20px;
    }

    /* Button Styling */
    .card-action-btn {
        width: 100%;
        text-align: center;
        padding: 10px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: block;
        margin-top: auto; /* This forces the button to the bottom */
    }

    /* Edit Button Specifics */
    .btn-edit {
        border: 1px solid var(--primary);
        color: var(--primary);
        background: transparent;
    }
    .btn-edit:hover {
        background: var(--primary);
        color: white;
    }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">

    <div class="dashboard-header" style="margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="color: var(--primary); margin-bottom: 5px;">My Account</h1>
            <p style="color: var(--text-light);">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?></p>
        </div>
        <div>
            <a href="listings.php" class="btn btn-primary">🔍 Find New PG</a>
        </div>
    </div>

    <div class="dashboard-grid">
        
        <div class="dash-card" style="border-left: 5px solid var(--primary);">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 1.4rem; color: var(--text-main); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                        </h2>
                        <span style="font-size: 0.85rem; color: #888; background: var(--bg-input); padding: 2px 8px; border-radius: 4px;">Student</span>
                    </div>
                    <div style="font-size: 1.5rem; color: var(--primary);">👤</div>
                </div>
                
                <div style="margin-top: 20px;">
                    <p style="margin-bottom: 8px; font-size: 0.95rem; color: var(--text-light);">
                        <strong>📧 Email:</strong><br> <?php echo htmlspecialchars($user['email']); ?>
                    </p>
                    <p style="font-size: 0.95rem; color: var(--text-light);">
                        <strong>📞 Phone:</strong><br> 
                        <?php if($user['phone'] == '0000000000'): ?>
                             <span style="color: #dc3545; font-weight: bold;">⚠️ Update Needed</span>
                        <?php else: ?>
                            <?php echo htmlspecialchars($user['phone']); ?>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            
            <a href="edit_profile.php" class="card-action-btn btn-edit">
                ✏️ Edit Details
            </a>
        </div>

        <div class="dash-card" style="border-left: 5px solid #ffc107;">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 0; line-height: 1;"><?php echo $user['credits']; ?></h2>
                        <p style="font-weight: bold; color: var(--text-light); margin-top: 5px;">Credits Available</p>
                    </div>
                    <div style="font-size: 1.8rem;">⚡</div>
                </div>
                <p style="margin-top: 15px; color: #888; font-size: 0.9rem;">
                    Use credits to unlock landlord contact numbers instantly.
                </p>
            </div>
            
            <a href="pricing.php" class="card-action-btn btn-primary" style="background: var(--primary); color: white;">
                Buy More Credits
            </a>
        </div>

        <div class="dash-card" style="border-left: 5px solid #28a745;">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 0; line-height: 1;"><?php echo $history_result->num_rows; ?></h2>
                        <p style="font-weight: bold; color: var(--text-light); margin-top: 5px;">Contacts Unlocked</p>
                    </div>
                    <div style="font-size: 1.8rem;">🔓</div>
                </div>
                <p style="margin-top: 15px; color: #888; font-size: 0.9rem;">
                    Total properties you have successfully contacted.
                </p>
            </div>
            
            <div style="margin-top: auto; padding: 10px; text-align: center; color: var(--text-light); font-size: 0.85rem; background: var(--bg-input); border-radius: 6px;">
                Lifetime Stats
            </div>
        </div>

    </div>

    <h2 style="color: var(--primary); margin-bottom: 20px;">Saved Properties</h2>

    <?php if ($fav_result->num_rows > 0): ?>
        <div class="grid-3" style="margin-bottom: 50px;">
            <?php while($row = $fav_result->fetch_assoc()): ?>
                <div class="card">
                    <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" class="card-img" alt="PG Image" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    
                    <div class="card-body" style="display: flex; flex-direction: column; flex-grow: 1;">
                        <div class="card-title" style="font-size: 1.2rem;">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </div>
                        
                        <div class="card-info" style="margin-bottom: 10px;">
                            <p>📍 <?php echo htmlspecialchars($row['city']); ?></p>
                            <p style="margin-top: 5px;"><?php echo $row['gender_type']; ?> Only • <?php echo $row['occupancy']; ?> Sharing</p>
                        </div>
                        
                        <div class="card-footer" style="margin-top: auto; display: flex; justify-content: space-between; align-items: center;">
                            <span class="price">₹<?php echo $row['rent']; ?>/mo</span>
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-primary stretched-link" style="padding: 6px 15px;">View</a>
                                <a href="toggle_favorite.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-outline fav-btn-action" style="padding: 6px 10px; border-color: #dc3545; color: #dc3545;" title="Remove from Favorites">
                                    ❌
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; background: var(--bg-card); border-radius: 8px; border: 1px dashed var(--border-color); margin-bottom: 50px;">
            <p style="color: var(--text-light); font-size: 1.1rem;">You haven't saved any properties to your favorites yet.</p>
            <a href="listings.php" class="btn btn-outline" style="margin-top: 15px;">Browse & Save PGs</a>
        </div>
    <?php endif; ?>

    <h2 style="color: var(--primary); margin-bottom: 20px;">Unlocked Contacts History</h2>

    <?php if ($history_result->num_rows > 0): ?>
        <div class="grid-3">
            <?php while($row = $history_result->fetch_assoc()): ?>
                
                <div class="card">
                    
                    <div class="card-body" style="display: flex; flex-direction: column; flex-grow: 1;">
                        
                        <div class="card-title">
                            <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" class="stretched-link"><?php echo htmlspecialchars($row['title']); ?></a>
                        </div>
                        <p style="color: var(--text-light); margin-bottom: 15px;">
                            📍 <?php echo htmlspecialchars($row['city']); ?> • ₹<?php echo $row['rent']; ?>/mo
                        </p>
                        
                        <div style="background: var(--bg-cyan-card); padding: 15px; border-radius: 6px; border: 1px solid var(--border-cyan); position: relative; z-index: 2;">
                            <p style="font-size: 0.85rem; color: var(--text-teal); margin-bottom: 5px;">Landlord Name:</p>
                            <strong style="color: var(--text-main);"><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            
                            <hr style="border: 0; border-top: 1px solid var(--border-cyan); margin: 10px 0;">
                            
                            <p style="font-size: 0.85rem; color: var(--text-teal); margin-bottom: 5px;">Phone Number:</p>
                            <a href="tel:<?php echo $row['phone']; ?>" class="contact-tel-link" style="font-size: 1.2rem; font-weight: bold; color: var(--text-teal); text-decoration: none;">
                                📞 <?php echo $row['phone']; ?>
                            </a>
                        </div>

                        <div style="margin-top: auto; padding-top: 15px; text-align: right; font-size: 0.8rem; color: var(--text-light);">
                            Unlocked on <?php echo date('d M Y', strtotime($row['unlocked_at'])); ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 60px; background: var(--bg-card); border-radius: 8px; border: 1px dashed var(--border-color);">
            <h3 style="color: var(--text-light);">No contacts unlocked yet.</h3>
            <p style="color: var(--text-light);">Search for a PG and unlock the owner's number to see it here.</p>
            <a href="listings.php" class="btn btn-primary" style="margin-top: 15px;">Browse Properties</a>
        </div>
    <?php endif; ?>

</div>

<?php include 'footer.php'; ?>