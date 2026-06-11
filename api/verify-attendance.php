<?php
require_once __DIR__ . '/../include/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true);
$token    = trim($body['token']       ?? '');
$credId   = trim($body['credential_id'] ?? '');
$sigCount = (int)($body['sign_count']   ?? 0);

if (!$token) {
    jsonResponse(['error' => 'Token required'], 400);
}

// Re-validate token (must still be valid and unused)
$stmt = db()->prepare('
    SELECT qt.*, e.full_name, e.department
    FROM qr_tokens qt
    JOIN employee e ON qt.staff_id = e.staff_id
    WHERE qt.token = ? AND qt.used = 0 AND qt.expires_at > NOW()
');
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonResponse(['error' => 'Token expired or already used. Ask admin for a new QR code.'], 410);
}

// Verify the credential belongs to this staff member
$credStmt = db()->prepare('SELECT * FROM webauthn_credentials WHERE staff_id = ? AND credential_id = ?');
$credStmt->execute([$qr['staff_id'], $credId]);
$cred = $credStmt->fetch();

if (!$cred) {
    jsonResponse(['error' => 'Fingerprint not recognised for this staff member.'], 403);
}

// Update sign count (replay attack prevention)
if ($sigCount > 0 && $sigCount <= $cred['sign_count']) {
    jsonResponse(['error' => 'Invalid authentication: possible replay attack detected.'], 403);
}
db()->prepare('UPDATE webauthn_credentials SET sign_count = ? WHERE id = ?')
    ->execute([$sigCount, $cred['id']]);

$today = date('Y-m-d');
$now   = date('Y-m-d H:i:s');
$db    = db();

// Handle action
if ($qr['action'] === 'clock_in') {
    // Check not already clocked in
    $check = $db->prepare('SELECT id FROM attendance WHERE staff_id = ? AND att_date = ? AND clock_out IS NULL');
    $check->execute([$qr['staff_id'], $today]);
    if ($check->fetch()) {
        jsonResponse(['error' => 'Already clocked in today.'], 409);
    }

    $db->prepare('INSERT INTO attendance (staff_id, clock_in, att_date) VALUES (?,?,?)')
       ->execute([$qr['staff_id'], $now, $today]);

    // Mark token used
    $db->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);

    jsonResponse([
        'success'    => true,
        'action'     => 'clock_in',
        'staff_name' => $qr['full_name'],
        'time'       => date('H:i'),
        'message'    => 'Clocked in successfully.',
    ]);
}

if ($qr['action'] === 'clock_out') {
    // Find the open record
    $record = $db->prepare('SELECT id, clock_in FROM attendance WHERE staff_id = ? AND att_date = ? AND clock_out IS NULL');
    $record->execute([$qr['staff_id'], $today]);
    $record = $record->fetch();

    if (!$record) {
        jsonResponse(['error' => 'No active clock-in found for today.'], 409);
    }

    // Calculate hours worked
    $inTime     = new DateTime($record['clock_in']);
    $outTime    = new DateTime($now);
    $diff       = $inTime->diff($outTime);
    $hoursWorked = round(($diff->h * 60 + $diff->i) / 60, 2);

    $db->prepare('UPDATE attendance SET clock_out = ?, hours_worked = ? WHERE id = ?')
       ->execute([$now, $hoursWorked, $record['id']]);

    // Mark token used
    $db->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);

    jsonResponse([
        'success'      => true,
        'action'       => 'clock_out',
        'staff_name'   => $qr['full_name'],
        'time'         => date('H:i'),
        'hours_worked' => $hoursWorked,
        'message'      => 'Clocked out successfully.',
    ]);
}

jsonResponse(['error' => 'Unexpected action type: ' . $qr['action']], 400);
