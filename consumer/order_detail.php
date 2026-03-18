<?php
require_once '../includes/config.php';
requireRole('consumer', '../index.php');

$orderId = (int)($_GET['id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT o.* FROM orders o WHERE o.id=? AND o.consumer_id=?");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header("Location: dashboard.php"); exit; }

$items = $db->query("SELECT oi.*, p.name, p.image, p.unit, u.name as farmer_name FROM order_items oi JOIN products p ON oi.product_id=p.id JOIN users u ON oi.farmer_id=u.id WHERE oi.order_id=$orderId");

$statusSteps = ['pending','confirmed','processing','shipped','delivered'];
$currentStep = array_search($order['status'], $statusSteps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Order #<?= $orderId ?> - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .order-container { max-width:800px; margin:30px auto; padding:0 20px; }
        .order-card { background:white; border-radius:14px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.07); margin-bottom:20px; }
        .order-card h3 { margin-bottom:18px; font-size:17px; color:#333; }
        .progress-steps { display:flex; justify-content:space-between; margin:10px 0 20px; position:relative; }
        .progress-steps::before { content:''; position:absolute; top:18px; left:0; right:0; height:3px; background:#eee; z-index:0; }
        .step { text-align:center; flex:1; position:relative; z-index:1; }
        .step-dot { width:36px; height:36px; border-radius:50%; background:#eee; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:700; }
        .step.done .step-dot { background:#56ad58; color:white; }
        .step.active .step-dot { background:#2196f3; color:white; box-shadow:0 0 0 5px #bbdefb; }
        .step-label { font-size:11px; color:#888; }
        .step.done .step-label, .step.active .step-label { color:#333; font-weight:600; }
        .item-row { display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid #f5f5f5; }
        .item-row img { width:55px; height:55px; object-fit:cover; border-radius:9px; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="order-container">
    <a href="dashboard.php" style="display:inline-block; margin:20px 0; color:#56ad58; text-decoration:none; font-size:14px;">← Back to Dashboard</a>

    <div class="order-card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
            <div>
                <h2 style="margin:0; font-size:22px;">Order #<?= $orderId ?></h2>
                <p style="color:#888; font-size:14px; margin-top:4px;"><?= date('F d, Y g:i A', strtotime($order['created_at'])) ?></p>
            </div>
            <span style="background:<?= $order['status']==='delivered'?'#d1e7dd':($order['status']==='cancelled'?'#f8d7da':'#fff3cd') ?>; color:<?= $order['status']==='delivered'?'#0a3622':($order['status']==='cancelled'?'#842029':'#856404') ?>; padding:8px 18px; border-radius:20px; font-weight:600; font-size:13px;">
                <?= ucfirst($order['status']) ?>
            </span>
        </div>

        <!-- Progress Tracker -->
        <?php if ($order['status'] !== 'cancelled'): ?>
        <div class="progress-steps">
            <?php foreach ($statusSteps as $si => $step): ?>
            <div class="step <?= $si < $currentStep ? 'done' : ($si === $currentStep ? 'active' : '') ?>">
                <div class="step-dot"><?= $si < $currentStep ? '✓' : ($si+1) ?></div>
                <div class="step-label"><?= ucfirst($step) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Order Items -->
    <div class="order-card">
        <h3>📦 Order Items</h3>
        <?php while ($item = $items->fetch_assoc()): ?>
        <div class="item-row">
            <img src="../<?= htmlspecialchars($item['image'] ?? '') ?>" alt="">
            <div style="flex:1;">
                <div style="font-weight:600;"><?= htmlspecialchars($item['name']) ?></div>
                <div style="font-size:13px; color:#888;">By <?= htmlspecialchars($item['farmer_name']) ?> · Rs. <?= number_format($item['unit_price'],2) ?>/<?= $item['unit'] ?></div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:13px; color:#888;">x<?= $item['quantity'] ?> <?= $item['unit'] ?></div>
                <div style="font-weight:700; color:#333;">Rs. <?= number_format($item['subtotal'],2) ?></div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <!-- Order Summary -->
    <div class="order-card">
        <h3>💰 Payment Summary</h3>
        <div style="display:flex; justify-content:space-between; margin:8px 0; font-size:15px;"><span style="color:#666;">Subtotal</span><span>Rs. <?= number_format($order['total_amount'],2) ?></span></div>
        <div style="display:flex; justify-content:space-between; margin:8px 0; font-size:15px;"><span style="color:#666;">Payment Method</span><span><?= ucwords(str_replace('_',' ',$order['payment_method'])) ?></span></div>
        <div style="display:flex; justify-content:space-between; margin:12px 0 0; font-size:17px; font-weight:700; border-top:2px solid #f0f0f0; padding-top:12px;"><span>Total</span><span>Rs. <?= number_format($order['total_amount'],2) ?></span></div>
    </div>

    <!-- Delivery Address -->
    <div class="order-card">
        <h3>🚚 Delivery Address</h3>
        <p style="color:#555; font-size:15px;"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></p>
        <?php if ($order['notes']): ?>
        <p style="margin-top:10px; color:#888; font-size:13px;">Note: <?= htmlspecialchars($order['notes']) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
