<?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_disclaimer'])) {
        require_once __DIR__ . '/../includes/session.php';
        require_once __DIR__ . '/../config/database.php';
        
        $userID = getCurrentUserID();
        //$userID = $_SESSION['userID'] ?? null;
        $conn = getDB();
        
        // Mark user as having seen the welcome page
        $stmt = $conn->prepare("UPDATE User SET hasSeenWelcome = 1 WHERE userID = ?");
        $stmt->bind_param("i", $userID);
        $stmt->execute();
        $stmt->close();
        
        // Redirect to main app
        header("Location: ../public/pages/app.php");
        exit;
    }
    ?>