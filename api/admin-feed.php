<?php
require_once __DIR__ . '/../include/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$body      = json_decode(file_get_contents('php://input'), true);
$token     = trim($body['token']         ?? '');
$credId    = trim($body['credential_id'] ?? '');
$publicKey = trim($body['public_key']    ?? '');
$sigCount  = (int)($body['sign_count']   ?? 0);

if (!$token || !$credId || !$publicKey) {
    jsonResponse(['error' => 'token, credential_id, and public_key are required'], 400);
}

// Validate QR token
$stmt = db()->prepare("
    SELECT qt.*, e.full_name
    FROM qr_tokens qt
    JOIN employee e ON qt.staff_id = e.staff_id
    WHERE qt.token = ? AND qt.action = 'register' AND qt.used = 0 AND qt.expires_at > NOW()
");
$stmt->execute([$token]);
$qr = $stmt->fetch();

if (!$qr) {
    jsonResponse(['error' => 'Registration QR is invalid or expired. Ask the admin to generate a new one.'], 410);
}

// Prevent duplicate credential ID
$dup = db()->prepare('SELECT id FROM webauthn_credentials WHERE credential_id = ?');
$dup->execute([$credId]);
if ($dup->fetch()) {
    db()->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);
    jsonResponse(['error' => 'This device is already registered. Reset fingerprint from admin panel first.'], 409);
}

// Delete any existing fingerprints for this staff
db()->prepare('DELETE FROM webauthn_credentials WHERE staff_id = ?')->execute([$qr['staff_id']]);

// Save new credential
db()->prepare('
    INSERT INTO webauthn_credentials (staff_id, credential_id, public_key, sign_count)
    VALUES (?,?,?,?)
')->execute([$qr['staff_id'], $credId, $publicKey, $sigCount]);

// Mark token used
db()->prepare('UPDATE qr_tokens SET used = 1 WHERE token = ?')->execute([$token]);

jsonResponse([
    'success'    => true,
    'staff_id'   => $qr['staff_id'],
    'staff_name' => $qr['full_name'],
    'message'    => 'Fingerprint registered successfully.',
]);<?php
require_once __DIR__ . '/../include/config.php';
requireAdmin();

$db    = db();
$today = date('Y-m-d');

// Live counts
$totalActive = $db->query('SELECT COUNT(*) FROM employee WHERE is_active=1')->fetchColumn();

$stIn = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NULL');
$stIn->execute([$today]);
$countIn = (int)$stIn->fetchColumn();

$stOut = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=? AND clock_out IS NOT NULL');
$stOut->execute([$today]);
$countOut = (int)$stOut->fetchColumn();

$stAtt = $db->prepare('SELECT COUNT(DISTINCT staff_id) FROM attendance WHERE att_date=?');
$stAtt->execute([$today]);
$countAbsent = $totalActive - (int)$stAtt->fetchColumn();

// Staff grid data (used by terminal refresh)
$employees = $db->prepare("
    SELECT
        e.staff_id, e.full_name, e.department, e.designation,
        a.clock_in, a.clock_out, a.hours_worked, a.auto_clocked_out,
        (SELECT COUNT(*) FROM webauthn_credentials w WHERE w.staff_id = e.staff_id) AS has_fp
    FROM employee e
    LEFT JOIN attendance a ON a.staff_id = e.staff_id AND a.att_date = ?
    WHERE e.is_active = 1
    ORDER BY e.full_name ASC
");
$employees->execute([$today]);
$employees = $employees->fetchAll();

// Build staff grid HTML
ob_start();
foreach ($employees as $e):
    $hasIn  = !empty($e['clock_in']);
    $hasOut = !empty($e['clock_out']);
    $statusClass = $hasIn && !$hasOut ? 'status-in' : ($hasOut ? 'status-out' : 'status-absent');
    $pillClass   = $hasIn && !$hasOut ? 'pill-in'   : ($hasOut ? 'pill-out'   : 'pill-absent');
    $nextAction  = ($hasIn && !$hasOut) ? 'clock_out' : 'clock_in';
    $staffJson   = htmlspecialchars(json_encode([
        'staff_id'    => $e['staff_id'],
        'full_name'   => $e['full_name'],
        'department'  => $e['department'],
        'designation' => $e['designation'],
        'clock_in'    => $e['clock_in']  ? date('H:i:s', strtotime($e['clock_in']))  : null,
        'clock_out'   => $e['clock_out'] ? date('H:i:s', strtotime($e['clock_out'])) : null,
        'hours_worked'=> $e['hours_worked'],
        'has_fp'      => (bool)$e['has_fp'],
        'next_action' => $nextAction,
    ]));
?>
<div class="staff-card <?= $statusClass ?>"
     data-name="<?= htmlspecialchars(strtolower($e['full_name'])) ?>"
     data-dept="<?= htmlspecialchars(strtolower($e['department'])) ?>"
     onclick="openOverlay(<?= $staffJson ?>)">
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
        <span class="fp-dot <?= $e['has_fp'] ? 'registered' : '' ?>"
              title="<?= $e['has_fp'] ? 'Fingerprint registered' : 'No fingerprint' ?>"></span>
    </div>
    <?php if ($e['clock_in']): ?>
    <div class="card-time <?= !$hasOut ? 'green' : '' ?>">
        In <?= date('H:i', strtotime($e['clock_in'])) ?>
        <?= $hasOut ? ' · Out ' . date('H:i', strtotime($e['clock_out'])) : '' ?>
    </div>
    <?php endif; ?>
</div>
<?php endforeach;
$gridHtml = ob_get_clean();

// Today's table HTML (used by dashboard)
ob_start();
$records = $db->prepare('
    SELECT a.*, e.full_name, e.department
    FROM attendance a
    JOIN employee e ON a.staff_id = e.staff_id
    WHERE a.att_date = ?
    ORDER BY a.clock_in DESC
');
$records->execute([$today]);
$records = $records->fetchAll();

if (empty($records)): ?>
    <div style="text-align:center;padding:48px;color:#4a5568;font-size:14px">No attendance records yet today.</div>
<?php else: ?>
    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr>
                <th style="padding:11px 20px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#4a5568;border-bottom:1px solid #1a2030;font-weight:500">Employee</th>
                <th style="padding:11px 20px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#4a5568;border-bottom:1px solid #1a2030;font-weight:500">Clock In</th>
                <th style="padding:11px 20px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#4a5568;border-bottom:1px solid #1a2030;font-weight:500">Clock Out</th>
                <th style="padding:11px 20px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#4a5568;border-bottom:1px solid #1a2030;font-weight:500">Hours</th>
                <th style="padding:11px 20px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#4a5568;border-bottom:1px solid #1a2030;font-weight:500">Status</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($records as $r): ?>
            <tr>
                <td style="padding:14px 20px;font-size:13px;border-bottom:1px solid rgba(26,32,48,0.5)">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:32px;height:32px;background:#1e40af;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;font-family:'Syne',sans-serif;flex-shrink:0">
                            <?= strtoupper(substr($r['full_name'],0,2)) ?>
                        </div>
                        <div>
                            <div style="font-weight:500;font-size:13px"><?= htmlspecialchars($r['full_name']) ?></div>
                            <div style="font-size:11px;color:#4a5568"><?= htmlspecialchars($r['department']) ?></div>
                        </div>
                    </div>
                </td>
                <td style="padding:14px 20px;font-size:12px;font-family:monospace;border-bottom:1px solid rgba(26,32,48,0.5)"><?= date('H:i', strtotime($r['clock_in'])) ?></td>
                <td style="padding:14px 20px;font-size:12px;font-family:monospace;border-bottom:1px solid rgba(26,32,48,0.5)"><?= $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : '—' ?></td>
                <td style="padding:14px 20px;font-size:12px;font-family:monospace;border-bottom:1px solid rgba(26,32,48,0.5)"><?= $r['hours_worked'] ? $r['hours_worked'].'h' : '—' ?></td>
                <td style="padding:14px 20px;font-size:13px;border-bottom:1px solid rgba(26,32,48,0.5)">
                    <?php if ($r['auto_clocked_out']): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:100px;font-size:11px;font-weight:600;text-transform:uppercase;background:rgba(245,158,11,0.12);color:#f59e0b">⚠ Auto-out</span>
                    <?php elseif ($r['clock_out']): ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:100px;font-size:11px;font-weight:600;text-transform:uppercase;background:rgba(107,159,255,0.1);color:#6b9fff">✓ Done</span>
                    <?php else: ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:100px;font-size:11px;font-weight:600;text-transform:uppercase;background:rgba(34,197,94,0.1);color:#22c55e">● Active</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif;
$tableHtml = ob_get_clean();

jsonResponse([
    'counts' => [
        'in'     => $countIn,
        'out'    => $countOut,
        'absent' => $countAbsent,
    ],
    'grid_html'  => $gridHtml,
    'html'       => $tableHtml,
]);
