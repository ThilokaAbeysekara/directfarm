<?php
// change_password.php  (place in root directfarm/ folder, works for all roles)
require_once 'includes/config.php';
requireLogin('index.php');

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($new) || empty($confirm)) {
        $msg = 'error:Please fill in all fields.';
    } elseif (strlen($new) < 6) {
        $msg = 'error:New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $msg = 'error:New passwords do not match.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!password_verify($current, $user['password'])) {
            $msg = 'error:Current password is incorrect.';
        } else {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $upd    = $db->prepare("UPDATE users SET password=? WHERE id=?");
            $upd->bind_param("si", $hashed, $_SESSION['user_id']);
            $upd->execute();
            $msg = 'success:Password changed successfully!';
        }
    }
}

// Determine back link based on role
$backLink = match($_SESSION['role']) {
    'farmer'   => 'farmer/dashboard.php?tab=profile',
    'consumer' => 'consumer/dashboard.php?tab=profile',
    'admin'    => 'admin/dashboard.php',
    default    => 'index.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Change Password - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .pw-wrap { max-width:480px; margin:60px auto; padding:0 20px; }
        .pw-card { background:white; border-radius:16px; padding:38px; box-shadow:0 4px 20px rgba(0,0,0,0.09); }
        .pw-card h2 { font-size:22px; margin-bottom:6px; color:#222; }
        .pw-card p.sub { color:#888; font-size:14px; margin-bottom:28px; }
        .fg { margin-bottom:20px; }
        .fg label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:7px; }
        .pw-input-wrap { position:relative; }
        .pw-input-wrap input { width:100%; padding:12px 42px 12px 14px; border:1.5px solid #eee; border-radius:10px; font-size:15px; background:#fafafa; transition:.2s; }
        .pw-input-wrap input:focus { outline:none; border-color:#56ad58; background:#fff; }
        .pw-toggle { position:absolute; right:13px; top:50%; transform:translateY(-50%); cursor:pointer; color:#aaa; font-size:15px; }
        .pw-toggle:hover { color:#56ad58; }
        .strength-bar { height:5px; border-radius:3px; margin-top:8px; background:#eee; overflow:hidden; }
        .strength-fill { height:100%; border-radius:3px; transition:.4s; width:0; }
        .strength-label { font-size:12px; margin-top:4px; font-weight:600; }
        .requirements { background:#f8f9fa; border-radius:10px; padding:14px 16px; margin-bottom:22px; }
        .requirements p { font-size:13px; color:#666; margin-bottom:6px; font-weight:600; }
        .req { font-size:13px; color:#aaa; margin:4px 0; display:flex; align-items:center; gap:7px; }
        .req.met { color:#27ae60; }
        .req i { width:14px; }
        .submit-pw { width:100%; background:#56ad58; color:white; padding:14px; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; transition:.2s; }
        .submit-pw:hover { background:#3f8e44; }
        .alert { padding:13px 16px; border-radius:10px; margin-bottom:20px; font-weight:500; font-size:14px; }
        .alert-success { background:#e0ffe8; color:#27ae60; }
        .alert-error { background:#ffe0e0; color:#c0392b; }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="pw-wrap">
    <a href="<?= $backLink ?>" style="display:inline-flex; align-items:center; gap:8px; color:#56ad58; text-decoration:none; font-weight:600; margin-bottom:22px; font-size:14px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Profile
    </a>

    <div class="pw-card">
        <h2>🔒 Change Password</h2>
        <p class="sub">Keep your account secure with a strong password.</p>

        <?php if ($msg): list($type,$text) = explode(':', $msg, 2); ?>
        <div class="alert alert-<?= $type === 'success' ? 'success' : 'error' ?>">
            <?= $type === 'success' ? '✓' : '✗' ?> <?= htmlspecialchars($text) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="pwForm">
            <!-- Current Password -->
            <div class="fg">
                <label>Current Password</label>
                <div class="pw-input-wrap">
                    <input type="password" name="current_password" id="currentPw" placeholder="Your current password" required autocomplete="current-password">
                    <i class="fa-solid fa-eye pw-toggle" onclick="togglePw('currentPw', this)"></i>
                </div>
            </div>

            <!-- New Password -->
            <div class="fg">
                <label>New Password</label>
                <div class="pw-input-wrap">
                    <input type="password" name="new_password" id="newPw" placeholder="Min. 6 characters" required autocomplete="new-password" oninput="checkStrength(this.value); checkReqs(this.value);">
                    <i class="fa-solid fa-eye pw-toggle" onclick="togglePw('newPw', this)"></i>
                </div>
                <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
                <div class="strength-label" id="strengthLabel" style="color:#aaa;"></div>
            </div>

            <!-- Password Requirements -->
            <div class="requirements">
                <p>Password must include:</p>
                <div class="req" id="req-len"><i class="fa-solid fa-circle-xmark"></i> At least 6 characters</div>
                <div class="req" id="req-upper"><i class="fa-solid fa-circle-xmark"></i> An uppercase letter</div>
                <div class="req" id="req-num"><i class="fa-solid fa-circle-xmark"></i> A number</div>
            </div>

            <!-- Confirm Password -->
            <div class="fg">
                <label>Confirm New Password</label>
                <div class="pw-input-wrap">
                    <input type="password" name="confirm_password" id="confirmPw" placeholder="Repeat new password" required autocomplete="new-password" oninput="checkMatch()">
                    <i class="fa-solid fa-eye pw-toggle" onclick="togglePw('confirmPw', this)"></i>
                </div>
                <div id="matchMsg" style="font-size:13px; margin-top:6px;"></div>
            </div>

            <button type="submit" class="submit-pw">Change Password</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function togglePw(id, icon) {
    const input = document.getElementById(id);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye','fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash','fa-eye');
    }
}

function checkStrength(val) {
    const fill  = document.getElementById('strengthFill');
    const label = document.getElementById('strengthLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const levels = [
        { w:'0%',   c:'#eee',    t:'' },
        { w:'25%',  c:'#e74c3c', t:'Weak' },
        { w:'50%',  c:'#f39c12', t:'Fair' },
        { w:'75%',  c:'#3498db', t:'Good' },
        { w:'90%',  c:'#27ae60', t:'Strong' },
        { w:'100%', c:'#1a8a42', t:'Very Strong' },
    ];
    const l = levels[Math.min(score, 5)];
    fill.style.width = l.w;
    fill.style.background = l.c;
    label.textContent = l.t;
    label.style.color = l.c;
}

function checkReqs(val) {
    setReq('req-len',   val.length >= 6);
    setReq('req-upper', /[A-Z]/.test(val));
    setReq('req-num',   /[0-9]/.test(val));
}

function setReq(id, met) {
    const el = document.getElementById(id);
    el.classList.toggle('met', met);
    el.querySelector('i').className = met ? 'fa-solid fa-circle-check' : 'fa-solid fa-circle-xmark';
}

function checkMatch() {
    const nw = document.getElementById('newPw').value;
    const cf = document.getElementById('confirmPw').value;
    const el = document.getElementById('matchMsg');
    if (!cf) { el.textContent = ''; return; }
    if (nw === cf) {
        el.textContent = '✓ Passwords match';
        el.style.color = '#27ae60';
    } else {
        el.textContent = '✗ Passwords do not match';
        el.style.color = '#e74c3c';
    }
}
</script>
</body>
</html>
