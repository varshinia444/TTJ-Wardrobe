<?php
// 1. Fetch live count of unread admin alerts to show the red dot
$dot_query = mysqli_query($conn, "SELECT COUNT(*) as unread FROM admin_alerts WHERE is_read = 0");
$dot_data = mysqli_fetch_assoc($dot_query);
$unread_alerts = $dot_data['unread'] ?? 0;
?>

<div class="top-bar container-constrain">
    <h1><?php echo $page_title ?? 'Admin Panel'; ?></h1>
    
    <div class="user-info" style="display: flex; align-items: center; gap: 15px;">
        
        <div class="bell-container" style="position: relative; cursor: pointer; display: flex; align-items: center;" 
             onclick="window.location.href='admin_alerts_view.php'">
            
            <span class="material-icons" style="color: #6327af; font-size: 28px;">notifications</span>
            
            <?php if($unread_alerts > 0): ?>
                <span class="notification-dot" style="position: absolute; top: 2px; right: 2px; height: 10px; width: 10px; background: #ff3b30; border-radius: 50%; border: 2px solid white;"></span>
            <?php endif; ?>
        </div>

        <div style="background:#6327af; color:white; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">A</div>
        <span>Admin</span>
        <button class="logout-btn" onclick="window.location.href='logout.php'">Logout</button>
    </div>
</div>