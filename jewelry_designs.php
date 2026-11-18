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

$msg = "";
$msg_type = "";

// --- HELPER: Image Upload Function ---
function uploadJewelryImage($file, $type = 'main') {
    if (isset($file) && $file['error'] == 0) {
        $target_dir = "ASSETS/image/jewelry/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($file_ext, $allowed)) {
            return false;
        }

        $prefix = ($type == 'main') ? 'main_' : 'extra_';
        $new_filename = $prefix . time() . "_" . rand(1000,9999) . "." . $file_ext;
        $target_file = $target_dir . $new_filename;

        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        }
    }
    return false;
}

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to add or edit designs.");
    }

    // CREATE NEW DESIGN
    if (isset($_POST['create_design'])) {
        $design_name = mysqli_real_escape_string($conn, $_POST['design_name']);
        $category    = mysqli_real_escape_string($conn, $_POST['category']);
        $price       = mysqli_real_escape_string($conn, $_POST['price']);

        if (empty($_FILES['main_image']['name'])) {
            $msg = "Main image is required!";
            $msg_type = "error";
        } else {
            $main_image = uploadJewelryImage($_FILES['main_image'], 'main');
            if (!$main_image) {
                $msg = "Failed to upload main image.";
                $msg_type = "error";
            } else {
                $image2 = !empty($_FILES['image2']['name']) ? uploadJewelryImage($_FILES['image2'], 'extra') : null;
                $image3 = !empty($_FILES['image3']['name']) ? uploadJewelryImage($_FILES['image3'], 'extra') : null;

                $sql = "INSERT INTO jewelry_designs 
                        (design_name, category, price, main_image_path, image2_path, image3_path)
                        VALUES ('$design_name', '$category', '$price', '$main_image', " .
                        ($image2 ? "'$image2'" : "NULL") . ", " .
                        ($image3 ? "'$image3'" : "NULL") . ")";

                if (mysqli_query($conn, $sql)) {
                    header("Location: jewelry_designs.php?msg=created");
                    exit();
                } else {
                    $msg = "Database error: " . mysqli_error($conn);
                    $msg_type = "error";
                }
            }
        }
    }

    // UPDATE EXISTING DESIGN
    if (isset($_POST['update_design'])) {
        $id = mysqli_real_escape_string($conn, $_POST['design_id']);
        $design_name = mysqli_real_escape_string($conn, $_POST['design_name']);
        $category    = mysqli_real_escape_string($conn, $_POST['category']);
        $price       = mysqli_real_escape_string($conn, $_POST['price']);

        $updates = [
            "design_name='$design_name'",
            "category='$category'",
            "price='$price'"
        ];

        if (!empty($_FILES['main_image']['name'])) {
            $new_main = uploadJewelryImage($_FILES['main_image'], 'main');
            if ($new_main) $updates[] = "main_image_path='$new_main'";
        }
        if (!empty($_FILES['image2']['name'])) {
            $new2 = uploadJewelryImage($_FILES['image2'], 'extra');
            if ($new2) $updates[] = "image2_path='$new2'";
        }
        if (!empty($_FILES['image3']['name'])) {
            $new3 = uploadJewelryImage($_FILES['image3'], 'extra');
            if ($new3) $updates[] = "image3_path='$new3'";
        }

        $sql = "UPDATE jewelry_designs SET " . implode(", ", $updates) . " WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            header("Location: jewelry_designs.php?msg=updated");
            exit();
        } else {
            $msg = "Error updating: " . mysqli_error($conn);
            $msg_type = "error";
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete'])) {
    
    // BACKEND SECURITY: Block viewers
    if ($is_viewer) {
        die("Error: Viewers do not have permission to delete designs.");
    }

    $id = mysqli_real_escape_string($conn, $_GET['delete']);
    
    $sql = "DELETE FROM jewelry_designs WHERE id='$id'";
    if (mysqli_query($conn, $sql)) {
        header("Location: jewelry_designs.php?msg=deleted");
        exit();
    } else {
        $msg = "Error deleting design.";
        $msg_type = "error";
    }
}

// --- Handle messages from redirect ---
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'created':
            $msg = "New jewellery design added successfully!";
            $msg_type = "success";
            break;
        case 'updated':
            $msg = "Design updated successfully!";
            $msg_type = "success";
            break;
        case 'deleted':
            $msg = "Design deleted successfully!";
            $msg_type = "success";
            break;
    }
}

// --- FETCH DESIGNS ---
$query = mysqli_query($conn, "SELECT * FROM jewelry_designs ORDER BY id DESC");

// --- FETCH UNREAD ALERTS FOR TOP BAR RED DOT ---
$header_alert_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$header_alert_data = mysqli_fetch_assoc($header_alert_query);
$header_unread_count = $header_alert_data['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>TTJ - Jewellery Designs Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; font-weight: 600; }
      .alert-error { padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
      .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
      .modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 12px; width: 600px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
      .close-btn { float: right; cursor: pointer; font-size: 28px; color: #666; }
      .img-preview { width: 100%; max-height: 200px; object-fit: contain; margin: 10px 0; background: #f9f9f9; border-radius: 8px; display: none; }
      .thumb { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 2px solid #eee; margin: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
      .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
      .btn-delete { background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; }
      .btn-delete:hover { background: #c82333; }

      /* Notification Bell and Red Dot Styling */
      .bell-container { position: relative; cursor: pointer; display: flex; align-items: center; transition: transform 0.2s; margin-right: 15px; }
      .bell-container:hover { transform: scale(1.1); }
      .notification-dot { 
          position: absolute; top: 2px; right: 2px; 
          height: 10px; width: 10px; 
          background: #ff3b30; color: white; border-radius: 50%; 
          border: 2px solid white;
          box-shadow: 0 0 5px rgba(255, 59, 48, 0.5);
      }
      .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
      .logout-btn-header:hover { background: #c62828; }

      @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Jewellery Designs Management</h1>
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
            
            <a href="?action=logout" class="logout-btn-header" onclick="return confirm('Are you sure you want to log out?');">Logout</a>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if($msg != ""): ?>
          <div class="alert-<?php echo $msg_type; ?>">
            <?php echo $msg; ?>
          </div>
        <?php endif; ?>

        <div class="section-header">
          <h2>All Designs</h2>
          
          <?php if(!$is_viewer): ?>
              <button class="btn btn-primary" onclick="openModal()">Add New Design</button>
          <?php endif; ?>
          
        </div>

        <div class="table-container" style="margin-top:12px;">
          <table>
            <thead>
              <tr>
                <th>Images</th>
                <th>Category</th>
                <th>Price</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if(mysqli_num_rows($query) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($query)): ?>
                  <tr>
                    <td style="padding:15px;">
                      <img src="<?php echo htmlspecialchars($row['main_image_path']); ?>" class="thumb" alt="Main">
                      <?php if($row['image2_path']): ?>
                        <img src="<?php echo htmlspecialchars($row['image2_path']); ?>" class="thumb">
                      <?php endif; ?>
                      <?php if($row['image3_path']): ?>
                        <img src="<?php echo htmlspecialchars($row['image3_path']); ?>" class="thumb">
                      <?php endif; ?>
                    </td>
                    <td style="vertical-align:middle; font-weight:600;">
                      <?php echo htmlspecialchars($row['category']); ?>
                    </td>
                    <td style="vertical-align:middle; font-weight:700; color:var(--primary);">
                      ₹<?php echo number_format($row['price'], 2); ?>
                    </td>
                    <td style="vertical-align:middle;">
                        
                      <?php if(!$is_viewer): ?>
                          <button class="btn btn-primary" style="padding:8px 16px; margin-right:8px;" 
                            onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                            Edit
                          </button>
                          <button class="btn-delete" 
                            onclick="if(confirm('Are you sure you want to delete this design?')) window.location.href='jewelry_designs.php?delete=<?php echo $row['id']; ?>'">
                            Delete
                          </button>
                      <?php else: ?>
                          <span style="color:#999; font-size:12px;">N/A</span>
                      <?php endif; ?>
                      
                    </td>
                  </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="4" style="text-align:center; padding:40px;">No designs found. Click "Add New Design" to upload one.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <?php if(!$is_viewer): ?>
    <div id="designModal" class="modal">
      <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h2 id="modalTitle">Add New Jewellery Design</h2>
        <hr><br>

        <form method="POST" action="jewelry_designs.php" enctype="multipart/form-data">
          <input type="hidden" name="design_id" id="edit_id">

          <div class="form-grid">
            <div>
              <label>Internal Name (for reference)</label>
              <input type="text" name="design_name" id="edit_name" class="form-control" placeholder="e.g. Floral Gold Ring 2025" required>
            </div>
            <div>
              <label>Category</label>
              <select name="category" id="edit_category" class="form-control" required>
                <option value="">Select Category</option>
                <option>Ring</option>
                <option>Necklace</option>
                <option>Earring</option>
                <option>Bracelet</option>
                <option>Pendant</option>
                <option>Bangle</option>
                <option>Other</option>
              </select>
            </div>
            <div>
              <label>Price (₹)</label>
              <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
            </div>
          </div>

          <div style="margin:25px 0;">
            <label>Main Image <span style="color:red;">*</span></label>
            <input type="file" name="main_image" class="form-control" accept="image/*">
            <img id="main_preview" class="img-preview">
          </div>

          <div style="margin-bottom:15px;">
            <label>Additional Image 1 (Optional)</label>
            <input type="file" name="image2" class="form-control" accept="image/*">
            <img id="img2_preview" class="img-preview">
          </div>

          <div style="margin-bottom:30px;">
            <label>Additional Image 2 (Optional)</label>
            <input type="file" name="image3" class="form-control" accept="image/*">
            <img id="img3_preview" class="img-preview">
          </div>

          <button type="submit" name="create_design" id="submitBtn" class="btn btn-primary" style="width:100%; height:50px; font-size:16px;">
            Add Design
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <script>
      const modal = document.getElementById('designModal');
      const mainPrev = document.getElementById('main_preview');
      const img2Prev = document.getElementById('img2_preview');
      const img3Prev = document.getElementById('img3_preview');

      function openModal() {
        if(!modal) return; // Prevent error for viewers
        document.getElementById('modalTitle').innerText = "Add New Jewellery Design";
        document.getElementById('submitBtn').name = "create_design";
        document.getElementById('submitBtn').innerText = "Add Design";
        document.querySelector('form').reset();
        document.getElementById('edit_id').value = "";
        mainPrev.style.display = img2Prev.style.display = img3Prev.style.display = 'none';
        modal.style.display = 'block';
      }

      function openEditModal(data) {
        if(!modal) return; // Prevent error for viewers
        document.getElementById('modalTitle').innerText = "Edit Jewellery Design";
        document.getElementById('submitBtn').name = "update_design";
        document.getElementById('submitBtn').innerText = "Update Design";

        document.getElementById('edit_id').value = data.id;
        document.getElementById('edit_name').value = data.design_name || '';
        document.getElementById('edit_category').value = data.category;
        document.getElementById('edit_price').value = data.price;

        if (data.main_image_path) { mainPrev.src = data.main_image_path; mainPrev.style.display = 'block'; }
        if (data.image2_path) { img2Prev.src = data.image2_path; img2Prev.style.display = 'block'; }
        if (data.image3_path) { img3Prev.src = data.image3_path; img3Prev.style.display = 'block'; }

        modal.style.display = 'block';
      }

      function closeModal() { if(modal) modal.style.display = 'none'; }
      window.onclick = (e) => { if (e.target == modal) closeModal(); }

      // Auto-hide success message after 5 seconds
      <?php if ($msg && $msg_type === 'success'): ?>
        setTimeout(() => {
          const alert = document.querySelector('.alert-success');
          if (alert) {
             alert.style.transition = 'opacity 0.5s'; 
             alert.style.opacity = '0';
             setTimeout(() => alert.remove(), 500);
          }
        }, 5000);
      <?php endif; ?>
    </script>
</body>
</html>