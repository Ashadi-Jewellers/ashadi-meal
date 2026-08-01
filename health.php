<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/config.local.php';

header('Content-Type: application/json');

const APP_NAME = 'meal';

$providedToken = $_SERVER['HTTP_X_HEALTH_TOKEN'] ?? ($_GET['token'] ?? null);
if (!defined('HEALTH_CHECK_TOKEN') || $providedToken !== HEALTH_CHECK_TOKEN) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$checks = [];
$overallOk = true;

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception('Connection failed');
    }
    $result = $conn->query("SELECT COUNT(*) as c FROM meal_records LIMIT 1");
    if (!$result) {
        throw new Exception('Query failed');
    }
    $result->fetch_assoc();
    $conn->close();
    $checks['database'] = ['ok' => true, 'message' => 'Connected and queried meal_records table successfully'];
} catch (Throwable $e) {
    $checks['database'] = ['ok' => false, 'message' => 'Database check failed'];
    $overallOk = false;
}

$checks['uploads'] = ['ok' => true, 'message' => 'N/A - meal app has no uploads directory'];

http_response_code($overallOk ? 200 : 503);
echo json_encode([
    'status' => $overallOk ? 'ok' : 'error',
    'app' => APP_NAME,
    'timestamp' => date('c'),
    'checks' => $checks,
], JSON_PRETTY_PRINT);
