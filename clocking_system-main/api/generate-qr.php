<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin(); // Only admin can generate QR codes

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body     = json_decode(file_get_contents('php://input'), true);
$staff_id = trim($body['staff_id'] ?? '');
$action   = trim($body['action']   ?? '');

// Validate action
if (!in_array($action, ['clock_in', 'clock_out', 'register'])) {
    jsonResponse(['error' => 'Invalid action. Must be clock_in, clock_out, or register.'], 400);
}

// Validate staff exists and is active
$emp = db()->prepare('SELECT staff_id, full_name FROM employee WHERE staff_id = ? AND is_active = True');
$emp->execute([$staff_id]);
$emp = $emp->fetch();

if (!$emp) {
    jsonResponse(['error' => 'Staff member not found or inactive.'], 404);
}

// Business logic checks
$today = date('Y-m-d');

if ($action === 'clock_in') {
    // Check not already clocked in today
    $existing = db()->prepare('SELECT id FROM attendance WHERE staff_id = ? AND att_date = ? AND clock_out IS NULL');
    $existing->execute([$staff_id, $today]);
    if ($existing->fetch()) {
        jsonResponse(['error' => $emp['full_name'] . ' is already clocked in today.'], 409);
    }
}

if ($action === 'clock_out') {
    // Must be clocked in to clock out
    $existing = db()->prepare('SELECT id FROM attendance WHERE staff_id = ? AND att_date = ? AND clock_out IS NULL');
    $existing->execute([$staff_id, $today]);
    if (!$existing->fetch()) {
        jsonResponse(['error' => $emp['full_name'] . ' has no active clock-in today.'], 409);
    }
}

if ($action === 'register') {
    // Check if already registered — admin can still regenerate (for re-registration)
    // No block, just allow
}

// Expire any existing unused tokens for this staff+action
db()->prepare('UPDATE qr_tokens SET used = 1 WHERE staff_id = ? AND action = ? AND used = 0')
    ->execute([$staff_id, $action]);

// Generate token
$token     = bin2hex(random_bytes(32));
$expiresAt = date('Y-m-d H:i:s', time() + QR_EXPIRY_SECONDS);

db()->prepare('INSERT INTO qr_tokens (token, staff_id, action, expires_at) VALUES (?,?,?,?)')
    ->execute([$token, $staff_id, $action, $expiresAt]);

jsonResponse([
    'token'      => $token,
    'staff_id'   => $staff_id,
    'staff_name' => $emp['full_name'],
    'action'     => $action,
    'expires_at' => $expiresAt,
    'expires_in' => QR_EXPIRY_SECONDS,
]);
