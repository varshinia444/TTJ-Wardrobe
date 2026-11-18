<?php
include('db_connect.php');

// --- 1. INTEGRATED UPDATE LOGIC ---
// This block handles the POST request when the 'Update Configuration' button is clicked
if (isset($_POST['update_scheme'])) {
    $plan_id_update = mysqli_real_escape_string($conn, $_POST['plan_id']);
    $name = mysqli_real_escape_string($conn, $_POST['plan_name']);
    $duration = mysqli_real_escape_string($conn, $_POST['duration']);
    // min_amount is removed from this logic to prevent database fatal errors
    $penalty = mysqli_real_escape_string($conn, $_POST['penalty']);
    $benefit = mysqli_real_escape_string($conn, $_POST['benefit']);
    $desc = mysqli_real_escape_string($conn, $_POST['description']);
    $note = mysqli_real_escape_string($conn, $_POST['note_content']); 
    $color = mysqli_real_escape_string($conn, $_POST['border_color']);

    // FIXED SQL: note_content and border_color are included, min_amount is excluded
    $update_sql = "UPDATE scheme_plans SET 
                plan_name = '$name', 
                duration = '$duration', 
                penalty = '$penalty', 
                benefit = '$benefit', 
                description = '$desc',
                note_content = '$note',
                border_color = '$color' 
                WHERE id = '$plan_id_update'";

    if (mysqli_query($conn, $update_sql)) {
        $msg = "Scheme configuration updated successfully!";
    } else {
        $error = "Error updating record: " . mysqli_error($conn);
    }
}

// --- 2. DATA FETCHING ---
// Get the current plan ID from the URL parameter
$plan_id = isset($_GET['plan_id']) ? mysqli_real_escape_string($conn, $_GET['plan_id']) : 1;

// Fetch current Plan details for the Header and Rules display
$plan_query = mysqli_query($conn, "SELECT * FROM scheme_plans WHERE id = '$plan_id' LIMIT 1");
$plan = mysqli_fetch_assoc($plan_query);

// Fetch all users currently enrolled in this specific plan
$enroll_sql = "SELECT e.*, u.name as user_name 
               FROM scheme_enrollments e 
               JOIN users u ON e.user_id = u.id 
               WHERE e.plan_id = '$plan_id' 
               ORDER BY e.start_date DESC";
$enroll_result = mysqli_query($conn, $enroll_sql);
$enrolled_count = mysqli_num_rows($enroll_result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TTJ - Scheme Details: <?php echo htmlspecialchars($plan['plan_name'] ?? 'Plan'); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      /* Integrated Modal Styling to match the Admin Dashboard */
      .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
      .modal-content { background-color: white; margin: 3% auto; padding: 25px; border-radius: 12px; width: 600px; box-shadow: 0 5px 20px rgba(0,0,0,0.3); overflow-y: auto; max-height: 85vh; }
      .form-group { margin-bottom: 15px; }
      .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
      .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-family: inherit; }
      textarea.form-input { resize: vertical; min-height: 80px; font-size: 13px; line-height: 1.5; }
      
      /* Alerts and Buttons */
      .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; border: 1px solid #c3e6cb; }
      .alert-danger { padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb; }
      
      /* FIXED: High-visibility Submit Button */
      .update-btn { width: 100%; height: 50px; background: #6327af; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 16px; margin-top: 10px; transition: background 0.3s; }
      .update-btn:hover { background: #4d1d8a; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <div style="display:flex; align-items:center; gap:10px;">
            <a href="schemes.php" style="color:#666; text-decoration:none; display:flex; align-items:center;">
                <span class="material-icons">arrow_back</span> Back
            </a>
            <h1 style="margin:0;">Scheme Details: <?php echo htmlspecialchars($plan['plan_name'] ?? 'Not Found'); ?></h1>
        </div>
        <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
          <div class="user-avatar" style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">A</div>
          <span>Admin</span>
          <button class="logout-btn" onclick="window.location.href='logout.php'" style="background:#dc3545; color:white; border:none; padding:8px 15px; border-radius:6px; cursor:pointer;">Logout</button>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if(isset($msg)) echo "<div class='alert-success'>$msg</div>"; ?>
        <?php if(isset($error)) echo "<div class='alert-danger'>$error</div>"; ?>
        
        <div class="section-header">
            <h2>Scheme Rules & Configuration</h2>
            <button class="btn btn-primary" style="background:#6327af; color:white; border:none; padding:10px 20px; border-radius:8px; cursor:pointer;" onclick="openEditModal()">Edit Rules</button>
        </div>
        
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin:0; gap:15px;">
            <div style="padding:15px; background:#fafafa; border-radius:8px; border:1px solid #eee;">
                <label style="font-size:12px; color:#666;">Duration</label>
                <div style="font-weight:700; font-size:16px;"><?php echo htmlspecialchars($plan['duration'] ?? 'N/A'); ?></div>
            </div>
            <div style="padding:15px; background:#fafafa; border-radius:8px; border:1px solid #eee;">
                <label style="font-size:12px; color:#666;">Pre-closure Penalty</label>
                <div style="font-weight:700; font-size:16px; color:#dc3545;"><?php echo htmlspecialchars($plan['penalty'] ?? 'N/A'); ?></div>
            </div>
            <div style="padding:15px; background:#fafafa; border-radius:8px; border:1px solid #eee;">
                <label style="font-size:12px; color:#666;">Plan Advantages</label>
                <div style="font-weight:700; font-size:16px; color:#28a745;"><?php echo htmlspecialchars($plan['benefit'] ?? 'N/A'); ?></div>
            </div>
        </div>

        <div style="margin-top:20px; font-size:14px; color:#444; line-height:1.6; background:#fff; padding:20px; border-radius:10px; border:1px solid #eee;">
            <p><strong>Terms & Conditions:</strong><br><?php echo nl2br(htmlspecialchars($plan['description'] ?? 'No terms available.')); ?></p>
            <hr style="margin:15px 0; border:none; border-top:1px solid #eee;">
            <p style="color:#856404; background:#fff9e6; padding:10px; border-radius:6px; border:1px solid #ffeeba;"><strong>Important Note:</strong><br><?php echo nl2br(htmlspecialchars($plan['note_content'] ?? 'No important notes added.')); ?></p>
        </div>
      </section>

      <section class="content-section container-constrain">
        <div class="section-header">
          <h2>Enrolled Users (<?php echo $enrolled_count; ?>)</h2>
        </div>

        <div class="table-container">
          <table>
            <thead>
              <tr>
                <th>User Name</th>
                <th>Join Date</th>
                <th>Maturity Date</th>
                <th>Total Saved</th>
                <th>Gold Balance</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if($enrolled_count > 0): ?>
                <?php while($row = mysqli_fetch_assoc($enroll_result)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['user_name']); ?></td>
                    <td><?php echo date("M d, Y", strtotime($row['start_date'])); ?></td>
                    <td><?php echo date("M d, Y", strtotime($row['maturity_date'])); ?></td>
                    <td>₹<?php echo number_format($row['total_amount_paid'] ?? 0, 2); ?></td>
                    <td style="color:#d4af37; font-weight:bold;"><?php echo number_format($row['gold_earned'] ?? 0, 3); ?> g</td>
                    <td><span class="badge" style="background:#e8f5e9; color:#2e7d32; padding:5px 10px; border-radius:50px; font-size:11px;"><?php echo $row['status']; ?></span></td>
                    <td><button class="btn btn-primary" style="padding:4px 10px; font-size:11px;" onclick="window.location.href='schemes.php?view_history=<?php echo $row['user_id']; ?>'">History</button></td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="7" style="text-align:center; padding:30px;">No users found for this scheme.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span style="float:right; cursor:pointer; font-size:24px;" onclick="closeEditModal()">&times;</span>
            <h2 style="color:#6327af; margin-top:0;">Edit Scheme Configuration</h2>
            <hr style="border:none; border-top:1px solid #eee; margin-bottom:20px;">
            
            <form method="POST">
                <input type="hidden" name="plan_id" value="<?php echo $plan['id'] ?? ''; ?>">
                
                <div class="form-group">
                    <label>Plan Name:</label>
                    <input type="text" name="plan_name" class="form-input" value="<?php echo htmlspecialchars($plan['plan_name'] ?? ''); ?>" required>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px;">
                    <div class="form-group">
                        <label>Duration:</label>
                        <input type="text" name="duration" class="form-input" value="<?php echo htmlspecialchars($plan['duration'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Pre-closure Penalty:</label>
                        <input type="text" name="penalty" class="form-input" value="<?php echo htmlspecialchars($plan['penalty'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Plan Advantages:</label>
                    <textarea name="benefit" class="form-input" required><?php echo htmlspecialchars($plan['benefit'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Terms and Conditions:</label>
                    <textarea name="description" class="form-input" style="min-height:100px;"><?php echo htmlspecialchars($plan['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Important Note:</label>
                    <textarea name="note_content" class="form-input" style="background:#fff9e6; border-color:#ffeeba;"><?php echo htmlspecialchars($plan['note_content'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Card Theme Color:</label>
                    <input type="color" name="border_color" value="<?php echo $plan['border_color'] ?? '#6327af'; ?>" style="height:45px; width:100%; cursor:pointer;">
                </div>
                
                <button type="submit" name="update_scheme" class="update-btn">Update Configuration</button>
            </form>
        </div>
    </div>

    <script>
        function openEditModal() { document.getElementById('editModal').style.display = 'block'; }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('editModal')) closeEditModal();
        }
    </script>
</body>
</html>