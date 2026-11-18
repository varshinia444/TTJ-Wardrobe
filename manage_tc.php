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

// 1. SAFETY CHECK: Ensure at least one row exists in the dedicated table
$check_exists = mysqli_query($conn, "SELECT id FROM terms_and_conditions WHERE id = 1");
if (mysqli_num_rows($check_exists) == 0) {
    // Automatically create row 1 with default text to prevent PHP null errors
    mysqli_query($conn, "INSERT INTO terms_and_conditions (id, tc_content) VALUES (1, 'Default Terms & Conditions text goes here...')");
}

// 2. Handle Update Request from the CMS Form
if (isset($_POST['update_tc'])) {
    
    // BACKEND SECURITY: Block viewers from saving changes
    if ($is_viewer) {
        die("Error: Viewers do not have permission to update Terms & Conditions.");
    }

    $content = mysqli_real_escape_string($conn, $_POST['tc_content']);
    $sql = "UPDATE terms_and_conditions SET tc_content = '$content' WHERE id = 1";
    
    if (mysqli_query($conn, $sql)) {
        $msg = "Terms & Conditions updated successfully!";
    } else {
        $error = "Update failed: " . mysqli_error($conn);
    }
}

// 3. Fetch current content from the dedicated table for display
$query = mysqli_query($conn, "SELECT tc_content FROM terms_and_conditions WHERE id = 1");
$tc_data = mysqli_fetch_assoc($query);
$current_content = $tc_data['tc_content'] ?? "";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage T&C - TTJ CMS</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./ASSETS/CSS/style.css">
    <style>
        .container { padding: 40px; max-width: 900px; margin-left: 20px; }
        .editor-box { 
            background: white; 
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
            border: 1px solid #eee;
        }
        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        h1 { color: #1e1b4b; font-size: 28px; font-weight: 700; margin: 0; }
        
        textarea { 
            width: 100%; 
            height: 450px; 
            padding: 20px; 
            border: 1px solid #ddd; 
            border-radius: 12px; 
            font-family: 'Inter', sans-serif; 
            font-size: 14px; 
            line-height: 1.6; 
            color: #333;
            resize: vertical; 
            background: #fcfcfc;
        }
        textarea:focus {
            outline: none;
            border-color: #6327af;
            box-shadow: 0 0 0 3px rgba(99, 39, 175, 0.1);
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

        .save-btn { 
            background: #6327af; 
            color: white; 
            border: none; 
            padding: 14px 40px; 
            border-radius: 10px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 16px;
            margin-top: 25px; 
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(99, 39, 175, 0.2);
        }
        .save-btn:hover { 
            background: #4a1d82; 
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(99, 39, 175, 0.3);
        }

        .alert { 
            padding: 15px 20px; 
            background: #d4edda; 
            color: #155724; 
            border-radius: 10px; 
            margin-bottom: 25px; 
            border: 1px solid #c3e6cb;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-danger { 
            background: #f8d7da; 
            color: #721c24; 
            border-color: #f5c6cb; 
        }
        label { 
            display: block; 
            margin-bottom: 15px; 
            font-weight: 600; 
            color: #4b5563; 
            font-size: 15px;
        }
        
        .user-actions { display: flex; align-items: center; gap: 12px; }
        .logout-btn-header { background: #dc3545; color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 14px; font-weight: bold; border: none; cursor: pointer; }
        .logout-btn-header:hover { background: #c62828; }
    </style>
</head>
<body>
    <?php include('sidebar.php'); ?>

    <main class="main-content">
        <div class="container">
            <div class="header-flex">
                <h1>Manage Terms & Conditions</h1>
                
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
                    <span style="color: #1e1b4b; font-weight: 600; margin-right: 10px;">
                        <?php echo $displayName; ?>
                    </span>
                    <button class="logout-btn-header" onclick="window.location.href='logout.php'">Logout</button>
                </div>
            </div>

            <p style="color: #6b7280; margin-bottom: 30px;">This content is fetched dynamically by the mobile app's profile section.</p>
            
            <?php if($msg != ""): ?>
                <div class="alert">
                    <span class="material-icons">check_circle</span>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <?php if($error != ""): ?>
                <div class="alert alert-danger">
                    <span class="material-icons">error</span>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="editor-box">
                <form method="POST">
                    <label>Mobile App Legal Content</label>
                    
                    <textarea name="tc_content" placeholder="Type your terms here... Use • for bullets and press Enter for new paragraphs." <?php echo $is_viewer ? 'readonly' : ''; ?>><?php echo htmlspecialchars($current_content); ?></textarea>
                    
                    <?php if(!$is_viewer): ?>
                        <div style="text-align: right;">
                            <button type="submit" name="update_tc" class="save-btn">
                                Update Mobile App
                            </button>
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
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>