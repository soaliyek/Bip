<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

requireLogin();
checkBanStatus();
updatePresence();

$user = getCurrentUser();
$conversationID = $_GET['id'] ?? null;

if (!$conversationID) {
    header('Location: dashboard.php');
    exit;
}

$conn = getDB();

// Verify user is part of this conversation
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM ConversationParticipant 
    WHERE conversationID = ? AND userID = ?
");
$userID = $user['userID'];
$stmt->bind_param("ii", $conversationID, $userID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] == 0) {
    header('Location: dashboard.php');
    exit;
}

// Get interlocutor information
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
$stmt->bind_param("iii", $userID, $conversationID, $userID);
$stmt->execute();
$result = $stmt->get_result();
$interlocutor = $result->fetch_assoc();
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
$stmt->bind_param("i", $conversationID);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with <?= e($interlocutor['username']) ?> - Bip</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="chat-container">
        <!-- Chat Header -->
        <div class="chat-header">
            <a href="dashboard.php" class="back-button">← Back</a>
            <div class="chat-header-user" id="interlocutorProfile" style="cursor: pointer;">
                <div class="profile-circle" style="background-color: <?= e($interlocutor['profileColor']) ?>"></div>
                <div class="chat-header-info">
                    <span class="chat-username"><?= e($interlocutor['username']) ?></span>
                    <span class="chat-status" id="interlocutorStatus">
                        <span class="status-dot-small" style="background-color: <?= getStatusColor($interlocutor['mode'], $interlocutor['isOnline'] && isUserOnline($interlocutor['lastSeenAt'])) ?>"></span>
                        <?= getStatusText($interlocutor['mode'], $interlocutor['isOnline'] && isUserOnline($interlocutor['lastSeenAt'])) ?>
                    </span>
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
                    <div class="message <?= $msg['senderUserID'] == $user['userID'] ? 'message-own' : 'message-other' ?>" 
                         data-message-id="<?= $msg['messageID'] ?>">
                        <div class="message-avatar">
                            <div class="profile-circle-small" style="background-color: <?= e($msg['senderColor']) ?>"></div>
                        </div>
                        <div class="message-content">
                            <div class="message-bubble <?= $msg['isFlagged'] ? 'message-flagged' : '' ?>">
                                <?= nl2br(e($msg['content'])) ?>
                            </div>
                            <div class="message-time"><?= date('g:i A', strtotime($msg['createdAt'])) ?></div>
                        </div>
                        <button class="message-report-btn" title="Report this message">⚠</button>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Message Input -->
        <div class="message-input-area">
            <form id="messageForm">
                <input type="hidden" name="conversationID" value="<?= $conversationID ?>">
                <textarea id="messageInput" name="content" placeholder="Type your message..." rows="1"></textarea>
                <button type="submit" class="btn btn-send">Send</button>
            </form>
        </div>
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
                <div class="profile-circle-large" style="background-color: <?= e($interlocutor['profileColor']) ?>"></div>
                <h3><?= e($interlocutor['username']) ?></h3>
                
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
                        <button class="btn btn-flag btn-flag-negative" data-flag="OTHER">Other</button>
                    </div>
                </div>
                
                <?php if ($interlocutor['isInSafelist']): ?>
                    <p class="safelist-info">★ This user is in your safelist</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const conversationID = <?= $conversationID ?>;
        const interlocutorID = <?= $interlocutor['userID'] ?>;
    </script>
    <script src="../js/chat.js"></script>
</body>
</html>
