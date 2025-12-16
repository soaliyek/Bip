<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /public/dashboard.php');
    exit;
}

$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'banned') {
    $error = "Your account has been banned. Please contact support.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Email and password are required";
    } else {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT * FROM User WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user && password_verify($password, $user['passwordHash'])) {
            // Check if account is banned
            if ($user['accountStatus'] === 'BANNED') {
                $error = "Your account has been banned. Please contact support.";
            } else {
                loginUser($user['userID']);
                header('Location: ../public/dashboard.php');
                exit;
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bip</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1>Welcome to Bip</h1>
            <p class="subtitle">Anonymous peer support chat</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <p><?= e($error) ?></p>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            
            <p class="auth-link">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
        </div>
    </div>
</body>
</html>
