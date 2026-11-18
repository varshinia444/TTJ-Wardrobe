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

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$msg_alert = "";

// --- FIREBASE FUNCTIONS REMOVED AS REQUESTED ---

// --- 3. HANDLE SENDING LOGIC ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_now'])) {
    
    // BACKEND SECURITY: Block viewers from sending notifications
    if ($is_viewer) {
        die("Error: Viewers do not have permission to send notifications.");
    }

    $type = mysqli_real_escape_string($conn, $_POST['type']);
    $audience = mysqli_real_escape_string($conn, $_POST['audience']);
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $message = mysqli_real_escape_string($conn, $_POST['message']);
    $image_url = mysqli_real_escape_string($conn, $_POST['image_url'] ?? "");

    // Count recipients
    if ($audience == 'All Customers') {
        $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users");
    } else {
        $tier = explode(' ', $audience)[0];
        $count_query = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE tier = '$tier'");
    }
    $count_data = mysqli_fetch_assoc($count_query);
    $recipients = $count_data['total'];

    // --- TRIGGER FCM REMOVED ---

    // Save to History
    $insert_sql = "INSERT INTO notifications (type, audience, title, message, recipients_count, status) 
                   VALUES ('$type', '$audience', '$title', '$message', '$recipients', 'Delivered')";
    
    if (mysqli_query($conn, $insert_sql)) {
        $msg_alert = "Notification details saved to database successfully for $recipients recipients!";
    }
}

// --- 4. FETCH ADMIN ALERTS COUNT ---
$alert_count_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$alert_data = mysqli_fetch_assoc($alert_count_query);
$unread_count = $alert_data['unread'] ?? 0;

$history_query = mysqli_query($conn, "SELECT * FROM notifications ORDER BY sent_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TTJ - Notifications</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      body { font-family: 'Inter', sans-serif; background-color: #f4f6f8; margin: 0; }
      .badge-gold { background: #e8f5e9; color: #28a745; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
      .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
      
      /* Bell Icon Styling */
      .bell-container { position: relative; margin-right: 20px; cursor: pointer; display: flex; align-items: center; }
      .bell-icon { color: #6327af; font-size: 28px; }
      .notification-badge { 
          position: absolute; top: -5px; right: -5px; 
          background: #ff3b30; color: white; border-radius: 50%; 
          padding: 2px 6px; font-size: 10px; font-weight: bold; 
          border: 2px solid white;
      }
      .user-info { display: flex; align-items: center; gap: 15px; }

      /* RED LOGOUT BUTTON STYLE (MATCHING SCREENSHOT) */
      .logout-btn-custom {
          background-color: #e74c3c;
          color: white !important;
          padding: 8px 20px;
          border-radius: 8px;
          text-decoration: none;
          font-weight: bold;
          font-size: 14px;
          border: none;
          cursor: pointer;
          transition: background 0.3s;
      }
      .logout-btn-custom:hover {
          background-color: #c0392b;
      }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    <main class="main-content">
        <div class="top-bar container-constrain" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 30px; background: #fff; border-bottom: 1px solid #eee;">
            <h1 style="color:#6327af; margin: 0;">Send Notifications</h1>
            <div class="user-info">
                <div class="bell-container" onclick="window.location.href='admin_alerts_view.php'">
                    <span class="material-icons bell-icon">notifications</span>
                    <?php if($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </div>

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
                <div style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo $avatarLetter; ?>
                </div>
                <span style="font-weight: 600; color: #1e1b4b;">
                    <?php echo $displayName; ?>
                </span>
                
                <button class="logout-btn-custom" onclick="window.location.href='logout.php'">Logout</button>
            </div>
        </div>

        <section class="content-section container-constrain">
            <?php if($msg_alert != "") echo "<div class='alert-success'>$msg_alert</div>"; ?>
            
            <?php if(!$is_viewer): ?>
            <div class="section-header"><h2>Compose Message</h2></div>
            <form method="POST">
                <div class="form-grid" style="display:flex; gap:20px;">
                    <div style="flex:1;">
                        <label>Notification Type</label>
                        <select name="type" class="form-control" required>
                            <option value="Promotional">Promotional</option>
                            <option value="Point Expiry Alert">Point Expiry Alert</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Target Audience</label>
                        <select name="audience" class="form-control" required>
                            <option value="All Customers">All Customers</option>
                            <option value="Silver Tier">Silver Tier</option>
                        </select>
                    </div>
                </div>
                <div style="margin-top:20px;">
                    <label>Notification Title</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter title" required>
                </div>
                <div style="margin-top:20px;">
                    <label>Image URL (Optional)</label>
                    <input type="url" name="image_url" class="form-control" placeholder="https://example.com/image.jpg">
                </div>
                <div style="margin-top:20px;">
                    <label>Message Body</label>
                    <textarea name="message" class="form-control" rows="4" required></textarea>
                </div>
                <div style="margin-top:20px;">
                    <button type="submit" name="send_now" class="btn btn-primary" style="background:#6327af;">Send Now</button>
                </div>
            </form>
            <?php endif; ?>

            <div class="table-container" style="margin-top:40px;">
                <h2>History</h2>
                <table>
                    <thead>
                        <tr><th>Date</th><th>Title</th><th>Type</th><th>Recipients</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($history_query)): ?>
                        <tr>
                            <td><?php echo date("M d, Y", strtotime($row['sent_at'])); ?></td>
                            <td style="color:#6327af; font-weight:600;"><?php echo htmlspecialchars($row['title']); ?></td>
                            <td><?php echo $row['type']; ?></td>
                            <td><?php echo $row['recipients_count']; ?></td>
                            <td><span class="badge-gold">Delivered</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</body>
</html>