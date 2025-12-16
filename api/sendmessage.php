<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$conversationID = $data['conversationID'] ?? null;
$content = trim($data['content'] ?? '');

if (!$conversationID || empty($content)) {
    jsonResponse(['error' => 'Conversation ID and message content required'], 400);
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

// Insert message
$stmt = $conn->prepare("
    INSERT INTO Message (conversationID, senderUserID, content, isSystem) 
    VALUES (?, ?, ?, 0)
");
$stmt->bind_param("iis", $conversationID, $currentUserID, $content);
$stmt->execute();
$messageID = $conn->insert_id;
$stmt->close();

// Get the inserted message with user info
$stmt = $conn->prepare("
    SELECT 
        m.*,
        u.username as senderUsername,
        u.profileColor as senderColor
    FROM Message m
    JOIN User u ON m.senderUserID = u.userID
    WHERE m.messageID = ?
");
$stmt->bind_param("i", $messageID);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();
$stmt->close();

jsonResponse([
    'success' => true,
    'message' => $message
]);
