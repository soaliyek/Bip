<?php
require_once '../includes/session.php';

if (isLoggedIn()) {
    header('Location: ../public/pages/dashboard.php');
} else {
    header('Location: ../authentication/login.php');
}
exit;

?>