<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

$db = db();

// Filters
$from_date   = trim($_GET['from']   ?? date('Y-m-01'));         // default: start of month
$to_date     = trim($_GET['to']     ?? date('Y-m-d'));           // default: today
$filter_dept = trim($_GET['dept']   ?? '');
$filter_emp  = trim($_GET['emp']    ?? '');

// Clamp dates
if ($from_date > $to_date) $from_date = $to_date;

// Departments for filter
$depts = $db->query('SELECT DISTINCT department FROM employee ORDER BY department')->fetchColumn();
$depts = $db->query('SELECT DISTINCT department FROM employee ORDER BY department')->fetchAll(PDO::FETCH_COLUMN);

// Build query
$where  = ['a.att_date BETWEEN ? AND ?'];
$params = [$from_date, $to_date];

if ($filter_dept) { $where[] = 'e.department = ?'; $params[] = $filter_dept; }
if ($filter_emp)  { $where[] = '(e.full_name LIKE ? OR e.staff_id = ?)'; $params[] = '%'.$filter_emp.'%'; $params[] = $filter_emp; }

$sql = 'SELECT a.*, e.full_name, e.department, e.designation
        FROM attendance a
        JOIN employee e ON a.staff_id = e.staff_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY a.att_date DESC, a.clock_in DESC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $from_date . '_to_' . $to_date . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Staff ID', 'Full Name', 'Department', 'Designation', 'Clock In', 'Clock Out', 'Hours Worked', 'Auto Clock-out', 'Notes']);
    foreach ($records as $r) {
        fputcsv($out, [
            $r['att_date'], $r['staff_id'], $r['full_name'], $r['department'], $r['designation'],
            $r['clock_in'], $r['clock_out'] ?? '', $r['hours_worked'] ?? '',
            $r['auto_clocked_out'] ? 'Yes' : 'No', $r['notes'] ?? ''
        ]);
    }
    fclose($out);
    exit;
}

// Summary stats for the filtered range
$totalDays   = 0; $totalHours = 0; $autoOuts = 0;
$empSet      = [];
foreach ($records as $r) {
    $totalDays++;
    $totalHours += floatval($r['hours_worked']);
    if ($r['auto_clocked_out']) $autoOuts++;
    $empSet[$r['staff_id']] = true;
}
$uniqueEmps = count($empSet);
$avgHours   = $totalDays ? round($totalHours / $totalDays, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root { --accent:#1e40af; --dark:#060912; --sidebar:#0a0f1e; --card:#0d1117; --border:#1a2030; --text:#e2e8f0; --muted:#4a5568; --green:#22c55e; --amber:#f59e0b; --red:#ef4444; }
        body { font-family:'DM Sans',sans-serif; background:var(--dark); color:var(--text); min-height:100vh; display:grid; grid-template-columns:220px 1fr; }

        .sidebar { background:var(--sidebar); border-right:1px solid var(--border); padding:24px 16px; display:flex; flex-direction:column; gap:4px; position:sticky; top:0; height:100vh; }
        .sidebar-logo { font-family:'Syne',sans-serif; font-weight:800; font-size:14px; padding:12px; margin-bottom:24px; color:#6b9fff; letter-spacing:.5px; display:flex; align-items:center; gap:8px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; color:var(--muted); text-decoration:none; transition:background .15s,color .15s; }
        .nav-item:hover,.nav-item.active { background:rgba(30,64,175,.12); color:var(--text); }
        .nav-item.active { color:#6b9fff; font-weight:500; }
        .sidebar-spacer { flex:1; }
        .sidebar-user { font-size:12px; color:var(--muted); padding:0 14px; margin-bottom:8px; }
        .nav-item.danger:hover { background:rgba(239,68,68,.1); color:#f87171; }

        .main { display:flex; flex-direction:column; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:20px 32px; border-bottom:1px solid var(--border); background:var(--card); }
        .topbar h1 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }

        .content { padding:28px 32px; flex:1; display:flex; flex-direction:column; gap:20px; }

        /* Filters */
        .filter-bar { background:var(--card); border:1px solid var(--border); border-radius:14px; padding:20px 24px; display:flex; align-items:flex-end; gap:16px; flex-wrap:wrap; }
        .filter-field { display:flex; flex-direction:column; gap:6px; }
        .filter-field label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); font-weight:600; }
        .filter-field input, .filter-field select {
            background:#111827; border:1px solid var(--border); border-radius:8px;
            padding:9px 13px; font-size:13px; color:var(--text); font-family:'DM Sans',sans-serif;
            outline:none; transition:border-color .2s;
        }
        .filter-field input:focus, .filter-field select:focus { border-color:var(--accent); }
        .filter-field select option { background:#1a2030; }
        .filter-actions { display:flex; gap:10px; margin-left:auto; }
        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:opacity .2s; font-family:'Syne',sans-serif; }
        .btn:hover { opacity:.85; }
        .btn-primary { background:var(--accent); color:#fff; }
        .btn-outline { background:transparent; color:var(--muted); border:1px solid var(--border); }
        .btn-green  { background:rgba(34,197,94,.12); color:var(--green); border:1px solid rgba(34,197,94,.2); }

        /* Stats */
        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
        .stat-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px; }
        .stat-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:6px; }
        .stat-value { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; }
        .stat-value.blue  { color:#6b9fff; }
        .stat-value.green { color:var(--green); }
        .stat-value.amber { color:var(--amber); }
        .stat-value.red   { color:var(--red); }

        /* Table */
        .table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .table-header { display:flex; align-items:center; justify-content:space-between; padding:18px 24px; border-bottom:1px solid var(--border); }
        .table-header h2 { font-family:'Syne',sans-serif; font-size:15px; font-weight:700; }
        .record-count { font-size:12px; color:var(--muted); }
        table { width:100%; border-collapse:collapse; }
        th { padding:11px 18px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); border-bottom:1px solid var(--border); font-weight:500; }
        td { padding:12px 18px; font-size:13px; border-bottom:1px solid rgba(26,32,48,.5); }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:rgba(30,64,175,.03); }
        .mono { font-family:'JetBrains Mono',monospace; font-size:12px; }
        .no-data { text-align:center; padding:48px; color:var(--muted); font-size:14px; }

        .badge { display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:100px; font-size:11px; font-weight:600; text-transform:uppercase; }
        .badge-active { background:rgba(34,197,94,.1);  color:var(--green); }
        .badge-done   { background:rgba(107,159,255,.1); color:#6b9fff; }
        .badge-auto   { background:rgba(245,158,11,.12); color:var(--amber); }

        .emp-link { color:#6b9fff; text-decoration:none; font-weight:500; }
        .emp-link:hover { text-decoration:underline; }

        @media (max-width:900px) { body { grid-template-columns:1fr; } .sidebar { display:none; } .stats-row { grid-template-columns:repeat(2,1fr); } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="#6b9fff"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg> ADMIN PANEL</div>
    <a href="<?= SITE_URL ?>/admin/terminal.php"  class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Terminal</a>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Employees</a>
    <a href="<?= SITE_URL ?>/admin/reports.php"   class="nav-item active"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Reports</a>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">Signed in as <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-item danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Sign Out</a>
</aside>

<div class="main">
    <div class="topbar">
        <h1>Attendance Reports</h1>
    </div>

    <div class="content">
        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <div class="filter-field">
                <label>From</label>
                <input type="date" name="from" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="filter-field">
                <label>To</label>
                <input type="date" name="to" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="filter-field">
                <label>Department</label>
                <select name="dept">
                    <option value="">All Departments</option>
                    <?php foreach ($depts as $d): ?>
                        <option value="<?= htmlspecialchars($d) ?>" <?= $filter_dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Staff Name / ID</label>
                <input type="text" name="emp" value="<?= htmlspecialchars($filter_emp) ?>" placeholder="Search…">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="reports.php" class="btn btn-outline">Reset</a>
            </div>
        </form>

        <!-- Summary stats -->
        <div class="stats-row">
            <div class="stat-card"><div class="stat-label">Unique Staff</div><div class="stat-value blue"><?= $uniqueEmps ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Records</div><div class="stat-value green"><?= $totalDays ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Hours</div><div class="stat-value amber"><?= number_format($totalHours, 1) ?></div></div>
            <div class="stat-card"><div class="stat-label">Auto Clock-outs</div><div class="stat-value red"><?= $autoOuts ?></div></div>
        </div>

        <!-- Table -->
        <div class="table-wrap">
            <div class="table-header">
                <div>
                    <h2>Records</h2>
                    <div class="record-count"><?= count($records) ?> result<?= count($records) !== 1 ? 's' : '' ?> · <?= date('d M Y', strtotime($from_date)) ?> – <?= date('d M Y', strtotime($to_date)) ?></div>
                </div>
                <?php if (!empty($records)): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-green">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Export CSV
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($records)): ?>
                <div class="no-data">No records found for the selected filters.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Staff ID</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $r): ?>
                    <tr>
                        <td><a href="<?= SITE_URL ?>/admin/employee-profile.php?id=<?= urlencode($r['staff_id']) ?>" class="emp-link"><?= htmlspecialchars($r['full_name']) ?></a></td>
                        <td class="mono"><?= htmlspecialchars($r['staff_id']) ?></td>
                        <td style="color:var(--muted)"><?= htmlspecialchars($r['department']) ?></td>
                        <td class="mono"><?= date('d M Y', strtotime($r['att_date'])) ?></td>
                        <td class="mono"><?= date('H:i:s', strtotime($r['clock_in'])) ?></td>
                        <td class="mono"><?= $r['clock_out'] ? date('H:i:s', strtotime($r['clock_out'])) : '—' ?></td>
                        <td class="mono"><?= $r['hours_worked'] ? $r['hours_worked'].'h' : '—' ?></td>
                        <td>
                            <?php if ($r['auto_clocked_out']): ?>
                                <span class="badge badge-auto">Auto-out</span>
                            <?php elseif ($r['clock_out']): ?>
                                <span class="badge badge-done">Done</span>
                            <?php else: ?>
                                <span class="badge badge-active">Active</span>
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

</body>
</html>