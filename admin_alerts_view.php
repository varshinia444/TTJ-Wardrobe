<?php
session_start();
include('db_connect.php');

// --- NEW AUTH GUARD (Allows both Admin and Viewer) ---
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

if (!$is_admin && !$is_viewer) {
    header("Location: index.php");
    exit;
}

// Mark as read logic
if(isset($_GET['mark_read'])) {
    
    // BACKEND SECURITY: Block viewers from marking alerts as read
    if ($is_viewer) {
        die("Error: Viewers do not have permission to mark alerts as read.");
    }

    mysqli_query($conn, "UPDATE admin_alerts SET is_read = 1 WHERE is_read = 0");
    header("Location: admin_alerts_view.php");
    exit;
}

// Search Logic
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where = $search ? " WHERE user_name LIKE '%$search%' OR user_id LIKE '%$search%' " : "";

$alerts_query = mysqli_query($conn, "SELECT * FROM admin_alerts $where ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Notifications</title>
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      body { font-family: 'Inter', sans-serif; background-color: #f4f6f8; margin: 0; }
      
      .dashboard-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          background: #fff;
          padding: 15px 30px;
          border-bottom: 1px solid #eee;
      }

      .header-right {
          display: flex;
          align-items: center;
          gap: 20px;
      }

      .avatar-circle {
          background-color: #5D1F88;
          color: white;
          width: 35px;
          height: 35px;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          font-weight: bold;
          font-size: 14px;
      }

      .logout-btn-custom {
          background-color: #e74c3c;
          color: white !important;
          padding: 8px 20px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: bold;
          font-size: 14px;
          transition: 0.3s;
      }

      .logout-btn-custom:hover {
          background-color: #c0392b;
      }

      .alert-card { background: white; border-radius: 12px; padding: 20px; margin-bottom: 15px; border-left: 5px solid #6327af; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
      .alert-unread { background: #f3e5f5; border-left: 5px solid #ff3b30; }
      .weight-badge { padding: 6px 12px; border-radius: 8px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; margin-top: 10px; font-size: 13px; }
      .badge-total { background: #e3f2fd; border: 1px solid #1976d2; color: #0d47a1; }
      .badge-pending { background: #fff3e0; border: 1px solid #ef6c00; color: #e65100; margin-left: 10px; }
      
      /* GOLD WALLET SPECIFIC BADGE */
      .badge-wallet { background: #e8f5e9; border: 1px solid #2e7d32; color: #1b5e20; }
      
      .amount-tag { color: #2e7d32; font-weight: bold; font-size: 22px; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    <main class="main-content">
        <header class="dashboard-header">
            <div class="header-left">
                <h1 style="color: #1e1b4b; margin:0;">Admin Notifications</h1>
            </div>
            
            <div class="header-right">
                <div style="display:flex; gap:10px; align-items:center; margin-right: 20px;">
                    <form method="GET">
                        <input type="text" name="search" placeholder="Search User..." class="search-box" style="padding:8px; border-radius:5px; border:1px solid #ddd;" value="<?php echo htmlspecialchars($search); ?>">
                    </form>
                    
                    <?php if(!$is_viewer): ?>
                        <a href="admin_alerts_view.php?mark_read=1" class="action-btn" style="background:#ff3b30; color:white; padding:10px; border-radius:5px; text-decoration:none; font-size: 12px; font-weight: bold;">Mark All as Read</a>
                    <?php endif; ?>
                </div>

                <div style="display: flex; align-items: center; gap: 10px;">
                    <?php
                    // Dynamic Header Avatar Logic
                    $displayName = "Super Admin";
                    $avatarLetter = "A";

                    if ($is_viewer) {
                        $displayName = "Viewer"; 
                        $avatarLetter = "V";
                    } elseif (isset($_SESSION['admin_username'])) {
                        $displayName = htmlspecialchars($_SESSION['admin_username']);
                        $avatarLetter = strtoupper(substr($displayName, 0, 1)); 
                    }
                    ?>
                    <div class="avatar-circle"><?php echo $avatarLetter; ?></div>
                    <span style="font-weight: 600; color: #1e1b4b; font-size: 14px;"><?php echo $displayName; ?></span>
                </div>
                
                <a href="logout.php" class="logout-btn-custom" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
            </div>
        </header>

        <section class="content-section container-constrain" style="padding: 20px;">
            <?php while($row = mysqli_fetch_assoc($alerts_query)): ?>
                <div class="alert-card <?php echo $row['is_read'] == 0 ? 'alert-unread' : ''; ?>">
                    <div>
                        <h3 style="margin:0;"><?php echo htmlspecialchars($row['user_name']); ?> <span style="font-weight:400; font-size:14px;">(ID: <?php echo $row['user_id']; ?>)</span></h3>
                        <p style="margin:5px 0; color:#6327af; font-weight:600;">Action: <?php echo $row['action_type']; ?></p>
                        
                        <?php if($row['action_type'] == 'Gold Wallet Purchase' || $row['action_type'] == 'Gold Purchased via App'): ?>
                            <div class="weight-badge badge-wallet">
                                <span class="material-icons" style="font-size:16px;">account_balance_wallet</span>
                                Purchased Grams: <?php echo number_format($row['target_weight'], 3); ?> g
                            </div>
                        <?php else: ?>
                            <div class="weight-badge badge-total">
                                <span class="material-icons" style="font-size:16px;">ads_click</span>
                                Total Grams: <?php echo number_format($row['target_weight'], 3); ?> g
                            </div>
                            <div class="weight-badge badge-pending">
                                <span class="material-icons" style="font-size:16px;">history</span>
                                Pending: <?php echo number_format($row['pending_grams'], 3); ?> g
                            </div>
                        <?php endif; ?>

                        <p style="color:#888; font-size:12px; margin-top:10px;"><?php echo date("M d, Y - h:i A", strtotime($row['created_at'])); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:12px; color:#888;">Amount Paid</div>
                        <div class="amount-tag">₹ <?php echo number_format($row['amount'], 2); ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </section>
    </main>
</body>
</html>