<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

// Only admins can comment
$currentUser = getCurrentUser();
if (!$currentUser['isAdmin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$reportID = $data['reportID'] ?? null;
$comment = trim($data['comment'] ?? '');
$action = $data['action'] ?? 'CONFIRMED'; // CONFIRMED or DISCARDED

if (!$reportID || !$comment) {
    echo json_encode(['error' => 'Report ID and comment required']);
    http_response_code(400);
    exit;
}

$conn = getDB();
$currentUserID = getCurrentUserID();

// Get report details
$stmt = $conn->prepare("
    SELECT 
        r.reportID,
        r.messageID,
        r.reporterUserID,
        m.senderUserID as reportedUserID,
        m.conversationID
    FROM Report r
    JOIN Message m ON r.messageID = m.messageID
    WHERE r.reportID = ?
");
$stmt->bind_param("i", $reportID);
$stmt->execute();
$result = $stmt->get_result();
$report = $result->fetch_assoc();
$stmt->close();

if (!$report) {
    echo json_encode(['error' => 'Report not found']);
    http_response_code(404);
    exit;
}

// Update report with admin comment
$stmt = $conn->prepare("
    UPDATE Report 
    SET status = ?, 
        adminComment = ?, 
        handledByAdminID = ?, 
        handledAt = NOW()
    WHERE reportID = ?
");
$stmt->bind_param("ssii", $action, $comment, $currentUserID, $reportID);
$stmt->execute();
$stmt->close();

// Create system message in the conversation
$systemMessage = "System Message: " . $comment;
$stmt = $conn->prepare("
    INSERT INTO Message (conversationID, senderUserID, content, isSystem)
    VALUES (?, NULL, ?, 1)
");
$stmt->bind_param("is", $report['conversationID'], $systemMessage);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Comment added and system message sent',
    'action' => $action,
    'reportID' => $reportID
]);
