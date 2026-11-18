<?php
include('db_connect.php');

$msg = "";

// Handle Rate Updates
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_rates'])) {
    $rates = $_POST['rates'];
    
    // Backend Calculation: ID 1 is Gold 24K
    $gold24 = floatval($rates[1]);
    if ($gold24 > 0) {
        $rates[2] = round($gold24 * 0.916, 2); // 22K
        $rates[3] = round($gold24 * 0.750, 2); // 18K
        $rates[4] = round($gold24 * 0.375, 2); // 9K
    }

    foreach ($rates as $id => $rate) {
        $clean_rate = mysqli_real_escape_string($conn, $rate);
        $clean_id = mysqli_real_escape_string($conn, $id);
        mysqli_query($conn, "UPDATE market_rates SET rate_inr = '$clean_rate', updated_at = NOW() WHERE id = '$clean_id'");
    }
    $msg = "Live rates synced and saved successfully!";
}

$query = mysqli_query($conn, "SELECT * FROM market_rates ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>TTJ - Update Metal Rates</title>
    <link rel="stylesheet" href="./ASSETS/CSS/style.css" />
    <style>
        .alert-success { padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; margin-bottom: 20px; }
        .rate-card { background: white; padding: 20px; border-radius: 12px; border: 1px solid #eee; margin-bottom: 10px; }
        input[readonly] { background: #f0f0f0; cursor: not-allowed; }
        .live-badge { background: #ff4757; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; float: right; }
    </style>
</head>
<body>
    <main class="main-content">
        <?php if($msg != "") echo "<div class='alert-success'>$msg</div>"; ?>
        
        <form method="POST" action="metal_rates.php" id="ratesForm">
            <div class="rate-grid">
                <?php while($row = mysqli_fetch_assoc($query)): ?>
                    <div class="rate-card">
                        <h3>
                            <?php echo htmlspecialchars($row['metal_name']); ?> 
                            <?php if($row['id'] == 1) echo '<span class="live-badge">Fetching Live...</span>'; ?>
                        </h3>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span>₹</span>
                            <input type="number" step="0.01" 
                                   name="rates[<?php echo $row['id']; ?>]" 
                                   id="rate-<?php echo $row['id']; ?>" 
                                   class="form-control" 
                                   value="<?php echo $row['rate_inr']; ?>" 
                                   required 
                                   <?php if(in_array($row['id'], [2,3,4])) echo 'readonly'; ?>>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
            <button type="submit" name="update_rates" class="btn btn-primary" style="margin-top: 20px; background: #6c5ce7;">
                Save Changes
            </button>
        </form>
    </main>

    <script>
        function autoCalculate() {
            let gold24 = parseFloat(document.getElementById('rate-1').value) || 0;
            if (gold24 > 0) {
                document.getElementById('rate-2').value = (gold24 * 0.916).toFixed(2); // 22K
                document.getElementById('dash-rate-3').value = (gold24 * 0.750).toFixed(2); // 18K
                document.getElementById('dash-rate-4').value = (gold24 * 0.375).toFixed(2); // 9K
            }
        }

        async function fetchInternetRates() {
            try {
                const response = await fetch('https://api.exchangerate-api.com/v4/latest/USD');
                const data = await response.json();
                const inrValue = data.rates.INR;
                let live24k = (2050 / 31.1035) * inrValue; 

                if (live24k > 0) {
                    document.getElementById('rate-1').value = live24k.toFixed(2);
                    autoCalculate();
                    document.querySelector('.live-badge').innerText = "LIVE SYNCED";
                }
            } catch (error) {
                document.querySelector('.live-badge').innerText = "MANUAL MODE";
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            document.getElementById('rate-1').addEventListener('input', autoCalculate);
            fetchInternetRates();
        });
    </script>
</body>
</html>