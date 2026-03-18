<?php
require_once 'includes/config.php';
$db = getDB();

// ── Live stats from DB ────────────────────────────────────────────────────────
$productCount  = $db->query("SELECT COUNT(*) as c FROM products WHERE is_available=1")->fetch_assoc()['c'];
$districtCount = $db->query("SELECT COUNT(DISTINCT district) as c FROM products WHERE is_available=1")->fetch_assoc()['c'];
$categoryCount = $db->query("SELECT COUNT(DISTINCT category) as c FROM products WHERE is_available=1")->fetch_assoc()['c'];

// ── Live products for price cards ─────────────────────────────────────────────
$liveProducts = $db->query("
    SELECT p.name, p.price, p.unit, p.category, p.district, p.stock,
           COALESCE(AVG(r.rating),0) as avg_rating
    FROM products p
    LEFT JOIN reviews r ON r.product_id = p.id
    WHERE p.is_available=1
    GROUP BY p.id
    ORDER BY p.category, p.name
")->fetch_all(MYSQLI_ASSOC);

// Group by category for charts
$catPrices = ['vegetables'=>[],'fruits'=>[],'grains'=>[],'spices'=>[]];
$catAvg    = ['vegetables'=>0,'fruits'=>0,'grains'=>0,'spices'=>0];
$catCount  = ['vegetables'=>0,'fruits'=>0,'grains'=>0,'spices'=>0];
foreach ($liveProducts as $p) {
    $cat = strtolower($p['category']);
    if (isset($catPrices[$cat])) {
        $catPrices[$cat][] = (float)$p['price'];
        $catAvg[$cat]   += (float)$p['price'];
        $catCount[$cat] += 1;
    }
}
foreach ($catAvg as $k => $v) {
    $catAvg[$k] = $catCount[$k] > 0 ? round($v / $catCount[$k]) : 0;
}

// JS-safe arrays for charts
$vegPrices    = json_encode(array_values($catPrices['vegetables']));
$fruitPrices  = json_encode(array_values($catPrices['fruits']));
$catAvgJson   = json_encode(array_values($catAvg));
$catAvgLabels = json_encode(array_keys($catAvg));

// Category colours
$catStyle = [
    'vegetables' => ['border'=>'#009933','bg'=>'#e8f5e9','color'=>'#2e7d32','label'=>'Vegetable'],
    'fruits'     => ['border'=>'#f39c12','bg'=>'#fff8e1','color'=>'#f57f17','label'=>'Fruit'],
    'grains'     => ['border'=>'#e67e22','bg'=>'#fce4ec','color'=>'#c62828','label'=>'Grain'],
    'spices'     => ['border'=>'#e74c3c','bg'=>'#f3e5f5','color'=>'#6a1b9a','label'=>'Spice'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Market Insights - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
    <style>
        .insights-hero { background:linear-gradient(135deg,#0e1726 0%,#1a3a2a 60%,#009933 100%); color:white; padding:65px 5%; text-align:center; }
        .insights-hero h1 { font-size:36px; margin-bottom:12px; }
        .insights-hero p  { color:#cce8cc; font-size:15px; max-width:580px; margin:0 auto; }
        .insights-hero .updated { font-size:12px; color:#aaa; margin-top:10px; }

        .summary-banner { background:#009933; color:white; display:flex; justify-content:space-around; padding:28px 5%; flex-wrap:wrap; gap:20px; }
        .summary-banner .s-item { text-align:center; }
        .summary-banner .s-num  { font-size:34px; font-weight:800; }
        .summary-banner .s-lbl  { font-size:13px; opacity:.85; margin-top:3px; }

        .insights-body { max-width:1200px; margin:0 auto; padding:45px 20px; }
        .sec-title { font-size:22px; font-weight:700; color:#1a2e1a; margin-bottom:6px; }
        .sec-sub   { color:#888; font-size:14px; margin-bottom:26px; }

        .cat-filter { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:22px; }
        .cat-btn { padding:8px 20px; border:2px solid #009933; border-radius:25px; background:white; color:#009933; font-size:13px; font-weight:600; cursor:pointer; transition:.2s; }
        .cat-btn.active,.cat-btn:hover { background:#009933; color:white; }

        .price-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:16px; margin-bottom:50px; }
        .price-card { background:white; border-radius:14px; padding:20px; box-shadow:0 3px 12px rgba(0,0,0,.07); transition:.2s; border-top:4px solid #009933; }
        .price-card:hover { transform:translateY(-4px); box-shadow:0 8px 22px rgba(0,0,0,.12); }
        .price-card.fruits  { border-top-color:#f39c12; }
        .price-card.grains  { border-top-color:#e67e22; }
        .price-card.spices  { border-top-color:#e74c3c; }
        .price-card .prod-name  { font-size:15px; font-weight:700; color:#222; margin-bottom:4px; }
        .price-card .cat-tag { display:inline-block; font-size:11px; font-weight:700; padding:2px 10px; border-radius:12px; margin-bottom:12px; }
        .price-card .avg-price { font-size:28px; font-weight:800; color:#1a2e1a; line-height:1; }
        .price-card .avg-price span { font-size:14px; color:#888; font-weight:400; }
        .price-card .range    { font-size:12px; color:#888; margin-top:6px; }
        .price-card .district { font-size:12px; color:#aaa; margin-top:4px; }
        .price-card .stock-info { font-size:12px; margin-top:6px; font-weight:600; }
        .stock-ok  { color:#27ae60; }
        .stock-low { color:#e67e22; }

        .charts-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; margin-bottom:50px; }
        .chart-box { background:white; border-radius:16px; padding:26px; box-shadow:0 4px 20px rgba(0,0,0,.08); border-top:3px solid #009933; }
        .chart-box h4 { font-size:14px; font-weight:700; color:#1a2e1a; margin-bottom:18px; }
        .chart-box { background:white; border-radius:14px; padding:24px; box-shadow:0 3px 12px rgba(0,0,0,.07); }
        .chart-box h4 { font-size:15px; font-weight:700; color:#222; margin-bottom:18px; }

        .season-table { background:white; border-radius:14px; overflow:hidden; box-shadow:0 3px 12px rgba(0,0,0,.07); margin-bottom:50px; }
        .season-table table { width:100%; border-collapse:collapse; }
        .season-table thead th { background:#0e1726; color:white; padding:13px 18px; text-align:left; font-size:13px; font-weight:600; }
        .season-table tbody td { padding:12px 18px; font-size:13px; border-bottom:1px solid #f5f5f5; color:#444; }
        .season-table tbody tr:last-child td { border-bottom:none; }
        .season-table tbody tr:hover { background:#f8faf8; }
        .avail-yes  { color:#27ae60; font-weight:600; }
        .avail-peak { color:#f39c12; font-weight:600; }
        .avail-no   { color:#ccc; }

        .tips-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:18px; margin-bottom:50px; }
        .tip-card { border-radius:12px; padding:20px 22px; border-left:5px solid; }
        .tip-card h4 { font-size:14px; font-weight:700; margin-bottom:7px; }
        .tip-card p  { font-size:13px; color:#555; line-height:1.6; margin:0; }

        .no-products { text-align:center; padding:50px; color:#aaa; background:white; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,.06); }

        @media(max-width:768px) { .charts-grid{grid-template-columns:1fr;} .summary-banner{justify-content:center;} }
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="insights-hero">
    <div style="font-size:50px;margin-bottom:14px;">📊</div>
    <h1>Market Price Insights</h1>
    <p>Real-time agricultural price data from across Sri Lanka — helping farmers price fairly and consumers shop smart.</p>
    <div class="updated" id="updatedDate"></div>
</div>

<div class="summary-banner">
    <div class="s-item"><div class="s-num"><?= $productCount ?></div><div class="s-lbl">Products Tracked</div></div>
    <div class="s-item"><div class="s-num"><?= $districtCount ?></div><div class="s-lbl">Districts Covered</div></div>
    <div class="s-item"><div class="s-num"><?= $categoryCount ?></div><div class="s-lbl">Categories</div></div>
    <div class="s-item"><div class="s-num">Weekly</div><div class="s-lbl">Update Frequency</div></div>
</div>

<div class="insights-body">

    <!-- Category Filter -->
    <div class="cat-filter">
        <button class="cat-btn active" onclick="filterCat('all',this)">All</button>
        <button class="cat-btn" onclick="filterCat('vegetables',this)">🥕 Vegetables</button>
        <button class="cat-btn" onclick="filterCat('fruits',this)">🍎 Fruits</button>
        <button class="cat-btn" onclick="filterCat('grains',this)">🌾 Grains</button>
        <button class="cat-btn" onclick="filterCat('spices',this)">🌶 Spices</button>
    </div>

    <!-- Live Price Cards -->
    <h2 class="sec-title">Today's Market Prices</h2>
    <p class="sec-sub">Live prices from active listings on DirectFarm LK — updated in real time</p>

    <?php if (empty($liveProducts)): ?>
    <div class="no-products">
        <i class="fa-solid fa-store-slash" style="font-size:40px;margin-bottom:14px;display:block;"></i>
        No products listed yet.
    </div>
    <?php else: ?>
    <div class="price-grid" id="priceGrid">
        <?php foreach ($liveProducts as $p):
            $cat   = strtolower($p['category']);
            $style = $catStyle[$cat] ?? $catStyle['vegetables'];
            $stockClass = $p['stock'] > 10 ? 'stock-ok' : 'stock-low';
            $stockLabel = $p['stock'] > 10 ? "✓ In Stock ({$p['stock']} {$p['unit']})" : "⚠ Low Stock ({$p['stock']} {$p['unit']})";
            // Estimate range ±15%
            $low  = round($p['price'] * 0.85);
            $high = round($p['price'] * 1.15);
        ?>
        <div class="price-card <?= $cat ?>" data-cat="<?= $cat ?>">
            <div class="prod-name"><?= htmlspecialchars($p['name']) ?></div>
            <div><span class="cat-tag" style="background:<?= $style['bg'] ?>;color:<?= $style['color'] ?>;"><?= $style['label'] ?></span></div>
            <div class="avg-price">Rs. <?= number_format($p['price'],0) ?> <span>/ <?= htmlspecialchars($p['unit']) ?></span></div>
            <div class="range">Range: Rs. <?= $low ?> – Rs. <?= $high ?></div>
            <div class="district"><i class="fa-solid fa-location-dot"></i> <?= htmlspecialchars($p['district']) ?></div>
            <div class="stock-info <?= $stockClass ?>"><?= $stockLabel ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Charts -->
    <h2 class="sec-title">Price Distribution by Category</h2>
    <p class="sec-sub">Average and individual prices from live marketplace listings</p>
    <div class="charts-grid">
        <div class="chart-box"><h4>📊 Average Price by Category (Rs./kg)</h4><canvas id="catAvgChart" height="220"></canvas></div>
        <div class="chart-box"><h4>🥕 Vegetable Prices (Rs./unit)</h4><canvas id="vegChart" height="220"></canvas></div>
        <div class="chart-box"><h4>🍎 Fruit Prices (Rs./unit)</h4><canvas id="fruitChart" height="220"></canvas></div>
        <div class="chart-box"><h4>🌾 Grain Prices (Rs./unit)</h4><canvas id="grainChart" height="220"></canvas></div>
        <div class="chart-box"><h4>🌶 Spice Price Distribution</h4><canvas id="spiceChart" height="220"></canvas></div>
    </div>

    <!-- Seasonal Table -->
    <h2 class="sec-title">Seasonal Availability Guide</h2>
    <p class="sec-sub">Know when to buy for best prices and freshest produce</p>
    <div class="season-table">
        <table>
            <thead><tr><th>Product</th><th>Jan–Mar</th><th>Apr–Jun</th><th>Jul–Sep</th><th>Oct–Dec</th><th>Best Season</th><th>Peak Price</th></tr></thead>
            <tbody>
                <tr><td><strong>Carrot</strong></td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td class="avail-yes">✓ Available</td><td class="avail-peak">🌟 Peak</td><td>Jan – Mar</td><td>Apr – Jun</td></tr>
                <tr><td><strong>Tomato</strong></td><td class="avail-yes">✓ Available</td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td class="avail-yes">✓ Available</td><td>Apr – Jun</td><td>Nov – Jan</td></tr>
                <tr><td><strong>Mango</strong></td><td class="avail-no">— Off season</td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td class="avail-no">— Off season</td><td>Apr – Jul</td><td>Jan – Mar</td></tr>
                <tr><td><strong>Pineapple</strong></td><td class="avail-yes">✓ Available</td><td class="avail-peak">🌟 Peak</td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td>May – Aug</td><td>Dec – Feb</td></tr>
                <tr><td><strong>Cinnamon</strong></td><td class="avail-yes">✓ Available</td><td class="avail-yes">✓ Available</td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td>Jul – Sep</td><td>Jan – Mar</td></tr>
                <tr><td><strong>Rice</strong></td><td class="avail-yes">✓ Available</td><td class="avail-no">— Off season</td><td class="avail-peak">🌟 Peak</td><td class="avail-peak">🌟 Peak</td><td>Aug – Dec</td><td>Mar – Jun</td></tr>
                <tr><td><strong>Cabbage</strong></td><td class="avail-peak">🌟 Peak</td><td class="avail-yes">✓ Available</td><td class="avail-yes">✓ Available</td><td class="avail-peak">🌟 Peak</td><td>Nov – Feb</td><td>Jun – Aug</td></tr>
                <?php foreach ($liveProducts as $p):
                    $name = htmlspecialchars($p['name']);
                    $skip = ['carrot','tomato','mango','pineapple','cinnamon','rice','cabbage','samba','basmathi'];
                    $match = false;
                    foreach ($skip as $s) { if (stripos($p['name'], $s) !== false) { $match = true; break; } }
                    if ($match) continue;
                ?>
                <tr>
                    <td><strong><?= $name ?></strong></td>
                    <td class="avail-yes">✓ Available</td>
                    <td class="avail-yes">✓ Available</td>
                    <td class="avail-yes">✓ Available</td>
                    <td class="avail-yes">✓ Available</td>
                    <td>Year-round</td>
                    <td>Varies</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Market Tips — dynamic based on live prices -->
    <h2 class="sec-title">Market Tips</h2>
    <p class="sec-sub">Advice for farmers and consumers based on current listings</p>
    <div class="tips-grid">
        <div class="tip-card" style="background:#e8f5e9;border-color:#009933;">
            <h4>🌱 Best Time to Sell Vegetables</h4>
            <p>Vegetable prices typically peak between October–December. Plan your harvest to align with this period for maximum returns.
            <?php $veg = array_filter($liveProducts, fn($p) => strtolower($p['category'])==='vegetables');
            if (!empty($veg)) { $avgV = round(array_sum(array_column($veg,'price'))/count($veg)); echo " Current average: <strong>Rs. $avgV/kg</strong>."; } ?></p>
        </div>
        <div class="tip-card" style="background:#fff8e1;border-color:#f39c12;">
            <h4>🍎 Buy Fruits in Season</h4>
            <p>Mangoes and pineapples are cheapest and freshest between April–July.
            <?php $frt = array_filter($liveProducts, fn($p) => strtolower($p['category'])==='fruits');
            if (!empty($frt)) { $avgF = round(array_sum(array_column($frt,'price'))/count($frt)); echo " Current fruit average: <strong>Rs. $avgF/kg</strong>."; } ?></p>
        </div>
        <div class="tip-card" style="background:#fce4ec;border-color:#e74c3c;">
            <h4>🌶 Spice Market</h4>
            <p>
            <?php $spc = array_filter($liveProducts, fn($p) => strtolower($p['category'])==='spices');
            if (!empty($spc)) {
                $avgS = round(array_sum(array_column($spc,'price'))/count($spc));
                echo "Current average spice price is <strong>Rs. $avgS/unit</strong>. If you grow spices, now is a good time to list your stock on DirectFarm LK.";
            } else { echo "Spice listings are low — a great opportunity for spice farmers to list products now."; } ?></p>
        </div>
        <div class="tip-card" style="background:#e3f2fd;border-color:#1565c0;">
            <h4>📦 Reduce Post-Harvest Waste</h4>
            <p>List surplus produce on the marketplace immediately after harvest. Getting to consumers quickly maximises freshness and profit. Currently <strong><?= $productCount ?> products</strong> are listed across <strong><?= $districtCount ?> districts</strong>.</p>
        </div>
        <div class="tip-card" style="background:#f3e5f5;border-color:#6a1b9a;">
            <h4>⚖️ Fair Pricing Guide</h4>
            <p>Set your product prices within the market range shown in the cards above. Pricing too high loses buyers; pricing within range builds trust and repeat customers.</p>
        </div>
        <div class="tip-card" style="background:#e0f7fa;border-color:#00838f;">
            <h4>📊 Live Data</h4>
            <p>All prices on this page come directly from active farmer listings on DirectFarm LK. Prices update automatically whenever farmers add or edit their products.</p>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>

<script>
document.getElementById('updatedDate').textContent = 'Last updated: ' + new Date().toLocaleDateString('en-GB',{day:'numeric',month:'long',year:'numeric'});

function filterCat(cat, btn) {
    document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.querySelectorAll('.price-card').forEach(card => {
        card.style.display = (cat==='all' || card.dataset.cat===cat) ? 'block' : 'none';
    });
}

<?php
$vegItems    = array_values(array_filter($liveProducts, fn($p) => strtolower($p['category'])==='vegetables'));
$fruitItems  = array_values(array_filter($liveProducts, fn($p) => strtolower($p['category'])==='fruits'));
$grainItems  = array_values(array_filter($liveProducts, fn($p) => strtolower($p['category'])==='grains'));
$spiceItems  = array_values(array_filter($liveProducts, fn($p) => strtolower($p['category'])==='spices'));
$vegLabels   = json_encode(array_column($vegItems,   'name'));
$vegData     = json_encode(array_column($vegItems,   'price'));
$fruitLabels = json_encode(array_column($fruitItems, 'name'));
$fruitData   = json_encode(array_column($fruitItems, 'price'));
$grainLabels = json_encode(array_column($grainItems, 'name'));
$grainData   = json_encode(array_column($grainItems, 'price'));
$spiceLabels = json_encode(array_column($spiceItems, 'name'));
$spiceData   = json_encode(array_column($spiceItems, 'price'));
?>

// Global chart defaults
Chart.defaults.font.family = "'Segoe UI', sans-serif";
Chart.defaults.color = '#555';

const chartOptions = (yLabel) => ({
    responsive: true,
    maintainAspectRatio: true,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#0e1726',
            titleColor: '#cce8cc',
            bodyColor: '#fff',
            padding: 12,
            cornerRadius: 10,
            callbacks: { label: ctx => ' Rs. ' + ctx.parsed.y.toLocaleString() }
        }
    },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 12 } } },
        y: {
            beginAtZero: true,
            grid: { color: '#f0f0f0', lineWidth: 1 },
            ticks: { callback: v => 'Rs.' + v.toLocaleString(), font: { size: 11 } }
        }
    }
});

// ── 1. Category Average — Gradient Bar Chart ──────────────────────────────────
const catLabels = <?= $catAvgLabels ?>;
const catAvgs   = <?= $catAvgJson ?>;
const catCtx = document.getElementById('catAvgChart').getContext('2d');

const catGradients = [
    (() => { const g=catCtx.createLinearGradient(0,0,0,300); g.addColorStop(0,'#009933'); g.addColorStop(1,'#0e1726'); return g; })(),
    (() => { const g=catCtx.createLinearGradient(0,0,0,300); g.addColorStop(0,'#f39c12'); g.addColorStop(1,'#e67e22'); return g; })(),
    (() => { const g=catCtx.createLinearGradient(0,0,0,300); g.addColorStop(0,'#e67e22'); g.addColorStop(1,'#c0392b'); return g; })(),
    (() => { const g=catCtx.createLinearGradient(0,0,0,300); g.addColorStop(0,'#9b59b6'); g.addColorStop(1,'#6a1b9a'); return g; })(),
];
new Chart(catCtx, {
    type: 'bar',
    data: {
        labels: catLabels.map(l => l.charAt(0).toUpperCase()+l.slice(1)),
        datasets: [{ label: 'Avg Price (Rs.)', data: catAvgs, backgroundColor: catGradients, borderRadius: 12, borderSkipped: false }]
    },
    options: chartOptions()
});

// ── 2. Vegetable Prices — Line Chart ─────────────────────────────────────────
const vegCtx = document.getElementById('vegChart').getContext('2d');
const vegGrad = vegCtx.createLinearGradient(0, 0, 0, 250);
vegGrad.addColorStop(0, 'rgba(0,153,51,0.4)');
vegGrad.addColorStop(1, 'rgba(0,153,51,0.02)');
new Chart(vegCtx, {
    type: 'line',
    data: {
        labels: <?= $vegLabels ?>,
        datasets: [{
            label: 'Price (Rs.)',
            data: <?= $vegData ?>,
            borderColor: '#009933',
            backgroundColor: vegGrad,
            borderWidth: 3,
            pointBackgroundColor: '#009933',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 9,
            fill: true,
            tension: 0.4
        }]
    },
    options: chartOptions()
});

// ── 3. Fruit Prices — Line Chart ──────────────────────────────────────────────
const fruitCtx = document.getElementById('fruitChart').getContext('2d');
const fruitGrad = fruitCtx.createLinearGradient(0, 0, 0, 250);
fruitGrad.addColorStop(0, 'rgba(243,156,18,0.4)');
fruitGrad.addColorStop(1, 'rgba(243,156,18,0.02)');
new Chart(fruitCtx, {
    type: 'line',
    data: {
        labels: <?= $fruitLabels ?>,
        datasets: [{
            label: 'Price (Rs.)',
            data: <?= $fruitData ?>,
            borderColor: '#f39c12',
            backgroundColor: fruitGrad,
            borderWidth: 3,
            pointBackgroundColor: '#f39c12',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 9,
            fill: true,
            tension: 0.4
        }]
    },
    options: chartOptions()
});

// ── 4. Grains — Gradient Bar Chart ───────────────────────────────────────────
const grainCtx = document.getElementById('grainChart').getContext('2d');
const grainGrad = grainCtx.createLinearGradient(0, 0, 0, 250);
grainGrad.addColorStop(0, '#e67e22');
grainGrad.addColorStop(1, '#c0392b');
new Chart(grainCtx, {
    type: 'bar',
    data: {
        labels: <?= $grainLabels ?>,
        datasets: [{ label: 'Price (Rs.)', data: <?= $grainData ?>,
            backgroundColor: grainGrad, borderRadius: 10, borderSkipped: false }]
    },
    options: chartOptions()
});

// ── 5. Spice Prices — Doughnut Chart ─────────────────────────────────────────
new Chart(document.getElementById('spiceChart'), {
    type: 'doughnut',
    data: {
        labels: <?= $spiceLabels ?>,
        datasets: [{
            data: <?= $spiceData ?>,
            backgroundColor: ['#9b59b6','#e74c3c','#c0392b','#8e44ad','#6a1b9a'],
            borderWidth: 3,
            borderColor: '#fff',
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } },
            tooltip: {
                backgroundColor: '#0e1726',
                titleColor: '#cce8cc',
                bodyColor: '#fff',
                padding: 12,
                cornerRadius: 10,
                callbacks: { label: ctx => ' Rs. ' + ctx.parsed.toLocaleString() + '/unit' }
            }
        }
    }
});
</script>
</body>
</html>
