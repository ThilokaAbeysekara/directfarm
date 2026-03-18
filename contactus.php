<?php
require_once 'includes/config.php';
$db = getDB();
$success = '';
$userReplies = [];

$db->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL");
$db->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS replied_at DATETIME NULL");

if (!empty($_GET['email'])) {
    $replyEmail = clean($_GET['email']);
    $rs = $db->prepare("SELECT * FROM contact_messages WHERE email=? AND admin_reply IS NOT NULL AND admin_reply != '' ORDER BY submitted_at DESC");
    if ($rs) {
        $rs->bind_param("s", $replyEmail);
        $rs->execute();
        $userReplies = $rs->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = clean($_POST['name']     ?? '');
    $email   = clean($_POST['email']    ?? '');
    $message = clean($_POST['messages'] ?? '');
    if ($name && $email && $message) {
        $stmt = $db->prepare("INSERT INTO contact_messages (name, email, message) VALUES (?,?,?)");
        if ($stmt) { $stmt->bind_param("sss", $name, $email, $message); $stmt->execute(); $success = 'Thank you! Your message has been sent. We will get back to you soon.'; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <title>Contact Us - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .contact-hero { background:linear-gradient(135deg,#0e1726 0%,#1a3a2a 60%,#009933 100%); color:white; padding:70px 5%; text-align:center; }
        .contact-hero h1 { font-size:38px; font-weight:800; margin-bottom:14px; }
        .contact-hero p  { color:#cce8cc; font-size:16px; max-width:540px; margin:0 auto; }

        .contact-body { max-width:1100px; margin:0 auto; padding:60px 20px; display:grid; grid-template-columns:1fr 1.4fr; gap:40px; align-items:start; }

        .contact-info h2 { font-size:22px; font-weight:800; color:#1a2e1a; margin-bottom:8px; }
        .contact-info p  { font-size:14px; color:#666; line-height:1.7; margin-bottom:28px; }
        .info-card { background:white; border-radius:14px; padding:22px; box-shadow:0 4px 16px rgba(0,0,0,.07); margin-bottom:16px; display:flex; gap:18px; align-items:center; }
        .info-icon { width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg,#1a3a2a,#009933); display:flex; align-items:center; justify-content:center; color:white; font-size:18px; flex-shrink:0; }
        .info-card h4 { font-size:14px; font-weight:700; color:#222; margin-bottom:4px; }
        .info-card p  { font-size:13px; color:#888; margin:0; }

        .social-row { display:flex; gap:12px; margin-top:20px; }
        .social-btn { width:42px; height:42px; border-radius:12px; background:#f0f7f0; color:#009933; display:flex; align-items:center; justify-content:center; font-size:18px; text-decoration:none; transition:.2s; }
        .social-btn:hover { background:#009933; color:white; }

        .contact-form-box { background:white; border-radius:20px; padding:36px; box-shadow:0 6px 24px rgba(0,0,0,.09); }
        .contact-form-box h2 { font-size:20px; font-weight:800; color:#1a2e1a; margin-bottom:6px; }
        .contact-form-box .sub { font-size:14px; color:#888; margin-bottom:24px; }
        .cf-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .cf-group { margin-bottom:18px; }
        .cf-group label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:7px; }
        .cf-group input, .cf-group textarea, .cf-group select {
            width:100%; padding:13px 16px; border:1.5px solid #eee; border-radius:12px;
            font-size:14px; background:#fafafa; font-family:inherit; box-sizing:border-box; transition:.2s;
        }
        .cf-group input:focus, .cf-group textarea:focus { outline:none; border-color:#009933; background:white; }
        .cf-group textarea { min-height:130px; resize:vertical; }
        .cf-submit { width:100%; padding:15px; background:linear-gradient(135deg,#1a3a2a,#009933); color:white; border:none; border-radius:12px; font-size:16px; font-weight:700; cursor:pointer; transition:.2s; }
        .cf-submit:hover { opacity:.9; transform:translateY(-1px); }
        .success-msg { background:#e8f5e9; color:#2e7d32; padding:14px 18px; border-radius:12px; margin-bottom:20px; font-weight:600; font-size:14px; border-left:4px solid #009933; }

        @media(max-width:768px){ .contact-body{grid-template-columns:1fr;} .cf-row{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="contact-hero">
    <div style="font-size:52px;margin-bottom:16px;">💬</div>
    <h1>Contact Us</h1>
    <p>Have questions? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
</div>

<div class="contact-body">

    <!-- Left: Info -->
    <div class="contact-info">
        <h2>Get in Touch</h2>
        <p>Whether you're a farmer needing support, a consumer with a question, or someone who wants to learn more about DirectFarm LK — we're here to help.</p>

        <div class="info-card">
            <div class="info-icon"><i class="fa-solid fa-envelope"></i></div>
            <div><h4>Email Us</h4><p>info@directfarmlk.com</p></div>
        </div>
        <div class="info-card">
            <div class="info-icon"><i class="fa-solid fa-phone"></i></div>
            <div><h4>Call Us</h4><p>+94 11 234 5678</p></div>
        </div>
        
        <div class="info-card">
            <div class="info-icon"><i class="fa-solid fa-clock"></i></div>
            <div><h4>Support Hours</h4><p>Mon – Fri: 8:00 AM – 6:00 PM</p></div>
        </div>

        
    </div>

    <!-- Right: Form -->
    <div class="contact-form-box">
        <h2>Send a Message</h2>
        <p class="sub">Fill in the form below and we'll get back to you within 24 hours.</p>

        <?php if ($success): ?>
        <div class="success-msg">✓ <?= $success ?> <a href="contactus.php?email=<?= urlencode($_POST['email'] ?? '') ?>" style="color:#2e7d32;font-weight:700;text-decoration:underline;">View replies →</a></div>
        <?php endif; ?>

        <?php if (!empty($_GET['email'])): ?>
        <?php if (empty($userReplies)): ?>
        <div style="background:#fff8e1;border:1.5px solid #ffe082;border-radius:14px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;gap:12px;">
            <i class="fa-solid fa-clock" style="color:#f57f17;font-size:20px;"></i>
            <div>
                <div style="font-weight:600;color:#f57f17;font-size:14px;">No replies yet for <?= htmlspecialchars($_GET['email']) ?></div>
                <div style="color:#888;font-size:13px;margin-top:2px;">Admin hasn't replied to your messages yet. Please check back later.</div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php if (!empty($userReplies)): ?>
        <div style="margin-bottom:28px;">
            <h3 style="font-size:16px;font-weight:700;color:#0e1726;margin-bottom:14px;"><i class="fa-solid fa-reply" style="color:#009933;margin-right:8px;"></i>Admin Replies to Your Messages</h3>
            <?php foreach ($userReplies as $r): ?>
            <div style="background:white;border-radius:14px;padding:18px 20px;margin-bottom:12px;box-shadow:0 2px 10px rgba(0,0,0,0.06);border:1.5px solid #eef2ee;">
                <!-- Original message -->
                <div style="font-size:12px;color:#aaa;margin-bottom:6px;"><?= date('M d, Y g:i A', strtotime($r['submitted_at'])) ?></div>
                <div style="background:#f8faf8;border-left:3px solid #ddd;padding:10px 14px;border-radius:0 8px 8px 0;color:#666;font-size:13px;margin-bottom:12px;">
                    <?= nl2br(htmlspecialchars($r['message'])) ?>
                </div>
                <!-- Admin reply -->
                <div style="display:flex;gap:10px;align-items:flex-start;">
                    <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#009933,#006622);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fa-solid fa-headset" style="color:white;font-size:13px;"></i>
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:12px;font-weight:700;color:#009933;margin-bottom:4px;">DirectFarm LK Support · <?= date('M d, Y g:i A', strtotime($r['replied_at'])) ?></div>
                        <div style="background:#e8f5e9;border-left:3px solid #009933;padding:10px 14px;border-radius:0 8px 8px 0;color:#2e7d32;font-size:14px;line-height:1.6;">
                            <?= nl2br(htmlspecialchars($r['admin_reply'])) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Check replies form -->
        <div style="background:#f0faf0;border:1.5px solid #c8e6c9;border-radius:14px;padding:16px 20px;margin-bottom:24px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <i class="fa-solid fa-envelope-open-text" style="color:#009933;font-size:18px;flex-shrink:0;"></i>
            <form method="GET" style="display:flex;gap:8px;flex:1;flex-wrap:wrap;align-items:center;">
                <span style="font-size:13px;color:#555;font-weight:600;white-space:nowrap;">Check replies to your message:</span>
                <input type="email" name="email" placeholder="Enter your email..." value="<?= htmlspecialchars($_GET['email'] ?? '') ?>"
                    required style="flex:1;min-width:200px;padding:8px 14px;border:1.5px solid #c8e6c9;border-radius:50px;font-size:13px;outline:none;">
                <button type="submit" style="background:#009933;color:white;border:none;padding:8px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Check</button>
            </form>
        </div>

        <form action="contactus.php" method="POST">
            <div class="cf-row">
                <div class="cf-group">
                    <label>Your Name</label>
                    <input type="text" name="name" placeholder="e.g. Kamal Perera" required>
                </div>
                <div class="cf-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com" required>
                </div>
            </div>
            <div class="cf-group">
                <label>Subject</label>
                <select name="subject">
                    <option value="">Select a topic...</option>
                    <option>Question about an order</option>
                    <option>Farmer registration help</option>
                    <option>Product or pricing issue</option>
                    <option>Technical support</option>
                    <option>General inquiry</option>
                </select>
            </div>
            <div class="cf-group">
                <label>Message</label>
                <textarea name="messages" placeholder="Write your message here..." required></textarea>
            </div>
            <button type="submit" class="cf-submit"><i class="fa-solid fa-paper-plane"></i> &nbsp;Send Message</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
