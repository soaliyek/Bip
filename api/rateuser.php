<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$targetUserID = $data['targetUserID'] ?? null;
$flagType = $data['flagType'] ?? null;
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
if ($flagType) {
    $validFlags = ['HELPFUL', 'SAFE', 'EMPATHETIC', 'TOXIC', 'DISRESPECTFUL', 'OTHER'];
    if (!in_array($flagType, $validFlags)) {
        jsonResponse(['error' => 'Invalid flag type'], 400);
    }
    
    // Insert user rating/flag
    $stmt = $conn->prepare("
        INSERT INTO UserRating (raterUserID, targetUserID, flagType, ratingValue) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("iisi", $currentUserID, $targetUserID, $flagType, $ratingValue);
    $stmt->execute();
    $stmt->close();
    
    // If positive flag, add to safelist
    $positiveFlags = ['HELPFUL', 'SAFE', 'EMPATHETIC'];
    if (in_array($flagType, $positiveFlags)) {
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
if ($ratingValue !== null) {
    if ($ratingValue < 1 || $ratingValue > 5) {
        jsonResponse(['error' => 'Rating must be between 1 and 5'], 400);
    }
    
    // Insert new rating
    $stmt = $conn->prepare("
        INSERT INTO UserRating (raterUserID, targetUserID, flagType, ratingValue) 
        VALUES (?, ?, 'OTHER', ?)
    ");
    $stmt->bind_param("iii", $currentUserID, $targetUserID, $ratingValue);
    $stmt->execute();
    $stmt->close();
}

jsonResponse([
    'success' => true,
    'message' => 'Rating submitted successfully'
]);
