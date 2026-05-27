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

// 1. GET: Fetch notifications list and unread count
if ($method === 'GET') {
    // Fetch all notifications for this user joining profile states
    $stmt = $conn->prepare("SELECT n.*, p.full_name AS sender_name, p.profile_pic AS sender_pic 
                            FROM user_notifications n 
                            LEFT JOIN user_profiles p ON n.sender_email = p.user_email 
                            WHERE n.receiver_email = ? 
                            ORDER BY n.created_at DESC LIMIT 50");
    $stmt->bind_param("s", $user_email);
    $stmt->execute();
    $notifs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Fetch unread count
    $cnt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM user_notifications WHERE receiver_email = ? AND is_read = 0");
    $cnt->bind_param("s", $user_email);
    $cnt->execute();
    $unread_res = $cnt->get_result()->fetch_assoc();

    echo json_encode([
        "status" => "success",
        "notifications" => $notifs,
        "unread_count" => (int)$unread_res['unread_count']
    ]);
    exit();
}

// 2. POST: Process actions
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    // FIX: Restored concrete code mapping block for mark_read
    if ($action === 'mark_read') {
        // Explicitly check for a valid notification ID to mark an individual item
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE id = ? AND receiver_email = ?");
            $stmt->bind_param("is", $data['id'], $user_email);
        } else {
            // Global global fallback: Mark ALL as read if no explicit single ID payload is defined
            $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1 WHERE receiver_email = ?");
            $stmt->bind_param("s", $user_email);
        }
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database Update Error: " . $conn->error]);
        }
        exit();
    }
    
    // BACKEND HANDLER HOOK: Permanently wipe the notification log for this user row context
    elseif ($action === 'clear_all') {
        $stmt = $conn->prepare("DELETE FROM user_notifications WHERE receiver_email = ?");
        $stmt->bind_param("s", $user_email);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database Deletion Error: " . $conn->error]);
        }
        exit();
    }
    
    echo json_encode(["status" => "error", "message" => "Invalid action requested."]);
    exit();
}
?>