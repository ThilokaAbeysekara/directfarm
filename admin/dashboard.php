<?php

require_once '../includes/config.php';
requireRole('admin', '../index.php');

$db  = getDB();
$msg = '';

// ADMIN ACTIONS 
$action = $_POST['action'] ?? '';

// Verify / Unverify farmer
if ($action === 'verify_farmer') {
    $uid    = (int)$_POST['user_id'];
    $status = (int)$_POST['status'];
    $s = $db->prepare("UPDATE users SET is_verified=? WHERE id=? AND role='farmer'");
    if ($s) { $s->bind_param("ii", $status, $uid); $s->execute(); }
    $msg = 'success:Farmer ' . ($status ? 'verified' : 'unverified') . ' successfully.';
}

// Delete user
if ($action === 'delete_user') {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $_SESSION['user_id']) {
        $s = $db->prepare("DELETE FROM users WHERE id=? AND role != 'admin'");
        if ($s) { $s->bind_param("i", $uid); $s->execute(); }
        $msg = 'success:User deleted.';
    }
}

// Toggle product availability
if ($action === 'toggle_product') {
    $pid  = (int)$_POST['product_id'];
    $avail = (int)$_POST['available'];
    $s = $db->prepare("UPDATE products SET is_available=? WHERE id=?");
    if ($s) { $s->bind_param("ii", $avail, $pid); $s->execute(); }
    $msg = 'success:Product visibility updated.';
}

// Delete product
if ($action === 'delete_product') {
    $pid = (int)$_POST['product_id'];
    $s = $db->prepare("DELETE FROM products WHERE id=?");
    if ($s) { $s->bind_param("i", $pid); $s->execute(); }
    $msg = 'success:Product deleted.';
}

// Mark contact message read
// Add admin_reply column if not exists
$db->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL");
$db->query("ALTER TABLE contact_messages ADD COLUMN IF NOT EXISTS replied_at DATETIME NULL");

if ($action === 'admin_reply') {
    $mid   = (int)($_POST['message_id'] ?? 0);
    $reply = clean($_POST['reply'] ?? '');
    if ($mid && $reply) {
        $stmt = $db->prepare("UPDATE contact_messages SET admin_reply=?, replied_at=NOW(), is_read=1 WHERE id=?");
        $stmt->bind_param("si", $reply, $mid);
        $stmt->execute();
        $msg = 'success:Reply sent!';
    }
}

if ($action === 'mark_read') {
    $mid = (int)$_POST['message_id'];
    $s = $db->prepare("UPDATE contact_messages SET is_read=1 WHERE id=?");
    if ($s) { $s->bind_param("i", $mid); $s->execute(); }
}

// Market price handlers
if ($action === 'add_price') {
    $pname    = clean($_POST['product_name'] ?? '');
    $cat      = clean($_POST['category'] ?? '');
    $avg      = (float)$_POST['avg_price'];
    $min      = (float)$_POST['min_price'];
    $max      = (float)$_POST['max_price'];
    $district = clean($_POST['district'] ?? '');
    if ($pname && $avg > 0) {
        $stmt = $db->prepare("INSERT INTO market_prices (product_name, category, avg_price, min_price, max_price, recorded_date, district) VALUES (?,?,?,?,?,CURDATE(),?)");
        $stmt->bind_param("ssddds", $pname, $cat, $avg, $min, $max, $district);
        $stmt->execute();
        $msg = 'success:Market price added.';
    }
}
if ($action === 'delete_price') {
    $pid = (int)($_POST['price_id'] ?? 0);
    if ($pid) {
        $stmt = $db->prepare("DELETE FROM market_prices WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $msg = 'success:Price entry deleted.';
    }
}
if ($action === 'update_price') {
    $pid      = (int)($_POST['price_id'] ?? 0);
    $avgp     = (float)($_POST['avg_price'] ?? 0);
    $minp     = (float)($_POST['min_price'] ?? 0);
    $maxp     = (float)($_POST['max_price'] ?? 0);
    if ($pid) {
        $stmt = $db->prepare("UPDATE market_prices SET avg_price=?, min_price=?, max_price=? WHERE id=?");
        $stmt->bind_param("dddi", $avgp, $minp, $maxp, $pid);
        $stmt->execute();
        $msg = 'success:Price updated.';
    }
}


// FETCH STATS
$totalUsers     = $db->query("SELECT COUNT(*) as c FROM users WHERE role != 'admin'")->fetch_assoc()['c'];
$totalFarmers   = $db->query("SELECT COUNT(*) as c FROM users WHERE role='farmer'")->fetch_assoc()['c'];
$pendingFarmers = $db->query("SELECT COUNT(*) as c FROM users WHERE role='farmer' AND is_verified=0")->fetch_assoc()['c'];
$totalProducts  = $db->query("SELECT COUNT(*) as c FROM products")->fetch_assoc()['c'];
$totalOrders    = $db->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$totalRevenue   = $db->query("SELECT COALESCE(SUM(total_amount),0) as r FROM orders WHERE status!='cancelled'")->fetch_assoc()['r'];
$unreadContact  = $db->query("SELECT COUNT(*) as c FROM contact_messages WHERE is_read=0")->fetch_assoc()['c'];

$tab = $_GET['tab'] ?? 'overview';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <title>Admin Panel - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        body { background:#f0f2f5; }
        .admin-layout { display:flex; min-height:90vh; }
        .sidebar { width:240px; background:#0e1726; color:white; padding:25px 0; flex-shrink:0; position:sticky; top:0; height:100vh; overflow-y:auto; }
        .sidebar .brand { padding:0 22px 22px; border-bottom:1px solid #1c2e42; margin-bottom:12px; }
        .sidebar .brand h3 { font-size:15px; color:#56ad58; }
        .sidebar .brand p { font-size:11px; color:#aaa; margin-top:2px; }
        .sidebar a { display:flex; align-items:center; gap:11px; padding:12px 22px; color:#bbb; text-decoration:none; font-size:13px; transition:.2s; }
        .sidebar a:hover, .sidebar a.active { background:#56ad58; color:white; }
        .sidebar a i { width:16px; }
        .sidebar .sep { padding:10px 22px 6px; font-size:10px; color:#556; text-transform:uppercase; letter-spacing:1px; }
        .main { flex:1; padding:28px; overflow-x:hidden; }
        .page-header { margin-bottom:24px; }
        .page-header h2 { font-size:22px; color:#222; margin-bottom:4px; }
        .page-header p { color:#888; font-size:13px; }
        .stat-row { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
        .stat-box { background:white; border-radius:12px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.06); display:flex; align-items:center; gap:15px; }
        .stat-icon { width:50px; height:50px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
        .stat-num { font-size:26px; font-weight:800; color:#222; line-height:1; }
        .stat-lbl { font-size:12px; color:#888; margin-top:3px; }
        .panel { background:white; border-radius:12px; padding:24px; box-shadow:0 2px 8px rgba(0,0,0,.06); margin-bottom:20px; }
        .panel-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .panel-head h3 { font-size:16px; color:#222; }
        table { width:100%; border-collapse:collapse; }
        thead th { background:#f8f9fa; padding:11px 14px; text-align:left; font-size:12px; color:#666; font-weight:700; text-transform:uppercase; letter-spacing:.5px; }
        tbody td { padding:12px 14px; font-size:13px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        tbody tr:last-child td { border-bottom:none; }
        .badge { padding:3px 11px; border-radius:20px; font-size:11px; font-weight:700; }
        .badge-verified   { background:#d1e7dd; color:#0a3622; }
        .badge-unverified { background:#fff3cd; color:#856404; }
        .badge-farmer     { background:#cfe2ff; color:#084298; }
        .badge-consumer   { background:#f8d7da; color:#842029; }
        .badge-active     { background:#d1e7dd; color:#0a3622; }
        .badge-hidden     { background:#f8d7da; color:#842029; }
        .badge-pending    { background:#fff3cd; color:#856404; }
        .badge-confirmed  { background:#cfe2ff; color:#084298; }
        .badge-delivered  { background:#d1e7dd; color:#0a3622; }
        .badge-cancelled  { background:#f8d7da; color:#842029; }
        .btn-xs { padding:5px 12px; border:none; border-radius:7px; cursor:pointer; font-size:12px; font-weight:600; }
        .btn-verify  { background:#d1e7dd; color:#0a3622; }
        .btn-unver   { background:#fff3cd; color:#856404; }
        .btn-del     { background:#ffe0e0; color:#c0392b; }
        .btn-show    { background:#e8f5e9; color:#2e7d32; }
        .btn-hide    { background:#fff3cd; color:#856404; }
        .btn-green   { background:#56ad58; color:white; padding:9px 20px; border:none; border-radius:9px; font-size:13px; font-weight:600; cursor:pointer; }
        .charts-row  { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }
        .form-inline { display:flex; gap:10px; flex-wrap:wrap; align-items:flex-end; }
        .fi { display:flex; flex-direction:column; gap:5px; }
        .fi label { font-size:12px; font-weight:600; color:#555; }
        .fi input, .fi select { padding:8px 12px; border:1.5px solid #eee; border-radius:8px; font-size:13px; background:#fafafa; }
        .msg-row { border-bottom:1px solid #f5f5f5; padding:14px 0; }
        .msg-row:last-child { border-bottom:none; }
        .badge-unread { background:#ffe0e0; color:#c0392b; }
        .badge-read { background:#f0f0f0; color:#888; }
        .search-admin { padding:8px 14px; border:1.5px solid #eee; border-radius:8px; font-size:13px; width:220px; }
        @media(max-width:1000px){ .stat-row{grid-template-columns:1fr 1fr;} .charts-row{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="admin-layout">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h3>⚙️ Admin Panel</h3>
            <p>DirectFarm LK</p>
        </div>
        <a href="dashboard.php?tab=overview"  class="<?= $tab==='overview'?'active':'' ?>"><i class="fa-solid fa-gauge"></i> Overview</a>
        <div class="sep">User Management</div>
        <a href="dashboard.php?tab=farmers"   class="<?= $tab==='farmers'?'active':'' ?>">
            <i class="fa-solid fa-tractor"></i> Farmers
            <?php if ($pendingFarmers > 0): ?><span style="background:#e74c3c;color:white;border-radius:50%;padding:1px 6px;font-size:10px;margin-left:auto;"><?= $pendingFarmers ?></span><?php endif; ?>
        </a>
        <a href="dashboard.php?tab=consumers" class="<?= $tab==='consumers'?'active':'' ?>"><i class="fa-solid fa-users"></i> Consumers</a>
        <div class="sep">Content</div>
        <a href="dashboard.php?tab=products"  class="<?= $tab==='products'?'active':'' ?>"><i class="fa-solid fa-seedling"></i> Products</a>
        <a href="dashboard.php?tab=orders"    class="<?= $tab==='orders'?'active':'' ?>"><i class="fa-solid fa-bag-shopping"></i> Orders</a>
        <a href="dashboard.php?tab=prices"    class="<?= $tab==='prices'?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> Market Prices</a>
        <div class="sep">Support</div>
        <a href="dashboard.php?tab=messages"  class="<?= $tab==='messages'?'active':'' ?>">
            <i class="fa-solid fa-envelope"></i> Contact Messages
            <?php if ($unreadContact > 0): ?><span style="background:#e74c3c;color:white;border-radius:50%;padding:1px 6px;font-size:10px;margin-left:auto;"><?= $unreadContact ?></span><?php endif; ?>
        </a>
        <hr style="border-color:#1c2e42; margin:12px 0;">
        <a href="../index.php"><i class="fa-solid fa-globe"></i> View Site</a>
        <form method="POST" action="../includes/auth.php">
            <input type="hidden" name="action" value="logout">
            <button type="submit" style="background:none;border:none;color:#ff6b6b;cursor:pointer;padding:12px 22px;font-size:13px;width:100%;text-align:left;display:flex;gap:11px;align-items:center;">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>
    </div>

    <!-- Main -->
    <div class="main">

        <?php if ($msg): list($t,$text) = explode(':', $msg, 2); ?>
        <div style="background:<?= $t==='success'?'#e0ffe8':'#ffe0e0' ?>;color:<?= $t==='success'?'#27ae60':'#c0392b' ?>;padding:12px 18px;border-radius:10px;margin-bottom:18px;font-weight:500;font-size:14px;">
            <?= $t==='success'?'✓':'✗' ?> <?= htmlspecialchars($text) ?>
        </div>
        <?php endif; ?>

      
        <?php if ($tab === 'overview'): ?>
        <div class="page-header"><h2>Dashboard Overview</h2><p>Welcome back, <?= htmlspecialchars($_SESSION['user_name']) ?></p></div>

        <div class="stat-row">
            <div class="stat-box">
                <div class="stat-icon" style="background:#e8f5e9;"><span>👥</span></div>
                <div><div class="stat-num"><?= $totalUsers ?></div><div class="stat-lbl">Total Users</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#fff9e6;"><span>🌾</span></div>
                <div><div class="stat-num"><?= $totalFarmers ?></div><div class="stat-lbl">Farmers <span style="color:#e74c3c;font-size:12px;">(<?= $pendingFarmers ?> pending)</span></div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#e3f2fd;"><span>📦</span></div>
                <div><div class="stat-num"><?= $totalProducts ?></div><div class="stat-lbl">Products Listed</div></div>
            </div>
            <div class="stat-box">
                <div class="stat-icon" style="background:#fce4ec;"><span>💰</span></div>
                <div><div class="stat-num" style="font-size:18px;">Rs.<?= number_format($totalRevenue,0) ?></div><div class="stat-lbl">Total Revenue (<?= $totalOrders ?> orders)</div></div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-row">
            <div class="panel">
                <h3 style="margin-bottom:15px;">Orders by Status</h3>
                <canvas id="orderChart" height="200"></canvas>
                <?php
                $oStats = $db->query("SELECT status, COUNT(*) as c FROM orders GROUP BY status");
                $oL = $oV = [];
                while ($r = $oStats->fetch_assoc()) { $oL[] = ucfirst($r['status']); $oV[] = $r['c']; }
                ?>
                <script>
                new Chart(document.getElementById('orderChart'), {
                    type:'doughnut',
                    data:{ labels:<?= json_encode($oL) ?>, datasets:[{ data:<?= json_encode($oV) ?>, backgroundColor:['#fff3cd','#cfe2ff','#ffe5d0','#d1ecf1','#d1e7dd','#f8d7da'], borderWidth:0 }] },
                    options:{ responsive:true, plugins:{ legend:{ position:'bottom' } } }
                });
                </script>
            </div>
            <div class="panel">
                <h3 style="margin-bottom:15px;">Products by Category</h3>
                <canvas id="catChart" height="200"></canvas>
                <?php
                $cStats = $db->query("SELECT category, COUNT(*) as c FROM products GROUP BY category");
                $cL = $cV = [];
                while ($r = $cStats->fetch_assoc()) { $cL[] = ucfirst($r['category']); $cV[] = $r['c']; }
                ?>
                <script>
                new Chart(document.getElementById('catChart'), {
                    type:'bar',
                    data:{ labels:<?= json_encode($cL) ?>, datasets:[{ data:<?= json_encode($cV) ?>, backgroundColor:'#56ad58', borderRadius:8 }] },
                    options:{ responsive:true, plugins:{ legend:{ display:false } }, scales:{ y:{ beginAtZero:true } } }
                });
                </script>
            </div>
        </div>

        <!-- Recent Orders -->
        <div class="panel">
            <div class="panel-head"><h3>Recent Orders</h3><a href="dashboard.php?tab=orders" style="color:#56ad58;font-size:13px;text-decoration:none;">View all →</a></div>
            <table>
                <thead><tr><th>#</th><th>Consumer</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php $recent = $db->query("SELECT o.*,u.name as cname FROM orders o JOIN users u ON o.consumer_id=u.id ORDER BY o.created_at DESC LIMIT 6");
                    while ($o = $recent->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $o['id'] ?></strong></td>
                        <td><?= htmlspecialchars($o['cname']) ?></td>
                        <td>Rs. <?= number_format($o['total_amount'],2) ?></td>
                        <td><?= ucwords(str_replace('_',' ',$o['payment_method'])) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- FARMERS -->
        <?php elseif ($tab === 'farmers'): ?>
        <div class="page-header">
            <h2>Farmer Management</h2>
            <p>Verify farmers to allow them to list products</p>
        </div>
        <?php if ($pendingFarmers > 0): ?>
        <div style="background:#fff9e6;border-left:4px solid #f39c12;padding:14px 18px;border-radius:10px;margin-bottom:18px;font-size:14px;color:#856404;">
            ⚠ <strong><?= $pendingFarmers ?> farmer(s)</strong> are waiting for verification.
        </div>
        <?php endif; ?>
        <div class="panel">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>District</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php $farmers = $db->query("SELECT * FROM users WHERE role='farmer' ORDER BY is_verified ASC, created_at DESC");
                    while ($f = $farmers->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $f['id'] ?></td>
                        <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                        <td><?= htmlspecialchars($f['email']) ?></td>
                        <td><?= htmlspecialchars($f['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($f['district'] ?? '—') ?></td>
                        <td><?= date('M d Y', strtotime($f['created_at'])) ?></td>
                        <td><span class="badge badge-<?= $f['is_verified']?'verified':'unverified' ?>"><?= $f['is_verified']?'Verified':'Pending' ?></span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <form method="POST">
                                <input type="hidden" name="action" value="verify_farmer">
                                <input type="hidden" name="user_id" value="<?= $f['id'] ?>">
                                <input type="hidden" name="status" value="<?= $f['is_verified']?0:1 ?>">
                                <button type="submit" class="btn-xs <?= $f['is_verified']?'btn-unver':'btn-verify' ?>">
                                    <?= $f['is_verified']?'Unverify':'✓ Verify' ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this farmer? All their products will also be deleted.')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $f['id'] ?>">
                                <button type="submit" class="btn-xs btn-del">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- CONSUMERS -->
        <?php elseif ($tab === 'consumers'): ?>
        <div class="page-header"><h2>Consumer Management</h2><p>View and manage all registered consumers</p></div>
        <div class="panel">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>District</th><th>Joined</th><th>Orders</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php $cons = $db->query("SELECT u.*, COUNT(o.id) as order_count FROM users u LEFT JOIN orders o ON o.consumer_id=u.id WHERE u.role='consumer' GROUP BY u.id ORDER BY u.created_at DESC");
                    while ($c = $cons->fetch_assoc()): ?>
                    <tr>
                        <td>#<?= $c['id'] ?></td>
                        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                        <td><?= htmlspecialchars($c['email']) ?></td>
                        <td><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($c['district'] ?? '—') ?></td>
                        <td><?= date('M d Y', strtotime($c['created_at'])) ?></td>
                        <td><?= $c['order_count'] ?></td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Delete this consumer?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?= $c['id'] ?>">
                                <button type="submit" class="btn-xs btn-del">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- PRODUCTS-->
        <?php elseif ($tab === 'products'): ?>
        <div class="page-header"><h2>Product Management</h2><p>Show or hide product listings across the marketplace</p></div>
        <div class="panel">
            <table>
                <thead><tr><th>Image</th><th>Product</th><th>Farmer</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php $prods = $db->query("SELECT p.*, u.name as fname FROM products p JOIN users u ON p.farmer_id=u.id ORDER BY p.created_at DESC");
                    while ($p = $prods->fetch_assoc()): ?>
                    <tr>
                        <td><img src="../<?= htmlspecialchars($p['image']??'') ?>" style="width:45px;height:45px;object-fit:cover;border-radius:8px;"></td>
                        <td><strong><?= htmlspecialchars($p['name']) ?></strong><br><small style="color:#aaa;"><?= htmlspecialchars($p['district']) ?></small></td>
                        <td><?= htmlspecialchars($p['fname']) ?></td>
                        <td><?= ucfirst($p['category']) ?></td>
                        <td>Rs.<?= number_format($p['price'],2) ?></td>
                        <td><?= $p['stock'] ?> <?= $p['unit'] ?></td>
                        <td><span class="badge badge-<?= $p['is_available']?'active':'hidden' ?>"><?= $p['is_available']?'Active':'Hidden' ?></span></td>
                        <td style="display:flex;gap:6px;flex-wrap:wrap;">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_product">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="available" value="<?= $p['is_available']?0:1 ?>">
                                <button type="submit" class="btn-xs <?= $p['is_available']?'btn-hide':'btn-show' ?>">
                                    <?= $p['is_available']?'Hide':'Show' ?>
                                </button>
                            </form>
                            <form method="POST" onsubmit="return confirm('Delete this product?')">
                                <input type="hidden" name="action" value="delete_product">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-xs btn-del">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- ORDERS -->
        <?php elseif ($tab === 'orders'): ?>
        <div class="page-header"><h2>Order Management</h2><p>All platform orders</p></div>
        <div class="panel">
            <table>
                <thead><tr><th>#</th><th>Consumer</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    <?php $ords = $db->query("SELECT o.*,u.name as cname,COUNT(oi.id) as ic FROM orders o JOIN users u ON o.consumer_id=u.id LEFT JOIN order_items oi ON o.id=oi.order_id GROUP BY o.id ORDER BY o.created_at DESC");
                    while ($o = $ords->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $o['id'] ?></strong></td>
                        <td><?= htmlspecialchars($o['cname']) ?></td>
                        <td><?= $o['ic'] ?> items</td>
                        <td><strong>Rs.<?= number_format($o['total_amount'],2) ?></strong></td>
                        <td><?= ucwords(str_replace('_',' ',$o['payment_method'])) ?></td>
                        <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- MARKET PRICES-->
        <?php elseif ($tab === 'prices'): ?>
        <div class="page-header"><h2>Market Price Management</h2><p>Add and manage price data shown on the Insights page</p></div>
        <div class="panel" style="margin-bottom:20px;">
            <h3 style="margin-bottom:18px;">Add New Price Entry</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_price">
                <div class="form-inline">
                    <div class="fi"><label>Product Name</label><input type="text" name="product_name" placeholder="e.g. Tomato" required></div>
                    <div class="fi">
                        <label>Category</label>
                        <select name="category">
                            <option value="vegetables">Vegetables</option>
                            <option value="fruits">Fruits</option>
                            <option value="grains">Grains</option>
                            <option value="spices">Spices</option>
                        </select>
                    </div>
                    <div class="fi"><label>Avg Price (Rs.)</label><input type="number" name="avg_price" step="0.01" required style="width:100px;"></div>
                    <div class="fi"><label>Min Price</label><input type="number" name="min_price" step="0.01" style="width:90px;"></div>
                    <div class="fi"><label>Max Price</label><input type="number" name="max_price" step="0.01" style="width:90px;"></div>
                    <div class="fi">
                        <label>District</label>
                        <select name="district">
                            <?php foreach (['Colombo','Kandy','Galle','Nuwara Eliya','Matale','Badulla','Polonnaruwa','Anuradhapura','Ratnapura','Kurunegala'] as $d): ?>
                            <option><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fi"><label>&nbsp;</label><button type="submit" class="btn-green">Add Price</button></div>
                </div>
            </form>
        </div>
        <div class="panel">
            <h3 style="margin-bottom:18px;">Existing Price Data</h3>
            <table>
                <thead><tr><th>Product</th><th>Category</th><th>Avg</th><th>Min</th><th>Max</th><th>District</th><th>Date</th></tr></thead>
                <tbody>
                    <?php $prices = $db->query("SELECT * FROM market_prices ORDER BY recorded_date DESC LIMIT 30");
                    while ($p = $prices->fetch_assoc()): ?>
                    <tr id="price-row-<?= $p['id'] ?>">
                        <td><strong><?= htmlspecialchars($p['product_name']) ?></strong></td>
                        <td><?= ucfirst($p['category']) ?></td>
                        <td>
                            <span class="price-display-<?= $p['id'] ?>">Rs. <?= number_format($p['avg_price'],0) ?></span>
                            <input class="price-input-<?= $p['id'] ?>" type="number" value="<?= $p['avg_price'] ?>" style="display:none;width:80px;padding:4px 8px;border:1.5px solid #009933;border-radius:8px;font-size:13px;">
                        </td>
                        <td>
                            <span class="price-display-<?= $p['id'] ?>">Rs. <?= number_format($p['min_price'],0) ?></span>
                            <input class="price-input-<?= $p['id'] ?>" type="number" value="<?= $p['min_price'] ?>" style="display:none;width:80px;padding:4px 8px;border:1.5px solid #009933;border-radius:8px;font-size:13px;">
                        </td>
                        <td>
                            <span class="price-display-<?= $p['id'] ?>">Rs. <?= number_format($p['max_price'],0) ?></span>
                            <input class="price-input-<?= $p['id'] ?>" type="number" value="<?= $p['max_price'] ?>" style="display:none;width:80px;padding:4px 8px;border:1.5px solid #009933;border-radius:8px;font-size:13px;">
                        </td>
                        <td><?= htmlspecialchars($p['district'] ?? '—') ?></td>
                        <td><?= date('M d, Y', strtotime($p['recorded_date'])) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <!-- Edit toggle -->
                                <button type="button" onclick="toggleEdit(<?= $p['id'] ?>)" id="editBtn-<?= $p['id'] ?>"
                                    style="padding:5px 12px;background:#e8f5e9;color:#2e7d32;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
                                    ✏️ Edit
                                </button>
                                <!-- Save (hidden) -->
                                <form method="POST" id="saveForm-<?= $p['id'] ?>" style="display:none;">
                                    <input type="hidden" name="action" value="update_price">
                                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="avg_price" id="avg_<?= $p['id'] ?>">
                                    <input type="hidden" name="min_price" id="min_<?= $p['id'] ?>">
                                    <input type="hidden" name="max_price" id="max_<?= $p['id'] ?>">
                                    <button type="submit"
                                        style="padding:5px 12px;background:#009933;color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
                                        💾 Save
                                    </button>
                                </form>
                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Delete this price entry?')" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_price">
                                    <input type="hidden" name="price_id" value="<?= $p['id'] ?>">
                                    <button type="submit"
                                        style="padding:5px 12px;background:#fce4e4;color:#c62828;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;">
                                        🗑
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- CONTACT MESSAGES -->
        <?php elseif ($tab === 'messages'): ?>
        <div class="page-header"><h2>Contact Messages</h2><p><?= $unreadContact ?> unread message(s)</p></div>
        <div class="panel">
            <?php $cms = $db->query("SELECT * FROM contact_messages ORDER BY submitted_at DESC");
            if ($cms->num_rows === 0): ?>
                <p style="color:#aaa;text-align:center;padding:30px;">No contact messages yet.</p>
            <?php else: while ($m = $cms->fetch_assoc()): ?>
            <div class="msg-row" style="padding:18px 0;">
                <!-- Message header -->
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;flex-wrap:wrap;">
                    <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#009933,#006622);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:15px;flex-shrink:0;">
                        <?= strtoupper(substr($m['name'],0,1)) ?>
                    </div>
                    <div>
                        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                            <strong style="font-size:14px;"><?= htmlspecialchars($m['name']) ?></strong>
                            <a href="mailto:<?= htmlspecialchars($m['email']) ?>" style="color:#888;font-size:13px;text-decoration:none;"><?= htmlspecialchars($m['email']) ?></a>
                            <span class="badge badge-<?= $m['is_read']?'read':'unread' ?>"><?= $m['is_read']?'Read':'New' ?></span>
                            <?php if (!empty($m['admin_reply'])): ?>
                            <span style="background:#e8f5e9;color:#2e7d32;font-size:11px;font-weight:700;padding:2px 8px;border-radius:50px;">✓ Replied</span>
                            <?php endif; ?>
                        </div>
                        <div style="color:#aaa;font-size:12px;"><?= date('M d, Y g:i A', strtotime($m['submitted_at'])) ?></div>
                    </div>
                </div>
                <!-- Message body -->
                <div style="background:#f8faf8;border-left:3px solid #009933;padding:12px 16px;border-radius:0 10px 10px 0;margin:8px 0 12px 48px;color:#444;font-size:14px;line-height:1.6;">
                    <?= nl2br(htmlspecialchars($m['message'])) ?>
                </div>
                <!-- Admin reply if exists -->
                <?php if (!empty($m['admin_reply'])): ?>
                <div style="margin-left:48px;margin-bottom:12px;">
                    <div style="font-size:12px;color:#888;margin-bottom:6px;"><i class="fa-solid fa-reply" style="color:#009933;margin-right:4px;"></i>Your reply · <?= date('M d, Y g:i A', strtotime($m['replied_at'])) ?></div>
                    <div style="background:#e8f5e9;border-left:3px solid #009933;padding:10px 16px;border-radius:0 10px 10px 0;color:#2e7d32;font-size:14px;line-height:1.6;">
                        <?= nl2br(htmlspecialchars($m['admin_reply'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Reply form -->
                <div style="margin-left:48px;">
                    <form method="POST" style="display:flex;gap:8px;align-items:flex-end;">
                        <input type="hidden" name="action" value="admin_reply">
                        <input type="hidden" name="message_id" value="<?= $m['id'] ?>">
                        <div style="flex:1;">
                            <textarea name="reply" rows="2" placeholder="Type your reply to <?= htmlspecialchars($m['name']) ?>..." required
                                style="width:100%;padding:10px 14px;border:1.5px solid #eee;border-radius:12px;font-size:13px;font-family:inherit;resize:none;transition:border-color 0.2s;outline:none;"
                                onfocus="this.style.borderColor='#009933'" onblur="this.style.borderColor='#eee'"><?= htmlspecialchars($m['admin_reply'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" style="background:#009933;color:white;border:none;padding:10px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;display:flex;align-items:center;gap:6px;height:42px;">
                            <i class="fa-solid fa-paper-plane"></i> <?= !empty($m['admin_reply']) ? 'Update' : 'Reply' ?>
                        </button>
                    </form>
                </div>
            </div>
            <?php endwhile; endif; ?>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
function toggleEdit(id) {
    const displays = document.querySelectorAll('.price-display-' + id);
    const inputs   = document.querySelectorAll('.price-input-' + id);
    const editBtn  = document.getElementById('editBtn-' + id);
    const saveForm = document.getElementById('saveForm-' + id);
    const isEditing = editBtn.textContent.includes('Cancel');

    if (isEditing) {
        displays.forEach(el => el.style.display = 'inline');
        inputs.forEach(el => el.style.display = 'none');
        editBtn.textContent = '✏️ Edit';
        editBtn.style.background = '#e8f5e9';
        editBtn.style.color = '#2e7d32';
        saveForm.style.display = 'none';
    } else {
        displays.forEach(el => el.style.display = 'none');
        inputs.forEach(el => el.style.display = 'inline-block');
        editBtn.textContent = '✕ Cancel';
        editBtn.style.background = '#f5f5f5';
        editBtn.style.color = '#888';
        saveForm.style.display = 'inline';
        // On save, copy input values to hidden fields
        saveForm.addEventListener('submit', function() {
            const inpts = document.querySelectorAll('.price-input-' + id);
            document.getElementById('avg_' + id).value = inpts[0].value;
            document.getElementById('min_' + id).value = inpts[1].value;
            document.getElementById('max_' + id).value = inpts[2].value;
        }, { once: true });
    }
}
</script>
</body>
</html>
