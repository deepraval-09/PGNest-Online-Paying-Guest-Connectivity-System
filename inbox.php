<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// --- FIX: FETCH LATEST CONVERSATIONS ---
// ERROR FIX: Changed 'id' to 'msg_id' (common naming difference)
// If this still fails, please check your database to see if the column is 'id', 'msg_id', or 'message_id'

$sql = "SELECT m.*, u.full_name, u.email 
        FROM messages m 
        JOIN (
            SELECT MAX(msg_id) as latest_msg_id
            FROM messages
            WHERE sender_id = $user_id OR receiver_id = $user_id
            GROUP BY CASE 
                WHEN sender_id = $user_id THEN receiver_id 
                ELSE sender_id 
            END
        ) as sub ON m.msg_id = sub.latest_msg_id
        JOIN users u ON (m.sender_id = u.user_id OR m.receiver_id = u.user_id)
        WHERE u.user_id != $user_id
        ORDER BY m.created_at DESC";

$result = $conn->query($sql);

include 'header.php';
?>

<style>
    /* Default Light Mode */
    .unread-msg {
        background-color: #e6fffa;
        font-weight: bold;
    }
    
    /* Dark Mode Override */
    body.dark-mode .unread-msg {
        background-color: rgba(64, 224, 208, 0.15) !important; /* Dark Teal Transparent */
        color: #fff !important;
    }
    
    /* Ensure table text adapts */
    body.dark-mode table thead {
        background-color: #333 !important;
        color: #fff !important;
    }
</style>

<div class="container" style="padding-top: 40px; padding-bottom: 60px;">
    
    <div class="dashboard-header">
        <h1 style="color: var(--primary);">My Inbox</h1>
    </div>

    <div class="card" style="padding: 0; overflow: hidden;">
        <?php if ($result && $result->num_rows > 0): ?>
            <table style="margin-top: 0;">
                <thead style="background: #f1f1f1; color: #333;">
                    <tr>
                        <th style="padding: 20px;">User</th>
                        <th>Last Message</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php 
                            // Identify the other person
                            $other_person_id = ($row['sender_id'] == $user_id) ? $row['receiver_id'] : $row['sender_id'];
                            $is_read = $row['is_read']; 
                            
                            // Check if this row is unread for ME
                            $is_unread_for_me = ($is_read == 0 && $row['receiver_id'] == $user_id);
                        ?>
                        
                        <tr class="<?php echo $is_unread_for_me ? 'unread-msg' : ''; ?>">
                            <td>
                                <div style="font-weight: bold; color: var(--primary);">
                                    <?php echo htmlspecialchars($row['full_name']); ?>
                                </div>
                                <div style="font-size: 0.8rem; opacity: 0.8;"><?php echo htmlspecialchars($row['email']); ?></div>
                            </td>
                            <td>
                                <?php 
                                    // Highlight text if unread
                                    $msg_preview = htmlspecialchars(substr($row['message'], 0, 50)) . (strlen($row['message']) > 50 ? '...' : '');
                                    echo $is_unread_for_me ? "<strong>$msg_preview</strong>" : $msg_preview;
                                ?>
                            </td>
                            <td><?php echo date('d M, h:i A', strtotime($row['created_at'])); ?></td>
                            <td>
                                <a href="chat_view.php?user_id=<?php echo $other_person_id; ?>" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.85rem;">
                                    Reply 💬
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div style="text-align: center; padding: 50px;">
                <h3 style="color: #666;">No messages yet.</h3>
                <p>Unlock a contact to start chatting!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>