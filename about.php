<?php require_once 'includes/config.php';
$db = getDB();
$farmerCount   = $db->query("SELECT COUNT(*) as c FROM users WHERE role='farmer'")->fetch_assoc()['c'];
$consumerCount = $db->query("SELECT COUNT(*) as c FROM users WHERE role='consumer'")->fetch_assoc()['c'];
$productCount  = $db->query("SELECT COUNT(*) as c FROM products WHERE is_available=1")->fetch_assoc()['c'];
$districtCount = $db->query("SELECT COUNT(DISTINCT district) as c FROM products")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>

    <title>About Us - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
   
    </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="about-hero">
    <div class="about-logo-wrap">
        <img src="DirectFarm logo.png" alt="DirectFarm LK" class="about-logo-img"
             onerror="this.style.display='none'">
    </div>
    <h1>About DirectFarm LK</h1>
    <p>A digital marketplace connecting Sri Lankan farmers directly with consumers — eliminating middlemen, ensuring fair prices, and supporting sustainable agriculture.</p>
</div>

<div class="stats-bar">
    <div class="stat-item"><div class="stat-num"><?= $farmerCount ?>+</div><div class="stat-lbl">Registered Farmers</div></div>
    <div class="stat-item"><div class="stat-num"><?= $consumerCount ?>+</div><div class="stat-lbl">Happy Consumers</div></div>
    <div class="stat-item"><div class="stat-num"><?= $productCount ?>+</div><div class="stat-lbl">Fresh Products</div></div>
    <div class="stat-item"><div class="stat-num"><?= $districtCount ?>+</div><div class="stat-lbl">Districts Covered</div></div>
</div>

<div class="about-body">

    <!-- Mission -->
    <div class="about-mission">
        <div class="mission-dark-card">
            <h3 class="mission-card-title">🌿 Why DirectFarm LK?</h3>
            <div class="mission-list">
                <div class="mission-item">
                    <div class="mission-icon">🚫</div>
                    <div><div class="mission-item-title">No Middlemen</div><div class="mission-item-desc">Farmers sell directly. No agents taking a cut.</div></div>
                </div>
                <div class="mission-item">
                    <div class="mission-icon">💰</div>
                    <div><div class="mission-item-title">Fair Prices</div><div class="mission-item-desc">Farmers earn more, consumers pay less.</div></div>
                </div>
                <div class="mission-item">
                    <div class="mission-icon">📍</div>
                    <div><div class="mission-item-title">Local & Fresh</div><div class="mission-item-desc">Know exactly which farm your food comes from.</div></div>
                </div>
                <div class="mission-item">
                    <div class="mission-icon">📱</div>
                    <div><div class="mission-item-title">Easy to Use</div><div class="mission-item-desc">Simple platform for farmers and consumers alike.</div></div>
                </div>
            </div>
        </div>
        <div>
            <h2>Our Mission</h2>
            <p>DirectFarm LK was built by a group of students at Sabaragamuwa University of Sri Lanka who saw a gap between the hard-working farmers growing Sri Lanka's food and the consumers who wanted to buy it fresh.</p>
            <p>We created a platform where farmers can list their produce directly, set fair prices, and reach customers across the country — no middlemen, no markups.</p>
            <p>For consumers, it means fresh, locally-sourced vegetables, fruits, grains, and spices delivered straight from the farm.</p>
        </div>
    </div>

    <!-- Values -->
    <div class="values-section">
        <h2>Our Values</h2>
        <p class="sub">The principles that guide everything we do</p>
        <div class="values-grid">
            <div class="value-card">
                <div class="icon">⚖️</div>
                <h3>Fair Pricing</h3>
                <p>Farmers set their own prices. No hidden fees, no exploitative middlemen cutting into their earnings.</p>
            </div>
            <div class="value-card">
                <div class="icon">🌱</div>
                <h3>Sustainability</h3>
                <p>We support farming practices that are good for the land, the farmer, and future generations.</p>
            </div>
            <div class="value-card">
                <div class="icon">🤝</div>
                <h3>Community</h3>
                <p>Building a community where farmers and consumers support each other and share knowledge.</p>
            </div>
            <div class="value-card">
                <div class="icon">🔒</div>
                <h3>Trust & Safety</h3>
                <p>Every farmer is verified. Every transaction is secure. Every product is quality-checked.</p>
            </div>
        </div>
    </div>

    <!-- Roles -->
    <div class="team-section">
        <h2>Who We Serve</h2>
        <p class="sub">DirectFarm LK is built for two groups of people</p>
        <div class="roles-grid">
            <div class="role-card farmer">
                <div class="role-icon">👨‍🌾</div>
                <div>
                    <h3>For Farmers</h3>
                    <ul>
                        <li>List products and set your own prices</li>
                        <li>Reach consumers across Sri Lanka</li>
                        <li>Manage orders from your dashboard</li>
                        <li>Get paid fairly for your harvest</li>
                        <li>Connect with the farming community</li>
                    </ul>
                </div>
            </div>
            <div class="role-card consumer">
                <div class="role-icon">🛒</div>
                <div>
                    <h3>For Consumers</h3>
                    <ul>
                        <li>Buy fresh produce direct from farmers</li>
                        <li>Know exactly where your food comes from</li>
                        <li>Browse by category, district or crop</li>
                        <li>Free delivery on orders over Rs. 1000</li>
                        <li>Leave reviews and contact farmers</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA -->
    <div class="cta-box">
        <h2>Ready to Join DirectFarm LK?</h2>
        <p>Whether you're a farmer looking to sell or a consumer looking for fresh produce — we have a place for you.</p>
        <div class="cta-btns">
            <a href="marketplace.php" class="cta-btn-primary">🛒 Shop Now</a>
            <a href="#" onclick="openAuth('register','farmer')" class="cta-btn-secondary">🌾 Register as Farmer</a>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
</body>
</html>
