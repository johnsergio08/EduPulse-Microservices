<?php
session_start();
require_once 'config/db.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit(); }

$user_email = $_SESSION['email'];

// Catch the target parameter email from routing query context
if (!isset($_GET['email'])) {
    header("Location: social_feed.php");
    exit();
}

$target_email = trim($_GET['email']);

// Prevent self-view bugs from breaking connection logic check
$is_own_profile = ($user_email === $target_email);

// 1. Fetch main account profile meta descriptors from user_profiles
$p_stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_email = ?");
$p_stmt->bind_param("s", $target_email);
$p_stmt->execute();
$profile = $p_stmt->get_result()->fetch_assoc();

// Fallbacks if profile isn't established yet
$full_name = $profile['full_name'] ?? 'No Profile Set';
$department = $profile['department'] ?? 'Not Specified';
$bio = $profile['bio'] ?? "This user hasn't populated a biography description yet.";
$profile_pic = (!empty($profile['profile_pic']) && $profile['profile_pic'] !== 'default_avatar.png') ? 'uploads/' . $profile['profile_pic'] : 'images/default_avatar.png';

// 2. Fetch role and student_number parameters securely from core users table
$u_stmt = $conn->prepare("SELECT role, student_number FROM users WHERE email = ?");
$u_stmt->bind_param("s", $target_email);
$u_stmt->execute();
$account = $u_stmt->get_result()->fetch_assoc();

$role = $account['role'] ?? 'User';
$student_number = (!empty($account['student_number'])) ? $account['student_number'] : 'N/A';

// 3. BACKEND CONNECTION STATE MATRIX LOGIC
$connection_status = null;
$requester_email = null;

if (!$is_own_profile) {
    $c_stmt = $conn->prepare("SELECT status, requester_email FROM user_connections WHERE 
                             (requester_email = ? AND target_email = ?) OR 
                             (requester_email = ? AND target_email = ?)");
    $c_stmt->bind_param("ssss", $user_email, $target_email, $target_email, $user_email);
    $c_stmt->execute();
    $c_res = $c_stmt->get_result()->fetch_assoc();
    
    if ($c_res) {
        $connection_status = $c_res['status'];
        $requester_email = $c_res['requester_email'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($full_name); ?> | Profile View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; --border: #e2e8f0; --slate-900: #0f172a; --primary-dark: #059669; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: var(--bg); min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        
        .glass-card { background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 600px; margin-top: 20px; }
        .banner { height: 140px; background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
        .profile-body { padding: 0 40px 40px; text-align: center; }
        .avatar-lg { width: 120px; height: 120px; border-radius: 50%; border: 5px solid white; margin: -60px auto 0; background: #cbd5e1; object-fit: cover; }
        
        /* Badges */
        .email-badge { background: #f1f5f9; padding: 6px 14px; border-radius: 99px; font-size: 0.85rem; color: #64748b; margin-top: 12px; display: inline-block; }
        .id-badge { background: #eff6ff; padding: 6px 14px; border-radius: 99px; font-size: 0.85rem; color: #1d4ed8; font-weight: 700; border: 1px solid #bfdbfe; margin-top: 12px; display: inline-block; margin-left: 5px; }
        
        .display-group { text-align: left; margin-top: 25px; }
        .display-group label { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
        .display-box { width: 100%; padding: 14px; background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; margin-top: 6px; font-size: 0.95rem; color: #334155; font-weight: 500; }
        .display-box.bio { font-style: italic; color: #475569; white-space: pre-wrap; min-height: 80px; }
        
        /* Navigation Controls */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: white; color: #475569; border: 1px solid var(--border); padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; cursor: pointer; transition: all 0.2s; align-self: flex-start; max-width: 600px; margin: 0 auto; width: 100%; }
        .btn-back:hover { background: #f1f5f9; color: var(--slate-900); }

        /* Connection Interactive Buttons Matrix */
        .connection-action-wrapper { margin-top: 20px; display: flex; justify-content: center; gap: 10px; }
        .btn-profile-action { border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.15s; }
        .btn-profile-action.connect { background: var(--primary); color: white; }
        .btn-profile-action.connect:hover { background: var(--primary-dark); }
        .btn-profile-action.disconnect { background: #fff; color: #ef4444; border: 1px solid #fca5a5; }
        .btn-profile-action.disconnect:hover { background: #fef2f2; }
        .btn-profile-action.pending { background: #e2e8f0; color: #64748b; cursor: not-allowed; }
        .btn-profile-action.accept { background: #3b82f6; color: white; }
        .btn-profile-action.accept:hover { background: #1d4ed8; }
        .btn-profile-action.decline { background: #ef4444; color: white; }
        .btn-profile-action.decline:hover { background: #dc2626; }

        /* Notification Modal styling overlay */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal-card { background: white; width: 400px; padding: 32px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; }
        .btn-modal-action { background: var(--primary); color: white; border: none; width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 20px; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div style="width: 100%; max-width: 600px; margin-bottom: 10px;">
            <a href="javascript:history.back()" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Previous Page</a>
        </div>

        <div class="glass-card">
            <div class="banner"></div>
            <div class="profile-body">
                <img src="<?php echo htmlspecialchars($profile_pic); ?>" class="avatar-lg">
                <h2 style="margin-top:15px; font-weight: 800; color: var(--slate-900);"><?php echo htmlspecialchars($full_name); ?></h2>
                
                <div class="email-badge"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($target_email); ?></div>
                
                <?php if ($role === 'Student'): ?>
                    <div class="id-badge"><i class="fa-solid fa-id-card"></i> SN: <?php echo htmlspecialchars($student_number); ?></div>
                <?php endif; ?>

                <p style="color: var(--primary); font-weight: 700; font-size: 0.9rem; margin-top: 8px; text-transform: uppercase; letter-spacing: 0.5px;"><?php echo htmlspecialchars($role); ?></p>

                <?php if (!$is_own_profile): ?>
                    <div class="connection-action-wrapper">
                        <?php if ($connection_status === null): ?>
                            <button onclick="executeConnectionChange('send_request')" class="btn-profile-action connect"><i class="fa-solid fa-user-plus"></i> Connect</button>
                        
                        <?php elseif ($connection_status === 'pending'): ?>
                            <?php if ($requester_email === $user_email): ?>
                                <button class="btn-profile-action pending" disabled><i class="fa-solid fa-clock"></i> Request Pending</button>
                            <?php else: ?>
                                <button onclick="executeConnectionChange('accept_request')" class="btn-profile-action accept"><i class="fa-solid fa-check"></i> Accept Request</button>
                                <button onclick="executeConnectionChange('decline_request')" class="btn-profile-action decline"><i class="fa-solid fa-xmark"></i> Decline</button>
                            <?php endif; ?>
                        
                        <?php elseif ($connection_status === 'accepted'): ?>
                            <button onclick="executeConnectionChange('disconnect')" class="btn-profile-action disconnect"><i class="fa-solid fa-user-minus"></i> Disconnect</button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="display-group">
                    <label>Department / Academic Course</label>
                    <div class="display-box"><?php echo htmlspecialchars($department); ?></div>
                </div>
                
                <div class="display-group">
                    <label style="display: flex; align-items: center; gap: 5px;"><i class="fa-solid fa-quote-left" style="color: var(--primary); font-size: 0.65rem;"></i> Biography Overview</label>
                    <div class="display-box bio"><?php echo htmlspecialchars($bio); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="notifyModal">
        <div class="modal-card" id="modalStatus">
            <i id="modalIcon" class="fa-solid"></i>
            <h3 id="modalTitle">Title</h3>
            <p id="modalMsg">Message goes here.</p>
            <button onclick="closeModal()" class="btn-modal-action">Acknowledge</button>
        </div>
    </div>

<script>
    const targetAccountEmail = '<?php echo $target_email; ?>';

    function showModal(title, msg, type = 'success') {
        const overlay = document.getElementById('notifyModal');
        const icon = document.getElementById('modalIcon');
        document.getElementById('modalStatus').className = 'modal-card ' + type;
        icon.className = 'fa-solid ' + (type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark');
        document.getElementById('modalTitle').innerText = title;
        document.getElementById('modalMsg').innerText = msg;
        overlay.style.display = 'flex';
    }

    function closeModal() { document.getElementById('notifyModal').style.display = 'none'; }

    // Unified client dispatcher targeting your updated discovery_api endpoint matrix
    async function executeConnectionChange(action) {
        if (action === 'disconnect' && !confirm("Are you sure you want to remove this user from your connections?")) return;
        
        try {
            const res = await fetch('api/discovery_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, target_email: targetAccountEmail })
            });
            const result = await res.json();
            
            if (result.status === 'success') {
                // Reload the page context layout to let the backend re-render the upgraded relationship button states
                window.location.reload();
            } else {
                showModal("Error", result.message || "Failed to process option updates.", "error");
            }
        } catch (err) {
            console.error(err);
            showModal("Error", "Network framework connection dropped.", "error");
        }
    }
</script>
</body>
</html>