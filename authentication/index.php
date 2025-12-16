<?php
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    header('Location: ../public/pages/dashboard.php');
} else {
    header('Location: login.php');
}
exit;

?>