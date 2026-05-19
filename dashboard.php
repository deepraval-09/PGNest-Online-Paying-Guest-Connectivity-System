<?php
session_start();
include 'db.php';

// Security: Only Landlords allowed
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'landlord') {
    header("Location: login.php");
    exit;
}

$landlord_id = $_SESSION['user_id'];

// 0. FETCH USER DETAILS (Verification & Subscription)
$u_sql = "SELECT * FROM users WHERE user_id = $landlord_id";
$u_res = $conn->query($u_sql);
$user_data = $u_res->fetch_assoc();

$is_doc_verified = isset($user_data['is_doc_verified']) ? $user_data['is_doc_verified'] : 0;
$plan = $user_data['subscription_plan'];
$expiry = $user_data['subscription_expiry'];
// Fetch Strikes for the warning system
$strikes = isset($user_data['strikes']) ? $user_data['strikes'] : 0;

// Check if Premium is Active
$is_premium = ($plan == 'premium' && $expiry >= date('Y-m-d'));

// 1. Fetch Properties
$prop_sql = "SELECT * FROM properties WHERE landlord_id = $landlord_id ORDER BY created_at DESC";
$prop_result = $conn->query($prop_sql);
$total_properties = $prop_result->num_rows;

// 2. Fetch Unlocks (Leads)
$lead_sql = "SELECT u.full_name, u.phone, p.title, un.unlocked_at 
             FROM unlocks un
             JOIN users u ON un.student_id = u.user_id
             JOIN properties p ON un.prop_id = p.prop_id
             WHERE p.landlord_id = $landlord_id
             ORDER BY un.unlocked_at DESC";
$lead_result = $conn->query($lead_sql);
$total_leads = $lead_result->num_rows;

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
        flex-direction: column;
        height: 100%;
        text-align: left;
        border: 1px solid var(--border-color);
        transition: transform 0.2s ease;
    }

    .dash-card:hover {
        transform: translateY(-2px);
    }

    .dash-content {
        flex-grow: 1; 
        margin-bottom: 20px;
    }

    .card-action-btn {
        width: 100%;
        text-align: center;
        padding: 10px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: block;
        margin-top: auto;
    }

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
    
    <div class="dashboard-header" style="margin-bottom: 30px;">
        <div>
            <h1 style="color: var(--primary); margin-bottom: 5px;">Provider Dashboard</h1>
            <p style="color: var(--text-light);">Manage your listings and view interested students.</p>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <?php if ($is_doc_verified == 1): ?>
                <span class="badge badge-verified" style="font-size: 1rem; padding: 10px 15px;">✅ Verified</span>
            <?php else: ?>
                <a href="verify_account.php" class="btn" style="background: #fff3cd; color: #856404; border: 1px solid #ffeeba;">
                    ⚠️ Get Verified
                </a>
            <?php endif; ?>

            <?php if ($is_premium): ?>
                <span class="badge badge-premium" style="font-size: 1rem; padding: 10px 15px; position: static;">🏆 Premium Host</span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($strikes >= 3): ?>
        <div style="background: #f8d7da; color: #842029; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #f5c6cb; box-shadow: 0 4px 10px rgba(220,53,69,0.2);">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">⛔ ACCOUNT RESTRICTED (3 Strikes)</h3>
            <p style="margin-bottom: 0;">You have received 3 strikes for ignoring student contacts or providing incorrect numbers. Your properties have been automatically hidden from search results to protect students. Please contact Admin to appeal.</p>
        </div>
    <?php elseif ($strikes > 0): ?>
        <div style="background: #fff3cd; color: #856404; padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffeeba;">
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">⚠️ Warning: Strike <?php echo $strikes; ?>/3</h3>
            <p style="margin-bottom: 0;">Students have reported that you are not answering calls after they unlocked your contact. If you reach 3 strikes, all your properties will be removed from the platform.</p>
        </div>
    <?php endif; ?>
    <div class="dashboard-grid">
        
        <div class="dash-card" style="border-left: 5px solid var(--primary);">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 1.4rem; color: var(--text-main); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($user_data['full_name']); ?>
                        </h2>
                        <span style="font-size: 0.85rem; color: #888; background: var(--bg-input); padding: 2px 8px; border-radius: 4px;">Landlord</span>
                    </div>
                    <div style="font-size: 1.5rem; color: var(--primary);">🏠</div>
                </div>
                
                <div style="margin-top: 20px;">
                    <p style="margin-bottom: 8px; font-size: 0.95rem; color: var(--text-light);">
                        <strong>📧 Email:</strong><br> <?php echo htmlspecialchars($user_data['email']); ?>
                    </p>
                    <p style="font-size: 0.95rem; color: var(--text-light);">
                        <strong>📞 Phone:</strong><br> 
                        <?php echo htmlspecialchars($user_data['phone']); ?>
                    </p>
                </div>
            </div>
            
            <a href="edit_landlord.php" class="card-action-btn btn-edit">
                ✏️ Edit Details
            </a>
        </div>

        <div class="dash-card" style="border-left: 5px solid #17a2b8;">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 0; line-height: 1;"><?php echo $total_properties; ?></h2>
                        <p style="font-weight: bold; color: var(--text-light); margin-top: 5px;">Active Listings</p>
                    </div>
                    <div style="font-size: 1.8rem;">🏢</div>
                </div>
                <p style="margin-top: 15px; color: #888; font-size: 0.9rem;">
                    Properties currently live on PGNest.
                </p>
            </div>
            <a href="add_pg.php" class="card-action-btn btn-primary" style="background: #17a2b8; color: white;">
                + Add Property
            </a>
        </div>

        <div class="dash-card" style="border-left: 5px solid #28a745;">
            <div class="dash-content">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h2 style="font-size: 2.5rem; margin-bottom: 0; line-height: 1;"><?php echo $total_leads; ?></h2>
                        <p style="font-weight: bold; color: var(--text-light); margin-top: 5px;">Student Leads</p>
                    </div>
                    <div style="font-size: 1.8rem;">👥</div>
                </div>
                <p style="margin-top: 15px; color: #888; font-size: 0.9rem;">
                    Students who unlocked your contact.
                </p>
            </div>
            <div style="margin-top: auto; padding: 10px; text-align: center; color: var(--text-light); font-size: 0.85rem; background: var(--bg-input); border-radius: 6px;">
                View list below
            </div>
        </div>
    </div>

    <?php if (!$is_premium): ?>
    <div style="background: linear-gradient(135deg, #141E30 0%, #243B55 100%); color: white; padding: 30px; border-radius: 12px; margin-bottom: 40px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
        <div>
            <h2 style="margin-top: 0; color: #FFD700; font-size: 1.8rem;">🚀 Boost Your Rankings!</h2>
            <p style="margin: 5px 0; opacity: 0.9;">Get <strong>5x More Leads</strong> and appear at the top of search results.</p>
        </div>
        <a href="pricing.php" class="btn btn-accent" style="box-shadow: 0 4px 15px rgba(255, 171, 0, 0.4);">
            Upgrade to Premium (₹499)
        </a>
    </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 style="color: var(--primary);">Your Properties</h2>
    </div>

    <div class="grid-3">
        <?php if ($total_properties > 0): ?>
            <?php while($row = $prop_result->fetch_assoc()): ?>
                
                <div class="card" style="position: relative;">
                    
                    <?php if ($row['availability_status'] == 1): ?>
                        <span class="badge badge-available" style="position: absolute; top: 10px; right: 10px; z-index: 10;">● Available</span>
                    <?php else: ?>
                        <span class="badge badge-full" style="position: absolute; top: 10px; right: 10px; z-index: 10;">● Sold Out / Hidden</span>
                    <?php endif; ?>

                    <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" class="card-img" style="<?php echo ($row['availability_status'] == 0) ? 'filter: grayscale(100%); opacity: 0.8;' : ''; ?>" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                    
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div class="card-info">
                            <?php echo htmlspecialchars($row['city']); ?> • ₹<?php echo $row['rent']; ?>/mo
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                             <span style="border: 1px solid var(--border-color); color: var(--text-light); padding: 2px 8px; border-radius: 4px; font-size: 0.8rem;">
                                <?php echo isset($row['occupancy']) ? $row['occupancy'] : 'Double'; ?> Sharing
                             </span>
                        </div>
                        
                        <div class="card-footer" style="flex-direction: column; gap: 10px; align-items: stretch;">
                            
                            <?php if ($strikes < 3): ?>
                                <?php if ($row['availability_status'] == 1): ?>
                                    <a href="toggle_status.php?id=<?php echo $row['prop_id']; ?>&status=0" class="btn btn-danger" style="width: 100%;">
                                        Mark Full ⛔
                                    </a>
                                <?php else: ?>
                                    <a href="toggle_status.php?id=<?php echo $row['prop_id']; ?>&status=1" class="btn btn-success" style="width: 100%; background-color: var(--success); color: white;">
                                        Mark Available ✅
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn btn-outline" disabled style="width: 100%; cursor: not-allowed; color: var(--danger); border-color: var(--danger);">Locked (3 Strikes)</button>
                            <?php endif; ?>

                            <div style="display: flex; gap: 10px;">
                                <a href="edit_pg.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-outline" style="flex: 1;">Edit</a>
                                <a href="delete_pg.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-outline" style="flex: 1; border-color: var(--danger); color: var(--danger);" onclick="return confirm('Delete this property?')">Delete</a>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px; background: var(--bg-card); border-radius: 8px;">
                <p style="color: var(--text-light);">You haven't listed any properties yet.</p>
            </div>
        <?php endif; ?>
    </div>

    <h2 style="margin-top: 50px; color: var(--primary);">Interested Students (Leads)</h2>
    
    <div style="background: var(--bg-card); border-radius: 8px; box-shadow: var(--shadow); overflow: hidden;">
        <?php if ($total_leads > 0): ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--primary); color: white;">
                    <tr>
                        <th style="padding: 15px; text-align: left;">Student Name</th>
                        <th style="padding: 15px; text-align: left;">Property</th>
                        <th style="padding: 15px; text-align: left;">Phone Number</th>
                        <th style="padding: 15px; text-align: left;">Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($lead = $lead_result->fetch_assoc()): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 15px;"><strong><?php echo htmlspecialchars($lead['full_name']); ?></strong></td>
                        <td style="padding: 15px;"><?php echo htmlspecialchars($lead['title']); ?></td>
                        <td style="padding: 15px; color: var(--primary); font-weight: bold;">
                            <a href="tel:<?php echo htmlspecialchars($lead['phone']); ?>"><?php echo htmlspecialchars($lead['phone']); ?></a>
                        </td>
                        <td style="padding: 15px; color: var(--text-light); font-size: 0.9rem;"><?php echo date('d M Y', strtotime($lead['unlocked_at'])); ?></td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="padding: 30px; text-align: center; color: var(--text-light);">
                No students have unlocked your contact yet.
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'footer.php'; ?>