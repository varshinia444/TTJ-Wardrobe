<?php
include('db_connect.php');

$msg = "";

// Handle Saving Rules
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_rules'])) {
    $points_per_100 = mysqli_real_escape_string($conn, $_POST['points_per_100']);
    $min_redemption = mysqli_real_escape_string($conn, $_POST['min_redemption']);
    $expiry_months = mysqli_real_escape_string($conn, $_POST['expiry_months']);
    $credit_hours = mysqli_real_escape_string($conn, $_POST['credit_hours']);
    
    // Checkboxes: If not checked, they don't appear in $_POST, so we default to 0
    $ex_gift = isset($_POST['ex_gift']) ? 1 : 0;
    $ex_repair = isset($_POST['ex_repair']) ? 1 : 0;
    $ex_alter = isset($_POST['ex_alter']) ? 1 : 0;
    $ex_making = isset($_POST['ex_making']) ? 1 : 0;

    $update_query = "UPDATE loyalty_rules SET 
        points_per_100 = '$points_per_100', 
        min_redemption = '$min_redemption', 
        expiry_months = '$expiry_months', 
        credit_hours = '$credit_hours',
        exclude_gift_cards = '$ex_gift',
        exclude_repairs = '$ex_repair',
        exclude_alterations = '$ex_alter',
        exclude_making_charges = '$ex_making'
        WHERE id = 1";

    if (mysqli_query($conn, $update_query)) {
        $msg = "Rules saved successfully!";
    }
}

// Fetch current rules
$rules_query = mysqli_query($conn, "SELECT * FROM loyalty_rules WHERE id = 1");
$rules = mysqli_fetch_assoc($rules_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TTJ - Rules Engine</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .alert-success {
          padding: 12px;
          background-color: #d4edda;
          color: #155724;
          border-radius: 4px;
          margin-bottom: 15px;
          border: 1px solid #c3e6cb;
      }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Rules Engine</h1>
        <div class="user-info">
          <div class="user-avatar">A</div>
          <span id="adminName">Admin</span>
          <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if($msg != "") echo "<div class='alert-success'>$msg</div>"; ?>

        <div class="section-header">
          <h2>Loyalty Rules Configuration</h2>
        </div>

        <form method="POST" action="rules.php">
            <div class="form-grid" style="margin-top:12px;">
              <div>
                  <label>Points per ₹100 Spent</label>
                  <input type="number" name="points_per_100" class="form-control" value="<?php echo $rules['points_per_100']; ?>">
              </div>
              <div>
                  <label>Minimum Redemption Points</label>
                  <input type="number" name="min_redemption" class="form-control" value="<?php echo $rules['min_redemption']; ?>">
              </div>
              <div>
                  <label>Point Expiry Period (Months)</label>
                  <input type="number" name="expiry_months" class="form-control" value="<?php echo $rules['expiry_months']; ?>">
              </div>
              <div>
                  <label>Point Credit Time (Hours)</label>
                  <input type="number" name="credit_hours" class="form-control" value="<?php echo $rules['credit_hours']; ?>">
              </div>
            </div>

            <div style="margin-top:24px;">
              <h3>Exclusions</h3>
              <div style="margin-top:12px;">
                <label style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <input type="checkbox" name="ex_gift" <?php echo $rules['exclude_gift_cards'] ? 'checked' : ''; ?>>
                    <span>Exclude gift card purchases</span>
                </label>
                <label style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <input type="checkbox" name="ex_repair" <?php echo $rules['exclude_repairs'] ? 'checked' : ''; ?>>
                    <span>Exclude repair charges</span>
                </label>
                <label style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <input type="checkbox" name="ex_alter" <?php echo $rules['exclude_alterations'] ? 'checked' : ''; ?>>
                    <span>Exclude alteration fees</span>
                </label>
                <label style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <input type="checkbox" name="ex_making" <?php echo $rules['exclude_making_charges'] ? 'checked' : ''; ?>>
                    <span>Exclude making charges</span>
                </label>
              </div>
            </div>
            
            <button type="submit" name="save_rules" class="btn btn-primary" style="margin-top:14px;">Save Rules</button>
        </form>
      </section>
    </main>
    <script src="./ASSETS/JS/script.js"></script>
</body>
</html>