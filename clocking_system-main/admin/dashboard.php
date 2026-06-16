<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

$db    = db();
$today = date('Y-m-d');

$totalActive = $db->query('SELECT COUNT(*) FROM employee WHERE is_active=TRUE')->fetchColumn();

// FIX: Use DISTINCT staff_id so employees with multiple records aren't double-counted
$stIn  = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NULL');
$stIn->execute([$today]);
$countIn = $stIn->fetchColumn();

$stOut = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NOT NULL');
$stOut->execute([$today]);
$countOut = $stOut->fetchColumn();

// FIX: Absent = active staff with NO attendance record today at all.
// Old logic subtracted both $countIn and $countOut — but both groups attended,
// so it was undercounting absences whenever anyone had clocked out.
$stAttended = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=?');
$stAttended->execute([$today]);
$countAttended = $stAttended->fetchColumn();
$countAbsent   = $totalActive - $countAttended;

$stmt = $db->prepare('
    SELECT a.*, e.full_name, e.department
    FROM attendance a
    JOIN employee e ON a.staff_id = e.staff_id
    WHERE a.att_date = ?
    ORDER BY a.clock_in DESC
    LIMIT 50
');
$stmt->execute([$today]);
$records = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --accent:#1e40af; --dark:#060912; --sidebar:#0a0f1e;
            --card:#0d1117; --border:#1a2030; --text:#e2e8f0; --muted:#4a5568;
            --green:#22c55e; --amber:#f59e0b; --red:#ef4444;
        }
        body { font-family:'DM Sans',sans-serif; background:var(--dark); color:var(--text); min-height:100vh; display:grid; grid-template-columns:220px 1fr; }

        .sidebar { background:var(--sidebar); border-right:1px solid var(--border); padding:24px 16px; display:flex; flex-direction:column; gap:4px; position:sticky; top:0; height:100vh; }
        .sidebar-logo { font-family:'Syne',sans-serif; font-weight:800; font-size:14px; padding:12px; margin-bottom:24px; color:#6b9fff; letter-spacing:.5px; display:flex; align-items:center; gap:8px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; color:var(--muted); text-decoration:none; transition:background .15s,color .15s; }
        .nav-item:hover,.nav-item.active { background:rgba(30,64,175,.12); color:var(--text); }
        .nav-item.active { color:#6b9fff; font-weight:500; }
        .sidebar-spacer { flex:1; }
        .sidebar-user { font-size:12px; color:var(--muted); padding:0 14px; margin-bottom:8px; }
        .nav-item.danger:hover { background:rgba(239,68,68,.1); color:#f87171; }

        .main { display:flex; flex-direction:column; overflow-x:hidden; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:20px 32px; border-bottom:1px solid var(--border); background:var(--card); }
        .topbar h1 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }
        .topbar-right { display:flex; align-items:center; gap:16px; }
        .live-badge { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--green); font-weight:600; }
        .live-dot { width:8px; height:8px; background:var(--green); border-radius:50%; animation:blink 1.5s ease-in-out infinite; }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

        .content { padding:28px 32px; flex:1; }
        .stats { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:28px; }
        .stat-card { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:20px; }
        .stat-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:8px; }
        .stat-value { font-family:'Syne',sans-serif; font-size:32px; font-weight:800; }
        .stat-value.blue  { color:#6b9fff; }
        .stat-value.green { color:var(--green); }
        .stat-value.amber { color:var(--amber); }
        .stat-value.muted { color:var(--muted); }

        .table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .table-header { display:flex; align-items:center; justify-content:space-between; padding:20px 24px; border-bottom:1px solid var(--border); }
        .table-header h2 { font-family:'Syne',sans-serif; font-size:16px; font-weight:700; }
        .action-link { padding:7px 14px; border:1px solid var(--border); border-radius:8px; font-size:12px; color:var(--muted); text-decoration:none; transition:border-color .2s,color .2s; }
        .action-link:hover { border-color:#6b9fff; color:#6b9fff; }

        table { width:100%; border-collapse:collapse; }
        th { padding:11px 20px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); border-bottom:1px solid var(--border); font-weight:500; }
        td { padding:13px 20px; font-size:13px; border-bottom:1px solid rgba(26,32,48,.5); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:rgba(30,64,175,.03); }

        .emp-cell { display:flex; align-items:center; gap:10px; }
        .emp-av { width:32px; height:32px; background:var(--accent); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; font-family:'Syne',sans-serif; flex-shrink:0; }
        .emp-av-name { font-weight:500; }
        .emp-av-dept { font-size:11px; color:var(--muted); }
        .mono { font-family:'JetBrains Mono',monospace; font-size:12px; }
        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:100px; font-size:11px; font-weight:600; text-transform:uppercase; }
        .badge-active { background:rgba(34,197,94,.1);  color:var(--green); }
        .badge-done   { background:rgba(107,159,255,.1); color:#6b9fff; }
        .badge-auto   { background:rgba(245,158,11,.12); color:var(--amber); }
        .no-data { text-align:center; padding:48px; color:var(--muted); font-size:14px; }

        @media (max-width:900px) { body { grid-template-columns:1fr; } .sidebar { display:none; } .stats { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#6b9fff"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        ADMIN PANEL
    </div>
    <!-- FIX: Nav links now use SITE_URL which is the app root, not a file path -->
    <a href="<?= SITE_URL ?>/admin/terminal.php"  class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Terminal</a>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item active"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Employees</a>
    <a href="<?= SITE_URL ?>/admin/reports.php"   class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Reports</a>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">Signed in as <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-item danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Sign Out</a>
</aside>

<div class="main">
    <div class="topbar">
        <h1>Live Dashboard</h1>
        <div class="topbar-right">
            <div class="live-badge"><div class="live-dot"></div> LIVE</div>
            <span id="liveTime" style="font-family:'JetBrains Mono',monospace;font-size:13px;color:var(--muted);"></span>
        </div>
    </div>

    <div class="content">
        <div class="stats">
            <div class="stat-card"><div class="stat-label">Total Staff</div><div class="stat-value blue"><?= $totalActive ?></div></div>
            <div class="stat-card"><div class="stat-label">Currently In</div><div class="stat-value green" id="countIn"><?= $countIn ?></div></div>
            <div class="stat-card"><div class="stat-label">Clocked Out</div><div class="stat-value amber" id="countOut"><?= $countOut ?></div></div>
            <div class="stat-card"><div class="stat-label">Not Yet In</div><div class="stat-value muted" id="countAbsent"><?= $countAbsent ?></div></div>
        </div>

        <div class="table-wrap">
            <div class="table-header">
                <h2>Today's Attendance — <?= date('D, d M Y') ?></h2>
                <a href="<?= SITE_URL ?>/admin/reports.php" class="action-link">Full Reports →</a>
            </div>
            <div id="attendanceBody">
                <?php if (empty($records)): ?>
                    <div class="no-data">No attendance records yet today.</div>
                <?php else: ?>
                <table>
                    <thead><tr><th>Employee</th><th>Staff ID</th><th>Clock In</th><th>Clock Out</th><th>Hours</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $r): ?>
                        <tr>
                            <td><div class="emp-cell"><div class="emp-av"><?= strtoupper(substr($r['full_name'],0,2)) ?></div><div><div class="emp-av-name"><?= htmlspecialchars($r['full_name']) ?></div><div class="emp-av-dept"><?= htmlspecialchars($r['department']) ?></div></div></div></td>
                            <td class="mono"><?= htmlspecialchars($r['staff_id']) ?></td>
                            <td class="mono"><?= date('H:i:s', strtotime($r['clock_in'])) ?></td>
                            <td class="mono"><?= $r['clock_out'] ? date('H:i:s', strtotime($r['clock_out'])) : '—' ?></td>
                            <td class="mono"><?= $r['hours_worked'] ? $r['hours_worked'].'h' : '—' ?></td>
                            <td>
                                <?php if ($r['auto_clocked_out']): ?>
                                    <span class="badge badge-auto">⚠ Auto-out</span>
                                <?php elseif ($r['clock_out']): ?>
                                    <span class="badge badge-done">✓ Done</span>
                                <?php else: ?>
                                    <span class="badge badge-active">● Active</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const SITE_URL = <?= json_encode(SITE_URL) ?>;
setInterval(() => { document.getElementById('liveTime').textContent = new Date().toLocaleTimeString('en-GB'); }, 1000);

setInterval(async () => {
    try {
        const res  = await fetch(SITE_URL + '/api/admin-feed.php');
        const data = await res.json();
        if (data.counts) {
            document.getElementById('countIn').textContent     = data.counts.in;
            document.getElementById('countOut').textContent    = data.counts.out;
            document.getElementById('countAbsent').textContent = data.counts.absent;
        }
        if (data.html) document.getElementById('attendanceBody').innerHTML = data.html;
    } catch(e) {}
}, 10000);
</script>
</body>
</html>