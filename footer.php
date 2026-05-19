</main> 

<footer class="footer">
    <div class="container" style="text-align: center;">
        <p style="font-weight: bold; color: white; margin-bottom: 10px;">&copy; <?php echo date('Y'); ?> PGNest</p>
        
        <p style="font-size: 0.9rem; opacity: 0.7;">
            Zero Brokerage. Verified PGs. Safe Stays.
        </p>
        
        <div style="margin-top: 20px; font-size: 0.8rem;">
            <?php 
                // Determine user role for smart footer links
                $footer_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
            ?>

            <?php if ($footer_role == 'admin'): ?>
                <a href="admin_panel.php" style="margin: 0 10px;">Admin Control Panel</a>
                <span style="opacity: 0.3;">|</span>
                <a href="logout.php" style="margin: 0 10px;">Logout</a>

            <?php elseif ($footer_role == 'landlord'): ?>
                <a href="dashboard.php" style="margin: 0 10px;">My Dashboard</a>
                <span style="opacity: 0.3;">|</span>
                <a href="add_pg.php" style="margin: 0 10px;">Add Property</a>
                <span style="opacity: 0.3;">|</span>
                <a href="inbox.php" style="margin: 0 10px;">Inbox</a>

            <?php elseif ($footer_role == 'student'): ?>
                <a href="index.php" style="margin: 0 10px;">Home</a>
                <span style="opacity: 0.3;">|</span>
                <a href="listings.php" style="margin: 0 10px;">Browse PGs</a>
                <span style="opacity: 0.3;">|</span>
                <a href="my_account.php" style="margin: 0 10px;">My Account</a>

            <?php else: ?>
                <a href="index.php" style="margin: 0 10px;">Home</a>
                <span style="opacity: 0.3;">|</span>
                <a href="listings.php" style="margin: 0 10px;">Browse PGs</a>
                <span style="opacity: 0.3;">|</span>
                <a href="login.php" style="margin: 0 10px;">Login</a>
            <?php endif; ?>
        </div>
    </div>
</footer>

</body>
</html>