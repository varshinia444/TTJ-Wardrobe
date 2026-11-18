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

// --- 1. HANDLE DIRECT DOWNLOAD CLICK (From Table) ---
if (isset($_GET['download_id'])) {
    
    // BACKEND SECURITY: Block viewers from downloading reports
    if ($is_viewer) {
        die("Error: Viewers do not have permission to download reports.");
    }

    $id = mysqli_real_escape_string($conn, $_GET['download_id']);
    $res = mysqli_query($conn, "SELECT * FROM generated_reports WHERE id = '$id'");
    $report = mysqli_fetch_assoc($res);

    if ($report) {
        $type = $report['report_type'];
        $filename = $report['report_name'];

        // Fetch the fresh data from DB for the specific report type
        if ($type == "Customer Activity") {
            $query = "SELECT name, email, mobile, points, tier FROM users";
            $header = ['Name', 'Email', 'Mobile', 'Points', 'Tier'];
        } elseif ($type == "Points Summary") {
            $query = "SELECT customer_name, type, points, trans_date FROM transactions";
            $header = ['Customer', 'Type', 'Points', 'Date'];
        } else {
            $query = "SELECT tier_name, threshold, multiplier FROM tier_settings";
            $header = ['Tier', 'Threshold', 'Multiplier'];
        }

        $data_res = mysqli_query($conn, $query);

        // SEND HEADERS FOR DOWNLOAD
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, $header);
        while($row = mysqli_fetch_assoc($data_res)) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit(); // Crucial: Stop script here to prevent HTML from being added to the file
    }
}

// --- 2. HANDLE FORM SUBMISSIONS (Instant Export & Custom Generation) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['instant_export']) || isset($_POST['generate_custom']))) {
    
    // BACKEND SECURITY: Block viewers from generating reports
    if ($is_viewer) {
        die("Error: Viewers do not have permission to generate reports.");
    }
    
    $type = isset($_POST['instant_export']) ? "Customer Activity" : mysqli_real_escape_string($conn, $_POST['type']);
    $format = isset($_POST['instant_export']) ? "csv" : mysqli_real_escape_string($conn, $_POST['format']);
    $timestamp = date("Ymd_His");
    $report_filename = "Report_" . str_replace(' ', '_', $type) . "_" . $timestamp;

    // Fetch data
    if ($type == "Customer Activity") {
        $query = "SELECT name, email, mobile, points, tier FROM users";
        $header = ['Name', 'Email', 'Mobile', 'Points', 'Tier'];
    } elseif ($type == "Points Summary") {
        $query = "SELECT customer_name, type, points, trans_date FROM transactions";
        $header = ['Customer', 'Type', 'Points', 'Date'];
    } else {
        $query = "SELECT tier_name, threshold, multiplier FROM tier_settings";
        $header = ['Tier', 'Threshold', 'Multiplier'];
    }
    
    $result = mysqli_query($conn, $query);

    // LOG TO HISTORY TABLE
    mysqli_query($conn, "INSERT INTO generated_reports (report_name, generated_date, report_type, status, file_path) 
                         VALUES ('$report_filename', NOW(), '$type', 'Ready', 'reports.php?download_id=LAST_ID')");
    $last_id = mysqli_insert_id($conn);

    // OUTPUT FILE IMMEDIATELY
    if ($format == 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.$report_filename.'.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $header);
        while($row = mysqli_fetch_assoc($result)) fputcsv($output, $row);
        fclose($output);
        exit();
    } 
    elseif ($format == 'xlsx') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=\"$report_filename.xls\"");
        echo "<table border='1'><tr><th>" . implode("</th><th>", $header) . "</th></tr>";
        while($row = mysqli_fetch_assoc($result)) echo "<tr><td>" . implode("</td><td>", $row) . "</td></tr>";
        echo "</table>";
        exit();
    } 
    else { // PDF Simulation (Formatted Text)
        header("Content-type: text/plain");
        header("Content-Disposition: attachment; filename=\"$report_filename.pdf\"");
        echo "TTJ LOYALTY SYSTEM - " . strtoupper($type) . " REPORT\n" . str_repeat("=", 50) . "\n\n";
        echo implode(" | ", $header) . "\n" . str_repeat("-", 50) . "\n";
        while($row = mysqli_fetch_assoc($result)) echo implode(" | ", $row) . "\n";
        exit();
    }
}

// --- 3. DYNAMIC OVERVIEW STATS ---
$points_query = mysqli_query($conn, "SELECT SUM(points) as total FROM users");
$points_data = mysqli_fetch_assoc($points_query);
$total_points = $points_data['total'] ?? 0;

// --- 4. FETCH RECENT REPORTS LIST ---
$reports_query = mysqli_query($conn, "SELECT * FROM generated_reports ORDER BY id DESC");

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
  <title>TTJ - Reports & Analytics</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
  <style>
      .badge-gold { background: #fff9e6; color: #856404; border: 1px solid #ffeeba; padding: 4px 10px; border-radius: 4px; font-size: 12px; font-weight: 600; }
      
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
      .user-info { display: flex; align-items: center; gap: 15px; }
  </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
      <div class="top-bar container-constrain">
        <h1>Reports & Analytics</h1>
        <div class="user-info">
            
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
            <span style="color: #1e1b4b; font-weight: 600;">
                <?php echo $displayName; ?>
            </span>
            
            <button class="logout-btn-header" onclick="window.location.href='logout.php'">Logout</button>
        </div>
      </div>

      <section class="content-section container-constrain">
        <div class="section-header">
          <h2>Overview</h2>
          
          <?php if(!$is_viewer): ?>
              <form method="POST">
                  <button type="submit" name="instant_export" class="btn btn-primary" style="background:#6327af; border:none;">Export Report</button>
              </form>
          <?php endif; ?>
          
        </div>

        <div class="stats-grid" style="margin-top:12px; max-width:100%;">
          <div class="stat-card">
              <h3>Total Points Liability</h3>
              <div class="stat-value">₹<?php echo number_format($total_points); ?></div>
          </div>
          <div class="stat-card"><h3>Points Earned (Month)</h3><div class="stat-value">₹2,45,890</div></div>
          <div class="stat-card"><h3>Points Redeemed (Month)</h3><div class="stat-value">₹1,89,340</div></div>
          <div class="stat-card"><h3>Expired Points (Month)</h3><div class="stat-value">₹34,220</div></div>
        </div>

        <?php if(!$is_viewer): ?>
        <div class="section-header" style="margin-top:24px;"><h2>Generate Custom Report</h2></div>
        <form method="POST">
            <div class="form-grid" style="margin-top:12px;">
              <div>
                  <label>Report Type</label>
                  <select name="type" class="form-control" required>
                      <option value="Points Summary">Points Summary</option>
                      <option value="Customer Activity">Customer Activity</option>
                      <option value="Tier Analysis">Tier Analysis</option>
                  </select>
              </div>
              <div>
                  <label>Format</label>
                  <select name="format" class="form-control" required>
                      <option value="xlsx">Excel (XLSX)</option>
                      <option value="pdf">PDF</option>
                      <option value="csv">CSV</option>
                  </select>
              </div>
            </div>
            <button type="submit" name="generate_custom" class="btn btn-primary" style="margin-top:12px; background:#6327af; border:none;">Generate Report</button>
        </form>
        <?php endif; ?>

        <div class="section-header" style="margin-top:30px;"><h2>Recent Reports</h2></div>
        <div class="table-container">
          <table>
            <thead>
                <tr>
                    <th>Report Name</th>
                    <th>Generated Date</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
              <?php if(mysqli_num_rows($reports_query) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($reports_query)): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['report_name']); ?></td>
                    <td><?php echo date("M d, Y", strtotime($row['generated_date'])); ?></td>
                    <td><?php echo htmlspecialchars($row['report_type']); ?></td>
                    <td><span class="badge badge-gold"><?php echo $row['status']; ?></span></td>
                    <td>
                        <?php if(!$is_viewer): ?>
                            <a href="reports.php?download_id=<?php echo $row['id']; ?>" 
                               class="btn btn-primary" style="padding:6px 12px; text-decoration:none; font-size:12px;">Download</a>
                        <?php else: ?>
                            <span style="color:#999; font-size:12px;">N/A</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="5" style="text-align:center; padding:30px;">No generated reports found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
    <script src="./ASSETS/JS/script.js"></script>
</body>
</html>