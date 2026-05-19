<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// JOIN properties and favorites table
// Assuming you have a 'favorites' table: (user_id, prop_id)
$sql = "SELECT p.*, u.subscription_plan 
        FROM properties p 
        JOIN favorites f ON p.prop_id = f.prop_id 
        JOIN users u ON p.landlord_id = u.user_id 
        WHERE f.user_id = $user_id";
$result = $conn->query($sql);

include 'header.php';
?>

<div class="container" style="padding-top: 40px;">
    <h1 style="color: var(--primary); margin-bottom: 30px;">❤️ Saved Properties</h1>

    <div class="grid-3">
        <?php if ($result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <div class="card">
                    <img src="uploads/<?php echo htmlspecialchars($row['image_main']); ?>" class="card-img" onerror="this.src='https://via.placeholder.com/400'">
                    <div class="card-body">
                        <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                        <div class="card-info">📍 <?php echo htmlspecialchars($row['city']); ?> • ₹<?php echo $row['rent']; ?></div>
                        
                        <div class="card-footer">
                            <a href="pg_details.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-primary">View</a>
                            <a href="toggle_favorite.php?id=<?php echo $row['prop_id']; ?>" class="btn btn-outline" style="color: red; border-color: red;">Remove ❌</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No saved properties yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?> 