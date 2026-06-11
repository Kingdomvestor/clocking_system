<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true);
$id       = (int)($body['id']        ?? 0);
$date     = trim($body['date']       ?? '');
$clockIn  = trim($body['clock_in']   ?? '');
$clockOut = trim($body['clock_out']  ?? '');
$notes    = trim($body['notes']      ?? '');

if (!$id || !$date || !$clockIn) {
    jsonResponse(['error' => 'id, date, and clock_in are required'], 400);
}

// Validate formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(['error' => 'Invalid date format'], 400);
}
if (!preg_match('/^\d{2}:\d{2}$/', $clockIn)) {
    jsonResponse(['error' => 'Invalid clock_in format (use HH:MM)'], 400);
}
if ($clockOut && !preg_match('/^\d{2}:\d{2}$/', $clockOut)) {
    jsonResponse(['error' => 'Invalid clock_out format (use HH:MM)'], 400);
}

// Verify record exists
$record = db()->prepare('SELECT id FROM attendance WHERE id = ?');
$record->execute([$id]);
if (!$record->fetch()) {
    jsonResponse(['error' => 'Record not found'], 404);
}

$clockInDt  = $date . ' ' . $clockIn . ':00';
$clockOutDt = $clockOut ? $date . ' ' . $clockOut . ':00' : null;
$hoursWorked = null;

// Calculate hours if both times present
if ($clockOutDt) {
    $in  = new DateTime($clockInDt);
    $out = new DateTime($clockOutDt);
    if ($out <= $in) {
        jsonResponse(['error' => 'Clock out must be after clock in'], 400);
    }
    $diff = $in->diff($out);
    $hoursWorked = round(($diff->h * 60 + $diff->i) / 60, 2);
}

db()->prepare('
    UPDATE attendance
    SET att_date = ?, clock_in = ?, clock_out = ?, hours_worked = ?,
        notes = ?, auto_clocked_out = 0
    WHERE id = ?
')->execute([$date, $clockInDt, $clockOutDt, $hoursWorked, $notes ?: null, $id]);

jsonResponse([
    'success'      => true,
    'hours_worked' => $hoursWorked,
    'message'      => 'Record updated.',
]);
