<?php

require_once '../includes/config.php';
requireRole('consumer', '../index.php');

$db  = getDB();
$uid = $_SESSION['user_id'];

// Handle profile update
$profileMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $name     = clean($_POST['name'] ?? '');
    $phone    = clean($_POST['phone'] ?? '');
    $district = clean($_POST['district'] ?? '');
    $address  = clean($_POST['address'] ?? '');
    $stmt = $db->prepare("UPDATE users SET name=?, phone=?, district=?, address=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $phone, $district, $address, $uid);
    $stmt->execute();
    $_SESSION['user_name'] = $name;
    $profileMsg = 'success:Profile updated successfully!';
}

// Fetch user info
$user = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();

// Fetch orders
$orders = $db->query("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id WHERE o.consumer_id=$uid GROUP BY o.id ORDER BY o.created_at DESC");

// Fetch messages
$messages = $db->query("SELECT m.*, u.name as sender_name FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid ORDER BY m.sent_at DESC LIMIT 20");
$unread   = $db->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND is_read=0")->fetch_assoc()['c'];

// Mark messages as read
$db->query("UPDATE messages SET is_read=1 WHERE receiver_id=$uid");

$tab = $_GET['tab'] ?? 'orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Consumer Dashboard - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .dashboard-layout { display:flex; min-height:80vh; }
        .sidebar { width:240px; background:#0e1726; color:white; padding:30px 0; flex-shrink:0; }
        .sidebar h3 { padding:0 25px 20px; font-size:16px; color:#aaa; border-bottom:1px solid #1c2e42; margin-bottom:15px; }
        .sidebar a { display:flex; align-items:center; gap:12px; padding:13px 25px; color:#ccc; text-decoration:none; font-size:14px; transition:0.2s; }
        .sidebar a:hover, .sidebar a.active { background:#56ad58; color:white; }
        .sidebar a i { width:18px; }
        .dash-content { flex:1; padding:30px; background:#f5f7fa; }
        .dash-header { margin-bottom:25px; }
        .dash-header h2 { font-size:24px; color:#222; }
        .dash-header p { color:#888; font-size:14px; }
        .stat-cards { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-bottom:25px; }
        .stat-card { background:white; border-radius:12px; padding:22px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .stat-card .icon { font-size:28px; margin-bottom:10px; }
        .stat-card .num { font-size:28px; font-weight:700; color:#333; }
        .stat-card .label { font-size:13px; color:#888; margin-top:3px; }
        .data-table { background:white; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; }
        .data-table table { width:100%; border-collapse:collapse; }
        .data-table th { background:#f8f9fa; padding:13px 18px; text-align:left; font-size:13px; color:#555; border-bottom:2px solid #eee; }
        .data-table td { padding:13px 18px; font-size:14px; border-bottom:1px solid #f5f5f5; }
        .badge { padding:4px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-pending    { background:#fff3cd; color:#856404; }
        .badge-confirmed  { background:#cfe2ff; color:#084298; }
        .badge-processing { background:#f8d7da; color:#842029; }
        .badge-shipped    { background:#d1ecf1; color:#0c5460; }
        .badge-delivered  { background:#d1e7dd; color:#0a3622; }
        .badge-cancelled  { background:#f8d7da; color:#842029; }
        .profile-form { background:white; border-radius:12px; padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.06); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:6px; font-size:13px; font-weight:600; color:#555; }
        .form-group input, .form-group select, .form-group textarea {
            width:100%; padding:11px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa;
        }
        .msg-item { background:white; border-radius:10px; padding:15px 18px; margin-bottom:12px; box-shadow:0 1px 6px rgba(0,0,0,0.05); }
        .msg-sender { font-weight:600; color:#333; margin-bottom:5px; }
        .msg-text { color:#555; font-size:14px; }
        .msg-time { font-size:12px; color:#aaa; margin-top:5px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="dashboard-layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Consumer Panel</h3>
        <a href="dashboard.php?tab=orders" class="<?= $tab==='orders'?'active':'' ?>"><i class="fa-solid fa-bag-shopping"></i> My Orders</a>
        <a href="dashboard.php?tab=messages" class="<?= $tab==='messages'?'active':'' ?>">
            <i class="fa-solid fa-envelope"></i> Messages
            <?php if ($unread > 0): ?><span style="background:#e74c3c; color:white; border-radius:50%; padding:2px 7px; font-size:11px; margin-left:auto;"><?= $unread ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php?tab=profile" class="<?= $tab==='profile'?'active':'' ?>"><i class="fa-solid fa-user-pen"></i> My Profile</a>
        <a href="../marketplace.php"><i class="fa-solid fa-store"></i> Marketplace</a>
        <a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> My Cart</a>
        <a href="../change_password.php"><i class="fa-solid fa-lock"></i> Change Password</a>
        <hr style="border-color:#1c2e42; margin:15px 0;">
        <form method="POST" action="../includes/auth.php">
            <input type="hidden" name="action" value="logout">
            <button type="submit" style="background:none; border:none; color:#ff6b6b; cursor:pointer; padding:13px 25px; font-size:14px; width:100%; text-align:left; display:flex; gap:12px; align-items:center;">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>
    </div>

    <!-- Main Content -->
    <div class="dash-content">
        <div class="dash-header">
            <h2>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?>! 👋</h2>
            <p>Here's an overview of your account</p>
        </div>

        <?php
        $totalOrders    = $db->query("SELECT COUNT(*) as c FROM orders WHERE consumer_id=$uid")->fetch_assoc()['c'];
        $totalSpent     = $db->query("SELECT COALESCE(SUM(total_amount),0) as s FROM orders WHERE consumer_id=$uid AND status != 'cancelled'")->fetch_assoc()['s'];
        $pendingOrders  = $db->query("SELECT COUNT(*) as c FROM orders WHERE consumer_id=$uid AND status='pending'")->fetch_assoc()['c'];
        ?>
        <div class="stat-cards">
            <div class="stat-card"><div class="icon">📦</div><div class="num"><?= $totalOrders ?></div><div class="label">Total Orders</div></div>
            <div class="stat-card"><div class="icon">💰</div><div class="num">Rs. <?= number_format($totalSpent,0) ?></div><div class="label">Total Spent</div></div>
            <div class="stat-card"><div class="icon">⏳</div><div class="num"><?= $pendingOrders ?></div><div class="label">Pending Orders</div></div>
        </div>

        <!-- ORDERS TAB -->
        <?php if ($tab === 'orders'): ?>
        <div class="data-table">
            <div style="padding:18px 22px; background:white; border-bottom:2px solid #f0f0f0; font-weight:600; font-size:16px;">📦 My Orders</div>
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $orders->data_seek(0); while ($o = $orders->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $o['id'] ?></strong></td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        <td><?= $o['item_count'] ?> items</td>
                        <td><strong>Rs. <?= number_format($o['total_amount'],2) ?></strong></td>
                        <td><?= ucwords(str_replace('_',' ',$o['payment_method'])) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td><a href="order_detail.php?id=<?= $o['id'] ?>" style="color:#56ad58; font-size:13px; font-weight:600;">View →</a></td>
                    </tr>
                    <?php endwhile; ?>
                    <?php if ($totalOrders === 0): ?>
                    <tr><td colspan="7" style="text-align:center; padding:40px; color:#aaa;">No orders yet. <a href="../marketplace.php" style="color:#56ad58;">Shop now!</a></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MESSAGES TAB -->
        <?php elseif ($tab === 'messages'): ?>
        <div style="margin-bottom:15px; font-weight:600; font-size:16px;">💬 Messages from Farmers</div>
        <?php if ($messages->num_rows === 0): ?>
            <div style="text-align:center; padding:50px; color:#aaa; background:white; border-radius:12px;">
                <i class="fa-solid fa-envelope-open" style="font-size:40px; margin-bottom:12px; display:block;"></i>
                No messages yet.
            </div>
        <?php else: ?>
            <?php while ($msg = $messages->fetch_assoc()): ?>
            <div class="msg-item">
                <div class="msg-sender"><i class="fa-solid fa-tractor" style="color:#56ad58;"></i> <?= htmlspecialchars($msg['sender_name']) ?></div>
                <div class="msg-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                <div class="msg-time"><?= date('M d, Y g:i A', strtotime($msg['sent_at'])) ?></div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>

        <!-- PROFILE TAB -->
        <?php elseif ($tab === 'profile'): ?>
        <?php if ($profileMsg): list($type,$msg) = explode(':', $profileMsg, 2); ?>
        <div style="background:<?= $type==='success'?'#e0ffe8':'#ffe0e0' ?>; color:<?= $type==='success'?'#27ae60':'#c0392b' ?>; padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:500;"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        <div class="profile-form">
            <h3 style="margin-bottom:22px;">My Profile</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-row">
                    <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                    <div class="form-group"><label>Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background:#f0f0f0;"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"></div>
                    <div class="form-group">
                        <label>District</label>
                        <select name="district">
                            <option value="">Select District</option>
                            <?php foreach (['Colombo','Gampaha','Kandy','Matale','Nuwara Eliya','Galle','Matara','Hambantota','Jaffna','Kurunegala','Anuradhapura','Polonnaruwa','Badulla','Ratnapura','Kegalle'] as $d): ?>
                            <option value="<?= $d ?>" <?= $user['district']===$d?'selected':'' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:18px;"><label>Address</label><textarea name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea></div>
                <button type="submit" style="background:#56ad58; color:white; padding:13px 35px; border:none; border-radius:10px; font-size:15px; font-weight:600; cursor:pointer;">Save Changes</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
