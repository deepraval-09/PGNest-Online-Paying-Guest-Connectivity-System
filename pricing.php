<?php
include 'header.php';
include 'db.php';

// Check if user is logged in
$user_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
?>

<div class="container" style="padding-top: 50px; padding-bottom: 80px;">
    
    <div style="text-align: center; max-width: 700px; margin: 0 auto 50px;">
        <h1 style="color: var(--primary); margin-bottom: 10px;">Simple, Transparent Pricing</h1>
        <p style="color: #666; font-size: 1.1rem;">
            Whether you are finding a home or listing one, we have the perfect plan for you.
        </p>
    </div>

    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 40px; align-items: flex-start;">

        <div class="card" style="width: 350px; border: 2px solid <?php echo ($user_role == 'student' ? 'var(--primary)' : '#eee'); ?>; position: relative; margin-top: 20px;">
            
            <?php if($user_role == 'student'): ?>
                <div style="background: var(--primary); color: white; text-align: center; padding: 5px; font-weight: bold; border-radius: 4px 4px 0 0;">
                    Recommended for You
                </div>
            <?php endif; ?>

            <div style="padding: 30px; text-align: center;">
                <h3 style="color: var(--text-light); margin-bottom: 10px;">The Nest Pass</h3>
                <div style="font-size: 3rem; font-weight: 800; color: var(--primary); margin-bottom: 10px;">
                    ₹99
                </div>
                <p style="margin-bottom: 30px; color: #666;">One-time payment</p>

                <ul style="text-align: left; margin-bottom: 30px; padding-left: 20px; list-style: none;">
                    <li style="margin-bottom: 10px;">🔓 <strong>10 Contact Unlocks</strong></li>
                    <li style="margin-bottom: 10px;">✅ Valid for 30 Days</li>
                    <li style="margin-bottom: 10px;">🛡️ 100% Refund if Fake</li>
                    <li style="margin-bottom: 10px;">📞 24/7 Support</li>
                </ul>

                <?php if($user_role == 'landlord'): ?>
                    <button class="btn btn-outline" disabled style="width: 100%; opacity: 0.5; cursor: not-allowed;">For Students Only</button>
                <?php else: ?>
                    <a href="payment_gateway.php?plan=nest_pass&amount=99" class="btn btn-primary" style="width: 100%;">Get Nest Pass</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="card premium-glow" style="width: 350px; position: relative; overflow: visible; margin-top: 20px; border: 2px solid #e91e63;">
            
            <span class="badge badge-premium" style="position: absolute; top: -15px; left: 50%; transform: translateX(-50%); z-index: 10; padding: 5px 20px; font-size: 0.9rem; box-shadow: 0 4px 10px rgba(233, 30, 99, 0.3);">
                MOST POPULAR
            </span>

            <div style="padding: 40px 30px 30px; text-align: center;">
                <h3 style="color: var(--text-light); margin-bottom: 10px;">Premium Host</h3>
                <div style="font-size: 3rem; font-weight: 800; color: #e91e63; margin-bottom: 10px;">
                    ₹499
                </div>
                <p style="margin-bottom: 30px; color: #666;">Per Month</p>

                <ul style="text-align: left; margin-bottom: 30px; padding-left: 20px; list-style: none;">
                    <li style="margin-bottom: 10px;">🚀 <strong>Top Rank in Search</strong></li>
                    <li style="margin-bottom: 10px;">✨ Gold Glow on Cards</li>
                    <li style="margin-bottom: 10px;">📈 5x More Views</li>
                    <li style="margin-bottom: 10px;">🏷️ "Verified" Priority</li>
                </ul>

                <?php if($user_role == 'student'): ?>
                    <button class="btn btn-outline" disabled style="width: 100%; opacity: 0.5; cursor: not-allowed;">For Landlords Only</button>
                <?php else: ?>
                    <a href="payment_gateway.php?plan=premium&amount=499" class="btn btn-accent" style="width: 100%; background-color: #e91e63; border: none;">Go Premium</a>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include 'footer.php'; ?>