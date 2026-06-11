<?php
require_once __DIR__ . '/../include/config.php';

// TEMP DEBUG
error_log('POST data: ' . print_r($_POST, true));
error_log('DB test: ' . (db() ? 'connected' : 'failed'));

if (isAdminLoggedIn()) {
    header('Location: ' . SITE_URL . '/admin/terminal.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = db()->prepare('SELECT * FROM admin WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        
            if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id']   = $admin['id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            db()->prepare('UPDATE admin SET last_login = NOW() WHERE id = ?')
               ->execute([$admin['id']]);
            header('Location: ' . SITE_URL . '/admin/terminal.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent: #1e40af;
            --accent-light: #3b63d4;
            --dark:   #060912;
            --card:   #0d1117;
            --border: #1a2030;
            --text:   #e2e8f0;
            --muted:  #4a5568;
            --input:  #111827;
            --green:  #22c55e;
            --red:    #ef4444;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 30% 20%, rgba(30,64,175,0.10) 0%, transparent 60%),
                radial-gradient(ellipse at 70% 80%, rgba(30,64,175,0.06) 0%, transparent 60%);
            pointer-events: none;
        }

        .container {
            width: 100%;
            max-width: 400px;
            position: relative;
            z-index: 1;
            animation: fadeUp 0.4s ease;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .top { text-align: center; margin-bottom: 32px; }

        .shield {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--accent), #1e3a8a);
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 8px 32px rgba(30,64,175,0.35);
        }

        .shield svg { width: 30px; height: 30px; fill: #fff; }

        h1 { font-family: 'Syne', sans-serif; font-size: 26px; font-weight: 700; margin-bottom: 6px; }
        .sub { font-size: 13px; color: var(--muted); }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
        }

        .error-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 13px;
            color: #f87171;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .field { margin-bottom: 18px; }

        label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--muted);
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            background: var(--input);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 14px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(30,64,175,0.12);
        }

        input::placeholder { color: var(--muted); }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 4px;
            transition: background 0.2s, transform 0.1s;
            letter-spacing: 0.5px;
        }

        .btn:hover  { background: var(--accent-light); }
        .btn:active { transform: scale(0.98); }
    </style>
</head>
<body>
<div class="container">
    <div class="top">
        <div class="shield">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
            </svg>
        </div>
        <h1>Admin Panel</h1>
        <p class="sub">Restricted access — authorized personnel only</p>
    </div>

    <div class="card">
        <?php if ($error): ?>
            <div class="error-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f87171" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label>Username</label>
                <input type="text" name="username" placeholder="admin" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn">Access Admin Panel →</button>
        </form>
    </div>
</div>
</body>
</html>
