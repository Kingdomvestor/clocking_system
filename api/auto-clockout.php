<?php
require_once __DIR__ . '/../include/config.php';

// This endpoint is called by a cron job at 17:00 daily (5pm WAT)
// Crontab example: 0 17 * * * curl -s http://localhost/clocking_system/api/auto-clockout.php?key=YOUR_SECRET_KEY
//
// Alternatively call it from admin panel with the same key.

$secretKey = 'sbs-auto-clock-2025'; // ← Change this to a strong secret
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
    $in  = new DateTime($r['clock_in']);
    $out = new DateTime($now);
    $diff = $in->diff($out);
    $hoursWorked = round(($diff->h * 60 + $diff->i) / 60, 2);

    $db->prepare('
        UPDATE attendance
        SET clock_out = ?, hours_worked = ?, auto_clocked_out = 1,
            notes = "Auto clocked out at 5:00 PM by system"
        WHERE id = ?
    ')->execute([$now, $hoursWorked, $r['id']]);

    $processed++;
}

jsonResponse([
    'success'   => true,
    'processed' => $processed,
    'message'   => "$processed staff member(s) auto clocked out.",
    'timestamp' => $now,
]);
