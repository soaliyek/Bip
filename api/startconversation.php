<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Not authenticated'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$listenerID = $data['listenerID'] ?? null;

if (!$listenerID) {
    jsonResponse(['error' => 'Listener ID required'], 400);
}

$conn = getDB();
$currentUserID = getCurrentUserID();

// Check if listener is available
$stmt = $conn->prepare("
    SELECT u.*, us.mode 
    FROM User u
    JOIN UserStatus us ON u.userID = us.userID
    WHERE u.userID = ? AND u.accountStatus = 'ACTIVE'
");
$stmt->bind_param("i", $listenerID);
$stmt->execute();
$result = $stmt->get_result();
$listener = $result->fetch_assoc();
$stmt->close();

if (!$listener) {
    jsonResponse(['error' => 'Listener not found'], 404);
}

if ($listener['mode'] !== 'LISTENER_AVAILABLE') {
    jsonResponse(['error' => 'Listener is not available'], 400);
}

// Check if conversation already exists between these users
$stmt = $conn->prepare("
    SELECT c.conversationID 
    FROM Conversation c
    JOIN ConversationParticipant cp1 ON c.conversationID = cp1.conversationID
    JOIN ConversationParticipant cp2 ON c.conversationID = cp2.conversationID
    WHERE cp1.userID = ? AND cp2.userID = ?
    LIMIT 1
");
$stmt->bind_param("ii", $currentUserID, $listenerID);
$stmt->execute();
$result = $stmt->get_result();
$existing = $result->fetch_assoc();
$stmt->close();

if ($existing) {
    jsonResponse([
        'success' => true,
        'conversationID' => $existing['conversationID'],
        'isNew' => false
    ]);
}

// Create new conversation
$conn->begin_transaction();

try {
    // Insert conversation
    $stmt = $conn->prepare("
        INSERT INTO Conversation (createdByUserID, initiatorRole) 
        VALUES (?, 'TALKER')
    ");
    $stmt->bind_param("i", $currentUserID);
    $stmt->execute();
    $conversationID = $conn->insert_id;
    $stmt->close();
    
    // Add talker as participant
    $stmt = $conn->prepare("
        INSERT INTO ConversationParticipant (conversationID, userID, roleInConversation) 
        VALUES (?, ?, 'TALKER')
    ");
    $stmt->bind_param("ii", $conversationID, $currentUserID);
    $stmt->execute();
    $stmt->close();
    
    // Add listener as participant
    $stmt = $conn->prepare("
        INSERT INTO ConversationParticipant (conversationID, userID, roleInConversation) 
        VALUES (?, ?, 'LISTENER')
    ");
    $stmt->bind_param("ii", $conversationID, $listenerID);
    $stmt->execute();
    $stmt->close();
    
    // Add system message
    $stmt = $conn->prepare("
        INSERT INTO Message (conversationID, content, isSystem) 
        VALUES (?, ?, 1)
    ");
    $systemMessage = "Conversation started";
    $stmt->bind_param("is", $conversationID, $systemMessage);
    $stmt->execute();
    $stmt->close();
    
    // Update both users' status to IN_CONVERSATION
    $stmt = $conn->prepare("UPDATE UserStatus SET mode = 'IN_CONVERSATION' WHERE userID IN (?, ?)");
    $stmt->bind_param("ii", $currentUserID, $listenerID);
    $stmt->execute();
    $stmt->close();
    
    $conn->commit();
    
    jsonResponse([
        'success' => true,
        'conversationID' => $conversationID,
        'isNew' => true
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    jsonResponse(['error' => 'Failed to create conversation'], 500);
}
