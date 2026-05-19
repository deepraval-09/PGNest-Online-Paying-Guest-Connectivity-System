<?php
session_start();
include 'db.php';

// 1. SECURITY CHECK
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['user_id'])) {
    header("Location: inbox.php");
    exit;
}

$me_id = $_SESSION['user_id'];
$other_id = intval($_GET['user_id']);

// 2. GET OTHER USER DETAILS (Name & Role)
$user_sql = "SELECT full_name, role FROM users WHERE user_id = $other_id";
$user_res = $conn->query($user_sql);
if ($user_res->num_rows == 0) { die("User not found."); }
$other_user = $user_res->fetch_assoc();

// --- 2.5 REVENUE LEAK SECURITY PATCH (GATEKEEPER) ---
$me_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
$other_role = $other_user['role'];

// Protect Landlord: If Student is trying to chat with a Landlord
if ($me_role == 'student' && $other_role == 'landlord') {
    // Did this student unlock ANY property owned by this landlord?
    $unlock_check = $conn->query("SELECT un.id FROM unlocks un JOIN properties p ON un.prop_id = p.prop_id WHERE un.student_id = $me_id AND p.landlord_id = $other_id");
    if ($unlock_check->num_rows == 0) {
        echo "<script>alert('⛔ Unauthorized! You must use 1 Credit to unlock this landlord\'s contact before chatting.'); window.location.href='listings.php';</script>";
        exit;
    }
}

// Protect Student: If Landlord is trying to chat with a Student
if ($me_role == 'landlord' && $other_role == 'student') {
    // Did this student unlock ANY property owned by this landlord?
    $unlock_check = $conn->query("SELECT un.id FROM unlocks un JOIN properties p ON un.prop_id = p.prop_id WHERE un.student_id = $other_id AND p.landlord_id = $me_id");
    if ($unlock_check->num_rows == 0) {
        echo "<script>alert('⛔ Unauthorized! This student has not unlocked your properties.'); window.location.href='dashboard.php';</script>";
        exit;
    }
}
// ------------------------------------------------------

// 3. MARK MESSAGES AS READ
$conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $other_id AND receiver_id = $me_id");

// 4. FETCH CONVERSATION HISTORY
$sql = "SELECT * FROM messages 
        WHERE (sender_id = $me_id AND receiver_id = $other_id) 
        OR (sender_id = $other_id AND receiver_id = $me_id) 
        ORDER BY created_at ASC";
$result = $conn->query($sql);

include 'header.php';
?>

<div class="container" style="padding-top: 20px; padding-bottom: 20px;">
    
    <div style="background: var(--bg-card); padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; border-radius: 8px 8px 0 0;">
        <a href="inbox.php" class="btn btn-outline" style="border: none; font-size: 1.2rem;">&larr;</a>
        <div>
            <h3 style="margin: 0; color: var(--primary);"><?php echo htmlspecialchars($other_user['full_name']); ?></h3>
            <span style="font-size: 0.8rem; color: #888; text-transform: capitalize;"><?php echo $other_user['role']; ?></span>
        </div>
    </div>

    <div style="background: var(--bg-chat-area); height: 500px; overflow-y: auto; padding: 20px; border: 1px solid var(--border-color); border-top: none; display: flex; flex-direction: column; gap: 10px;">
        
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                
                <?php 
                    $is_me = ($row['sender_id'] == $me_id);
                ?>

                <div style="display: flex; justify-content: <?php echo $is_me ? 'flex-end' : 'flex-start'; ?>;">
                    <div style="
                        max-width: 70%; 
                        padding: 10px 15px; 
                        border-radius: 15px; 
                        font-size: 0.95rem; 
                        position: relative;
                        /* Updated Message Colors */
                        background-color: <?php echo $is_me ? 'var(--primary)' : 'var(--bg-msg-received)'; ?>; 
                        color: <?php echo $is_me ? 'white' : 'var(--text-msg-received)'; ?>;
                        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
                        border-bottom-<?php echo $is_me ? 'right' : 'left'; ?>-radius: 0;
                    ">
                        <?php echo htmlspecialchars($row['message']); ?>
                        
                        <div style="font-size: 0.7rem; opacity: 0.7; margin-top: 5px; text-align: right;">
                            <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                        </div>
                    </div>
                </div>

            <?php endwhile; ?>
        <?php else: ?>
            <div style="text-align: center; color: #999; margin-top: 50px;">
                <p>No messages yet. Say hello! 👋</p>
            </div>
        <?php endif; ?>
        
        <div id="bottom"></div>
    </div>

    <div style="background: var(--bg-card); padding: 15px; border: 1px solid var(--border-color); border-top: none; border-radius: 0 0 8px 8px;">
        <form action="send_message.php" method="POST" style="display: flex; gap: 10px;">
            <input type="hidden" name="receiver_id" value="<?php echo $other_id; ?>">
            
            <input type="text" name="message" placeholder="Type a message..." required autocomplete="off" 
                   style="flex: 1; padding: 12px; border: 1px solid var(--border-color); border-radius: 30px; outline: none; background: var(--bg-input); color: var(--text-main);">
            
            <button type="submit" class="btn btn-primary" style="border-radius: 50%; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; padding: 0;">
                ➤
            </button>
        </form>
    </div>

</div>

<script>
    window.onload = function() {
        document.getElementById('bottom').scrollIntoView();
    }
</script>

<?php include 'footer.php'; ?>