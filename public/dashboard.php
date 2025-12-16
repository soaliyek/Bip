<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
checkBanStatus();
updatePresence();

$user = getCurrentUser();
$conn = getDB();

// Get user's conversations
$stmt = $conn->prepare("
    SELECT 
        c.conversationID,
        c.createdAt,
        cp.userID as interlocutorID,
        u.username as interlocutorUsername,
        u.profileColor as interlocutorColor,
        us.mode as interlocutorMode,
        us.isOnline as interlocutorOnline,
        us.lastSeenAt as interlocutorLastSeen,
        (SELECT content FROM Message WHERE conversationID = c.conversationID ORDER BY createdAt DESC LIMIT 1) as lastMessage,
        (SELECT createdAt FROM Message WHERE conversationID = c.conversationID ORDER BY createdAt DESC LIMIT 1) as lastMessageTime,
        (SELECT COUNT(*) FROM SafeUser WHERE userID = ? AND safeUserID = cp.userID) as isInSafelist
    FROM Conversation c
    JOIN ConversationParticipant cp ON c.conversationID = cp.conversationID
    JOIN User u ON cp.userID = u.userID
    LEFT JOIN UserStatus us ON cp.userID = us.userID
    WHERE c.conversationID IN (
        SELECT conversationID FROM ConversationParticipant WHERE userID = ?
    )
    AND cp.userID != ?
    ORDER BY lastMessageTime DESC
");
$userID = $user['userID'];
$stmt->bind_param("iii", $userID, $userID, $userID);
$stmt->execute();
$result = $stmt->get_result();
$conversations = [];
while ($row = $result->fetch_assoc()) {
    $conversations[] = $row;
}
$stmt->close();

// Get user's current status
$stmt = $conn->prepare("SELECT * FROM UserStatus WHERE userID = ?");
$stmt->bind_param("i", $userID);
$stmt->execute();
$result = $stmt->get_result();
$userStatus = $result->fetch_assoc();
$stmt->close();

$hasFreshExperience = empty($conversations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bip</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Bip</h2>
                <div class="user-profile">
                    <div class="profile-circle" style="background-color: <?= e($user['profileColor']) ?>"></div>
                    <span class="username"><?= e($user['username']) ?></span>
                </div>
            </div>
            
            <div class="sidebar-actions">
                <button class="btn btn-listen" id="listenBtn">
                    <span class="status-dot" style="background-color: #52B788"></span>
                    Listen
                </button>
                <button class="btn btn-talk" id="talkBtn">
                    <span class="status-dot" style="background-color: #4ECDC4"></span>
                    Talk
                </button>
            </div>
            
            <div class="sidebar-search">
                <input type="text" id="conversationSearch" placeholder="Search conversations..." 
                       <?= $hasFreshExperience ? 'disabled title="No conversations yet"' : '' ?>>
            </div>
            
            <div class="sidebar-nav">
                <button class="btn btn-secondary btn-block" id="seeOnlineBtn">
                    See Online Users
                </button>
                <a href="settings.php" class="btn btn-secondary btn-block">Settings</a>
                <?php if ($user['isAdmin']): ?>
                    <a href="admin.php" class="btn btn-secondary btn-block">Admin Panel</a>
                <?php endif; ?>
                <a href="/authentication/logout.php" class="btn btn-secondary btn-block">Logout</a>
            </div>
            
            <?php if (!$hasFreshExperience): ?>
                <div class="conversations-list" id="conversationsList">
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item" data-conversation-id="<?= $conv['conversationID'] ?>">
                            <div class="conv-avatar">
                                <div class="profile-circle" style="background-color: <?= e($conv['interlocutorColor']) ?>"></div>
                                <span class="status-dot-small" style="background-color: <?= getStatusColor($conv['interlocutorMode'], $conv['interlocutorOnline'] && isUserOnline($conv['interlocutorLastSeen'])) ?>"></span>
                            </div>
                            <div class="conv-content">
                                <div class="conv-header">
                                    <span class="conv-username"><?= e($conv['interlocutorUsername']) ?></span>
                                    <?php if ($conv['isInSafelist']): ?>
                                        <span class="safelist-badge" title="In your safelist">★</span>
                                    <?php endif; ?>
                                </div>
                                <div class="conv-preview">
                                    <?= e(substr($conv['lastMessage'] ?? 'No messages yet', 0, 50)) ?>
                                    <?= strlen($conv['lastMessage'] ?? '') > 50 ? '...' : '' ?>
                                </div>
                                <div class="conv-time"><?= $conv['lastMessageTime'] ? timeAgo($conv['lastMessageTime']) : '' ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <?php if ($hasFreshExperience): ?>
                <div class="fresh-user-welcome">
                    <div class="welcome-card">
                        <h1>Welcome to Bip, <?= e($user['username']) ?>!</h1>
                        <p class="welcome-subtitle">Your anonymous peer support space</p>
                        
                        <div class="welcome-profile">
                            <div class="profile-circle-large" style="background-color: <?= e($user['profileColor']) ?>"></div>
                            <p>This is your anonymous profile color</p>
                        </div>
                        
                        <div class="welcome-actions">
                            <h3>Get Started:</h3>
                            <div class="action-cards">
                                <div class="action-card">
                                    <div class="action-icon listen-icon">👂</div>
                                    <h4>Listen</h4>
                                    <p>Offer support to others who need someone to talk to</p>
                                    <button class="btn btn-listen" onclick="setMode('listen')">Start Listening</button>
                                </div>
                                
                                <div class="action-card">
                                    <div class="action-icon talk-icon">💬</div>
                                    <h4>Talk</h4>
                                    <p>Find a listener and share what's on your mind</p>
                                    <button class="btn btn-talk" onclick="setMode('talk')">Find a Listener</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="welcome-info">
                            <p><strong>Remember:</strong> Bip is for peer support, not professional therapy. Be kind, be respectful, and stay anonymous.</p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="chat-placeholder">
                    <div class="placeholder-content">
                        <h2>Select a conversation</h2>
                        <p>Choose a conversation from the left to start chatting</p>
                        <p>Or click "See Online Users" to start a new conversation</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="/public/js/dashboard.js"></script>
</body>
</html>
