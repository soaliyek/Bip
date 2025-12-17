<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();
checkBanStatus();
updatePresence();

$user = getCurrentUser();
$conn = getDB();

// Get selected conversation ID
$selectedConversationID = $_GET['conversation'] ?? null;

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

// Get selected conversation details if any
$selectedConversation = null;
$messages = [];
if ($selectedConversationID) {
    // Verify access
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM ConversationParticipant 
        WHERE conversationID = ? AND userID = ?
    ");
    $stmt->bind_param("ii", $selectedConversationID, $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row['count'] > 0) {
        // Get interlocutor info
        $stmt = $conn->prepare("
            SELECT 
                u.userID,
                u.username,
                u.profileColor,
                us.mode,
                us.isOnline,
                us.lastSeenAt,
                (SELECT COUNT(*) FROM SafeUser WHERE userID = ? AND safeUserID = u.userID) as isInSafelist
            FROM ConversationParticipant cp
            JOIN User u ON cp.userID = u.userID
            LEFT JOIN UserStatus us ON u.userID = us.userID
            WHERE cp.conversationID = ? AND cp.userID != ?
        ");
        $stmt->bind_param("iii", $userID, $selectedConversationID, $userID);
        $stmt->execute();
        $result = $stmt->get_result();
        $selectedConversation = $result->fetch_assoc();
        $stmt->close();
        
        // Get messages
        $stmt = $conn->prepare("
            SELECT 
                m.*,
                u.username as senderUsername,
                u.profileColor as senderColor
            FROM Message m
            LEFT JOIN User u ON m.senderUserID = u.userID
            WHERE m.conversationID = ?
            ORDER BY m.createdAt ASC
        ");
        $stmt->bind_param("i", $selectedConversationID);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
}

$hasFreshExperience = empty($conversations);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bip - Anonymous Peer Support</title>
    <link rel="stylesheet" href="../css/app-style.css">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="top-nav">
        <div class="nav-left">
            <h1 class="logo">BipLogo</h1>
        </div>
        <div class="nav-center">
            <button class="nav-btn" id="bipersBtn">Bipers</button>
            <button class="nav-btn" id="talkBtn">Talk</button>
            <button class="nav-btn" id="listenBtn">Listen</button>
            <?php if ($user['isAdmin']): ?>
                <a href="admin.php" class="nav-btn">Admin</a>
            <?php endif; ?>
        </div>
        <div class="nav-right">
            <a href="settings.php" class="profile-link" title="Settings">
                <div class="profile-circle-nav" style="background-color: <?= e($user['profileColor']) ?>"></div>
            </a>
            <a href="../../authentication/logout.php" class="logout-btn" title="Logout">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </nav>

    <!-- Main Container -->
    <div class="app-container">
        <!-- Left Sidebar: Conversations -->
        <aside class="conversations-sidebar">
            <div class="search-container">
                <input type="text" 
                       id="conversationSearch" 
                       placeholder="Search in conversation..." 
                       <?= $hasFreshExperience ? 'disabled' : '' ?>>
            </div>
            
            <div class="conversations-list" id="conversationsList">
                <?php if ($hasFreshExperience): ?>
                    <div class="no-conversations">
                        <p>No conversations yet</p>
                        <p>Click "Talk" or "Listen" to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <div class="conversation-item <?= $selectedConversationID == $conv['conversationID'] ? 'active' : '' ?>" 
                             data-conversation-id="<?= $conv['conversationID'] ?>">
                            <div class="conv-avatar-wrapper">
                                <div class="conv-avatar" style="background-color: <?= e($conv['interlocutorColor']) ?>"></div>
                                <span class="status-dot" style="background-color: <?= getStatusColor($conv['interlocutorMode'], $conv['interlocutorOnline'] && isUserOnline($conv['interlocutorLastSeen'])) ?>"></span>
                            </div>
                            <div class="conv-info">
                                <div class="conv-username">
                                    <?= e($conv['interlocutorUsername']) ?>
                                    <?php if ($conv['isInSafelist']): ?>
                                        <span class="safelist-star">★</span>
                                    <?php endif; ?>
                                </div>
                                <div class="conv-time"><?= $conv['lastMessageTime'] ? timeAgo($conv['lastMessageTime']) : 'Just started' ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Right Panel: Messages -->
        <main class="messages-panel">
            <?php if (!$selectedConversation): ?>
                <div class="no-conversation-selected">
                    <div class="welcome-message">
                        <h2>Welcome to Bip!</h2>
                        <p>Select a conversation from the left to start chatting</p>
                        <p>Or click "Talk" to find a listener</p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Conversation Header -->
                <div class="conversation-header">
                    <div class="header-user-info" id="interlocutorProfile">
                        <div class="header-avatar-wrapper">
                            <div class="header-avatar" style="background-color: <?= e($selectedConversation['profileColor']) ?>"></div>
                            <span class="status-dot" style="background-color: <?= getStatusColor($selectedConversation['mode'], $selectedConversation['isOnline'] && isUserOnline($selectedConversation['lastSeenAt'])) ?>"></span>
                        </div>
                        <div class="header-info">
                            <div class="header-username"><?= e($selectedConversation['username']) ?></div>
                            <div class="header-status" id="interlocutorStatus">
                                <?= getStatusText($selectedConversation['mode'], $selectedConversation['isOnline'] && isUserOnline($selectedConversation['lastSeenAt'])) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Messages Area -->
                <div class="messages-area" id="messagesArea">
                    <?php foreach ($messages as $msg): ?>
                        <?php if ($msg['isSystem']): ?>
                            <div class="message-system">
                                <?= e($msg['content']) ?>
                            </div>
                        <?php else: ?>
                            <?php $isOwn = $msg['senderUserID'] == $user['userID']; ?>
                            <div class="message <?= $isOwn ? 'message-own' : 'message-other' ?>" 
                                 data-message-id="<?= $msg['messageID'] ?>">
                                <div class="message-avatar">
                                    <div class="message-avatar-circle" style="background-color: <?= e($msg['senderColor']) ?>"></div>
                                </div>
                                <div class="message-content">
                                    <div class="message-bubble <?= $msg['isFlagged'] ? 'message-flagged' : '' ?>">
                                        <?= nl2br(e($msg['content'])) ?>
                                    </div>
                                    <div class="message-time"><?= date('g:i A', strtotime($msg['createdAt'])) ?></div>
                                </div>
                                <?php if (!$isOwn): ?>
                                    <button class="message-report-btn" title="Report this message" data-message-id="<?= $msg['messageID'] ?>">⚠</button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <!-- Message Input -->
                <div class="message-input-container">
                    <form id="messageForm">
                        <input type="hidden" name="conversationID" value="<?= $selectedConversationID ?>">
                        <textarea id="messageInput" 
                                  placeholder="Type Your Message Here..." 
                                  rows="1"></textarea>
                        <button type="submit" class="send-btn">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Report Modal -->
    <div id="reportModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2>Report Message</h2>
            <form id="reportForm">
                <input type="hidden" id="reportMessageID" name="messageID">
                <div class="form-group">
                    <label>Reason for reporting:</label>
                    <select name="flagType" required>
                        <option value="">Select a reason</option>
                        <option value="INSULTING">Insulting</option>
                        <option value="SEXUAL">Sexual content</option>
                        <option value="THREATENING">Threatening</option>
                        <option value="DISCRIMINATORY">Discriminatory</option>
                        <option value="SPAM">Spam</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Additional details (optional):</label>
                    <textarea name="reason" rows="3"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Submit Report</button>
            </form>
        </div>
    </div>

    <!-- Profile Modal -->
    <div id="profileModal" class="modal">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2>User Profile</h2>
            <div class="profile-modal-content">
                <?php if ($selectedConversation): ?>
                    <div class="profile-circle-large" style="background-color: <?= e($selectedConversation['profileColor']) ?>"></div>
                    <h3><?= e($selectedConversation['username']) ?></h3>
                    
                    <div class="profile-rating">
                        <h4>Rate this user:</h4>
                        <div class="star-rating" id="starRating">
                            <span class="star" data-value="1">☆</span>
                            <span class="star" data-value="2">☆</span>
                            <span class="star" data-value="3">☆</span>
                            <span class="star" data-value="4">☆</span>
                            <span class="star" data-value="5">☆</span>
                        </div>
                    </div>
                    
                    <div class="profile-flags">
                        <h4>Flag this user:</h4>
                        <div class="flag-buttons">
                            <button class="btn btn-flag btn-flag-positive" data-flag="HELPFUL">Helpful</button>
                            <button class="btn btn-flag btn-flag-positive" data-flag="SAFE">Safe</button>
                            <button class="btn btn-flag btn-flag-positive" data-flag="EMPATHETIC">Empathetic</button>
                            <button class="btn btn-flag btn-flag-negative" data-flag="TOXIC">Toxic</button>
                            <button class="btn btn-flag btn-flag-negative" data-flag="DISRESPECTFUL">Disrespectful</button>
                        </div>
                    </div>
                    
                    <?php if ($selectedConversation['isInSafelist']): ?>
                        <p class="safelist-info">★ This user is in your safelist</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        <?php if ($selectedConversationID && $selectedConversation): ?>
        const conversationID = <?= $selectedConversationID ?>;
        const interlocutorID = <?= $selectedConversation['userID'] ?>;
        const currentUserID = <?= $user['userID'] ?>;
        <?php else: ?>
        const conversationID = null;
        const interlocutorID = null;
        const currentUserID = <?= $user['userID'] ?>;
        <?php endif; ?>
    </script>
    <script src="../js/app.js"></script>
</body>
</html>
