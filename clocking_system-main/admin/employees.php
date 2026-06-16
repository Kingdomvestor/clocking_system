<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

$db      = db();
$success = '';
$error   = '';

// Add employee — no password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $staff_id    = trim($_POST['staff_id']    ?? '');
    $full_name   = trim($_POST['full_name']   ?? '');
    $department  = trim($_POST['department']  ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $email       = trim($_POST['email']       ?? '');
    $phone       = trim($_POST['phone']       ?? '');

    if ($staff_id && $full_name && $department && $designation && $email) {
        try {
            $db->prepare('INSERT INTO employee (staff_id, full_name, department, designation, email, phone, enroll_date) VALUES (?,?,?,?,?,?,CURRENT_DATE)')
               ->execute([$staff_id, $full_name, $department, $designation, $email, $phone]);
            $success = "Staff member '$full_name' added.";
        } catch (PDOException $e) {
            $error = $e->getCode() === '23000' ? 'Staff ID or email already exists.' : $e->getMessage();
        }
    } else {
        $error = 'Please fill in all required fields.';
    }
}

// Deactivate / Activate
if (isset($_GET['deactivate'])) {
    $db->prepare('UPDATE employee SET is_active=FALSE WHERE staff_id=?')->execute([trim($_GET['deactivate'])]);
    $success = 'Employee deactivated.';
}
if (isset($_GET['activate'])) {
    $db->prepare('UPDATE employee SET is_active=TRUE WHERE staff_id=?')->execute([trim($_GET['activate'])]);
    $success = 'Employee reactivated.';
}

// Reset fingerprint
if (isset($_GET['reset_fp'])) {
    $db->prepare('DELETE FROM webauthn_credentials WHERE staff_id=?')->execute([trim($_GET['reset_fp'])]);
    $success = 'Fingerprint reset. Staff must re-register.';
}

$employees = $db->query('
    SELECT e.*,
           (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.staff_id = e.staff_id) AS has_fp
    FROM employee e
    ORDER BY e.is_active DESC, e.full_name ASC
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
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
        .add-btn { padding:10px 20px; background:var(--accent); color:#fff; border:none; border-radius:8px; font-family:'Syne',sans-serif; font-size:13px; font-weight:700; cursor:pointer; transition:opacity .2s; }
        .add-btn:hover { opacity:.88; }

        .content { padding:28px 32px; display:flex; flex-direction:column; gap:24px; }

        .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; transition: opacity 0.5s ease-out;}
        .alert-success { background:rgba(34,197,94,.08); border:1px solid rgba(34,197,94,.2); color:var(--green); }
        .alert-error   { background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); color:#f87171; }

        .add-form-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; display:none; }
        .add-form-wrap.open { display:block; }
        .form-header { padding:20px 24px; border-bottom:1px solid var(--border); font-family:'Syne',sans-serif; font-size:15px; font-weight:700; }
        .add-form { padding:24px; display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .field { display:flex; flex-direction:column; gap:6px;}
        .field.full { grid-column:1/-1; }
        label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); font-weight:600; }
        input { background:#111827; border:1px solid var(--border); border-radius:8px; padding:10px 14px; font-size:13px; color:var(--text); font-family:'DM Sans',sans-serif; outline:none; transition:border-color .2s; }
        input:focus { border-color:var(--accent); }
        .submit-btn { padding:12px 24px; background:var(--accent); color:#fff; border:none; border-radius:8px; font-family:'Syne',sans-serif; font-size:13px; font-weight:700; cursor:pointer; grid-column:1/-1; transition:opacity .2s; }
        .submit-btn:hover { opacity:.88; }

        .table-wrap { background:var(--card); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
        .table-head { padding:18px 24px; border-bottom:1px solid var(--border); font-family:'Syne',sans-serif; font-size:15px; font-weight:700; }
        table { width:100%; border-collapse:collapse; }
        th { padding:11px 18px; text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:1px; color:var(--muted); border-bottom:1px solid var(--border); font-weight:500; }
        td { padding:12px 18px; font-size:13px; border-bottom:1px solid rgba(26,32,48,.5); vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tbody tr { cursor:pointer; transition:background .15s; }
        tbody tr:hover td { background:rgba(30,64,175,.05); }

        .emp-cell { display:flex; align-items:center; gap:10px; }
        .emp-av { width:34px; height:34px; background:var(--accent); border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; font-family:'Syne',sans-serif; flex-shrink:0; }
        .emp-av.inactive { background:#1a2030; color:var(--muted); }

        .status-dot { display:inline-flex; align-items:center; gap:5px; font-size:12px; }
        .dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
        .dot-green { background:var(--green); }
        .dot-gray  { background:var(--muted); }

        .fp-badge { font-size:11px; padding:2px 8px; border-radius:100px; font-weight:600; }
        .fp-yes { background:rgba(34,197,94,.1); color:var(--green); border:1px solid rgba(34,197,94,.2); }
        .fp-no  { background:rgba(245,158,11,.1); color:var(--amber); border:1px solid rgba(245,158,11,.2); }

        .act-link { font-size:12px; color:#6b9fff; text-decoration:none; margin-right:10px; cursor:pointer; transition:color .15s; }
        .act-link:hover { text-decoration:underline; }
        .act-link.red   { color:#f87171; }
        .act-link.amber { color:var(--amber); }

        :root {
        --primary-blue: #1e40af;       /* Deep Blue */
        --accent-orange: #ff4500;     /* OrangeRed */
        --bg-dark: #0d1117;           /* Dark Background */
        --text-light: #e2e8f0;        /* Light Text */
        --border-color: #1a2030;      /* Subtle Border */
        }

        .field {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .field label {
            font-family: 'DM Sans', sans-serif;
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.95rem;
        }

        .field placeholder {

        }

        .field select {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--bg-dark);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-light);
            font-family: 'DM Sans', sans-serif;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            appearance: none; /* Removes default browser arrow */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%23ff4500'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E" );
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 18px;
        }

        /* Hover State */
        .field select:hover {
            border-color: var(--primary-blue);
        }

        /* Focus State */
        .field select:focus {
            outline: none;
            border-color: var(--accent-orange);
            box-shadow: 0 0 0 3px rgba(255, 69, 0, 0.2);
        }

        /* Style for the options dropdown */
        .field select option {
            background-color: #1a2030;
            color: var(--text-light);
            padding: 10px;
        }

        @media (max-width:900px) { body { grid-template-columns:1fr; } .sidebar { display:none; } }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-logo"><svg width="18" height="18" viewBox="0 0 24 24" fill="#6b9fff"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg> ADMIN PANEL</div>
    <a href="<?= SITE_URL ?>/admin/terminal.php"  class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg> Terminal</a>
    <a href="<?= SITE_URL ?>/admin/dashboard.php" class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg> Dashboard</a>
    <a href="<?= SITE_URL ?>/admin/employees.php" class="nav-item active"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> Employees</a>
    <a href="<?= SITE_URL ?>/admin/reports.php"   class="nav-item"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg> Reports</a>
    <div class="sidebar-spacer"></div>
    <div class="sidebar-user">Signed in as <strong style="color:var(--text)"><?= htmlspecialchars($_SESSION['admin_name']) ?></strong></div>
    <a href="<?= SITE_URL ?>/admin/logout.php" class="nav-item danger"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Sign Out</a>
</aside>

<div class="main">
    <div class="topbar">
        <h1>Manage Staff</h1>
        <button class="add-btn" onclick="toggleForm()">+ Add Staff Member</button>
    </div>

    <div class="content">
        <?php if ($success): ?>
            <div class="alert alert-success auto-dismiss">✓ <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error auto-dismiss">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>


        <div class="add-form-wrap" id="addForm">
            <div class="form-header">New Staff Member</div>
            <form method="POST" class="add-form">
                <div class="field">
                    <label>Staff ID *</label>
                    <input type="text" name="staff_id" placeholder="SBS/LEC/001" required>
                </div>
                <div class="field">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" placeholder="Enter your full name" required>
                </div>
                <div class="field">
                    <label>Department *</label>
                    <select name="department" required>
                        <option value="" disabled selected>Select Department</option>
                        <option value="Accountancy">Accountancy</option>
                        <option value="Business Administration">Business Administration</option>
                        <option value="Computer Science">Computer Science</option>
                        <option value="Computer Science">Foundational Class</option>
                    </select>
                </div>

                <div class="field">
                    <label>Designation *</label>
                    <input type="text" name="designation" placeholder="Lecturer" required>
                </div>

                <div class="field">
                    <label>Phone *</label>
                    <input type="phone" name="phone" placeholder="Enter your phone number" required>
                </div>

                <div class="field full">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="Input your e-mail address" required>
                </div>

                <button type="submit" name="add_employee" class="submit-btn">Add Staff Member</button>
            </form>
        </div>

        <div class="table-wrap">
            <div class="table-head">All Staff (<?= count($employees) ?>)</div>
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Staff ID</th>
                        <th>Department</th>
                        <th>Enrolled</th>
                        <th>Fingerprint</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($employees as $e): ?>
                    <tr onclick="window.location='<?= SITE_URL ?>/admin/employee-profile.php?id=<?= urlencode($e['staff_id']) ?>'">
                        <td>
                            <div class="emp-cell">
                                <div class="emp-av <?= !$e['is_active'] ? 'inactive' : '' ?>"><?= strtoupper(substr($e['full_name'],0,2)) ?></div>
                                <?= htmlspecialchars($e['full_name']) ?>
                            </div>
                        </td>
                        <td style="font-family:monospace;font-size:12px"><?= htmlspecialchars($e['staff_id']) ?></td>
                        <td style="color:var(--muted)"><?= htmlspecialchars($e['department']) ?></td>
                        <td style="color:var(--muted);font-size:12px"><?= date('d M Y', strtotime($e['enroll_date'])) ?></td>
                        <td>
                            <?php if ($e['has_fp']): ?>
                                <span class="fp-badge fp-yes">✓ Registered</span>
                            <?php else: ?>
                                <span class="fp-badge fp-no">⚠ Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($e['is_active']): ?>
                                <span class="status-dot"><span class="dot dot-green"></span> Active</span>
                            <?php else: ?>
                                <span class="status-dot"><span class="dot dot-gray"></span> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <a href="<?= SITE_URL ?>/admin/employee-profile.php?id=<?= urlencode($e['staff_id']) ?>" class="act-link">Profile</a>
                            <?php if ($e['has_fp']): ?>
                                <a href="?reset_fp=<?= urlencode($e['staff_id']) ?>" class="act-link amber" onclick="return confirm('Reset fingerprint for <?= htmlspecialchars($e['full_name']) ?>?')">Reset FP</a>
                            <?php endif; ?>
                            <?php if ($e['is_active']): ?>
                                <a href="?deactivate=<?= urlencode($e['staff_id']) ?>" class="act-link red" onclick="return confirm('Deactivate <?= htmlspecialchars($e['full_name']) ?>?')">Deactivate</a>
                            <?php else: ?>
                                <a href="?activate=<?= urlencode($e['staff_id']) ?>" class="act-link">Activate</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleForm() { document.getElementById('addForm').classList.toggle('open'); }
</script>
</body>
</html>
