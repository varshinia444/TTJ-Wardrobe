<?php
session_start();
// Redirect to dashboard if already logged in via PHP session
// This is faster than JavaScript and prevents "fluctuations"
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>TTJ Login</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons&display=block" rel="stylesheet">

    <style>
        /* --- Modern Reset & Variables --- */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            /* Your Brand Colors */
            --brand-primary: #6327af; 
            --brand-secondary: #c13584;
            --brand-gradient: linear-gradient(135deg, #c13584 0%, #6327af 100%);
            
            /* Modern UI Colors */
            --bg-body: #f8f9fd;
            --surface: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --focus-ring: rgba(99, 39, 175, 0.25);
            --danger-bg: #fef2f2;
            --danger-text: #ef4444;
            
            /* Shadows & Radius */
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-lg: 1rem; /* 16px */
            --radius-md: 0.75rem; /* 12px */
        }

        body {
            font-family: 'Inter', sans-serif;
            /* Modern full-screen gradient background */
            background: var(--bg-body);
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                              radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
            background-size: cover;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-main);
            padding: 1.5rem;
        }

        /* --- Animation --- */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* --- Login Card --- */
        .login-card {
            width: 100%;
            max-width: 440px;
            background: var(--surface);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            padding: 2.5rem;
            animation: fadeIn 0.6s ease-out forwards;
            position: relative;
            overflow: hidden;
        }

        /* Decorative top bar */
        .login-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: var(--brand-gradient);
        }

        /* --- Header Section --- */
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-wrap {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            border-radius: 50%; /* Circle logo container */
            border: 1px solid var(--border-color);
        }

        .logo-img {
            max-width: 50px;
            max-height: 50px;
            object-fit: contain;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 0.5rem;
            letter-spacing: -0.025em;
        }

        .login-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* --- Form Elements --- */
        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-main);
            margin-bottom: 0.5rem;
        }

        .input-wrapper {
            position: relative;
        }

        .input-field {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            line-height: 1.5;
            color: var(--text-main);
            background-color: #fff;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .input-field:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 4px var(--focus-ring);
        }

        .input-field::placeholder {
            color: #9ca3af;
        }

        /* Password Toggle */
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            cursor: pointer;
            font-size: 1.25rem; /* Adjust icon size */
            user-select: none;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: var(--brand-primary);
        }

        /* --- Button --- */
        .login-btn {
            width: 100%;
            padding: 0.875rem;
            background: var(--brand-gradient);
            color: white;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: transform 0.1s, opacity 0.2s, box-shadow 0.2s;
            margin-top: 0.5rem;
        }

        .login-btn:hover {
            opacity: 0.95;
            box-shadow: 0 4px 12px rgba(99, 39, 175, 0.3);
            transform: translateY(-1px);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        /* --- Alerts --- */
        .error-message {
            background-color: var(--danger-bg);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger-text);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            text-align: center;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="logo-wrap">
                <img src="./ASSETS/image/ttj.png" alt="TTJ Logo" class="logo-img">
            </div>
            <h1>Welcome Back</h1>
            <p>Enter your details to access the Loyalty Admin</p>
        </div>

        <?php if(isset($_GET['error'])): ?>
        <div class="error-message">
            <span class="material-icons" style="font-size: 18px;">error_outline</span>
            <span>Invalid credentials. Please try again.</span>
        </div>
        <?php endif; ?>

        <form id="loginForm" action="login_process.php" method="POST" autocomplete="off">
            
            <div class="form-group">
                <label for="username">Username</label>
                <div class="input-wrapper">
                    <input 
                        id="username" 
                        name="username" 
                        type="text" 
                        class="input-field" 
                        placeholder="Enter your username" 
                        required 
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrapper">
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        class="input-field" 
                        placeholder="••••••••" 
                        required
                    >
                    <span class="material-icons toggle-password" onclick="togglePassword()">visibility</span>
                </div>
            </div>

            <button type="submit" class="login-btn">Sign In</button>
        </form>
    </div>

    <script>
        // Toggle Password Visibility (Logic preserved)
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.textContent = 'visibility_off';
            } else {
                passwordInput.type = 'password';
                toggleIcon.textContent = 'visibility';
            }
        }
    </script>
</body>
</html>