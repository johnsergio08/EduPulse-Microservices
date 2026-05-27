<?php
header("Content-Type: application/json");
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'Student') {
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit();
}

if (!isset($_GET['section_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing Workspace Tracking ID"]);
    exit();
}

$user_email = $_SESSION['email'];
$section_id = (int)$_GET['section_id'];

// 1. Get the Student ID mapping record linked to this section
$stud_stmt = $conn->prepare("
    SELECT s.id 
    FROM students s
    JOIN user_profiles up ON s.name = up.full_name
    WHERE up.user_email = ? AND s.section_id = ?
");
$stud_stmt->bind_param("si", $user_email, $section_id);
$stud_stmt->execute();
$stud_res = $stud_stmt->get_result()->fetch_assoc();
$student_table_id = $stud_res['id'] ?? null;

if (!$student_table_id) {
    echo json_encode(["status" => "success", "summary" => ["midterm" => "0.00", "finals" => "0.00"], "categories" => []]);
    exit();
}

// 2. Build the structural gradebook grid map trees
$categories = [];
$cat_query = $conn->query("SELECT id, term, name, weight FROM grading_categories WHERE section_id = $section_id ORDER BY id ASC");
while ($c = $cat_query->fetch_assoc()) {
    $c['assignments'] = [];
    $categories[$c['id']] = $c;
}

if (!empty($categories)) {
    $cat_ids_str = implode(',', array_keys($categories));
    
    // Fetch assignments inside those buckets
    $ass_query = $conn->query("SELECT id, category_id, name, max_score FROM assignments WHERE category_id IN ($cat_ids_str) ORDER BY id ASC");
    $assignments = [];
    while ($a = $ass_query->fetch_assoc()) {
        $a['score'] = null; // default value
        $assignments[$a['id']] = $a;
    }
    
    if (!empty($assignments)) {
        $ass_ids_str = implode(',', array_keys($assignments));
        
        // Match student performance marks securely
        $score_query = $conn->query("SELECT assignment_id, score FROM student_scores WHERE student_id = $student_table_id AND assignment_id IN ($ass_ids_str)");
        while ($s = $score_query->fetch_assoc()) {
            if (isset($assignments[$s['assignment_id']])) {
                $assignments[$s['assignment_id']]['score'] = $s['score'];
            }
        }
    }
    
    // Nest assignments back into their parenting grading buckets
    foreach ($assignments as $a) {
        $categories[$a['category_id']]['assignments'][] = $a;
    }
}

// 3. Compute accurate cumulative weights
$midterm_total = 0;
$finals_total = 0;

foreach ($categories as $cat) {
    $cat_earned = 0;
    $cat_max = 0;
    foreach ($cat['assignments'] as $ass) {
        $cat_max += $ass['max_score'];
        $cat_earned += $ass['score'] ?? 0;
    }
    
    if ($cat_max > 0) {
        $calculated_weight = ($cat_earned / $cat_max) * $cat['weight'];
        if ($cat['term'] === 'Midterm') {
            $midterm_total += $calculated_weight;
        } else {
            $finals_total += $calculated_weight;
        }
    }
}

echo json_encode([
    "status" => "success",
    "summary" => [
        "midterm" => number_format($midterm_total, 2),
        "finals" => number_format($finals_total, 2)
    ],
    "categories" => array_values($categories)
]);
exit();
?>