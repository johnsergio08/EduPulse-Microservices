<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['role'] !== 'Student') {
    header("Location: index.php");
    exit();
}

$user_email = $_SESSION['email'];
$selected_section = isset($_GET['section']) ? $_GET['section'] : null;
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'overview';

// 1. Fetch the Student's actual name/ID from their profile to link them to classes
$profile_query = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
$profile_query->bind_param("s", $user_email);
$profile_query->execute();
$prof_res = $profile_query->get_result()->fetch_assoc();
$full_name = $prof_res['full_name'] ?? '';

// 2. Find sections where this student is enrolled
$sections = [];
if ($full_name) {
    $sec_query = $conn->prepare("SELECT s.id, s.section_name, s.owner_email FROM sections s 
                                 JOIN students st ON s.id = st.section_id 
                                 WHERE st.name = ?");
    $sec_query->bind_param("s", $full_name);
    $sec_query->execute();
    $sections = $sec_query->get_result()->fetch_all(MYSQLI_ASSOC);
}

// 3. Handle data for the selected section
$current_section_id = null;
$professor_email = null;
if ($selected_section) {
    foreach ($sections as $s) {
        if ($s['section_name'] === $selected_section) {
            $current_section_id = $s['id'];
            $professor_email = $s['owner_email'];
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Portal | EduPulse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #10b981; --bg: #f8fafc; --border: #e2e8f0; --text: #1e293b; --danger: #ef4444; }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: var(--bg); min-height: 100vh; }
        .main-content { flex: 1; padding: 40px; }
        .data-card { background: white; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); overflow: hidden; margin-bottom: 24px; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .tab-nav { display: flex; gap: 20px; padding: 0 24px; border-bottom: 1px solid var(--border); background: #fcfcfc; }
        .tab-link { padding: 15px 0; color: #64748b; text-decoration: none; font-weight: 600; font-size: 0.9rem; border-bottom: 3px solid transparent; }
        .tab-link.active { color: var(--primary); border-bottom-color: var(--primary); }
        .grade-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.9rem; }
        .badge { background: #f1f5f9; padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; color: #475569; }
        
        /* Summary Overview Boxes */
        .summary-wrapper { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .summary-box { background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 20px; text-align: center; }
        .summary-box h4 { font-size: 0.75rem; text-transform: uppercase; color: #64748b; margin-bottom: 5px; }
        .summary-box p { font-size: 1.8rem; font-weight: 800; color: var(--primary); }
        .bucket-block { margin-bottom: 25px; background: white; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .bucket-title { background: #f1f5f9; padding: 12px 20px; font-weight: 700; font-size: 0.85rem; display: flex; justify-content: space-between; color: #334155; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php if (!$selected_section): ?>
            <div style="text-align: center; margin-top: 100px;">
                <i class="fa-solid fa-graduation-cap" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 20px;"></i>
                <h2>Welcome to your Student Portal</h2>
                <p style="color: #64748b;">Select a class from the sidebar to view your progress.</p>
            </div>
        <?php else: ?>
            <div class="data-card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-chalkboard-user"></i> <?php echo htmlspecialchars($selected_section); ?></h3>
                    <div style="font-size: 0.85rem; color: var(--primary); font-weight: 700;">Prof: <?php echo htmlspecialchars($professor_email); ?></div>
                </div>

                <div class="tab-nav">
                    <a href="?section=<?php echo urlencode($selected_section); ?>&tab=overview" class="tab-link <?php echo $current_tab == 'overview' ? 'active' : ''; ?>">Class Feed</a>
                    <a href="?section=<?php echo urlencode($selected_section); ?>&tab=grades" class="tab-link <?php echo $current_tab == 'grades' ? 'active' : ''; ?>">My Grades</a>
                    <a href="?section=<?php echo urlencode($selected_section); ?>&tab=resources" class="tab-link <?php echo $current_tab == 'resources' ? 'active' : ''; ?>">Learning Materials</a>
                </div>

                <div style="padding: 24px;">
                    <?php if ($current_tab === 'overview'): ?>
                        <div id="api-feed-container">
                            <p style="color: grey; font-style: italic;">Loading class announcements...</p>
                        </div>

                    <?php elseif ($current_tab === 'grades'): ?>
                        <div id="student-grades-container">
                            <p style="color: grey; font-style: italic;">Loading your academic record...</p>
                        </div>

                    <?php elseif ($current_tab === 'resources'): ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="text-align: left; font-size: 0.75rem; color: #64748b; text-transform: uppercase; border-bottom: 1px solid var(--border);">
                                    <th style="padding: 10px 0;">File Name</th>
                                    <th>Date</th>
                                    <th style="text-align: right;">Download</th>
                                </tr>
                            </thead>
                            <tbody id="resource-list-container">
                                <tr><td colspan="3" style="padding:15px 0; color:grey;">Loading tracking materials...</td></tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const sectionId = <?php echo $current_section_id ?? 'null'; ?>;

        async function loadFeed() {
            if(!sectionId) return;
            try {
                const res = await fetch(`api/social_api.php?section_id=${sectionId}`);
                const posts = await res.json();
                document.getElementById('api-feed-container').innerHTML = posts.map(p => `
                    <div style="padding: 15px; border: 1px solid #f1f5f9; border-radius: 8px; margin-bottom: 10px; background:#fff;">
                        <div style="font-weight: 700; color: var(--primary); font-size: 0.8rem;">${p.author_name} • ${p.created_at}</div>
                        <p style="margin-top: 5px; font-size: 0.95rem; color:#334155;">${p.post_content}</p>
                    </div>
                `).join('') || '<p style="color:grey; font-style:italic;">No announcements yet.</p>';
            } catch(e) { console.error(e); }
        }

        async function loadResources() {
            if(!sectionId) return;
            try {
                const res = await fetch(`api/resource_api.php?section_id=${sectionId}`);
                const files = await res.json();
                document.getElementById('resource-list-container').innerHTML = files.map(f => `
                    <tr style="border-bottom: 1px solid #f8fafc;">
                        <td style="padding: 12px 0;"><strong><i class="fa-solid fa-file-lines" style="color:#64748b; margin-right:6px;"></i> ${f.file_display_name}</strong></td>
                        <td style="font-size: 0.8rem; color: grey;">${f.created_at}</td>
                        <td style="text-align: right;">
                            <a href="uploads/resources/${f.file_path}" download style="color: var(--primary); font-size:1.1rem;"><i class="fa-solid fa-cloud-arrow-down"></i></a>
                        </td>
                    </tr>
                `).join('') || '<tr><td colspan="3" style="padding:15px 0; color:grey; font-style:italic;">No learning materials available for this section.</td></tr>';
            } catch(e) { console.error(e); }
        }

        async function loadMyGrades() {
            if(!sectionId) return;
            const container = document.getElementById('student-grades-container');
            
            try {
                const res = await fetch(`api/student_grades_api.php?section_id=${sectionId}`);
                const result = await res.json();
                
                if(result.status === 'error') {
                    container.innerHTML = `<p style="color:var(--danger);">${result.message}</p>`;
                    return;
                }
                
                // 1. Build upper calculation score widgets
                let html = `
                    <div class="summary-wrapper">
                        <div class="summary-box">
                            <h4>Calculated Midterm Score</h4>
                            <p>${result.summary.midterm}%</p>
                        </div>
                        <div class="summary-box">
                            <h4>Calculated Finals Score</h4>
                            <p>${result.summary.finals}%</p>
                        </div>
                    </div>
                `;
                
                // 2. Map out assignment rows nested inside their weighting structure buckets
                if(result.categories.length === 0) {
                    html += `<p style="color:grey; font-style:italic; text-align:center;">No grading categories initialized by the instructor yet.</p>`;
                } else {
                    result.categories.forEach(cat => {
                        html += `
                            <div class="bucket-block">
                                <div class="bucket-title">
                                    <span>${cat.name} (${cat.term})</span>
                                    <span>Weight: ${cat.weight}%</span>
                                </div>
                                <div style="padding: 0 20px;">
                        `;
                        
                        if(cat.assignments.length === 0) {
                            html += `<div style="padding:15px 0; color:grey; font-style:italic; font-size:0.85rem;">No requirements recorded inside this category.</div>`;
                        } else {
                            cat.assignments.forEach(ass => {
                                const scoreDisplay = ass.score !== null ? `<strong>${ass.score}</strong>` : `<span style="color:grey; font-style:italic;">Ungraded</span>`;
                                html += `
                                    <div class="grade-row">
                                        <span style="font-weight:500; color:#475569;">${ass.name}</span>
                                        <span>${scoreDisplay} / ${ass.max_score}</span>
                                    </div>
                                `;
                            });
                        }
                        
                        html += `</div></div>`;
                    });
                }
                
                container.innerHTML = html;
            } catch(err) {
                console.error(err);
                container.innerHTML = `<p style="color:var(--danger);">Failed to load academic records.</p>`;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            if("<?php echo $current_tab; ?>" === "overview") loadFeed();
            if("<?php echo $current_tab; ?>" === "resources") loadResources();
            if("<?php echo $current_tab; ?>" === "grades") loadMyGrades();
        });
    </script>
</body>
</html>