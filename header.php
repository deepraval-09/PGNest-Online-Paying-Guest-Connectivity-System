<?php 
// 1. START SESSION SAFELY
if (session_status() === PHP_SESSION_NONE) { session_start(); } 

// 2. INCLUDE DB FOR NOTIFICATIONS CHECK (New Addition)
include_once 'db.php'; 

// 3. CHECK FOR UNREAD NOTIFICATIONS
$notif_count = 0;
if (isset($_SESSION['user_id'])) {
    $uid_check = $_SESSION['user_id'];
    // Count unread notifications for this user
    $n_sql = "SELECT COUNT(*) as c FROM notifications WHERE user_id = $uid_check AND is_read = 0";
    if(isset($conn)) { // Safety check if db connection exists
        $n_res = $conn->query($n_sql);
        if ($n_res) {
            $notif_count = $n_res->fetch_assoc()['c'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>PGNest | Zero Brokerage PG Finder</title>
  
  <link rel="stylesheet" href="style.css">
  
  <style>
    body { font-family: 'Segoe UI', 'Roboto', 'Helvetica Neue', sans-serif; }
    
    /* FIX: Professional Login Button */
    .btn-login {
        background-color: transparent;
        border: 2px solid var(--primary);
        color: var(--primary);
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-block;
    }
    
    .btn-login:hover {
        background-color: var(--primary) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        text-decoration: none;
    }

    /* Professional Logout Button */
    .btn-logout {
        background-color: transparent;
        border: 2px solid #dc3545;
        color: #dc3545;
        padding: 8px 20px;
        border-radius: 6px;
        font-weight: 600;
        text-decoration: none;
        transition: all 0.3s ease;
        display: inline-block;
    }
    
    .btn-logout:hover {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        box-shadow: 0 4px 6px rgba(220, 53, 69, 0.2);
        text-decoration: none;
    }

    /* --- NEW NOTIFICATION STYLES --- */
    .notif-btn {
        position: relative;
        font-size: 1.3rem; /* Size of the bell */
        margin-right: 15px; /* Space between bell and logout */
        text-decoration: none;
        color: inherit; 
        cursor: pointer;
        display: flex;
        align-items: center;
    }
    
    /* Dark Mode Adjustment for Bell Color */
    body:not(.dark-mode) .notif-btn { color: #333; }
    body.dark-mode .notif-btn { color: #eee; }

    .notif-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        background-color: #dc3545; /* Red Alert */
        color: white;
        font-size: 0.7rem;
        font-weight: bold;
        padding: 2px 5px;
        border-radius: 50%;
        min-width: 18px;
        text-align: center;
        line-height: 1;
        border: 2px solid var(--bg-body, #fff); /* Small border to separate from bell */
    }
  </style>
</head>
<body>

<header class="navbar">
  <div class="nav-container">
      
      <a class="brand" href="index.php">
          <img src="l2.png" alt="PGNest Logo" onerror="this.onerror=null; this.src='l2.jpg';"> 
          <span>PGNest</span>
      </a>

      <nav class="nav-links" style="display: flex; align-items: center;">
          
          <?php 
            // DETERMINE USER ROLE
            // Default to 'guest' if not logged in
            $current_role = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'guest';
          ?>

          <?php if ($current_role == 'student' || $current_role == 'guest'): ?>
              <a href="index.php">Home</a>
              <a href="listings.php">Browse PGs</a>
          <?php endif; ?>
          
          <?php if (isset($_SESSION['user_id'])): ?>
              
              <?php if ($current_role == 'landlord'): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="inbox.php">Messages</a>
              
              <?php elseif ($current_role == 'student'): ?>
                <a href="my_account.php">My Account</a>
                <a href="inbox.php">Inbox</a>
              
              <?php elseif ($current_role == 'admin'): ?>
                <a href="admin_panel.php">Admin Panel</a>
              <?php endif; ?>
              
              <a href="notifications.php" class="notif-btn" title="Notifications">
                  <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                      <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                  </svg>
                  
                  <?php if ($notif_count > 0): ?>
                      <span class="notif-badge"><?php echo $notif_count; ?></span>
                  <?php endif; ?>
              </a>

              <a href="logout.php" class="btn-logout">Logout</a>
              
          <?php else: ?>
              <a href="login.php" class="btn-login">Login</a>
          <?php endif; ?>

          <button id="theme-toggle" class="theme-btn" title="Toggle Dark Mode" style="margin-left: 10px;">
              🌙
          </button>
      </nav>
  </div>
</header>

<script>
    const toggleBtn = document.getElementById('theme-toggle');
    const body = document.body;

    if (localStorage.getItem('theme') === 'dark') {
        body.classList.add('dark-mode');
        toggleBtn.textContent = '☀️';
    }

    toggleBtn.addEventListener('click', () => {
        body.classList.toggle('dark-mode');

        if (body.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            toggleBtn.textContent = '☀️';
        } else {
            localStorage.setItem('theme', 'light');
            toggleBtn.textContent = '🌙';
        }
    });
</script>

<main>