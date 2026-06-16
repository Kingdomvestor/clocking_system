<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin(); // Only admin terminal polls this

$token = trim($_GET['token'] ?? '');

if (!$token) {
    jsonResponse(['error' => 'Token required'], 400);
}

// Look up the token
$stmt = db()->prepare('
    SELECT qt.*, e.full_name
    FROM qr_tokens qt
    JOIN employee e ON qt.staff_id = e.staff_id
    WHERE qt.token = ?
');
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonResponse(['confirmed' => false, 'expired' => true]);
}

// Token expired
if (strtotime($qr['expires_at']) < time()) {
    jsonResponse(['confirmed' => false, 'expired' => true]);
}

// Token already used = action confirmed
if ($qr['used']) {
    $time = date('H:i');

    // Try to get the actual time from the attendance record (clock_in/out)
    if ($qr['action'] === 'clock_in') {
        $rec = db()->prepare('SELECT clock_in FROM attendance WHERE staff_id=? AND att_date=CURRENT_DATE');
        $rec->execute([$qr['staff_id']]);
        $rec = $rec->fetch();
        if ($rec) $time = date('H:i', strtotime($rec['clock_in']));
    } elseif ($qr['action'] === 'clock_out') {
        $rec = db()->prepare('SELECT clock_out FROM attendance WHERE staff_id=? AND att_date=CURRENT_DATE AND clock_out IS NOT NULL ORDER BY clock_out DESC LIMIT 1');
        $rec->execute([$qr['staff_id']]);
        $rec = $rec->fetch();
        if ($rec) $time = date('H:i', strtotime($rec['clock_out']));
    }

    jsonResponse([
        'confirmed'  => true,
        'action'     => $qr['action'],
        'staff_name' => $qr['full_name'],
        'time'       => $time,
    ]);
}

// Still pending
jsonResponse([
    'confirmed' => false,
    'expired'   => false,
    'expires_at'=> $qr['expires_at'],
]);
