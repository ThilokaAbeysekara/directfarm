<?php
require_once '../includes/config.php';
requireRole('consumer', '../index.php');

$db = getDB();

// cart action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'remove') {
        $pid = (int)$_POST['product_id'];
        $uid = $_SESSION['user_id'];
        $stmt = $db->prepare("DELETE FROM cart WHERE consumer_id=? AND product_id=?");
        if ($stmt) { $stmt->bind_param("ii", $uid, $pid); $stmt->execute(); }
    } elseif ($action === 'update') {
        $pid = (int)$_POST['product_id'];
        $uid = $_SESSION['user_id'];
        $qty = max(1, (float)$_POST['qty']);
        $stmt = $db->prepare("UPDATE cart SET quantity=? WHERE consumer_id=? AND product_id=?");
        if ($stmt) { $stmt->bind_param("dii", $qty, $uid, $pid); $stmt->execute(); }
    }
    header("Location: cart.php");
    exit;
}

// Fetch cart items
$stmt = $db->prepare("SELECT c.*, p.name, p.price, p.unit, p.image, p.stock, p.district, u.name as farmer_name
    FROM cart c JOIN products p ON c.product_id=p.id JOIN users u ON p.farmer_id=u.id
    WHERE c.consumer_id=? ORDER BY c.added_at DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$cartItems = $stmt->get_result();

$total    = 0;
$allItems = [];
while ($item = $cartItems->fetch_assoc()) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $total           += $item['subtotal'];
    $allItems[]       = $item;
}
// Zone-based delivery fee
$expressZone  = ['Colombo','Gampaha','Kalutara','Kandy','Galle','Matara'];
$standardZone = ['Nuwara Eliya','Matale','Badulla','Ratnapura','Kegalle','Hambantota','Kurunegala'];
$extendedZone = ['Jaffna','Kilinochchi','Mannar','Vavuniya','Trincomalee','Batticaloa','Ampara','Puttalam','Anuradhapura','Polonnaruwa','Monaragala'];

$dStmt = $db->prepare("SELECT district FROM users WHERE id=?");
$dStmt->bind_param("i", $_SESSION['user_id']);
$dStmt->execute();
$dRow = $dStmt->get_result()->fetch_assoc();
$userDistrict = $dRow['district'] ?? '';

if (in_array($userDistrict, $expressZone)) {
    $zoneName   = 'Express Zone';
    $zoneFee    = 150;
    $freeThresh = 1000;
} elseif (in_array($userDistrict, $standardZone)) {
    $zoneName   = 'Standard Zone';
    $zoneFee    = 200;
    $freeThresh = 1000;
} elseif (in_array($userDistrict, $extendedZone)) {
    $zoneName   = 'Extended Zone';
    $zoneFee    = 350;
    $freeThresh = 1500;
} else {
    $zoneName   = 'Standard Zone';
    $zoneFee    = 200;
    $freeThresh = 1000;
}

// Only show delivery fee if cart has items
$deliveryFee = ($total > 0) ? ($total >= $freeThresh ? 0 : $zoneFee) : 0;
$grandTotal  = $total + $deliveryFee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Cart - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=5"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .cart-container { max-width:1100px; margin:30px auto; padding:0 20px; display:grid; grid-template-columns:2fr 1fr; gap:25px; }
        .cart-table { background:white; border-radius:14px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.07); }
        .cart-header { background:#56ad58; color:white; padding:18px 25px; font-size:18px; font-weight:600; }
        .cart-item { display:flex; align-items:center; padding:18px 25px; border-bottom:1px solid #f0f0f0; gap:15px; }
        .cart-item img { width:75px; height:75px; object-fit:cover; border-radius:10px; }
        .cart-item-info { flex:1; }
        .qty-input { width:65px; padding:6px 10px; border:1px solid #ddd; border-radius:8px; text-align:center; font-size:14px; }
        .btn-remove { background:#ffe0e0; color:#e74c3c; border:none; padding:7px 14px; border-radius:8px; cursor:pointer; font-size:13px; }
        .summary-box { background:white; border-radius:14px; padding:25px; box-shadow:0 2px 10px rgba(0,0,0,0.07); position:sticky; top:90px; }
        .summary-row { display:flex; justify-content:space-between; margin:10px 0; font-size:15px; }
        .summary-row.total { font-weight:700; font-size:18px; color:#333; border-top:2px solid #f0f0f0; padding-top:12px; }
        .checkout-btn { display:block; width:100%; background:#56ad58; color:white; padding:15px; border:none; border-radius:12px; font-size:17px; font-weight:600; cursor:pointer; text-align:center; text-decoration:none; margin-top:18px; }
        .checkout-btn:hover { background:#3f8e44; }
        .empty-cart { text-align:center; padding:60px; color:#aaa; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<h2 class="page-title" style="padding:25px 5% 5px;">🛒 Your Cart</h2>

<div class="cart-container">
    <div>
        <div class="cart-table">
            <div class="cart-header">Cart Items (<?= count($allItems) ?>)</div>
            <?php if (empty($allItems)): ?>
            <div class="empty-cart">
                <i class="fa-solid fa-cart-shopping" style="font-size:50px; margin-bottom:15px; display:block;"></i>
                <h3>Your cart is empty</h3>
                <p>Browse our marketplace to find fresh products.</p>
                <a href="../marketplace.php" style="color:#56ad58; font-weight:600;">Shop Now →</a>
            </div>
            <?php else: ?>
                <?php foreach ($allItems as $item): ?>
                <div class="cart-item">
                    <img src="../<?= htmlspecialchars($item['image'] ?? 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <div class="cart-item-info">
                        <h4 style="margin:0 0 4px;"><?= htmlspecialchars($item['name']) ?></h4>
                        <p style="font-size:13px; color:#888;">By <?= htmlspecialchars($item['farmer_name']) ?> · <?= htmlspecialchars($item['district']) ?></p>
                        <p style="font-weight:600; color:#56ad58; margin-top:4px;">Rs. <?= number_format($item['price'],2) ?> / <?= $item['unit'] ?></p>
                    </div>
                    <!-- Update Qty -->
                    <form method="POST" style="display:flex; align-items:center; gap:8px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                        <input type="number" name="qty" class="qty-input" value="<?= $item['quantity'] ?>" min="1" max="<?= $item['stock'] ?>" onchange="this.form.submit()">
                    </form>
                    <p style="font-weight:700; min-width:90px; text-align:right;">Rs. <?= number_format($item['subtotal'],2) ?></p>
                    <form method="POST">
                        <input type="hidden" name="action" value="remove">
                        <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                        <button type="submit" class="btn-remove">✕ Remove</button>
                    </form>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Summary -->
    <div class="summary-box">
        <h3 style="margin-bottom:18px;">Order Summary</h3>
        <div class="summary-row"><span>Subtotal</span><span>Rs. <?= number_format($total,2) ?></span></div>
        <?php if ($total > 0): ?>
        <div class="summary-row">
            <span>Delivery <small style="color:#888;font-size:11px;">(<?= $zoneName ?>)</small></span>
            <span><?= $deliveryFee === 0 ? '<span style="color:#27ae60;font-weight:700;">Free 🎉</span>' : 'Rs. '.number_format($deliveryFee,2) ?></span>
        </div>
        <?php if ($deliveryFee > 0): ?>
        <div style="font-size:12px; color:#aaa; margin-bottom:8px;">
            Add Rs. <?= number_format($freeThresh-$total,2) ?> more for free delivery
            <span style="color:#888;">(<?= $zoneName ?>)</span>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="summary-row total"><span>Total</span><span>Rs. <?= number_format($grandTotal,2) ?></span></div>
        <?php if (!empty($allItems)): ?>
        <a href="checkout.php" class="checkout-btn">Proceed to Checkout →</a>
        <?php endif; ?>
        <a href="../marketplace.php" style="display:block; text-align:center; margin-top:12px; color:#56ad58; font-size:14px;">Continue Shopping</a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
</body>
</html>
