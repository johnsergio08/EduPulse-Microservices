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

// 1. GET: Fetch posts (Either Section-specific or Global) with Likes and Comments
if ($method === 'GET') {
    if (isset($_GET['scope']) && $_GET['scope'] === 'Global') {
        // Fetch posts where section_id is NULL (Campus-wide) with like counts and user like status
        $stmt = $conn->prepare("SELECT p.*, 
                                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
                                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_email = ?) AS has_liked
                                FROM class_posts p 
                                WHERE p.section_id IS NULL 
                                ORDER BY p.created_at DESC");
        $stmt->bind_param("s", $user_email);
    } elseif (isset($_GET['section_id'])) {
        // Fetch posts for a specific classroom
        $section_id = (int)$_GET['section_id'];
        $stmt = $conn->prepare("SELECT p.*, 
                                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id) AS likes_count,
                                       (SELECT COUNT(*) FROM post_likes WHERE post_id = p.id AND user_email = ?) AS has_liked
                                FROM class_posts p 
                                WHERE p.section_id = ? 
                                ORDER BY p.created_at DESC");
        $stmt->bind_param("si", $user_email, $section_id);
    } else {
        echo json_encode(["status" => "error", "message" => "Missing parameters"]);
        exit();
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $posts = $result->fetch_all(MYSQLI_ASSOC);

    // Fetch nested comments for each post
    foreach ($posts as &$post) {
        $c_stmt = $conn->prepare("SELECT id, author_name, author_email, comment_content, created_at 
                                  FROM post_comments 
                                  WHERE post_id = ? 
                                  ORDER BY created_at ASC");
        $c_stmt->bind_param("i", $post['id']);
        $c_stmt->execute();
        $post['comments'] = $c_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    echo json_encode($posts);
    exit();
}

// 2. POST: Create post, toggle like, or add comment
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Fallback for form-encoded posts (e.g. legacy/testing)
    if (empty($data)) {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? 'create_post';

    // ACTION: TOGGLE LIKE
    if ($action === 'toggle_like') {
        $post_id = (int)$data['post_id'];
        
        // Check if user already liked this post
        $check = $conn->prepare("SELECT 1 FROM post_likes WHERE post_id = ? AND user_email = ?");
        $check->bind_param("is", $post_id, $user_email);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows > 0) {
            // Unlike
            $del = $conn->prepare("DELETE FROM post_likes WHERE post_id = ? AND user_email = ?");
            $del->bind_param("is", $post_id, $user_email);
            $del->execute();
            $has_liked = 0;
        } else {
            // Like
            $ins = $conn->prepare("INSERT INTO post_likes (post_id, user_email) VALUES (?, ?)");
            $ins->bind_param("is", $post_id, $user_email);
            if ($ins->execute()) {
                $has_liked = 1;
                
                // Get post author
                $p_author = $conn->prepare("SELECT author_email FROM class_posts WHERE id = ?");
                $p_author->bind_param("i", $post_id);
                $p_author->execute();
                $p_author_res = $p_author->get_result()->fetch_assoc();
                $receiver_email = $p_author_res['author_email'] ?? '';
                
                // Insert a notification if the liker is not the post author
                if (!empty($receiver_email) && $receiver_email !== $user_email) {
                    $p_name = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
                    $p_name->bind_param("s", $user_email);
                    $p_name->execute();
                    $p_name_res = $p_name->get_result()->fetch_assoc();
                    $liker_name = !empty($p_name_res['full_name']) ? $p_name_res['full_name'] : $_SESSION['role'];
                    
                    $notif_msg = htmlspecialchars($liker_name) . " liked your post.";
                    $ins_notif = $conn->prepare("INSERT INTO user_notifications (receiver_email, sender_email, type, post_id, message) VALUES (?, ?, 'like', ?, ?)");
                    $ins_notif->bind_param("ssis", $receiver_email, $user_email, $post_id, $notif_msg);
                    $ins_notif->execute();
                }
            } else {
                $has_liked = 0;
            }
        }
        
        // Fetch the updated like count
        $cnt = $conn->prepare("SELECT COUNT(*) as likes_count FROM post_likes WHERE post_id = ?");
        $cnt->bind_param("i", $post_id);
        $cnt->execute();
        $cnt_res = $cnt->get_result()->fetch_assoc();
        
        echo json_encode([
            "status" => "success",
            "likes_count" => (int)$cnt_res['likes_count'],
            "has_liked" => $has_liked
        ]);
        exit();
    }
    
    // ACTION: ADD COMMENT
    elseif ($action === 'add_comment') {
        $post_id = (int)$data['post_id'];
        $comment_content = trim($data['content']);
        
        if (empty($comment_content)) {
            echo json_encode(["status" => "error", "message" => "Comment content is empty"]);
            exit();
        }
        
        // Resolve full name from profiles
        $p_stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
        $p_stmt->bind_param("s", $user_email);
        $p_stmt->execute();
        $p_res = $p_stmt->get_result();
        $author_display = $_SESSION['role'];
        if ($p_row = $p_res->fetch_assoc()) {
            if (!empty($p_row['full_name'])) {
                $author_display = $p_row['full_name'];
            }
        }
        
        $ins = $conn->prepare("INSERT INTO post_comments (post_id, author_name, author_email, comment_content) VALUES (?, ?, ?, ?)");
        $ins->bind_param("isss", $post_id, $author_display, $user_email, $comment_content);
        
        if ($ins->execute()) {
            $new_id = $ins->insert_id;
            
            // Get post author
            $p_author = $conn->prepare("SELECT author_email FROM class_posts WHERE id = ?");
            $p_author->bind_param("i", $post_id);
            $p_author->execute();
            $p_author_res = $p_author->get_result()->fetch_assoc();
            $receiver_email = $p_author_res['author_email'] ?? '';
            
            // Insert a notification if the commenter is not the post author
            if (!empty($receiver_email) && $receiver_email !== $user_email) {
                $snippet = mb_strimwidth($comment_content, 0, 45, '...');
                $notif_msg = htmlspecialchars($author_display) . " replied: \"" . htmlspecialchars($snippet) . "\"";
                
                $ins_notif = $conn->prepare("INSERT INTO user_notifications (receiver_email, sender_email, type, post_id, message) VALUES (?, ?, 'comment', ?, ?)");
                $ins_notif->bind_param("ssis", $receiver_email, $user_email, $post_id, $notif_msg);
                $ins_notif->execute();
            }
            
            echo json_encode([
                "status" => "success",
                "comment" => [
                    "id" => $new_id,
                    "post_id" => $post_id,
                    "author_name" => $author_display,
                    "author_email" => $user_email,
                    "comment_content" => htmlspecialchars($comment_content),
                    "created_at" => date("Y-m-d H:i:s")
                ]
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
        }
        exit();
    }
    
    // ACTION: CREATE POST (Default)
    else {
        if (!empty($data['content'])) {
            $section_id = (!empty($data['section_id'])) ? (int)$data['section_id'] : null;
            
            // Resolve full name from profiles
            $p_stmt = $conn->prepare("SELECT full_name FROM user_profiles WHERE user_email = ?");
            $p_stmt->bind_param("s", $user_email);
            $p_stmt->execute();
            $p_res = $p_stmt->get_result();
            $author_display = $_SESSION['role'];
            if ($p_row = $p_res->fetch_assoc()) {
                if (!empty($p_row['full_name'])) {
                    $author_display = $p_row['full_name'];
                }
            }
            
            $stmt = $conn->prepare("INSERT INTO class_posts (section_id, author_name, author_email, post_content) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("isss", $section_id, $author_display, $user_email, $data['content']);
            
            if ($stmt->execute()) {
                $new_post_id = $stmt->insert_id;
                
                // If it is a global post, and is created by Admin or Faculty, send announcement notifications to everyone
                if (empty($section_id) && in_array($_SESSION['role'], ['System Admin', 'Faculty', 'Teacher', 'adminoffice'])) {
                    // Fetch all other users
                    $all_u = $conn->prepare("SELECT email FROM users WHERE email != ?");
                    $all_u->bind_param("s", $user_email);
                    $all_u->execute();
                    $all_u_res = $all_u->get_result();
                    
                    $post_snippet = mb_strimwidth($data['content'], 0, 45, '...');
                    $notif_msg = htmlspecialchars($author_display) . " shared a new post: \"" . htmlspecialchars($post_snippet) . "\"";
                    
                    while ($row_u = $all_u_res->fetch_assoc()) {
                        $ins_notif = $conn->prepare("INSERT INTO user_notifications (receiver_email, sender_email, type, post_id, message) VALUES (?, ?, 'announcement', ?, ?)");
                        $ins_notif->bind_param("ssis", $row_u['email'], $user_email, $new_post_id, $notif_msg);
                        $ins_notif->execute();
                    }
                }
                
                echo json_encode(["status" => "success"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Content is empty"]);
        }
        exit();
    }
}
?>