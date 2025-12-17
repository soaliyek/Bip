<?php
require_once __DIR__ . '/includes/session.php';

if (isLoggedIn()) {
    header('Location: public/pages/app.php');
} else {
    header('Location: authentication/login.php');
}
exit;
