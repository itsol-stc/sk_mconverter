<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config/dbconn_config_saiattendance.php';

header('Content-Type: application/json');

if (!isset($_GET['ym']) || !preg_match('/^\d{6}$/', $_GET['ym'])) {
    echo json_encode(['error' => 'invalid parameter']);
    exit;
}

$ym = $_GET['ym'];
$year = substr($ym, 0, 4);
$month = substr($ym, 4, 2);

$stmt = $pdo_sai->prepare("
    SELECT payment_date
    FROM public.salary_payments
    WHERE target_year = :year AND target_month = :month
    LIMIT 1
");
$stmt->execute([
    ':year' => (int)$year,
    ':month' => (int)$month
]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo json_encode(['payment_date' => $row['payment_date']]);
} else {
    echo json_encode(['payment_date' => null]);
}
