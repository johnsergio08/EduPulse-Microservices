<?php
// Ensure the profile name is available for matching enrolled classes
$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];

// Fetch the full name for student enrollment matching
$sidebar_profile_name = "";
$p_stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
$p_stmt->bind_param("s", $user_email);
$p_stmt->execute();
$p_res = $p_stmt->get_result();
if ($p_row = $p_res->fetch_assoc()) {
    $sidebar_profile_name = $p_row['full_name'];
}
?>

<style>
    .sidebar { width: 280px; background: #0f172a; color: white; display: flex; flex-direction: column; height: 100vh; position: sticky; top: 0; }
    .sidebar-header { padding: 32px 24px; display: flex; align-items: center; gap: 12px; }
    .sidebar-header i { font-size: 1.5rem; color: #10b981; }
    .sidebar-header h2 { font-size: 1.25rem; font-weight: 800; letter-spacing: 0.5px; }

    .sidebar-menu { flex: 1; padding: 0 16px; overflow-y: auto; }
    .menu-label { padding: 24px 12px 8px; font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 1px; }
    .sidebar-menu a { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
        padding: 12px; 
        color: #94a3b8; 
        text-decoration: none; 
        font-size: 0.9rem; 
        font-weight: 500; 
        border-radius: 8px; 
        transition: 0.2s;
    }
    .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.05); color: white; }
    .sidebar-menu a.active { color: #10b981; border-left: 3px solid #10b981; border-radius: 0 8px 8px 0; }

    .sidebar-footer { padding: 24px; border-top: 1px solid rgba(255,255,255,0.05); }
    .sidebar-footer a { color: #f87171; text-decoration: none; font-weight: 600; font-size: 0.85rem; display: flex; align-items: center; gap: 8px; }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <i class="fa-solid fa-graduation-cap"></i>
        <h2>EDUPULSE</h2>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-label">Main</div>
        
        <?php $home_page = ($user_role === 'Student') ? 'student_portal.php' : 'dashboard.php'; ?>
        <a href="<?php echo $home_page; ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) == $home_page && !isset($_GET['section'])) ? 'active' : ''; ?>">
            <i class="fa-solid fa-house"></i> Home
        </a>

        <div class="menu-label">Collaboration</div>
        <a href="social_space.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'social_space.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-circle"></i> My Profile
        </a>
        <a href="social_feed.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'social_feed.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-rss"></i> Campus Feed
        </a>        

        <?php if ($user_role === 'System Admin'): ?>
            <div class="menu-label">Admin</div>
            <a href="dashboard.php?view=users" class="<?php echo (isset($_GET['view']) && $_GET['view'] === 'users') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users"></i> Users
            </a>
            <a href="dashboard.php?view=logs" class="<?php echo (isset($_GET['view']) && $_GET['view'] === 'logs') ? 'active' : ''; ?>">
                <i class="fa-solid fa-list-check"></i> Audit Logs
            </a>

        <?php elseif ($user_role === 'Student'): ?>
            <div class="menu-label">My Enrolled Classes</div>
            <?php 
            // Join sections with students table where the name matches the profile
            $s_stmt = $conn->prepare("SELECT s.section_name FROM sections s 
                                      JOIN students st ON s.id = st.section_id 
                                      WHERE st.name = ?");
            $s_stmt->bind_param("s", $sidebar_profile_name);
            $s_stmt->execute();
            $s_res = $s_stmt->get_result();
            
            if ($s_res->num_rows > 0):
                while($sec = $s_res->fetch_assoc()): 
                    $active = (isset($_GET['section']) && $_GET['section'] == $sec['section_name']) ? 'active' : '';
            ?>
                <a href="student_portal.php?section=<?php echo urlencode($sec['section_name']); ?>" class="<?php echo $active; ?>">
                    <i class="fa-solid fa-book-bookmark"></i> <?php echo htmlspecialchars($sec['section_name']); ?>
                </a>
            <?php endwhile; else: ?>
                <p style="color: #64748b; font-size: 0.7rem; padding: 0 12px;">No classes found. Ensure your Profile Name matches the Professor's record.</p>
            <?php endif; ?>

        <?php else: // Teacher / Admin Office ?>
            <div class="menu-label" style="display: flex; justify-content: space-between; align-items: center;">
                Assigned Sections
                <a href="dashboard.php" style="padding: 0; color: #10b981; margin: 0; display: inline-flex;" title="Add New Class">
                    <i class="fa-solid fa-circle-plus"></i>
                </a>
            </div>
            <?php 
            $stmt_side = $conn->prepare("SELECT section_name FROM sections WHERE owner_email = ?");
            $stmt_side->bind_param("s", $user_email);
            $stmt_side->execute();
            $res_side = $stmt_side->get_result();
            while($sec = $res_side->fetch_assoc()): 
                $active = (isset($_GET['section']) && $_GET['section'] == $sec['section_name']) ? 'active' : '';
            ?>
                <a href="dashboard.php?section=<?php echo urlencode($sec['section_name']); ?>" class="<?php echo $active; ?>">
                    <i class="fa-solid fa-users-rectangle"></i> <?php echo htmlspecialchars($sec['section_name']); ?>
                </a>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> Secure Logout</a>
    </div>
</div>