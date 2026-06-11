<?php
require_once __DIR__ . '/../include/config.php';

// This endpoint is called from the MOBILE page (not admin)
// It validates the QR token and returns a WebAuthn challenge

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body  = json_decode(file_get_contents('php://input'), true);
$token = trim($body['token'] ?? '');
$type  = trim($body['type']  ?? 'get'); // 'create' for register, 'get' for verify

if (!$token) {
    jsonResponse(['error' => 'Token required'], 400);
}

// Validate token
$stmt = db()->prepare('
    SELECT qt.*, e.full_name
    FROM qr_tokens qt
    JOIN employee e ON qt.staff_id = e.staff_id
    WHERE qt.token = ? AND qt.used = 0 AND qt.expires_at > NOW()
');
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonResponse(['error' => 'QR code is invalid or has expired. Please ask admin to generate a new one.'], 410);
}

// Generate a cryptographic challenge
$challenge = base64_encode(random_bytes(32));

// Store in session keyed by token (not by user login — mobile user has no session)
$_SESSION['webauthn_challenge_' . $token] = $challenge;
$_SESSION['webauthn_token_' . $token]     = $token;

// For registration, return the user handle
$userHandle = base64_encode($qr['staff_id']);

// For authentication, return existing credential IDs for this staff member
$allowCredentials = [];
if ($type === 'get') {
    $creds = db()->prepare('SELECT credential_id FROM webauthn_credentials WHERE staff_id = ?');
    $creds->execute([$qr['staff_id']]);
    $allowCredentials = array_map(fn($c) => [
        'type' => 'public-key',
        'id'   => $c['credential_id'],
    ], $creds->fetchAll());
}

jsonResponse([
    'challenge'         => $challenge,
    'staff_id'          => $qr['staff_id'],
    'staff_name'        => $qr['full_name'],
    'action'            => $qr['action'],
    'user_handle'       => $userHandle,
    'allow_credentials' => $allowCredentials,
    'rp_id'             => parse_url(SITE_URL, PHP_URL_HOST),
    'rp_name'           => SITE_NAME,
]);
