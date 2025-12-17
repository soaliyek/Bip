<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();

header('Content-Type: application/json');

$category = $_GET['category'] ?? 'BOTH'; // MESSAGE, USER, or BOTH

$conn = getDB();

// Validate category
$validCategories = ['MESSAGE', 'USER', 'BOTH'];
if (!in_array($category, $validCategories)) {
    echo json_encode(['error' => 'Invalid category']);
    http_response_code(400);
    exit;
}

// Fetch flags based on category
if ($category === 'MESSAGE') {
    $sql = "
        SELECT 
            flagTypeID,
            code,
            displayName,
            description,
            severity
        FROM FlagType
        WHERE category IN ('MESSAGE', 'BOTH')
            AND isActive = 1
        ORDER BY 
            FIELD(severity, 'CRITICAL', 'HIGH', 'MEDIUM', 'LOW'),
            displayName
    ";
} elseif ($category === 'USER') {
    $sql = "
        SELECT 
            flagTypeID,
            code,
            displayName,
            description,
            severity,
            CASE 
                WHEN code IN ('HELPFUL', 'SAFE', 'EMPATHETIC') THEN 'POSITIVE'
                WHEN code IN ('TOXIC', 'DISRESPECTFUL', 'INAPPROPRIATE') THEN 'NEGATIVE'
                ELSE 'NEUTRAL'
            END as sentiment
        FROM FlagType
        WHERE category IN ('USER', 'BOTH')
            AND isActive = 1
        ORDER BY 
            CASE 
                WHEN code IN ('HELPFUL', 'SAFE', 'EMPATHETIC') THEN 1
                ELSE 2
            END,
            displayName
    ";
} else {
    $sql = "
        SELECT 
            flagTypeID,
            code,
            displayName,
            description,
            category,
            severity
        FROM FlagType
        WHERE isActive = 1
        ORDER BY category, displayName
    ";
}

$result = $conn->query($sql);

$flags = [];
while ($row = $result->fetch_assoc()) {
    $flags[] = $row;
}

echo json_encode([
    'success' => true,
    'flags' => $flags,
    'count' => count($flags)
]);
