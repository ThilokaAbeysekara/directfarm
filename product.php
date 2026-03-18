<?php
// product.php - Product Detail Page
require_once 'includes/config.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: marketplace.php"); exit; }

$db = getDB();

// Fetch product + farmer info
$stmt = $db->prepare("SELECT p.*, u.name as farmer_name, u.phone as farmer_phone, u.district as farmer_district, u.id as farmer_uid,
    (SELECT COUNT(*) FROM orders oi JOIN order_items oii ON oi.id=oii.order_id WHERE oii.farmer_id=p.farmer_id) as farmer_sales,
    u.created_at as farmer_since
    FROM products p JOIN users u ON p.farmer_id=u.id WHERE p.id=? AND p.is_available=1");
$stmt->bind_param("i", $id);
$stmt->execute();
$p = $stmt->get_result()->fetch_assoc();
if (!$p) { header("Location: marketplace.php"); exit; }

// Handle Add to Cart
$cartMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isLoggedIn()) {
        $cartMsg = 'error:Please log in to add items to cart.';
    } elseif (!hasRole('consumer')) {
        $cartMsg = 'error:Only consumers can add items to cart.';
    } elseif ($_POST['action'] === 'add_cart') {
        $qty = max(1, (float)($_POST['qty'] ?? 1));
        $stmt2 = $db->prepare("INSERT INTO cart (consumer_id, product_id, quantity) VALUES (?,?,?)
                               ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $stmt2->bind_param("iidd", $_SESSION['user_id'], $id, $qty, $qty);
        $stmt2->execute();
        $cartMsg = 'success:Added to cart successfully!';
    }
}

// Handle Review Submission
$reviewMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'review') {
    if (!isLoggedIn() || !hasRole('consumer')) {
        $reviewMsg = 'error:Please log in as a consumer to write a review.';
    } else {
        $rating  = (int)($_POST['rating'] ?? 0);
        $comment = clean($_POST['comment'] ?? '');
        if ($rating < 1 || $rating > 5) {
            $reviewMsg = 'error:Please select a valid star rating.';
        } elseif (empty($comment)) {
            $reviewMsg = 'error:Please write a comment.';
        } else {
            // Check if already reviewed
            $rcheck = $db->prepare("SELECT id FROM reviews WHERE product_id=? AND consumer_id=?");
            $rcheck->bind_param("ii", $id, $_SESSION['user_id']);
            $rcheck->execute();
            if ($rcheck->get_result()->num_rows > 0) {
                $reviewMsg = 'error:You have already reviewed this product.';
            } else {
                $rstmt = $db->prepare("INSERT INTO reviews (product_id, consumer_id, rating, comment) VALUES (?,?,?,?)");
                $rstmt->bind_param("iiis", $id, $_SESSION['user_id'], $rating, $comment);
                $rstmt->execute();
                $reviewMsg = 'success:Review submitted successfully!';
            }
        }
    }
}

// Fetch reviews
$reviews = $db->query("SELECT r.*, u.name as reviewer FROM reviews r JOIN users u ON r.consumer_id=u.id WHERE r.product_id=$id ORDER BY r.created_at DESC");
$avgRating = $db->query("SELECT AVG(rating) as avg FROM reviews WHERE product_id=$id")->fetch_assoc()['avg'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= htmlspecialchars($p['name']) ?> - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<a href="marketplace.php" class="back-link" style="display:block; padding:15px 5%; font-size:15px; color:#333; text-decoration:none;">
    <i class="fa-solid fa-arrow-left"></i> Back to Marketplace
</a>

<!-- Flash Messages -->
<?php if ($cartMsg): list($type,$msg) = explode(':', $cartMsg, 2); ?>
<div style="background:<?= $type==='success'?'#e0ffe8':'#ffe0e0' ?>; color:<?= $type==='success'?'#27ae60':'#c0392b' ?>; padding:12px 5%; font-weight:500;">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="product-details-container">
    <div class="product-image">
        <img src="<?= htmlspecialchars($p['image'] ?? 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($p['name']) ?>" style="width:100%; border-radius:12px;">
    </div>
    <div class="product-info">
        <h1 class="product-title"><?= htmlspecialchars($p['name']) ?></h1>
        <p><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($p['district']) ?></p>
        <?php if ($avgRating): ?>
        <p style="margin-top:6px; color:#f39c12; font-size:18px;">
            <?= str_repeat('★', round($avgRating)) ?><?= str_repeat('☆', 5-round($avgRating)) ?>
            <span style="color:#666; font-size:14px;"><?= number_format($avgRating,1) ?>/5</span>
        </p>
        <?php endif; ?>
        <h3 class="price-tag" style="margin:15px 0;">Rs. <?= number_format($p['price'],2) ?> / <?= htmlspecialchars($p['unit']) ?></h3>
        <hr>
        <h3 style="margin:15px 0 8px;">Description</h3>
        <p class="product-description"><?= htmlspecialchars($p['description']) ?></p>

        <p class="highlight" style="margin:15px 0;">
            <i class="fa-solid fa-truck"></i> Free delivery over Rs. 1000 &nbsp;
            <i class="fa-solid fa-circle-check"></i> Quality guaranteed
        </p>

        <p style="color:<?= $p['stock'] > 0 ? '#27ae60' : '#e74c3c' ?>; font-weight:600;">
            <?= $p['stock'] > 0 ? "✓ In Stock ({$p['stock']} {$p['unit']} available)" : "✗ Out of Stock" ?>
        </p>

        <?php if ($p['stock'] > 0): ?>
        <form method="POST">
            <input type="hidden" name="action" value="add_cart">
            <div class="quantity-box">
                <button type="button" class="qty-btn" onclick="changeQty(-1)">–</button>
                <input type="number" name="qty" id="qty" value="1" min="1" max="<?= $p['stock'] ?>">
                <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
            </div>
            <p class="total">Total Price: Rs. <span id="totalPrice"><?= number_format($p['price'],2) ?></span></p>
            <div style="display:flex; gap:10px; margin-top:10px;">
                <button type="submit" class="add-cart-btn" style="flex:1; padding:12px 0;"><i class="fa-solid fa-cart-shopping"></i> Add To Cart</button>
                <button type="button" class="contact-btn" style="flex:1; padding:12px 0;" onclick="openMessageModal()"><i class="fa-solid fa-message"></i> Contact Farmer</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Farmer Info -->
<div class="farmer-section">
    <h2>About the Farmer</h2>
    <div class="farmer-box">
        <div class="farmer-left">
            <h3><i class="fa fa-user-circle"></i> <?= htmlspecialchars($p['farmer_name']) ?></h3><br>
            <p>
                <i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($p['farmer_district']) ?> &nbsp;
                <?php if ($avgRating): ?>
                    <i class="fa-solid fa-star"></i> <?= number_format($avgRating,1) ?> ratings &nbsp;
                <?php endif; ?>
                <i class="fa-solid fa-seedling"></i> <?= $p['farmer_sales'] ?>+ sales &nbsp;
                <i class="fa-solid fa-calendar"></i> Member since <?= date('Y', strtotime($p['farmer_since'])) ?>
            </p>
        </div>
    </div>
</div>

<!-- Customer Reviews -->
<div class="reviews-section">
    <h2>Customer Reviews (<?= $reviews->num_rows ?>)</h2>
    <?php
    $reviews->data_seek(0);
    while ($r = $reviews->fetch_assoc()):
    ?>
    <div class="review-card">
        <h4><?= htmlspecialchars($r['reviewer']) ?></h4>
        <p style="color:#f39c12;"><?= str_repeat('★', $r['rating']) ?><?= str_repeat('☆', 5-$r['rating']) ?> <?= $r['rating'] ?>.0</p>
        <p><?= htmlspecialchars($r['comment']) ?></p>
        <small style="color:#aaa;"><?= date('M d, Y', strtotime($r['created_at'])) ?></small>
    </div>
    <?php endwhile; ?>
</div>

<!-- Write Review -->
<?php if (isLoggedIn() && hasRole('consumer')): ?>
<div class="review-section">
    <h2>Write a Review</h2>
    <?php if ($reviewMsg): list($rtype,$rmsg) = explode(':', $reviewMsg, 2); ?>
    <div style="background:<?= $rtype==='success'?'#e0ffe8':'#ffe0e0' ?>; color:<?= $rtype==='success'?'#27ae60':'#c0392b' ?>; padding:10px; border-radius:8px; margin-bottom:15px;">
        <?= htmlspecialchars($rmsg) ?>
    </div>
    <?php endif; ?>
    <form method="POST">
        <input type="hidden" name="action" value="review">
        <div class="star-rating" id="starRating">
            <?php for ($i=1; $i<=5; $i++): ?>
            <i class="fa-solid fa-star star" data-value="<?= $i ?>" style="color:#ddd; font-size:26px; cursor:pointer;"></i>
            <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="ratingInput" value="0">
        <textarea name="comment" class="review-input" placeholder="Write your review here..." style="margin-top:10px;"></textarea>
        <button type="submit" class="submit-review-btn">Submit Review</button>
    </form>
</div>
<?php elseif (!isLoggedIn()): ?>
<div class="review-section" style="text-align:center; color:#666;">
    <p><a href="#" onclick="openAuth('login')" style="color:#56ad58;">Login</a> to write a review.</p>
</div>
<?php endif; ?>

<!-- Message Modal -->
<div id="messageModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:3000; justify-content:center; align-items:center;">
    <div style="background:white; padding:35px; border-radius:16px; width:420px; position:relative;">
        <span onclick="document.getElementById('messageModal').style.display='none'" style="position:absolute; top:15px; right:20px; font-size:24px; cursor:pointer; color:#aaa;">×</span>
        <h3 style="margin-bottom:15px;">Message to <?= htmlspecialchars($p['farmer_name']) ?></h3>
        <?php if (isLoggedIn() && hasRole('consumer')): ?>
        <form method="POST" action="includes/message.php">
            <input type="hidden" name="receiver_id" value="<?= $p['farmer_uid'] ?>">
            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="redirect" value="product.php?id=<?= $p['id'] ?>">
            <textarea name="message" rows="4" style="width:100%; padding:12px; border:1px solid #ddd; border-radius:8px; font-size:14px; margin-bottom:15px;" placeholder="Hi, I'm interested in your <?= htmlspecialchars($p['name']) ?>..."></textarea>
            <button type="submit" style="width:100%; background:#56ad58; color:white; padding:12px; border:none; border-radius:8px; font-size:16px; cursor:pointer;">Send Message</button>
        </form>
        <?php else: ?>
        <p style="color:#666;">Please <a href="#" onclick="openAuth('login'); document.getElementById('messageModal').style.display='none';" style="color:#56ad58;">login</a> to contact the farmer.</p>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
const pricePerUnit = <?= $p['price'] ?>;
function changeQty(val) {
    const input = document.getElementById('qty');
    let qty = parseInt(input.value) + val;
    if (qty < 1) qty = 1;
    input.value = qty;
    document.getElementById('totalPrice').innerText = (qty * pricePerUnit).toLocaleString('en-LK', {minimumFractionDigits:2});
}
document.getElementById('qty')?.addEventListener('input', function() {
    document.getElementById('totalPrice').innerText = (parseInt(this.value||1) * pricePerUnit).toLocaleString('en-LK', {minimumFractionDigits:2});
});
// Stars
document.querySelectorAll('.star').forEach(star => {
    star.addEventListener('click', () => {
        const val = parseInt(star.getAttribute('data-value'));
        document.getElementById('ratingInput').value = val;
        document.querySelectorAll('.star').forEach(s => {
            s.style.color = parseInt(s.getAttribute('data-value')) <= val ? 'gold' : '#ddd';
        });
    });
});
function openMessageModal() { document.getElementById('messageModal').style.display = 'flex'; }
</script>
</body>
</html>
