<?php
require_once 'includes/config.php';
$db = getDB();

$search   = clean($_GET['search']   ?? '');
$category = clean($_GET['category'] ?? '');
$district = clean($_GET['district'] ?? '');
$sort     = clean($_GET['sort']     ?? 'newest');

$where  = "WHERE p.is_available = 1";
$params = []; $types = '';

if ($search)   { $where .= " AND (p.name LIKE ? OR p.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $types .= 'ss'; }
if ($category) { $where .= " AND p.category = ?"; $params[] = $category; $types .= 's'; }
if ($district) { $where .= " AND p.district = ?"; $params[] = $district; $types .= 's'; }

$orderBy = match($sort) {
    'price_asc'  => 'p.price ASC',
    'price_desc' => 'p.price DESC',
    'rating'     => 'avg_rating DESC',
    default      => 'p.created_at DESC'
};

$sql = "SELECT p.*, u.name as farmer_name,
        COALESCE(AVG(r.rating),0) as avg_rating,
        COUNT(r.id) as review_count
        FROM products p
        JOIN users u ON p.farmer_id = u.id
        LEFT JOIN reviews r ON r.product_id = p.id
        $where GROUP BY p.id ORDER BY $orderBy";

$stmt = $db->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result();
$total    = $products->num_rows;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    
    <title>Marketplace - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root { --green:#009933; --dark:#0e1726; --mid:#1a3a2a; --light-bg:#f7faf7; }

        /* HERO BANNER */
        .mp-hero {
            background: linear-gradient(135deg, #0e1726 0%, #1a3a2a 60%, #009933 100%);
            padding: 70px 7% 60px;
            position: relative; overflow: hidden;
        }
        .mp-hero::before {
            content:''; position:absolute; inset:0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/svg%3E");
        }
        .mp-hero-inner { position:relative; z-index:1; }
        .mp-tag { display:inline-flex; align-items:center; gap:8px; background:rgba(0,153,51,0.18); border:1px solid rgba(0,153,51,0.4); color:#7ddb99; font-size:11px; font-weight:700; letter-spacing:2px; text-transform:uppercase; padding:5px 14px; border-radius:100px; margin-bottom:18px; font-family:'DM Sans',sans-serif; }
        .mp-tag-dot { width:6px; height:6px; border-radius:50%; background:var(--green); animation:pulse 1.5s infinite; display:inline-block; }
        @keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.6);opacity:0.5} }
        .mp-hero h1 { font-family:'DM Sans',sans-serif; font-size:clamp(32px,5vw,56px); font-weight:700; color:white; line-height:1.1; margin-bottom:10px; }
        .mp-hero h1 span { color:var(--green); }
        .mp-hero p { font-family:'DM Sans',sans-serif; font-size:16px; color:rgba(255,255,255,0.6); margin:0; }
        .mp-hero-count { display:inline-block; background:rgba(255,255,255,0.1); border:1px solid rgba(255,255,255,0.15); color:white; padding:4px 14px; border-radius:50px; font-size:13px; font-weight:600; font-family:'DM Sans',sans-serif; margin-top:14px; }

        /* FILTER BAR */
        .filter-wrap { background:white; border-bottom:1px solid #eee; padding:14px 7%; position:sticky; top:63px; z-index:99; box-shadow:0 4px 20px rgba(0,0,0,0.08); transition:box-shadow 0.3s; }
        .filter-inner { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .filter-search { display:flex; align-items:center; gap:10px; background:#f7faf7; border:1.5px solid #e8f0e8; border-radius:50px; padding:10px 18px; flex:1; min-width:200px; transition:border-color 0.2s, box-shadow 0.2s; }
        .filter-search:focus-within { border-color:var(--green); box-shadow:0 0 0 3px rgba(0,153,51,0.1); }
        .filter-search i { color:#bbb; font-size:14px; }
        .filter-search input { border:none; background:transparent; font-family:'DM Sans',sans-serif; font-size:14px; color:var(--dark); outline:none; width:100%; }
        .filter-select { display:flex; align-items:center; gap:8px; background:#f7faf7; border:1.5px solid #e8f0e8; border-radius:50px; padding:10px 16px; transition:border-color 0.2s; }
        .filter-select:focus-within { border-color:var(--green); }
        .filter-select i { color:#bbb; font-size:13px; flex-shrink:0; }
        .filter-select select { border:none; background:transparent; font-family:'DM Sans',sans-serif; font-size:13px; color:var(--dark); outline:none; cursor:pointer; }
        .filter-btn { background:var(--green); color:white; border:none; border-radius:50px; padding:11px 24px; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:7px; transition:background 0.2s, transform 0.2s; white-space:nowrap; }
        .filter-btn:hover { background:#00b33c; transform:scale(1.03); }
        .filter-clear { background:transparent; color:#e74c3c; border:1.5px solid #fcc; border-radius:50px; padding:10px 16px; font-family:'DM Sans',sans-serif; font-size:13px; font-weight:600; cursor:pointer; text-decoration:none; white-space:nowrap; transition:all 0.2s; }
        .filter-clear:hover { background:#fef0f0; }
        .filter-divider-v { width:1px; height:24px; background:#e8e8e8; flex-shrink:0; }

        /* RESULTS BAR */
        .results-bar { padding:20px 7% 0; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; }
        .results-count { font-family:'DM Sans',sans-serif; font-size:14px; color:#888; }
        .results-count strong { color:var(--dark); font-size:16px; }
        .cat-pills { display:flex; gap:8px; flex-wrap:wrap; }
        .cat-pill { padding:7px 16px; border-radius:50px; border:1.5px solid #e8e8e8; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:600; color:#888; cursor:pointer; transition:all 0.2s; background:white; text-decoration:none; }
        .cat-pill:hover, .cat-pill.active { background:var(--dark); color:white; border-color:var(--dark); }
        .cat-pill.active { background:var(--green); border-color:var(--green); }

        /* PRODUCT GRID */
        .mp-grid-wrap { padding:28px 7% 80px; }
        .mp-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:24px; }

        /* PRODUCT CARD */
        .mp-card {
            border-radius:18px; overflow:hidden; background:white;
            border:1.5px solid #eef2ee; cursor:pointer;
            opacity:0; transform:translateY(28px);
            transition:opacity 0.5s ease, transform 0.5s ease;
        }
        .mp-card.visible { opacity:1; transform:translateY(0); }
        .mp-card:hover { transform:translateY(-8px) !important; box-shadow:0 20px 52px rgba(0,0,0,0.11); }
        .mp-card-img { position:relative; height:200px; overflow:hidden; }
        .mp-card-img img { width:100%; height:100%; object-fit:cover; transition:transform 0.5s ease; }
        .mp-card:hover .mp-card-img img { transform:scale(1.07); }
        .mp-cat-badge { position:absolute; top:12px; left:12px; background:rgba(255,255,255,0.92); backdrop-filter:blur(6px); color:var(--green); font-size:10px; font-weight:700; letter-spacing:1px; text-transform:uppercase; padding:4px 11px; border-radius:50px; font-family:'DM Sans',sans-serif; }
        .mp-stock-badge { position:absolute; top:12px; right:12px; background:#fff3cd; color:#856404; font-size:10px; font-weight:700; padding:4px 10px; border-radius:50px; font-family:'DM Sans',sans-serif; }
        .mp-card-body { padding:18px 18px 20px; }
        .mp-card-name { font-family:'DM Sans',sans-serif; font-size:18px; font-weight:700; color:var(--dark); margin-bottom:6px; }
        .mp-card-meta { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
        .mp-farmer { font-family:'DM Sans',sans-serif; font-size:12px; color:#999; display:flex; align-items:center; gap:5px; }
        .mp-loc { font-family:'DM Sans',sans-serif; font-size:12px; color:#bbb; display:flex; align-items:center; gap:4px; }
        .mp-stars { color:#f39c12; font-size:12px; margin-bottom:12px; font-family:'DM Sans',sans-serif; }
        .mp-stars span { color:#aaa; font-size:11px; }
        .mp-footer { display:flex; align-items:center; justify-content:space-between; margin-top:14px; border-top:1px solid #f5f5f5; padding-top:14px; }
        .mp-price { font-family:'DM Sans',sans-serif; font-size:20px; font-weight:700; color:var(--green); }
        .mp-unit { font-family:'DM Sans',sans-serif; font-size:11px; color:#bbb; font-weight:400; }
        .mp-view-btn { background:var(--dark); color:white; padding:9px 18px; border-radius:50px; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:600; text-decoration:none; transition:background 0.2s, transform 0.2s; display:inline-flex; align-items:center; gap:6px; }
        .mp-view-btn:hover { background:var(--green); transform:scale(1.05); }

        /* EMPTY STATE */
        .empty-state { text-align:center; padding:80px 20px; }
        .empty-icon { font-size:64px; margin-bottom:20px; display:block; opacity:0.4; }
        .empty-state h3 { font-family:'DM Sans',sans-serif; font-size:24px; color:var(--dark); margin-bottom:10px; }
        .empty-state p { font-family:'DM Sans',sans-serif; font-size:15px; color:#999; margin-bottom:24px; }
        .empty-state a { background:var(--green); color:white; padding:12px 28px; border-radius:50px; text-decoration:none; font-family:'DM Sans',sans-serif; font-weight:600; }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<!-- HERO -->
<div class="mp-hero">
    <div class="mp-hero-inner">
        <div class="mp-tag"><span class="mp-tag-dot"></span> Fresh from the farm</div>
        <h1>Sri Lanka's <span>Freshest</span><br>Farm Marketplace</h1>
        <p>Sourced directly from verified local farmers across the island.</p>
        <div class="mp-hero-count"><?= $total ?> products available</div>
    </div>
</div>

<!-- FILTER BAR -->
<div class="filter-wrap">
    <form method="GET" action="marketplace.php">
    <div class="filter-inner">
        <div class="filter-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" placeholder="Search vegetables, fruits, grains..." value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="filter-divider-v"></div>
        <div class="filter-select">
            <i class="fa-solid fa-tag"></i>
            <select name="category" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <option value="vegetables" <?= $category==='vegetables'?'selected':'' ?>>🥕 Vegetables</option>
                <option value="fruits"     <?= $category==='fruits'    ?'selected':'' ?>>🍎 Fruits</option>
                <option value="grains"     <?= $category==='grains'    ?'selected':'' ?>>🌾 Grains</option>
                <option value="spices"     <?= $category==='spices'    ?'selected':'' ?>>🌶 Spices</option>
            </select>
        </div>
        <div class="filter-select">
            <i class="fa-solid fa-location-dot"></i>
            <select name="district" onchange="this.form.submit()">
                <option value="">All Districts</option>
                <?php foreach (['Colombo','Gampaha','Kalutara','Kandy','Matale','Nuwara Eliya','Galle','Matara','Hambantota','Jaffna','Trincomalee','Batticaloa','Kurunegala','Puttalam','Anuradhapura','Polonnaruwa','Badulla','Monaragala','Ratnapura','Kegalle'] as $d): ?>
                <option value="<?= $d ?>" <?= $district===$d?'selected':'' ?>><?= $d ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-select">
            <i class="fa-solid fa-arrow-up-wide-short"></i>
            <select name="sort" onchange="this.form.submit()">
                <option value="newest"     <?= $sort==='newest'    ?'selected':'' ?>>Newest</option>
                <option value="price_asc"  <?= $sort==='price_asc' ?'selected':'' ?>>Price: Low to High</option>
                <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                <option value="rating"     <?= $sort==='rating'    ?'selected':'' ?>>Top Rated</option>
            </select>
        </div>
        <button type="submit" class="filter-btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if ($search || $category || $district): ?>
            <a href="marketplace.php" class="filter-clear">✕ Clear</a>
        <?php endif; ?>
    </div>
    </form>
</div>

<!-- RESULTS + PILLS -->
<div class="results-bar">
    <div class="results-count">
        Showing <strong><?= $total ?></strong> product<?= $total!==1?'s':'' ?>
        <?= $category ? ' in <strong>'.ucfirst($category).'</strong>' : '' ?>
        <?= $district ? ' from <strong>'.$district.'</strong>' : '' ?>
    </div>
    <div class="cat-pills">
        <a href="marketplace.php<?= $district?'?district='.$district:'' ?>" class="cat-pill <?= !$category?'active':'' ?>">All</a>
        <a href="marketplace.php?category=vegetables<?= $district?'&district='.$district:'' ?>" class="cat-pill <?= $category==='vegetables'?'active':'' ?>">🥕 Vegetables</a>
        <a href="marketplace.php?category=fruits<?= $district?'&district='.$district:'' ?>"     class="cat-pill <?= $category==='fruits'    ?'active':'' ?>">🍎 Fruits</a>
        <a href="marketplace.php?category=grains<?= $district?'&district='.$district:'' ?>"     class="cat-pill <?= $category==='grains'    ?'active':'' ?>">🌾 Grains</a>
        <a href="marketplace.php?category=spices<?= $district?'&district='.$district:'' ?>"     class="cat-pill <?= $category==='spices'    ?'active':'' ?>">🌶 Spices</a>
    </div>
</div>

<!-- GRID -->
<div class="mp-grid-wrap">
    <?php if ($total === 0): ?>
    <div class="empty-state">
        <span class="empty-icon">🌱</span>
        <h3>No products found</h3>
        <p>Try adjusting your search or filters to find what you're looking for.</p>
        <a href="marketplace.php">View all products</a>
    </div>
    <?php else: ?>
    <div class="mp-grid">
        <?php while ($p = $products->fetch_assoc()): ?>
        <div class="mp-card" onclick="window.location='product.php?id=<?= $p['id'] ?>'">
            <div class="mp-card-img">
                <img src="<?= htmlspecialchars($p['image'] ?? 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                <div class="mp-cat-badge"><?= ucfirst($p['category']) ?></div>
                <?php if ($p['stock'] <= 5 && $p['stock'] > 0): ?>
                <div class="mp-stock-badge">⚠ <?= $p['stock'] ?> left</div>
                <?php endif; ?>
            </div>
            <div class="mp-card-body">
                <div class="mp-card-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="mp-card-meta">
                    <span class="mp-farmer"><i class="fa-solid fa-user" style="color:var(--green);font-size:10px;"></i><?= htmlspecialchars($p['farmer_name']) ?></span>
                    <span class="mp-loc"><i class="fa-solid fa-location-dot" style="font-size:10px;"></i><?= htmlspecialchars($p['district']) ?></span>
                </div>
                <?php if ($p['avg_rating'] > 0): ?>
                <div class="mp-stars">
                    <?= str_repeat('★', round($p['avg_rating'])) ?><?= str_repeat('☆', 5-round($p['avg_rating'])) ?>
                    <span>(<?= $p['review_count'] ?>)</span>
                </div>
                <?php else: ?>
                <div style="height:20px;"></div>
                <?php endif; ?>
                <div class="mp-footer">
                    <div class="mp-price">Rs. <?= number_format($p['price'],2) ?> <span class="mp-unit">/ <?= htmlspecialchars($p['unit']) ?></span></div>
                    <a href="product.php?id=<?= $p['id'] ?>" class="mp-view-btn" onclick="event.stopPropagation()">View <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Scroll reveal for cards
const obs = new IntersectionObserver((entries) => {
    entries.forEach((e, i) => {
        if (e.isIntersecting) {
            setTimeout(() => e.target.classList.add('visible'), i * 80);
            obs.unobserve(e.target);
        }
    });
}, { threshold: 0.08 });
document.querySelectorAll('.mp-card').forEach(el => obs.observe(el));
</script>
</body>
</html>
