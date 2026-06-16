<?php
require_once __DIR__ . '/../include/config.php';

$token = trim($_GET['token'] ?? '');

if (!$token) {
    http_response_code(400);
    $pageError = 'No token provided. Please scan a valid QR code from the admin terminal.';
}

// Validate token upfront (fast fail before rendering)
$qr = null;
if (!isset($pageError)) {
    $stmt = db()->prepare('
        SELECT qt.*, e.full_name, e.department
        FROM qr_tokens qt
        JOIN employee e ON qt.staff_id = e.staff_id
        WHERE qt.token = ? AND qt.used = 0 AND qt.expires_at > NOW()
    ');
    $stmt->execute([$token]);
    $qr = $stmt->fetch();

    if (!$qr) {
        $pageError = 'This QR code has expired or already been used. Please ask the admin to generate a new one.';
    }
}

$actionLabel = [
    'clock_in'  => 'Clock In',
    'clock_out' => 'Clock Out',
    'register'  => 'Register Fingerprint',
][$qr['action'] ?? ''] ?? 'Verify';

$actionColor = [
    'clock_in'  => '#22c55e',
    'clock_out' => '#6b9fff',
    'register'  => '#1e40af',
][$qr['action'] ?? ''] ?? '#1e40af';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($actionLabel) ?> — <?= SITE_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@600;700;800&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --accent: #1e40af;
            --dark:   #060912;
            --card:   #0d1117;
            --border: #1a2030;
            --text:   #e2e8f0;
            --muted:  #4a5568;
            --green:  #22c55e;
            --amber:  #f59e0b;
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
                radial-gradient(ellipse at 30% 20%, rgba(30,64,175,0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 70% 80%, rgba(30,64,175,0.07) 0%, transparent 60%);
            pointer-events: none;
        }
        .container {
            width: 100%;
            max-width: 380px;
            position: relative;
            z-index: 1;
        }
        .school-name {
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--muted);
            margin-bottom: 28px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 32px 28px;
            text-align: center;
        }

        /* Staff info */
        .staff-av {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--accent), #1e3a8a);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 24px;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(30,64,175,0.3);
        }
        .staff-name {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 20px;
            margin-bottom: 4px;
        }
        .staff-dept { font-size: 13px; color: var(--muted); margin-bottom: 20px; }

        .action-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 28px;
        }

        /* Divider */
        .divider { height: 1px; background: var(--border); margin: 0 0 28px; }

        /* Fingerprint button */
        .fp-btn {
            width: 100%;
            padding: 18px;
            border: none;
            border-radius: 14px;
            font-family: 'Syne', sans-serif;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            transition: opacity 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            letter-spacing: 0.3px;
        }
        .fp-btn:hover  { opacity: 0.9; }
        .fp-btn:active { transform: scale(0.97); }
        .fp-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .fp-icon { font-size: 22px; }

        /* State screens */
        .state {
            display: none;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
        }
        .state.visible { display: flex; }
        .state-icon { font-size: 56px; line-height: 1; }
        .state-title {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 22px;
        }
        .state-sub { font-size: 14px; color: var(--muted); line-height: 1.6; }
        .state-sub strong { color: var(--text); }

        /* Error banner */
        .error-banner {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.25);
            border-radius: 12px;
            padding: 16px;
            font-size: 13px;
            color: #f87171;
            line-height: 1.6;
            text-align: center;
        }

        /* Loading spinner */
        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .hint { font-size: 12px; color: var(--muted); margin-top: 8px; line-height: 1.6; }
    </style>
</head>
<body>
<div class="container">
    <div class="school-name">Soteria Business School</div>

    <div class="card">

        <?php if (isset($pageError)): ?>
            <div class="error-banner">
                <div style="font-size:32px;margin-bottom:12px">⚠️</div>
                <strong>Invalid QR Code</strong><br>
                <?= htmlspecialchars($pageError) ?>
            </div>

        <?php else: ?>
            <!-- Staff info (shown initially) -->
            <div id="infoPanel">
                <div class="staff-av"><?= strtoupper(substr($qr['full_name'],0,2)) ?></div>
                <div class="staff-name"><?= htmlspecialchars($qr['full_name']) ?></div>
                <div class="staff-dept"><?= htmlspecialchars($qr['department']) ?></div>

                <span class="action-badge" style="background:<?= $actionColor ?>22;color:<?= $actionColor ?>;border:1px solid <?= $actionColor ?>44">
                    <?= htmlspecialchars($actionLabel) ?>
                </span>

                <div class="divider"></div>

                <!-- Error message area -->
                <div id="errMsg" style="display:none;margin-bottom:16px" class="error-banner"></div>

                <!-- Main button -->
                <button class="fp-btn" id="fpBtn"
                    style="background:<?= $actionColor ?>;color:<?= $qr['action'] === 'register' ? '#fff' : '#000' ?>"
                    onclick="startAuth()">
                    <span class="fp-icon">
                        <?= $qr['action'] === 'register' ? '➕' : '☝️' ?>
                    </span>
                    <?= $qr['action'] === 'register' ? 'Register Fingerprint' : 'Verify Fingerprint' ?>
                </button>
                <div class="hint">
                    <?php if ($qr['action'] === 'register'): ?>
                        This will save your fingerprint to this device.<br>You only need to do this once.
                    <?php else: ?>
                        Press and hold your fingerprint sensor or use Face ID.
                    <?php endif; ?>
                </div>
            </div>

            <!-- Loading state -->
            <div class="state" id="stateLoading">
                <div class="spinner"></div>
                <div class="state-title" style="font-size:16px;color:var(--muted)" id="loadingMsg">Preparing…</div>
            </div>

            <!-- Success state -->
            <div class="state" id="stateSuccess">
                <div class="state-icon">✅</div>
                <div class="state-title" style="color:#22c55e" id="successTitle">Done!</div>
                <div class="state-sub" id="successMsg"></div>
            </div>

            <!-- Error state -->
            <div class="state" id="stateError">
                <div class="state-icon">❌</div>
                <div class="state-title" style="color:#f87171">Failed</div>
                <div class="state-sub" id="errorMsg"></div>
                <button onclick="resetUI()" style="margin-top:8px;padding:10px 24px;background:var(--accent);color:#fff;border:none;border-radius:8px;font-family:'Syne',sans-serif;font-weight:700;font-size:13px;cursor:pointer">Try Again</button>
            </div>

        <?php endif; ?>
    </div>
</div>

<?php if (!isset($pageError)): ?>
<script>
const SITE_URL = <?= json_encode(SITE_URL) ?>;
const TOKEN    = <?= json_encode($token) ?>;
const ACTION   = <?= json_encode($qr['action']) ?>;

function showState(id) {
    document.getElementById('infoPanel').style.display  = 'none';
    document.getElementById('stateLoading').classList.remove('visible');
    document.getElementById('stateSuccess').classList.remove('visible');
    document.getElementById('stateError').classList.remove('visible');
    if (id) document.getElementById(id).classList.add('visible');
}

function resetUI() {
    document.getElementById('infoPanel').style.display = 'block';
    document.getElementById('errMsg').style.display    = 'none';
    document.getElementById('stateLoading').classList.remove('visible');
    document.getElementById('stateSuccess').classList.remove('visible');
    document.getElementById('stateError').classList.remove('visible');
    document.getElementById('fpBtn').disabled = false;
}

function showInlineError(msg) {
    const el = document.getElementById('errMsg');
    el.textContent = msg;
    el.style.display = 'block';
    document.getElementById('fpBtn').disabled = false;
}

// Base64url helpers
function b64urlToBuffer(b64url) {
    const b64 = b64url.replace(/-/g,'+').replace(/_/g,'/');
    const raw = atob(b64);
    return Uint8Array.from(raw, c => c.charCodeAt(0));
}
function bufferToB64url(buf) {
    return btoa(String.fromCharCode(...new Uint8Array(buf)))
        .replace(/\+/g,'-').replace(/\//g,'_').replace(/=/g,'');
}

async function startAuth() {
    document.getElementById('fpBtn').disabled = true;

    if (!window.PublicKeyCredential) {
        showInlineError('Your browser does not support fingerprint / biometric authentication (WebAuthn). Please try a different browser or device.');
        return;
    }

    // Get challenge from server
    showState('stateLoading');
    document.getElementById('loadingMsg').textContent = ACTION === 'register' ? 'Preparing registration…' : 'Preparing verification…';

    let challengeData;
    try {
        const res = await fetch(SITE_URL + '/api/webauthn-challenge.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token: TOKEN, type: ACTION === 'register' ? 'create' : 'get' })
        });
        challengeData = await res.json();
        if (challengeData.error) throw new Error(challengeData.error);
    } catch(e) {
        showState(null);
        resetUI();
        showInlineError(e.message || 'Could not connect to server. Please try again.');
        return;
    }

    document.getElementById('loadingMsg').textContent = ACTION === 'register' ? 'Scan your fingerprint…' : 'Verify your fingerprint…';

    try {
        if (ACTION === 'register') {
            await doRegister(challengeData);
        } else {
            await doVerify(challengeData);
        }
    } catch(e) {
        showState(null);
        resetUI();
        const msg = e.name === 'NotAllowedError'
            ? 'Fingerprint verification was cancelled or timed out. Please try again.'
            : e.message || 'Authentication failed. Please try again.';
        showInlineError(msg);
    }
}

// ── Register ────────────────────────────────────────────────────────────────
async function doRegister(ch) {
    const rpId = ch.rp_id || location.hostname;

    const credential = await navigator.credentials.create({
        publicKey: {
            challenge:          b64urlToBuffer(ch.challenge),
            rp:                 { id: rpId, name: ch.rp_name },
            user: {
                id:          b64urlToBuffer(ch.user_handle),
                name:        ch.staff_id,
                displayName: ch.staff_name,
            },
            pubKeyCredParams:   [{ alg: -7, type: 'public-key' }, { alg: -257, type: 'public-key' }],
            authenticatorSelection: {
                authenticatorAttachment: 'platform',
                requireResidentKey:      false,
                userVerification:        'required',
            },
            timeout: 60000,
            attestation: 'none',
        }
    });

    document.getElementById('loadingMsg').textContent = 'Saving fingerprint…';

    const credId    = bufferToB64url(credential.rawId);
    const publicKey = bufferToB64url(credential.response.getPublicKey
        ? credential.response.getPublicKey()
        : credential.response.attestationObject);

    const res  = await fetch(SITE_URL + '/api/register-finger.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            token:         TOKEN,
            credential_id: credId,
            public_key:    publicKey,
            sign_count:    0,
        })
    });
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    showState('stateSuccess');
    document.getElementById('successTitle').textContent = 'Registered!';
    document.getElementById('successMsg').innerHTML =
        '<strong>' + data.staff_name + '</strong><br>Your fingerprint has been saved.<br>You can now clock in and out.';
}

// ── Verify (clock in / clock out) ───────────────────────────────────────────
async function doVerify(ch) {
    const rpId = ch.rp_id || location.hostname;

    const allowCreds = (ch.allow_credentials || []).map(c => ({
        type: 'public-key',
        id:   b64urlToBuffer(c.id),
    }));

    if (allowCreds.length === 0) {
        throw new Error('No fingerprint registered for this staff member. Please ask admin to register your fingerprint first.');
    }

    const assertion = await navigator.credentials.get({
        publicKey: {
            challenge:        b64urlToBuffer(ch.challenge),
            rpId:             rpId,
            allowCredentials: allowCreds,
            userVerification: 'required',
            timeout:          60000,
        }
    });

    document.getElementById('loadingMsg').textContent = 'Recording attendance…';

    const credId    = bufferToB64url(assertion.rawId);
    const sigCount  = assertion.response.authenticatorData
        ? new DataView(assertion.response.authenticatorData).getUint32(33)
        : 0;

    const res  = await fetch(SITE_URL + '/api/verify-attendance.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            token:         TOKEN,
            credential_id: credId,
            sign_count:    sigCount,
        })
    });
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    showState('stateSuccess');
    const actionLabels = {
        clock_in:  'Clocked In',
        clock_out: 'Clocked Out',
    };
    document.getElementById('successTitle').textContent = actionLabels[data.action] || 'Done!';
    document.getElementById('successMsg').innerHTML =
        '<strong>' + data.staff_name + '</strong><br>'
        + (data.action === 'clock_in' ? 'Clocked in at ' : 'Clocked out at ')
        + '<strong>' + data.time + '</strong>'
        + (data.hours_worked ? '<br>Hours worked: <strong>' + data.hours_worked + 'h</strong>' : '');
}
</script>
<?php endif; ?>
</body>
</html>
