<?php

require_once '../includes/config.php';
requireRole('consumer', '../index.php');

$orderId = (int)($_GET['order_id'] ?? 0);
$db = getDB();
$stmt = $db->prepare("SELECT o.*, COUNT(oi.id) as item_count FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id WHERE o.id=? AND o.consumer_id=? GROUP BY o.id");
$stmt->bind_param("ii", $orderId, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header("Location: dashboard.php"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <title>Order Placed! - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=2"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div style="text-align:center; padding:80px 20px; max-width:550px; margin:auto;">
    <div style="font-size:70px; margin-bottom:20px;">🎉</div>
    <h1 style="color:#27ae60; margin-bottom:12px;">Order Placed Successfully!</h1>
    <p style="color:#666; font-size:16px; margin-bottom:25px;">
        Thank you for your order, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>!
        Your order <strong>#<?= $orderId ?></strong> has been received.
    </p>
    <div style="background:#f9fafb; border-radius:14px; padding:25px; text-align:left; margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; margin:8px 0;"><span style="color:#888;">Order ID</span><strong>#<?= $orderId ?></strong></div>
        <div style="display:flex; justify-content:space-between; margin:8px 0;"><span style="color:#888;">Items</span><strong><?= $order['item_count'] ?> products</strong></div>
        <div style="display:flex; justify-content:space-between; margin:8px 0;"><span style="color:#888;">Total</span><strong>Rs. <?= number_format($order['total_amount'],2) ?></strong></div>
        <div style="display:flex; justify-content:space-between; margin:8px 0;"><span style="color:#888;">Payment</span><strong><?= ucwords(str_replace('_',' ',$order['payment_method'])) ?></strong></div>
        <div style="display:flex; justify-content:space-between; margin:8px 0;"><span style="color:#888;">Status</span><span style="background:#fff3cd; color:#856404; padding:3px 10px; border-radius:20px; font-size:13px;">Pending</span></div>
    </div>
    <a href="dashboard.php" style="display:inline-block; background:#56ad58; color:white; padding:14px 35px; border-radius:30px; text-decoration:none; font-size:16px; font-weight:600; margin-right:12px;">My Orders</a>
    <a href="../marketplace.php" style="display:inline-block; background:#f0f0f0; color:#333; padding:14px 35px; border-radius:30px; text-decoration:none; font-size:16px; font-weight:600;">Shop More</a>
</div>
<?php include '../includes/footer.php'; ?>
</body>
</html>
