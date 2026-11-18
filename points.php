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

// --- HELPER: Set Toast Message & Redirect ---
function redirectWithToast($type, $message) {
    $_SESSION['toast'] = ['type' => $type, 'message' => $message];
    header("Location: points.php");
    exit();
}

// --- 0. API HANDLER: FETCH HISTORY (AJAX) ---
// Note: Viewers are allowed to fetch and view history.
if (isset($_POST['action']) && $_POST['action'] == 'fetch_history') {
    $hist_user_id = intval($_POST['user_id']);
    $hist_query = "SELECT * FROM loyalty_ledger WHERE user_id = '$hist_user_id' ORDER BY created_at DESC LIMIT 50";
    $hist_result = mysqli_query($conn, $hist_query);
    
    if (mysqli_num_rows($hist_result) > 0) {
        echo '<table class="history-table">
                <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>Points</th></tr></thead>
                <tbody>';
        while ($row = mysqli_fetch_assoc($hist_result)) {
            $typeClass = ($row['points'] > 0) ? 'text-green' : 'text-red';
            $sign = ($row['points'] > 0) ? '+' : '';
            echo '<tr>
                    <td>' . date('d M Y', strtotime($row['created_at'])) . '</td>
                    <td>' . htmlspecialchars($row['description']) . '</td>
                    <td><span class="badge ' . strtolower($row['transaction_type']) . '">' . $row['transaction_type'] . '</span></td>
                    <td class="' . $typeClass . '" style="font-weight:bold;">' . $sign . number_format($row['points']) . '</td>
                  </tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<div style="text-align:center; padding:20px; color:#999;">No transaction history found.</div>';
    }
    exit();
}

// --- 1. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_points'])) {
    
    // BACKEND SECURITY: Block viewers from adding or deducting points
    if ($is_viewer) {
        echo "<script>alert('Error: Viewers do not have permission to adjust points or register users.'); window.history.back();</script>"; 
        exit();
    }

    // Inputs
    $user_id = intval($_POST['user_id'] ?? 0);
    $adj_type = mysqli_real_escape_string($conn, $_POST['adj_type']);
    $points_val = intval($_POST['points_val']); 
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $bill_ref = mysqli_real_escape_string($conn, $_POST['bill_ref']); 
    $bill_amount = floatval($_POST['bill_amount'] ?? 0);
    
    // Manual Registration Inputs
    $manual_fname      = mysqli_real_escape_string($conn, $_POST['manual_fname'] ?? '');
    $manual_lname      = mysqli_real_escape_string($conn, $_POST['manual_lname'] ?? '');
    $manual_mobile     = mysqli_real_escape_string($conn, $_POST['manual_mobile'] ?? '');
    $manual_email      = mysqli_real_escape_string($conn, $_POST['manual_email'] ?? '');
    $manual_gender     = mysqli_real_escape_string($conn, $_POST['manual_gender'] ?? '');
    $manual_dob        = mysqli_real_escape_string($conn, $_POST['manual_dob'] ?? '');
    $manual_password   = mysqli_real_escape_string($conn, $_POST['manual_password'] ?? '');

    // Validation
    if ($user_id == 0) {
        if (!preg_match('/^[0-9]{10}$/', $manual_mobile)) { // Simple check
             echo "<script>alert('Error: Mobile must be 10 digits'); window.history.back();</script>"; exit();
        }
    }
    
    if ($points_val <= 0) {
         echo "<script>alert('Error: Points must be > 0'); window.history.back();</script>"; exit();
    }

    // New User Creation
    if ($user_id == 0 && !empty($manual_mobile)) {
        $check_query = "SELECT id FROM users WHERE mobile = '$manual_mobile' OR email = '$manual_email' LIMIT 1";
        $check_result = mysqli_query($conn, $check_query);

        if (mysqli_num_rows($check_result) > 0) {
             echo "<script>alert('User already exists!'); window.history.back();</script>"; exit();
        } else {
            $insert_user_sql = "INSERT INTO users 
                (name, last_name, mobile, email, password, gender, dob, points, tier, status, created_at) 
                VALUES 
                ('$manual_fname', '$manual_lname', '$manual_mobile', '$manual_email', '$manual_password', '$manual_gender', '$manual_dob', 0, 'Silver', 'Active', NOW())";
            
            if (mysqli_query($conn, $insert_user_sql)) {
                $user_id = mysqli_insert_id($conn);
            } else {
                 echo "<script>alert('DB Error'); window.history.back();</script>"; exit();
            }
        }
    }

    // Get Current Data
    $user_query = mysqli_query($conn, "SELECT name, mobile, points FROM users WHERE id = '$user_id'");
    $user_data = mysqli_fetch_assoc($user_query);
    $customer_name = $user_data['name'] ?? $manual_fname;
    $customer_mobile = $user_data['mobile'] ?? $manual_mobile;
    $current_db_points = (int)($user_data['points'] ?? 0);

    // Calc Balance
    if ($adj_type == 'Earned') {
        $new_total = $current_db_points + $points_val;
        $toast_type = "success";
        $toast_msg = "Added $points_val points to $customer_name";
    } else {
        if ($current_db_points < $points_val) {
             echo "<script>alert('Insufficient Points!'); window.history.back();</script>"; exit();
        }
        $new_total = $current_db_points - $points_val;
        $toast_type = "warning";
        $toast_msg = "Redeemed $points_val points from $customer_name";
    }

    // Execute Updates
    mysqli_query($conn, "UPDATE users SET points = $new_total WHERE id = '$user_id'");
    mysqli_query($conn, "INSERT INTO points (user_id, customer_name, mobile, bill_amount, bill_ref, points_earned, adjustment_type, reason, created_at) VALUES ('$user_id', '$customer_name', '$customer_mobile', '$bill_amount', '$bill_ref', '$points_val', '$adj_type', '$reason', NOW())");

    $action_desc = ($adj_type == 'Earned') ? "Purchase" : "Redemption";
    $ledger_desc = "$action_desc (Ref: $bill_ref) - $reason";
    mysqli_query($conn, "INSERT INTO loyalty_ledger (user_id, transaction_type, order_amount, points, description, total_balance, created_at) VALUES ('$user_id', 'STORE_PURCHASE', '$bill_amount', '$points_val', '$ledger_desc', '$new_total', NOW())");

    // FAST REDIRECT WITH TOAST
    redirectWithToast($toast_type, $toast_msg);
}

// --- 2. SEARCH LOGIC ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$where_sql = " WHERE 1=1";
if (!empty($search)) { $where_sql .= " AND (name LIKE '%$search%' OR mobile LIKE '%$search%')"; }
$query = "SELECT * FROM users $where_sql ORDER BY id DESC LIMIT 50";
$result = mysqli_query($conn, $query);

// --- 3. FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TTJ - Point Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f4f6f8; }
        
        /* TOAST NOTIFICATION STYLES */
        #toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px; }
        .toast {
            min-width: 300px; background: white; padding: 16px 20px; border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); display: flex; align-items: center; gap: 12px;
            animation: slideIn 0.3s ease-out forwards; border-left: 5px solid #ddd;
        }
        .toast.success { border-left-color: #2e7d32; }
        .toast.success i { color: #2e7d32; }
        .toast.warning { border-left-color: #ef6c00; }
        .toast.warning i { color: #ef6c00; }
        .toast.error { border-left-color: #c62828; }
        .toast.error i { color: #c62828; }
        
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

        /* Notification Bell Indicator */
        .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; }
        .bell-container:hover { transform: scale(1.1); }
        .notification-dot { 
            position: absolute; top: 2px; right: 2px; 
            height: 10px; width: 10px; 
            background: #ff3b30; border-radius: 50%; 
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
        }

        /* Standard Logout Styling */
        .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .logout-btn-header:hover { background: #c62828; }

        /* Existing Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background: white; margin: 3vh auto; padding: 25px; border-radius: 12px; width: 95%; max-width: 600px; position: relative; border-top: 6px solid #6327af; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        
        .balance-card { background: #f3e5f5; padding: 15px; border-radius: 10px; margin-bottom: 20px; border-left: 5px solid #6327af; color: #6327af; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .form-input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; margin-bottom: 15px; font-family: 'Inter', sans-serif; transition: border 0.3s; }
        .form-input:focus { border-color: #6327af; outline: none; }
        .form-row { display: flex; gap: 15px; } .form-col { flex: 1; }
        
        .confirm-btn { width: 100%; background: #6327af; color: white; border: none; padding: 15px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.3s; }
        .confirm-btn:hover { background: #4a1c82; }
        
        .table-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th { text-align: left; padding: 15px; color: #666; font-size: 13px; text-transform: uppercase; border-bottom: 2px solid #eee; background: #fafafa; }
        td { padding: 15px; border-bottom: 1px solid #eee; color: #333; vertical-align: middle; }
        
        .btn-group { display: flex; gap: 8px; }
        .btn-icon { width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-view { background: #e3f2fd; color: #1565c0; } .btn-view:hover { background: #bbdefb; }
        .btn-hist { background: #fff3e0; color: #ef6c00; } .btn-hist:hover { background: #ffe0b2; }
        .btn-adjust { background: #6327af; color: white; padding: 0 15px; font-weight: 600; font-size: 13px; width: auto; }
        .btn-adjust:hover { background: #4a1c82; }

        .history-table { width: 100%; font-size: 13px; }
        .history-table th { padding: 10px; background: #f5f5f5; }
        .history-table td { padding: 10px; border-bottom: 1px solid #eee; }
        .text-green { color: #2e7d32; } .text-red { color: #c62828; }
        .badge { padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge.store_purchase { background: #e8eaf6; color: #3f51b5; }
        
        /* Radio */
        .transaction-type-group { display: flex; gap: 15px; margin-bottom: 20px; }
        .type-option { flex: 1; position: relative; }
        .type-option input { position: absolute; opacity: 0; cursor: pointer; }
        .type-btn { display: block; padding: 12px; text-align: center; border: 2px solid #ddd; border-radius: 8px; font-weight: bold; color: #666; cursor: pointer; transition: all 0.2s; }
        .type-option input[value="Earned"]:checked + .type-btn { background-color: #e8f5e9; border-color: #2e7d32; color: #2e7d32; }
        .type-option input[value="Redeemed"]:checked + .type-btn { background-color: #ffebee; border-color: #c62828; color: #c62828; }
    </style>
</head>
<body>

    <?php include('sidebar.php'); ?>

    <div id="toast-container"></div>

    <main class="main-content">
        <div class="container-constrain">
            <div class="top-bar" style="display: flex; justify-content: space-between; align-items: center; padding: 20px 0; background: transparent;">
                <div>
                    <h1 style="color: #6327af; margin:0;">Points Management</h1>
                    <p style="color:#666; margin:5px 0 0; font-size: 14px;">Manage customer points, view history and details.</p>
                </div>
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
                    <div style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                        <?php echo $avatarLetter; ?>
                    </div>
                    <span style="color: #6327af; font-weight: 600;">
                        <?php echo $displayName; ?>
                    </span>
                    
                    <a href="logout.php" class="logout-btn-header" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
                </div>
            </div>

            <section class="content-section">
                <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
                    <form method="GET" style="flex: 1; min-width: 250px; display: flex; gap: 10px;">
                        <input type="text" name="search" class="form-input" style="margin-bottom:0;" placeholder="Search by Name or Mobile..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" style="background:#1e1b4b; color:white; border:none; padding: 0 25px; border-radius: 8px; cursor:pointer;"><i class="material-icons">search</i></button>
                    </form>
                    
                    <?php if(!$is_viewer): ?>
                    <button onclick="openManualEntryModal()" style="background: #6327af; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="material-icons">person_add</i> Full Register
                    </button>
                    <?php endif; ?>
                    
                </div>
                
                <div class="table-container">
                    <?php if (mysqli_num_rows($result) > 0): ?>
                    <table>
                        <thead><tr><th>Customer</th><th>Details</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; font-size:15px;"><?php echo htmlspecialchars($row['name']); ?> <?php echo htmlspecialchars($row['last_name'] ?? ''); ?></div>
                                    <small style="color:#999;">ID: #<?php echo $row['id']; ?></small>
                                </td>
                                <td>
                                    <div style="color: #6327af; font-family: monospace; font-size:14px;"><i class="material-icons" style="font-size:12px; vertical-align:middle;">phone</i> <?php echo htmlspecialchars($row['mobile']); ?></div>
                                </td>
                                <td style="font-weight:bold; color: #1e1b4b; font-size:16px;"><?php echo number_format($row['points']); ?></td>
                                <td><span style="background: #e0f2f1; color: #00695c; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: bold;"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                <td>
                                    <div class="btn-group">
                                        <button class="btn-icon btn-view" title="View Profile" onclick="openViewModal('<?php echo addslashes($row['name']); ?>', '<?php echo addslashes($row['last_name']); ?>', '<?php echo $row['mobile']; ?>', '<?php echo addslashes($row['email']); ?>', '<?php echo $row['gender']; ?>', '<?php echo $row['dob']; ?>', '<?php echo $row['tier']; ?>', '<?php echo $row['created_at']; ?>')"><i class="material-icons" style="font-size:18px;">visibility</i></button>
                                        <button class="btn-icon btn-hist" title="History" onclick="openHistoryModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>')"><i class="material-icons" style="font-size:18px;">history</i></button>
                                        
                                        <?php if(!$is_viewer): ?>
                                            <button class="btn-icon btn-adjust" onclick="openRedeemModal(<?php echo $row['id']; ?>, '<?php echo addslashes($row['name']); ?>', '<?php echo $row['mobile']; ?>', <?php echo $row['points']; ?>)"><i class="material-icons" style="font-size:16px; vertical-align:middle; margin-right:5px;">tune</i> Adjust Pts</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <div style="text-align:center; padding: 40px; color: #888;">No users found.</div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="redeemModal" class="modal">
        <div class="modal-content">
            <span style="float:right; cursor:pointer;" onclick="closeModal('redeemModal')"><i class="material-icons">close</i></span>
            <h2 id="modalTitle" style="color:#6327af; margin-top:0;">Adjust Points</h2>
            
            <form method="POST" id="adjForm">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <div id="manualInputFields" style="display:none; background: #f5f3ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin:0 0 15px 0; color:#6327af; border-bottom:1px solid #ddd; padding-bottom:10px;">Full Registration</h4>
                    <div class="form-row">
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">First Name *</label><input type="text" name="manual_fname" id="manualFname" class="form-input" placeholder="First Name"></div>
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">Last Name</label><input type="text" name="manual_lname" id="manualLname" class="form-input" placeholder="Last Name"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">Mobile *</label><input type="number" name="manual_mobile" id="manualMobile" class="form-input" oninput="validatePhone(this.value)"></div>
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">Email</label><input type="email" name="manual_email" id="manualEmail" class="form-input"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">Gender</label><select name="manual_gender" class="form-input"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                        <div class="form-col"><label style="font-size:12px; font-weight:600;">DOB</label><input type="date" name="manual_dob" class="form-input"></div>
                    </div>
                    <label style="font-size:12px; font-weight:600;">Set Password *</label><input type="text" name="manual_password" id="manualPassword" class="form-input">
                </div>

                <div id="existingUserFields">
                    <div class="balance-card"><span>Current Balance</span><span style="font-size: 18px;"><span id="currentBalance">0</span> Pts</span></div>
                    <label style="font-size:12px; font-weight:600;">Registered Mobile</label><input type="text" id="modalMobileDisplay" class="form-input" readonly style="background:#e8eaf6;">
                </div>

                <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">

                <div class="transaction-type-group">
                    <label class="type-option"><input type="radio" name="adj_type" value="Earned" checked onchange="toggleInputFields()"><span class="type-btn"><i class="material-icons" style="vertical-align:middle;">add_circle</i> Add (+)</span></label>
                    <label class="type-option"><input type="radio" name="adj_type" value="Redeemed" onchange="toggleInputFields()"><span class="type-btn"><i class="material-icons" style="vertical-align:middle;">remove_circle</i> Deduct (-)</span></label>
                </div>

                <div id="billAmountContainer">
                    <label style="font-size:12px; font-weight:600;">Bill Amount (₹)</label><input type="number" name="bill_amount" id="billAmount" class="form-input" oninput="calcPoints(this.value)">
                    <div id="calcBox" style="background:#e8f5e9; padding:10px; border-radius:8px; margin-bottom:15px; display:none; color:#2e7d32;"><span>Calculated: <span id="calcVal">0</span> Pts</span></div>
                </div>

                <div id="pointsInputContainer" style="display:none;">
                    <label style="font-size:12px; font-weight:600; color:#c62828;">Points to Deduct</label><input type="number" id="manualPoints" class="form-input" oninput="updateManualPoints(this.value)">
                </div>
                
                <input type="hidden" name="points_val" id="finalPointsInput">
                
                <div class="form-row">
                    <div class="form-col"><label style="font-size:12px; font-weight:600;">Bill Ref #</label><input type="text" name="bill_ref" class="form-input" placeholder="INV-001" required></div>
                    <div class="form-col"><label style="font-size:12px; font-weight:600;">Reason</label><input type="text" name="reason" class="form-input" value="In-Store Purchase" required></div>
                </div>

                <button type="submit" name="adjust_points" id="submitBtn" class="confirm-btn">Confirm</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span style="float:right; cursor:pointer;" onclick="closeModal('viewModal')"><i class="material-icons">close</i></span>
            <h2 style="color:#1565c0; margin-top:0;">Profile</h2>
            <div style="background:#f5f5f5; padding:15px; border-radius:8px; margin-bottom:15px;">
                <h3 id="viewName" style="margin:0; color:#333;"></h3>
            </div>
            <div class="form-row"><div class="form-col"><b>Mobile</b><p id="viewMobile"></p></div><div class="form-col"><b>Email</b><p id="viewEmail"></p></div></div>
            <div class="form-row"><div class="form-col"><b>Gender</b><p id="viewGender"></p></div><div class="form-col"><b>DOB</b><p id="viewDob"></p></div></div>
            <div style="margin-top:10px;"><b>Joined</b><p id="viewJoined"></p></div>
        </div>
    </div>

    <div id="historyModal" class="modal">
        <div class="modal-content" style="max-width:700px;">
            <span style="float:right; cursor:pointer;" onclick="closeModal('historyModal')"><i class="material-icons">close</i></span>
            <h2 style="color:#ef6c00; margin-top:0;">History</h2>
            <h4 id="historyUserName" style="margin:0 0 15px 0; color:#666;"></h4>
            <div id="historyContent" style="max-height:400px; overflow-y:auto; border:1px solid #eee; border-radius:8px;"></div>
        </div>
    </div>

    <script>
        // --- TOAST LOGIC ---
        function showToast(message, type = 'success') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            let icon = 'check_circle';
            if(type === 'warning') icon = 'info';
            if(type === 'error') icon = 'error';

            toast.innerHTML = `<i class="material-icons">${icon}</i><span>${message}</span>`;
            
            container.appendChild(toast);
            
            // Remove after 3 seconds
            setTimeout(() => {
                toast.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => toast.remove(), 500);
            }, 3000);
        }

        // Check for PHP Session Toast
        <?php if (isset($_SESSION['toast'])): ?>
            showToast("<?php echo $_SESSION['toast']['message']; ?>", "<?php echo $_SESSION['toast']['type']; ?>");
            <?php unset($_SESSION['toast']); ?>
        <?php endif; ?>

        // --- GLOBAL JS ---
        function toggleInputFields() {
            const isEarned = document.querySelector('input[name="adj_type"]:checked').value === 'Earned';
            const btn = document.getElementById('submitBtn');
            if (isEarned) {
                document.getElementById('billAmountContainer').style.display = 'block';
                document.getElementById('pointsInputContainer').style.display = 'none';
                if(btn) { btn.style.backgroundColor = '#6327af'; btn.innerText = "Confirm Add (+)"; }
            } else {
                document.getElementById('billAmountContainer').style.display = 'none';
                document.getElementById('pointsInputContainer').style.display = 'block';
                if(btn) { btn.style.backgroundColor = '#c62828'; btn.innerText = "Confirm Deduct (-)"; }
            }
        }
        function calcPoints(amt) {
            let p = (amt > 0) ? Math.floor(amt * 0.01) : 0;
            document.getElementById('calcVal').innerText = p;
            document.getElementById('finalPointsInput').value = p;
            document.getElementById('calcBox').style.display = (amt > 0) ? 'block' : 'none';
        }
        function updateManualPoints(pts) { document.getElementById('finalPointsInput').value = pts; }
        
        function openRedeemModal(id, name, mobile, points) {
            let modal = document.getElementById('redeemModal');
            if(!modal) return; // Prevent errors for viewers
            
            document.getElementById('modalUserId').value = id;
            document.getElementById('modalTitle').innerText = "Adjust: " + name;
            document.getElementById('modalMobileDisplay').value = mobile;
            document.getElementById('currentBalance').innerText = points;
            document.getElementById('manualInputFields').style.display = 'none';
            document.getElementById('existingUserFields').style.display = 'block';
            toggleRegReq(false);
            document.querySelector('input[name="adj_type"][value="Earned"]').checked = true;
            toggleInputFields();
            modal.style.display = 'block';
        }

        function openManualEntryModal() {
            let modal = document.getElementById('redeemModal');
            if(!modal) return; // Prevent errors for viewers
            
            document.getElementById('modalUserId').value = 0;
            document.getElementById('modalTitle').innerText = "Register New Customer";
            document.getElementById('manualInputFields').style.display = 'block';
            document.getElementById('existingUserFields').style.display = 'none';
            toggleRegReq(true);
            document.querySelector('input[name="adj_type"][value="Earned"]').checked = true;
            toggleInputFields();
            modal.style.display = 'block';
        }

        function toggleRegReq(req) {
            let fname = document.getElementById('manualFname');
            let mobile = document.getElementById('manualMobile');
            let pass = document.getElementById('manualPassword');
            if(fname) fname.required = req;
            if(mobile) mobile.required = req;
            if(pass) pass.required = req;
        }

        function openViewModal(fname, lname, mobile, email, gender, dob, tier, created) {
            document.getElementById('viewName').innerText = fname + " " + lname;
            document.getElementById('viewMobile').innerText = mobile;
            document.getElementById('viewEmail').innerText = email || 'N/A';
            document.getElementById('viewGender').innerText = gender || '-';
            document.getElementById('viewDob').innerText = dob || '-';
            document.getElementById('viewJoined').innerText = created;
            document.getElementById('viewModal').style.display = 'block';
        }

        function openHistoryModal(userId, name) {
            document.getElementById('historyUserName').innerText = "Customer: " + name;
            document.getElementById('historyModal').style.display = 'block';
            document.getElementById('historyContent').innerHTML = '<div style="padding:20px; text-align:center;">Loading...</div>';

            const formData = new FormData();
            formData.append('action', 'fetch_history');
            formData.append('user_id', userId);

            fetch('points.php', { method: 'POST', body: formData })
                .then(response => response.text())
                .then(html => { document.getElementById('historyContent').innerHTML = html; })
                .catch(err => { document.getElementById('historyContent').innerHTML = '<div style="color:red; padding:20px;">Error loading history.</div>'; });
        }

        function validatePhone(val) {
            const btn = document.getElementById('submitBtn');
            if(!btn) return;
            if(document.getElementById('modalUserId').value == 0 && val.length !== 10) {
                btn.disabled = true; btn.style.opacity = '0.5';
            } else {
                btn.disabled = false; btn.style.opacity = '1';
            }
        }

        function closeModal(id) { 
            let modal = document.getElementById(id);
            if(modal) {
                modal.style.display = 'none'; 
                if(id === 'redeemModal') document.getElementById('adjForm').reset();
            }
        }
    </script>
</body>
</html>