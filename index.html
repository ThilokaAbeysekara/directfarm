<?php
require_once 'includes/config.php';
$db = getDB();
$farmerCount  = $db->query("SELECT COUNT(*) as c FROM users WHERE role='farmer'")->fetch_assoc()['c'];
$productCount = $db->query("SELECT COUNT(*) as c FROM products WHERE is_available=1")->fetch_assoc()['c'];
$orderCount   = $db->query("SELECT COUNT(*) as c FROM orders")->fetch_assoc()['c'];
$products     = $db->query("SELECT p.*, u.name as farmer_name FROM products p JOIN users u ON p.farmer_id=u.id WHERE p.is_available=1 ORDER BY p.created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <title>DirectFarm LK - Fresh From Farm to You</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root { --green:#009933; --dark:#0e1726; --mid:#1a3a2a; --gold:#f5a623; --light-bg:#f7faf7; }

        /* LOADER */
        #pageLoader {
            position:fixed; inset:0; z-index:99999; background:var(--dark);
            display:flex; flex-direction:column; align-items:center; justify-content:center;
            transition:opacity 0.7s ease, visibility 0.7s ease;
        }
        #pageLoader.hide { opacity:0; visibility:hidden; pointer-events:none; }
        .loader-ring-wrap { position:relative; width:72px; height:72px; margin-bottom:24px; }
        .loader-ring {
            width:72px; height:72px; border-radius:50%;
            border:3px solid rgba(0,153,51,0.15); border-top-color:var(--green);
            animation:spin 0.9s linear infinite; position:absolute; inset:0;
        }
        .loader-ring-inner {
            width:50px; height:50px; border-radius:50%;
            border:3px solid rgba(245,166,35,0.15); border-bottom-color:var(--gold);
            animation:spin 1.4s linear infinite reverse;
            position:absolute; top:11px; left:11px;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        .loader-text { color:white; font-family:'DM Sans',sans-serif; font-size:22px; letter-spacing:2px; margin-bottom:6px; }
        .loader-sub  { color:rgba(255,255,255,0.4); font-family:'DM Sans',sans-serif; font-size:12px; letter-spacing:3px; text-transform:uppercase; }

        /* HERO */
        .hero { position:relative; min-height:92vh; display:flex; align-items:center; overflow:hidden; }
        .hero::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(105deg,rgba(14,23,38,0.90) 0%,rgba(14,23,38,0.55) 55%,rgba(14,23,38,0.15) 100%);
            z-index:1;
        }
        .hero-content { position:relative; z-index:3; padding:0 8%; max-width:680px; }
        .hero-badge {
            display:inline-flex; align-items:center; gap:8px;
            background:rgba(0,153,51,0.15); border:1px solid rgba(0,153,51,0.35);
            color:#7ddb99; font-size:12px; font-weight:600; letter-spacing:2px;
            text-transform:uppercase; padding:6px 16px; border-radius:100px; margin-bottom:28px;
            opacity:0; animation:fadeUp 0.4s 0.1s ease forwards;
        }
        .hero-badge-dot { width:6px; height:6px; border-radius:50%; background:var(--green); animation:pulse 1.5s infinite; display:inline-block; }
        @keyframes pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.6);opacity:0.5} }
        .hero-content h1 {
            font-family:'DM Sans',sans-serif; font-size:clamp(28px,3.5vw,46px); font-weight:700; letter-spacing:-0.5px;
            font-weight:700; line-height:1.08; color:white; margin-bottom:8px;
            opacity:0; animation:fadeUp 0.4s 0.2s ease forwards;
        }
        .hero-content h1 .accent { color:var(--green); }
        .typed-wrap { display:block; min-height:1.2em; font-size:clamp(24px,3vw,40px); font-weight:600; border-right:3px solid var(--green); animation:blink 0.75s step-end infinite; }
        @keyframes blink { 0%,100%{border-color:var(--green)} 50%{border-color:transparent} }
        .hero-content p {
            font-family:'DM Sans',sans-serif; font-size:17px; color:rgba(255,255,255,0.65);
            line-height:1.75; margin-bottom:38px; max-width:460px;
            opacity:0; animation:fadeUp 0.4s 0.3s ease forwards;
        }
        .hero-buttons { display:flex; gap:14px; flex-wrap:wrap; opacity:0; animation:fadeUp 0.4s 0.4s ease forwards; }
        .btn-primary {
            background:var(--green); color:white; padding:15px 32px; border-radius:50px;
            border:none; font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600;
            cursor:pointer; display:inline-flex; align-items:center; gap:9px; text-decoration:none;
            box-shadow:0 8px 28px rgba(0,153,51,0.4);
            transition:transform 0.2s, box-shadow 0.2s, background 0.2s;
        }
        .btn-primary:hover { transform:translateY(-3px); box-shadow:0 14px 36px rgba(0,153,51,0.5); background:#00b33c; }
        .btn-secondary {
            background:rgba(255,255,255,0.1); color:white; padding:15px 32px; border-radius:50px;
            border:1.5px solid rgba(255,255,255,0.3); font-family:'DM Sans',sans-serif; font-size:15px;
            font-weight:600; cursor:pointer; display:inline-flex; align-items:center; gap:9px;
            text-decoration:none; backdrop-filter:blur(6px);
            transition:transform 0.2s, background 0.2s, border-color 0.2s;
        }
        .btn-secondary:hover { transform:translateY(-3px); background:rgba(255,255,255,0.18); border-color:white; }
        .scroll-hint {
            position:absolute; bottom:32px; left:50%; transform:translateX(-50%); z-index:3;
            display:flex; flex-direction:column; align-items:center; gap:8px;
            color:rgba(255,255,255,0.35); font-size:10px; letter-spacing:2px; text-transform:uppercase;
            font-family:'DM Sans',sans-serif; opacity:0; animation:fadeUp 0.4s 0.6s ease forwards;
        }
        .scroll-dot { width:22px; height:36px; border-radius:11px; border:1.5px solid rgba(255,255,255,0.2); display:flex; justify-content:center; padding-top:6px; }
        .scroll-dot::before { content:''; width:4px; height:8px; border-radius:2px; background:rgba(255,255,255,0.45); animation:scrollDot 1.8s ease infinite; }
        @keyframes scrollDot { 0%,100%{transform:translateY(0);opacity:1} 80%{transform:translateY(10px);opacity:0} }
        @keyframes fadeUp { from{opacity:0;transform:translateY(30px)} to{opacity:1;transform:translateY(0)} }

        /* FLOATING STATS */
        .hero-stats {
            position:absolute; bottom:60px; right:7%; z-index:3;
            display:flex; flex-direction:column; gap:12px;
            opacity:0; animation:fadeUp 0.4s 0.5s ease forwards;
        }
        .hero-stat-pill {
            background:rgba(255,255,255,0.07); backdrop-filter:blur(14px);
            border:1px solid rgba(255,255,255,0.1); border-radius:60px;
            padding:11px 20px; display:flex; align-items:center; gap:12px;
            transition:background 0.3s, transform 0.3s;
        }
        .hero-stat-pill:hover { background:rgba(255,255,255,0.14); transform:translateX(-4px); }
        .hero-stat-icon { font-size:20px; }
        .hero-stat-num { color:white; font-family:'DM Sans',sans-serif; font-size:21px; font-weight:700; line-height:1; }
        .hero-stat-label { color:rgba(255,255,255,0.5); font-family:'DM Sans',sans-serif; font-size:10px; letter-spacing:1px; text-transform:uppercase; }

        /* SECTION HEADER */
        .section-header { text-align:center; margin-bottom:50px; }
        .section-tag { display:inline-block; color:var(--green); font-family:'DM Sans',sans-serif; font-size:12px; font-weight:700; letter-spacing:3px; text-transform:uppercase; margin-bottom:12px; }
        .section-header h2 { font-family:'DM Sans',sans-serif; font-size:clamp(28px,4vw,44px); color:var(--dark); font-weight:700; }
        .section-header p { color:#777; font-family:'DM Sans',sans-serif; font-size:16px; margin-top:10px; }

        /* CATEGORIES */
        .categories-section { padding:90px 7%; background:var(--light-bg); }
        .cat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; }
        .cat-card {
            position:relative; border-radius:20px; overflow:hidden; aspect-ratio:3/4;
            cursor:pointer; opacity:0; transform:translateY(40px);
            transition:opacity 0.6s ease, transform 0.6s ease;
        }
        .cat-card.visible { opacity:1; transform:translateY(0); }
        .cat-card img { width:100%; height:100%; object-fit:cover; transition:transform 0.6s cubic-bezier(0.25,0.46,0.45,0.94); }
        .cat-card:hover img { transform:scale(1.09); }
        .cat-card::before {
            content:''; position:absolute; inset:0; z-index:1;
            background:linear-gradient(to top, rgba(14,23,38,0.88) 0%, rgba(14,23,38,0.08) 60%);
        }
        .cat-info { position:absolute; bottom:0; left:0; right:0; z-index:2; padding:24px 20px; transform:translateY(6px); transition:transform 0.3s; }
        .cat-card:hover .cat-info { transform:translateY(0); }
        .cat-info h3 { color:white; font-family:'DM Sans',sans-serif; font-size:21px; font-weight:700; margin-bottom:3px; }
        .cat-info p  { color:rgba(255,255,255,0.55); font-family:'DM Sans',sans-serif; font-size:12px; margin:0; }
        .cat-arrow {
            position:absolute; top:18px; right:18px; z-index:2;
            width:34px; height:34px; border-radius:50%;
            background:rgba(255,255,255,0.12); border:1px solid rgba(255,255,255,0.25);
            display:flex; align-items:center; justify-content:center; color:white; font-size:13px;
            opacity:0; transform:scale(0.7) rotate(-45deg); transition:opacity 0.3s, transform 0.3s;
        }
        .cat-card:hover .cat-arrow { opacity:1; transform:scale(1) rotate(0deg); }

        /* FEATURED PRODUCTS */
        .products-section { padding:90px 7%; background:white; }
        .products-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:26px; }
        .prod-card {
            border-radius:20px; overflow:hidden; border:1.5px solid #eef2ee; background:white;
            cursor:pointer; opacity:0; transform:translateY(40px);
            transition:opacity 0.6s ease, transform 0.6s ease;
        }
        .prod-card.visible { opacity:1; transform:translateY(0); }
        .prod-card:hover { transform:translateY(-10px) !important; box-shadow:0 24px 60px rgba(0,0,0,0.11); }
        .prod-img-wrap { position:relative; overflow:hidden; height:215px; }
        .prod-img-wrap img { width:100%; height:100%; object-fit:cover; transition:transform 0.5s ease; }
        .prod-card:hover .prod-img-wrap img { transform:scale(1.06); }
        .prod-cat-badge {
            position:absolute; top:13px; left:13px;
            background:rgba(255,255,255,0.92); backdrop-filter:blur(6px);
            color:var(--green); font-size:11px; font-weight:700; letter-spacing:1px;
            text-transform:uppercase; padding:5px 12px; border-radius:50px; font-family:'DM Sans',sans-serif;
        }
        .prod-body { padding:22px 22px 24px; }
        .prod-name { font-family:'DM Sans',sans-serif; font-size:19px; font-weight:700; color:var(--dark); margin-bottom:8px; }
        .prod-meta { display:flex; align-items:center; gap:14px; margin-bottom:16px; }
        .prod-farmer { font-family:'DM Sans',sans-serif; font-size:12px; color:#888; }
        .prod-loc { font-family:'DM Sans',sans-serif; font-size:12px; color:#bbb; display:flex; align-items:center; gap:4px; }
        .prod-footer { display:flex; align-items:center; justify-content:space-between; }
        .prod-price { font-family:'DM Sans',sans-serif; font-size:21px; font-weight:700; color:var(--green); }
        .prod-price span { font-family:'DM Sans',sans-serif; font-size:12px; color:#aaa; font-weight:400; }
        .prod-btn {
            background:var(--dark); color:white; padding:10px 20px; border-radius:50px;
            font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; text-decoration:none;
            transition:background 0.2s, transform 0.2s; display:inline-flex; align-items:center; gap:7px;
        }
        .prod-btn:hover { background:var(--green); transform:scale(1.04); }
        .view-all-wrap { text-align:center; margin-top:50px; }
        .btn-outline {
            display:inline-flex; align-items:center; gap:10px;
            border:2px solid var(--dark); color:var(--dark); padding:14px 36px; border-radius:50px;
            font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; text-decoration:none;
            transition:all 0.25s;
        }
        .btn-outline:hover { background:var(--dark); color:white; transform:translateY(-2px); }

        /* FEATURES */
        .features-section { padding:80px 7%; background:var(--dark); position:relative; overflow:hidden; }
        .features-section::before {
            content:''; position:absolute; top:-80px; right:-80px; width:400px; height:400px;
            border-radius:50%; background:radial-gradient(circle,rgba(0,153,51,0.12) 0%,transparent 70%);
        }
        .features-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; position:relative; z-index:1; }
        .feat-card {
            padding:34px 28px; border-radius:20px; border:1px solid rgba(255,255,255,0.06);
            background:rgba(255,255,255,0.02); opacity:0; transform:translateY(30px);
            transition:opacity 0.6s, transform 0.6s, border-color 0.3s, background 0.3s;
        }
        .feat-card.visible { opacity:1; transform:translateY(0); }
        .feat-card:hover { border-color:rgba(0,153,51,0.35); background:rgba(0,153,51,0.05); transform:translateY(-6px) !important; }
        .feat-icon { width:50px; height:50px; border-radius:14px; background:rgba(0,153,51,0.12); border:1px solid rgba(0,153,51,0.2); display:flex; align-items:center; justify-content:center; font-size:20px; color:var(--green); margin-bottom:18px; }
        .feat-card h3 { font-family:'DM Sans',sans-serif; font-size:19px; color:white; margin-bottom:10px; }
        .feat-card p  { font-family:'DM Sans',sans-serif; font-size:14px; color:rgba(255,255,255,0.45); line-height:1.75; margin:0; }

        /* STATS */
        .stats-section { padding:80px 7%; background:linear-gradient(135deg,var(--green) 0%,#006622 100%); position:relative; overflow:hidden; }
        .stats-section::before { content:''; position:absolute; inset:0; background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E"); }
        .stats-grid { display:grid; grid-template-columns:1fr auto 1fr auto 1fr; gap:20px; align-items:center; position:relative; z-index:1; max-width:800px; margin:0 auto; }
        .stat-item { text-align:center; }
        .stat-num { font-family:'DM Sans',sans-serif; font-size:clamp(48px,6vw,70px); font-weight:700; color:white; line-height:1; display:block; margin-bottom:8px; }
        .stat-lbl { font-family:'DM Sans',sans-serif; font-size:13px; color:rgba(255,255,255,0.65); letter-spacing:1px; text-transform:uppercase; }
        .stat-divider { width:1px; height:60px; background:rgba(255,255,255,0.2); }

        /* HOW IT WORKS */
        .how-section { padding:90px 7%; background:var(--light-bg); }
        .how-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:22px; margin-top:50px; }
        .how-step {
            text-align:center; padding:34px 18px; background:white; border-radius:20px;
            border:1.5px solid #eef2ee; position:relative; opacity:0; transform:translateY(30px);
            transition:opacity 0.6s, transform 0.6s, box-shadow 0.3s;
        }
        .how-step.visible { opacity:1; transform:translateY(0); }
        .how-step:hover { box-shadow:0 16px 48px rgba(0,0,0,0.08); }
        .how-step::after { content:'→'; position:absolute; right:-16px; top:50%; transform:translateY(-50%); font-size:18px; color:#ddd; z-index:1; }
        .how-step:last-child::after { display:none; }
        .step-num { width:42px; height:42px; border-radius:50%; background:var(--green); color:white; font-family:'DM Sans',sans-serif; font-size:17px; font-weight:700; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; }
        .step-icon { font-size:30px; margin-bottom:12px; display:block; }
        .how-step h4 { font-family:'DM Sans',sans-serif; font-size:16px; color:var(--dark); margin-bottom:8px; }
        .how-step p  { font-family:'DM Sans',sans-serif; font-size:13px; color:#888; line-height:1.6; margin:0; }

        /* CTA */
        .cta-section { padding:80px 7%; background:white; }
        .cta-box { background:linear-gradient(135deg,var(--dark) 0%,var(--mid) 60%,rgba(0,153,51,0.35) 100%); border-radius:28px; padding:70px 50px; position:relative; overflow:hidden; text-align:center; }
        .cta-box::before { content:'🌿'; position:absolute; font-size:200px; right:-10px; top:-40px; opacity:0.05; line-height:1; }
        .cta-box h2 { font-family:'DM Sans',sans-serif; font-size:clamp(26px,4vw,44px); color:white; font-weight:700; margin-bottom:12px; }
        .cta-box p  { font-family:'DM Sans',sans-serif; font-size:16px; color:rgba(255,255,255,0.55); margin-bottom:36px; }
        .cta-btns   { display:flex; gap:16px; justify-content:center; flex-wrap:wrap; }

        /* TOAST */
        #toast { position:fixed; bottom:32px; right:32px; z-index:9999; background:var(--dark); color:white; padding:14px 22px; border-radius:12px; font-family:'DM Sans',sans-serif; font-size:14px; box-shadow:0 8px 32px rgba(0,0,0,0.3); transform:translateY(80px); opacity:0; transition:transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.4s; display:flex; align-items:center; gap:10px; }
        #toast.show { transform:translateY(0); opacity:1; }
        .toast-dot { width:8px; height:8px; border-radius:50%; background:var(--green); flex-shrink:0; }
    </style>
</head>
<body>

<div id="pageLoader">
    <div class="loader-ring-wrap">
        <div class="loader-ring"></div>
        <div class="loader-ring-inner"></div>
    </div>
    <div class="loader-text">DirectFarm LK</div>
    <div class="loader-sub">Farm &middot; Fresh &middot; Direct</div>
</div>

<div id="toast"><span class="toast-dot"></span><span id="toastMsg"></span></div>

<?php include 'includes/navbar.php'; ?>

<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge"><span class="hero-badge-dot"></span> Sri Lanka's #1 Farm Marketplace</div>
        <h1>
            <span class="accent">Buy Fresh.</span>
            <span class="typed-wrap" id="typedText"></span>
        </h1>
        <p>Connect directly with Sri Lankan farmers. No middlemen, no markup — just honest food delivered to your doorstep.</p>
        <div class="hero-buttons">
            <?php if (isLoggedIn() && hasRole('consumer')): ?>
                <a href="consumer/dashboard.php" class="btn-primary"><i class="fa-solid fa-user"></i> My Dashboard</a>
                <a href="marketplace.php" class="btn-secondary"><i class="fa-solid fa-store"></i> Shop Now</a>
            <?php elseif (isLoggedIn() && hasRole('farmer')): ?>
                <a href="farmer/dashboard.php" class="btn-primary"><i class="fa-solid fa-tractor"></i> Farmer Dashboard</a>
            <?php else: ?>
                <button class="btn-primary" onclick="openAuth('register','consumer')"><i class="fa-solid fa-user"></i> I'm a Consumer</button>
                <button class="btn-secondary" onclick="openAuth('register','farmer')"><i class="fa-solid fa-tractor"></i> I'm a Farmer</button>
            <?php endif; ?>
        </div>
    </div>
    <div class="hero-stats">
        <div class="hero-stat-pill">
            <span class="hero-stat-icon">👨‍🌾</span>
            <div><div class="hero-stat-num"><?= $farmerCount ?>+</div><div class="hero-stat-label">Farmers</div></div>
        </div>
        <div class="hero-stat-pill">
            <span class="hero-stat-icon">🥦</span>
            <div><div class="hero-stat-num"><?= $productCount ?>+</div><div class="hero-stat-label">Products</div></div>
        </div>
        <div class="hero-stat-pill">
            <span class="hero-stat-icon">📦</span>
            <div><div class="hero-stat-num"><?= $orderCount ?>+</div><div class="hero-stat-label">Orders</div></div>
        </div>
    </div>
    <div class="scroll-hint"><div class="scroll-dot"></div><span>Scroll</span></div>
</section>

<!-- CATEGORIES -->
<section class="categories-section">
    <div class="section-header">
        <div class="section-tag">🌿 What we offer</div>
        <h2>Browse by Category</h2>
        <p>Handpicked fresh produce, direct from Sri Lankan farms</p>
    </div>
    <div class="cat-grid">
        <a href="marketplace.php?category=vegetables" style="text-decoration:none;display:block;">
            <div class="cat-card"><img src="front-view-vegetable.jpg" alt="Vegetables">
            <div class="cat-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="cat-info"><h3>Vegetables</h3><p>Farm-fresh daily picks</p></div></div>
        </a>
        <a href="marketplace.php?category=fruits" style="text-decoration:none;display:block;">
            <div class="cat-card"><img src="colorful-fruit.jpg" alt="Fruits">
            <div class="cat-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="cat-info"><h3>Fruits</h3><p>Sweet &amp; seasonal</p></div></div>
        </a>
        <a href="marketplace.php?category=grains" style="text-decoration:none;display:block;">
            <div class="cat-card"><img src="grains.jpg" alt="Grains">
            <div class="cat-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="cat-info"><h3>Grains</h3><p>Wholesome staples</p></div></div>
        </a>
        <a href="marketplace.php?category=spices" style="text-decoration:none;display:block;">
            <div class="cat-card"><img src="various-spices-stucco-background.jpg" alt="Spices">
            <div class="cat-arrow"><i class="fa-solid fa-arrow-right"></i></div>
            <div class="cat-info"><h3>Spices</h3><p>Authentic flavours</p></div></div>
        </a>
    </div>
</section>

<!-- FEATURED PRODUCTS -->
<section class="products-section">
    <div class="section-header">
        <div class="section-tag">⭐ Just arrived</div>
        <h2>Featured Products</h2>
        <p>Freshest listings from our farmers this week</p>
    </div>
    <div class="products-grid">
        <?php foreach ($products as $p): ?>
        <div class="prod-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
            <div class="prod-img-wrap">
                <img src="<?= htmlspecialchars($p['image'] ?? 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <div class="prod-cat-badge"><?= ucfirst(htmlspecialchars($p['category'])) ?></div>
            </div>
            <div class="prod-body">
                <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="prod-meta">
                    <span class="prod-farmer"><i class="fa-solid fa-user" style="color:var(--green);margin-right:4px;font-size:10px;"></i><?= htmlspecialchars($p['farmer_name']) ?></span>
                    <span class="prod-loc"><i class="fa-solid fa-location-dot" style="font-size:10px;"></i><?= htmlspecialchars($p['district']) ?></span>
                </div>
                <div class="prod-footer">
                    <div class="prod-price">Rs. <?= number_format($p['price'],2) ?> <span>/ <?= htmlspecialchars($p['unit']) ?></span></div>
                    <a href="product.php?id=<?= $p['id'] ?>" class="prod-btn" onclick="event.stopPropagation()">View <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="view-all-wrap">
        <a href="marketplace.php" class="btn-outline">Browse All Products <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</section>

<!-- FEATURES -->
<section class="features-section">
    <div class="section-header" style="margin-bottom:46px;">
        <div class="section-tag" style="color:#7ddb99;">✦ Why DirectFarm</div>
        <h2 style="color:white;">Built for Sri Lanka's Farmers</h2>
        <p style="color:rgba(255,255,255,0.45);">Everything you need to buy and sell fresh produce online</p>
    </div>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon"><i class="fa-solid fa-leaf"></i></div>
            <h3>100% Fresh</h3>
            <p>Products listed directly by farmers. No cold storage, no transit delays — just farm-fresh quality.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon"><i class="fa-solid fa-handshake"></i></div>
            <h3>Support Local</h3>
            <p>Every purchase directly supports a Sri Lankan farming family. No commissions, no middlemen.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            <h3>Fair Prices</h3>
            <p>Real market prices updated daily. Farmers earn more, consumers pay less.</p>
        </div>
    </div>
</section>

<!-- STATS -->
<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <span class="stat-num" data-target="<?= $farmerCount ?>" id="statFarmers">0</span>
            <span class="stat-lbl">Verified Farmers</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-num" data-target="<?= $productCount ?>" id="statProducts">0</span>
            <span class="stat-lbl">Fresh Products</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
            <span class="stat-num" data-target="<?= $orderCount ?>" id="statOrders">0</span>
            <span class="stat-lbl">Orders Fulfilled</span>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section">
    <div class="section-header">
        <div class="section-tag">🛒 Simple steps</div>
        <h2>How It Works</h2>
        <p>Get farm-fresh produce in four easy steps</p>
    </div>
    <div class="how-grid">
        <div class="how-step"><div class="step-num">1</div><span class="step-icon">📱</span><h4>Sign Up</h4><p>Create your free account as a consumer or farmer in seconds</p></div>
        <div class="how-step"><div class="step-num">2</div><span class="step-icon">🔍</span><h4>Browse</h4><p>Explore fresh products by category or district</p></div>
        <div class="how-step"><div class="step-num">3</div><span class="step-icon">🛒</span><h4>Order</h4><p>Add to cart and checkout with card or bank transfer</p></div>
        <div class="how-step"><div class="step-num">4</div><span class="step-icon">🚚</span><h4>Delivered</h4><p>Fresh produce delivered based on your zone</p></div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="cta-box">
        <h2>Ready to eat fresh?</h2>
        <p>Join thousands of Sri Lankans buying direct from farmers today.</p>
        <div class="cta-btns">
            <?php if (!isLoggedIn()): ?>
            <button class="btn-primary" onclick="openAuth('register','consumer')"><i class="fa-solid fa-user"></i> Join as Consumer</button>
            <button class="btn-secondary" onclick="openAuth('register','farmer')"><i class="fa-solid fa-tractor"></i> Join as Farmer</button>
            <?php else: ?>
            <a href="marketplace.php" class="btn-primary"><i class="fa-solid fa-store"></i> Go to Marketplace</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
// Loader
window.addEventListener('load', () => setTimeout(() => document.getElementById('pageLoader').classList.add('hide'), 700));

// Typing animation
const phrases = ['Support Local Farmers.', 'Eat What Nature Grows.', 'Trust Your Farmer.', 'Skip the Middleman.'];
let pi=0, ci=0, deleting=false;
const typedEl = document.getElementById('typedText');
function type() {
    const phrase = phrases[pi];
    if (!deleting) {
        typedEl.textContent = phrase.slice(0, ++ci);
        if (ci === phrase.length) { deleting=true; setTimeout(type, 2200); return; }
    } else {
        typedEl.textContent = phrase.slice(0, --ci);
        if (ci === 0) { deleting=false; pi=(pi+1)%phrases.length; }
    }
    setTimeout(type, deleting ? 42 : 88);
}
setTimeout(type, 500);

// Scroll reveal
const obs = new IntersectionObserver((entries) => {
    entries.forEach((e,i) => {
        if (e.isIntersecting) {
            setTimeout(() => e.target.classList.add('visible'), (e.target.dataset.delay||0)*1);
            obs.unobserve(e.target);
        }
    });
}, { threshold: 0.1 });
document.querySelectorAll('.cat-card, .prod-card, .feat-card, .how-step').forEach((el,i) => {
    el.dataset.delay = i * 110;
    obs.observe(el);
});

// Counter animation
const cObs = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            const target = +e.target.dataset.target;
            let cur = 0;
            const step = Math.max(1, Math.ceil(target/55));
            const t = setInterval(() => {
                cur = Math.min(cur+step, target);
                e.target.textContent = cur + '+';
                if (cur >= target) clearInterval(t);
            }, 26);
            cObs.unobserve(e.target);
        }
    });
}, { threshold: 0.5 });
document.querySelectorAll('.stat-num[data-target]').forEach(el => cObs.observe(el));

// Toast
function showToast(msg) {
    document.getElementById('toastMsg').textContent = msg;
    const t = document.getElementById('toast');
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3200);
}

window.addEventListener('click', e => { if (e.target.id === 'authModal') e.target.style.display='none'; });
</script>
</body>
</html>
