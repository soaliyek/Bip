<?php
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

requireAdmin();
updatePresence();

$conn = getDB();
$user = getCurrentUser();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'handle_report') {
        $reportID = $_POST['report_id'] ?? null;
        $status = $_POST['status'] ?? null;
        $comment = trim($_POST['comment'] ?? '');
        
        if ($reportID && in_array($status, ['CONFIRMED', 'DISCARDED'])) {
            $stmt = $conn->prepare("
                UPDATE Report 
                SET status = ?, handledByAdminID = ?, handledAt = NOW(), adminComment = ? 
                WHERE reportID = ?
            ");
            $adminID = $user['userID'];
            $stmt->bind_param("sisi", $status, $adminID, $comment, $reportID);
            $stmt->execute();
            $stmt->close();
            $success = "Report handled successfully";
        }
    } elseif ($action === 'ban_user') {
        $targetUserID = $_POST['target_user_id'] ?? null;
        $penaltyType = $_POST['penalty_type'] ?? 'WARNING';
        $reason = trim($_POST['reason'] ?? '');
        
        if ($targetUserID && $reason) {
            $conn->begin_transaction();
            try {
                // Insert penalty
                $stmt = $conn->prepare("
                    INSERT INTO UserPenalty (targetUserID, adminUserID, penaltyType, reason) 
                    VALUES (?, ?, ?, ?)
                ");
                $adminID = $user['userID'];
                $stmt->bind_param("iiss", $targetUserID, $adminID, $penaltyType, $reason);
                $stmt->execute();
                $stmt->close();
                
                // Update user status
                if ($penaltyType === 'PERMA_BAN') {
                    $newStatus = 'BANNED';
                } elseif ($penaltyType === 'WARNING') {
                    $newStatus = 'WARNED';
                } else {
                    $newStatus = 'WARNED';
                }
                
                $stmt = $conn->prepare("UPDATE User SET accountStatus = ? WHERE userID = ?");
                $stmt->bind_param("si", $newStatus, $targetUserID);
                $stmt->execute();
                $stmt->close();
                
                $conn->commit();
                $success = "Penalty applied successfully";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Failed to apply penalty";
            }
        }
    }
}

// Get pending reports
$stmt = $conn->prepare("
    SELECT 
        r.*,
        m.content as messageContent,
        m.conversationID,
        reporter.username as reporterUsername,
        sender.username as senderUsername,
        sender.userID as senderUserID
    FROM Report r
    JOIN Message m ON r.messageID = m.messageID
    JOIN User reporter ON r.reporterUserID = reporter.userID
    LEFT JOIN User sender ON m.senderUserID = sender.userID
    WHERE r.status = 'PENDING'
    ORDER BY r.createdAt DESC
");
$stmt->execute();
$result = $stmt->get_result();
$pendingReports = [];
while ($row = $result->fetch_assoc()) {
    $pendingReports[] = $row;
}
$stmt->close();

// Get statistics
$result = $conn->query("SELECT COUNT(*) as count FROM User");
$totalUsers = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM User WHERE accountStatus = 'ACTIVE'");
$activeUsers = $result->fetch_assoc()['count'];

$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM User u
    JOIN UserStatus us ON u.userID = us.userID
    WHERE us.isOnline = 1 AND TIMESTAMPDIFF(SECOND, us.lastSeenAt, NOW()) <= 180
");
$onlineUsers = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM Conversation");
$totalConversations = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM Report WHERE status = 'PENDING'");
$pendingReportsCount = $result->fetch_assoc()['count'];

$result = $conn->query("SELECT COUNT(*) as count FROM Report WHERE status = 'CONFIRMED'");
$confirmedReportsCount = $result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Bip</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-container {
            min-height: 100vh;
            background: white;
            padding: 20px;
        }
        .admin-header {
            max-width: 1200px;
            margin: 0 auto 30px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .admin-stats {
            max-width: 1200px;
            margin: 0 auto 30px auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .stat-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #667eea;
        }
        .stat-label {
            color: #666;
            margin-top: 5px;
        }
        .admin-content {
            max-width: 1200px;
            margin: 0 auto;
        }
        .report-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
        }
        .report-meta {
            font-size: 14px;
            color: #666;
        }
        .report-flag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            background: #fee;
            color: #c33;
            font-size: 12px;
            font-weight: 500;
        }
        .report-message {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 3px solid #ff6b6b;
        }
        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <h1>Admin Panel</h1>
            <a href="dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success" style="max-width: 1200px; margin: 0 auto 20px auto;">
                <p><?= e($success) ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error" style="max-width: 1200px; margin: 0 auto 20px auto;">
                <p><?= e($error) ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="admin-stats">
            <div class="stat-card">
                <div class="stat-value"><?= $totalUsers ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $activeUsers ?></div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $onlineUsers ?></div>
                <div class="stat-label">Online Now</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $totalConversations ?></div>
                <div class="stat-label">Total Conversations</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $pendingReportsCount ?></div>
                <div class="stat-label">Pending Reports</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $confirmedReportsCount ?></div>
                <div class="stat-label">Confirmed Reports</div>
            </div>
        </div>
        
        <!-- Reports -->
        <div class="admin-content">
            <h2 style="margin-bottom: 20px;">Pending Reports</h2>
            
            <?php if (empty($pendingReports)): ?>
                <p style="color: #666;">No pending reports.</p>
            <?php else: ?>
                <?php foreach ($pendingReports as $report): ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div>
                                <span class="report-flag"><?= e($report['flagType']) ?></span>
                                <span class="report-meta">
                                    Reported by <strong><?= e($report['reporterUsername']) ?></strong>
                                    on <?= date('M j, Y g:i A', strtotime($report['createdAt'])) ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="report-message">
                            <strong>Message from <?= e($report['senderUsername'] ?? 'System') ?>:</strong>
                            <p><?= nl2br(e($report['messageContent'])) ?></p>
                        </div>
                        
                        <?php if ($report['reason']): ?>
                            <div style="margin: 15px 0;">
                                <strong>Reporter's explanation:</strong>
                                <p><?= nl2br(e($report['reason'])) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="handle_report">
                            <input type="hidden" name="report_id" value="<?= $report['reportID'] ?>">
                            
                            <div class="form-group">
                                <label>Admin Comment:</label>
                                <textarea name="comment" rows="2" style="width: 100%; padding: 10px; border: 1px solid #e0e0e0; border-radius: 8px;"></textarea>
                            </div>
                            
                            <div class="report-actions">
                                <button type="submit" name="status" value="CONFIRMED" class="btn" style="background: #28a745; color: white;">
                                    Confirm Report
                                </button>
                                <button type="submit" name="status" value="DISCARDED" class="btn" style="background: #6c757d; color: white;">
                                    Discard Report
                                </button>
                                
                                <?php if ($report['senderUserID']): ?>
                                    <button type="button" class="btn" style="background: #dc3545; color: white;" 
                                            onclick="showBanForm(<?= $report['senderUserID'] ?>, '<?= e($report['senderUsername']) ?>')">
                                        Ban User
                                    </button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Ban User Modal -->
    <div id="banModal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="document.getElementById('banModal').style.display='none'">&times;</span>
            <h2>Apply Penalty</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="ban_user">
                <input type="hidden" name="target_user_id" id="banUserID">
                
                <p>Applying penalty to: <strong id="banUsername"></strong></p>
                
                <div class="form-group">
                    <label>Penalty Type:</label>
                    <select name="penalty_type" required>
                        <option value="WARNING">Warning</option>
                        <option value="TEMP_BAN">Temporary Ban</option>
                        <option value="PERMA_BAN">Permanent Ban</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Reason:</label>
                    <textarea name="reason" rows="3" required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Apply Penalty</button>
            </form>
        </div>
    </div>
    
    <script>
        function showBanForm(userID, username) {
            document.getElementById('banUserID').value = userID;
            document.getElementById('banUsername').textContent = username;
            document.getElementById('banModal').style.display = 'block';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('banModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
