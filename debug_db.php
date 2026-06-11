<?php
// TEMPORARY DEBUG FILE — DELETE AFTER USE
$host = getenv('DB_HOST');
$port = getenv('DB_PORT');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$name = getenv('DB_NAME');
$ssl  = getenv('DB_SSLMODE');

echo "HOST: $host\n";
echo "PORT: $port\n";
echo "USER: $user\n";
echo "NAME: $name\n";
echo "SSL:  $ssl\n";
echo "PASS SET: " . (!empty($pass) ? 'YES (' . strlen($pass) . ' chars)' : 'NO') . "\n\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=$ssl";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "CONNECTION: SUCCESS\n";
    $row = $pdo->query("SELECT current_user, now()")->fetch();
    echo "DB USER: " . $row['current_user'] . "\n";
    echo "DB TIME: " . $row['now'] . "\n";
} catch (PDOException $e) {
    echo "CONNECTION FAILED: " . $e->getMessage() . "\n";
}

// Check extensions
echo "\npdo_pgsql loaded: " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "\n";
echo "pgsql loaded: "     . (extension_loaded('pgsql')     ? 'YES' : 'NO') . "\n";
