<?php
require_once __DIR__ . '/../include/config.php';

// This endpoint is called by a cron job at 17:00 daily (5pm WAT)
// Crontab: 0 17 * * * curl -s https://soteria-clocking-system.onrender.com/api/auto-clockout.php?key=YOUR_SECRET

$secretKey   = getenv('CRON_SECRET') ?: 'sbs-auto-clock-2025';
$providedKey = trim($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? '');

if ($providedKey !== $secretKey) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

$db    = db();
$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');

// Find all staff still clocked in today
$open = $db->prepare('
    SELECT id, staff_id, clock_in
    FROM attendance
    WHERE att_date = ? AND clock_out IS NULL
');
$open->execute([$today]);
$openRecords = $open->fetchAll();

$processed = 0;

foreach ($openRecords as $r) {
    $in          = new DateTime($r['clock_in']);
    $out         = new DateTime($now);
    $diff        = $in->diff($out);
    $hoursWorked = round(($diff->h * 60 + $diff->i) / 60, 2);

    $db->prepa
