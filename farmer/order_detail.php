<?php
require_once '../includes/config.php';
requireRole('farmer', '../index.php');

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();

// Verify this farmer has items in this order
$check = $db->prepare("SELECT DISTINCT o.* FROM orders o JOIN order_items oi ON o.id=oi.order_id WHERE o.id=? AND oi.farmer_id=?");
$check->bind_param("ii", $orderId, $_SESSION['user_id']);
$check->execute();
$order = $check->get_result()->fetch_assoc();
if (!$order) { header("Location: dashboard.php?tab=orders"); exit; }

// Fetch consumer info
$consumer = $db->query("SELECT * FROM users WHERE id={$order['consumer_id']}")->fetch_assoc();

// Fetch only THIS farmer's items in this order
$stmt = $db->prepare("SELECT oi.*, p.name, p.image, p.unit, p.category FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=? AND oi.farmer_id=?");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$items = $stmt->get_result();

$farmerTotal = $db->prepare("SELECT SUM(subtotal) as t FROM order_items WHERE order_id=? AND farmer_id=?");
$farmerTotal->bind_param("ii", $orderId, $_SESSION['user_id']);
$farmerTotal->execute();
$myTotal = $farmerTotal->get_result()->fetch_assoc()['t'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $status = clean($_POST['status']);
    $valid  = ['confirmed','processing','shipped','delivered','cancelled'];
    if (in_array($status, $valid)) {
        $db->prepare("UPDATE orders SET status=? WHERE id=?")->bind_param("si", $status, $orderId)->execute();
        header("Location: order_detail.php?id=$orderId&updated=1");
        exit;
    }
}

$statusSteps = ['pending','confirmed','processing','shipped','delivered'];
$currentStep = array_search($order['status'], $statusSteps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Order #<?= $orderId ?> - Farmer View</title>
    <link rel="stylesheet" href="../style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .page-wrap { max-width:900px; margin:30px auto; padding:0 20px; }
        .card { background:white; border-radius:14px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.07); margin-bottom:20px; }
        .card h3 { font-size:17px; color:#333; margin-bottom:18px; padding-bottom:12px; border-bottom:2px solid #f0f0f0; }
        .item-row { display:flex; align-items:center; gap:15px; padding:14px 0; border-bottom:1px solid #f5f5f5; }
        .item-row:last-child { border-bottom:none; }
        .item-row img { width:60px; height:60px; object-fit:cover; border-radius:10px; }
        .badge { padding:5px 14px; border-radius:20px; font-size:12px; font-weight:700; }
        .badge-pending    { background:#fff3cd; color:#856404; }
        .badge-confirmed  { background:#cfe2ff; color:#084298; }
        .badge-processing { background:#ffe5d0; color:#8a3700; }
        .badge-shipped    { background:#d1ecf1; color:#0c5460; }
        .badge-delivered  { background:#d1e7dd; color:#0a3622; }
        .badge-cancelled  { background:#f8d7da; color:#842029; }
        .progress-steps { display:flex; justify-content:space-between; margin:10px 0 5px; position:relative; }
        .progress-steps::before { content:''; position:absolute; top:18px; left:0; right:0; height:3px; background:#eee; z-index:0; }
        .step { text-align:center; flex:1; position:relative; z-index:1; }
        .step-dot { width:36px; height:36px; border-radius:50%; background:#eee; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:700; color:#aaa; }
        .step.done .step-dot  { background:#56ad58; color:white; }
        .step.active .step-dot { background:#2196f3; color:white; box-shadow:0 0 0 5px #bbdefb; }
        .step-label { font-size:11px; color:#aaa; }
        .step.done .step-label, .step.active .step-label { color:#333; font-weight:600; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:15px; }
        .info-item label { font-size:12px; color:#aaa; font-weight:600; text-transform:uppercase; letter-spacing:.5px; display:block; margin-bottom:4px; }
        .info-item span { font-size:15px; color:#333; font-weight:500; }
        .status-select { padding:9px 14px; border:2px solid #56ad58; border-radius:10px; font-size:14px; font-weight:600; color:#333; background:white; cursor:pointer; }
        .btn-green { background:#56ad58; color:white; padding:10px 24px; border:none; border-radius:10px; font-size:14px; font-weight:600; cursor:pointer; }
        .btn-green:hover { background:#3f8e44; }
        .summary-row { display:flex; justify-content:space-between; padding:8px 0; font-size:15px; border-bottom:1px solid #f5f5f5; }
        .summary-row:last-child { border-bottom:none; font-weight:700; font-size:17px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="page-wrap">
    <a href="dashboard.php?tab=orders" style="display:inline-flex; align-items:center; gap:8px; color:#56ad58; text-decoration:none; font-weight:600; margin-bottom:20px; font-size:14px;">
        <i class="fa-solid fa-arrow-left"></i> Back to Orders
    </a>

    <?php if (isset($_GET['updated'])): ?>
    <div style="background:#e0ffe8; color:#27ae60; padding:12px 18px; border-radius:10px; margin-bottom:18px; font-weight:500;">
        ✓ Order status updated successfully.
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="card" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
        <div>
            <h2 style="margin:0; font-size:24px;">Order #<?= $orderId ?></h2>
            <p style="color:#888; font-size:14px; margin-top:5px;">
                <i class="fa-solid fa-clock" style="color:#56ad58;"></i>
                Placed on <?= date('F d, Y \a\t g:i A', strtotime($order['created_at'])) ?>
            </p>
        </div>
        <span class="badge badge-<?= $order['status'] ?>" style="font-size:14px; padding:8px 20px;">
            <?= ucfirst($order['status']) ?>
        </span>
    </div>

    <!-- Order Progress -->
    <?php if ($order['status'] !== 'cancelled'): ?>
    <div class="card">
        <h3>📍 Order Progress</h3>
        <div class="progress-steps">
            <?php foreach ($statusSteps as $si => $step): ?>
            <div class="step <?= $si < $currentStep ? 'done' : ($si === $currentStep ? 'active' : '') ?>">
                <div class="step-dot"><?= $si < $currentStep ? '✓' : ($si + 1) ?></div>
                <div class="step-label"><?= ucfirst($step) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Update Status -->
    <div class="card">
        <h3>🔄 Update Order Status</h3>
        <form method="POST" style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
            <select name="status" class="status-select">
                <?php foreach (['confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-green">Update Status</button>
        </form>
        <p style="font-size:13px; color:#aaa; margin-top:10px;">
            <i class="fa-solid fa-circle-info"></i>
            Confirm → Processing → Shipped → Delivered
        </p>
    </div>

    <!-- Ordered Items (this farmer's only) -->
    <div class="card">
        <h3>📦 Your Items in This Order</h3>
        <?php $items->data_seek(0); while ($item = $items->fetch_assoc()): ?>
        <div class="item-row">
            <img src="../<?= htmlspecialchars($item['image'] ?? 'placeholder.jpg') ?>" alt="">
            <div style="flex:1;">
                <div style="font-weight:600; font-size:15px;"><?= htmlspecialchars($item['name']) ?></div>
                <div style="font-size:13px; color:#888; margin-top:3px;">
                    <?= ucfirst($item['category']) ?> · Rs. <?= number_format($item['unit_price'],2) ?>/<?= $item['unit'] ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:13px; color:#666;">Qty: <?= $item['quantity'] ?> <?= $item['unit'] ?></div>
                <div style="font-weight:700; color:#56ad58; font-size:16px; margin-top:3px;">Rs. <?= number_format($item['subtotal'],2) ?></div>
            </div>
        </div>
        <?php endwhile; ?>
        <div class="summary-row" style="margin-top:15px; padding-top:15px; border-top:2px solid #f0f0f0; border-bottom:none;">
            <span>Your Total Revenue</span>
            <span style="color:#56ad58;">Rs. <?= number_format($myTotal,2) ?></span>
        </div>
    </div>

    <!-- Customer Info -->
    <div class="card">
        <h3>👤 Customer Information</h3>
        <div class="info-grid">
            <div class="info-item">
                <label>Customer Name</label>
                <span><?= htmlspecialchars($consumer['name']) ?></span>
            </div>
            <div class="info-item">
                <label>Phone</label>
                <span><?= htmlspecialchars($consumer['phone'] ?? 'Not provided') ?></span>
            </div>
            <div class="info-item">
                <label>District</label>
                <span><?= htmlspecialchars($consumer['district'] ?? '—') ?></span>
            </div>
            <div class="info-item">
                <label>Payment Method</label>
                <span><?= ucwords(str_replace('_',' ',$order['payment_method'])) ?></span>
            </div>
        </div>
        <div style="margin-top:15px;">
            <label style="font-size:12px; color:#aaa; font-weight:600; text-transform:uppercase; letter-spacing:.5px;">Delivery Address</label>
            <p style="font-size:15px; color:#333; margin-top:5px;"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
        </div>
        <?php if ($order['notes']): ?>
        <div style="margin-top:12px; background:#fffbf0; border-left:4px solid #f39c12; padding:10px 14px; border-radius:6px;">
            <label style="font-size:12px; color:#aaa; font-weight:600; text-transform:uppercase;">Customer Note</label>
            <p style="font-size:14px; color:#555; margin-top:4px;"><?= htmlspecialchars($order['notes']) ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Message Customer -->
    <div class="card">
        <h3>💬 Message Customer</h3>
        <form method="POST" action="../includes/message.php" style="display:flex; gap:12px; align-items:flex-end;">
            <input type="hidden" name="receiver_id" value="<?= $consumer['id'] ?>">
            <input type="hidden" name="redirect" value="farmer/order_detail.php?id=<?= $orderId ?>">
            <textarea name="message" rows="3" placeholder="Send a message to the customer about this order..." required style="flex:1; padding:12px; border:1.5px solid #eee; border-radius:10px; font-size:14px; resize:vertical;"></textarea>
            <button type="submit" class="btn-green" style="flex-shrink:0;">Send</button>
        </form>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
