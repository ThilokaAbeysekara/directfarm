<?php
$basePath = '';
if (strpos($_SERVER['PHP_SELF'], '/farmer/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/consumer/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false) {
    $basePath = '../';
}
?>
<footer class="footer">
    <div class="footer-container">
        <div class="footer-section">
            <h3>DirectFarm LK</h3>
            <p>Connecting farmers and consumers across Sri Lanka.</p>
        </div>
        <div class="footer-section">
            <h4>Quick Links</h4>
            <a href="<?= $basePath ?>index.php">Home</a>
            <a href="<?= $basePath ?>about.php">About</a>
            <a href="<?= $basePath ?>marketplace.php">Marketplace</a>
            <a href="<?= $basePath ?>insights.php">Insights</a>
            <a href="<?= $basePath ?>news.php">News & Blog</a>
            <a href="<?= $basePath ?>forum.php">Forum</a>
            <a href="<?= $basePath ?>logistics.php">Delivery</a>
            <a href="<?= $basePath ?>faq.php">FAQ</a>
        </div>
        <div class="footer-section">
            <h4>Support</h4>
            <a href="<?= $basePath ?>contactus.php">Contact</a>
        </div>
        <div class="footer-section">
            <h4>Connect</h4>
            <p><a href="mailto:info@directfarmlk.com" style="color:inherit;text-decoration:none;">📧 info@directfarmlk.com</a></p>
            <p><a href="tel:+94112345678" style="color:inherit;text-decoration:none;">📞 +94 11 234 5678</a></p>
        </div>
    </div>
    <p class="footer-bottom">© 2025 DirectFarm LK. All Rights Reserved.</p>
</footer>
