<?php
require_once 'session.php';

if (isLoggedIn()) {
    header('Location: ../public/pages/dashboard.php');
} else {
    header('Location: ../authentication/login.php');
}
exit;

?>