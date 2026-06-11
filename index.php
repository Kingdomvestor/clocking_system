<?php
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'];

// Redirect to the admin dashboard
header("Location: {$scheme}://{$host}/clocking_system/admin/dashboard.php");
exit;