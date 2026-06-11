<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

function profile_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function profile_query_value(string $key, string $default = ''): string {
    return isset($_GET[$key]) && is_scalar($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

function profile_month_start(string $month): DateTimeImmutable {
    if (preg_match('/^\d{4}-\d{2}$/', $month)) {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
        if ($date && $date->format('Y-m') === $month) {
            return $date;
        }
    }

    return new DateTimeImmutable(date('Y-m-01'));
}

function profile_date(string $date, string $fallback): DateTimeImmutable {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($parsed && $parsed->format('Y-m-d') === $date) {
            return $parsed;
        }
    }

    return new DateTimeImmutable($fallback);
}

function profile_working_days(DateTimeImmutable $start, DateTimeImmutable $end): int {
    if ($start > $end) {
        return 0;
    }

    $days = 0;
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
        if ((int)$date->format('N') <= 5) {
            $days++;
        }
    }

    return $days;
}

function profile_format_date($value): string {
    if (!$value) {
        return '&mdash;';
    }

    $time = strtotime((string)$value);
    return $time ? date('d M Y', $time) : '&mdash;';
}

function profile_format_time($value): string {
    if (!$value) {
        return '&mdash;';
    }

    $time = strtotime((string)$value);
    return $time ? date('H:i:s', $time) : '&mdash;';
}

function profile_format_hours($value): string {
    if ($value === null || $value === '') {
        return '&mdash;';
    }

    return number_format((float)$value, 2) . 'h';
}

function profile_url(array $params): string {
    return '?' . http_build_query($params);
}

$db       = db();
$staff_id = profile_query_value('id');

if (!$staff_id) {
    header('Location: ' . SITE_URL . '/admin/employees.php');
    exit;
}

$stmt = $db->prepare('
    SELECT e.*,
           (
               SELECT COUNT(*)
               FROM webauthn_credentials w
               WHERE w.staff_id = e.staff_id
           ) AS has_fp
    FROM employee e
    WHERE e.staff_id = ?
    LIMIT 1
');
$stmt->execute([$staff_id]);
$emp = $stmt->fetch();

if (!$emp) {
    header('Location: ' . SITE_URL . '/admin/employees.php');
    exit;
}

$today      = new DateTimeImmutable('today');
$monthStart = profile_month_start(profile_query_value('month', $today->format('Y-m')));
$monthEnd   = $monthStart->modify('last day of this month');

$defaultRangeTo = $monthStart->format('Y-m') === $today->format('Y-m') ? $today : $monthEnd;

$hoursFrom = profile_date(profile_query_value('from'), $monthStart->format('Y-m-d'));
$hoursTo   = profile_date(profile_query_value('to'), $defaultRangeTo->format('Y-m-d'));

if ($hoursFrom > $hoursTo) {
    [$hoursFrom, $hoursTo] = [$hoursTo, $hoursFrom];
}

$statsQ = $db->prepare("
    SELECT
        COUNT(DISTINCT att_date) AS total_days,
        COALESCE(SUM(COALESCE(hours_worked, 0)), 0) AS total_hours,
        COALESCE(AVG(hours_worked), 0) AS avg_hours,
        COALESCE(SUM(CASE WHEN auto_clocked_out = 1 THEN 1 ELSE 0 END), 0) AS auto_outs
    FROM attendance
    WHERE staff_id = ?
      AND att_date >= CURRENT_DATE - INTERVAL '30 days'
");
$statsQ->execute([$staff_id]);
$stats = $statsQ->fetch();

$recordsQ = $db->prepare('
    SELECT *
    FROM attendance
    WHERE staff_id = ?
    ORDER BY att_date DESC, clock_in DESC
');
$recordsQ->execute([$staff_id]);
$records = $recordsQ->fetchAll();

$monthRecordsQ = $db->prepare('
    SELECT
        att_date,
        MIN(clock_in) AS first_clock_in,
        MAX(clock_out) AS last_clock_out,
        SUM(COALESCE(hours_worked, 0)) AS total_hours
    FROM attendance
    WHERE staff_id = ?
      AND att_date BETWEEN ? AND ?
    GROUP BY att_date
    ORDER BY att_date ASC
');
$monthRecordsQ->execute([
    $staff_id,
    $monthStart->format('Y-m-d'),
    $monthEnd->format('Y-m-d')
]);

$attendanceByDate = [];
foreach ($monthRecordsQ->fetchAll() as $record) {
    $attendanceByDate[$record['att_date']] = $record;
}

$rangeRecordsQ = $db->prepare('
    SELECT
        att_date,
        SUM(COALESCE(hours_worked, 0)) AS total_hours
    FROM attendance
    WHERE staff_id = ?
      AND att_date BETWEEN ? AND ?
    GROUP BY att_date
    ORDER BY att_date ASC
');
$rangeRecordsQ->execute([
    $staff_id,
    $hoursFrom->format('Y-m-d'),
    $hoursTo->format('Y-m-d')
]);

$calcTotalHours        = 0.0;
$calcDaysPresent       = 0;
$calcPresentWorkingDay = 0;

foreach ($rangeRecordsQ->fetchAll() as $record) {
    $calcDaysPresent++;
    $calcTotalHours += (float)$record['total_hours'];

    $recordDate = new DateTimeImmutable($record['att_date']);
    if ((int)$recordDate->format('N') <= 5) {
        $calcPresentWorkingDay++;
    }
}

$calcWorkingDays    = profile_working_days($hoursFrom, $hoursTo);
$calcAvgHours       = $calcDaysPresent > 0 ? $calcTotalHours / $calcDaysPresent : 0;
$calcAttendanceRate = $calcWorkingDays > 0 ? min(100, ($calcPresentWorkingDay / $calcWorkingDays) * 100) : 0;

$firstWeekday       = (int)$monthStart->format('N');
$daysInMonth        = (int)$monthEnd->format('j');
$calendarDays       = [];
$monthWorkingDays   = 0;
$presentWorkingDays = 0;
$absentWorkingDays  = 0;

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date    = $monthStart->setDate((int)$monthStart->format('Y'), (int)$monthStart->format('m'), $day);
    $dateKey = $date->format('Y-m-d');

    $isWeekend = (int)$date->format('N') >= 6;
    $isPresent = isset($attendanceByDate[$dateKey]);
    $isFuture  = $date > $today;
    $isToday   = $dateKey === $today->format('Y-m-d');

    if (!$isWeekend) {
        $monthWorkingDays++;

        if (!$isFuture) {
            if ($isPresent) {
                $presentWorkingDays++;
            } else {
                $absentWorkingDays++;
            }
        }
    }

    $calendarDays[] = [
        'date'       => $date,
        'key'        => $dateKey,
        'record'     => $attendanceByDate[$dateKey] ?? null,
        'is_weekend' => $isWeekend,
        'is_present' => $isPresent,
        'is_future'  => $isFuture,
        'is_today'   => $isToday,
    ];
}

$prevMonth    = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth    = $monthStart->modify('+1 month')->format('Y-m');
$currentMonth = $today->format('Y-m');

$baseUrlParams = [
    'id'   => $staff_id,
    'from' => $hoursFrom->format('Y-m-d'),
    'to'   => $hoursTo->format('Y-m-d'),
];

$initials = strtoupper(substr((string)$emp['full_name'], 0, 2));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= profile_h($emp['full_name']) ?> &mdash; <?= profile_h(SITE_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
        :root { --accent:#1e40af; --dark:#060912; --sidebar:#0a0f1e; --card:#0d1117; --soft:#0b1020; --border:#1a2030; --text:#e2e8f0; --muted:#64748b; --green:#22c55e; --amber:#f59e0b; --red:#ef4444; --blue:#6b9fff; }
        body { font-family:'DM Sans',sans-serif; background:var(--dark); color:var(--text); min-height:100vh; display:grid; grid-template-columns:220px 1fr; }

        .sidebar { background:var(--sidebar); border-right:1px solid var(--border); padding:24px 16px; display:flex; flex-direction:column; gap:4px; position:sticky; top:0; height:100vh; }
        .sidebar-logo { font-family:'Syne',sans-serif; font-weight:800; font-size:14px; padding:12px; margin-bottom:24px; color:var(--blue); letter-spacing:.5px; display:flex; align-items:center; gap:8px; }
        .nav-item { display:flex; align-items:center; gap:10px; padding:10px 14px; border-radius:10px; font-size:14px; color:var(--muted); text-decoration:none; transition:background .15s,color .15s; }
        .nav-item:hover,.nav-item.active { background:rgba(30,64,175,.12); color:var(--text); }
        .nav-item.active { color:var(--blue); font-weight:500; }
        .sidebar-spacer { flex:1; }
        .sidebar-user { font-size:12px; color:var(--muted); padding:0 14px; margin-bottom:8px; }
        .nav-item.danger:hover { background:rgba(239,68,68,.1); color:#f87171; }

        .main { display:flex; flex-direction:column; overflow-x:hidden; }
        .topbar { display:flex; align-items:center; justify-content:space-between; padding:20px 32px; border-bottom:1px solid var(--border); background:var(--card); }
        .topbar-left { display:flex; align-items:center; gap:14px; }
        .back-link { display:flex; align-items:center; gap:6px; color:var(--muted); text-decoration:none; font-size:13px; transition:color .15s; }
        .back-link:hover { color:var(--text); }
        .topbar h1 { font-family:'Syne',sans-serif; font-size:20px; font-weight:700; }

        .content { padding:28px 32px; flex:1; display:flex; flex-direction:column; gap:24px; }

        .profile-header { display:flex; align-items:center; gap:20px; background:var(--card); border:1px solid var(--border); border-radius:16px; padding:24px; }
        .profile-av { width:64px; height:64px; background:var(--accent); border-radius:14px; display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-size:22px; font-weight:800; flex-shrink:0; }
        .profile-av.inactive { background:#1a2030; color:var(--muted); }
        .profile-name { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; }
        .profile-meta { display:flex; gap:14px; margin-top:6px; flex-wrap:wrap; }
        .meta-item { font-size:12px; color:var(--muted); display:flex; align-items:center; gap:5px; }
        .meta-item strong { color:var(--text); }
        .profile-actions { margin-left:auto; display:flex; gap:10px; flex-shrink:0; flex-wrap:wrap; }

        .btn { padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; cursor:pointer; border:none; text-decoration:none; display:inline-flex; align-items:center; gap:7px; transition:opacity .2s, transform .2s; font-family:'Syne',sans-serif; }
        .btn:hover { opacity:.88; transform:translateY(-1px); }
        .btn-primary { background:#2b4cc4; color:#fff; }
        .btn-outline { background:transparent; color:var(--muted); border:1px solid var(--border); }
        .btn-danger { background:rgba(239,68,68,.12); color:#f87171; border:1px solid rgba(239,68,68,.2); }
        .btn-amber { background:rgba(245,158,11,.12); color:var(--amber); border:1px solid rgba(245,158,11,.2); }
        .btn-full { width:100%; justify-content:center; }

        .stats-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; }
        .stat-card { background:var(--card); border:1px solid var(--border); border-radius:12px; padding:18px; }
        .stat-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); margin-bottom:6px; }
        .stat-value { font-family:'Syne',sans-serif; font-size:28px; font-weight:800; }
        .stat-value.blue { color:var(--blue); }
        .stat-value.green { color:var(--green); }
        .stat-value.amber { color:var(--amber); }
        .stat-value.red { color:var(--red); }

        .info-panel { background:var(--card); border:1px solid var(--border); border-radius:16px; padding:24px; }
        .info-panel h3 { font-family:'Syne',sans-serif; font-size:15px; font-weight:700; margin-bottom:16px; }
        .info-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
        .info-row { background:var(--soft); border:1px solid rgba(26,32,48,.75); border-radius:12px; padding:14px; }
        .info-key { display:block; color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px; }
        .info-val { font-size:13px; font-weight:500; word-break:break-word; }
        .fp-badge { font-size:11px; padding:3px 10px; border-radius:100px; font-weight:600; display:inline-flex; }
        .fp-yes { background:rgba(34,197,94,.1); color:var(--green); border:1px solid rgba(34,197,94,.2); }
        .fp-no { background:rgba(245,158,11,.1); color:var(--amber); border:1px solid rgba(245,158,11,.2); }

        .calendar-hours-grid { display:grid; grid-template-columns:1fr 1fr; gap:24px; align-items:stretch; }
        .calendar-card, .hours-card { background:var(--card); border:1px solid var(--border); border-radius:24px; overflow:hidden; }
        .calendar-top, .hours-head { padding:26px 30px; border-bottom:1px solid var(--border); }
        .calendar-top { display:flex; align-items:center; justify-content:space-between; gap:16px; }
        .calendar-top h2, .hours-head h2 { font-family:'Syne',sans-serif; font-size:22px; font-weight:800; }
        .hours-head p { margin-top:6px; color:var(--muted); font-size:14px; }

        .calendar-nav { display:flex; align-items:center; gap:10px; }
        .nav-square { width:40px; height:40px; border-radius:9px; border:1px solid var(--border); background:#111827; color:var(--muted); text-decoration:none; display:flex; align-items:center; justify-content:center; font-family:'Syne',sans-serif; font-weight:800; transition:background .15s,color .15s; }
        .nav-square:hover { background:rgba(107,159,255,.12); color:var(--blue); }

        .calendar-body { padding:28px 26px 10px; }
        .calendar-weekdays { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; margin-bottom:14px; }
        .calendar-weekdays div { color:var(--muted); font-size:12px; font-weight:700; letter-spacing:1px; text-transform:uppercase; text-align:center; }
        .calendar-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:6px; }
        .calendar-day { min-height:86px; border-radius:12px; border:1px solid transparent; background:#080c14; color:#334155; padding:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:5px; position:relative; }
        .calendar-day span { font-family:'JetBrains Mono',monospace; font-size:15px; }
        .calendar-day small { font-family:'JetBrains Mono',monospace; font-size:11px; }
        .calendar-blank { background:transparent; }
        .calendar-day.is-present { background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.35); color:#22c55e; }
        .calendar-day.is-absent { background:rgba(239,68,68,.12); border-color:rgba(239,68,68,.28); color:#ff4d4d; }
        .calendar-day.is-weekend { background:transparent; color:#526079; }
        .calendar-day.is-future { background:transparent; color:#1f3458; }
        .calendar-day.is-today { outline:2px solid #3b82f6; outline-offset:2px; }

        .calendar-footer { padding:18px 30px 22px; border-top:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .legend { display:flex; align-items:center; gap:16px; flex-wrap:wrap; color:var(--muted); font-size:14px; }
        .legend-item { display:flex; align-items:center; gap:8px; }
        .legend-dot { width:14px; height:14px; border-radius:4px; display:inline-block; }
        .dot-present { background:rgba(34,197,94,.35); border:1px solid rgba(34,197,94,.65); }
        .dot-absent { background:rgba(239,68,68,.28); border:1px solid rgba(239,68,68,.55); }
        .dot-weekend { background:#1b2438; }
        .month-summary { display:flex; align-items:center; gap:10px; color:var(--muted); font-size:13px; }
        .month-summary .present { color:#00ff7f; }
        .month-summary .absent { color:#ff4d4d; }

        .hours-body { padding:26px 30px 30px; }
        .hours-form { display:flex; flex-direction:column; gap:20px; }
        .date-fields { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .field label { display:block; color:var(--muted); font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:1px; margin-bottom:9px; }
        .field input { width:100%; background:#111827; color:#fff; border:1px solid var(--border); border-radius:9px; padding:14px 16px; font-family:'DM Sans',sans-serif; font-size:16px; color-scheme:dark; }
        .field input:focus { outline:none; border-color:#2b4cc4; box-shadow:0 0 0 3px rgba(43,76,196,.15); }

        .hours-result { margin-top:30px; background:linear-gradient(180deg, rgba(17,24,39,.95), rgba(9,14,28,.95)); border:1px solid rgba(43,76,196,.35); border-radius:16px; padding:36px 18px 28px; text-align:center; }
        .hours-total { font-family:'Syne',sans-serif; font-size:44px; font-weight:800; color:#6b9fff; line-height:1; }
        .hours-total span { color:#4b5f83; font-size:32px; }
        .hours-range { margin-top:10px; color:var(--muted); font-size:12px; letter-spacing:1px; text-transform:uppercase; }

        .mini-stats { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-top:20px; }
        .mini-card { background:#080d1c; border:1px solid var(--border); border-radius:12px; padding:20px; text-align:center; }
        .mini-value { font-family:'JetBrains Mono',monospace; font-size:24px; font-weight:800; }
        .mini-value.green { color:#00e676; }
        .mini-value.amber { color:#ff9800; }
        .mini-value.red { color:#ff4d4d; }
        .mini-label { margin-top:8px; color:var(--muted); font-size:12px; text-transform:uppercase; letter-spacing:.6px; }

        .table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .table-header { padding:22px 30px; border-bottom:1px solid var(--border); font-family:'Syne',sans-serif; font-size:19px; font-weight:800; }
        .table-scroll { width:100%; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:720px; }
        th { padding:13px 18px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); border-bottom:1px solid var(--border); font-weight:700; }
        td { padding:14px 18px; font-size:13px; border-bottom:1px solid rgba(26,32,48,.5); }
        tr:last-child td { border-bottom:none; }
        .mono { font-family:'JetBrains Mono',monospace; font-size:12px; }
        .no-data { text-align:center; padding:44px; color:var(--muted); font-size:14px; }

        .badge { display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border-radius:100px; font-size:11px; font-weight:700; text-transform:uppercase; }
        .badge-active { background:rgba(34,197,94,.1); color:var(--green); }
        .badge-done { background:rgba(107,159,255,.1); color:var(--blue); }
        .badge-auto { background:rgba(245,158,11,.12); color:var(--amber); }

        @media (max-width:1200px) {
            .calendar-hours-grid { grid-template-columns:1fr; }
            .info-grid { grid-template-columns:repeat(2,1fr); }
        }

        @media (max-width:1000px) {
            .stats-row { grid-template-columns:repeat(2,1fr); }
            .profile-header { align-items:flex-start; flex-wrap:wrap; }
            .profile-actions { margin-left:0; width:100%; }
        }

        @media (max-width:900px) {
            body { grid-template-columns:1fr; }
            .sidebar { display:none; }
        }

        @media (max-width:640px) {
            .content { padding:20px; }
            .topbar { padding:18px 20px; }
            .stats-row, .info-grid, .date-fields, .mini-stats { grid-template-columns:1fr; }
            .calendar-day { min-height:58px; padding:8px; }
            .calendar-top, .hours-head, .hours-body, .calendar-footer { padding-left:20px; padding-right:20px; }
        }

        /* Stronger compact sizing for all rectangle/card layouts */
.profile-header {
    padding: 16px 18px;
    border-radius: 12px;
    gap: 14px;
}

.profile-av {
    width: 48px;
    height: 48px;
    font-size: 17px;
    border-radius: 10px;
}

.profile-name {
    font-size: 18px;
}

.btn {
    padding: 7px 12px;
    font-size: 11px;
    border-radius: 7px;
}

.stats-row {
    gap: 10px;
}

.stat-card {
    padding: 12px 14px;
    border-radius: 10px;
}

.stat-label {
    font-size: 9px;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 21px;
}

.info-panel,
.calendar-card,
.hours-card,
.table-wrap {
    border-radius: 12px;
}

.info-panel {
    padding: 14px 16px;
}

.info-grid {
    gap: 8px;
}

.info-row {
    padding: 9px 10px;
    border-radius: 8px;
}

.calendar-hours-grid {
    gap: 14px;
    grid-template-columns: minmax(0, 1fr) minmax(320px, .85fr);
}

.calendar-top,
.hours-head {
    padding: 14px 18px;
}

.calendar-top h2,
.hours-head h2 {
    font-size: 15px;
}

.calendar-body {
    padding: 16px 14px 6px;
}

.calendar-grid {
    gap: 4px;
}

.calendar-day {
    min-height: 46px;
    border-radius: 7px;
    padding: 5px 4px;
}

.calendar-day span {
    font-size: 11px;
}

.calendar-day small {
    font-size: 8px;
}

.calendar-footer {
    padding: 11px 16px;
}

.hours-body {
    padding: 16px 18px 18px;
}

.field input {
    padding: 8px 10px;
    font-size: 12px;
}

.hours-result {
    margin-top: 14px;
    padding: 20px 12px 18px;
    border-radius: 10px;
}

.hours-total {
    font-size: 27px;
}

.hours-total span {
    font-size: 19px;
}

.mini-stats {
    gap: 8px;
    margin-top: 10px;
}

.mini-card {
    padding: 11px 10px;
    border-radius: 8px;
}

.mini-value {
    font-size: 16px;
}

.table-header {
    padding: 13px 18px;
    font-size: 14px;
}

th,
td {
    padding: 8px 12px;
}

@media (max-width: 1200px) {
    .calendar-hours-grid {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="#6b9fff"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
        ADMIN PANEL
    </div>
    <a href="<?= SITE_URL ?>/admin/terminal.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Terminal</a>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="nav-item active"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Employees</a>
    <a href="<?= SITE_URL ?>/admin/reports.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Reports</a>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">Signed in as <strong style="color:var(--text)"><?= profile_h($_SESSION['admin_name'] ?? 'Admin') ?></strong></div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-item danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Sign Out</a>
</aside>

<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <a href="<?= SITE_URL ?>/admin/employees.php" class="back-link">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Employees
            </a>
            <h1><?= profile_h($emp['full_name']) ?></h1>
        </div>
    </div>

    <div class="content">
        <div class="profile-header">
            <div class="profile-av <?= !$emp['is_active'] ? 'inactive' : '' ?>">
                <?= profile_h($initials) ?>
            </div>

            <div>
                <div class="profile-name"><?= profile_h($emp['full_name']) ?></div>
                <div class="profile-meta">
                    <span class="meta-item"><strong><?= profile_h($emp['designation'] ?? '') ?></strong></span>
                    <span class="meta-item">&middot; <?= profile_h($emp['department'] ?? '') ?></span>
                    <span class="meta-item">&middot; <strong><?= profile_h($emp['staff_id']) ?></strong></span>
                    <span class="meta-item">&middot; <?= $emp['is_active'] ? '<span style="color:var(--green)">&bull; Active</span>' : '<span style="color:var(--muted)">&bull; Inactive</span>' ?></span>
                </div>
            </div>

            <div class="profile-actions">
                <?php if ($emp['has_fp']): ?>
                    <a href="employees.php?reset_fp=<?= urlencode($staff_id) ?>&redirect=profile" class="btn btn-amber" onclick="return confirm('Reset fingerprint for <?= profile_h($emp['full_name']) ?>?')">Reset FP</a>
                <?php endif; ?>

                <?php if ($emp['is_active']): ?>
                    <a href="employees.php?deactivate=<?= urlencode($staff_id) ?>&redirect=profile" class="btn btn-danger" onclick="return confirm('Deactivate this employee?')">Deactivate</a>
                <?php else: ?>
                    <a href="employees.php?activate=<?= urlencode($staff_id) ?>&redirect=profile" class="btn btn-outline">Activate</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-label">Days Present (30d)</div>
                <div class="stat-value blue"><?= (int)($stats['total_days'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Hours (30d)</div>
                <div class="stat-value green"><?= number_format((float)($stats['total_hours'] ?? 0), 1) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Avg Hours/Day</div>
                <div class="stat-value amber"><?= number_format((float)($stats['avg_hours'] ?? 0), 1) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Auto Clock-outs</div>
                <div class="stat-value red"><?= (int)($stats['auto_outs'] ?? 0) ?></div>
            </div>
        </div>

        <div class="info-panel">
            <h3>Employee Details</h3>
            <div class="info-grid">
                <div class="info-row">
                    <span class="info-key">Email</span>
                    <span class="info-val"><?= !empty($emp['email']) ? profile_h($emp['email']) : '&mdash;' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Phone</span>
                    <span class="info-val"><?= !empty($emp['phone']) ? profile_h($emp['phone']) : '&mdash;' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Enrolled</span>
                    <span class="info-val"><?= profile_format_date($emp['enroll_date'] ?? null) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key">Fingerprint</span>
                    <span class="info-val">
                        <span class="fp-badge <?= $emp['has_fp'] ? 'fp-yes' : 'fp-no' ?>">
                            <?= $emp['has_fp'] ? '&check; Registered' : '&#9888; Not set' ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="calendar-hours-grid">
            <section class="calendar-card">
                <div class="calendar-top">
                    <h2><?= profile_h($monthStart->format('F Y')) ?></h2>
                    <div class="calendar-nav">
                        <a class="nav-square" href="<?= profile_h(profile_url(array_merge($baseUrlParams, ['month' => $prevMonth]))) ?>" title="Previous month">&lsaquo;</a>
                        <a class="nav-square" href="<?= profile_h(profile_url(array_merge($baseUrlParams, ['month' => $currentMonth]))) ?>" title="Current month">&bull;</a>
                        <a class="nav-square" href="<?= profile_h(profile_url(array_merge($baseUrlParams, ['month' => $nextMonth]))) ?>" title="Next month">&rsaquo;</a>
                    </div>
                </div>

                <div class="calendar-body">
                    <div class="calendar-weekdays">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>

                    <div class="calendar-grid">
                        <?php for ($blank = 1; $blank < $firstWeekday; $blank++): ?>
                            <div class="calendar-day calendar-blank"></div>
                        <?php endfor; ?>

                        <?php foreach ($calendarDays as $calendarDay): ?>
                            <?php
                                $classes = ['calendar-day'];

                                if ($calendarDay['is_present']) {
                                    $classes[] = 'is-present';
                                } elseif ($calendarDay['is_weekend']) {
                                    $classes[] = 'is-weekend';
                                } elseif ($calendarDay['is_future']) {
                                    $classes[] = 'is-future';
                                } else {
                                    $classes[] = 'is-absent';
                                }

                                if ($calendarDay['is_today']) {
                                    $classes[] = 'is-today';
                                }
                            ?>
                            <div class="<?= profile_h(implode(' ', $classes)) ?>" title="<?= profile_h($calendarDay['date']->format('d M Y')) ?>">
                                <span><?= profile_h($calendarDay['date']->format('j')) ?></span>
                                <?php if ($calendarDay['is_present']): ?>
                                    <small><?= number_format((float)$calendarDay['record']['total_hours'], 2) ?>h</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="calendar-footer">
                    <div class="legend">
                        <span class="legend-item"><span class="legend-dot dot-present"></span> Present</span>
                        <span class="legend-item"><span class="legend-dot dot-absent"></span> Absent</span>
                        <span class="legend-item"><span class="legend-dot dot-weekend"></span> Weekend</span>
                    </div>
                    <div class="month-summary">
                        <strong class="present"><?= (int)$presentWorkingDays ?> present</strong>
                        <span>/</span>
                        <strong class="absent"><?= (int)$absentWorkingDays ?> absent</strong>
                        <span>of <?= (int)$monthWorkingDays ?> working days</span>
                    </div>
                </div>
            </section>

            <section class="hours-card">
                <div class="hours-head">
                    <h2>Hours Calculator</h2>
                    <p>Calculate total hours worked in any custom date range</p>
                </div>

                <div class="hours-body">
                    <form class="hours-form" method="get">
                        <input type="hidden" name="id" value="<?= profile_h($staff_id) ?>">
                        <input type="hidden" name="month" value="<?= profile_h($monthStart->format('Y-m')) ?>">

                        <div class="date-fields">
                            <div class="field">
                                <label for="from">From</label>
                                <input type="date" id="from" name="from" value="<?= profile_h($hoursFrom->format('Y-m-d')) ?>">
                            </div>
                            <div class="field">
                                <label for="to">To</label>
                                <input type="date" id="to" name="to" value="<?= profile_h($hoursTo->format('Y-m-d')) ?>">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-full">Calculate Hours</button>
                    </form>

                    <div class="hours-result">
                        <div class="hours-total"><?= number_format($calcTotalHours, 2) ?><span>h</span></div>
                        <div class="hours-range">
                            Total Hours &middot; <?= profile_h(strtoupper($hoursFrom->format('d M Y'))) ?> &mdash; <?= profile_h(strtoupper($hoursTo->format('d M Y'))) ?>
                        </div>
                    </div>

                    <div class="mini-stats">
                        <div class="mini-card">
                            <div class="mini-value green"><?= (int)$calcDaysPresent ?></div>
                            <div class="mini-label">Days Present</div>
                        </div>
                        <div class="mini-card">
                            <div class="mini-value amber"><?= number_format($calcAvgHours, 2) ?></div>
                            <div class="mini-label">Avg Hrs/Day</div>
                        </div>
                        <div class="mini-card">
                            <div class="mini-value red"><?= number_format($calcAttendanceRate, 0) ?>%</div>
                            <div class="mini-label">Attendance Rate</div>
                        </div>
                        <div class="mini-card">
                            <div class="mini-value"><?= (int)$calcWorkingDays ?></div>
                            <div class="mini-label">Working Days</div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="table-wrap">
            <div class="table-header">All Attendance Records (<?= count($records) ?>)</div>

            <?php if (empty($records)): ?>
                <div class="no-data">No attendance records yet.</div>
            <?php else: ?>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Clock In</th>
                                <th>Clock Out</th>
                                <th>Hours Worked</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td class="mono"><?= profile_format_date($record['att_date'] ?? null) ?></td>
                                    <td class="mono"><?= profile_format_time($record['clock_in'] ?? null) ?></td>
                                    <td class="mono"><?= profile_format_time($record['clock_out'] ?? null) ?></td>
                                    <td class="mono"><?= profile_format_hours($record['hours_worked'] ?? null) ?></td>
                                    <td>
                                        <?php if (!empty($record['auto_clocked_out'])): ?>
                                            <span class="badge badge-auto">Auto-out</span>
                                        <?php elseif (!empty($record['clock_out'])): ?>
                                            <span class="badge badge-done">&check; Done</span>
                                        <?php else: ?>
                                            <span class="badge badge-active">&bull; Active</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
