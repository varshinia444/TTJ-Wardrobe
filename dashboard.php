<?php
include 'db_connect.php';
session_start();

// --- AUTH GUARD (Allows both Admin and Viewer) ---
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

if (!$is_admin && !$is_viewer) {
    // If not logged in as either, kick them out
    header("Location: index.php");
    exit;
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

// --- 1. HANDLE RATE UPDATES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rates_btn'])) {
    
    // BACKEND SECURITY: Block viewers from submitting data
    if ($is_viewer) {
        header("Location: dashboard.php?msg=Error: Viewers do not have permission to update rates.");
        exit;
    }

    // Check if 24K Gold (ID 1) is being updated to calculate others automatically
    if (isset($_POST['rates'][1]['rate'])) {
        $rate24k = floatval($_POST['rates'][1]['rate']);
        $change24k = mysqli_real_escape_string($conn, $_POST['rates'][1]['change']);

        // Define standard purity ratios
        $rate22k = $rate24k * 0.9167; // 22/24 Purity
        $rate18k = $rate24k * 0.7500; // 18/24 Purity
        $rate9k  = $rate24k * 0.3750; // 9/24 Purity

        // Apply automatic updates to the POST array so the loop handles them
        $_POST['rates'][2]['rate'] = $rate22k; // ID 2 = 22K
        $_POST['rates'][3]['rate'] = $rate18k; // ID 3 = 18K
        $_POST['rates'][4]['rate'] = $rate9k;  // ID 4 = 9K
    }

    foreach ($_POST['rates'] as $id => $data) {
        $new_rate = mysqli_real_escape_string($conn, $data['rate']);
        $new_change = isset($data['change']) ? mysqli_real_escape_string($conn, $data['change']) : '0.00';
        
        // Update query for market_rates table
        $update_sql = "UPDATE market_rates SET rate_inr = '$new_rate', change_24h = '$new_change', updated_at = NOW() WHERE id = '$id'";
        mysqli_query($conn, $update_sql);
    }
    header("Location: dashboard.php?msg=All Gold Rates Updated Automatically Based on 24K");
    exit;
}

// --- 2. FETCH OVERVIEW STATS ---
// Total Members count
$members_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM users");
$total_members = mysqli_fetch_assoc($members_q)['cnt'] ?? 0;

// Active Points (Liability)
$points_q = mysqli_query($conn, "SELECT SUM(points) as sum FROM users");
$active_points = mysqli_fetch_assoc($points_q)['sum'] ?? 0;

// Points Redeemed (Updated to show total value from transactions)
$redeemed_q = mysqli_query($conn, "SELECT SUM(points) as sum FROM transactions WHERE type='Redeemed'");
$points_redeemed = mysqli_fetch_assoc($redeemed_q)['sum'] ?? 0;

// Golden Harvest Schemes (Active Enrollments)
$schemes_q = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM scheme_enrollments WHERE status='Active'");
$active_schemes = mysqli_fetch_assoc($schemes_q)['cnt'] ?? 0;

// --- 3. FETCH LIVE METAL RATES ---
$rates_query = mysqli_query($conn, "SELECT * FROM market_rates ORDER BY id ASC");
$modal_rates_query = mysqli_query($conn, "SELECT * FROM market_rates ORDER BY id ASC");

// --- 4. FETCH RECENT TRANSACTIONS ---
$recent_tx_query = mysqli_query($conn, "SELECT trans_date, customer_name, type, points, status FROM transactions ORDER BY trans_date DESC LIMIT 5");

// --- 5. FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>TTJ Loyalty Admin - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
    <style>
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 8px; }
        .stat-card .stat-value { font-size: 24px; font-weight: 700; color: #6327af; }
        .stat-card .stat-label { font-size: 12px; margin-top: 5px; }
        .label-up { color: #28a745; }
        .label-neutral { color: #666; }
        .badge-completed { background: #fff3cd; color: #856404; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        
        /* Modal Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 12px; width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .close-btn { float: right; cursor: pointer; font-size: 24px; color: #666; }
        .rate-input-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 15px; align-items: center; }
        .form-control-sm { padding: 8px; border: 1px solid #ddd; border-radius: 4px; width: 100%; }
        .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
        .alert-danger { padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }

        /* Updated Notification Indicator Logic */
        .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; }
        .bell-container:hover { transform: scale(1.1); }
        .notification-dot { 
            position: absolute; top: 2px; right: 2px; 
            height: 10px; width: 10px; 
            background: #ff3b30; border-radius: 50%; 
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
        }
    </style>
</head>
<body>
    <?php include_once 'sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar container-constrain">
            <h1>Dashboard Overview</h1>
            <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
                
                <div class="bell-container" onclick="window.location.href='admin_alerts_view.php'">
                    <span class="material-icons" style="color: #6327af; font-size: 28px;">notifications</span>
                    <?php if($header_unread_count > 0): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
                </div>

                <?php
                // Determine the display name and avatar letter based on the session
                $displayName = "Super Admin";
                $avatarLetter = "A";

                if ($is_viewer) {
                    $displayName = "Viewer";
                    $avatarLetter = "V";
                } elseif (isset($_SESSION['admin_username'])) {
                    // If Admin is logged in, show their actual username
                    $displayName = htmlspecialchars($_SESSION['admin_username']);
                    $avatarLetter = strtoupper(substr($displayName, 0, 1)); // Gets the first letter
                }
                ?>
                <div class="user-avatar" style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo $avatarLetter; ?>
                </div>
                <span id="adminName" style="font-weight: 600;">
                    <?php echo $displayName; ?>
                </span>
                <a href="dashboard.php?action=logout" class="logout-btn" style="text-decoration:none;">Logout</a>
            </div>
        </div>

        <section class="content-section container-constrain">
            
            <?php 
            if(isset($_GET['msg'])) {
                if (strpos($_GET['msg'], 'Error') !== false || strpos($_GET['msg'], 'Denied') !== false) {
                    echo "<div class='alert-danger'>".htmlspecialchars($_GET['msg'])."</div>";
                } else {
                    echo "<div class='alert-success'>".htmlspecialchars($_GET['msg'])."</div>";
                }
            }
            ?>

            <div class="stats-grid" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                <div class="stat-card">
                    <h3>Total Members</h3>
                    <div class="stat-value"><?php echo number_format($total_members); ?></div>
                    <div class="stat-label label-up">↑ Live Count</div>
                </div>
                <div class="stat-card">
                    <h3>Active Points</h3>
                    <div class="stat-value">₹<?php echo number_format($active_points); ?></div>
                    <div class="stat-label label-neutral">Points liability</div>
                </div>
                <div class="stat-card">
                    <h3>Points Redeemed</h3>
                    <div class="stat-value">₹<?php echo number_format($points_redeemed); ?></div>
                    <div class="stat-label label-neutral">Total lifetime</div>
                </div>
                <div class="stat-card">
                    <h3>Golden Harvest Schemes</h3>
                    <div class="stat-value"><?php echo number_format($active_schemes); ?></div>
                    <div class="stat-label label-up">Active enrollments</div>
                </div>
            </div>

            <div class="section-header" style="margin-top:40px;">
                <h2>Live Gold & Silver Rates ✨</h2>
                
                <?php if(!$is_viewer): ?>
                    <button class="btn btn-primary" style="background:#6327af;" onclick="openRatesModal()">Update Rates</button>
                <?php endif; ?>
                
            </div>
            
            <div class="table-container" style="margin-top:15px;">
                <table>
                    <thead>
                        <tr>
                            <th>Metal</th><th>Unit</th><th>Rate (INR)</th><th>24H Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($rate = mysqli_fetch_assoc($rates_query)): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($rate['metal_name']); ?></td>
                            <td><?php echo htmlspecialchars($rate['unit']); ?></td>
                            <td>₹ <?php echo number_format($rate['rate_inr']); ?></td>
                            <td style="color: <?php echo $rate['change_24h'] >= 0 ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo $rate['change_24h'] >= 0 ? '+' : ''; ?><?php echo number_format($rate['change_24h'], 2); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <div class="section-header" style="margin-top:40px;">
                <h2>Recent Transactions</h2>
            </div>
            <div class="table-container" style="margin-top:15px;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th><th>Customer</th><th>Type</th><th>Points</th><th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($tx = mysqli_fetch_assoc($recent_tx_query)): ?>
                        <tr>
                            <td><?php echo date("M d, Y", strtotime($tx['trans_date'])); ?></td>
                            <td><?php echo htmlspecialchars($tx['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($tx['type']); ?></td>
                            <td style="color: <?php echo ($tx['type'] == 'Earned') ? '#28a745' : '#dc3545'; ?>;">
                                <?php echo ($tx['type'] == 'Earned') ? '+' : '-'; ?><?php echo $tx['points']; ?>
                            </td>
                            <td><span class="badge-completed"><?php echo $tx['status']; ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="ratesModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeRatesModal()">&times;</span>
            <h2 style="margin-top:0; color:#6327af;">Update Metal Rates</h2>
            <p style="font-size: 12px; color: #666;">Note: Updating 24K Gold will automatically calculate 22K, 18K, and 9K rates.</p>
            <hr><br>
            <form method="POST" action="dashboard.php">
                <div class="rate-input-grid" style="font-weight:bold; color:#666; font-size:12px;">
                    <span>Metal</span><span>Rate (₹)</span><span>Change</span>
                </div>
                <?php while($m_rate = mysqli_fetch_assoc($modal_rates_query)): ?>
                <div class="rate-input-grid">
                    <span style="font-size:14px;"><?php echo $m_rate['metal_name']; ?></span>
                    <input type="number" name="rates[<?php echo $m_rate['id']; ?>][rate]" value="<?php echo $m_rate['rate_inr']; ?>" class="form-control-sm" step="0.01">
                    <input type="number" name="rates[<?php echo $m_rate['id']; ?>][change]" value="<?php echo $m_rate['change_24h']; ?>" class="form-control-sm" step="0.01">
                </div>
                <?php endwhile; ?>
                <br>
                <button type="submit" name="update_rates_btn" class="btn btn-primary" style="width:100%; padding:12px; background:#6327af;">Save Changes</button>
            </form>
        </div>
    </div>

    <script>
        function openRatesModal() { document.getElementById('ratesModal').style.display = 'block'; }
        function closeRatesModal() { document.getElementById('ratesModal').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('ratesModal')) closeRatesModal();
        }
    </script>
    <?php endif; ?>
</body>
</html>