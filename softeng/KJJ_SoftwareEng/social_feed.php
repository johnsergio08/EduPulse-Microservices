<?php
session_start();
require_once 'config/db.php';
if (!isset($_SESSION['admin_logged_in'])) { header("Location: index.php"); exit(); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campus Feed | EduPulse</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #10b981; 
            --bg: #f8fafc; 
            --border: #e2e8f0; 
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary-dark: #059669;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; background: var(--bg); min-height: 100vh; color: var(--text-main); overflow-x: hidden; }
        .main-content { flex: 1; padding: 40px; }
        
        .feed-card { background: white; border-radius: 16px; border: 1px solid var(--border); max-width: 850px; margin: 0 auto; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); display: flex; flex-direction: column; }
        
        /* Top Navigation Header Styling */
        .feed-top-bar { display: flex; justify-content: space-between; align-items: center; padding: 18px 24px; border-bottom: 1px solid var(--border); background: white; }
        .drawer-trigger-btn { background: #f1f5f9; border: none; width: 42px; height: 42px; border-radius: 50%; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; position: relative; font-size: 1.15rem; transition: all 0.2s ease; outline: none; }
        .drawer-trigger-btn:hover { background: #e2e8f0; color: var(--primary); transform: scale(1.05); }
        
        /* Badges */
        .badge-notif { background: #ef4444; color: white; font-size: 0.65rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; min-width: 18px; text-align: center; border: 2px solid white; }

        /* Sliding Drawers CSS System */
        .drawer-backdrop { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(4px); z-index: 10000; opacity: 0; pointer-events: none; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .drawer-backdrop.active { opacity: 1; pointer-events: auto; }
        
        .drawer-panel { position: fixed; top: 0; right: 0; width: 430px; height: 100vh; background: white; z-index: 10001; box-shadow: -10px 0 30px rgba(15, 23, 42, 0.15); transform: translateX(100%); transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .drawer-panel.active { transform: translateX(0); }

        /* Community Feed Styling */
        .post-box { padding: 24px; border-bottom: 1px solid var(--border); background: #fff; }
        .input-style { width: 100%; padding: 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 0.95rem; resize: none; outline: none; transition: border-color 0.2s; }
        .input-style:focus { border-color: var(--primary); }
        .btn-post { background: var(--primary); color: white; border: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-top: 12px; transition: background 0.2s; }
        .btn-post:hover { background: #059669; }

        /* Engagement styles */
        .feed-actions { display: flex; gap: 24px; margin-top: 16px; border-top: 1px solid #f1f5f9; padding-top: 14px; }
        .action-btn { background: none; border: none; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; outline: none; }
        .action-btn:hover { color: var(--primary); }
        .action-btn.liked { color: #ef4444; }
        .action-btn.liked i { animation: heartBeat 0.3s ease-in-out; }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.35); }
            100% { transform: scale(1); }
        }

        /* Comments styling */
        .comments-section { background: #f8fafc; border-top: 1px solid #f1f5f9; padding: 18px 24px; }
        .comments-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 14px; max-height: 250px; overflow-y: auto; }
        .comment-item { display: flex; flex-direction: column; background: white; padding: 12px 16px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .comment-header { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 0.75rem; }
        .comment-author { font-weight: 700; color: #1e293b; display: flex; align-items: center; gap: 4px; }
        .comment-time { color: var(--text-muted); }
        .comment-body { color: #334155; line-height: 1.4; }

        /* Comment Input styling */
        .comment-input-box { display: flex; gap: 10px; margin-top: 8px; }
        .comment-input { flex: 1; padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; outline: none; transition: border 0.2s; }
        .comment-input:focus { border-color: var(--primary); }
        .btn-comment-submit { background: var(--primary); color: white; border: none; padding: 8px 18px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-comment-submit:hover { background: #059669; }

        /* Notifications Hub Styling */
        .notif-feed-container { padding: 0; display: flex; flex-direction: column; gap: 12px; }
        .notif-card { display: flex; align-items: flex-start; gap: 14px; padding: 16px; background: #ffffff; border: 1px solid var(--border); border-radius: 10px; transition: transform 0.2s, box-shadow 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .notif-card:hover { transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.04); }
        .notif-card.unread { border-left: 4px solid var(--primary); background: #f0fdf4; }
        .notif-icon-box { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .notif-icon-box.like { background: #fee2e2; color: #ef4444; }
        .notif-icon-box.comment { background: #d1fae5; color: #10b981; }
        .notif-icon-box.announcement { background: #e0f2fe; color: #0284c7; }
        .notif-content-area { flex: 1; min-width: 0; }
        .notif-message { font-size: 0.82rem; line-height: 1.4; margin-bottom: 4px; color: var(--text-main); word-wrap: break-word; }
        .notif-time { font-size: 0.7rem; color: var(--text-muted); }

        /* Split-Pane Messaging Client Styling */
        .messenger-search-container { padding: 16px; border-bottom: 1px solid var(--border); position: relative; }
        .search-results-dropdown { position: absolute; top: 100%; left: 16px; right: 16px; background: white; border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); z-index: 10; max-height: 200px; overflow-y: auto; }
        .search-result-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f1f5f9; }
        .search-result-item:hover { background: #f8fafc; }
        
        .chats-list-box { flex: 1; overflow-y: auto; display: flex; flex-direction: column; padding: 10px 0; }
        .conversation-item { display: flex; align-items: center; gap: 12px; padding: 14px 20px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f8fafc; border-left: 3px solid transparent; }
        .conversation-item:hover { background: #f8fafc; }
        .conversation-item.active { background: #f0fdf4; border-left-color: var(--primary); }
        .conversation-item.unread-msg-item { background: #f0fdf4; border-left-color: var(--primary); }
        .conv-name { font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 3px; }
        .conv-last-msg { font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 220px; }

        .chat-header { padding: 16px 20px; background: white; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
        .chat-messages-stream { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 12px; background: #f8fafc; }
        
        /* Chat bubbles */
        .bubble-wrapper { display: flex; flex-direction: column; width: 100%; }
        .chat-bubble { max-width: 80%; padding: 10px 14px; font-size: 0.84rem; line-height: 1.4; position: relative; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .chat-bubble.outgoing {
            align-self: flex-end;
            background: var(--primary);
            color: white;
            border-radius: 14px 14px 0 14px;
        }
        .chat-bubble.incoming {
            align-self: flex-start;
            background: white;
            color: var(--text-main);
            border: 1px solid #e2e8f0;
            border-radius: 14px 14px 14px 0;
        }
        .bubble-meta { font-size: 0.6rem; margin-top: 4px; color: #94a3b8; }
        .outgoing .bubble-meta { color: #d1fae5; text-align: right; }

        .chat-composer { padding: 14px 18px; background: white; border-top: 1px solid var(--border); display: flex; gap: 10px; align-items: center; }
        
        /* Avatar Placeholder Utility */
        .avatar-box { width: 36px; height: 36px; border-radius: 50%; background: #10b981; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.95rem; box-shadow: 0 2px 4px rgba(16,185,129,0.15); flex-shrink: 0; }
        .avatar-box.large { width: 38px; height: 38px; font-size: 1rem; }
        .avatar-box.gray { background: #64748b; box-shadow: 0 2px 4px rgba(100,116,139,0.15); }

        /* Interactive Profile Links Styling */
        .profile-link { color: var(--text-main); font-weight: 700; cursor: pointer; text-decoration: none; transition: color 0.15s ease; }
        .profile-link:hover { color: var(--primary); text-decoration: underline; }
        .comment-author .profile-link { color: #1e293b; }

        /* UPDATED: Subtle Connection Logo Indicator Badge */
        .connection-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #e6f4ea;
            color: #137333;
            font-size: 0.72rem;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            margin-left: 6px;
            vertical-align: middle;
            border: 1px solid #a3cfbb;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="feed-card">
            <div class="feed-top-bar">
                <h3 style="font-weight: 800; font-size: 1.25rem; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-square-rss" style="color: var(--primary);"></i> Campus Feed
                </h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <button class="drawer-trigger-btn" onclick="openDrawer('notifications')" title="Notifications" style="position: relative;">
                        <i class="fa-solid fa-bell"></i>
                        <span class="badge-notif" id="notif-badge" style="display:none; position: absolute; top: -4px; right: -4px;">0</span>
                    </button>
                    <button class="drawer-trigger-btn" onclick="openDrawer('messages')" title="Private Messages" style="position: relative;">
                        <i class="fa-solid fa-comments"></i>
                        <span class="badge-notif" id="messages-badge" style="display:none; position: absolute; top: -4px; right: -4px;">0</span>
                    </button>
                </div>
            </div>

            <div id="viewport-feed" class="viewport active">
                <div class="post-box">
                    <h3 style="margin-bottom:15px; font-weight:800; color:#1e293b; font-size:1.1rem; display:flex; align-items:center; gap:8px;">
                        <i class="fa-solid fa-users" style="color:var(--primary);"></i> Campus Community
                    </h3>
                    <textarea id="globalPostInput" class="input-style" placeholder="Share an update with everyone on campus..." style="height: 100px;"></textarea>
                    <div style="text-align: right;">
                        <button class="btn-post" onclick="submitGlobalPost()">Post Update</button>
                    </div>
                </div>
                <div id="global-feed-container" style="padding: 24px; background:#f1f5f9;"></div>
            </div>
        </div>
    </div>

    <div class="drawer-backdrop" id="drawer-backdrop" onclick="closeDrawer()"></div>

    <div class="drawer-panel" id="drawer-notifications">
        <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: white;">
            <h3 style="font-weight: 800; font-size: 1.15rem; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-bell" style="color: var(--primary);"></i> Notifications
            </h3>
            <div style="display: flex; align-items: center; gap: 6px;">
                <button class="btn-comment-submit" style="padding: 6px 12px; font-size: 0.72rem; background: #3b82f6;" onclick="markNotificationsAsRead()">Read all</button>
                <button class="btn-comment-submit" style="padding: 6px 12px; font-size: 0.72rem; background: #ef4444;" onclick="clearAllNotifications()">Clear all</button>
                <button onclick="closeDrawer()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; outline: none; padding: 4px; margin-left: 4px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>
        <div id="notifications-list-container" class="notif-feed-container" style="flex: 1; overflow-y: auto; padding: 20px; background: #f8fafc;">
            </div>
    </div>

    <div class="drawer-panel" id="drawer-messages">
        <div id="messages-list-view" style="display: flex; flex-direction: column; height: 100%;">
            <div style="padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: white;">
                <h3 style="font-weight: 800; font-size: 1.15rem; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-comments" style="color: var(--primary);"></i> Private Messages
                </h3>
                <button onclick="closeDrawer()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; outline: none; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="messenger-search-container">
                <input type="text" class="comment-input" style="width: 100%;" id="user-search-field" placeholder="?? Search system users..." oninput="searchMessengerUsers(this.value)">
                <div class="search-results-dropdown" id="search-results-box" style="display:none; left: 16px; right: 16px; width: auto;"></div>
            </div>
            <div class="chats-list-box" id="chats-list-box">
                </div>
        </div>

        <div id="messages-chat-view" style="display: none; flex-direction: column; height: 100%; background: #f8fafc;">
            <div class="chat-header">
                <button onclick="showConversationsList()" style="background: none; border: none; font-size: 0.9rem; color: var(--primary); cursor: pointer; padding: 4px 8px; display: flex; align-items: center; gap: 4px; font-weight: 600; outline: none;" title="Back to Inbox">
                    <i class="fa-solid fa-chevron-left"></i> Back
                </button>
                <div class="avatar-box large" id="chat-header-avatar">T</div>
                <div style="display:flex; flex-direction:column; flex: 1; min-width: 0;">
                    <span style="font-weight:700; font-size:0.9rem; color:#1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="chat-header-name">Loading...</span>
                    <span style="font-size:0.65rem; color:#94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" id="chat-header-email">Loading...</span>
                </div>
                <button onclick="closeDrawer()" style="background: none; border: none; font-size: 1.25rem; color: var(--text-muted); cursor: pointer; outline: none; padding: 4px;"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="chat-messages-stream" id="chat-messages-stream">
                </div>
            <div class="chat-composer">
                <input type="text" class="comment-input" id="chat-composer-field" placeholder="Write your message here..." onkeydown="if(event.key==='Enter') sendChatMessage()">
                <button class="btn-comment-submit" style="padding:10px 18px;" onclick="sendChatMessage()">Send</button>
            </div>
        </div>
    </div>

    <script>
        let currentActiveDrawer = null;
        let activeChatRecipient = null;
        let chatInterval = null;
        let unreadBadgeInterval = null;

        // Drawer Sliding Actions
        function openDrawer(drawerName) {
            closeDrawer();
            currentActiveDrawer = drawerName;
            document.getElementById('drawer-backdrop').classList.add('active');
            document.getElementById(`drawer-${drawerName}`).classList.add('active');

            if (drawerName === 'notifications') {
                loadNotifications();
            } else if (drawerName === 'messages') {
                document.getElementById('messages-badge').style.display = 'none';
                showConversationsList();
            }
        }

        function closeDrawer() {
            document.querySelectorAll('.drawer-panel').forEach(panel => panel.classList.remove('active'));
            document.getElementById('drawer-backdrop').classList.remove('active');
            clearInterval(chatInterval);
            currentActiveDrawer = null;
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeDrawer();
            }
        });

        function viewUserProfile(email) {
            window.location.href = `view_profile.php?email=${encodeURIComponent(email)}`;
        }

        // -------------------------
        // COMMUNITY FEED ENGINE
        // -------------------------
        async function loadGlobalFeed() {
            const res = await fetch('api/social_api.php?scope=Global'); 
            const posts = await res.json();
            
            // Fetch accepted connections matrix
            const friendsRes = await fetch('api/discovery_api.php');
            const friends = await friendsRes.json();
            const connectedEmails = friends.map(f => f.email);

            document.getElementById('global-feed-container').innerHTML = posts.map(p => {
                const likedClass = p.has_liked == 1 ? 'liked' : '';
                const heartIcon = p.has_liked == 1 ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
                const commentCount = p.comments ? p.comments.length : 0;
                
                // UPDATED: Simple human profile icon badge silhouette (No "1st" label text)
                const postConnectedBadge = connectedEmails.includes(p.author_email) 
                    ? `<span class="connection-badge" title="Connected friend"><i class="fa-solid fa-user"></i></span>` 
                    : '';

                const commentsHTML = p.comments && p.comments.length > 0
                    ? p.comments.map(c => {
                        const commentConnectedBadge = connectedEmails.includes(c.author_email)
                            ? `<span class="connection-badge" title="Connected friend"><i class="fa-solid fa-user"></i></span>`
                            : '';
                            
                        return `
                            <div class="comment-item">
                                <div class="comment-header">
                                    <span class="comment-author">
                                        <a onclick="viewUserProfile('${c.author_email}')" class="profile-link">${c.author_name}</a>
                                        ${commentConnectedBadge}
                                    </span>
                                    <span class="comment-time">${c.created_at}</span>
                                </div>
                                <div class="comment-body">${c.comment_content}</div>
                            </div>
                        `;
                    }).join('')
                    : `<p class="no-comments-placeholder" style="font-size:0.8rem; color:#94a3b8; padding:4px 0;">No replies yet. Start the conversation!</p>`;
                
                return `
                <div class="feed-item" style="padding:0; margin-bottom: 24px; border: 1px solid var(--border); border-radius:12px; overflow:hidden; background:white; box-shadow:0 1px 3px rgba(0,0,0,0.02);">
                    <div style="padding:20px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:12px; align-items:center;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <div class="avatar-box">
                                    ${p.author_name.charAt(0).toUpperCase()}
                                </div>
                                <div style="display:flex; flex-direction:column;">
                                    <strong style="font-size:0.85rem; display: flex; align-items: center;">
                                        <a onclick="viewUserProfile('${p.author_email}')" class="profile-link">${p.author_name}</a>
                                        ${postConnectedBadge}
                                    </strong>
                                    <span style="font-size:0.65rem; color:#94a3b8;">${p.author_email}</span>
                                </div>
                            </div>
                            <span style="font-size:0.7rem; color:#94a3b8;">${p.created_at}</span>
                        </div>
                        <p style="font-size:0.95rem; line-height:1.6; color:#334155; white-space:pre-wrap;">${p.post_content}</p>
                        
                        <div class="feed-actions">
                            <button class="action-btn ${likedClass}" onclick="toggleLike(${p.id}, this)">
                                <i class="${heartIcon}"></i>
                                <span class="like-count">${p.likes_count}</span> Likes
                            </button>
                            <button class="action-btn" onclick="focusCommentField(${p.id})">
                                <i class="fa-regular fa-comment"></i>
                                <span>${commentCount}</span> Comments
                            </button>
                        </div>
                    </div>
                    
                    <div class="comments-section" id="comments-sec-${p.id}">
                        <div class="comments-list" id="comments-list-${p.id}">
                            ${commentsHTML}
                        </div>
                        <div class="comment-input-box">
                            <input type="text" class="comment-input" id="comment-input-${p.id}" placeholder="Write a reply..." onkeydown="if(event.key==='Enter') submitComment(${p.id})">
                            <button class="btn-comment-submit" onclick="submitComment(${p.id})">Reply</button>
                        </div>
                    </div>
                </div>
                `;
            }).join('') || '<p style="padding:40px; text-align:center; color:#64748b; background:white; border-radius:12px;">No campus updates shared yet.</p>';
        }

        async function submitGlobalPost() {
            const input = document.getElementById('globalPostInput');
            if (!input.value.trim()) return;
            await fetch('api/social_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ content: input.value, section_id: null })
            });
            input.value = '';
            loadGlobalFeed();
            checkUnreadNotificationsCount();
        }

        async function toggleLike(postId, btnElement) {
            const res = await fetch('api/social_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'toggle_like', post_id: postId })
            });
            const data = await res.json();
            if (data.status === 'success') {
                const heart = btnElement.querySelector('i');
                const countSpan = btnElement.querySelector('.like-count');
                
                countSpan.textContent = data.likes_count;
                if (data.has_liked === 1) {
                    btnElement.classList.add('liked');
                    heart.className = 'fa-solid fa-heart';
                } else {
                    btnElement.classList.remove('liked');
                    heart.className = 'fa-regular fa-heart';
                }
            }
        }

        function focusCommentField(postId) {
            document.getElementById(`comment-input-${postId}`).focus();
        }

        async function submitComment(postId) {
            const input = document.getElementById(`comment-input-${postId}`);
            const content = input.value.trim();
            if (!content) return;
            
            const res = await fetch('api/social_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'add_comment', post_id: postId, content: content })
            });
            const data = await res.json();
            
            if (data.status === 'success') {
                input.value = '';
                loadGlobalFeed();
            }
        }

        async function loadNotifications() {
            const res = await fetch('api/notifications_api.php');
            const data = await res.json();
            
            if (data.status === 'success') {
                const list = document.getElementById('notifications-list-container');
                list.innerHTML = data.notifications.map(n => {
                    const unreadClass = n.is_read == 0 ? 'unread' : '';
                    let iconClass = 'fa-solid fa-circle-exclamation';
                    let iconType = 'announcement';
                    
                    if (n.type === 'like') {
                        iconClass = 'fa-solid fa-heart';
                        iconType = 'like';
                    } else if (n.type === 'comment') {
                        iconClass = 'fa-solid fa-comment';
                        iconType = 'comment';
                    } else if (n.type === 'announcement') {
                        iconClass = 'fa-solid fa-bullhorn';
                        iconType = 'announcement';
                    }

                    const clickAction = n.post_id ? `onclick="viewPost(${n.post_id}, ${n.id})"` : '';
                    const pointerStyle = n.post_id ? 'style="cursor: pointer;"' : '';

                    return `
                    <div class="notif-card ${unreadClass}" ${clickAction} ${pointerStyle}>
                        <div class="notif-icon-box ${iconType}">
                            <i class="${iconClass}"></i>
                        </div>
                        <div class="notif-content-area">
                            <div class="notif-message">${n.message}</div>
                            <div class="notif-time">${n.created_at}</div>
                        </div>
                    </div>
                    `;
                }).join('') || '<p style="padding:40px; text-align:center; color:#64748b; background:white; border-radius:12px;">You have no notifications yet.</p>';
                
                document.getElementById('notif-badge').style.display = 'none';
            }
        }

        async function viewPost(postId, notifId) {
            await fetch('api/notifications_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'mark_read', id: notifId })
            });
            
            closeDrawer();
            
            setTimeout(() => {
                const postElement = document.getElementById(`comments-sec-${postId}`);
                if (postElement) {
                    const postCard = postElement.closest('.feed-item');
                    if (postCard) {
                        postCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        
                        postCard.style.transition = 'all 0.4s ease';
                        postCard.style.outline = '3px solid var(--primary)';
                        postCard.style.boxShadow = '0 0 20px rgba(16,185,129,0.4)';
                        
                        setTimeout(() => {
                            postCard.style.outline = 'none';
                            postCard.style.boxShadow = '0 1px 3px rgba(0,0,0,0.02)';
                        }, 3000);
                    }
                }
            }, 300);
        }

        async function markNotificationsAsRead() {
            const res = await fetch('api/notifications_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'mark_read' })
            });
            const data = await res.json();
            if (data.status === 'success') {
                loadNotifications();
                checkUnreadNotificationsCount();
            }
        }

        async function clearAllNotifications() {
            if (!confirm("Are you sure you want to permanently clear all notifications?")) return;
            try {
                const res = await fetch('api/notifications_api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'clear_all' })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    loadNotifications();
                    checkUnreadNotificationsCount();
                }
            } catch (err) {
                console.error("Failed to clear notifications:", err);
            }
        }

        async function checkUnreadNotificationsCount() {
            const res = await fetch('api/notifications_api.php');
            const data = await res.json();
            if (data.status === 'success' && data.unread_count > 0) {
                if (currentActiveDrawer === 'notifications') {
                    document.getElementById('notif-badge').style.display = 'none';
                } else {
                    const badge = document.getElementById('notif-badge');
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline-block';
                }
            } else {
                document.getElementById('notif-badge').style.display = 'none';
            }
        }

        async function checkUnreadMessagesCount() {
            const res = await fetch('api/messages_api.php?action=get_unread_count');
            const data = await res.json();
            if (data.status === 'success' && data.unread_count > 0) {
                if (currentActiveDrawer === 'messages') {
                    document.getElementById('messages-badge').style.display = 'none';
                } else {
                    const badge = document.getElementById('messages-badge');
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline-block';
                }
            } else {
                document.getElementById('messages-badge').style.display = 'none';
            }
        }

        async function searchMessengerUsers(query) {
            const box = document.getElementById('search-results-box');
            if (!query.trim()) {
                box.style.display = 'none';
                return;
            }

            const res = await fetch(`api/messages_api.php?action=search_users&query=${encodeURIComponent(query)}`);
            const users = await res.json();

            if (users.length > 0) {
                box.innerHTML = users.map(u => `
                    <div class="search-result-item" onclick="startChat('${u.email}', '${u.full_name}')">
                        <div class="avatar-box" style="width:28px; height:28px; font-size:0.75rem;">
                            ${u.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div style="display:flex; flex-direction:column;">
                            <span style="font-size:0.8rem; font-weight:700; color:#1e293b;">${u.full_name}</span>
                            <span style="font-size:0.65rem; color:#94a3b8;">${u.email}</span>
                        </div>
                    </div>
                `).join('');
                box.style.display = 'block';
            } else {
                box.innerHTML = '<p style="padding:10px; font-size:0.75rem; text-align:center; color:#94a3b8;">No matches found</p>';
                box.style.display = 'block';
            }
        }

        function startChat(email, name) {
            document.getElementById('search-results-box').style.display = 'none';
            document.getElementById('user-search-field').value = '';
            activeChatRecipient = { email: email, name: name };
            showChatRoom();
        }

        function showConversationsList() {
            document.getElementById('messages-list-view').style.display = 'flex';
            document.getElementById('messages-chat-view').style.display = 'none';
            clearInterval(chatInterval);
            activeChatRecipient = null;
            loadConversations();
        }

        function showChatRoom() {
            if (!activeChatRecipient) return;

            document.getElementById('chat-header-avatar').textContent = activeChatRecipient.name.charAt(0).toUpperCase();
            document.getElementById('chat-header-name').textContent = activeChatRecipient.name;
            document.getElementById('chat-header-email').textContent = activeChatRecipient.email;

            document.getElementById('messages-list-view').style.display = 'none';
            document.getElementById('messages-chat-view').style.display = 'flex';

            loadChatMessages();

            clearInterval(chatInterval);
            chatInterval = setInterval(loadChatMessages, 3000);
        }

        async function loadConversations() {
            const res = await fetch('api/messages_api.php?action=get_conversations');
            const convs = await res.json();
            
            const list = document.getElementById('chats-list-box');
            list.innerHTML = convs.map(c => {
                const activeClass = activeChatRecipient && activeChatRecipient.email === c.email ? 'active' : '';
                const unreadClass = c.unread_count > 0 ? 'unread-msg-item' : '';
                const unreadBadge = c.unread_count > 0 ? `<span class="badge-notif" style="position: static; margin-left: auto; padding: 2px 6px; font-size: 0.65rem;">${c.unread_count}</span>` : '';
                const lastMsgStyle = c.unread_count > 0 ? 'font-weight: 700; color: var(--text-main);' : '';
                const nameStyle = c.unread_count > 0 ? 'font-weight: 800;' : '';

                return `
                <div class="conversation-item ${activeClass} ${unreadClass}" onclick="startChat('${c.email}', '${c.full_name}')" style="display: flex; align-items: center; justify-content: space-between; gap: 12px; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                        <div class="avatar-box gray" style="width:34px; height:34px; font-size:0.85rem;">
                            ${c.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div style="display:flex; flex-direction:column; flex:1; min-width:0;">
                            <div style="display:flex; justify-content:space-between; align-items:center; width:100%; gap: 6px;">
                                <span class="conv-name" style="${nameStyle} margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 140px;">${c.full_name}</span>
                                <span style="font-size:0.6rem; color:#94a3b8; flex-shrink: 0;">${c.created_at ? c.created_at.slice(11, 16) : ''}</span>
                            </div>
                            <span class="conv-last-msg" style="${lastMsgStyle} white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">${c.last_message || 'Start messaging...'}</span>
                        </div>
                    </div>
                    ${unreadBadge}
                </div>
                `;
            }).join('') || '<p style="padding:40px 20px; font-size:0.8rem; text-align:center; color:#94a3b8;">No chats yet. Search a user to compose a message!</p>';
        }

        async function loadChatMessages() {
            if (!activeChatRecipient) return;

            const res = await fetch(`api/messages_api.php?action=get_chat&with=${encodeURIComponent(activeChatRecipient.email)}`);
            const messages = await res.json();

            const activeStream = document.getElementById('chat-messages-stream');
            activeStream.innerHTML = messages.map(m => {
                const sideClass = m.sender_email === '<?php echo $_SESSION['email']; ?>' ? 'outgoing' : 'incoming';
                return `
                <div class="bubble-wrapper">
                    <div class="chat-bubble ${sideClass}">
                        <div>${m.message_text}</div>
                        <div class="bubble-meta">${m.created_at}</div>
                    </div>
                </div>
                `;
            }).join('') || '<p style="padding:40px; text-align:center; color:#94a3b8; font-size:0.8rem;">No messages exchanged yet. Say hello!</p>';

            activeStream.scrollTop = activeStream.scrollHeight;
        }

        async function sendChatMessage() {
            if (!activeChatRecipient) return;
            const input = document.getElementById('chat-composer-field');
            const message = input.value.trim();
            if (!message) return;

            const res = await fetch('api/messages_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'send_message', receiver_email: activeChatRecipient.email, message: message })
            });
            const data = await res.json();

            if (data.status === 'success') {
                input.value = '';
                loadChatMessages();
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadGlobalFeed();
            checkUnreadNotificationsCount();
            checkUnreadMessagesCount();
            
            const urlParams = new URLSearchParams(window.location.search);
            const openParam = urlParams.get('open');
            if (openParam === 'notifications') {
                openDrawer('notifications');
            } else if (openParam === 'messages') {
                openDrawer('messages');
            }
            
            unreadBadgeInterval = setInterval(() => {
                checkUnreadNotificationsCount();
                checkUnreadMessagesCount();
            }, 10000);
        });
    </script>
</body>
</html>