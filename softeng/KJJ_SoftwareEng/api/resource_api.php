<?php
// Set headers immediately before any code execution
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle CORS Preflight Pre-checks seamlessly
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once '../config/db.php';
session_start();

if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_email = $_SESSION['email'];
$user_role = $_SESSION['role'];
$method = $_SERVER['REQUEST_METHOD'];

// Dynamic absolute path builder to ensure consistency across environments
$base_dir = dirname(__DIR__) . "/uploads/resources/";

// --- GET: List resources for a specific section ---
if ($method === 'GET') {
    if (!isset($_GET['section_id']) || empty($_GET['section_id'])) {
        echo json_encode(["status" => "error", "message" => "Missing section ID"]);
        exit();
    }
    
    $section_id = (int)$_GET['section_id'];
    
    // Fallback Check: Try selecting all columns first to see if specific field names don't exist yet
    $stmt = $conn->prepare("SELECT * FROM resources WHERE section_id = ? ORDER BY id DESC");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "Database Query Preparation Failed: " . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $resources = [];
    while ($row = $result->fetch_assoc()) {
        // Map old or custom column names to the frontend properties if needed
        $resources[] = [
            "id" => $row['id'],
            "section_id" => $row['section_id'],
            "file_display_name" => $row['file_display_name'] ?? $row['name'] ?? 'Unnamed Document',
            "file_path" => $row['file_path'] ?? $row['path'] ?? '',
            "file_type" => $row['file_type'] ?? $row['type'] ?? 'application/octet-stream',
            "file_size" => $row['file_size'] ?? $row['size'] ?? 0,
            "created_at" => $row['created_at'] ?? $row['upload_time'] ?? 'Unknown Date'
        ];
    }
    
    echo json_encode($resources);
    exit();
}
// --- POST: Upload a new resource ---
if ($method === 'POST') {
    if ($user_role === 'Student') {
        echo json_encode(["status" => "error", "message" => "Students cannot upload files"]);
        exit();
    }

    if (!isset($_POST['section_id']) || !isset($_POST['file_display_name']) || !isset($_FILES['resource_file'])) {
        echo json_encode(["status" => "error", "message" => "Required form fields are missing."]);
        exit();
    }

    $section_id = (int)$_POST['section_id'];
    $display_name = trim($_POST['file_display_name']);
    $file = $_FILES['resource_file'];

    // Check for upload errors from PHP's end
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["status" => "error", "message" => "PHP File Upload Error Code: " . $file['error']]);
        exit();
    }

    // Ensure resources directory exists securely
    if (!file_exists($base_dir)) {
        mkdir($base_dir, 0755, true);
    }

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_name = "res_" . uniqid() . "_" . time() . "." . $file_ext;
    $target_path = $base_dir . $unique_name;

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        $stmt = $conn->prepare("INSERT INTO resources (section_id, uploader_email, file_display_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssi", $section_id, $user_email, $display_name, $unique_name, $file['type'], $file['size']);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success", "message" => "File uploaded successfully."]);
        } else {
            // Clean up the isolated file if DB insertion errors out
            if (file_exists($target_path)) {
                unlink($target_path);
            }
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "Upload failed. Verify write permissions on folder: " . $base_dir]);
    }
    exit();
}

// --- DELETE: Remove a resource ---
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (!isset($data['id'])) {
        echo json_encode(["status" => "error", "message" => "Missing target resource ID."]);
        exit();
    }
    
    $res_id = (int)$data['id'];

    $stmt = $conn->prepare("SELECT file_path FROM resources WHERE id = ? AND uploader_email = ?");
    $stmt->bind_param("is", $res_id, $user_email);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    if ($res) {
        $full_file_path = $base_dir . $res['file_path'];
        if (file_exists($full_file_path)) {
            unlink($full_file_path);
        }
        
        $del = $conn->prepare("DELETE FROM resources WHERE id = ?");
        $del->bind_param("i", $res_id);
        $del->execute();
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Not authorized or material not found."]);
    }
    exit();
}

// Catch-all safety gate for alternative execution methods
echo json_encode(["status" => "error", "message" => "Unsupported request method: " . $method]);
exit();
?>