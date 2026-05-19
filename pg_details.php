<?php
session_start();
include 'db.php';
include 'header.php';

// 1. GET PROPERTY ID
if (!isset($_GET['id'])) {
    echo "<script>window.location.href='listings.php';</script>";
    exit;
}
$prop_id = intval($_GET['id']); // Added intval for security
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';

// 2. HANDLE UNLOCK REQUEST
if (isset($_POST['unlock_contact'])) {
    if (!isset($_SESSION['user_id'])) {
        echo "<script>window.location.href='login.php';</script>";
        exit;
    }

    // Check Credits
    $check_credits = $conn->query("SELECT credits FROM users WHERE user_id = $user_id");
    $user_data = $check_credits->fetch_assoc();

    if ($user_data['credits'] > 0) {
        $conn->query("UPDATE users SET credits = credits - 1 WHERE user_id = $user_id");
        $conn->query("INSERT INTO unlocks (student_id, prop_id) VALUES ($user_id, $prop_id)");
        
        // --- NOTIFICATION LOGIC FOR LANDLORD ---
        $prop_q = $conn->query("SELECT landlord_id, title FROM properties WHERE prop_id = $prop_id");
        if ($prop_q && $prop_q->num_rows > 0) {
            $p_data = $prop_q->fetch_assoc();
            $landlord_id = $p_data['landlord_id'];
            $prop_title = $conn->real_escape_string($p_data['title']);

            // Get Student Name
            $student_q = $conn->query("SELECT full_name FROM users WHERE user_id = $user_id");
            $student_name = "A student";
            if ($student_q && $student_q->num_rows > 0) {
                $student_name = $conn->real_escape_string($student_q->fetch_assoc()['full_name']);
            }

            // Send Notification (Redirects landlord to chat with student)
            $notif_msg = "🔓 $student_name just unlocked the contact for '$prop_title'.";
            $link = "chat_view.php?user_id=" . $user_id;
            
            if (function_exists('send_notification')) {
                send_notification($conn, $landlord_id, $notif_msg, "success", $link);
            }
        }
        // ---------------------------------------------

        echo "<script>window.location.href='pg_details.php?id=$prop_id';</script>";
    } else {
        echo "<script>window.location.href='pricing.php';</script>";
    }
}

// 2.5 HANDLE REPORT SUBMISSION
if (isset($_POST['submit_report'])) {
    if ($user_role == 'student') {
        $issue_type = $conn->real_escape_string($_POST['issue_type']);
        $description = $conn->real_escape_string($_POST['description']);

        $report_sql = "INSERT INTO reports (student_id, prop_id, issue_type, description, status) 
                       VALUES ($user_id, $prop_id, '$issue_type', '$description', 'pending')";
        
        if ($conn->query($report_sql)) {
            // Notify Admin about the dispute
            if (function_exists('send_notification')) {
                $admin_q = $conn->query("SELECT user_id FROM users WHERE role = 'admin' LIMIT 1");
                if ($admin_q && $admin_q->num_rows > 0) {
                    $admin_id = $admin_q->fetch_assoc()['user_id'];
                    send_notification($conn, $admin_id, "⚠️ Dispute Filed: A student reported Property #$prop_id.", "danger", "admin_panel.php");
                }
            }
            echo "<script>alert('Report submitted successfully! Admin will review it shortly.'); window.location.href='pg_details.php?id=$prop_id';</script>";
            exit;
        } else {
            echo "<script>alert('Error submitting report.');</script>";
        }
    }
}

// 2.6 HANDLE REVIEW SUBMISSION & UPDATION
if (isset($_POST['submit_review'])) {
    if ($user_role == 'student') {
        
        // Double check they actually unlocked the property before accepting POST data
        $check_access = $conn->query("SELECT * FROM unlocks WHERE student_id = $user_id AND prop_id = $prop_id");
        if ($check_access->num_rows > 0) {
            $rating = intval($_POST['rating']);
            $comment = $conn->real_escape_string(htmlspecialchars($_POST['comment']));

            // --- FIX: Check if it is a NEW review or an EDIT ---
            $check_rev = $conn->query("SELECT review_id FROM reviews WHERE user_id = $user_id AND prop_id = $prop_id");
            if ($check_rev->num_rows > 0) {
                // UPDATE EXISTING REVIEW
                $rev_sql = "UPDATE reviews SET rating = $rating, comment = '$comment', created_at = CURRENT_TIMESTAMP WHERE user_id = $user_id AND prop_id = $prop_id";
                if ($conn->query($rev_sql)) {
                    echo "<script>alert('Your review has been successfully updated!'); window.location.href='pg_details.php?id=$prop_id';</script>";
                    exit;
                } else {
                    echo "<script>alert('Error updating review.');</script>";
                }
            } else {
                // INSERT NEW REVIEW
                $rev_sql = "INSERT INTO reviews (prop_id, user_id, rating, comment) VALUES ($prop_id, $user_id, $rating, '$comment')";
                if ($conn->query($rev_sql)) {
                    echo "<script>alert('Thank you! Your review has been posted.'); window.location.href='pg_details.php?id=$prop_id';</script>";
                    exit;
                } else {
                    echo "<script>alert('Error posting review.');</script>";
                }
            }
            // ---------------------------------------------------
        } else {
             echo "<script>alert('You must unlock the property contact before leaving a review.');</script>";
        }
    }
}

// 3. FETCH PROPERTY DETAILS
$sql = "SELECT p.*, u.full_name, u.phone, u.is_doc_verified 
        FROM properties p 
        JOIN users u ON p.landlord_id = u.user_id 
        WHERE p.prop_id = $prop_id";
$result = $conn->query($sql);

if (!$result || $result->num_rows == 0) {
    echo "<h2>Property not found.</h2>";
    exit;
}
$row = $result->fetch_assoc();

// 3.5 FETCH REVIEWS & CALCULATE AVERAGE
$rev_query = $conn->query("SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.user_id WHERE r.prop_id = $prop_id ORDER BY r.created_at DESC");
$total_reviews = $rev_query->num_rows;
$avg_rating = 0;

$avg_query = $conn->query("SELECT AVG(rating) as avg_rating FROM reviews WHERE prop_id = $prop_id");
if ($avg_query && $avg_query->num_rows > 0) {
    $avg_data = $avg_query->fetch_assoc();
    if (is_numeric($avg_data['avg_rating'])) {
        $avg_rating = round($avg_data['avg_rating'], 1);
    }
}

// 4. CHECK IF ALREADY UNLOCKED, FAVORITED, REPORTED & REVIEWED
$is_unlocked = false;
$is_fav = false;
$has_reported = false;
$report_status = "";

// --- NEW: Variables for the Edit Review state ---
$has_reviewed = false;
$existing_rating = 5;
$existing_comment = "";

if ($user_id > 0) {
    // Check Unlock
    $check_unlock = $conn->query("SELECT * FROM unlocks WHERE student_id = $user_id AND prop_id = $prop_id");
    if ($check_unlock->num_rows > 0) {
        $is_unlocked = true;
    }
    if ($_SESSION['user_id'] == $row['landlord_id']) {
        $is_unlocked = true;
    }
    
    // Check Favorite, Report & Reviews (Student Only)
    if ($user_role == 'student') {
        $check_fav = $conn->query("SELECT * FROM favorites WHERE user_id = $user_id AND prop_id = $prop_id");
        if ($check_fav && $check_fav->num_rows > 0) {
            $is_fav = true;
        }

        $check_report = $conn->query("SELECT status FROM reports WHERE student_id = $user_id AND prop_id = $prop_id LIMIT 1");
        if ($check_report && $check_report->num_rows > 0) {
            $has_reported = true;
            $report_status = $check_report->fetch_assoc()['status'];
        }

        // --- NEW: Fetch existing review to pre-fill the form ---
        $check_my_rev = $conn->query("SELECT rating, comment FROM reviews WHERE user_id = $user_id AND prop_id = $prop_id LIMIT 1");
        if ($check_my_rev && $check_my_rev->num_rows > 0) {
            $my_rev_data = $check_my_rev->fetch_assoc();
            $existing_rating = $my_rev_data['rating'];
            $existing_comment = $my_rev_data['comment'];
            $has_reviewed = true;
        }
    }
}
?>

<style>
    /* CSS for Interactive Clickable Card */
    .card {
        position: relative; /* Necessary for stretched link */
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

    /* UPDATED FAVORITE BUTTON CSS - STRICT RED (#dc3545) */
    .btn-favorite {
        transition: all 0.3s ease;
        font-weight: 600;
        display: inline-block;
        text-align: center;
        width: 100%;
        padding: 10px;
        border-radius: 6px;
        font-size: 1rem;
        text-decoration: none;
    }

    .btn-favorite.unsaved {
        border: 2px solid #dc3545;
        background: transparent;
        color: var(--text-main);
    }
    .btn-favorite.unsaved:hover {
        background-color: #dc3545;
        color: #ffffff !important;
        box-shadow: 0 4px 6px rgba(220, 53, 69, 0.2);
    }

    .btn-favorite.saved {
        border: 2px solid #dc3545;
        background-color: #dc3545;
        color: #ffffff !important;
    }
    .btn-favorite.saved:hover {
        background-color: #c82333;
        border-color: #bd2130;
    }

    body.dark-mode .btn-favorite.unsaved {
        border-color: #dc3545 !important;
        color: #dc3545 !important; 
    }
    body.dark-mode .btn-favorite.unsaved:hover {
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }
    
    body.dark-mode .btn-favorite.saved {
        border-color: #dc3545 !important;
        background-color: #dc3545 !important;
        color: #ffffff !important;
    }
    body.dark-mode .btn-favorite.saved:hover {
        background-color: #c82333 !important;
        border-color: #bd2130 !important;
    }
</style>

<div class="container" style="margin-top: 30px;">
    
    <div class="detail-header">
        <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" alt="Property Image" onerror="this.src='https://via.placeholder.com/1200x400?text=No+Image'">
        <div style="position: absolute; bottom: 20px; left: 20px; background: rgba(0,0,0,0.8); color: white; padding: 15px 25px; border-radius: 8px;">
            <h1 style="margin: 0; font-size: 2rem;"><?php echo htmlspecialchars($row['title']); ?></h1>
            <p style="margin: 5px 0 0 0; opacity: 0.9;">📍 <?php echo htmlspecialchars($row['address']); ?>, <?php echo htmlspecialchars($row['city']); ?></p>
            
            <?php if($total_reviews > 0): ?>
                <div style="margin-top: 10px; display: inline-block; background: rgba(255,193,7,0.2); padding: 4px 10px; border-radius: 4px; border: 1px solid #ffc107;">
                    <span style="color: #ffc107; font-weight: bold; font-size: 1.1rem;">⭐ <?php echo $avg_rating; ?>/5</span> 
                    <span style="font-size: 0.9rem; margin-left: 5px;">(<?php echo $total_reviews; ?> Reviews)</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px;">
        
        <div class="detail-content">
            <h2 style="color: var(--primary); border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Property Overview</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Rent:</strong> <br> <span style="font-size: 1.2rem; color: var(--primary);">₹<?php echo $row['rent']; ?> / month</span>
                </div>
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Deposit:</strong> <br> ₹<?php echo $row['rent'] * 2; ?> (Estimated)
                </div>
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Occupancy:</strong> <br> <?php echo $row['occupancy']; ?> Sharing
                </div>
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Food:</strong> <br> <?php echo $row['food_type']; ?>
                </div>
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Type:</strong> <br> <?php echo $row['gender_type']; ?> Only
                </div>
                <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px;">
                    <strong>Landlord Lives Here?</strong> <br> 
                    <?php echo ($row['live_in_landlord'] == 1) ? '<span style="color:var(--success);">Yes (Safe)</span>' : 'No'; ?>
                </div>
            </div>

            <h3 style="margin-bottom: 10px;">Amenities</h3>
            <p style="color: var(--text-light); line-height: 1.8;">
                <?php echo !empty($row['amenities']) ? $row['amenities'] : "Basic amenities included (Water, Electricity)."; ?>
            </p>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 40px 0;">
            <h2 style="color: var(--primary); border-bottom: 2px solid var(--border-color); padding-bottom: 10px; margin-bottom: 20px;">Student Reviews</h2>
            
            <?php if($user_role == 'student'): ?>
                <?php if($is_unlocked): ?>
                    <div style="background: var(--bg-detail-card); padding: 20px; border-radius: 8px; margin-bottom: 30px; border: 1px solid var(--border-color);">
                        <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--text-main);">
                            <?php echo $has_reviewed ? '✏️ Edit Your Review' : 'Rate Your Stay'; ?>
                        </h4>
                        <form method="POST">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; color: var(--text-light); font-size: 0.9rem;">Rating</label>
                                <select name="rating" required style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main);">
                                    <option value="5" <?php if($existing_rating == 5) echo 'selected'; ?>>⭐⭐⭐⭐⭐ (5/5) - Excellent</option>
                                    <option value="4" <?php if($existing_rating == 4) echo 'selected'; ?>>⭐⭐⭐⭐ (4/5) - Very Good</option>
                                    <option value="3" <?php if($existing_rating == 3) echo 'selected'; ?>>⭐⭐⭐ (3/5) - Average</option>
                                    <option value="2" <?php if($existing_rating == 2) echo 'selected'; ?>>⭐⭐ (2/5) - Poor</option>
                                    <option value="1" <?php if($existing_rating == 1) echo 'selected'; ?>>⭐ (1/5) - Terrible</option>
                                </select>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 5px; color: var(--text-light); font-size: 0.9rem;">Written Review</label>
                                <textarea name="comment" rows="3" required placeholder="Share details of your experience at this PG..." style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid var(--border-color); background: var(--bg-input); color: var(--text-main);"><?php echo htmlspecialchars($existing_comment); ?></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary">
                                <?php echo $has_reviewed ? 'Update Review' : 'Submit Review'; ?>
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div style="background: rgba(255,193,7,0.1); border: 1px solid #ffeeba; padding: 15px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
                        <p style="color: #856404; margin: 0; font-size: 0.9rem;">🔒 You must unlock this property's contact before leaving a review.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php if($total_reviews > 0): ?>
                    <?php while($rev = $rev_query->fetch_assoc()): ?>
                        <div style="background: var(--bg-detail-card); padding: 15px; border-radius: 8px; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <strong style="color: var(--primary); font-size: 1.1rem;"><?php echo htmlspecialchars($rev['full_name']); ?></strong>
                                <span style="font-size: 0.9rem; background: rgba(255,193,7,0.1); padding: 3px 8px; border-radius: 4px; border: 1px solid #ffeeba;">
                                    <?php echo str_repeat('⭐', $rev['rating']); ?>
                                </span>
                            </div>
                            <p style="margin: 0; color: var(--text-main); line-height: 1.5; font-size: 0.95rem;">
                                <?php echo nl2br(htmlspecialchars($rev['comment'])); ?>
                            </p>
                            <small style="color: var(--text-light); display: block; margin-top: 10px; font-size: 0.8rem;">
                                Posted on <?php echo date('F j, Y', strtotime($rev['created_at'])); ?>
                            </small>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: var(--text-light); font-style: italic;">No reviews yet. Be the first to share your experience!</p>
                <?php endif; ?>
            </div>
            </div>

        <div class="form-box" style="margin: 0; height: fit-content;">
            <h3 style="text-align: center; margin-bottom: 20px;">Landlord Details</h3>
            
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="width: 80px; height: 80px; background: var(--bg-detail-card); border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 2rem;">👤</div>
                
                <h4 style="margin: 0;"><?php echo htmlspecialchars($row['full_name']); ?></h4>
                
                <?php if(isset($row['is_doc_verified']) && $row['is_doc_verified'] == 1): ?>
                    <span class="badge badge-verified" style="margin-top: 5px; display: inline-block;">✅ Verified Owner</span>
                <?php else: ?>
                    <span class="badge badge-full" style="margin-top: 5px; display: inline-block;">Not Verified</span>
                <?php endif; ?>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">

            <?php if($is_unlocked): ?>
                <div style="background: var(--bg-success-soft); padding: 15px; border-radius: 8px; text-align: center; color: var(--text-success-soft);">
                    <p style="margin-bottom: 5px; font-weight: bold;">Contact Number:</p>
                    <a href="tel:<?php echo $row['phone']; ?>" style="font-size: 1.5rem; font-weight: bold; text-decoration: none; color: var(--text-success-soft);">
                        <?php echo $row['phone']; ?>
                    </a>
                </div>
                <p style="font-size: 0.8rem; text-align: center; margin-top: 10px; color: var(--text-light);">
                    Call timing: 9 AM - 9 PM
                </p>
                <div style="text-align: center; margin-top: 15px;">
                    <a href="chat_view.php?user_id=<?php echo $row['landlord_id']; ?>" class="btn btn-primary" style="font-size: 0.9rem;">Chat Now 💬</a>
                </div>
                
                <?php if ($user_role == 'student'): ?>
                    <div style="margin-top: 20px;">
                        <?php if ($has_reported): ?>
                            <div style="background: #fff3cd; color: #856404; padding: 10px; border-radius: 6px; text-align: center; font-size: 0.85rem; border: 1px solid #ffeeba;">
                                ⚠️ Report submitted. Status: <strong><?php echo ucfirst($report_status); ?></strong>
                            </div>
                        <?php else: ?>
                            <button id="btn-show-report" type="button" onclick="document.getElementById('report-section').style.display='block'; this.style.display='none';" class="btn btn-outline" style="width: 100%; font-size: 0.85rem; color: var(--danger); border-color: var(--danger); padding: 8px;">
                                ⚠️ Report Issue
                            </button>

                            <div id="report-section" style="display: none; text-align: left; background: var(--bg-detail-card); padding: 15px; border-radius: 8px; border: 1px solid var(--danger);">
                                <h4 style="color: var(--danger); margin-top: 0; margin-bottom: 10px; font-size: 0.95rem;">Submit Dispute</h4>
                                <form method="POST">
                                    <div style="margin-bottom: 10px;">
                                        <select name="issue_type" required style="width: 100%; padding: 8px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);">
                                            <option value="">Select an issue...</option>
                                            <option value="Fake Number">Fake/Wrong Number</option>
                                            <option value="Not Answering">Landlord Not Answering</option>
                                            <option value="Already Rented">Property Already Rented</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div style="margin-bottom: 10px;">
                                        <textarea name="description" rows="2" placeholder="Brief details..." required style="width: 100%; padding: 8px; font-size: 0.85rem; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-input); color: var(--text-main);"></textarea>
                                    </div>
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="submit_report" class="btn" style="background: var(--danger); color: white; flex: 1; padding: 6px; font-size: 0.85rem;">Submit</button>
                                        <button type="button" onclick="document.getElementById('report-section').style.display='none'; document.getElementById('btn-show-report').style.display='block';" class="btn btn-outline" style="flex: 1; padding: 6px; font-size: 0.85rem;">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php else: ?>
                <div style="text-align: center; filter: blur(0px);">
                    <p style="font-size: 1.5rem; font-weight: bold; color: var(--text-light); letter-spacing: 2px;">+91 98XXXX XXXX</p>
                </div>
                
                <form method="POST">
                    <button type="submit" name="unlock_contact" class="btn btn-accent" style="width: 100%; margin-top: 15px; font-size: 1.1rem;">
                        🔓 Unlock Contact (1 Credit)
                    </button>
                </form>
                
                <p style="font-size: 0.8rem; text-align: center; margin-top: 10px; color: var(--text-light);">
                    Instant access. 100% Refund if fake.
                </p>
            <?php endif; ?>

            <?php if ($user_role == 'student'): ?>
                <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 20px 0;">
                <div style="text-align: center;">
                    <?php $btn_class = $is_fav ? 'saved' : 'unsaved'; ?>
                    <a href="toggle_favorite.php?id=<?php echo $prop_id; ?>" class="btn-favorite <?php echo $btn_class; ?>">
                        <?php echo $is_fav ? '❤️ Saved to Favorites' : '🤍 Save to Favorites'; ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <div style="margin-top: 60px; margin-bottom: 60px;">
        <h3 style="margin-bottom: 20px;">Similar Homes in <?php echo htmlspecialchars($row['city']); ?></h3>
        <div class="grid-3">
            <?php
            $city = $conn->real_escape_string($row['city']);
            $ai_sql = "SELECT * FROM properties WHERE city LIKE '%$city%' AND prop_id != $prop_id LIMIT 3";
            $ai_result = $conn->query($ai_sql);

            if ($ai_result && $ai_result->num_rows > 0) {
                while($ai_row = $ai_result->fetch_assoc()) {
                    echo '
                    <div class="card">
                        <img src="uploads/'.htmlspecialchars($ai_row['image_main']).'" class="card-img" onerror="this.src=\'https://via.placeholder.com/400x300\'">
                        <div class="card-body">
                            <div class="card-title">'.htmlspecialchars($ai_row['title']).'</div>
                            <div class="card-info">₹'.$ai_row['rent'].'/mo • '.$ai_row['gender_type'].'</div>
                            
                            <a href="pg_details.php?id='.$ai_row['prop_id'].'" class="btn btn-primary stretched-link" style="width:100%">View</a>
                        
                        </div>
                    </div>';
                }
            } else {
                echo "<p>No similar properties found yet.</p>";
            }
            ?>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>