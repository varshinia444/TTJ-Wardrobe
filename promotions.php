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

// Handle Logout (Fallback just in case, though you are using logout.php)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: index.php");
    exit;
}

$msg = "";

// --- 1. HANDLE FORM SUBMISSIONS (Create & Update) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // BACKEND SECURITY: Block viewers from managing promotions
    if ($is_viewer) {
        die("Error: Viewers do not have permission to manage promotions.");
    }

    // Helper function to handle image upload
    function uploadPromotionImage($file) {
        if (isset($file) && $file['error'] == 0) {
            $target_dir = "ASSETS/image/promotions/";
            if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
            
            $file_ext = pathinfo($file["name"], PATHINFO_EXTENSION);
            $new_filename = "promo_" . time() . "." . $file_ext;
            $target_file = $target_dir . $new_filename;
            
            if (move_uploaded_file($file["tmp_name"], $target_file)) {
                return $target_file;
            }
        }
        return null;
    }

    // Handle Create New Campaign
    if (isset($_POST['create_campaign'])) {
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $type = mysqli_real_escape_string($conn, $_POST['type']);
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $multiplier = mysqli_real_escape_string($conn, $_POST['multiplier']);
        
        $image_path = uploadPromotionImage($_FILES['promo_image']);

        $today = date('Y-m-d');
        $status = ($today >= $start && $today <= $end) ? 'Active' : (($today > $end) ? 'Expired' : 'Scheduled');

        $sql = "INSERT INTO promotions (campaign_name, campaign_type, start_date, end_date, multiplier, status, image_path) 
                VALUES ('$name', '$type', '$start', '$end', '$multiplier', '$status', '$image_path')";

        if (mysqli_query($conn, $sql)) { $msg = "New campaign created successfully!"; }
    }

    // Handle Edit/Update Campaign
    if (isset($_POST['update_campaign'])) {
        $id = mysqli_real_escape_string($conn, $_POST['promo_id']);
        $name = mysqli_real_escape_string($conn, $_POST['name']);
        $type = mysqli_real_escape_string($conn, $_POST['type']);
        $start = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end = mysqli_real_escape_string($conn, $_POST['end_date']);
        $multiplier = mysqli_real_escape_string($conn, $_POST['multiplier']);

        $image_sql_part = "";
        $new_image = uploadPromotionImage($_FILES['promo_image']);
        if ($new_image) {
            $image_sql_part = ", image_path='$new_image'";
        }

        $today = date('Y-m-d');
        $status = ($today >= $start && $today <= $end) ? 'Active' : (($today > $end) ? 'Expired' : 'Scheduled');

        $update_sql = "UPDATE promotions SET 
                       campaign_name='$name', campaign_type='$type', start_date='$start', 
                       end_date='$end', multiplier='$multiplier', status='$status' $image_sql_part
                       WHERE id='$id'";

        if (mysqli_query($conn, $update_sql)) { $msg = "Campaign updated successfully!"; }
    }
}

// --- 2. FETCH CAMPAIGNS ---
$query = mysqli_query($conn, "SELECT * FROM promotions ORDER BY start_date ASC");

// --- 3. FETCH UNREAD ALERTS FOR RED DOT INDICATOR ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TTJ - Promotions Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600; }
      .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
      .modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 12px; width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-height: 90vh; overflow-y: auto; }
      .close-btn { float: right; cursor: pointer; font-size: 24px; color: #666; }
      .promo-img-preview { width: 50px; height: 50px; object-fit: cover; border-radius: 4px; border: 1px solid #ddd; }
      .modal-img-preview { width: 100%; height: 150px; object-fit: contain; margin-bottom: 10px; background: #f9f9f9; border-radius: 8px; display: none; }

      /* Notification Bell and Red Dot Styling */
      .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; margin-right: 15px; }
      .bell-container:hover { transform: scale(1.1); }
      .notification-dot { 
          position: absolute; top: 2px; right: 2px; 
          height: 10px; width: 10px; 
          background: #ff3b30; border-radius: 50%; 
          border: 2px solid white;
          box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
      }
      .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
      .logout-btn-header:hover { background: #c62828; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Promotions & Campaigns</h1>
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
            
            <a href="logout.php" class="logout-btn-header">Logout</a>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if($msg != "") echo "<div class='alert-success'>$msg</div>"; ?>

        <div class="section-header">
          <h2>Active Campaigns</h2>
          
          <?php if(!$is_viewer): ?>
              <button class="btn btn-primary" onclick="openPromoModal()">Create New Promotion</button>
          <?php endif; ?>
          
        </div>

        <div class="table-container" style="margin-top:12px;">
          <table>
            <thead>
              <tr>
                  <th>Image</th><th>Campaign Name</th><th>Type</th><th>Start Date</th><th>End Date</th><th>Bonus</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(mysqli_num_rows($query) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($query)): ?>
                  <tr>
                    <td>
                        <?php if($row['image_path']): ?>
                            <img src="<?php echo $row['image_path']; ?>" class="promo-img-preview">
                        <?php else: ?>
                            <div class="promo-img-preview" style="display:flex; align-items:center; justify-content:center; background:#eee;"><i class="material-icons" style="font-size:18px; color:#999;">image</i></div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['campaign_name']); ?></td>
                    <td><?php echo $row['campaign_type']; ?></td>
                    <td><?php echo date("M d, Y", strtotime($row['start_date'])); ?></td>
                    <td><?php echo date("M d, Y", strtotime($row['end_date'])); ?></td>
                    <td style="font-weight:700; color:var(--primary);"><?php echo $row['multiplier']; ?>x Points</td>
                    <td><span class="badge <?php echo ($row['status'] == 'Active') ? 'badge-gold' : 'badge-silver'; ?>"><?php echo $row['status']; ?></span></td>
                    <td>
                        <?php if(!$is_viewer): ?>
                            <button class="btn btn-primary" style="padding:6px 12px" 
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">Edit</button>
                        <?php else: ?>
                            <span style="color:#999; font-size:12px;">N/A</span>
                        <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="8" style="text-align:center;">No campaigns found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if(!$is_viewer): ?>
        <div id="promo-form-section" class="section-header" style="margin-top:40px;"><h2>Quick Create</h2></div>
        <form method="POST" action="promotions.php" enctype="multipart/form-data" style="background:white; padding:20px; border-radius:8px; border:1px solid #eee;">
            <div class="form-grid">
              <div><label>Campaign Name</label><input type="text" name="name" class="form-control" required></div>
              <div><label>Type</label><select name="type" class="form-control" required><option value="Festive">Festive</option><option value="Seasonal">Seasonal</option><option value="Flash Sale">Flash Sale</option></select></div>
              <div><label>Start Date</label><input type="date" name="start_date" class="form-control" required></div>
              <div><label>End Date</label><input type="date" name="end_date" class="form-control" required></div>
              <div><label>Points Multiplier</label><input type="number" name="multiplier" class="form-control" step="0.1" required></div>
              <div><label>Promotion Image</label><input type="file" name="promo_image" class="form-control" accept="image/*"></div>
            </div>
            <button type="submit" name="create_campaign" class="btn btn-primary" style="margin-top:20px; width:200px;">Create Campaign</button>
        </form>
        <?php endif; ?>
        
      </section>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="promoModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closePromoModal()">&times;</span>
            <h2 id="modalTitle">Add New Promotion</h2>
            <hr><br>
            <form method="POST" action="promotions.php" enctype="multipart/form-data">
                <input type="hidden" name="promo_id" id="edit_id">
                
                <img id="img_preview" class="modal-img-preview">
                
                <div style="margin-bottom:10px;"><label>Name</label><input type="text" name="name" id="edit_name" class="form-control" required></div>
                <div style="margin-bottom:10px;"><label>Type</label><select name="type" id="edit_type" class="form-control" required><option value="Festive">Festive</option><option value="Seasonal">Seasonal</option><option value="Flash Sale">Flash Sale</option></select></div>
                <div style="display:flex; gap:10px; margin-bottom:10px;">
                    <div style="flex:1;"><label>Start Date</label><input type="date" name="start_date" id="edit_start" class="form-control" required></div>
                    <div style="flex:1;"><label>End Date</label><input type="date" name="end_date" id="edit_end" class="form-control" required></div>
                </div>
                <div style="margin-bottom:10px;"><label>Points Multiplier</label><input type="number" name="multiplier" id="edit_multiplier" class="form-control" step="0.1" required></div>
                <div style="margin-bottom:20px;"><label>Change Image (Optional)</label><input type="file" name="promo_image" class="form-control" accept="image/*"></div>
                
                <button type="submit" name="create_campaign" id="submitBtn" class="btn btn-primary" style="width:100%; height:45px;">Save Promotion</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const modal = document.getElementById('promoModal');
        const imgPreview = document.getElementById('img_preview');
        
        function openPromoModal() {
            if(!modal) return; // Prevent error for viewers
            document.getElementById('modalTitle').innerText = "Add New Promotion";
            document.getElementById('submitBtn').name = "create_campaign";
            document.getElementById('edit_id').value = "";
            imgPreview.style.display = 'none';
            modal.style.display = 'block';
        }

        function openEditModal(data) {
            if(!modal) return; // Prevent error for viewers
            document.getElementById('modalTitle').innerText = "Edit Promotion";
            document.getElementById('submitBtn').name = "update_campaign";
            document.getElementById('submitBtn').innerText = "Update Promotion";
            
            // Populate fields
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.campaign_name;
            document.getElementById('edit_type').value = data.campaign_type;
            document.getElementById('edit_start').value = data.start_date;
            document.getElementById('edit_end').value = data.end_date;
            document.getElementById('edit_multiplier').value = data.multiplier;
            
            if(data.image_path) {
                imgPreview.src = data.image_path;
                imgPreview.style.display = 'block';
            } else {
                imgPreview.style.display = 'none';
            }
            
            modal.style.display = 'block';
        }

        function closePromoModal() { if(modal) modal.style.display = 'none'; }
        window.onclick = (e) => { if(e.target == modal) closePromoModal(); }
    </script>
</body>
</html>