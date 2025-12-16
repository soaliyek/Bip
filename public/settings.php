<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

requireLogin();
checkBanStatus();
updatePresence();

$user = getCurrentUser();
$conn = getDB();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_color') {
        $newColor = $_POST['profile_color'] ?? '';
        $colors = getProfileColors();
        
        if (in_array($newColor, $colors)) {
            $stmt = $conn->prepare("UPDATE User SET profileColor = ? WHERE userID = ?");
            $userID = $user['userID'];
            $stmt->bind_param("si", $newColor, $userID);
            $stmt->execute();
            $stmt->close();
            $success = "Profile color updated successfully!";
            $user['profileColor'] = $newColor;
        } else {
            $errors[] = "Invalid color selected";
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (!password_verify($currentPassword, $user['passwordHash'])) {
            $errors[] = "Current password is incorrect";
        } else {
            $passwordErrors = validatePassword($newPassword);
            if (!empty($passwordErrors)) {
                $errors = array_merge($errors, $passwordErrors);
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = "New passwords do not match";
            }
            
            if (empty($errors)) {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE User SET passwordHash = ? WHERE userID = ?");
                $userID = $user['userID'];
                $stmt->bind_param("si", $newHash, $userID);
                $stmt->execute();
                $stmt->close();
                $success = "Password changed successfully!";
            }
        }
    }
}

$colors = getProfileColors();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Bip</title>
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <div class="settings-container">
        <div class="settings-header">
            <a href="/public/dashboard.php" class="back-button">← Back to Dashboard</a>
            <h1>Settings</h1>
        </div>
        
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
            </div>
        <?php endif; ?>
        
        <div class="settings-content">
            <!-- Profile Section -->
            <div class="settings-section">
                <h2>Profile</h2>
                <div class="profile-info">
                    <div class="profile-circle-large" style="background-color: <?= e($user['profileColor']) ?>"></div>
                    <div>
                        <p><strong>Username:</strong> <?= e($user['username']) ?></p>
                        <p><strong>Email:</strong> <?= e($user['email']) ?></p>
                        <p><strong>Member since:</strong> <?= date('F j, Y', strtotime($user['createdAt'])) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Change Profile Color -->
            <div class="settings-section">
                <h2>Profile Color</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_color">
                    <div class="form-group">
                        <label>Select a new profile color:</label>
                        <div class="color-picker">
                            <?php foreach ($colors as $color): ?>
                                <label class="color-option">
                                    <input type="radio" name="profile_color" value="<?= $color ?>" 
                                           <?= $user['profileColor'] === $color ? 'checked' : '' ?>>
                                    <span class="color-circle" style="background-color: <?= $color ?>"></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Color</button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="settings-section">
                <h2>Change Password</h2>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required>
                        <small>At least 8 characters with uppercase, lowercase, and digit</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </form>
            </div>
            
            <!-- Account Status -->
            <?php if ($user['accountStatus'] !== 'ACTIVE'): ?>
                <div class="settings-section">
                    <div class="alert alert-warning">
                        <p><strong>Account Status:</strong> <?= e($user['accountStatus']) ?></p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
