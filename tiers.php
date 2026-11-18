<?php
include('db_connect.php');

// Handle Form Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_tiers'])) {
    // These keys now match the 'name' attributes in the HTML form exactly
    $gold_threshold = mysqli_real_escape_string($conn, $_POST['gold_threshold']);
    $gold_multiplier = mysqli_real_escape_string($conn, $_POST['gold_multiplier']);
    $plat_threshold = mysqli_real_escape_string($conn, $_POST['platinum_threshold']); // Fixed key
    $plat_multiplier = mysqli_real_escape_string($conn, $_POST['platinum_multiplier']); // Fixed key

    // Update the database
    mysqli_query($conn, "UPDATE tier_settings SET threshold = '$gold_threshold', multiplier = '$gold_multiplier' WHERE tier_name = 'Gold'");
    mysqli_query($conn, "UPDATE tier_settings SET threshold = '$plat_threshold', multiplier = '$plat_multiplier' WHERE tier_name = 'Platinum'");
    
    $msg = "Settings updated successfully!";
}

// 1. Fetch Member Counts from 'users' table
$counts = mysqli_query($conn, "SELECT tier, COUNT(*) as total FROM users GROUP BY tier");
$member_counts = ['Silver' => 0, 'Gold' => 0, 'Platinum' => 0];
while($row = mysqli_fetch_assoc($counts)) {
    $member_counts[$row['tier']] = $row['total'];
}

// 2. Fetch Config from 'tier_settings' table
$settings_query = mysqli_query($conn, "SELECT * FROM tier_settings");
$settings = [];
while($row = mysqli_fetch_assoc($settings_query)) {
    $settings[$row['tier_name']] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>TTJ - Tier Management</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .alert-success {
          padding: 15px;
          background-color: #d4edda;
          color: #155724;
          border-radius: 4px;
          margin-bottom: 20px;
          border: 1px solid #c3e6cb;
      }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Tier Management</h1>
        <div class="user-info">
          <div class="user-avatar">A</div>
          <span id="adminName">Admin</span>
          <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
        </div>
      </div>

      <section class="content-section container-constrain">
        <?php if(isset($msg)): ?>
            <div class="alert-success"><?php echo $msg; ?></div>
        <?php endif; ?>
        
        <div class="section-header">
          <h2>Current Tiers</h2>
        </div>
        
        <div class="stats-grid" style="margin-top:8px; max-width:100%;">
          <div class="stat-card">
            <h3>Silver Members</h3>
            <div class="stat-value"><?php echo number_format($member_counts['Silver']); ?></div>
            <div class="stat-change">Base tier - 1x points</div>
          </div>
          <div class="stat-card">
            <h3>Gold Members</h3>
            <div class="stat-value"><?php echo number_format($member_counts['Gold'] ?? 0); ?></div>
            <div class="stat-change">Spend > ₹<?php echo number_format($settings['Gold']['threshold'] ?? 50000); ?> - <?php echo $settings['Gold']['multiplier'] ?? 1.25; ?>x points</div>
          </div>
          <div class="stat-card">
            <h3>Platinum Members</h3>
            <div class="stat-value"><?php echo number_format($member_counts['Platinum'] ?? 0); ?></div>
            <div class="stat-change">Spend > ₹<?php echo number_format($settings['Platinum']['threshold'] ?? 150000); ?> - <?php echo $settings['Platinum']['multiplier'] ?? 1.5; ?>x points</div>
          </div>
        </div>

        <div class="section-header" style="margin-top:24px;">
          <h2>Tier Configuration</h2>
        </div>
        
        <form method="POST" action="tiers.php">
            <div class="form-grid" style="margin-top:12px;">
              <div>
                  <label>Gold Tier Threshold</label>
                  <input type="number" name="gold_threshold" class="form-control" value="<?php echo $settings['Gold']['threshold'] ?? 50000; ?>">
              </div>
              <div>
                  <label>Gold Point Multiplier</label>
                  <input type="number" name="gold_multiplier" class="form-control" value="<?php echo $settings['Gold']['multiplier'] ?? 1.25; ?>" step="0.01">
              </div>
              <div>
                  <label>Platinum Tier Threshold</label>
                  <input type="number" name="platinum_threshold" class="form-control" value="<?php echo $settings['Platinum']['threshold'] ?? 150000; ?>">
              </div>
              <div>
                  <label>Platinum Point Multiplier</label>
                  <input type="number" name="platinum_multiplier" class="form-control" value="<?php echo $settings['Platinum']['multiplier'] ?? 1.5; ?>" step="0.01">
              </div>
            </div>
            <button type="submit" name="update_tiers" class="btn btn-primary" style="margin-top:14px;">Update Tier Settings</button>
        </form>
      </section>
    </main>
    <script src="./ASSETS/JS/script.js"></script>
</body>
</html>