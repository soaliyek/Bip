<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$conversationID = $_GET['conversationID'] ?? null;
$lastMessageID = $_GET['lastMessageID'] ?? 0;

if (!$conversationID) {
    jsonResponse(['error' => 'Conversation ID required'], 400);
}

$conn = getDB();
$currentUserID = getCurrentUserID();

// Verify user is part of this conversation
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM ConversationParticipant 
    WHERE conversationID = ? AND userID = ?
");
$stmt->bind_param("ii", $conversationID, $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] == 0) {
    jsonResponse(['error' => 'Not authorized for this conversation'], 403);
}

// Get new messages
$stmt = $conn->prepare("
    SELECT 
        m.*,
        u.username as senderUsername,
        u.profileColor as senderColor
    FROM Message m
    LEFT JOIN User u ON m.senderUserID = u.userID
    WHERE m.conversationID = ? AND m.messageID > ?
    ORDER BY m.createdAt ASC
");
$stmt->bind_param("ii", $conversationID, $lastMessageID);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

// Get interlocutor status
$stmt = $conn->prepare("
    SELECT 
        us.mode,
        us.isOnline,
        us.lastSeenAt
    FROM ConversationParticipant cp
    JOIN UserStatus us ON cp.userID = us.userID
    WHERE cp.conversationID = ? AND cp.userID != ?
");
$stmt->bind_param("ii", $conversationID, $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
$interlocutorStatus = $result->fetch_assoc();
$stmt->close();

jsonResponse([
    'success' => true,
    'messages' => $messages,
    'interlocutorStatus' => $interlocutorStatus
]);
