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

// --- 1. HANDLE ADD NEW PLAN ---
if (isset($_POST['save_plan'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to create plans.");
    }

    $name = mysqli_real_escape_string($conn, $_POST['plan_name']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);
    $benefit = mysqli_real_escape_string($conn, $_POST['benefit']);
    $penalty = mysqli_real_escape_string($conn, $_POST['penalty']);
    $min_amt = mysqli_real_escape_string($conn, $_POST['min_amount']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']); 
    $color = mysqli_real_escape_string($conn, $_POST['border_color']);

    $sql = "INSERT INTO scheme_plans (plan_name, duration, benefit, penalty, min_amount, description, border_color, status) 
            VALUES ('$name', '$duration', '$benefit', '$penalty', '$min_amt', '$desc', '$color', 'Active')";
            
    if (mysqli_query($conn, $sql)) {
        $msg = "New Scheme Plan Created Successfully!";
    } else {
        $error = "Error: " . mysqli_error($conn);
    }
}

// --- 2. HANDLE DELETE PLAN ---
if (isset($_GET['delete_id'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to delete plans.");
    }

    $del_id = intval($_GET['delete_id']);
    $check_sql = "SELECT COUNT(*) as count FROM scheme_enrollments WHERE plan_id = $del_id";
    $check_res = mysqli_query($conn, $check_sql);
    $check_data = mysqli_fetch_assoc($check_res);

    if ($check_data['count'] > 0) {
        mysqli_query($conn, "UPDATE scheme_plans SET status = 'Inactive' WHERE id = $del_id");
        $msg = "Plan has active users. It has been marked as 'Inactive'.";
    } else {
        mysqli_query($conn, "DELETE FROM scheme_plans WHERE id = $del_id");
        $msg = "Plan deleted successfully.";
    }
}

// --- 3. HANDLE DROPDOWN ACTIONS (SAVE TO DATABASE) ---
if (isset($_GET['action']) && isset($_GET['enroll_id'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to modify enrollments.");
    }

    $eid = intval($_GET['enroll_id']);
    $action = $_GET['action'];

    if ($action == 'redemption') {
        // FIXED: Set status to 'Completed' so the badge shows correctly
        $update_sql = "UPDATE scheme_enrollments SET status = 'Completed' WHERE id = $eid";
        if(mysqli_query($conn, $update_sql)) {
            $msg = "Success: Scheme Redeemed! This user's dashboard section will now be hidden.";
        }
    } elseif ($action == 'cancel') {
        $update_sql = "UPDATE scheme_enrollments SET status = 'Cancelled' WHERE id = $eid";
        if(mysqli_query($conn, $update_sql)) {
            $msg = "Success: Enrollment Cancelled in Database.";
        }
    } elseif ($action == 'block') {
        $update_sql = "UPDATE scheme_enrollments SET status = 'Blocked' WHERE id = $eid";
        if(mysqli_query($conn, $update_sql)) {
            $msg = "Success: User Blocked in Database.";
        }
    } elseif ($action == 'unblock') {
        $update_sql = "UPDATE scheme_enrollments SET status = 'Active' WHERE id = $eid";
        if(mysqli_query($conn, $update_sql)) {
            $msg = "Success: User Unblocked in Database.";
        }
    }
}

// --- 4. DATA FETCHING ---
$plans_result = mysqli_query($conn, "SELECT p.*, (SELECT COUNT(*) FROM scheme_enrollments e WHERE e.plan_id = p.id) as enroll_count FROM scheme_plans p ORDER BY p.id DESC");

$enroll_sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.mobile as user_mobile, p.plan_name 
               FROM scheme_enrollments e 
               JOIN users u ON e.user_id = u.id 
               JOIN scheme_plans p ON e.plan_id = p.id 
               ORDER BY e.start_date DESC";
$enroll_result = mysqli_query($conn, $enroll_sql);

// --- 5. HISTORY VIEW ---
$view_user_id = isset($_GET['view_history']) ? mysqli_real_escape_string($conn, $_GET['view_history']) : null;
$target_user = null;
if ($view_user_id) {
    $history_sql = "SELECT name, total_amount_paid, gold_earned, last_payment_date, status FROM scheme_enrollments WHERE user_id = '$view_user_id'";
    $history_result = mysqli_query($conn, $history_sql);
    $target_user = mysqli_fetch_assoc($history_result);
}

// --- 6. ALERTS ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TTJ - Scheme Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }
      .modal-content { background: #fff; margin: 3% auto; padding: 0; border-radius: 16px; width: 600px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
      .modal-header { padding: 25px; border-bottom: 1px solid #eee; }
      .modal-header h2 { color: #6327af; font-size: 24px; margin: 0; font-weight: 700; }
      .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
      .form-group { margin-bottom: 15px; }
      .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
      .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: 'Inter', sans-serif; }
      .alert-box { padding: 15px; margin-bottom: 20px; border-radius: 8px; transition: opacity 0.5s ease; }
      .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
      .manage-select { padding: 5px; border-radius: 4px; border: 1px solid #ddd; font-size: 12px; cursor: pointer; color: #6327af; font-weight: bold; outline: none; background: #fff; }
      
      /* Standard Logout Styling */
      .logout-btn-header { background: #dc3545; color: white; padding: 6px 14px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: bold; border: none; cursor: pointer; display: inline-block; margin-left: 10px; }
      .logout-btn-header:hover { background: #c62828; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Scheme Management</h1>
        <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
            <div class="bell-container" onclick="window.location.href='admin_alerts_view.php'" style="cursor:pointer; position:relative; margin-right:10px;">
                <span class="material-icons" style="color: #6327af; font-size: 28px;">notifications</span>
                <?php if($header_unread_count > 0): ?> <span class="notification-dot" style="position: absolute; top: 0; right: 0; height: 10px; width: 10px; background: #ff3b30; border-radius: 50%; border: 2px solid white;"></span> <?php endif; ?>
            </div>
            
            <?php
            // Dynamic Header Avatar Logic
            $displayName = "Super Admin";
            $avatarLetter = "A";

            if ($is_viewer) {
                $displayName = "Viewer"; // Just "Viewer"
                $avatarLetter = "V";
            } elseif (isset($_SESSION['admin_username'])) {
                $displayName = htmlspecialchars($_SESSION['admin_username']);
                $avatarLetter = strtoupper(substr($displayName, 0, 1)); 
            }
            ?>
            <div class="user-avatar" style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo $avatarLetter; ?>
            </div>
            <span style="font-weight: 600; color: #333;">
                <?php echo $displayName; ?>
            </span>
            
            <a href="?action=logout" class="logout-btn-header">Logout</a>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if(isset($msg)): ?>
            <div id="status-msg" class="alert-box alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
            <div id="error-msg" class="alert-box" style="background:#f8d7da; color:#721c24; border:1px solid #f5c6cb;"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if($view_user_id): ?>
            <div class="section-header" style="margin-top: 30px;">
                <h2>History: <?php echo htmlspecialchars($target_user['name']); ?></h2>
                <button class="btn btn-primary" onclick="window.location.href='schemes.php'">Back</button>
            </div>
            <div class="table-container">
              <table>
                <thead><tr><th>Date</th><th>Type</th><th>Grams</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                    <tr>
                        <td><?php echo date("M d, Y", strtotime($target_user['last_payment_date'])); ?></td>
                        <td style="color:#5D1F88; font-weight:600;">Scheme Credit</td>
                        <td><strong style="color:#d4af37;"><?php echo number_format($target_user['gold_earned'], 3); ?> g</strong></td>
                        <td>₹<?php echo number_format($target_user['total_amount_paid'], 2); ?></td>
                        <td><?php echo $target_user['status']; ?></td>
                    </tr>
                </tbody>
              </table>
            </div>

        <?php else: ?>
            <div class="section-header">
              <h2>Available Plans</h2>
              
              <?php if(!$is_viewer): ?>
                  <button class="btn btn-primary" onclick="openPlanModal()">+ Add New Plan</button>
              <?php endif; ?>
              
            </div>

            <div class="stats-grid" style="margin-top:12px; display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap:20px;">
              <?php while($plan = mysqli_fetch_assoc($plans_result)): ?>
              <div class="stat-card" style="border-left: 6px solid <?php echo $plan['border_color']; ?>; position:relative;">
                
                <?php if(!$is_viewer): ?>
                    <a href="?delete_id=<?php echo $plan['id']; ?>" onclick="return confirm('Inactivate this plan?');" style="position:absolute; top:15px; right:15px; color:#dc3545;"><span class="material-icons">delete</span></a>
                <?php endif; ?>
                
                <h3><?php echo htmlspecialchars($plan['plan_name']); ?></h3>
                <div class="stat-value" style="font-size: 18px; color:var(--primary);"><?php echo $plan['enroll_count']; ?> Enrolled</div>
              </div>
              <?php endwhile; ?>
            </div>

            <div class="section-header" style="margin-top: 40px;">
              <h2>Enrollment Records</h2>
            </div>

            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Customer</th>
                    <th>Plan Name</th>
                    <th>Maturity</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = mysqli_fetch_assoc($enroll_result)): ?>
                    <tr>
                        <td>
                            <div style="font-weight:700; color:#1e1b4b;"><?php echo htmlspecialchars($row['user_name']); ?></div>
                            <div style="font-size:12px; color:#666;"><?php echo htmlspecialchars($row['user_mobile']); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($row['plan_name']); ?></td>
                        <td><?php echo date("M d, Y", strtotime($row['maturity_date'])); ?></td>
                        <td>
                            <?php 
                                $st = $row['status'];
                                // FIXED BADGE COLOR: Completed status is now blue
                                $badgeColor = ($st == 'Active') ? '#e8f5e9' : (($st == 'Completed') ? '#e7f1ff' : '#ffebee');
                                $textColor = ($st == 'Active') ? '#28a745' : (($st == 'Completed') ? '#007bff' : '#dc3545');
                            ?>
                            <span class="badge" style="color:<?php echo $textColor; ?>; background:<?php echo $badgeColor; ?>; padding: 5px 10px; border-radius: 4px; font-weight: bold;"><?php echo $st; ?></span>
                        </td>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <a href="?view_history=<?php echo $row['user_id']; ?>" style="color:#6327af; font-weight:bold; font-size:12px;">HISTORY</a>
                                
                                <?php if(!$is_viewer): ?>
                                    <select class="manage-select" onchange="if(this.value) { if(confirm('Update status?')) window.location.href=this.value; }">
                                        <option value="">Manage</option>
                                        <?php if($st == 'Active'): ?>
                                            <option value="?action=redemption&enroll_id=<?php echo $row['id']; ?>">Redemption</option>
                                            <option value="?action=cancel&enroll_id=<?php echo $row['id']; ?>">Cancel</option>
                                            <option value="?action=block&enroll_id=<?php echo $row['id']; ?>">Block</option>
                                        <?php elseif($st == 'Blocked'): ?>
                                            <option value="?action=unblock&enroll_id=<?php echo $row['id']; ?>">Unblock</option>
                                        <?php else: ?>
                                            <option disabled>Finalized (<?php echo $st; ?>)</option>
                                        <?php endif; ?>
                                    </select>
                                <?php endif; ?>
                                
                            </div>
                        </td>
                    </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
        <?php endif; ?>
      </section>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="planModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><span style="float:right; cursor:pointer; font-size:24px;" onclick="closePlanModal()">&times;</span><h2>New Plan</h2></div>
            <div class="modal-body">
                <form method="POST">
                    <div class="form-group"><label>Plan Name</label><input type="text" name="plan_name" class="form-input" required></div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                        <div class="form-group"><label>Duration</label><input type="text" name="duration" class="form-input" required></div>
                        <div class="form-group"><label>Min Amount</label><input type="number" name="min_amount" class="form-input" required></div>
                    </div>
                    <div class="form-group"><label>Benefit</label><input type="text" name="benefit" class="form-input" required></div>
                    <div class="form-group"><label>Penalty</label><input type="text" name="penalty" class="form-input" required></div>
                    <div class="form-group"><label>Description</label><textarea name="description" class="form-input"></textarea></div>
                    <div class="form-group"><label>Theme Color</label><input type="color" name="border_color" value="#6327af" style="width:100%; height:45px;"></div>
                    <button type="submit" name="save_plan" class="btn btn-primary" style="width:100%; background:#6327af; border:none; padding:15px; color:#fff; border-radius:6px; font-weight:bold;">Create Plan</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openPlanModal() { let m = document.getElementById('planModal'); if(m) m.style.display = 'block'; }
        function closePlanModal() { let m = document.getElementById('planModal'); if(m) m.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == document.getElementById('planModal')) closePlanModal(); }

        // AUTO-HIDE SUCCESS MESSAGE: Disappears after 10 seconds
        setTimeout(function() {
            var msg = document.getElementById('status-msg');
            if (msg) {
                msg.style.opacity = '0';
                setTimeout(function() { msg.style.display = 'none'; }, 500);
            }
            var err = document.getElementById('error-msg');
            if (err) {
                err.style.opacity = '0';
                setTimeout(function() { err.style.display = 'none'; }, 500);
            }
        }, 10000); // 10000ms = 10 seconds
    </script>
</body>
</html>