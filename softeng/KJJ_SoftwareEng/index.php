<?php
session_start();
require_once 'config/db.php'; 

$error_message = "";
$success_message = "";

// --- GOOGLE OAUTH CONFIGURATION ---
$google_client_id = '60493602611-u6kbm2i5t9ugr4551jsq267bunfsorcv.apps.googleusercontent.com';
$google_client_secret = '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';
$google_redirect_uri = 'http://edupulse.dlsud.edu.ph/softeng/KJJ_SoftwareEng/index.php';

// Handle user clicking "Cancel" during any OTP or Reset phase
if (isset($_GET['cancel_mfa'])) {
    session_unset();
    header("Location: index.php");
    exit();
}

// Display global success messages (like after a password reset)
if (isset($_SESSION['global_success'])) {
    $success_message = $_SESSION['global_success'];
    unset($_SESSION['global_success']);
}

// ==========================================
// GOOGLE INTERCEPTOR: CATCH AUTH CODE CALLBACK
// ==========================================
if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Exchange the Auth Code for a Token Pack via cURL backend handshake
    $token_url = 'https://oauth2.googleapis.com/token';
    $post_fields = [
        'code'          => $code,
        'client_id'     => $google_client_id,
        'client_secret' => $google_client_secret,
        'redirect_uri'  => $google_redirect_uri,
        'grant_type'    => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    $response = curl_exec($ch);
    curl_close($ch);

    $token_data = json_decode($response, true);

    if (isset($token_data['id_token'])) {
        // Explode and decode the secure ID Token JWT Payload component
        $token_parts = explode('.', $token_data['id_token']);
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $token_parts[1])), true);

        if ($payload && isset($payload['email'])) {
            $google_email = $payload['email'];

            // Intersect against local database records
            $stmt = $conn->prepare("SELECT id, role FROM users WHERE email = ?");
            $stmt->bind_param("s", $google_email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['email'] = $google_email;
                $_SESSION['role'] = $user['role'];

                // Explicit logging statement entry
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_email, action) VALUES (?, 'Successful Single-Sign-On via Institutional Google Profile Mapping.')");
                $log_stmt->bind_param("s", $google_email);
                $log_stmt->execute();

                // RBAC Core Portal Router Link Redirection
                if ($user['role'] === 'Student') {
                    header("Location: student_portal.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error_message = "This Google account (<strong>" . htmlspecialchars($google_email) . "</strong>) is not registered in EduPulse.";
            }
        }
    } else {
        $error_message = "Google Authentication endpoint synchronization dropped.";
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // ==========================================
    // LOGIN: HYBRID LOGIC (HANDLES HASH & PLAIN)
    // ==========================================
    if (isset($_POST['login_step_1'])) {
        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT id, role, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $db_password = $user['password'];
            $is_match = false;

            if (strpos($db_password, '$2y$') === 0) {
                $is_match = password_verify($password, $db_password);
            } else {
                $is_match = ($password === $db_password);
            }

            if ($is_match) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['email'] = $email;
                $_SESSION['role'] = $user['role'];
                
                $log_action = "Successful login via web portal.";
                $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_email, action) VALUES (?, ?)");
                $log_stmt->bind_param("ss", $email, $log_action);
                $log_stmt->execute();
                
                if ($user['role'] === 'Student') {
                    header("Location: student_portal.php");
                } else {
                    header("Location: dashboard.php");
                }
                exit();
            } else {
                $error_message = "Invalid email or password.";
            }
        } else {
            $error_message = "Invalid email or password.";
        }
    }
    
    // ==========================================
    // RECOVERY STEP 1: SEND OTP
    // ==========================================
    elseif (isset($_POST['forgot_step_1'])) {
        $email = $_POST['email'];
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $otp = rand(100000, 999999);
            $_SESSION['otp_context'] = 'forgot_password';
            $_SESSION['pending_email'] = $email;
            $_SESSION['otp'] = $otp;
            $success_message = "Recovery code sent! (Local Test - Your code is: <strong>$otp</strong>)";
        } else {
            $error_message = "If an account exists, a recovery code was sent."; 
        }
    }

    // ==========================================
    // RECOVERY STEP 2: VERIFY OTP
    // ==========================================
    elseif (isset($_POST['verify_forgot_otp'])) {
        if (trim($_POST['otp_code']) == $_SESSION['otp']) {
            $_SESSION['reset_password_granted'] = true;
            unset($_SESSION['otp_context']);
            unset($_SESSION['otp']);
            $success_message = "Identity verified. You may now create a new password.";
        } else {
            $error_message = "Invalid security code. Please try again.";
        }
    }

    // ==========================================
    // RECOVERY STEP 3: HASH & SAVE NEW PASSWORD
    // ==========================================
    elseif (isset($_POST['reset_password'])) {
        $hashed_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $email = $_SESSION['pending_email'];
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        $stmt->execute();
        
        $log_action = "Account password recovered and reset via OTP.";
        $log_stmt = $conn->prepare("INSERT INTO audit_logs (user_email, action) VALUES (?, ?)");
        $log_stmt->bind_param("ss", $email, $log_action);
        $log_stmt->execute();

        session_unset();
        $_SESSION['global_success'] = "Password successfully reset. You can now sign in.";
        header("Location: index.php");
        exit();
    }
}

$is_forgot_pw = isset($_GET['action']) && $_GET['action'] === 'forgot_password';

// Dynamic execution query link generator built securely for the UI container
$google_login_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
    'response_type' => 'code',
    'client_id'     => $google_client_id,
    'redirect_uri'  => $google_redirect_uri,
    'scope'         => 'openid email profile',
    'prompt'        => 'select_account'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal | EduPulse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #10b981; --primary-hover: #059669; --slate-900: #0f172a; --slate-700: #334155; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { height: 100vh; display: flex; align-items: center; justify-content: center; background-color: var(--slate-900); position: relative; overflow: hidden; }
        body::before { content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('images/dlsud.png'); background-size: cover; background-position: center; filter: blur(8px) brightness(0.6); z-index: -1; }
        .login-card { background: white; width: 400px; padding: 48px; border-radius: 16px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); z-index: 1; }
        .header { text-align: center; margin-bottom: 32px; }
        .header i { font-size: 2.5rem; color: var(--primary); margin-bottom: 12px; }
        .header h2 { color: var(--slate-900); font-weight: 700; font-size: 1.5rem; }
        .error-box { background: #fef2f2; color: #b91c1c; padding: 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; margin-bottom: 20px; text-align: center; }
        .success-box { background: #ecfdf5; color: #047857; padding: 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; margin-bottom: 20px; border: 1px solid #a7f3d0; text-align: center; }
        .input-group { margin-bottom: 20px; }
        .input-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--slate-700); margin-bottom: 6px; text-transform: uppercase; }
        .input-group input, .input-group select { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; }
        .btn-submit { width: 100%; background: var(--primary); color: white; border: none; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; text-align: center; }
        .btn-submit:hover { background: var(--primary-hover); }
        .footer { margin-top: 24px; text-align: center; font-size: 0.85rem; color: #94a3b8; }
        .footer a { color: var(--primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="header">
            <i class="fa-solid fa-graduation-cap"></i>
            <h2>EduPulse Portal</h2>
        </div>
        
        <?php if (!empty($success_message)): ?><div class="success-box"><?php echo $success_message; ?></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="error-box"><?php echo $error_message; ?></div><?php endif; ?>

        <?php if (isset($_SESSION['reset_password_granted'])): ?>
            <form method="POST">
                <div class="input-group">
                    <label>Create New Password</label>
                    <input type="password" name="new_password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required minlength="6">
                </div>
                <button type="submit" name="reset_password" class="btn-submit">Save Password</button>
            </form>

        <?php elseif (isset($_SESSION['otp_context'])): ?>
            <form method="POST">
                <div class="input-group">
                    <label>Security Code</label>
                    <input type="text" name="otp_code" placeholder="123456" maxlength="6" required autocomplete="off" style="text-align: center; letter-spacing: 8px; font-size: 1.25rem; font-weight: 700;">
                </div>
                <button type="submit" name="verify_forgot_otp" class="btn-submit">Verify Identity</button>
            </form>
            <div class="footer"><a href="index.php?cancel_mfa=1">Cancel</a></div>

        <?php elseif ($is_forgot_pw): ?>
            <form method="POST" action="index.php?action=forgot_password">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@dlsud.edu.ph" required>
                </div>
                <button type="submit" name="forgot_step_1" class="btn-submit">Send Recovery Code</button>
            </form>
            <div class="footer">Remember your password? <a href="index.php">Sign In</a></div>

        <?php else: ?>
            <form method="POST" action="index.php">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="name@dlsud.edu.ph" required>
                </div>
                <div class="input-group">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <label style="margin-bottom: 0;">Password</label>
                        <a href="index.php?action=forgot_password" style="font-size: 0.75rem; text-decoration: none; color: var(--primary); font-weight: 600;">Forgot password?</a>
                    </div>
                    <input type="password" name="password" placeholder="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" required>
                </div>
                <button type="submit" name="login_step_1" class="btn-submit">Sign In</button>
            </form>
            
            <a href="<?php echo $google_login_url; ?>" class="btn-submit" style="display: flex; align-items: center; justify-content: center; gap: 10px; background: white; color: #334155; border: 1px solid #cbd5e1; text-decoration: none; margin-top: 15px; box-sizing: border-box;">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c1/Google_%22G%22_logo.svg" style="width: 18px; height: 18px;">
                Sign In with Google
            </a>

            <div class="footer">
                <div class="policy-text">Protected by Institutional IT Policy</div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>