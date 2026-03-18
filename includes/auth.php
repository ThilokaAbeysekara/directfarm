<?php


require_once 'config.php';

$action = $_POST['action'] ?? '';

// LOGIN
if ($action === 'login') {
    $email    = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
        exit;
    }

    $db   = getDB();
    $stmt = $db->prepare("SELECT id, name, email, password, role, is_verified FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        exit;
    }

    // Set session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    // Determine redirect URL based on role
    $redirect = match($user['role']) {
        'farmer'   => BASE_URL . 'farmer/dashboard.php',
        'consumer' => BASE_URL . 'consumer/dashboard.php',
        'admin'    => BASE_URL . 'admin/dashboard.php',
        default    => BASE_URL . 'index.php'
    };

    echo json_encode(['success' => true, 'role' => $user['role'], 'redirect' => $redirect]);
    exit;
}

//  REGISTER 
if ($action === 'register') {
    $name     = clean($_POST['name'] ?? '');
    $email    = clean($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = in_array($_POST['role'] ?? '', ['farmer','consumer']) ? $_POST['role'] : 'consumer';
    $phone    = clean($_POST['phone'] ?? '');
    $district = clean($_POST['district'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Name, email and password are required.']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    $db = getDB();

    // Check if email already exists
    $check = $db->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered.']);
        exit;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt   = $db->prepare("INSERT INTO users (name, email, password, role, phone, district) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $name, $email, $hashed, $role, $phone, $district);

    if ($stmt->execute()) {
        $user_id = $db->insert_id;
        $_SESSION['user_id']   = $user_id;
        $_SESSION['user_name'] = $name;
        $_SESSION['email']     = $email;
        $_SESSION['role']      = $role;

        $redirect = ($role === 'farmer')
            ? BASE_URL . 'farmer/dashboard.php'
            : BASE_URL . 'consumer/dashboard.php';

        echo json_encode(['success' => true, 'role' => $role, 'redirect' => $redirect]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    }
    exit;
}

//  LOGOUT
if ($action === 'logout') {
    session_destroy();
    header("Location: " . BASE_URL . "index.php");
    exit;
}

//  GET SESSION STATUS (for JS) 
if ($action === 'status') {
    echo json_encode([
        'loggedIn' => isLoggedIn(),
        'name'     => $_SESSION['user_name'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ]);
    exit;
}

// SEND OTP (Forgot Password Step 1) 
if ($action === 'send_otp') {
    $email = clean($_POST['email'] ?? '');
    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Email is required.']);
        exit;
    }

    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No account found with that email.']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

    // Store OTP in session (in real app, would send via email)
    $_SESSION['reset_otp'] = $otp;
    $_SESSION['reset_email'] = $email;
    $_SESSION['otp_created_at'] = time();

    echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);
    exit;
}

// VERIFY OTP (Forgot Password Step 2) 
if ($action === 'verify_otp') {
    $otp = clean($_POST['otp'] ?? '');
    if (!$otp) {
        echo json_encode(['success' => false, 'message' => 'OTP is required.']);
        exit;
    }

    // Check if OTP matches and hasn't expired (10 minutes)
    if (empty($_SESSION['reset_otp']) || $_SESSION['reset_otp'] !== $otp) {
        echo json_encode(['success' => false, 'message' => 'Incorrect OTP.']);
        exit;
    }

    if (time() - ($_SESSION['otp_created_at'] ?? 0) > 600) {
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'OTP verified']);
    exit;
}

//RESET PASSWORD (Forgot Password Step 3) 
if ($action === 'reset_password') {
    $newPass = $_POST['new_password'] ?? '';

    if (strlen($newPass) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }

    if (empty($_SESSION['reset_email'])) {
        echo json_encode(['success' => false, 'message' => 'Session expired. Please try again.']);
        exit;
    }

    $db = getDB();
    $email = $_SESSION['reset_email'];
    $hash = password_hash($newPass, PASSWORD_DEFAULT);

    $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->bind_param("ss", $hash, $email);

    if ($stmt->execute()) {
        // Clear reset session data
        unset($_SESSION['reset_otp']);
        unset($_SESSION['reset_email']);
        unset($_SESSION['otp_created_at']);
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
    exit;
}
?>
