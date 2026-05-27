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

// 1. GET Requests: chat retrieval, active conversation lists, user searches
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    // ACTION: SEARCH USERS
    if ($action === 'search_users') {
        $query = isset($_GET['query']) ? "%" . $_GET['query'] . "%" : '';
        if (empty($query)) {
            echo json_encode([]);
            exit();
        }

        $stmt = $conn->prepare("SELECT u.email, 
                                       COALESCE(p.full_name, u.role) AS full_name, 
                                       COALESCE(p.profile_pic, 'default_avatar.png') AS profile_pic, 
                                       p.department 
                                FROM users u
                                LEFT JOIN user_profiles p ON u.email = p.user_email
                                WHERE (p.full_name LIKE ? OR u.email LIKE ?) 
                                AND u.email != ? 
                                LIMIT 10");
        $stmt->bind_param("sss", $query, $query, $user_email);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
    }

    // ACTION: GET CONVERSATION LIST
    elseif ($action === 'get_conversations') {
        // Query unique chat partners
        $stmt = $conn->prepare("SELECT DISTINCT 
                                       CASE WHEN sender_email = ? THEN receiver_email ELSE sender_email END AS contact_email
                                FROM user_messages
                                WHERE sender_email = ? OR receiver_email = ?");
        $stmt->bind_param("sss", $user_email, $user_email, $user_email);
        $stmt->execute();
        $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $conversations = [];
        foreach ($contacts as $c) {
            $email = $c['contact_email'];
            
            // Get profile details
            $p_stmt = $conn->prepare("SELECT COALESCE(full_name, 'User') as full_name, COALESCE(profile_pic, 'default_avatar.png') as profile_pic FROM user_profiles WHERE user_email = ?");
            $p_stmt->bind_param("s", $email);
            $p_stmt->execute();
            $profile = $p_stmt->get_result()->fetch_assoc();
            
            $name = $profile['full_name'] ?? $email;
            $pic = $profile['profile_pic'] ?? 'default_avatar.png';

            // Get last message details
            $m_stmt = $conn->prepare("SELECT message_text, created_at 
                                      FROM user_messages 
                                      WHERE (sender_email = ? AND receiver_email = ?) 
                                      OR (sender_email = ? AND receiver_email = ?) 
                                      ORDER BY created_at DESC LIMIT 1");
            $m_stmt->bind_param("ssss", $user_email, $email, $email, $user_email);
            $m_stmt->execute();
            $last_msg = $m_stmt->get_result()->fetch_assoc();

            // Get unread count
            $u_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_messages WHERE sender_email = ? AND receiver_email = ? AND is_read = 0");
            $u_stmt->bind_param("ss", $email, $user_email);
            $u_stmt->execute();
            $unread_data = $u_stmt->get_result()->fetch_assoc();
            $unread_count = intval($unread_data['unread_count'] ?? 0);

            $conversations[] = [
                "email" => $email,
                "full_name" => $name,
                "profile_pic" => $pic,
                "last_message" => $last_msg['message_text'] ?? '',
                "created_at" => $last_msg['created_at'] ?? '',
                "unread_count" => $unread_count
            ];
        }

        // Sort conversations by last message time DESC
        usort($conversations, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        echo json_encode($conversations);
        exit();
    }

    // ACTION: GET CHAT LOGS
    elseif ($action === 'get_chat') {
        $with = $_GET['with'] ?? '';
        if (empty($with)) {
            echo json_encode(["status" => "error", "message" => "Missing recipient"]);
            exit();
        }

        // Mark incoming unread messages from this sender as read!
        $up_stmt = $conn->prepare("UPDATE user_messages SET is_read = 1 WHERE sender_email = ? AND receiver_email = ? AND is_read = 0");
        $up_stmt->bind_param("ss", $with, $user_email);
        $up_stmt->execute();

        $stmt = $conn->prepare("SELECT m.*, 
                                       COALESCE(p.full_name, m.sender_email) AS sender_name 
                                FROM user_messages m
                                LEFT JOIN user_profiles p ON m.sender_email = p.user_email
                                WHERE (m.sender_email = ? AND m.receiver_email = ?) 
                                OR (m.sender_email = ? AND m.receiver_email = ?) 
                                ORDER BY m.created_at ASC");
        $stmt->bind_param("ssss", $user_email, $with, $with, $user_email);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
    }

    // ACTION: GET UNREAD COUNT
    elseif ($action === 'get_unread_count') {
        $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM user_messages WHERE receiver_email = ? AND is_read = 0");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();
        echo json_encode([
            "status" => "success",
            "unread_count" => intval($data['unread_count'] ?? 0)
        ]);
        exit();
    }

    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit();
}

// 2. POST Requests: send messages
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    $action = $data['action'] ?? '';

    if ($action === 'send_message') {
        $receiver = $data['receiver_email'] ?? '';
        $msg_text = trim($data['message'] ?? '');

        if (empty($receiver) || empty($msg_text)) {
            echo json_encode(["status" => "error", "message" => "Missing fields"]);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO user_messages (sender_email, receiver_email, message_text) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user_email, $receiver, $msg_text);

        if ($stmt->execute()) {
            echo json_encode([
                "status" => "success",
                "message" => [
                    "id" => $stmt->insert_id,
                    "sender_email" => $user_email,
                    "receiver_email" => $receiver,
                    "message_text" => htmlspecialchars($msg_text),
                    "created_at" => date("Y-m-d H:i:s")
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => $conn->error]);
        }
        exit();
    }

    echo json_encode(["status" => "error", "message" => "Invalid action"]);
    exit();
}
?>
