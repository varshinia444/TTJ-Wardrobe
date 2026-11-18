<?php
include('db_connect.php');
session_start(); // START SESSION FOR VALIDATION

// --- NEW AUTH GUARD (Allows both Admin and Viewer) ---
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

if (!$is_admin && !$is_viewer) {
    header("Location: index.php"); // Redirect to main login if not authenticated
    exit;
}

// --- DIRECT LOGOUT LOGIC (No Confirmation) ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: index.php"); 
    exit();
}

// --- 1. HANDLE MANUAL ADJUSTMENT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_adjustment'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to make manual adjustments.");
    }

    $shop_id_input = mysqli_real_escape_string($conn, $_POST['manual_shop_id']);
    $type = mysqli_real_escape_string($conn, $_POST['type']); 
    $grams = floatval($_POST['grams']);
    $rate = floatval($_POST['buy_rate']);
    $desc = mysqli_real_escape_string($conn, $_POST['description'] ?? 'Manual Adjustment');
    $manual_date = mysqli_real_escape_string($conn, $_POST['adj_date']);
    $calc_amt = $grams * $rate; // Added to update total_amount_paid

    $user_query = mysqli_query($conn, "SELECT id FROM users WHERE shop_number = '$shop_id_input' OR id = '$shop_id_input' LIMIT 1");
    
    if ($user_query && mysqli_num_rows($user_query) > 0) {
        $user_data = mysqli_fetch_assoc($user_query);
        $user_id = $user_data['id'];

        if ($type == 'Credit') {
            $update_wallet = "INSERT INTO gold_wallets (user_id, gold_balance, avg_buy_rate, total_amount_paid) 
                              VALUES ('$user_id', '$grams', '$rate', '$calc_amt') 
                              ON DUPLICATE KEY UPDATE 
                              avg_buy_rate = '$rate', 
                              gold_balance = gold_balance + $grams,
                              total_amount_paid = total_amount_paid + $calc_amt,
                              last_activity = '$manual_date'";
        } else {
            $update_wallet = "UPDATE gold_wallets SET gold_balance = gold_balance - $grams, last_activity = '$manual_date' WHERE user_id = '$user_id'";
        }
        mysqli_query($conn, $update_wallet);

        mysqli_query($conn, "INSERT INTO gold_history (user_id, type, grams, rate, description, created_at) 
                             VALUES ('$user_id', '$type', '$grams', '$rate', '$desc', '$manual_date')");

        header("Location: goldsavings.php?msg=Adjustment processed successfully");
    } else {
        header("Location: goldsavings.php?error=Shop ID not found");
    }
    exit();
}

// --- 2. HANDLE WALLET EDIT ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_wallet_data'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to edit wallets.");
    }

    $u_id = mysqli_real_escape_string($conn, $_POST['edit_u_id']);
    $new_balance = floatval($_POST['edit_balance']);
    $new_rate = floatval($_POST['edit_rate']);

    $sql = "UPDATE gold_wallets SET gold_balance = '$new_balance', avg_buy_rate = '$new_rate', last_activity = NOW() WHERE user_id = '$u_id'";
    if(mysqli_query($conn, $sql)) {
        header("Location: goldsavings.php?msg=Wallet updated successfully");
    }
    exit();
}

// --- 3. HANDLE LIVE RATE UPDATE ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_live_rate'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to update live rates.");
    }

    $new_rate = floatval($_POST['new_rate']);
    mysqli_query($conn, "UPDATE market_rates SET rate_inr = '$new_rate', updated_at = NOW() WHERE metal_key = 'gold22'");
    header("Location: goldsavings.php?msg=Live rate updated");
    exit();
}

// --- 4. DYNAMIC STATS ---
$rate_query = mysqli_query($conn, "SELECT rate_inr FROM market_rates WHERE metal_key = 'gold22' LIMIT 1");
$rate_data = mysqli_fetch_assoc($rate_query);
$live_rate = $rate_data['rate_inr'] ?? 12480; 

$stats_query = mysqli_query($conn, "SELECT SUM(gold_balance) as total_gold, COUNT(*) as active_count, SUM(total_amount_paid) as total_paid_all FROM gold_wallets WHERE gold_balance > 0");
$stats = mysqli_fetch_assoc($stats_query);
$total_liability = $stats['total_gold'] ?? 0;
$wallet_value_inr = $stats['total_paid_all'] ?? 0; // Show actual revenue paid by users
$active_wallets = $stats['active_count'] ?? 0;

// --- 5. DATA FETCHING ---
$view_user_id = isset($_GET['view_history']) ? mysqli_real_escape_string($conn, $_GET['view_history']) : null;

if ($view_user_id) {
    $u_res = mysqli_query($conn, "SELECT name, shop_number FROM users WHERE id = '$view_user_id'");
    $target_user = mysqli_fetch_assoc($u_res);
    $history_result = mysqli_query($conn, "SELECT * FROM gold_history WHERE user_id = '$view_user_id' ORDER BY created_at DESC");
} else {
    $wallet_sql = "SELECT u.id as u_id, u.name, u.mobile, u.shop_number, 
                    IFNULL(w.gold_balance, 0) as gold_balance, 
                    IFNULL(w.avg_buy_rate, 0) as avg_buy_rate, 
                    IFNULL(w.total_amount_paid, 0) as total_paid, 
                    w.last_activity,
                    (SELECT admin_acknowledged FROM transactions WHERE customer_name = u.name ORDER BY id DESC LIMIT 1) as is_acked,
                    (SELECT id FROM transactions WHERE customer_name = u.name ORDER BY id DESC LIMIT 1) as last_trans_id
                    FROM users u 
                    LEFT JOIN gold_wallets w ON u.id = w.user_id 
                    GROUP BY u.id 
                    ORDER BY w.last_activity DESC";
    $wallet_result = mysqli_query($conn, $wallet_sql);
}

// --- 6. FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TTJ - Gold Wallet Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
      .modal-content { background-color: white; margin: 2% auto; padding: 25px; border-radius: 12px; width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
      .close { float: right; cursor: pointer; font-size: 28px; color: #666; }
      .form-group { margin-bottom: 15px; }
      .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
      .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
      .calc-hint { font-size: 14px; color: #856404; margin-top: 8px; font-weight: 800; background: #fff3cd; padding: 8px; border-radius: 4px; display: block; }
      .shop-badge { background: #f3e5f5; color: #6327af; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; display: inline-block; }
      .rate-bar { background: #fff9e6; padding: 15px 25px; border-radius: 12px; border: 1px solid #ffeeba; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
      .action-btn-grp { display: flex; gap: 8px; justify-content: center; }
      .new-badge { background: #e8f5e9; color: #2e7d32; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 900; border: 1px solid #2e7d32; display: block; width: fit-content; margin-top: 4px; }
      .ack-btn { font-size: 10px; color: #5D1F88; text-decoration: underline; margin-left: 2px; cursor: pointer; border: none; background: none; padding: 0; }
      
      /* Updated Notification Bell and Red Dot Styling */
      .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; margin-right: 15px; }
      .bell-container:hover { transform: scale(1.1); }
      .notification-dot { 
          position: absolute; top: 2px; right: 2px; 
          height: 10px; width: 10px; 
          background: #ff3b30; border-radius: 50%; 
          border: 2px solid white;
          box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
      }

      /* Standard Logout Styling */
      .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; display: inline-block; }
      .logout-btn-header:hover { background: #c62828; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Gold Wallet Management</h1>
        <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
            
            <div class="bell-container" onclick="window.location.href='admin_alerts_view.php'">
                <span class="material-icons" style="color: #6327af; font-size: 28px;">notifications</span>
                <?php if($header_unread_count > 0): ?>
                    <span class="notification-dot"></span>
                <?php endif; ?>
            </div>

            <?php
            // Dynamic Header Avatar Logic
            $displayName = "Super Admin";
            $avatarLetter = "A";

            if ($is_viewer) {
                $displayName = "Viewer"; // Just "Viewer" as requested
                $avatarLetter = "V";
            } elseif (isset($_SESSION['admin_username'])) {
                $displayName = htmlspecialchars($_SESSION['admin_username']);
                $avatarLetter = strtoupper(substr($displayName, 0, 1)); 
            }
            ?>
            <div style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                <?php echo $avatarLetter; ?>
            </div>
            <span style="color: #6327af; font-weight: 600;">
                <?php echo $displayName; ?>
            </span>
            
            <a href="?action=logout" class="logout-btn-header">Logout</a>
        </div>
      </div>

      <section class="content-section container-constrain">
        
        <div class="rate-bar">
          <div style="display:flex; align-items:center; gap:15px;">
            <span class="material-icons" style="color: #d4af37; font-size: 32px;">monetization_on</span>
            <div>
              <h3 style="margin:0; color: #856404; font-size: 14px;">Live Buy Rate (22k)</h3>
              <span style="font-size: 22px; font-weight: 800;">₹<?php echo number_format($live_rate); ?> / gram</span>
            </div>
          </div>
          
          <?php if(!$is_viewer): ?>
              <button class="btn btn-primary" style="background: #d4af37; border: none; padding: 10px 20px;" onclick="openRateModal()">Update Rate</button>
          <?php endif; ?>
          
        </div>

        <div class="stats-grid">
          <div class="stat-card"><h3>Total Gold Liability</h3><div class="stat-value" style="color:#d4af37;"><?php echo number_format($total_liability, 3); ?> g</div><div class="stat-change">Customer holdings</div></div>
          <div class="stat-card"><h3>Revenue (Actual)</h3><div class="stat-value">₹<?php echo number_format($wallet_value_inr); ?></div><div class="stat-change">Total User Payments</div></div>
          <div class="stat-card"><h3>Active Wallets</h3><div class="stat-value"><?php echo $active_wallets; ?></div><div class="stat-change">Users with balance > 0</div></div>
        </div>

        <div class="section-header" style="margin-top: 30px;">
          <?php if($view_user_id): ?>
            <h2>History: <?php echo htmlspecialchars($target_user['name']); ?></h2>
            <button class="btn btn-primary" onclick="window.location.href='goldsavings.php'">Back</button>
          <?php else: ?>
            <h2>User Wallet Balances</h2>
            
            <?php if(!$is_viewer): ?>
                <button class="btn btn-primary" onclick="openModal()" style="background:#6327af;">Manual Credit / Debit</button>
            <?php endif; ?>
            
          <?php endif; ?>
        </div>

        <div class="table-container">
          <table>
            <?php if($view_user_id): ?>
                <thead><tr><th>Date</th><th>Type</th><th>Grams</th><th>Rate</th><th>Reason</th></tr></thead>
                <tbody>
                  <?php if(mysqli_num_rows($history_result) > 0): while($h = mysqli_fetch_assoc($history_result)): ?>
                  <tr>
                    <td><?php echo date("M d, Y h:i A", strtotime($h['created_at'])); ?></td>
                    <td class="<?php echo ($h['type'] == 'Credit') ? 'type-credit' : 'type-debit'; ?>"><?php echo $h['type']; ?></td>
                    <td><?php echo number_format($h['grams'], 3); ?> g</td>
                    <td>₹<?php echo number_format($h['rate']); ?></td>
                    <td><?php echo htmlspecialchars($h['description']); ?></td>
                  </tr>
                  <?php endwhile; else: echo "<tr><td colspan='5' style='text-align:center;'>No history found</td></tr>"; endif; ?>
                </tbody>
            <?php else: ?>
                <thead>
                  <tr>
                    <th>Customer</th><th>Shop ID</th><th>Mobile</th><th>Total Grams</th><th>Last Activity</th><th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while($row = mysqli_fetch_assoc($wallet_result)): 
                     $is_new_time = false;
                     if ($row['last_activity']) {
                         $last_act = strtotime($row['last_activity']);
                         if ($last_act > strtotime('-24 hours')) { $is_new_time = true; }
                     }
                     $show_badge = ($is_new_time && $row['is_acked'] == 0 && $row['last_trans_id'] != null);
                  ?>
                  <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['name']); ?></strong>
                        
                        <?php if($show_badge): ?>
                            <br>
                            <span class="new-badge">NEW PURCHASE</span>
                            
                            <?php if(!$is_viewer): ?>
                                <a href="ack_transaction.php?id=<?php echo $row['last_trans_id']; ?>" 
                                   class="ack-btn" 
                                   onclick="return confirm('Mark this purchase as seen?')">
                                   [Mark Seen]
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="shop-badge"><?php echo htmlspecialchars($row['shop_number'] ?: '1'); ?></span></td>
                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                    <td><strong style="color:#d4af37;"><?php echo number_format($row['gold_balance'] ?? 0, 3); ?> g</strong></td>
                    
                    <td><?php echo $row['last_activity'] ? date("M d, Y", strtotime($row['last_activity'])) : '---'; ?></td>
                    <td>
                      <div class="action-btn-grp">
                        <a href="?view_history=<?php echo $row['u_id']; ?>" class="btn btn-primary" style="padding:6px 10px; font-size:11px;">History</a>
                        
                        <?php if(!$is_viewer): ?>
                            <button class="btn" style="background:#eee; padding:6px 10px; border-radius:4px;" onclick='openEditWalletModal(<?php echo json_encode($row); ?>)'><span class="material-icons" style="font-size:16px; color:#333;">edit</span></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
            <?php endif; ?>
          </table>
        </div>
      </section>
    </main>

    <?php if(!$is_viewer): ?>
        
        <div id="walletModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h2 style="margin-top:0; color: #6327af;">Manual Adjustment</h2>
                <form method="POST">
                    <div class="form-group"><label>Shop ID:</label><input type="text" name="manual_shop_id" class="form-input" required></div>
                    <div class="form-group"><label>Transaction Date:</label><input type="datetime-local" name="adj_date" class="form-input" value="<?php echo date('Y-m-d\TH:i'); ?>" required></div>
                    <div class="form-group"><label>Type:</label><select name="type" class="form-input"><option value="Credit">Credit (+)</option><option value="Debit">Debit (-)</option></select></div>
                    <div class="form-group">
                        <label>Grams:</label>
                        <input type="number" step="0.001" name="grams" id="gramInput" class="form-input" placeholder="0.000" required>
                        <span id="calcLabel" class="calc-hint">Estimated Value: ₹0</span> 
                    </div>
                    <div class="form-group"><label>Rate (per g):</label><input type="number" name="buy_rate" id="rateInput" class="form-input" value="<?php echo $live_rate; ?>"></div>
                    <div class="form-group"><label>Description:</label><input type="text" name="description" class="form-input"></div>
                    <button type="submit" name="submit_adjustment" class="btn btn-primary" style="width:100%; background:#d4af37; border:none; height:45px; font-weight:700;">Confirm Adjustment</button>
                </form>
            </div>
        </div>

        <div id="editWalletModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2 style="margin-top:0; color: #6327af;">Edit Wallet Data</h2>
                <form method="POST">
                    <input type="hidden" name="edit_u_id" id="edit_u_id">
                    <div class="form-group"><label>User Name:</label><input type="text" id="edit_name" class="form-input" readonly style="background:#f5f5f5;"></div>
                    <div class="form-group"><label>Current Balance (g):</label><input type="number" step="0.001" name="edit_balance" id="edit_balance" class="form-input" required></div>
                    <div class="form-group"><label>Last Buy Rate (₹):</label><input type="number" name="edit_rate" id="edit_rate" class="form-input" required></div>
                    <button type="submit" name="update_wallet_data" class="btn btn-primary" style="width:100%; background:#6327af; height:45px;">Save Changes</button>
                </form>
            </div>
        </div>

        <div id="rateModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeRateModal()">&times;</span>
                <h2 style="margin-top:0; color: #856404;">Update Live Buy Rate</h2>
                <form method="POST">
                    <div class="form-group"><label>New Rate per Gram (₹):</label><input type="number" name="new_rate" class="form-input" value="<?php echo $live_rate; ?>" required></div>
                    <button type="submit" name="update_live_rate" class="btn btn-primary" style="width:100%; background:#d4af37; border:none; height:45px; font-weight:700;">Save New Rate</button>
                </form>
            </div>
        </div>

    <?php endif; ?>

    <script>
        function openModal() { let m = document.getElementById('walletModal'); if(m) m.style.display = 'block'; }
        function closeModal() { let m = document.getElementById('walletModal'); if(m) m.style.display = 'none'; }
        function openRateModal() { let m = document.getElementById('rateModal'); if(m) m.style.display = 'block'; }
        function closeRateModal() { let m = document.getElementById('rateModal'); if(m) m.style.display = 'none'; }
        function closeEditModal() { let m = document.getElementById('editWalletModal'); if(m) m.style.display = 'none'; }
        
        function openEditWalletModal(data) {
            let m = document.getElementById('editWalletModal');
            if(!m) return; // Prevent error for viewers
            document.getElementById('edit_u_id').value = data.u_id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_balance').value = data.gold_balance || 0;
            document.getElementById('edit_rate').value = data.avg_buy_rate || 0;
            m.style.display = 'block';
        }

        const gramInput = document.getElementById('gramInput');
        const rateInput = document.getElementById('rateInput');
        const calcLabel = document.getElementById('calcLabel');
        
        function updateEstimatedValue() {
            if(!gramInput || !rateInput) return;
            const grams = parseFloat(gramInput.value) || 0, rate = parseFloat(rateInput.value) || 0;
            calcLabel.innerText = "Estimated Value: ₹" + (grams * rate).toLocaleString('en-IN');
        }
        
        if(gramInput) gramInput.addEventListener('input', updateEstimatedValue);
        if(rateInput) rateInput.addEventListener('input', updateEstimatedValue);
        
        window.onclick = function(event) { 
            if (event.target.className === 'modal') { 
                closeModal(); 
                closeRateModal(); 
                closeEditModal(); 
            } 
        }
    </script>
</body>
</html>