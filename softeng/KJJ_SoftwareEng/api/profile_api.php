<?php
header("Content-Type: application/json");
require_once '../config/db.php';
session_start();

if (!isset($_SESSION['email'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$user_email = $_SESSION['email'];
$method = $_SERVER['REQUEST_METHOD'];

// --- GET: Fetch Profile (Supports self view and target peer view) ---
if ($method === 'GET') {
    // INTERCEPTOR: If a targeted email query string exists, use it. Otherwise, default to session email.
    $target_email = isset($_GET['email']) ? trim($_GET['email']) : $user_email;

    $stmt = $conn->prepare("SELECT * FROM user_profiles WHERE user_email = ?");
    $stmt->bind_param("s", $target_email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($profile = $result->fetch_assoc()) {
        echo json_encode($profile);
    } else {
        // Fallback layout context values if the target account hasn't established a profile card row yet
        echo json_encode([
            "full_name" => "", 
            "bio" => "This user hasn't populated a biography description yet.", 
            "department" => "Not Specified", 
            "profile_pic" => "default_avatar.png"
        ]);
    }
    exit();
}

// --- POST: Update Profile Details ---
if ($method === 'POST') {
    $full_name = $_POST['full_name'] ?? '';
    $department = $_POST['department'] ?? '';
    $bio = $_POST['bio'] ?? '';
    
    // Fetch the current picture from DB to avoid overwriting with 'default' if upload fails
    $stmt_check = $conn->prepare("SELECT profile_pic FROM user_profiles WHERE user_email = ?");
    $stmt_check->bind_param("s", $user_email);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    $row = $res_check->fetch_assoc();
    $profile_pic = $row['profile_pic'] ?? 'default_avatar.png';
    
    // Handle File Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $new_filename = "profile_" . md5($user_email) . "_" . time() . "." . $ext;
            $upload_directory = __DIR__ . '/../uploads/';
            
            if (!file_exists($upload_directory)) {
                mkdir($upload_directory, 0755, true);
            }
            
            $upload_path = $upload_directory . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $upload_path)) {
                $profile_pic = $new_filename;
            } else {
                echo json_encode([
                    "status" => "error", 
                    "message" => "Failed to move file. Ensure target folder exists and has write permissions."
                ]);
                exit();
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Invalid file extension type."]);
            exit();
        }
    }

    $stmt = $conn->prepare("INSERT INTO user_profiles (user_email, full_name, bio, department, profile_pic) 
                            VALUES (?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            full_name = ?, 
                            bio = ?, 
                            department = ?, 
                            profile_pic = ?");
    
    $stmt->bind_param("sssssssss", 
        $user_email, $full_name, $bio, $department, $profile_pic, 
        $full_name, $bio, $department, $profile_pic  
    );
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "new_pic" => $profile_pic]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database Error: " . $conn->error]);
    }
    exit();
}
?>