<?php
require_once __DIR__ . '/../include/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$token     = trim($body['token']         ?? '');
$credId    = trim($body['credential_id'] ?? '');
$publicKey = trim($body['public_key']    ?? '');
$sigCount  = (int)($body['sign_count']   ?? 0);

if (!$token || !$credId || !$publicKey) {
    jsonResponse(['error' => 'token, credential_id, and public_key are required'], 400);
}

// Validate QR token
$stmt = db()->prepare("
    SELECT qt.*, e.full_name
    FROM qr_tokens qt
    JOIN employee e ON qt.staff_id = e.staff_id
    WHERE qt.token = ? AND qt.action = 'register' AND qt.used = 0 AND qt.expires_at > NOW()
");
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonResponse(['error' => 'Registration QR is invalid or expired. Ask the admin to generate a new one.'], 410);
}

// Prevent duplicate credential ID
$dup = db()->prepare('SELECT id FROM webauthn_credentials WHERE credential_id = ?');
$dup->execute([$credId]);
if ($dup->fetch()) {
    db()->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);
    jsonResponse(['error' => 'This device is already registered. Reset fingerprint from admin panel first.'], 409);
}

// Delete any existing fingerprints for this staff
db()->prepare('DELETE FROM webauthn_credentials WHERE staff_id = ?')->execute([$qr['staff_id']]);

// Save new credential
db()->prepare('
    INSERT INTO webauthn_credentials (staff_id, credential_id, public_key, sign_count)
    VALUES (?,?,?,?)
')->execute([$qr['staff_id'], $credId, $publicKey, $sigCount]);

// Mark token used
db()->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);

jsonResponse([
    'success'    => true,
    'staff_id'   => $qr['staff_id'],
    'staff_name' => $qr['full_name'],
    'message'    => 'Fingerprint registered successfully.',
]);
