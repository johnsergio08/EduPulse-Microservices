<?php
session_start();
require_once 'config/db.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit(); }

$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];

// --- EXTRACTION: Pull student number directly from the users table schema ---
$student_number = "N/A";
if ($user_role === 'Student') {
    $stmt = $conn->prepare("SELECT student_number FROM users WHERE email = ?");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res && !empty($res['student_number'])) {
        $student_number = $res['student_number'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Social Space | EduPulse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; --border: #e2e8f0; --primary-dark: #059669; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: var(--bg); min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; display: flex; flex-direction: column; align-items: center; }
        
        /* Tab Navigation Matrix Layout */
        .tab-nav { display: flex; gap: 10px; margin-bottom: 25px; width: 100%; max-width: 600px; }
        .tab-btn { flex: 1; padding: 12px; border-radius: 10px; border: 1px solid var(--border); background: white; cursor: pointer; font-weight: 600; color: #64748b; transition: 0.2s; position: relative; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .tab-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Request Notification Indicator Badges */
        .tab-badge { background: #ef4444; color: white; font-size: 0.65rem; font-weight: 800; padding: 2px 7px; border-radius: 10px; display: none; }

        .glass-card { background: white; border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); width: 100%; max-width: 600px; }
        .banner { height: 120px; background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%); }
        .profile-body { padding: 0 40px 40px; text-align: center; }
        .avatar-lg { width: 120px; height: 120px; border-radius: 50%; border: 5px solid white; margin: -60px auto 0; background: #cbd5e1; object-fit: cover; }
        .avatar-wrapper { position: relative; width: 120px; height: 120px; margin: -60px auto 0; cursor: pointer; }
        .email-badge { background: #f1f5f9; padding: 4px 12px; border-radius: 99px; font-size: 0.8rem; color: #64748b; margin-top: 8px; display: inline-block; }
        .id-badge { background: #eff6ff; padding: 4px 12px; border-radius: 99px; font-size: 0.8rem; color: #1d4ed8; font-weight: 700; border: 1px solid #bfdbfe; margin-top: 8px; display: inline-block; margin-left: 5px; }
        .input-group { text-align: left; margin-top: 20px; }
        .input-group label { font-size: 0.7rem; font-weight: 800; color: #64748b; text-transform: uppercase; }
        .input-style { width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; margin-top: 5px; font-size: 0.95rem; outline: none; }
        .btn-action { background: var(--primary); color: white; border: none; width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 30px; }

        .search-item { display: flex; align-items: center; justify-content: space-between; padding: 15px; border-bottom: 1px solid var(--border); }
        .user-info { display: flex; align-items: center; gap: 12px; }
        .avatar-sm { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; background: #eee; }
        
        .profile-link { color: var(--primary); text-decoration: none; font-weight: 700; cursor: pointer; transition: color 0.15s; }
        .profile-link:hover { color: var(--primary-dark); text-decoration: underline; }

        /* Dynamic Status Actions styling */
        .btn-connect { background: var(--primary); color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; }
        .btn-connect:hover { background: var(--primary-dark); }
        .btn-pending { background: #e2e8f0; color: #64748b; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: not-allowed; }
        .btn-accept { background: #3b82f6; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; margin-right: 4px; }
        .btn-accept:hover { background: #2563eb; }
        .btn-decline { background: #ef4444; color: white; border: none; padding: 6px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; }
        .btn-decline:hover { background: #dc2626; }
        .btn-disconnect { background: #fff; color: #64748b; border: 1px solid #cbd5e1; padding: 6px 12px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer; }
        .btn-disconnect:hover { background: #fecdd3; color: #9f1239; border-color: #fca5a5; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 9999; }
        .modal-card { background: white; width: 400px; padding: 32px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="tab-nav">
            <button id="btn-profile" class="tab-btn active" onclick="switchTab('profile')"><i class="fa-solid fa-id-card"></i> My Profile</button>
            <button id="btn-discovery" class="tab-btn" onclick="switchTab('discovery')"><i class="fa-solid fa-magnifying-glass"></i> Discover People</button>
            <button id="btn-requests" class="tab-btn" onclick="switchTab('requests')">
                <i class="fa-solid fa-user-clock"></i> Friend Requests 
                <span class="tab-badge" id="requests-badge-counter">0</span>
            </button>
        </div>

        <div id="profile-view" class="glass-card">
            <div class="banner"></div>
            <div class="profile-body">
                <form id="profileForm">
                    <div class="avatar-wrapper" onclick="document.getElementById('fileInput').click()">
                        <img src="images/default_avatar.png" class="avatar-lg" id="profPic">
                        <input type="file" id="fileInput" name="profile_pic" style="display: none;" accept="image/*" onchange="previewImage(this)">
                    </div>
                    <h2 id="profName" style="margin-top:15px; font-weight: 800;"><?php echo htmlspecialchars($user_email); ?></h2>
                    
                    <div class="email-badge"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user_email); ?></div>
                    
                    <?php if ($user_role === 'Student'): ?>
                        <div class="id-badge"><i class="fa-solid fa-id-card"></i> SN: <?php echo htmlspecialchars($student_number); ?></div>
                    <?php endif; ?>

                    <p style="color: var(--primary); font-weight: 600; font-size: 0.85rem; margin-top: 5px;"><?php echo htmlspecialchars($user_role); ?></p>

                    <div class="input-group">
                        <label>Display Name</label>
                        <input type="text" name="full_name" id="editName" class="input-style" placeholder="Your Name">
                    </div>
                    <div class="input-group">
                        <label>Department</label>
                        <input type="text" name="department" id="editDept" class="input-style" placeholder="e.g. BS Computer Engineering">
                    </div>
                    <div class="input-group">
                        <label>Bio</label>
                        <textarea name="bio" id="editBio" class="input-style" style="height: 80px;" placeholder="Write something about yourself..."></textarea>
                    </div>
                    <input type="hidden" name="existing_pic" id="existingPicPath">
                    <button type="button" class="btn-action" onclick="saveProfile()">Sync Profile Details</button>
                </form>
            </div>
        </div>

        <div id="discovery-view" class="glass-card" style="display: none; padding: 30px;">
            <h3>Find Connections</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 30px;">
                <input type="text" id="searchInput" class="input-style" style="margin-top:0;" placeholder="Search name or email...">
                <button onclick="searchPeople()" class="btn-action" style="margin-top:0; width: auto; padding: 0 20px;">Search</button>
            </div>
            <div id="search-results"></div>
            <h3 style="margin-top: 30px; margin-bottom: 15px;"><i class="fa-solid fa-user-group"></i> My Friends</h3>
            <div id="friends-list" style="display: grid; grid-template-columns: 1fr; gap: 10px;"></div>
        </div>

        <div id="requests-view" class="glass-card" style="display: none; padding: 30px;">
            <h3 style="margin-bottom: 20px;"><i class="fa-solid fa-bell-concierge"></i> Pending Inbound Invitations</h3>
            <div id="incoming-requests-container" style="display: flex; flex-direction: column; gap: 10px;">
                </div>
        </div>
    </div>

    <div class="modal-overlay" id="notifyModal">
        <div class="modal-card" id="modalStatus">
            <i id="modalIcon" class="fa-solid"></i>
            <h3 id="modalTitle">Title</h3>
            <p id="modalMsg">Message goes here.</p>
            <button onclick="closeModal()" class="btn-action" style="margin-top: 0; width: 100%;">Acknowledge</button>
        </div>
    </div>

<script>
    const currentUserEmail = '<?php echo $user_email; ?>';

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

    // Enhanced 3-Way Active View Router State Controller Switcher
    function switchTab(view) {
        document.getElementById('profile-view').style.display = (view === 'profile') ? 'block' : 'none';
        document.getElementById('discovery-view').style.display = (view === 'discovery') ? 'block' : 'none';
        document.getElementById('requests-view').style.display = (view === 'requests') ? 'block' : 'none';
        
        document.getElementById('btn-profile').classList.toggle('active', view === 'profile');
        document.getElementById('btn-discovery').classList.toggle('active', view === 'discovery');
        document.getElementById('btn-requests').classList.toggle('active', view === 'requests');

        if(view === 'discovery') {
            loadFriends();
            document.getElementById('search-results').innerHTML = '';
            document.getElementById('searchInput').value = '';
        } else if (view === 'requests') {
            loadIncomingRequests();
        }
    }

    function viewUserProfile(email) {
        window.location.href = `view_profile.php?email=${encodeURIComponent(email)}`;
    }

    async function loadProfile() {
        const res = await fetch('api/profile_api.php');
        const data = await res.json();
        document.getElementById('editName').value = data.full_name || '';
        document.getElementById('editDept').value = data.department || '';
        document.getElementById('editBio').value = data.bio || '';
        if(data.full_name) document.getElementById('profName').innerText = data.full_name;
        if(data.profile_pic && data.profile_pic !== 'default_avatar.png') document.getElementById('profPic').src = 'uploads/' + data.profile_pic;
    }

    async function saveProfile() {
        try {
            const formElement = document.getElementById('profileForm');
            const fd = new FormData(formElement);
            const res = await fetch('api/profile_api.php', { method: 'POST', body: fd });
            const text = await res.text(); 
            let result = JSON.parse(text);

            if(result.status === 'success') { 
                showModal("Success", "Profile updated successfully."); 
                setTimeout(() => { window.location.reload(); }, 1500);
            } else {
                showModal("Error", result.message || "Failed to save details.", "error");
            }
        } catch (error) {
            showModal("Error", "Something went wrong while connecting to the server.", "error");
        }
    }

    async function searchPeople() {
        const q = document.getElementById('searchInput').value;
        const res = await fetch(`api/discovery_api.php?search=${q}`);
        const users = await res.json();
        
        document.getElementById('search-results').innerHTML = users.map(u => {
            let actionButtonsHTML = '';
            
            if (!u.status) {
                actionButtonsHTML = `<button onclick="manageConnection('send_request', '${u.email}')" class="btn-connect">Connect</button>`;
            } else if (u.status === 'pending') {
                if (u.requester_email === currentUserEmail) {
                    actionButtonsHTML = `<button class="btn-pending" disabled>Pending</button>`;
                } else {
                    actionButtonsHTML = `
                        <div style="display: flex;">
                            <button onclick="manageConnection('accept_request', '${u.email}')" class="btn-accept">Accept</button>
                            <button onclick="manageConnection('decline_request', '${u.email}')" class="btn-decline">Decline</button>
                        </div>
                    `;
                }
            } else if (u.status === 'accepted') {
                actionButtonsHTML = `<button onclick="manageConnection('disconnect', '${u.email}')" class="btn-disconnect">Disconnect</button>`;
            }

            return `
                <div class="search-item">
                    <div class="user-info">
                        <img src="uploads/${u.profile_pic || 'default_avatar.png'}" class="avatar-sm">
                        <div>
                            <a onclick="viewUserProfile('${u.email}')" class="profile-link">${u.full_name || u.email}</a>
                            <br><small>${u.department || 'N/A'}</small>
                        </div>
                    </div>
                    <div class="action-container">${actionButtonsHTML}</div>
                </div>
            `;
        }).join('') || '<p style="padding:15px; font-size:0.85rem; color:#64748b; text-align:center;">No campus accounts matched your search terms.</p>';
    }

    // TARGETED HOOK: Dynamically loads requests awaiting permission verification
    async function loadIncomingRequests() {
        const res = await fetch('api/discovery_api.php?get_requests=1');
        const requests = await res.json();
        
        // Update notification count indicator text parameters inside the tab header button matrix
        const badge = document.getElementById('requests-badge-counter');
        if (requests.length > 0) {
            badge.innerText = requests.length;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }

        document.getElementById('incoming-requests-container').innerHTML = requests.map(r => `
            <div class="search-item" style="background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 5px;">
                <div class="user-info">
                    <img src="uploads/${r.profile_pic || 'default_avatar.png'}" class="avatar-sm">
                    <div>
                        <a onclick="viewUserProfile('${r.email}')" class="profile-link">${r.full_name || r.email}</a>
                        <br><small>${r.department || 'N/A'}</small>
                    </div>
                </div>
                <div style="display: flex;">
                    <button onclick="manageConnection('accept_request', '${r.email}')" class="btn-accept"><i class="fa-solid fa-user-plus"></i> Accept</button>
                    <button onclick="manageConnection('decline_request', '${r.email}')" class="btn-decline"><i class="fa-solid fa-user-xmark"></i> Decline</button>
                </div>
            </div>
        `).join('') || '<p style="padding:30px 10px; font-size:0.85rem; color:#64748b; text-align:center; font-style: italic;">No pending friend requests at the moment.</p>';
    }

    async function manageConnection(action, email) {
        try {
            const res = await fetch('api/discovery_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: action, target_email: email })
            });
            const result = await res.json();
            
            if (result.status === 'success') {
                if (document.getElementById('searchInput').value.trim() !== '') searchPeople();
                loadFriends();
                loadIncomingRequests(); // Sync indicators immediately on action callback
            } else {
                showModal("Error", result.message || "Failed to complete transaction.", "error");
            }
        } catch (err) {
            showModal("Error", "Server sync dropped.", "error");
        }
    }

    async function loadFriends() {
        const res = await fetch('api/discovery_api.php');
        const friends = await res.json();
        
        document.getElementById('friends-list').innerHTML = friends.map(f => `
            <div class="search-item" style="background:#f8fafc; border-radius:10px; border:none; margin-bottom: 5px;">
                <div class="user-info">
                    <img src="uploads/${f.profile_pic || 'default_avatar.png'}" class="avatar-sm">
                    <div>
                        <a onclick="viewUserProfile('${f.email}')" class="profile-link">${f.full_name}</a>
                        <br><small>${f.department}</small>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 15px;">
                    <i class="fa-solid fa-circle-check" style="color:var(--primary);"></i>
                    <button onclick="manageConnection('disconnect', '${f.email}')" class="btn-disconnect"><i class="fa-solid fa-user-minus"></i> Remove</button>
                </div>
            </div>
        `).join('') || '<p style="padding:10px; font-size:0.85rem; color:#64748b; text-align:center;">You haven\'t established any approved connections yet.</p>';
    }

    document.addEventListener('DOMContentLoaded', () => { 
        loadProfile(); 
        loadIncomingRequests(); // Trigger an initial background poll for badge counts
    });
</script>
</body>
</html>