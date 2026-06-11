<?php
// Registration is now handled entirely by mobile/verify.php
// This file redirects for backward compatibility if anything links here directly
require_once __DIR__ . '/../include/config.php';

$token = trim($_GET['token'] ?? '');
if ($token) {
    header('Location: ' . SITE_URL . '/mobile/verify.php?token=' . urlencode($token));
} else {
    header('Location: ' . SITE_URL . '/mobile/verify.php');
}
exit;
