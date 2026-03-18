<?php
// logistics.php - Logistics & Delivery Page
require_once 'includes/config.php';
$db = getDB();

// ─── TRACKING LOOKUP ─────────────────────────────────────────────────────────
$trackedOrder = null;
$trackError   = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['track_order'])) {
    $trackId    = (int)($_POST['order_id'] ?? 0);
    $trackEmail = clean($_POST['email'] ?? '');

    if (!$trackId || !$trackEmail) {
        $trackError = 'Please enter both Order ID and email.';
    } else {
        $stmt = $db->prepare("
            SELECT o.*, u.name as consumer_name, u.email as consumer_email,
                   COUNT(oi.id) as item_count, SUM(oi.subtotal) as items_total
            FROM orders o
            JOIN users u ON o.consumer_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ? AND u.email = ?
            GROUP BY o.id
        ");
        $stmt->bind_param("is", $trackId, $trackEmail);
        $stmt->execute();
        $trackedOrder = $stmt->get_result()->fetch_assoc();
        if (!$trackedOrder) $trackError = 'No order found with that ID and email combination.';
    }
}

// Fetch delivery stats for display
$totalDelivered = $db->query("SELECT COUNT(*) as c FROM orders WHERE status='delivered'")->fetch_assoc()['c'];
$avgDeliveryDays = 2; // static for display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Logistics & Delivery - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ── Hero ── */
        .logistics-hero {
            background: linear-gradient(135deg, #0e1726 0%, #1a3a2a 60%, #56ad58 100%);
            color: white;
            padding: 70px 5%;
            text-align: center;
        }
        .logistics-hero h1 { font-size: 38px; margin-bottom: 12px; }
        .logistics-hero p  { font-size: 16px; color: #cce8cc; max-width: 600px; margin: 0 auto; }

        /* ── Shared ── */
        .section { padding: 60px 5%; }
        .section-title { font-size: 26px; font-weight: 700; color: #1a2e1a; margin-bottom: 8px; }
        .section-sub   { color: #666; font-size: 15px; margin-bottom: 35px; }
        .card { background: white; border-radius: 16px; padding: 28px; box-shadow: 0 4px 18px rgba(0,0,0,0.07); }

        /* ── Tracking Box ── */
        .track-section {
            background: #f0f7f0;
            padding: 55px 5%;
        }
        .track-box {
            max-width: 680px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.10);
        }
        .track-box h2 { font-size: 24px; margin-bottom: 6px; color: #1a2e1a; }
        .track-box p.sub { color: #888; font-size: 14px; margin-bottom: 28px; }
        .track-inputs { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 18px; }
        .track-inputs input {
            padding: 13px 16px;
            border: 1.5px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            background: #fafafa;
            transition: .2s;
        }
        .track-inputs input:focus { outline: none; border-color: #56ad58; background: #fff; }
        .track-btn {
            width: 100%;
            background: #56ad58;
            color: white;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s;
        }
        .track-btn:hover { background: #3f8e44; }
        .track-error { background: #ffe0e0; color: #c0392b; padding: 12px 16px; border-radius: 10px; margin-bottom: 18px; font-size: 14px; }

        /* ── Tracking Result ── */
        .track-result { margin-top: 28px; border-top: 2px solid #f0f0f0; padding-top: 25px; }
        .track-result h3 { font-size: 18px; color: #222; margin-bottom: 18px; }
        .progress-track {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 10px 0 28px;
        }
        .progress-track::before {
            content: '';
            position: absolute;
            top: 22px;
            left: 5%;
            right: 5%;
            height: 4px;
            background: #eee;
            z-index: 0;
        }
        .progress-track::after {
            content: '';
            position: absolute;
            top: 22px;
            left: 5%;
            height: 4px;
            background: #56ad58;
            z-index: 1;
            transition: width .5s ease;
        }
        .pt-step { text-align: center; flex: 1; position: relative; z-index: 2; }
        .pt-dot {
            width: 44px; height: 44px;
            border-radius: 50%;
            background: #eee;
            margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 17px;
            border: 3px solid #eee;
            transition: .3s;
        }
        .pt-step.done   .pt-dot { background: #56ad58; border-color: #56ad58; color: white; }
        .pt-step.active .pt-dot { background: white;   border-color: #56ad58; color: #56ad58; box-shadow: 0 0 0 6px #d4edda; }
        .pt-step.future .pt-dot { background: #f8f8f8; border-color: #ddd; color: #bbb; }
        .pt-label { font-size: 12px; font-weight: 600; color: #aaa; }
        .pt-step.done   .pt-label { color: #27ae60; }
        .pt-step.active .pt-label { color: #1a6e2a; }
        .pt-time { font-size: 11px; color: #bbb; margin-top: 3px; }

        .order-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
        .meta-item label { display: block; font-size: 11px; color: #aaa; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 3px; }
        .meta-item span  { font-size: 14px; color: #333; font-weight: 600; }
        .status-badge {
            display: inline-block;
            padding: 5px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 700;
        }

        /* ── Stats Row ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            max-width: 1100px;
            margin: 0 auto 50px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 24px;
            text-align: center;
            box-shadow: 0 3px 14px rgba(0,0,0,0.07);
            border-top: 4px solid #56ad58;
        }
        .stat-card .icon { font-size: 34px; margin-bottom: 10px; }
        .stat-card .num  { font-size: 30px; font-weight: 800; color: #1a2e1a; }
        .stat-card .lbl  { font-size: 13px; color: #888; margin-top: 4px; }

        /* ── Process Timeline ── */
        .timeline { max-width: 750px; margin: 0 auto; position: relative; padding-left: 35px; }
        .timeline::before { content:''; position:absolute; left:15px; top:0; bottom:0; width:3px; background:#e0f0e0; border-radius:3px; }
        .tl-item { position: relative; margin-bottom: 35px; }
        .tl-item:last-child { margin-bottom: 0; }
        .tl-dot {
            position: absolute;
            left: -26px;
            top: 4px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: #56ad58;
            color: white;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            font-weight: 700;
            box-shadow: 0 0 0 5px #e0f0e0;
        }
        .tl-content { background: white; border-radius: 12px; padding: 18px 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); }
        .tl-content h4 { margin-bottom: 5px; color: #1a2e1a; font-size: 15px; }
        .tl-content p  { color: #666; font-size: 13px; line-height: 1.6; margin: 0; }
        .tl-time { font-size: 11px; color: #aaa; margin-top: 6px; font-weight: 600; }

        /* ── Zones Grid ── */
        .zones-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; max-width: 1100px; margin: 0 auto; }
        .zone-card {
            background: white;
            border-radius: 14px;
            padding: 22px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.07);
            border-left: 5px solid;
            transition: .2s;
        }
        .zone-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.12); }
        .zone-card h4 { font-size: 16px; margin-bottom: 8px; color: #222; }
        .zone-card .districts { font-size: 13px; color: #666; line-height: 1.8; }
        .zone-card .fee { font-size: 14px; font-weight: 700; margin-top: 12px; }
        .zone-card .eta { font-size: 12px; color: #888; margin-top: 3px; }
        .zone-express { border-color: #27ae60; }
        .zone-standard{ border-color: #3498db; }
        .zone-economy  { border-color: #f39c12; }

        /* ── FAQ Accordion ── */
        .faq-list { max-width: 760px; margin: 0 auto; }
        .faq-item { background: white; border-radius: 12px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .faq-q {
            padding: 18px 22px;
            font-size: 15px;
            font-weight: 600;
            color: #222;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            user-select: none;
        }
        .faq-q:hover { background: #f8faf8; }
        .faq-q .chevron { color: #56ad58; transition: .3s; font-size: 14px; }
        .faq-a { max-height: 0; overflow: hidden; transition: max-height .35s ease, padding .3s; }
        .faq-a.open { max-height: 200px; }
        .faq-a p { padding: 0 22px 18px; color: #555; font-size: 14px; line-height: 1.7; margin: 0; }

        /* ── CTA Banner ── */
        .cta-banner {
            background: linear-gradient(135deg, #1a3a2a, #56ad58);
            color: white;
            text-align: center;
            padding: 60px 5%;
        }
        .cta-banner h2 { font-size: 30px; margin-bottom: 12px; }
        .cta-banner p  { color: #cce8cc; font-size: 16px; margin-bottom: 28px; }
        .cta-btn {
            display: inline-block;
            background: white;
            color: #1a6e2a;
            padding: 14px 36px;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            text-decoration: none;
            margin: 6px;
            transition: .2s;
        }
        .cta-btn:hover { background: #e8f5e9; }
        .cta-btn.outline { background: transparent; color: white; border: 2px solid white; }
        .cta-btn.outline:hover { background: rgba(255,255,255,.1); }

        @media (max-width: 768px) {
            .track-inputs { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: 1fr 1fr; }
            .zones-grid { grid-template-columns: 1fr; }
            .order-meta { grid-template-columns: 1fr; }
            .progress-track { overflow-x: auto; }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<!-- ── HERO ─────────────────────────────────────────────────────────────────── -->
<div class="logistics-hero">
    <div style="font-size:56px; margin-bottom:18px;">🚚</div>
    <h1>Logistics & Delivery</h1>
    <p>Fast, reliable delivery from farms across Sri Lanka straight to your doorstep. Track your order in real time.</p>
</div>

<!-- ── ORDER TRACKING ────────────────────────────────────────────────────────── -->
<div class="track-section">
    <div class="track-box">
        <h2><i class="fa-solid fa-magnifying-glass" style="color:#56ad58;"></i> Track Your Order</h2>
        <p class="sub">Enter your Order ID and the email used at checkout to see live delivery status.</p>

        <?php if ($trackError): ?>
        <div class="track-error"><i class="fa-solid fa-circle-xmark"></i> <?= htmlspecialchars($trackError) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="track-inputs">
                <input type="number" name="order_id" placeholder="Order ID (e.g. 12)"
                       value="<?= htmlspecialchars($_POST['order_id'] ?? '') ?>" required>
                <input type="email" name="email" placeholder="Email used at checkout"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <button type="submit" name="track_order" class="track-btn">
                <i class="fa-solid fa-satellite-dish"></i> Track Order
            </button>
        </form>

        <?php if ($trackedOrder):
            $steps = [
                ['pending',    '📋', 'Order Placed',  'Your order has been received'],
                ['confirmed',  '✅', 'Confirmed',      'Farmer confirmed your order'],
                ['processing', '📦', 'Packing',        'Your items are being packed'],
                ['shipped',    '🚚', 'On the Way',     'Your order is en route'],
                ['delivered',  '🏠', 'Delivered',      'Order delivered successfully'],
            ];
            $statusOrder = ['pending','confirmed','processing','shipped','delivered'];
            $currentIdx  = array_search($trackedOrder['status'], $statusOrder);
            $cancelled   = $trackedOrder['status'] === 'cancelled';

            $pctMap = [0=>5, 1=>28, 2=>51, 3=>74, 4=>97];
            $fillPct = $cancelled ? 0 : ($pctMap[$currentIdx] ?? 5);

            $badgeColors = [
                'pending'    => '#fff3cd;color:#856404',
                'confirmed'  => '#cfe2ff;color:#084298',
                'processing' => '#ffe5d0;color:#8a3700',
                'shipped'    => '#d1ecf1;color:#0c5460',
                'delivered'  => '#d1e7dd;color:#0a3622',
                'cancelled'  => '#f8d7da;color:#842029',
            ];
            $bc = $badgeColors[$trackedOrder['status']] ?? '#eee;color:#333';
        ?>
        <div class="track-result">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:20px;">
                <h3 style="margin:0;">Order #<?= $trackedOrder['id'] ?></h3>
                <span class="status-badge" style="background:<?= $bc ?>;">
                    <?= ucfirst($trackedOrder['status']) ?>
                </span>
            </div>

            <!-- Progress Bar -->
            <?php if (!$cancelled): ?>
            <div style="position:relative;">
                <div class="progress-track" id="progressTrack">
                    <?php foreach ($steps as $si => [$skey, $icon, $label, $hint]): ?>
                    <?php
                        $state = 'future';
                        if ($si < $currentIdx) $state = 'done';
                        elseif ($si === $currentIdx) $state = 'active';
                    ?>
                    <div class="pt-step <?= $state ?>">
                        <div class="pt-dot"><?= $state === 'done' ? '✓' : $icon ?></div>
                        <div class="pt-label"><?= $label ?></div>
                        <div class="pt-time"><?= $hint ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <!-- animated fill bar -->
                <div style="position:absolute;top:22px;left:5%;height:4px;background:#56ad58;z-index:1;border-radius:2px;width:<?= $fillPct ?>%;transition:width .8s ease;"></div>
            </div>
            <?php else: ?>
            <div style="background:#ffe0e0;color:#c0392b;padding:14px 18px;border-radius:10px;text-align:center;font-weight:600;margin-bottom:18px;">
                ✗ This order was cancelled.
            </div>
            <?php endif; ?>

            <!-- Order Meta -->
            <div class="order-meta">
                <div class="meta-item">
                    <label>Customer</label>
                    <span><?= htmlspecialchars($trackedOrder['consumer_name']) ?></span>
                </div>
                <div class="meta-item">
                    <label>Order Date</label>
                    <span><?= date('M d, Y g:i A', strtotime($trackedOrder['created_at'])) ?></span>
                </div>
                <div class="meta-item">
                    <label>Items</label>
                    <span><?= $trackedOrder['item_count'] ?> product(s)</span>
                </div>
                <div class="meta-item">
                    <label>Total Amount</label>
                    <span style="color:#56ad58;">Rs. <?= number_format($trackedOrder['total_amount'],2) ?></span>
                </div>
                <div class="meta-item">
                    <label>Payment</label>
                    <span><?= ucwords(str_replace('_',' ',$trackedOrder['payment_method'])) ?></span>
                </div>
                <div class="meta-item">
                    <label>Estimated Delivery</label>
                    <span>
                        <?php if ($trackedOrder['status'] === 'delivered'): ?>
                            ✅ Delivered
                        <?php elseif ($cancelled): ?>
                            —
                        <?php else: ?>
                            <?= date('M d, Y', strtotime('+2 days', strtotime($trackedOrder['created_at']))) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <div style="background:#f8faf8;border-radius:10px;padding:14px 16px;">
                <label style="font-size:11px;color:#aaa;font-weight:700;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px;">Delivery Address</label>
                <p style="color:#444;font-size:14px;margin:0;"><?= nl2br(htmlspecialchars($trackedOrder['delivery_address'])) ?></p>
            </div>

            <?php if (isLoggedIn() && hasRole('consumer')): ?>
            <div style="margin-top:16px;text-align:center;">
                <a href="consumer/order_detail.php?id=<?= $trackedOrder['id'] ?>" style="display:inline-block;background:#56ad58;color:white;padding:11px 28px;border-radius:10px;text-decoration:none;font-size:14px;font-weight:600;">View Full Order Details →</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── DELIVERY STATS ─────────────────────────────────────────────────────────── -->
<div class="section" style="background:white; padding-bottom:50px;">
    <div style="text-align:center; margin-bottom:35px;">
        <h2 class="section-title">Our Delivery Network</h2>
        <p class="section-sub">Trusted by farmers and consumers across Sri Lanka</p>
    </div>
    <div class="stats-row">
        <div class="stat-card">
            <div class="icon">📦</div>
            <div class="num"><?= $totalDelivered ?>+</div>
            <div class="lbl">Orders Delivered</div>
        </div>
        <div class="stat-card">
            <div class="icon">⚡</div>
            <div class="num"><?= $avgDeliveryDays ?></div>
            <div class="lbl">Avg. Delivery Days</div>
        </div>
        <div class="stat-card">
            <div class="icon">🗺️</div>
            <div class="num">24</div>
            <div class="lbl">Districts Covered</div>
        </div>
        <div class="stat-card">
            <div class="icon">⭐</div>
            <div class="num">98%</div>
            <div class="lbl">On-Time Rate</div>
        </div>
    </div>
</div>

<!-- ── HOW DELIVERY WORKS ─────────────────────────────────────────────────────── -->
<div class="section" style="background:#f5f7f5;">
    <div style="text-align:center;">
        <h2 class="section-title">How Delivery Works</h2>
        <p class="section-sub">From farm to your door in 4 simple steps</p>
    </div>
    <div class="timeline">
        <div class="tl-item">
            <div class="tl-dot">1</div>
            <div class="tl-content">
                <h4>🛒 Place Your Order</h4>
                <p>Browse the marketplace, add products to your cart, and complete checkout with your delivery address and preferred payment method.</p>
                <div class="tl-time">Instant confirmation</div>
            </div>
        </div>
        <div class="tl-item">
            <div class="tl-dot">2</div>
            <div class="tl-content">
                <h4>✅ Farmer Confirms & Packs</h4>
                <p>The farmer reviews your order, harvests fresh produce, and prepares your package. You'll receive a status update when your order is confirmed and being packed.</p>
                <div class="tl-time">Within 4–6 hours of ordering</div>
            </div>
        </div>
        <div class="tl-item">
            <div class="tl-dot">3</div>
            <div class="tl-content">
                <h4>🚚 Pickup & Transit</h4>
                <p>Our logistics partner picks up the package from the farm. Your order is transported with care to maintain freshness throughout the journey.</p>
                <div class="tl-time">Same day or next morning pickup</div>
            </div>
        </div>
        <div class="tl-item">
            <div class="tl-dot">4</div>
            <div class="tl-content">
                <h4>🏠 Delivered to Your Door</h4>
                <p>Your fresh produce arrives at your address. Cash-on-delivery customers pay upon receipt. A confirmation is sent once delivery is complete.</p>
                <div class="tl-time">Within 1–3 business days</div>
            </div>
        </div>
    </div>
</div>

<!-- ── DELIVERY ZONES & FEES ─────────────────────────────────────────────────── -->
<div class="section">
    <div style="text-align:center;">
        <h2 class="section-title">Delivery Zones & Fees</h2>
        <p class="section-sub">Free delivery on orders above Rs. 1,000. Below that, a small delivery fee applies based on your zone.</p>
    </div>
    <div class="zones-grid">
        <div class="zone-card zone-express">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:28px;">⚡</span>
                <div>
                    <h4 style="margin:0;">Express Zone</h4>
                    <span style="font-size:11px;background:#e8f5e9;color:#2e7d32;padding:2px 10px;border-radius:20px;font-weight:700;">1–2 Days</span>
                </div>
            </div>
            <div class="districts">Colombo · Gampaha · Kalutara · Kandy · Galle · Matara</div>
            <div class="fee" style="color:#27ae60;">Rs. 150 delivery fee</div>
            <div class="eta">Free on orders over Rs. 1,000</div>
        </div>

        <div class="zone-card zone-standard">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:28px;">🚛</span>
                <div>
                    <h4 style="margin:0;">Standard Zone</h4>
                    <span style="font-size:11px;background:#e3f2fd;color:#1565c0;padding:2px 10px;border-radius:20px;font-weight:700;">2–3 Days</span>
                </div>
            </div>
            <div class="districts">Nuwara Eliya · Matale · Badulla · Ratnapura · Kegalle · Hambantota · Kurunegala</div>
            <div class="fee" style="color:#1565c0;">Rs. 200 delivery fee</div>
            <div class="eta">Free on orders over Rs. 1,000</div>
        </div>

        <div class="zone-card zone-economy">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                <span style="font-size:28px;">🗺️</span>
                <div>
                    <h4 style="margin:0;">Extended Zone</h4>
                    <span style="font-size:11px;background:#fff8e1;color:#f57f17;padding:2px 10px;border-radius:20px;font-weight:700;">3–5 Days</span>
                </div>
            </div>
            <div class="districts">Jaffna · Kilinochchi · Mannar · Vavuniya · Trincomalee · Batticaloa · Ampara · Puttalam · Anuradhapura · Polonnaruwa · Monaragala</div>
            <div class="fee" style="color:#e65100;">Rs. 350 delivery fee</div>
            <div class="eta">Free on orders over Rs. 1,500</div>
        </div>
    </div>

    <!-- Delivery Note -->
    <div style="max-width:760px;margin:28px auto 0;background:#e8f5e9;border-radius:14px;padding:20px 24px;display:flex;align-items:flex-start;gap:14px;">
        <span style="font-size:26px;flex-shrink:0;">💡</span>
        <div>
            <strong style="font-size:15px;color:#1a5c2a;">Pro tip for farmers:</strong>
            <p style="font-size:14px;color:#3a6e3a;margin:5px 0 0;line-height:1.6;">
                Orders from the same district are typically dispatched the same evening. Grouping multiple products from the same region reduces delivery time and cost for consumers.
            </p>
        </div>
    </div>
</div>

<!-- ── PACKAGING STANDARDS ────────────────────────────────────────────────────── -->
<div class="section" style="background:#f5f7f5;">
    <div style="text-align:center;">
        <h2 class="section-title">Freshness Guarantee</h2>
        <p class="section-sub">We partner with farmers who meet our quality packaging standards</p>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px;max-width:1100px;margin:0 auto;">
        <?php
        $standards = [
            ['🌿','Eco Packaging',     'Biodegradable and recyclable packaging materials to keep produce fresh and reduce waste.'],
            ['❄️','Cool Chain',        'Temperature-controlled storage during transit to maintain freshness for vegetables and fruits.'],
            ['🔒','Tamper-Proof',      'Sealed packaging ensures your order arrives exactly as the farmer prepared it.'],
            ['⚖️','Accurate Weight',   'All orders are weighed and verified before dispatch so you always receive what you paid for.'],
        ];
        foreach ($standards as [$icon,$title,$desc]):
        ?>
        <div style="background:white;border-radius:14px;padding:24px;box-shadow:0 3px 12px rgba(0,0,0,0.07);text-align:center;">
            <div style="font-size:38px;margin-bottom:12px;"><?= $icon ?></div>
            <h4 style="margin-bottom:8px;color:#1a2e1a;"><?= $title ?></h4>
            <p style="font-size:13px;color:#666;line-height:1.6;margin:0;"><?= $desc ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── FAQ ────────────────────────────────────────────────────────────────────── -->
<div class="section">
    <div style="text-align:center;">
        <h2 class="section-title">Delivery FAQs</h2>
        <p class="section-sub">Common questions about our delivery service</p>
    </div>
    <div class="faq-list">
        <?php
        $faqs = [
            ['Can I change my delivery address after placing an order?',
             'You can request an address change within 1 hour of placing the order by contacting us directly. Once the order is confirmed by the farmer, address changes may not be possible.'],
            ['What happens if I miss the delivery?',
             'Our delivery partner will attempt delivery twice. If both attempts fail, your order will be returned to the farmer and a refund or re-delivery will be arranged.'],
            ['Do you deliver on weekends and public holidays?',
             'Yes! We operate 7 days a week including public holidays, though deliveries may take 1 additional day during peak festive seasons.'],
            ['How is the freshness of produce maintained during delivery?',
             'All perishable items are packed with insulating materials. Farmers harvest produce on the day of dispatch, and our logistics partners prioritize farm-to-door speed.'],
            ['Can I return produce if it arrives damaged?',
             'Yes. We have a 24-hour return policy for quality issues. Take a photo of the damaged produce and contact our support team. Refunds or replacements are processed within 48 hours.'],
            ['Is cash on delivery available everywhere?',
             'Cash on delivery (COD) is available in all delivery zones. For Extended Zone deliveries, a small COD handling fee of Rs. 50 may apply.'],
            ['Can farmers arrange their own delivery?',
             'Currently all deliveries are handled through DirectFarm LK\'s logistics network to ensure quality and accountability. Farmer-arranged deliveries are not supported at this time.'],
        ];
        foreach ($faqs as $i => [$q, $a]):
        ?>
        <div class="faq-item">
            <div class="faq-q" onclick="toggleFaq(<?= $i ?>)">
                <?= htmlspecialchars($q) ?>
                <i class="fa-solid fa-chevron-down chevron" id="chevron-<?= $i ?>"></i>
            </div>
            <div class="faq-a" id="faq-<?= $i ?>">
                <p><?= htmlspecialchars($a) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── CTA BANNER ─────────────────────────────────────────────────────────────── -->
<div class="cta-banner">
    <h2>Ready to Order Fresh Produce?</h2>
    <p>Browse hundreds of products from verified Sri Lankan farmers, delivered fresh to your door.</p>
    <a href="marketplace.php" class="cta-btn">🛒 Shop Marketplace</a>
    <a href="contactus.php"   class="cta-btn outline">📞 Contact Support</a>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleFaq(i) {
    const body    = document.getElementById('faq-' + i);
    const chevron = document.getElementById('chevron-' + i);
    const isOpen  = body.classList.contains('open');
    // close all
    document.querySelectorAll('.faq-a').forEach(el => el.classList.remove('open'));
    document.querySelectorAll('.chevron').forEach(el => el.style.transform = 'rotate(0deg)');
    // open clicked if it was closed
    if (!isOpen) {
        body.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
    }
}
// Auto-open first FAQ
toggleFaq(0);
</script>
</body>
</html>
