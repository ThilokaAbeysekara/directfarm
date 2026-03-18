<?php
// farmer_register.php
require_once 'includes/config.php';

// Already logged in? redirect
if (isLoggedIn()) {
    header("Location: " . (hasRole('farmer') ? 'farmer/dashboard.php' : 'consumer/dashboard.php'));
    exit;
}

$msg   = '';
$input = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'name'       => clean($_POST['name'] ?? ''),
        'email'      => clean($_POST['email'] ?? ''),
        'password'   => $_POST['password'] ?? '',
        'confirm'    => $_POST['confirm_password'] ?? '',
        'phone'      => clean($_POST['phone'] ?? ''),
        'district'   => clean($_POST['district'] ?? ''),
        'address'    => clean($_POST['address'] ?? ''),
        'farm_name'  => clean($_POST['farm_name'] ?? ''),
        'farm_size'  => clean($_POST['farm_size'] ?? ''),
        'experience' => clean($_POST['experience'] ?? ''),
        'crops'      => clean($_POST['crops'] ?? ''),
        'bio'        => clean($_POST['bio'] ?? ''),
    ];

    // Validation
    if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
        $msg = 'error:Name, email and password are required.';
    } elseif (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $msg = 'error:Please enter a valid email address.';
    } elseif (strlen($input['password']) < 6) {
        $msg = 'error:Password must be at least 6 characters.';
    } elseif ($input['password'] !== $input['confirm']) {
        $msg = 'error:Passwords do not match.';
    } elseif (empty($input['phone'])) {
        $msg = 'error:Phone number is required.';
    } elseif (empty($input['district'])) {
        $msg = 'error:Please select your district.';
    } else {
        $db = getDB();

        // Check email
        $chk = $db->prepare("SELECT id FROM users WHERE email=?");
        $chk->bind_param("s", $input['email']);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $msg = 'error:This email is already registered.';
        } else {
            $hashed = password_hash($input['password'], PASSWORD_DEFAULT);

            // Combine extra info into address field as JSON
            $extra = json_encode([
                'farm_name'  => $input['farm_name'],
                'farm_size'  => $input['farm_size'],
                'experience' => $input['experience'],
                'crops'      => $input['crops'],
                'bio'        => $input['bio'],
            ]);

            $stmt = $db->prepare("INSERT INTO users (name, email, password, role, phone, district, address) VALUES (?,?,?,'farmer',?,?,?)");
            $stmt->bind_param("ssssss", $input['name'], $input['email'], $hashed, $input['phone'], $input['district'], $extra);

            if ($stmt->execute()) {
                $_SESSION['user_id']   = $db->insert_id;
                $_SESSION['user_name'] = $input['name'];
                $_SESSION['email']     = $input['email'];
                $_SESSION['role']      = 'farmer';
                header("Location: farmer/dashboard.php");
                exit;
            } else {
                $msg = 'error:Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Farmer Registration - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .reg-page { background:#f0f7f0; min-height:100vh; padding:50px 20px; }
        .reg-wrap { max-width:780px; margin:auto; }
        .reg-hero { text-align:center; margin-bottom:35px; }
        .reg-hero h1 { font-size:30px; color:#1a5c2a; margin-bottom:8px; }
        .reg-hero p { color:#666; font-size:15px; }
        .reg-card { background:white; border-radius:18px; padding:40px; box-shadow:0 6px 24px rgba(0,0,0,0.09); }
        .section-label { font-size:12px; font-weight:700; color:#56ad58; text-transform:uppercase; letter-spacing:1px; margin:28px 0 16px; border-left:4px solid #56ad58; padding-left:10px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .fg { display:flex; flex-direction:column; gap:6px; }
        .fg.full { grid-column:1/-1; }
        .fg label { font-size:13px; font-weight:600; color:#444; }
        .fg label span { color:#e74c3c; }
        .fg input, .fg select, .fg textarea {
            padding:12px 14px; border:1.5px solid #e8e8e8; border-radius:10px;
            font-size:14px; background:#fafafa; transition:.2s; font-family:inherit;
        }
        .fg input:focus, .fg select:focus, .fg textarea:focus { outline:none; border-color:#56ad58; background:#fff; }
        .pw-wrap { position:relative; }
        .pw-wrap input { width:100%; padding-right:42px; }
        .pw-eye { position:absolute; right:13px; top:50%; transform:translateY(-50%); cursor:pointer; color:#aaa; }
        .pw-eye:hover { color:#56ad58; }
        .submit-reg { width:100%; background:#56ad58; color:white; padding:15px; border:none; border-radius:12px; font-size:17px; font-weight:700; cursor:pointer; margin-top:28px; transition:.2s; }
        .submit-reg:hover { background:#3f8e44; transform:translateY(-2px); box-shadow:0 6px 18px rgba(86,173,88,.35); }
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:22px; font-weight:500; font-size:14px; }
        .alert-error { background:#ffe0e0; color:#c0392b; }
        .login-link { text-align:center; margin-top:22px; font-size:14px; color:#666; }
        .login-link a { color:#56ad58; font-weight:600; text-decoration:none; }
        .badge-info { background:#e8f5e9; color:#2e7d32; padding:12px 16px; border-radius:10px; font-size:13px; margin-bottom:20px; }
        @media(max-width:600px){ .form-grid{grid-template-columns:1fr;} .fg.full{grid-column:1;} }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="reg-page">
    <div class="reg-wrap">
        <div class="reg-hero">
            <div style="font-size:52px; margin-bottom:10px;">🌾</div>
            <h1>Join as a Farmer</h1>
            <p>Start selling your fresh produce directly to consumers across Sri Lanka.</p>
        </div>

        <div class="reg-card">
            <?php if ($msg): list($type,$text) = explode(':', $msg, 2); ?>
            <div class="alert alert-<?= $type === 'success' ? 'success' : 'error' ?>">✗ <?= htmlspecialchars($text) ?></div>
            <?php endif; ?>

            <div class="badge-info">
                <i class="fa-solid fa-circle-info" style="color:#56ad58;"></i>
                <strong>Free to join!</strong> After registering, you can immediately start listing your products. No fees, no middlemen.
            </div>

            <form method="POST">

                <!-- ACCOUNT INFO -->
                <div class="section-label">Account Information</div>
                <div class="form-grid">
                    <div class="fg">
                        <label>Full Name <span>*</span></label>
                        <input type="text" name="name" placeholder="e.g. Sunil Perera" value="<?= htmlspecialchars($input['name'] ?? '') ?>" required>
                    </div>
                    <div class="fg">
                        <label>Email Address <span>*</span></label>
                        <input type="email" name="email" placeholder="sunil@email.com" value="<?= htmlspecialchars($input['email'] ?? '') ?>" required>
                    </div>
                    <div class="fg">
                        <label>Password <span>*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="password" id="pw1" placeholder="Min. 6 characters" required>
                            <i class="fa-solid fa-eye pw-eye" onclick="togglePw('pw1',this)"></i>
                        </div>
                    </div>
                    <div class="fg">
                        <label>Confirm Password <span>*</span></label>
                        <div class="pw-wrap">
                            <input type="password" name="confirm_password" id="pw2" placeholder="Repeat password" required oninput="checkMatch()">
                            <i class="fa-solid fa-eye pw-eye" onclick="togglePw('pw2',this)"></i>
                        </div>
                        <div id="matchMsg" style="font-size:12px; margin-top:3px;"></div>
                    </div>
                </div>

                <!-- CONTACT INFO -->
                <div class="section-label">Contact & Location</div>
                <div class="form-grid">
                    <div class="fg">
                        <label>Phone Number <span>*</span></label>
                        <input type="tel" name="phone" placeholder="+94 77 123 4567" value="<?= htmlspecialchars($input['phone'] ?? '') ?>" required>
                    </div>
                    <div class="fg">
                        <label>District <span>*</span></label>
                        <select name="district" required>
                            <option value="">Select your district</option>
                            <?php foreach (['Colombo','Gampaha','Kalutara','Kandy','Matale','Nuwara Eliya','Galle','Matara','Hambantota','Jaffna','Kilinochchi','Mannar','Vavuniya','Trincomalee','Batticaloa','Ampara','Kurunegala','Puttalam','Anuradhapura','Polonnaruwa','Badulla','Monaragala','Ratnapura','Kegalle'] as $d): ?>
                            <option value="<?= $d ?>" <?= ($input['district'] ?? '')===$d?'selected':'' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fg full">
                        <label>Farm Address</label>
                        <input type="text" name="address" placeholder="Village, Town, District" value="<?= htmlspecialchars($input['address'] ?? '') ?>">
                    </div>
                </div>

                <!-- FARM INFO -->
                <div class="section-label">Farm Information</div>
                <div class="form-grid">
                    <div class="fg">
                        <label>Farm Name</label>
                        <input type="text" name="farm_name" placeholder="e.g. Perera Family Farm" value="<?= htmlspecialchars($input['farm_name'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label>Farm Size</label>
                        <select name="farm_size">
                            <option value="">Select farm size</option>
                            <option value="< 1 acre"    <?= ($input['farm_size']??'')==='< 1 acre'?'selected':'' ?>>Less than 1 acre</option>
                            <option value="1-5 acres"   <?= ($input['farm_size']??'')==='1-5 acres'?'selected':'' ?>>1–5 acres</option>
                            <option value="5-10 acres"  <?= ($input['farm_size']??'')==='5-10 acres'?'selected':'' ?>>5–10 acres</option>
                            <option value="10-50 acres" <?= ($input['farm_size']??'')==='10-50 acres'?'selected':'' ?>>10–50 acres</option>
                            <option value="> 50 acres"  <?= ($input['farm_size']??'')==='> 50 acres'?'selected':'' ?>>More than 50 acres</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Years of Farming Experience</label>
                        <select name="experience">
                            <option value="">Select experience</option>
                            <option value="< 1 year"  <?= ($input['experience']??'')==='< 1 year'?'selected':'' ?>>Less than 1 year</option>
                            <option value="1-3 years" <?= ($input['experience']??'')==='1-3 years'?'selected':'' ?>>1–3 years</option>
                            <option value="3-5 years" <?= ($input['experience']??'')==='3-5 years'?'selected':'' ?>>3–5 years</option>
                            <option value="5-10 years"<?= ($input['experience']??'')==='5-10 years'?'selected':'' ?>>5–10 years</option>
                            <option value="10+ years" <?= ($input['experience']??'')==='10+ years'?'selected':'' ?>>10+ years</option>
                        </select>
                    </div>
                    <div class="fg">
                        <label>Main Crops / Products</label>
                        <input type="text" name="crops" placeholder="e.g. Carrots, Cabbage, Tomatoes" value="<?= htmlspecialchars($input['crops'] ?? '') ?>">
                    </div>
                    <div class="fg full">
                        <label>About Your Farm (Bio)</label>
                        <textarea name="bio" rows="4" placeholder="Tell consumers about your farm, your farming methods, what makes your products special..."><?= htmlspecialchars($input['bio'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Terms -->
                <div style="display:flex; align-items:flex-start; gap:12px; margin-top:22px; padding:16px; background:#f8f9fa; border-radius:10px;">
                    <input type="checkbox" id="terms" required style="width:18px; height:18px; margin-top:2px; accent-color:#56ad58; flex-shrink:0;">
                    <label for="terms" style="font-size:14px; color:#555; cursor:pointer; line-height:1.5;">
                        I agree to the <a href="#" style="color:#56ad58;">Terms of Service</a> and
                        <a href="#" style="color:#56ad58;">Privacy Policy</a>. I confirm that all
                        information provided is accurate and I am a genuine farmer in Sri Lanka.
                    </label>
                </div>

                <button type="submit" class="submit-reg">🌾 Create Farmer Account</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="index.php" onclick="openAuth('login'); return false;">Login here</a>
                &nbsp;·&nbsp;
                Looking to buy? <a href="consumer_register.php">Register as Consumer</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function togglePw(id, icon) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}
function checkMatch() {
    const p1 = document.getElementById('pw1').value;
    const p2 = document.getElementById('pw2').value;
    const el = document.getElementById('matchMsg');
    if (!p2) { el.textContent = ''; return; }
    el.textContent = p1 === p2 ? '✓ Passwords match' : '✗ Passwords do not match';
    el.style.color  = p1 === p2 ? '#27ae60' : '#e74c3c';
}
</script>
</body>
</html>
