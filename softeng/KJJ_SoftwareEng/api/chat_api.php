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

// GET: Fetch conversation history OR unread count breakdown
if ($method === 'GET') {
    
    // 1. Handle detailed unread message check
    if (isset($_GET['check_unread'])) {
        // Get total unread count for the main tab button
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM chat_messages WHERE receiver_email = ? AND is_read = 0");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];

        // Get breakdown per sender for individual sidebar/profile badges
        $stmt2 = $conn->prepare("SELECT sender_email, COUNT(*) as count FROM chat_messages WHERE receiver_email = ? AND is_read = 0 GROUP BY sender_email");
        $stmt2->bind_param("s", $user_email);
        $stmt2->execute();
        $per_user = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

        echo json_encode([
            "total" => (int)$total,
            "per_user" => $per_user
        ]);
        exit();
    }

    // 2. Standard history fetch (requires 'with' parameter)
    if (!isset($_GET['with'])) {
        echo json_encode(["status" => "error", "message" => "No recipient specified"]);
        exit();
    }
    
    $other_user = $_GET['with'];

    // Mark messages as read when opening the chat
    $upd = $conn->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_email = ? AND receiver_email = ?");
    $upd->bind_param("ss", $other_user, $user_email);
    $upd->execute();

    // Fetch history
    $stmt = $conn->prepare("SELECT * FROM chat_messages 
                            WHERE (sender_email = ? AND receiver_email = ?) 
                            OR (sender_email = ? AND receiver_email = ?) 
                            ORDER BY created_at ASC");
    $stmt->bind_param("ssss", $user_email, $other_user, $other_user, $user_email);
    $stmt->execute();
    
    echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
    exit();
}

// POST: Send a message
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (empty($data['receiver_email']) || empty($data['message'])) {
        echo json_encode(["status" => "error", "message" => "Incomplete data"]);
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO chat_messages (sender_email, receiver_email, message_text) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $user_email, $data['receiver_email'], $data['message']);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => $conn->error]);
    }
    exit();
}
?>