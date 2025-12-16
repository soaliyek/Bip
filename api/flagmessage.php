<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$messageID = $data['messageID'] ?? null;
$flagType = $data['flagType'] ?? 'OTHER';
$reason = trim($data['reason'] ?? '');

if (!$messageID) {
    jsonResponse(['error' => 'Message ID required'], 400);
}

$validFlags = ['INSULTING', 'SEXUAL', 'THREATENING', 'DISCRIMINATORY', 'SPAM', 'OTHER'];
if (!in_array($flagType, $validFlags)) {
    jsonResponse(['error' => 'Invalid flag type'], 400);
}

$conn = getDB();
$currentUserID = getCurrentUserID();

// Verify message exists and user has access to it
$stmt = $conn->prepare("
    SELECT m.* 
    FROM Message m
    JOIN ConversationParticipant cp ON m.conversationID = cp.conversationID
    WHERE m.messageID = ? AND cp.userID = ?
");
$stmt->bind_param("ii", $messageID, $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();
$stmt->close();

if (!$message) {
    jsonResponse(['error' => 'Message not found or access denied'], 404);
}

// Check if already reported by this user
$stmt = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM Report 
    WHERE messageID = ? AND reporterUserID = ?
");
$stmt->bind_param("ii", $messageID, $currentUserID);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if ($row['count'] > 0) {
    jsonResponse(['error' => 'You have already reported this message'], 400);
}

// Insert report
$stmt = $conn->prepare("
    INSERT INTO Report (messageID, reporterUserID, flagType, reason, status) 
    VALUES (?, ?, ?, ?, 'PENDING')
");
$stmt->bind_param("iiss", $messageID, $currentUserID, $flagType, $reason);
$stmt->execute();
$stmt->close();

// Mark message as flagged
$stmt = $conn->prepare("UPDATE Message SET isFlagged = 1 WHERE messageID = ?");
$stmt->bind_param("i", $messageID);
$stmt->execute();
$stmt->close();

jsonResponse([
    'success' => true,
    'message' => 'Message reported successfully'
]);
