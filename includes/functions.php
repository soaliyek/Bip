<?php

// Sanitize output
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Validate username
function validateUsername($username) {
    $errors = [];
    
    // Length check
    if (strlen($username) < 3 || strlen($username) > 50) {
        $errors[] = "Username must be between 3 and 50 characters";
    }
    
    // Must contain at least one digit
    if (!preg_match('/\d/', $username)) {
        $errors[] = "Username must contain at least one digit";
    }
    
    // No spaces
    if (strpos($username, ' ') !== false) {
        $errors[] = "Username cannot contain spaces";
    }
    
    return $errors;
}

// Check if username contains email local part
function usernameContainsEmail($username, $email) {
    $localPart = explode('@', $email)[0];
    return stripos($username, $localPart) !== false;
}

// Validate password
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters";
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    
    if (!preg_match('/\d/', $password)) {
        $errors[] = "Password must contain at least one digit";
    }
    
    return $errors;
}

// Get profile colors palette
function getProfileColors() {
    return [
        '#FF6B6B', // Red
        '#4ECDC4', // Teal
        '#45B7D1', // Blue
        '#FFA07A', // Light Salmon
        '#98D8C8', // Mint
        '#F7DC6F', // Yellow
        '#BB8FCE', // Purple
        '#85C1E2', // Sky Blue
        '#F8B739', // Orange
        '#52B788', // Green
        '#E56B6F', // Rose
        '#6C757D', // Gray
    ];
}

// Format time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Check if user is online
function isUserOnline($lastSeenAt) {
    $threshold = 180; // 3 minutes
    $diff = time() - strtotime($lastSeenAt);
    return $diff <= $threshold;
}

// Get status color
function getStatusColor($mode, $isOnline) {
    if (!$isOnline) {
        return '#6C757D'; // Gray - offline
    }
    
    switch ($mode) {
        case 'LISTENER_AVAILABLE':
            return '#52B788'; // Green - listening
        case 'LOOKING_TO_TALK':
            return '#4ECDC4'; // Teal - looking to talk
        case 'IN_CONVERSATION':
            return '#FFA07A'; // Orange - busy
        default: // IDLE
            return '#85C1E2'; // Light blue - online/idle
    }
}

// Get status text
function getStatusText($mode, $isOnline) {
    if (!$isOnline) {
        return 'Offline';
    }
    
    switch ($mode) {
        case 'LISTENER_AVAILABLE':
            return 'Listening';
        case 'LOOKING_TO_TALK':
            return 'Looking to talk';
        case 'IN_CONVERSATION':
            return 'In conversation';
        default:
            return 'Online';
    }
}

// JSON response helper
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
