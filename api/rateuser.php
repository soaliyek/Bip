<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$targetUserID = $data['targetUserID'] ?? null;
$flagTypeID = $data['flagTypeID'] ?? null;
$ratingValue = $data['ratingValue'] ?? null;

if (!$targetUserID) {
    jsonResponse(['error' => 'Target user ID required'], 400);
}

$conn = getDB();
$currentUserID = getCurrentUserID();

// Cannot rate yourself
if ($currentUserID == $targetUserID) {
    jsonResponse(['error' => 'Cannot rate yourself'], 400);
}

// Verify target user exists
$stmt = $conn->prepare("SELECT userID FROM User WHERE userID = ?");
$stmt->bind_param("i", $targetUserID);
$stmt->execute();
$result = $stmt->get_result();
if (!$result->fetch_assoc()) {
    jsonResponse(['error' => 'User not found'], 404);
}
$stmt->close();

// Handle flag type
if ($flagTypeID) {
    // Verify flag type exists and is for users
    $stmt = $conn->prepare("SELECT code FROM FlagType WHERE flagTypeID = ? AND category IN ('USER', 'BOTH')");
    $stmt->bind_param("i", $flagTypeID);
    $stmt->execute();
    $result = $stmt->get_result();
    $flagData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$flagData) {
        jsonResponse(['error' => 'Invalid flag type'], 400);
    }
    
    // Insert user rating/flag
    $stmt = $conn->prepare("
        INSERT INTO UserRating (raterUserID, targetUserID, flagTypeID, ratingValue) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iiii", $currentUserID, $targetUserID, $flagTypeID, $ratingValue);
    $stmt->execute();
    $stmt->close();
    
    // If positive flag, add to safelist
    $positiveFlags = ['HELPFUL', 'SAFE', 'EMPATHETIC'];
    if (in_array($flagData['code'], $positiveFlags)) {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO SafeUser (userID, safeUserID) 
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $currentUserID, $targetUserID);
        $stmt->execute();
        $stmt->close();
    }
}

// Handle star rating
if ($ratingValue !== null && $flagTypeID === null) {
    if ($ratingValue < 1 || $ratingValue > 5) {
        jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
    }
    
    // Insert new rating without flag
    $stmt = $conn->prepare("
        INSERT INTO UserRating (raterUserID, targetUserID, flagTypeID, ratingValue) 
        VALUES (?, ?, NULL, ?)
    ");
    $stmt->bind_param("iii", $currentUserID, $targetUserID, $ratingValue);
    $stmt->execute();
    $stmt->close();
}

jsonResponse([
    'success' => true,
    'message' => 'Rating submitted successfully'
]);
