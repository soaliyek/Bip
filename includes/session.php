<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['userID']);
}

// Get current user ID
function getCurrentUserID() {
    return $_SESSION['userID'] ?? null;
}

// Get current user data
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    require_once __DIR__ . '/../config/database.php';
    $conn = getDB();
    
    $stmt = $conn->prepare("SELECT * FROM User WHERE userID = ?");
    $userID = getCurrentUserID();
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user;
}

// Check if user is admin
function isAdmin() {
    $user = getCurrentUser();
    return $user && $user['isAdmin'] == 1;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /authentication/login.php');
        exit;
    }
}

// Require admin
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: /public/dashboard.php');
        exit;
    }
}

// Check if user is banned
function checkBanStatus() {
    $user = getCurrentUser();
    if ($user && $user['accountStatus'] === 'BANNED') {
        session_destroy();
        header('Location: /authentication/login.php?error=banned');
        exit;
    }
}

// Login user
function loginUser($userID) {
    $_SESSION['userID'] = $userID;
    
    // Update last login
    require_once __DIR__ . '/../config/database.php';
    $conn = getDB();
    $stmt = $conn->prepare("UPDATE User SET lastLoginAt = NOW() WHERE userID = ?");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->close();
    
    // Create or update user status
    $stmt = $conn->prepare("
        INSERT INTO UserStatus (userID, isOnline, lastSeenAt) 
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE isOnline = 1, lastSeenAt = NOW()
    ");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $stmt->close();
}

// Logout user
function logoutUser() {
    if (isLoggedIn()) {
        // Set user offline
        require_once __DIR__ . '/../config/database.php';
        $conn = getDB();
        $stmt = $conn->prepare("UPDATE UserStatus SET isOnline = 0, mode = 'IDLE' WHERE userID = ?");
        $userID = getCurrentUserID();
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->close();
    }
    
    session_destroy();
}

// Update user presence
function updatePresence() {
    if (isLoggedIn()) {
        require_once __DIR__ . '/../config/database.php';
        $conn = getDB();
        $stmt = $conn->prepare("
            UPDATE UserStatus 
            SET isOnline = 1, lastSeenAt = NOW() 
            WHERE userID = ?
        ");
        $userID = getCurrentUserID();
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->close();
    }
}
