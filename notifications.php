<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- LOGIC 1: HANDLE CLICK & REDIRECT ---
// If the user clicked a notification, we mark it as read and send them to the link
if (isset($_GET['read_id']) && isset($_GET['link'])) {
    $notif_id = intval($_GET['read_id']);
    $link = $_GET['link'];
    
    // Mark as read (Vanish from list)
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id AND user_id = $user_id");
    
    // Redirect to the destination (e.g., inbox.php or dashboard.php)
    header("Location: " . $link);
    exit;
}

// --- LOGIC 2: FETCH ONLY UNREAD NOTIFICATIONS ---
// This ensures they "vanish" once clicked
$sql = "SELECT * FROM notifications WHERE user_id = $user_id AND is_read = 0 ORDER BY created_at DESC";
$result = $conn->query($sql);

include 'header.php';
?>

<div class="container" style="padding: 40px 20px; min-height: 60vh;">
    
    <div style="max-width: 700px; margin: 0 auto;">
        <h2 style="color: var(--primary); margin-bottom: 25px; border-bottom: 2px solid var(--border-color); padding-bottom: 10px;">
            🔔 New Notifications
        </h2>

        <?php if ($result && $result->num_rows > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php while($row = $result->fetch_assoc()): ?>
                    
                    <?php 
                        // Style settings
                        $bg = "var(--bg-card)";
                        $border = "var(--border-color)";
                        $icon = "ℹ️";
                        
                        if($row['type'] == 'success') { $icon = "✅"; $border = "#28a745"; }
                        if($row['type'] == 'warning') { $icon = "⚠️"; $border = "#ffc107"; }
                        if($row['type'] == 'danger') { $icon = "❌"; $border = "#dc3545"; }
                    ?>

                    <a href="notifications.php?read_id=<?php echo $row['id']; ?>&link=<?php echo urlencode($row['link']); ?>" 
                       style="text-decoration: none; color: inherit; display: block;">
                        
                        <div style="background: <?php echo $bg; ?>; padding: 15px; border-radius: 8px; border-left: 5px solid <?php echo $border; ?>; box-shadow: var(--shadow); display: flex; gap: 15px; align-items: center; transition: transform 0.2s;">
                            
                            <div style="font-size: 1.5rem;"><?php echo $icon; ?></div>
                            
                            <div style="flex: 1;">
                                <p style="margin: 0; color: var(--text-main); font-size: 1rem; font-weight: 500;">
                                    <?php echo htmlspecialchars($row['message']); ?>
                                </p>
                                <small style="color: var(--text-light); font-size: 0.8rem;">
                                    <?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?> • Click to view
                                </small>
                            </div>

                        </div>
                    </a>

                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: var(--text-light); border: 2px dashed var(--border-color); border-radius: 10px;">
                <h3 style="margin-bottom: 10px;">All Caught Up!</h3>
                <p>No new notifications.</p>
                <a href="index.php" class="btn btn-outline" style="margin-top: 10px;">Go Home</a>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include 'footer.php'; ?>