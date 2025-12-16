<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
checkBanStatus();
updatePresence();

$user = getCurrentUser();
$conn = getDB();

// Get online users with safelist priority
$stmt = $conn->prepare("
    SELECT 
        u.userID,
        u.username,
        u.profileColor,
        us.mode,
        us.isOnline,
        us.lastSeenAt,
        (SELECT COUNT(*) FROM SafeUser WHERE userID = ? AND safeUserID = u.userID) as isInSafelist
    FROM User u
    LEFT JOIN UserStatus us ON u.userID = us.userID
    WHERE u.userID != ?
        AND us.isOnline = 1
        AND TIMESTAMPDIFF(SECOND, us.lastSeenAt, NOW()) <= 180
    ORDER BY isInSafelist DESC, us.mode, u.username
");
$userID = $user['userID'];
$stmt->bind_param("ii", $userID, $userID);
$stmt->execute();
$result = $stmt->get_result();
$onlineUsers = [];
while ($row = $result->fetch_assoc()) {
    $onlineUsers[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Users - Bip</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <div class="online-users-container">
        <div class="online-users-header">
            <a href="/public/dashboard.php" class="back-button">← Back to Conversations</a>
            <h1>Online Users</h1>
        </div>
        
        <div class="online-users-list">
            <?php if (empty($onlineUsers)): ?>
                <div class="no-users">
                    <p>No users are currently online.</p>
                    <p>Check back later or set your status to "Listen" to help others!</p>
                </div>
            <?php else: ?>
                <?php foreach ($onlineUsers as $onlineUser): ?>
                    <div class="online-user-card">
                        <div class="user-avatar">
                            <div class="profile-circle" style="background-color: <?= e($onlineUser['profileColor']) ?>"></div>
                            <span class="status-dot-large" style="background-color: <?= getStatusColor($onlineUser['mode'], true) ?>"></span>
                        </div>
                        <div class="user-info">
                            <div class="user-name">
                                <?= e($onlineUser['username']) ?>
                                <?php if ($onlineUser['isInSafelist']): ?>
                                    <span class="safelist-badge" title="In your safelist">★</span>
                                <?php endif; ?>
                            </div>
                            <div class="user-status">
                                <?= getStatusText($onlineUser['mode'], true) ?>
                            </div>
                        </div>
                        <div class="user-actions">
                            <?php if ($onlineUser['mode'] === 'LISTENER_AVAILABLE'): ?>
                                <button class="btn btn-primary start-conversation-btn" 
                                        data-user-id="<?= $onlineUser['userID'] ?>"
                                        data-username="<?= e($onlineUser['username']) ?>">
                                    Start Conversation
                                </button>
                            <?php else: ?>
                                <span class="status-label"><?= getStatusText($onlineUser['mode'], true) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="/public/js/online-users.js"></script>
</body>
</html>
