<?php
// --- 1. INCLUDE DATABASE CONNECTION ---
include 'db_connect.php';
session_start();

// --- NEW AUTH GUARD (Allows both Admin and Viewer) ---
$is_admin = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$is_viewer = (isset($_SESSION['viewer_logged_in']) && $_SESSION['viewer_logged_in'] === true);

if (!$is_admin && !$is_viewer) {
    header("Location: index.php");
    exit;
}

// --- 2. HANDLE ACTIONS (Add / Accept / Deactivate) ---
$msg = "";

// A. Handle Add New User
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_user'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to add users.");
    }

    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $mobile = $conn->real_escape_string($_POST['mobile']);
    $shop = $conn->real_escape_string($_POST['shop_name']);
    
    // Insert main user data
    $insertSql = "INSERT INTO users (name, email, mobile, shop_number, tier, points, status) 
                  VALUES ('$name', '$email', '$mobile', '$shop', 'Silver', 0, 'Active')";
    
    if ($conn->query($insertSql)) {
        $new_user_id = $conn->insert_id;
        
        // Insert bank details into related table
        $acc_no = $conn->real_escape_string($_POST['account_number']);
        $bank_name = $conn->real_escape_string($_POST['bank_name']);
        $ifsc = $conn->real_escape_string($_POST['ifsc_code']);
        $acc_type = $conn->real_escape_string($_POST['account_type']);
        
        $bankSql = "INSERT INTO user_bank_details (user_id, account_number, bank_name, ifsc_code, account_type) 
                    VALUES ('$new_user_id', '$acc_no', '$bank_name', '$ifsc', '$acc_type')";
        $conn->query($bankSql);

        header("Location: users.php?msg=User Added Successfully");
        exit();
    }
}

// B. Handle Status Changes
if (isset($_GET['action']) && isset($_GET['id'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to change user status.");
    }

    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action == 'accept') {
        $conn->query("UPDATE users SET status='Active' WHERE id=$id");
    } elseif ($action == 'deactivate') {
        $conn->query("UPDATE users SET status='Inactive' WHERE id=$id");
    }
    header("Location: users.php");
    exit();
}

// --- 3. FETCH DATA (Joined with Bank Details) ---
$sql = "SELECT u.*, b.account_number, b.bank_name, b.ifsc_code, b.account_type 
        FROM users u 
        LEFT JOIN user_bank_details b ON u.id = b.user_id 
        ORDER BY u.id DESC";
$result = $conn->query($sql);

// --- 4. FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TTJ Users Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
    .btn-action { padding: 6px 12px; font-size: 12px; margin-right: 5px; border: none; cursor: pointer; color: white; border-radius: 4px; text-decoration: none; display: inline-block; font-weight: 600; }
    .btn-accept { background-color: #28a745; } 
    .btn-deactivate { background-color: #dc3545; }
    .btn-view { background-color: #17a2b8; } 
    
    /* Modal Styles */
    .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
    .modal-content { background-color: #fff; margin: 4% auto; padding: 25px; border-radius: 12px; width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
    .close { float: right; font-size: 24px; cursor: pointer; color: #6327af; }
    .form-group { margin-bottom: 12px; }
    .form-group label { display: block; margin-bottom: 4px; font-weight: 600; color: #555; font-size: 13px; }
    .form-input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
    
    .doc-btn { display: block; width: 100%; padding: 10px; margin-top: 10px; background: #f4f4f9; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #6327af; font-weight: 600; text-align: center; font-size: 13px; cursor: pointer; }
    .doc-btn:hover { background: #eee; }

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
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>User Management</h1> 
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
          <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
      </div>

      <section class="content-section container-constrain">
        <div class="section-header">
          <h2>All Users</h2>
          
          <?php if(!$is_viewer): ?>
              <button class="btn btn-primary" style="background:#6327af;" onclick="openAddModal()">Add New User</button>
          <?php endif; ?>
          
        </div>

        <div class="table-container" style="margin-top:12px;">
          <table>
            <thead>
              <tr>
                <th>Name</th><th>Shop Name</th><th>Mobile</th><th>Tier</th><th>Points</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                  <?php while($row = $result->fetch_assoc()): 
                      $tier_class = 'badge-silver';
                      if (strtolower($row['tier']) == 'platinum') $tier_class = 'badge-platinum';
                      elseif (strtolower($row['tier']) == 'gold') $tier_class = 'badge-gold';
                      $jsonData = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                  ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong><br>
                        <small style='color:#777'><?php echo htmlspecialchars($row['email']); ?></small></td>
                    <td><?php echo htmlspecialchars($row['shop_number'] ?: 'Peelamedu Branch'); ?></td>
                    <td><?php echo htmlspecialchars($row['mobile']); ?></td>
                    <td><span class='badge <?php echo $tier_class; ?>'><?php echo htmlspecialchars($row['tier']); ?></span></td>
                    <td><?php echo number_format($row['points']); ?></td>
                    <td style="color: <?php echo ($row['status'] == 'Active') ? 'green' : 'red'; ?>; font-weight:bold;"><?php echo $row['status']; ?></td>
                    <td>
                      <button class='btn-action btn-view' onclick='viewUser(<?php echo $jsonData; ?>)'>Check</button>
                      
                      <?php if(!$is_viewer): ?>
                          <?php if ($row['status'] == 'Active'): ?>
                              <a href='users.php?action=deactivate&id=<?php echo $row['id']; ?>' class='btn-action btn-deactivate' onclick='return confirm("Deactivate user?")'>Deactivate</a>
                          <?php else: ?>
                              <a href='users.php?action=accept&id=<?php echo $row['id']; ?>' class='btn-action btn-accept'>Accept</a>
                          <?php endif; ?>
                      <?php endif; ?>

                    </td>
                  </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                  <tr><td colspan="7" style="text-align:center;">No users found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="addModal" class="modal">
      <div class="modal-content">
        <span class="close" onclick="closeAddModal()">&times;</span>
        <h2 style="color:#6327af; margin-top:0;">Create New User Account</h2>
        <hr>
        <form method="POST" style="margin-top:15px;">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Email Address</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Mobile</label>
                    <input type="text" name="mobile" class="form-input" required>
                </div>
            </div>
            <div class="form-group">
                <label>Shop Name</label>
                <input type="text" name="shop_name" class="form-input" value="Peelamedu Branch">
            </div>
            <h4 style="color:#6327af; margin:15px 0 5px 0;">Bank Details</h4>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>Account Number</label>
                    <input type="text" name="account_number" class="form-input">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" class="form-input">
                </div>
            </div>
            <div style="display:flex; gap:10px;">
                <div class="form-group" style="flex:1;">
                    <label>IFSC Code</label>
                    <input type="text" name="ifsc_code" class="form-input">
                </div>
                <div class="form-group" style="flex:1;">
                    <label>Account Type</label>
                    <input type="text" name="account_type" class="form-input" placeholder="Savings / Current">
                </div>
            </div>
            <br>
            <button type="submit" name="add_user" class="btn btn-primary" style="width:100%; height:45px; background:#6327af;">Register User</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div id="userModal" class="modal">
      <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h2 style="color:#6327af; margin-top:0;">User Profile & Documents</h2>
        <hr>
        <div id="modalBody" style="margin-top:15px; font-size:14px;"></div>
        
        <div style="margin-top:20px;">
            <strong style="color:#6327af;">Uploaded Documents:</strong>
            <div id="docLinks" style="margin-top:10px;"></div>
        </div>

        <div style="margin-top:25px; text-align:right;">
             <button class="btn btn-primary" style="background:#6327af; width:100%; padding:12px;" onclick="closeModal()">Close</button>
        </div>
      </div>
    </div>

    <script>
      // Pass PHP role boolean to JavaScript
      const isViewerRole = <?php echo $is_viewer ? 'true' : 'false'; ?>;

      function openAddModal() { 
          let modal = document.getElementById("addModal");
          if(modal) modal.style.display = "block"; 
      }
      function closeAddModal() { 
          let modal = document.getElementById("addModal");
          if(modal) modal.style.display = "none"; 
      }

      function viewUser(data) {
          // Display Profile and Bank Info
          document.getElementById("modalBody").innerHTML = `
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Name:</strong> ${data.name}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Email:</strong> ${data.email}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Mobile:</strong> ${data.mobile}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Shop Name:</strong> ${data.shop_number || 'Peelamedu Branch'}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0; color:#6327af;"><strong>Bank:</strong> ${data.bank_name || 'N/A'}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Account No:</strong> ${data.account_number || 'N/A'}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>IFSC Code:</strong> ${data.ifsc_code || 'N/A'}</div>
              <div style="border-bottom:1px solid #eee; padding:8px 0;"><strong>Tier:</strong> ${data.tier}</div>
              <div style="padding:8px 0;"><strong>Current Points:</strong> ${data.points}</div>
          `;

          // Check role and display Document Links accordingly
          let docs = "";
          
          if (data.aadhar_file) {
              if (isViewerRole) {
                  docs += `<a href="javascript:void(0);" onclick="alert('Restricted: Viewers cannot access identity documents.');" class="doc-btn" style="background:#ffebee; color:#c62828; cursor:not-allowed; border-color:#ffcdd2;">View Aadhar Card (Locked)</a>`;
              } else {
                  docs += `<a href="${data.aadhar_file}" target="_blank" class="doc-btn">View Aadhar Card</a>`;
              }
          }
          if (data.pancard_file) {
              if (isViewerRole) {
                  docs += `<a href="javascript:void(0);" onclick="alert('Restricted: Viewers cannot access identity documents.');" class="doc-btn" style="background:#ffebee; color:#c62828; cursor:not-allowed; border-color:#ffcdd2;">View PAN Card (Locked)</a>`;
              } else {
                  docs += `<a href="${data.pancard_file}" target="_blank" class="doc-btn">View PAN Card</a>`;
              }
          }
          
          document.getElementById("docLinks").innerHTML = docs || "<p style='color:#999; font-style:italic;'>No documents uploaded.</p>";
          document.getElementById("userModal").style.display = "block";
      }

      function closeModal() { document.getElementById("userModal").style.display = "none"; }

      window.onclick = function(e) {
          if (e.target.className == "modal") {
              closeAddModal();
              closeModal();
          }
      }
    </script>
</body>
</html>
<?php $conn->close(); ?>