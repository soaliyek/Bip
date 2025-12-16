<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /public/dashboard.php');
    exit;
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $profileColor = $_POST['profile_color'] ?? '#888888';
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    // Validate username
    $usernameErrors = validateUsername($username);
    if (!empty($usernameErrors)) {
        $errors = array_merge($errors, $usernameErrors);
    }
    
    // Check if username contains email local part
    if (usernameContainsEmail($username, $email)) {
        $errors[] = "Username cannot contain your email address";
    }
    
    // Validate password
    $passwordErrors = validatePassword($password);
    if (!empty($passwordErrors)) {
        $errors = array_merge($errors, $passwordErrors);
    }
    
    // Check password confirmation
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email or username already exists
    if (empty($errors)) {
        $conn = getDB();
        $stmt = $conn->prepare("SELECT userID FROM User WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->fetch_assoc()) {
            $errors[] = "Email or username already exists";
        }
        $stmt->close();
    }
    
    // Register user
    if (empty($errors)) {
        $conn = getDB();
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("
            INSERT INTO User (email, passwordHash, username, profileColor) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("ssss", $email, $passwordHash, $username, $profileColor);
        
        if ($stmt->execute()) {
            $success = "Registration successful! You can now login.";
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
}

$colors = getProfileColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Bip</title>
    <link rel="stylesheet" href="../public/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-box">
            <h1 style="justify-self: center;">Join Bip</h1>
            <p class="subtitle" style="justify-self: center;">Anonymous peer support chat</p>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <p><?= e($success) ?></p>
                    <p><a href="login.php">Click here to login</a></p>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= e($_POST['email'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required 
                               value="<?= e($_POST['username'] ?? '') ?>">
                        <small>Must be 3-50 characters, contain at least one digit, no spaces, and cannot contain your email</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                        <small>At least 8 characters with uppercase, lowercase, and digit</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Profile Color (your anonymous avatar)</label>
                        <div class="color-picker">
                            <?php foreach ($colors as $color): ?>
                                <label class="color-option">
                                    <input type="radio" name="profile_color" value="<?= $color ?>" 
                                           <?= (!isset($_POST['profile_color']) && $color === '#4ECDC4') || 
                                               (isset($_POST['profile_color']) && $_POST['profile_color'] === $color) ? 'checked' : '' ?>>
                                    <span class="color-circle" style="background-color: <?= $color ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Register</button>
                </form>
                
                <p class="auth-link">
                    Already have an account? <a href="login.php">Login here</a>
                </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
