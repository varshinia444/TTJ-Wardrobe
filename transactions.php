<?php
// 1. DATABASE CONNECTION
include 'db_connect.php'; 
session_start();

// --- AJAX: AUTO-FETCH USER BY MOBILE ---
if (isset($_POST['fetch_customer'])) {
    $mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
    
    // We search the 'users' table because it holds the mobile numbers
    $u_qry = "SELECT id, name, last_name, email FROM users WHERE mobile = '$mobile' LIMIT 1";
    $u_res = mysqli_query($conn, $u_qry);

    if (mysqli_num_rows($u_res) > 0) {
        $row = mysqli_fetch_assoc($u_res);
        $full_name = trim($row['name'] . ' ' . $row['last_name']);
        echo json_encode([
            'status' => 'found', 
            'user_id' => $row['id'], // Critical for loyalty_ledger
            'name' => $full_name, 
            'email' => $row['email']
        ]);
    } else {
        echo json_encode(['status' => 'not_found']);
    }
    exit;
}

// --- NEW AUTH GUARD (Allows both Admin and Viewer) ---
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

if (!$is_admin && !$is_viewer) {
    header("Location: index.php");
    exit;
}

// --- MANUAL ENTRY SAVE ENGINE (Using loyalty_ledger) ---
$msg = "";
if (isset($_POST['add_transaction'])) {
    
    // BACKEND SECURITY: Block viewers from adding transactions
    if ($is_viewer) {
        die("Error: Viewers do not have permission to add transactions.");
    }

    // 1. Get User ID (Hidden field populated by JS)
    $user_id = (int)$_POST['user_id'];
    
    // If JS didn't get it (e.g. manual type without lookup), try to find by mobile again
    if ($user_id == 0) {
        $c_mobile = mysqli_real_escape_string($conn, $_POST['mobile']);
        $check_user = mysqli_query($conn, "SELECT id FROM users WHERE mobile = '$c_mobile'");
        if ($row = mysqli_fetch_assoc($check_user)) {
            $user_id = $row['id'];
        }
    }

    if ($user_id > 0) {
        $c_amount  = (float) $_POST['amount'];
        $c_points  = floor($c_amount / 100); // 100 INR = 1 Point
        $c_details = mysqli_real_escape_string($conn, $_POST['details']);
        $c_type    = 'EARN'; // Defaulting to EARN for purchases
        
        // Handle Date (created_at)
        if (!empty($_POST['custom_date'])) {
            $c_date = mysqli_real_escape_string($conn, $_POST['custom_date']);
        } else {
            $c_date = date('Y-m-d H:i:s');
        }

        // Insert into loyalty_ledger
        $sql = "INSERT INTO loyalty_ledger (user_id, transaction_type, order_amount, points, description, created_at) 
                VALUES ('$user_id', '$c_type', '$c_amount', '$c_points', '$c_details', '$c_date')";

        if (mysqli_query($conn, $sql)) {
            header("Location: transactions.php?msg=success");
            exit;
        } else {
            $msg = "<div style='color:red; margin-bottom:15px;'>Database Error: " . mysqli_error($conn) . "</div>";
        }
    } else {
        $msg = "<div style='color:red; margin-bottom:15px;'>Error: User not found! Please register the customer first.</div>";
    }
}

if (isset($_GET['msg']) && $_GET['msg'] == 'success') {
    $msg = "<div style='color:green; margin-bottom:15px; font-weight:600; background:#e8f5e9; padding:10px; border-radius:5px;'>Transaction added successfully to Loyalty Ledger!</div>";
}

// --- FILTER & SEARCH ENGINE ---
// We query 'loyalty_ledger' and JOIN 'users' to get Name/Mobile
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';

$base_where = " WHERE 1=1";

if (!empty($search)) {
    // Search in User table (Name/Mobile) or Ledger (ID)
    $base_where .= " AND (u.name LIKE '%$search%' OR u.mobile LIKE '%$search%' OR l.id LIKE '%$search%')";
}
if ($type_filter !== 'all') {
    $base_where .= " AND l.transaction_type = '$type_filter'";
}
if (!empty($date_filter)) {
    $base_where .= " AND DATE(l.created_at) = '$date_filter'";
}

// Main Query
$query = "SELECT l.*, u.name, u.last_name, u.mobile, u.email 
          FROM loyalty_ledger l 
          LEFT JOIN users u ON l.user_id = u.id 
          " . $base_where . " 
          ORDER BY l.created_at DESC";

$result = mysqli_query($conn, $query);

// --- CSV EXPORT ---
if (isset($_POST['export_csv'])) {
    
    // BACKEND SECURITY: Block viewers from downloading data
    if ($is_viewer) {
        die("Error: Viewers do not have permission to export data.");
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=TTJ_Ledger_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, array('Date', 'User Name', 'Mobile', 'Type', 'Amount', 'Points', 'Description'));

    $export_res = mysqli_query($conn, $query); // Use same query as display
    while ($row = mysqli_fetch_assoc($export_res)) {
        $full_name = $row['name'] . ' ' . $row['last_name'];
        fputcsv($output, array(
            $row['created_at'], $full_name, $row['mobile'], 
            $row['transaction_type'], $row['order_amount'], $row['points'], $row['description']
        ));
    }
    fclose($output);
    exit;
}

// --- 5. FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;

// LOGOUT
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>TTJ - Loyalty Transactions</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
    <style>
        /* Exact UI Matches */
        .category-earn { background: #e8f5e9; color: #1b5e20; }
        .category-redeem { background: #fff3cd; color: #856404; }
        
        .manual-entry-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .manual-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 15px;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 10px;
        }

        /* Updated Notification Indicator Logic */
        .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; margin-right: 5px; }
        .bell-container:hover { transform: scale(1.1); }
        .notification-dot { 
            position: absolute; top: 2px; right: 2px; 
            height: 10px; width: 10px; 
            background: #ff3b30; border-radius: 50%; 
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
        }
    </style>
    <script>
        // Auto-fetch Customer Details from USERS table
        function lookupCustomer() {
            var mobile = document.getElementById('mobileInput').value;
            // Reset fields
            document.getElementById('nameInput').value = "";
            document.getElementById('emailInput').value = "";
            document.getElementById('userIdInput').value = "0";

            if(mobile.length > 5) {
                var formData = new FormData();
                formData.append('fetch_customer', true);
                formData.append('mobile', mobile);

                fetch('transactions.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => {
                    if(data.status === 'found') {
                        document.getElementById('nameInput').value = data.name;
                        document.getElementById('emailInput').value = data.email;
                        document.getElementById('userIdInput').value = data.user_id; // Store ID
                        document.getElementById('mobileInput').style.borderColor = "#2e7d32"; // Green border
                    } else {
                        document.getElementById('mobileInput').style.borderColor = "#c62828"; // Red border
                    }
                });
            }
        }

        // Auto-calculate Points
        function calculatePoints() {
            var amount = document.getElementById('amountInput').value;
            if(amount) {
                var points = Math.floor(amount / 100);
                document.getElementById('pointsInput').value = points;
            } else {
                document.getElementById('pointsInput').value = 0;
            }
        }
    </script>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <div class="top-bar container-constrain">
            <h1>Loyalty Ledger</h1>
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
                    $displayName = "Viewer";
                    $avatarLetter = "V";
                } elseif (isset($_SESSION['admin_username'])) {
                    $displayName = htmlspecialchars($_SESSION['admin_username']);
                    $avatarLetter = strtoupper(substr($displayName, 0, 1)); 
                }
                ?>
                <div class="user-avatar" style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                    <?php echo $avatarLetter; ?>
                </div>
                <span id="adminName" style="font-weight: 600;">
                    <?php echo $displayName; ?>
                </span>
                <a href="transactions.php?action=logout" class="logout-btn" style="text-decoration:none;" onclick="return confirm('Logout?')">Logout</a>
            </div>
        </div>

        <section class="content-section container-constrain">
            
            <?php if(!$is_viewer): ?>
            <div class="manual-entry-box">
                <div class="manual-title">Add Loyalty Transaction</div>
                <?php echo $msg; ?>
                
                <form method="POST" action="transactions.php" class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    
                    <input type="hidden" id="userIdInput" name="user_id" value="0">

                    <div>
                        <label>Mobile Number <small>(Search)</small></label>
                        <input type="text" id="mobileInput" name="mobile" class="form-control" placeholder="Enter Registered Mobile" required onblur="lookupCustomer()">
                    </div>
                    <div>
                        <label>Customer Name</label>
                        <input type="text" id="nameInput" name="customer_name" class="form-control" placeholder="Auto-filled" readonly style="background-color:#f8fafc;">
                    </div>
                    <div>
                        <label>Email ID</label>
                        <input type="email" id="emailInput" name="email" class="form-control" placeholder="Auto-filled" readonly style="background-color:#f8fafc;">
                    </div>

                    <div>
                        <label>Amount (₹)</label>
                        <input type="number" id="amountInput" name="amount" class="form-control" placeholder="0.00" onkeyup="calculatePoints()" required>
                    </div>
                    <div>
                        <label>Points (Auto)</label>
                        <input type="number" id="pointsInput" name="points" class="form-control" placeholder="0" readonly style="background-color:#f8fafc;">
                    </div>
                    
                    <div>
                        <label>Details / Description</label>
                        <input type="text" name="details" class="form-control" placeholder="e.g. Gold Chain Purchase">
                    </div>

                    <div>
                        <label>Date & Time</label>
                        <input type="datetime-local" name="custom_date" class="form-control">
                    </div>
                    
                    <div style="grid-column: 1 / -1; margin-top:10px;">
                        <button type="submit" name="add_transaction" class="btn btn-primary" style="width: auto; padding: 10px 30px;">Add Points</button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="section-header">
                <h2>Transaction History</h2>
                
                <?php if(!$is_viewer): ?>
                <form method="POST">
                    <button type="submit" name="export_csv" class="btn btn-primary">Export CSV</button>
                </form>
                <?php endif; ?>
                
            </div>

            <form method="GET" action="transactions.php" class="form-grid" style="margin-top:18px; align-items:end; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div>
                     <label>Search User</label>
                     <input type="text" name="search" class="form-control" placeholder="Name or Mobile" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div>
                     <label>Type</label>
                     <select name="type" class="form-control">
                        <option value="all" <?php if($type_filter == 'all') echo 'selected'; ?>>All Types</option>
                        <option value="EARN" <?php if($type_filter == 'EARN') echo 'selected'; ?>>Earned (EARN)</option>
                        <option value="REDEEM" <?php if($type_filter == 'REDEEM') echo 'selected'; ?>>Redeemed (REDEEM)</option>
                     </select>
                </div>
                <div>
                     <label>Date</label>
                     <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                </div>
                <div>
                     <button type="submit" class="btn btn-primary" style="width:100%;">Apply Filters</button>
                </div>
            </form>

            <div class="table-container" style="margin-top:24px;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Mobile</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Points</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if(mysqli_num_rows($result) > 0): 
                            while($row = mysqli_fetch_assoc($result)): 
                                $type = strtoupper($row['transaction_type']);
                                $badgeClass = ($type == 'EARN') ? 'category-earn' : 'category-redeem';
                        ?>
                        <tr>
                            <td>
                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?><br>
                                <span style="font-size:0.8em; color:#888;"><?php echo date('h:i A', strtotime($row['created_at'])); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['name'] . ' ' . $row['last_name']); ?></strong><br>
                                <small style="color:#666;"><?php echo htmlspecialchars($row['email'] ?? ''); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($row['mobile'] ?? '-'); ?></td>
                            
                            <td><span class="badge <?php echo $badgeClass; ?>"><?php echo $type; ?></span></td>

                            <td>₹<?php echo number_format($row['order_amount'], 2); ?></td>

                            <td style="font-weight:700; color: <?php echo ($type == 'EARN') ? '#2e7d32' : '#c62828'; ?>;">
                                <?php echo ($type == 'EARN') ? '+' : '-'; ?>
                                <?php echo $row['points']; ?>
                            </td>

                            <td><span style="font-size: 0.9em; color: #555;"><?php echo htmlspecialchars($row['description'] ?: 'N/A'); ?></span></td>
                        </tr>
                        <?php 
                            endwhile; 
                        else: 
                        ?>
                            <tr><td colspan="7" style="text-align:center; padding: 20px;">No transactions found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 15px; font-size: 0.85em; color: #666;">
                Showing <?php echo mysqli_num_rows($result); ?> records found.
            </div>
        </section>
    </main>
</body>
</html>