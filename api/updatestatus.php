<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$mode = $data['mode'] ?? null;

$validModes = ['IDLE', 'LISTENER_AVAILABLE', 'LOOKING_TO_TALK', 'IN_CONVERSATION'];

if (!in_array($mode, $validModes)) {
    jsonResponse(['error' => 'Invalid mode'], 400);
}

$conn = getDB();
$stmt = $conn->prepare("
    UPDATE UserStatus 
    SET mode = ?, isOnline = 1, lastSeenAt = NOW() 
    WHERE userID = ?
");
$userID = getCurrentUserID();
$stmt->bind_param("si", $mode, $userID);
$stmt->execute();
$stmt->close();

jsonResponse(['success' => true, 'mode' => $mode]);
