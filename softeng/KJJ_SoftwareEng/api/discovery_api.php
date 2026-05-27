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

// --- GET: Search Users, List Connections, or Fetch Pending Requests ---
if ($method === 'GET') {
    // CONDITIONAL PATH A: Fetch inbound requests waiting specifically for your approval
    if (isset($_GET['get_requests'])) {
        $stmt = $conn->prepare("SELECT u.email, p.full_name, p.profile_pic, p.department 
                                FROM user_connections c
                                JOIN users u ON c.requester_email = u.email
                                LEFT JOIN user_profiles p ON u.email = p.user_email
                                WHERE c.target_email = ? AND c.status = 'pending'");
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
    }
    
    // CONDITIONAL PATH B: Search Directory
    if (isset($_GET['search'])) {
        $search = "%" . $_GET['search'] . "%";
        
        $stmt = $conn->prepare("SELECT u.email, p.full_name, p.profile_pic, p.department,
                                       c.status, c.requester_email
                                FROM users u 
                                LEFT JOIN user_profiles p ON u.email = p.user_email 
                                LEFT JOIN user_connections c ON 
                                     ((c.requester_email = ? AND c.target_email = u.email) OR 
                                      (c.requester_email = u.email AND c.target_email = ?))
                                WHERE (p.full_name LIKE ? OR u.email LIKE ?) 
                                AND u.email != ? LIMIT 10");
        $stmt->bind_param("sssss", $user_email, $user_email, $search, $search, $user_email);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
        
    } else {
        // CONDITIONAL PATH C: List Established Connections (Friends)
        $stmt = $conn->prepare("SELECT p.full_name, p.profile_pic, p.user_email AS email, p.department 
                                FROM user_connections c
                                JOIN user_profiles p ON (c.target_email = p.user_email OR c.requester_email = p.user_email)
                                WHERE (c.requester_email = ? OR c.target_email = ?) 
                                AND c.status = 'accepted'
                                AND p.user_email != ?");
        $stmt->bind_param("sss", $user_email, $user_email, $user_email);
        $stmt->execute();
        echo json_encode($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
        exit();
    }
}

// --- POST: Handle Actions (Requests, Approvals, and Disconnections) ---
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['action']) || !isset($data['target_email'])) {
        echo json_encode(["status" => "error", "message" => "Missing parameter payloads."]);
        exit();
    }
    
    $action = $data['action'];
    $target = trim($data['target_email']); // In 'accept_request', $target is the original requester
    
    // ACTION: SEND CONNECTION REQUEST
    if ($action === 'send_request') {
        $stmt = $conn->prepare("INSERT INTO user_connections (requester_email, target_email, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ss", $user_email, $target);
        
        if ($stmt->execute()) {
            $name_query = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
            $name_query->bind_param("s", $user_email);
            $name_query->execute();
            $name_res = $name_query->get_result()->fetch_assoc();
            $requester_name = !empty($name_res['full_name']) ? $name_res['full_name'] : $user_email;
            
            $msg = htmlspecialchars($requester_name) . " sent you a connection request.";
            $notif = $conn->prepare("INSERT INTO user_notifications (receiver_email, sender_email, message, type, is_read) VALUES (?, ?, ?, 'connection_request', 0)");
            $notif->bind_param("sss", $target, $user_email, $msg);
            $notif->execute();
            
            echo json_encode(["status" => "success", "message" => "Request sent successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Request already pending or error occurred."]);
        }
        exit();
    }
    
    // ACTION: ACCEPT CONNECTION REQUEST (WITH CONFIRMATION NOTIFICATION HOOK)
    if ($action === 'accept_request') {
        $stmt = $conn->prepare("UPDATE user_connections SET status = 'accepted' WHERE requester_email = ? AND target_email = ?");
        $stmt->bind_param("ss", $target, $user_email);
        
        if ($stmt->execute()) {
            // 1. Fetch the display name of the user who is accepting the request (current session)
            $name_query = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
            $name_query->bind_param("s", $user_email);
            $name_query->execute();
            $name_res = $name_query->get_result()->fetch_assoc();
            $accepter_name = !empty($name_res['full_name']) ? $name_res['full_name'] : $user_email;
            
            // 2. Build confirmation text string message payload
            $msg = htmlspecialchars($accepter_name) . " accepted your connection request. You are now connected!";
            
            // 3. Push structured record to user_notifications targeting the original requester ($target)
            $notif = $conn->prepare("INSERT INTO user_notifications (receiver_email, sender_email, message, type, is_read) VALUES (?, ?, ?, 'connection_accepted', 0)");
            $notif->bind_param("sss", $target, $user_email, $msg);
            $notif->execute();
            
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Failed to accept request."]);
        }
        exit();
    }
    
    // ACTION: DECLINE OR DISCONNECT RELATIONSHIP
    if ($action === 'decline_request' || $action === 'disconnect') {
        $stmt = $conn->prepare("DELETE FROM user_connections WHERE 
                                (requester_email = ? AND target_email = ?) OR 
                                (requester_email = ? AND target_email = ?)");
        $stmt->bind_param("ssss", $user_email, $target, $target, $user_email);
        
        if ($stmt->execute()) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Operation failed."]);
        }
        exit();
    }
}
?>