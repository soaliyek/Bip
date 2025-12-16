<?php
require_once __DIR__ . '/../includes/session.php';

logoutUser();
header('Location: /authentication/login.php');
exit;
