<?php
/**
 * Safe Function Declaration to prevent redeclaration errors
 */
if (!function_exists('isActive')) {
    function isActive($pageName) {
        $current_page = basename($_SERVER['PHP_SELF']);
        if ($pageName === $current_page) {
            echo 'active';
        }
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <h2>TTJ Loyalty Admin</h2>
    </div>
    <nav>
        <ul class="nav-menu">
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php isActive('dashboard.php'); ?>">
                    <span class="nav-icon material-icons">dashboard</span>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="users.php" class="nav-link <?php isActive('users.php'); ?>">
                    <span class="nav-icon material-icons">people</span>
                    <span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="transactions.php" class="nav-link <?php isActive('transactions.php'); ?>">
                    <span class="nav-icon material-icons">receipt_long</span>
                    <span>All Transactions</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="points.php" class="nav-link <?php isActive('points.php'); ?>">
                    <span class="nav-icon material-icons">star_rate</span>
                    <span>Points Management</span>
                </a>
            </li>
          <li class="nav-item">
                <a href="goldsavings.php" class="nav-link <?php isActive('goldsavings.php'); ?>">
                    <span class="nav-icon material-icons">account_balance_wallet</span>
                    <span>Gold Wallet</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="schemes.php" class="nav-link <?php isActive('schemes.php'); ?>">
                    <span class="nav-icon material-icons">event_note</span>
                    <span>Schemes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="promotions.php" class="nav-link <?php isActive('promotions.php'); ?>">
                    <span class="nav-icon material-icons">card_giftcard</span>
                    <span>Promotions</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="jewelry_designs.php" class="nav-link <?php isActive('jewelry_designs.php'); ?>">
                    <span class="nav-icon material-icons">diamond</span>
                    <span>Jewellery Designs</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php isActive('reports.php'); ?>">
                    <span class="nav-icon material-icons">trending_up</span>
                    <span>Reports & Analytics</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="notifications.php" class="nav-link <?php isActive('notifications.php'); ?>">
                    <span class="nav-icon material-icons">notifications</span>
                    <span>Notifications</span>
                </a>
            </li>
            
            <li class="nav-item">
               <a href="manage_tc.php" class="nav-link <?php isActive('manage_tc.php'); ?>">
                     <span class="nav-icon material-icons">description</span>
                     <span>Terms and Conditions</span>
               </a>
           </li>

            <li class="nav-item">
                <a href="manage_about.php" class="nav-link <?php isActive('manage_about.php'); ?>">
                     <span class="nav-icon material-icons">info</span>
                     <span>About TTJ</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>