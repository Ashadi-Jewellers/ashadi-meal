<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
if (!is_logged_in()) { http_response_code(403); echo '[]'; exit; }

header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) { echo '[]'; exit; }

$conn = db_connect();
$like = '%' . $conn->real_escape_string($q) . '%';

// Search by Name OR EmpID (display number), return the auto-increment id for storage
$sql = "SELECT id, EmpID, Name, Section, Company
        FROM employees
        WHERE (Name LIKE '$like' OR EmpID LIKE '$like')
        AND PresentEmployee = 'Yes'
        ORDER BY Name
        LIMIT 25";

$res = $conn->query($sql);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) $rows[] = $r;
}
$conn->close();
echo json_encode($rows);
