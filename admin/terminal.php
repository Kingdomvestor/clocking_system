<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

$db    = db();
$today = date('Y-m-d');

// Stats
$totalActive = $db->query('SELECT COUNT(*) FROM employee WHERE is_active=True')->fetchColumn();

$stAttended = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=?');
$stAttended->execute([$today]);
$countAttended = (int)$stAttended->fetchColumn();

$stIn = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NULL');
$stIn->execute([$today]);
$countIn = (int)$stIn->fetchColumn();

$stOut = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NOT NULL');
$stOut->execute([$today]);
$countOut = (int)$stOut->fetchColumn();

$countAbsent = $totalActive - $countAttended;

// All active staff with today's attendance + fingerprint status
$employees = $db->prepare("
    SELECT
        e.staff_id, e.full_name, e.department, e.designation,
        a.clock_in, a.clock_out, a.hours_worked, a.auto_clocked_out,
        (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.staff_id = e.staff_id) AS has_fp
    FROM employee e
    LEFT JOIN attendance a ON a.staff_id = e.staff_id AND a.att_date = ?
    WHERE e.is_active = True
    ORDER BY e.full_name ASC
");
$employees->execute([$today]);
$employees = $employees->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --accent:  #1e40af;
            --accent2: #3b63d4;
            --dark:    #060912;
            --sidebar: #0a0f1e;
            --card:    #0d1117;
            --card2:   #0a0f1e;
            --border:  #1a2030;
            --text:    #e2e8f0;
            --muted:   #4a5568;
            --green:   #22c55e;
            --amber:   #f59e0b;
            --red:     #ef4444;
            /* Old terminal overlay vars */
            --ot-red:    #C1121F;
            --ot-dark:   #0a0a0f;
            --ot-card:   #13131a;
            --ot-border: #1e1e2e;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
            display: grid;
            grid-template-columns: 220px 1fr;
        }

        /* ── Sidebar ── */
        .sidebar {
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            padding: 24px 16px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-logo {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 14px;
            padding: 12px;
            margin-bottom: 24px;
            color: #6b9fff;
            letter-spacing: .5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 14px;
            color: var(--muted);
            text-decoration: none;
            transition: background .15s, color .15s;
        }
        .nav-item:hover, .nav-item.active { background: rgba(30,64,175,.12); color: var(--text); }
        .nav-item.active { color: #6b9fff; font-weight: 500; }
        .sidebar-spacer { flex: 1; }
        .sidebar-user { font-size: 12px; color: var(--muted); padding: 0 14px; margin-bottom: 8px; }
        .nav-item.danger:hover { background: rgba(239,68,68,.1); color: #f87171; }

        /* ── Main ── */
        .main { display: flex; flex-direction: column; overflow-x: hidden; min-height: 100vh; }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 32px;
            border-bottom: 1px solid var(--border);
            background: var(--card);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .topbar h1 { font-family: 'Syne', sans-serif; font-size: 20px; font-weight: 700; }
        .topbar-right { display: flex; align-items: center; gap: 20px; }
        .live-badge { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--green); font-weight: 600; }
        .live-dot { width: 8px; height: 8px; background: var(--green); border-radius: 50%; animation: blink 1.5s ease-in-out infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.3} }
        #liveTime { font-family: 'JetBrains Mono', monospace; font-size: 14px; color: var(--muted); }

        .content { padding: 28px 32px; display: flex; flex-direction: column; gap: 24px; flex: 1; }

        /* ── Stats ── */
        .stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; }
        .stat-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 8px; }
        .stat-value { font-family: 'Syne', sans-serif; font-size: 32px; font-weight: 800; }
        .stat-value.blue  { color: #6b9fff; }
        .stat-value.green { color: var(--green); }
        .stat-value.amber { color: var(--amber); }
        .stat-value.muted { color: var(--muted); }

        /* ── Section header ── */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .section-header h2 { font-family: 'Syne', sans-serif; font-size: 16px; font-weight: 700; }

        /* ── Search ── */
        .search-wrap { position: relative; width: 260px; }
        .search-input {
            width: 100%;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 16px 10px 38px;
            font-size: 13px;
            color: var(--text);
            font-family: 'DM Sans', sans-serif;
            outline: none;
            transition: border-color .2s;
        }
        .search-input:focus { border-color: var(--accent); }
        .search-input::placeholder { color: var(--muted); }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); pointer-events: none; }

        /* ── Staff Grid ── */
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
        }

        .staff-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: border-color .2s, transform .15s, background .2s;
            position: relative;
            overflow: hidden;
            user-select: none;
        }
        .staff-card:hover {
            border-color: var(--accent);
            background: rgba(30,64,175,.06);
            transform: translateY(-2px);
        }
        .staff-card:active { transform: scale(.98); }

        /* Status stripe on left edge */
        .staff-card::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            border-radius: 16px 0 0 16px;
            background: var(--border);
            transition: background .2s;
        }
        .staff-card.status-in::before    { background: var(--green); }
        .staff-card.status-out::before   { background: #6b9fff; }
        .staff-card.status-absent::before { background: var(--border); }

        .card-top { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }

        .card-av {
            width: 42px; height: 42px;
            border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 14px;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--accent), #1e3a8a);
            box-shadow: 0 4px 12px rgba(30,64,175,.25);
        }
        .staff-card.status-in  .card-av { background: linear-gradient(135deg, #166534, #15803d); box-shadow: 0 4px 12px rgba(34,197,94,.2); }
        .staff-card.status-out .card-av { background: linear-gradient(135deg, #1e3a8a, #1e40af); }

        .card-meta { flex: 1; min-width: 0; }
        .card-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card-dept { font-size: 11px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .card-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .pill-in     { background: rgba(34,197,94,.1);   color: var(--green); }
        .pill-out    { background: rgba(107,159,255,.1);  color: #6b9fff; }
        .pill-absent { background: rgba(74,85,104,.12);   color: var(--muted); }

        .fp-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--amber);
            title: 'No fingerprint';
            flex-shrink: 0;
        }
        .fp-dot.registered { background: var(--green); }

        .card-time {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: var(--muted);
            margin-top: 6px;
        }
        .card-time.green { color: var(--green); }

        /* Hidden cards */
        .staff-card.hidden { display: none; }

        /* ── Overlay ── */
        .overlay-bg {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(6,9,18,.88);
            backdrop-filter: blur(6px);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .overlay-bg.open { display: flex; }

        /* Overlay mimics old terminal style exactly */
        .overlay-shell {
            width: 100%;
            max-width: 820px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            animation: overlayIn .25s ease;
        }
        @keyframes overlayIn {
            from { opacity: 0; transform: translateY(20px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .ov-panel {
            background: var(--ot-card);
            border: 1px solid var(--ot-border);
            border-radius: 20px;
            padding: 28px;
            position: relative;
        }

        /* Close button */
        .ov-close {
            position: absolute;
            top: 16px; right: 16px;
            background: rgba(255,255,255,.06);
            border: 1px solid var(--ot-border);
            border-radius: 8px;
            width: 32px; height: 32px;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            color: var(--muted);
            font-size: 16px;
            transition: background .2s, color .2s;
            z-index: 2;
        }
        .ov-close:hover { background: rgba(239,68,68,.12); color: #f87171; border-color: rgba(239,68,68,.3); }

        /* QR side */
        .ov-qr { display: flex; flex-direction: column; align-items: center; text-align: center; }

        .ov-action-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 100px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .badge-in  { background: rgba(34,197,94,.1);   color: var(--green); border: 1px solid rgba(34,197,94,.25); }
        .badge-out { background: rgba(107,159,255,.1);  color: #6b9fff;     border: 1px solid rgba(107,159,255,.3); }
        .badge-reg { background: rgba(30,64,175,.12);   color: #6b9fff;     border: 1px solid rgba(30,64,175,.35); }

        .ov-hint { font-size: 13px; color: var(--muted); margin-bottom: 4px; }

        .ov-qr-wrap {
            width: 220px; height: 220px;
            background: #fff;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 16px 0;
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }
        .ov-qr-wrap.expired::after {
            content: 'EXPIRED';
            position: absolute;
            inset: 0;
            background: rgba(10,10,15,.88);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 20px;
            color: var(--ot-red);
            border-radius: 16px;
        }
        #ovQrCanvas { border-radius: 12px; overflow: hidden; }

        /* Timer ring — matches old terminal exactly */
        .ov-timer-ring {
            position: relative;
            width: 64px; height: 64px;
            margin: 6px 0;
        }
        .ov-timer-svg { transform: rotate(-90deg); }
        .ov-timer-track { fill: none; stroke: var(--ot-border); stroke-width: 4; }
        .ov-timer-fill {
            fill: none;
            stroke: var(--ot-red);
            stroke-width: 4;
            stroke-linecap: round;
            stroke-dasharray: 163;
            stroke-dashoffset: 0;
            transition: stroke-dashoffset 1s linear, stroke .3s;
        }
        .ov-timer-num {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 16px; font-weight: 500;
        }

        .ov-steps { font-size: 12px; color: var(--muted); line-height: 1.8; margin-top: 4px; }
        .ov-steps strong { color: var(--text); }

        /* Loading spinner for QR */
        .qr-loading {
            width: 40px; height: 40px;
            border: 3px solid var(--ot-border);
            border-top-color: var(--ot-red);
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Status side */
        .ov-status { display: flex; flex-direction: column; gap: 16px; }

        .ov-staff-header { display: flex; align-items: center; gap: 14px; margin-bottom: 4px; padding-right: 40px; }
        .ov-staff-av {
            width: 48px; height: 48px;
            border-radius: 13px;
            background: linear-gradient(135deg, var(--accent), #1e3a8a);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800; font-size: 16px;
            flex-shrink: 0;
            box-shadow: 0 4px 16px rgba(30,64,175,.3);
        }
        .ov-staff-name { font-family: 'Syne', sans-serif; font-weight: 700; font-size: 17px; }
        .ov-staff-dept { font-size: 12px; color: var(--muted); margin-top: 2px; }

        .ov-today-card {
            background: #0d0d15;
            border: 1px solid var(--ot-border);
            border-radius: 14px;
            padding: 18px;
        }
        .ov-today-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); margin-bottom: 4px; }
        .ov-today-value {
            font-family: 'JetBrains Mono', monospace;
            font-size: 22px; font-weight: 500;
            color: var(--text);
        }
        .ov-today-value.green { color: var(--green); }
        .ov-today-value.red   { color: #ff6b7a; }

        .ov-row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Notify box */
        .ov-notify {
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 13px;
            display: none;
            animation: slideIn .3s ease;
        }
        @keyframes slideIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
        .ov-notify.success { display: block; background: rgba(34,197,94,.06); border: 1px solid rgba(34,197,94,.3); color: var(--green); }
        .ov-notify.error   { display: block; background: rgba(239,68,68,.06); border: 1px solid rgba(239,68,68,.3); color: #f87171; }
        .ov-notify-title { font-weight: 700; font-size: 14px; margin-bottom: 3px; }

        /* Fingerprint warning */
        .ov-fp-warn {
            background: rgba(193,18,31,.05);
            border: 1px dashed rgba(193,18,31,.3);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }
        .ov-fp-warn span { color: #ff6b7a; font-weight: 600; }

        /* Action row in overlay — register + close */
        .ov-action-row {
            display: flex;
            gap: 10px;
        }
        .ov-reg-btn {
            flex: 1;
            padding: 10px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-family: 'Syne', sans-serif;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
        }
        .ov-reg-btn:hover { opacity: .88; }
        .ov-close-btn {
            flex: 1;
            padding: 10px;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--ot-border);
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 12px;
            cursor: pointer;
            transition: border-color .2s, color .2s;
        }
        .ov-close-btn:hover { border-color: var(--text); color: var(--text); }

        /* No staff message */
        .no-staff { text-align: center; padding: 60px; color: var(--muted); font-size: 14px; grid-column: 1/-1; }

        @media (max-width: 900px) {
            body { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .stats { grid-template-columns: repeat(2,1fr); }
            .staff-grid { grid-template-columns: repeat(auto-fill, minmax(160px,1fr)); }
            .overlay-shell { grid-template-columns: 1fr; max-width: 400px; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#6b9fff"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        ADMIN PANEL
    </div>
    <a href="<?= SITE_URL ?>/admin/terminal.php"  class="nav-item active">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
        Terminal
    </a>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Dashboard
    </a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Employees
    </a>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="nav-item">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Reports
    </a>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">Signed in as <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-item danger">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
    </a>
</aside>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <h1>Soteria Attendance System</h1>
        <div class="topbar-right">
            <div class="live-badge"><div class="live-dot"></div> LIVE</div>
            <span id="liveTime"></span>
        </div>
    </div>

    <div class="content">

        <!-- Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Staff</div>
                <div class="stat-value blue" id="statTotal"><?= $totalActive ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Currently In</div>
                <div class="stat-value green" id="statIn"><?= $countIn ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Clocked Out</div>
                <div class="stat-value amber" id="statOut"><?= $countOut ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Not Yet In</div>
                <div class="stat-value muted" id="statAbsent"><?= $countAbsent ?></div>
            </div>
        </div>

        <!-- Section header + search -->
        <div class="section-header">
            <h2>All Staff</h2>
            <div class="search-wrap">
                <svg class="search-icon" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="staffSearch" class="search-input" placeholder="Search staff…" oninput="filterCards()">
            </div>
        </div>

        <!-- Staff grid -->
        <div class="staff-grid" id="staffGrid">
            <?php foreach ($employees as $e):
                $hasIn  = !empty($e['clock_in']);
                $hasOut = !empty($e['clock_out']);
                $statusClass = $hasIn && !$hasOut ? 'status-in' : ($hasOut ? 'status-out' : 'status-absent');
                $statusLabel = $hasIn && !$hasOut ? 'In' : ($hasOut ? 'Done' : 'Not In');
                $pillClass   = $hasIn && !$hasOut ? 'pill-in' : ($hasOut ? 'pill-out' : 'pill-absent');

                // Determine next action
                $nextAction = ($hasIn && !$hasOut) ? 'clock_out' : 'clock_in';
            ?>
            <div class="staff-card <?= $statusClass ?>"
                 data-name="<?= htmlspecialchars(strtolower($e['full_name'])) ?>"
                 data-dept="<?= htmlspecialchars(strtolower($e['department'])) ?>"
                 onclick="openOverlay(<?= htmlspecialchars(json_encode([
                     'staff_id'    => $e['staff_id'],
                     'full_name'   => $e['full_name'],
                     'department'  => $e['department'],
                     'designation' => $e['designation'],
                     'clock_in'    => $e['clock_in'] ? date('H:i:s', strtotime($e['clock_in'])) : null,
                     'clock_out'   => $e['clock_out'] ? date('H:i:s', strtotime($e['clock_out'])) : null,
                     'hours_worked'=> $e['hours_worked'],
                     'has_fp'      => (bool)$e['has_fp'],
                     'next_action' => $nextAction,
                 ])) ?>)">

                <div class="card-top">
                    <div class="card-av"><?= strtoupper(substr($e['full_name'],0,2)) ?></div>
                    <div class="card-meta">
                        <div class="card-name"><?= htmlspecialchars($e['full_name']) ?></div>
                        <div class="card-dept"><?= htmlspecialchars($e['department']) ?></div>
                    </div>
                </div>

                <div class="card-status">
                    <span class="status-pill <?= $pillClass ?>">
                        <?= $hasIn && !$hasOut ? '● In' : ($hasOut ? '✓ Done' : '○ Not In') ?>
                    </span>
                    <span class="fp-dot <?= $e['has_fp'] ? 'registered' : '' ?>" title="<?= $e['has_fp'] ? 'Fingerprint registered' : 'No fingerprint' ?>"></span>
                </div>

                <?php if ($e['clock_in']): ?>
                <div class="card-time <?= !$hasOut ? 'green' : '' ?>">
                    In <?= date('H:i', strtotime($e['clock_in'])) ?>
                    <?= $hasOut ? ' · Out ' . date('H:i', strtotime($e['clock_out'])) : '' ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (empty($employees)): ?>
                <div class="no-staff">No active staff found. <a href="<?= SITE_URL ?>/admin/employees.php" style="color:#6b9fff">Add staff →</a></div>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- ── Clock Overlay ── -->
<div class="overlay-bg" id="overlayBg" onclick="handleOverlayClick(event)">
    <div class="overlay-shell" id="overlayShell">

        <!-- Left: QR panel -->
        <div class="ov-panel ov-qr">
            <button class="ov-close" onclick="closeOverlay()" title="Close">✕</button>

            <div class="ov-action-badge" id="ovBadge"></div>
            <p class="ov-hint">Scan with your phone to verify</p>

            <div class="ov-qr-wrap" id="ovQrWrap">
                <div class="qr-loading" id="ovQrLoading"></div>
                <div id="ovQrCanvas" style="width:200px;height:200px;display:none;border-radius:12px;overflow:hidden;"></div>
            </div>

            <div class="ov-timer-ring">
                <svg class="ov-timer-svg" width="64" height="64" viewBox="0 0 64 64">
                    <circle class="ov-timer-track" cx="32" cy="32" r="26"/>
                    <circle class="ov-timer-fill"  cx="32" cy="32" r="26" id="ovTimerArc"/>
                </svg>
                <div class="ov-timer-num" id="ovTimerNum">--</div>
            </div>

            <p class="ov-steps">
                <strong>1.</strong> Scan QR with phone camera<br>
                <strong>2.</strong> Verify with fingerprint<br>
                <strong>3.</strong> Attendance recorded automatically
            </p>

            <!-- Switch action links -->
            <div style="margin-top:14px;display:flex;gap:12px;justify-content:center">
                <button id="btnSwitchIn"  onclick="switchAction('clock_in')"  style="font-size:11px;padding:5px 12px;border-radius:6px;border:1px solid rgba(34,197,94,.3);background:rgba(34,197,94,.06);color:var(--green);cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s">Clock In</button>
                <button id="btnSwitchOut" onclick="switchAction('clock_out')" style="font-size:11px;padding:5px 12px;border-radius:6px;border:1px solid rgba(107,159,255,.3);background:rgba(107,159,255,.06);color:#6b9fff;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s">Clock Out</button>
                <button id="btnSwitchReg" onclick="switchAction('register')"  style="font-size:11px;padding:5px 12px;border-radius:6px;border:1px solid rgba(30,64,175,.3);background:rgba(30,64,175,.06);color:#6b9fff;cursor:pointer;font-family:'DM Sans',sans-serif;transition:opacity .2s">Register FP</button>
            </div>
        </div>

        <!-- Right: Status panel -->
        <div class="ov-panel ov-status">
            <div class="ov-staff-header">
                <div class="ov-staff-av" id="ovStaffAv"></div>
                <div>
                    <div class="ov-staff-name" id="ovStaffName"></div>
                    <div class="ov-staff-dept" id="ovStaffDept"></div>
                </div>
            </div>

            <h3 style="font-family:'Syne',sans-serif;font-size:15px;font-weight:700">Today's Attendance</h3>

            <div class="ov-today-card">
                <div class="ov-today-label">Clock In</div>
                <div class="ov-today-value green" id="ovClockIn">--:--:--</div>
            </div>

            <div class="ov-row2">
                <div class="ov-today-card">
                    <div class="ov-today-label">Clock Out</div>
                    <div class="ov-today-value red" id="ovClockOut">--:--</div>
                </div>
                <div class="ov-today-card">
                    <div class="ov-today-label">Hours</div>
                    <div class="ov-today-value" id="ovHours">--</div>
                </div>
            </div>

            <div class="ov-notify" id="ovNotify">
                <div class="ov-notify-title" id="ovNotifyTitle"></div>
                <div id="ovNotifyMsg"></div>
            </div>

            <div class="ov-fp-warn" id="ovFpWarn" style="display:none">
                ⚠ Fingerprint not registered yet.<br>
                <span>Use "Register FP" button to set up fingerprint.</span>
            </div>

            <div class="ov-action-row">
                <button class="ov-reg-btn" id="ovRegBtn" onclick="switchAction('register')" style="display:none">⊕ Register Fingerprint</button>
                <button class="ov-close-btn" onclick="closeOverlay()">✕ Close</button>
            </div>
        </div>

    </div>
</div>

<script>
const SITE_URL = <?= json_encode(SITE_URL) ?>;
const EXPIRY   = 120;
const CIRC     = 2 * Math.PI * 26;

let ovStaff = null, ovAction = null, ovToken = null;
let timerInt = null, pollInt = null, countdown = EXPIRY;

function updateClock() {
    const now = new Date();
    document.getElementById('liveTime').textContent =
        now.toLocaleTimeString('en-GB') + '  ' +
        now.toLocaleDateString('en-GB', { weekday:'short', day:'2-digit', month:'short' });
}
setInterval(updateClock, 1000);
updateClock();

function filterCards() {
    const q = document.getElementById('staffSearch').value.toLowerCase().trim();
    document.querySelectorAll('.staff-card').forEach(card => {
        const match = !q || card.dataset.name.includes(q) || card.dataset.dept.includes(q);
        card.classList.toggle('hidden', !match);
    });
}

function openOverlay(staff) {
    ovStaff = staff; ovAction = staff.next_action;
    document.getElementById('ovStaffAv').textContent   = staff.full_name.substring(0,2).toUpperCase();
    document.getElementById('ovStaffName').textContent = staff.full_name;
    document.getElementById('ovStaffDept').textContent = staff.staff_id + ' · ' + staff.department;
    document.getElementById('ovClockIn').textContent   = staff.clock_in  || '--:--:--';
    document.getElementById('ovClockOut').textContent  = staff.clock_out || '--:--';
    document.getElementById('ovHours').textContent     = staff.hours_worked ? staff.hours_worked + 'h' : '--';
    const fpWarn = document.getElementById('ovFpWarn');
    const fpBtn  = document.getElementById('ovRegBtn');
    fpWarn.style.display = staff.has_fp ? 'none' : 'block';
    fpBtn.style.display  = staff.has_fp ? 'none' : 'block';
    document.getElementById('ovNotify').className = 'ov-notify';
    document.getElementById('overlayBg').classList.add('open');
    document.body.style.overflow = 'hidden';
    generateQR();
}

function closeOverlay() {
    clearInterval(timerInt); clearInterval(pollInt);
    document.getElementById('overlayBg').classList.remove('open');
    document.body.style.overflow = '';
    ovStaff = ovToken = ovAction = null;
    document.getElementById('ovQrLoading').style.display = 'block';
    document.getElementById('ovQrCanvas').style.display  = 'none';
    document.getElementById('ovQrWrap').classList.remove('expired');
    document.getElementById('ovTimerNum').textContent = '--';
    document.getElementById('ovTimerArc').style.strokeDashoffset = 0;
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('overlayBg')) closeOverlay();
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeOverlay(); });

function switchAction(action) {
    if (ovAction === action) return;
    ovAction = action;
    clearInterval(timerInt); clearInterval(pollInt);
    document.getElementById('ovQrCanvas').innerHTML = '';
    document.getElementById('ovQrCanvas').style.display  = 'none';
    document.getElementById('ovQrLoading').style.display = 'block';
    document.getElementById('ovQrWrap').classList.remove('expired');
    document.getElementById('ovTimerNum').textContent = '--';
    document.getElementById('ovTimerArc').style.strokeDashoffset = 0;
    generateQR();
}

function updateBadge(action) {
    const badge = document.getElementById('ovBadge');
    const map = {
        clock_in:  ['● Clock In',            'ov-action-badge badge-in'],
        clock_out: ['● Clock Out',            'ov-action-badge badge-out'],
        register:  ['⊕ Register Fingerprint', 'ov-action-badge badge-reg'],
    };
    badge.textContent = map[action][0];
    badge.className   = map[action][1];
}

async function generateQR() {
    if (!ovStaff || !ovAction) return;
    updateBadge(ovAction);
    document.getElementById('ovQrLoading').style.display = 'block';
    document.getElementById('ovQrCanvas').style.display  = 'none';
    document.getElementById('ovQrWrap').classList.remove('expired');
    try {
        const res  = await fetch(SITE_URL + '/api/generate-qr.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: ovStaff.staff_id, action: ovAction })
        });
        const data = await res.json();
        if (data.error) { showOverlayError(data.error); return; }
        ovToken = data.token;
        const canvas = document.getElementById('ovQrCanvas');
        canvas.innerHTML = '';
        new QRCode(canvas, {
            text: SITE_URL + '/mobile/verify.php?token=' + data.token,
            width: 200, height: 200,
            colorDark: '#000000', colorLight: '#ffffff',
        });
        document.getElementById('ovQrLoading').style.display = 'none';
        canvas.style.display = 'block';
        startTimer();
        startPolling(ovToken);
    } catch(e) {
        showOverlayError('Network error. Please try again.');
    }
}

function startTimer() {
    clearInterval(timerInt);
    countdown = EXPIRY;
    const arc = document.getElementById('ovTimerArc');
    const num = document.getElementById('ovTimerNum');
    arc.style.strokeDasharray  = CIRC;
    arc.style.strokeDashoffset = 0;
    arc.style.stroke = 'var(--ot-red)';
    num.textContent  = countdown;
    timerInt = setInterval(() => {
        countdown--;
        num.textContent = countdown;
        arc.style.strokeDashoffset = CIRC * (1 - countdown / EXPIRY);
        arc.style.stroke = countdown <= 10 ? '#ff6b7a' : 'var(--ot-red)';
        if (countdown <= 0) {
            clearInterval(timerInt); clearInterval(pollInt);
            document.getElementById('ovQrWrap').classList.add('expired');
            setTimeout(() => { if (ovStaff && ovAction) generateQR(); }, 400);
        }
    }, 1000);
}

function startPolling(token) {
    clearInterval(pollInt);
    pollInt = setInterval(async () => {
        try {
            const res  = await fetch(SITE_URL + '/api/status.php?token=' + token);
            const data = await res.json();
            if (data.confirmed) { clearInterval(pollInt); clearInterval(timerInt); onConfirmed(data); }
            if (data.expired)   { clearInterval(pollInt); }
        } catch(e) {}
    }, 2000);
}

function onConfirmed(data) {
    refreshStats(); // ✅ Immediate grid update

    const notify = document.getElementById('ovNotify');
    const actionLabels = {
        clock_in:  '✓ Clocked In Successfully',
        clock_out: '✓ Clocked Out Successfully',
        register:  '✓ Fingerprint Registered',
    };
    notify.className = 'ov-notify success';
    document.getElementById('ovNotifyTitle').textContent = actionLabels[data.action] || '✓ Done';
    document.getElementById('ovNotifyMsg').textContent   = 'Recorded at ' + data.time;
    if (data.action === 'clock_in')  document.getElementById('ovClockIn').textContent  = data.time;
    if (data.action === 'clock_out') document.getElementById('ovClockOut').textContent = data.time;
    document.getElementById('ovQrWrap').classList.add('expired');
    document.getElementById('ovTimerNum').textContent = '✓';
    setTimeout(() => { refreshStats(); closeOverlay(); }, 4000);
}

function showOverlayError(msg) {
    const notify = document.getElementById('ovNotify');
    document.getElementById('ovNotifyTitle').textContent = 'Error';
    document.getElementById('ovNotifyMsg').textContent   = msg;
    notify.className = 'ov-notify error';
    document.getElementById('ovQrLoading').style.display = 'none';
    setTimeout(() => {
        notify.style.transition = 'opacity 0.4s ease';
        notify.style.opacity = '0';
        setTimeout(() => { notify.style.display = 'none'; notify.style.opacity = '1'; }, 600);
    }, 5000);
}

async function refreshStats() {
    try {
        const res  = await fetch(SITE_URL + '/api/admin-feed.php');
        const data = await res.json();
        if (data.counts) {
            document.getElementById('statIn').textContent     = data.counts.in;
            document.getElementById('statOut').textContent    = data.counts.out;
            document.getElementById('statAbsent').textContent = data.counts.absent;
        }
        if (data.grid_html) {
            document.getElementById('staffGrid').innerHTML = data.grid_html;
        }
    } catch(e) {}
}

// ✅ Refresh every 3 seconds
setInterval(refreshStats, 3000);
</script>
    
</body>
</html>
