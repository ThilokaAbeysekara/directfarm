<?php

require_once __DIR__ . '/config.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$basePath = '';

if (strpos($_SERVER['PHP_SELF'], '/farmer/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/consumer/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $basePath = '../';
}
?>
<header class="navbar">
    <div class="container nav-content">
        <div class="logo">
            <img src="<?= $basePath ?>DirectFarm logo.png" alt="logo">
            <h2>DirectFarm LK</h2>
        </div>

        <nav style="font-size:15px; font-weight:600;">
            <a href="<?= $basePath ?>index.php" <?= $currentPage==='index.php'?'style="color:#009933"':'' ?>>Home</a>
            <a href="<?= $basePath ?>about.php" <?= $currentPage==='about.php'?'style="color:#009933"':'' ?>>About</a>
            <a href="<?= $basePath ?>marketplace.php" <?= $currentPage==='marketplace.php'?'style="color:#009933"':'' ?>>Marketplace</a>
            <a href="<?= $basePath ?>insights.php" <?= $currentPage==='insights.php'?'style="color:#009933"':''  ?>>Insights</a>
            <a href="<?= $basePath ?>news.php" <?= $currentPage==='news.php'?'style="color:#009933"':'' ?>>News</a>
            <a href="<?= $basePath ?>forum.php" <?= $currentPage==='forum.php'?'style="color:#009933"':'' ?>>Forum</a>
            <a href="<?= $basePath ?>logistics.php" <?= $currentPage==='logistics.php'?'style="color:#009933"':'' ?>>Delivery</a>
            <a href="<?= $basePath ?>faq.php" <?= $currentPage==='faq.php'?'style="color:#009933"':'' ?>>FAQ</a>
            <a href="<?= $basePath ?>contactus.php" <?= $currentPage==='contactus.php'?'style="color:#009933"':'' ?>>Contact</a>
        </nav>

        <div class="nav-buttons" id="navAuthArea">
            <?php if (isLoggedIn()): ?>
                <?php
    $navPic = '';
    if (isset($_SESSION['user_id'])) {
        $navDb = getDB();
        $npStmt = $navDb->prepare("SELECT profile_pic FROM users WHERE id=?");
        $npStmt->bind_param("i", $_SESSION['user_id']);
        $npStmt->execute();
        $navPic = $npStmt->get_result()->fetch_assoc()['profile_pic'] ?? '';
    }
    ?>
    <span class="nav-username" style="display:flex;align-items:center;gap:8px;">
        <?php if ($navPic && file_exists($basePath.$navPic)): ?>
            <img src="<?= $basePath.htmlspecialchars($navPic) ?>?t=<?= time() ?>" style="width:30px;height:30px;border-radius:50%;object-fit:cover;border:2px solid #009933;">
        <?php else: ?>
            <span style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#009933,#006622);display:inline-flex;align-items:center;justify-content:center;color:white;font-size:13px;font-weight:700;"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></span>
        <?php endif; ?>
        <?= htmlspecialchars($_SESSION['user_name']) ?>
    </span>
                <?php if (hasRole('farmer')): ?>
                    <a href="<?= $basePath ?>farmer/dashboard.php" class="nav-dashboard-btn">Dashboard</a>
                <?php elseif (hasRole('consumer')): ?>
                    <a href="<?= $basePath ?>consumer/dashboard.php" class="nav-dashboard-btn">Dashboard</a>
                    <a href="<?= $basePath ?>consumer/cart.php" class="nav-cart-btn">🛒 Cart<?php
                        $db = getDB();
                        $uid = $_SESSION['user_id'];
                        $cstmt = $db->prepare("SELECT COUNT(*) as cnt FROM cart WHERE consumer_id=?");
                        if ($cstmt) { $cstmt->bind_param("i", $uid); $cstmt->execute();
                        $cnt = $cstmt->get_result()->fetch_assoc()['cnt'];
                        if ($cnt > 0) echo " ($cnt)"; }
                    ?></a>
                <?php elseif (hasRole('admin')): ?>
                    <a href="<?= $basePath ?>admin/dashboard.php" class="nav-dashboard-btn">Dashboard</a>
                <?php endif; ?>
                <form method="POST" action="<?= $basePath ?>includes/auth.php" style="display:inline;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="nav-logout-btn" style="background:#e74c3c;color:white;border-color:#e74c3c;">Logout</button>
                </form>
            <?php else: ?>
                <button class="login-btn" onclick="openAuth('login')">Login</button>
                <button class="register-btn" onclick="openAuth('register')">Register</button>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php if (!isLoggedIn()): ?>
<!-- AUTH MODAL -->
<div class="modal-overlay" id="authModal" style="align-items:flex-start; padding:30px 0; overflow-y:auto;">
    <div class="modal-box" style="max-height:90vh; overflow-y:auto; padding:28px;">
        <span class="close-modal" onclick="document.getElementById('authModal').style.display='none'">&times;</span>
        <h2>Join DirectFarm LK</h2>
        <p class="modal-subtitle">Directly connecting you to the heart of Sri Lankan farming.</p>

        <div class="auth-toggle">
            <button id="toggleLogin" class="active" onclick="setAuthMode('login')">Login</button>
            <button id="toggleRegister" onclick="setAuthMode('register')">Register</button>
        </div>

        <div id="authMessage" style="display:none; padding:10px; border-radius:8px; margin-bottom:15px; font-size:14px;"></div>

        <!-- Forgot Password Form - Multi Step -->
        <div id="forgotForm" style="display:none;">
            <div style="text-align:center; margin-bottom:18px;">
                <div style="font-size:40px; margin-bottom:8px;">🔑</div>
                <h3 style="font-size:18px; color:#1a2e1a;">Reset Password</h3>
                <p style="font-size:13px; color:#888;" id="forgotSubtitle">Enter your email to receive an OTP</p>
            </div>
            <div id="forgotMsg" style="display:none; padding:10px; border-radius:8px; margin-bottom:14px; font-size:14px;"></div>

            <!-- Step 1: Enter Email -->
            <div id="forgotStep1">
                <div class="input-group">
                    <label>Email Address</label>
                    <input type="email" id="forgotEmail" placeholder="your@email.com" autocomplete="off">
                </div>
                <button onclick="submitForgotStep1()" style="width:100%;padding:14px;background:#009933;color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px;">Send OTP</button>
            </div>

            <!-- Step 2: Verify OTP -->
            <div id="forgotStep2" style="display:none;">
                <p style="font-size:13px; color:#666; margin-bottom:12px; text-align:center;">
                    ✓ OTP has been sent to your email<br>
                    <span style="font-size:11px; color:#999;">Valid for 10 minutes</span>
                </p>
                <div class="input-group">
                    <label>Enter OTP</label>
                    <input type="text" id="forgotOTP" placeholder="000000" autocomplete="off" maxlength="6" style="letter-spacing:8px; font-size:18px; text-align:center; font-weight:700;">
                </div>
                <button onclick="submitForgotStep2()" style="width:100%;padding:14px;background:#009933;color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px;">Verify OTP</button>
                <div style="text-align:center;margin-top:12px;">
                    <a href="#" onclick="resetForgotForm(event)" style="font-size:13px;color:#888;text-decoration:none;">← Try another email</a>
                </div>
            </div>

            <!-- Step 3: Create New Password -->
            <div id="forgotStep3" style="display:none;">
                <div class="input-group">
                    <label>New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="forgotNewPass" placeholder="Min. 6 characters" autocomplete="new-password" style="width:100%; padding-right:48px; box-sizing:border-box;">
                        <span onclick="togglePass('forgotNewPass','eyeIcon2')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;font-size:16px;z-index:10;user-select:none;">
                            <i class="fa-solid fa-eye" id="eyeIcon2"></i>
                        </span>
                    </div>
                </div>
                <div class="input-group">
                    <label>Confirm Password</label>
                    <div style="position:relative;">
                        <input type="password" id="forgotConfirmPass" placeholder="Repeat new password" autocomplete="new-password" style="width:100%; padding-right:48px; box-sizing:border-box;">
                        <span onclick="togglePass('forgotConfirmPass','eyeIcon3')" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;font-size:16px;z-index:10;user-select:none;">
                            <i class="fa-solid fa-eye" id="eyeIcon3"></i>
                        </span>
                    </div>
                </div>
                <button onclick="submitForgotStep3()" style="width:100%;padding:14px;background:#009933;color:white;border:none;border-radius:12px;font-size:15px;font-weight:700;cursor:pointer;margin-top:4px;">Reset Password</button>
            </div>

            <div style="text-align:center;margin-top:14px;">
                <a href="#" onclick="hideForgotForm()" style="font-size:13px;color:#888;text-decoration:none;">← Back to Login</a>
            </div>
        </div>

        <div id="nameField" style="display:none;" class="input-group">
            <label>Full Name</label>
            <input type="text" placeholder="e.g. Kamal Perera" id="regName" autocomplete="off">
        </div>
        <div class="input-group">
            <label>Email Address</label>
            <input type="email" placeholder="kamal@email.com" id="regEmail" autocomplete="off">
        </div>
        <div id="phoneField" style="display:none;" class="input-group">
            <label>Phone Number</label>
            <input type="tel" placeholder="+94 77 123 4567" id="regPhone" autocomplete="off">
        </div>
        <div id="districtField" style="display:none;" class="input-group">
            <label>District</label>
            <select id="regDistrict" style="width:100%;padding:14px 18px;border:1.5px solid #eee;border-radius:12px;background:#fafafa;font-size:1rem;">
                <option value="">Select District</option>
                <option>Colombo</option><option>Gampaha</option><option>Kalutara</option>
                <option>Kandy</option><option>Matale</option><option>Nuwara Eliya</option>
                <option>Galle</option><option>Matara</option><option>Hambantota</option>
                <option>Jaffna</option><option>Kilinochchi</option><option>Mannar</option>
                <option>Vavuniya</option><option>Trincomalee</option><option>Batticaloa</option>
                <option>Ampara</option><option>Kurunegala</option><option>Puttalam</option>
                <option>Anuradhapura</option><option>Polonnaruwa</option><option>Badulla</option>
                <option>Monaragala</option><option>Ratnapura</option><option>Kegalle</option>
            </select>
        </div>
        <div class="input-group">
            <label>Password</label>
            <div style="position:relative;">
                <input type="password" placeholder="Min. 6 characters" id="regPass" autocomplete="new-password" style="width:100%; padding-right:48px; box-sizing:border-box; position:relative; z-index:1;">
                <i class="fa-solid fa-eye" id="eyeIcon" onclick="togglePass('regPass','eyeIcon')" style="position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;font-size:16px;z-index:99;"></i>
            </div>
        </div>
        <div id="forgotLink" style="text-align:right; margin-top:-8px; margin-bottom:12px;">
            <a href="#" onclick="showForgotForm()" style="font-size:13px; color:#009933; text-decoration:none;">Forgot password?</a>
        </div>
        <div class="role-selection" id="roleSelection" style="display:none;">
            <p>Register as a:</p>
            <label><input type="radio" name="role" value="consumer" checked> 🛒 Consumer</label>
            <label><input type="radio" name="role" value="farmer"> 🧑‍🌱 Farmer</label>
        </div>
        <button class="submit-btn" id="submitBtn" onclick="submitAuth()">Login</button>
    </div>
</div>

<script>
let authMode = 'login';
function openAuth(mode, forceRole) {
    document.getElementById('authModal').style.display = 'flex';
    setAuthMode(mode);
    if (forceRole) {
        const radio = document.querySelector(`input[name="role"][value="${forceRole}"]`);
        if (radio) radio.checked = true;
    }
    clearAuthMessage();
}
function setAuthMode(mode) {
    authMode = mode;
    const isRegister = mode === 'register';
    document.getElementById('nameField').style.display = isRegister ? 'block' : 'none';
    document.getElementById('phoneField').style.display = isRegister ? 'block' : 'none';
    document.getElementById('districtField').style.display = isRegister ? 'block' : 'none';
    document.getElementById('roleSelection').style.display = isRegister ? 'block' : 'none';
    document.getElementById('submitBtn').innerText = isRegister ? 'Create Account' : 'Login';
    document.getElementById('toggleLogin').classList.toggle('active', !isRegister);
    document.getElementById('toggleRegister').classList.toggle('active', isRegister);
    // Hide forgot password link on register tab
    const fl = document.getElementById('forgotLink');
    if (fl) fl.style.display = isRegister ? 'none' : 'block';
    clearAuthMessage();
}
function showAuthMessage(msg, isError) {
    const el = document.getElementById('authMessage');
    el.style.display = 'block';
    el.style.background = isError ? '#ffe0e0' : '#e0ffe8';
    el.style.color = isError ? '#c0392b' : '#27ae60';
    el.innerText = msg;
}
function clearAuthMessage() {
    document.getElementById('authMessage').style.display = 'none';
}
function submitAuth() {
    const email = document.getElementById('regEmail').value.trim();
    const pass  = document.getElementById('regPass').value;
    if (!email || !pass) { showAuthMessage('Please fill in all required fields.', true); return; }

    const body = new URLSearchParams({ action: authMode, email, password: pass });
    if (authMode === 'register') {
        const name = document.getElementById('regName').value.trim();
        if (!name) { showAuthMessage('Please enter your name.', true); return; }
        body.append('name', name);
        body.append('phone', document.getElementById('regPhone').value);
        body.append('district', document.getElementById('regDistrict').value);
        body.append('role', document.querySelector('input[name="role"]:checked').value);
    }

    document.getElementById('submitBtn').innerText = 'Please wait...';
    document.getElementById('submitBtn').disabled = true;

    fetch('<?= $basePath ?>includes/auth.php', { method: 'POST', body })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAuthMessage('Success! Redirecting...', false);
                setTimeout(() => window.location.href = data.redirect, 800);
            } else {
                showAuthMessage(data.message || 'Something went wrong.', true);
                document.getElementById('submitBtn').innerText = authMode === 'register' ? 'Create Account' : 'Login';
                document.getElementById('submitBtn').disabled = false;
            }
        });
}
window.addEventListener('click', e => {
    if (e.target.id === 'authModal') e.target.style.display = 'none';
});

//  Eye icon toggle 
function togglePass(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) icon.className = 'fa-solid fa-eye-slash';
    } else {
        input.type = 'password';
        if (icon) icon.className = 'fa-solid fa-eye';
    }
}

// Forgot Password 
function showForgotForm() {
    // Hide normal login fields
    document.getElementById('nameField').style.display      = 'none';
    document.getElementById('phoneField').style.display     = 'none';
    document.getElementById('districtField').style.display  = 'none';
    document.getElementById('roleSelection').style.display  = 'none';
    document.querySelector('.auth-toggle').style.display    = 'none';
    document.getElementById('forgotLink').style.display     = 'none';
    document.getElementById('authMessage').style.display    = 'none';

    // Hide email/password fields
    document.getElementById('regEmail').closest('.input-group').style.display = 'none';
    document.getElementById('regPass').closest('.input-group').style.display  = 'none';
    document.getElementById('submitBtn').style.display = 'none';

    // Show forgot form at step 1
    document.getElementById('forgotForm').style.display = 'block';
    document.getElementById('forgotStep1').style.display = 'block';
    document.getElementById('forgotStep2').style.display = 'none';
    document.getElementById('forgotStep3').style.display = 'none';
    document.getElementById('forgotEmail').focus();
}

function hideForgotForm() {
    document.getElementById('forgotForm').style.display = 'none';
    setAuthMode('login');
    document.getElementById('submitBtn').style.display = 'block';
    document.getElementById('regEmail').closest('.input-group').style.display = 'block';
    document.getElementById('regPass').closest('.input-group').style.display  = 'block';
    document.querySelector('.auth-toggle').style.display = 'flex';
    clearAuthMessage();
    resetForgotForm();
}

function resetForgotForm(e) {
    if (e) e.preventDefault();
    document.getElementById('forgotEmail').value = '';
    document.getElementById('forgotOTP').value = '';
    document.getElementById('forgotNewPass').value = '';
    document.getElementById('forgotConfirmPass').value = '';
    document.getElementById('forgotMsg').style.display = 'none';
    document.getElementById('forgotStep1').style.display = 'block';
    document.getElementById('forgotStep2').style.display = 'none';
    document.getElementById('forgotStep3').style.display = 'none';
}

async function submitForgotStep1() {
    const email = document.getElementById('forgotEmail').value.trim();
    if (!email) {
        showForgotMsg('Please enter your email.', 'error');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Sending OTP...';

    try {
        const res = await fetch('<?= $basePath ?>includes/auth.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'send_otp', email })
        });
        const data = await res.json();

        if (data.success) {
            showForgotMsg('✓ OTP has been sent to your email. Check your inbox!', 'success');
            setTimeout(() => {
                document.getElementById('forgotStep1').style.display = 'none';
                document.getElementById('forgotStep2').style.display = 'block';
                document.getElementById('forgotOTP').focus();
                document.getElementById('forgotMsg').style.display = 'none';
            }, 1500);
        } else {
            showForgotMsg(data.message || 'Failed to send OTP.', 'error');
        }
    } catch (err) {
        showForgotMsg('Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Send OTP';
    }
}

async function submitForgotStep2() {
    const otp = document.getElementById('forgotOTP').value.trim();
    if (!otp || otp.length !== 6) {
        showForgotMsg('Please enter a valid 6-digit OTP.', 'error');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Verifying...';

    try {
        const res = await fetch('<?= $basePath ?>includes/auth.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'verify_otp', otp })
        });
        const data = await res.json();

        if (data.success) {
            showForgotMsg('✓ OTP verified! Now create your new password.', 'success');
            setTimeout(() => {
                document.getElementById('forgotStep2').style.display = 'none';
                document.getElementById('forgotStep3').style.display = 'block';
                document.getElementById('forgotNewPass').focus();
                document.getElementById('forgotMsg').style.display = 'none';
            }, 1500);
        } else {
            showForgotMsg(data.message || 'Invalid OTP.', 'error');
        }
    } catch (err) {
        showForgotMsg('Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Verify OTP';
    }
}

async function submitForgotStep3() {
    const newPass = document.getElementById('forgotNewPass').value;
    const confirm = document.getElementById('forgotConfirmPass').value;

    if (newPass.length < 6) {
        showForgotMsg('Password must be at least 6 characters.', 'error');
        return;
    }
    if (newPass !== confirm) {
        showForgotMsg('Passwords do not match.', 'error');
        return;
    }

    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Resetting...';

    try {
        const res = await fetch('<?= $basePath ?>includes/auth.php', {
            method: 'POST',
            body: new URLSearchParams({ action: 'reset_password', new_password: newPass })
        });
        const data = await res.json();

        if (data.success) {
            showForgotMsg('✓ Password reset successfully! You can now login.', 'success');
            setTimeout(() => hideForgotForm(), 2000);
        } else {
            showForgotMsg(data.message || 'Failed to reset password.', 'error');
        }
    } catch (err) {
        showForgotMsg('Network error. Please try again.', 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = 'Reset Password';
    }
}

function showForgotMsg(msg, type) {
    const el = document.getElementById('forgotMsg');
    el.textContent        = msg;
    el.style.display      = 'block';
    el.style.background   = type === 'success' ? '#e8f5e9' : '#fce4e4';
    el.style.color        = type === 'success' ? '#2e7d32' : '#c62828';
    el.style.padding      = '10px 14px';
    el.style.borderRadius = '8px';
    el.style.fontSize     = '13px';
    el.style.marginBottom = '12px';
}
</script>
<?php endif; ?>
