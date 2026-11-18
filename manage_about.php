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

$msg = "";
$error = "";

// 1. Handle Update Request for a single unified paragraph
if (isset($_POST['update_about'])) {
    
    // BACKEND SECURITY: Block viewers from making changes
    if ($is_viewer) {
        die("Error: Viewers do not have permission to update About TTJ content.");
    }

    $full_content = mysqli_real_escape_string($conn, $_POST['about_full_content']);

    // Update ID 1 with the entire pasted paragraph
    $sql = "UPDATE about_us_content SET content = '$full_content' WHERE id = 1";
    
    if (mysqli_query($conn, $sql)) {
        $msg = "About TTJ Content Updated Successfully!";
    } else {
        $error = "Error updating database: " . mysqli_error($conn);
    }
}

// 2. Fetch Current Content from ID 1
$res = mysqli_query($conn, "SELECT content FROM about_us_content WHERE id = 1");
$row = mysqli_fetch_assoc($res);
$current_content = $row['content'] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage About TTJ - CMS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="./ASSETS/CSS/style.css">
    <style>
        .container { padding: 40px; max-width: 900px; }
        .editor-box { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); margin-bottom: 20px; }
        textarea { 
            width: 100%; 
            height: 500px; 
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 8px; 
            font-family: 'Inter', sans-serif; 
            line-height: 1.6; 
            font-size: 14px;
            resize: vertical;
            transition: all 0.3s;
        }
        textarea:focus {
            outline: none;
            border-color: #6327af;
        }
        
        /* Style for readonly textarea (Viewers) */
        textarea[readonly] {
            background: #f3f4f6;
            color: #6b7280;
            cursor: not-allowed;
        }
        textarea[readonly]:focus {
            border-color: #ddd;
            box-shadow: none;
        }

        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .save-btn { background: #6327af; color: white; border: none; padding: 14px 40px; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 16px; transition: background 0.3s; }
        .save-btn:hover { background: #4a1d82; }
        .alert { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; font-weight: 600; border: 1px solid #c3e6cb; }
        
        /* Header User Info */
        .user-actions { display: flex; align-items: center; gap: 15px; }
        .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .logout-btn-header:hover { background: #c62828; }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>
    
    <main class="main-content">
        <div class="container">
            <div class="header-flex">
                <h1>Manage About TTJ</h1>
                
                <div class="user-actions">
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
                    <span style="color: #333; font-weight: 600;">
                        <?php echo $displayName; ?>
                    </span>
                    <button class="logout-btn-header" onclick="window.location.href='logout.php'">Logout</button>
                </div>
            </div>
            
            <p style="color: #666; margin-bottom: 20px;">Paste your full company description below. This will reflect in the "About TTJ" section of the app.</p>
            
            <?php if($msg != "") echo "<div class='alert'>$msg</div>"; ?>
            <?php if($error != "") echo "<div class='alert' style='background:#f8d7da; color:#721c24; border-color:#f5c6cb;'>$error</div>"; ?>

            <div class="editor-box">
                <form method="POST">
                    <label style="display:block; margin-bottom:15px; font-weight:bold; color:#333;">Full Content Paragraph</label>
                    
                    <textarea name="about_full_content" placeholder="Paste your full About TTJ description here..." <?php echo $is_viewer ? 'readonly' : ''; ?>><?php echo htmlspecialchars($current_content); ?></textarea>
                    
                    <?php if(!$is_viewer): ?>
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="submit" name="update_about" class="save-btn">Update Mobile App</button>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Optional: Auto-hide success message after 5 seconds
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.style.display = 'none', 500);
            });
        }, 5000);
    </script>
</body>
</html>