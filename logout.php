<?php
require_once __DIR__ . '/includes/auth.php';
session_start_safe();
session_unset();
session_destroy();
header('Location: login.php');
exit;
