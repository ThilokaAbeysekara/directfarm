<?php
require_once 'includes/config.php';
$db = getDB();

// Auto-create table if not exists
$db->query("CREATE TABLE IF NOT EXISTS blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    category ENUM('news','tips','market','community') DEFAULT 'news',
    emoji VARCHAR(10) DEFAULT '📰',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
)");

$msg = '';

// Admin: create post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasRole('admin')) {
    $act = $_POST['act'] ?? '';

    if ($act === 'create') {
        $title    = clean($_POST['title'] ?? '');
        $body     = clean($_POST['body'] ?? '');
        $category = clean($_POST['category'] ?? 'news');
        $emoji    = clean($_POST['emoji'] ?? '📰');
        if ($title && $body) {
            $s = $db->prepare("INSERT INTO blog_posts (admin_id, title, body, category, emoji) VALUES (?,?,?,?,?)");
            if ($s) { $s->bind_param("issss", $_SESSION['user_id'], $title, $body, $category, $emoji); $s->execute(); }
            $msg = 'success:Post published!';
        }
    }
    if ($act === 'edit') {
        $id    = (int)$_POST['post_id'];
        $title = clean($_POST['title'] ?? '');
        $body  = clean($_POST['body'] ?? '');
        $cat   = clean($_POST['category'] ?? 'news');
        $emoji = clean($_POST['emoji'] ?? '📰');
        $s = $db->prepare("UPDATE blog_posts SET title=?, body=?, category=?, emoji=? WHERE id=?");
        if ($s) { $s->bind_param("ssssi", $title, $body, $cat, $emoji, $id); $s->execute(); }
        $msg = 'success:Post updated!';
    }
    if ($act === 'delete') {
        $id = (int)$_POST['post_id'];
        $s  = $db->prepare("DELETE FROM blog_posts WHERE id=?");
        if ($s) { $s->bind_param("i", $id); $s->execute(); }
        $msg = 'success:Post deleted.';
    }
}

// Fetch posts
$filterCat = clean($_GET['cat'] ?? 'all');
$catWhere  = $filterCat !== 'all' ? "WHERE category='$filterCat'" : '';
$posts = $db->query("SELECT bp.*, u.name as author FROM blog_posts bp JOIN users u ON bp.admin_id=u.id $catWhere ORDER BY bp.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$featured  = $posts[0] ?? null;
$restPosts = array_slice($posts, 1);

// Recent 5 for sidebar
$recent = $db->query("SELECT id, title, emoji, created_at FROM blog_posts ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$catColors = [
    'news'      => ['bg'=>'#fce4e4','color'=>'#c62828','label'=>'News'],
    'tips'      => ['bg'=>'#e8f5e9','color'=>'#2e7d32','label'=>'Farmer Tips'],
    'market'    => ['bg'=>'#fff8e1','color'=>'#f57f17','label'=>'Market Update'],
    'community' => ['bg'=>'#e3f2fd','color'=>'#1565c0','label'=>'Community'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>News & Blog - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .news-hero { background: linear-gradient(135deg, #0e1726 0%, #1a3a2a 60%, #009933 100%); color:white; padding:65px 5%; text-align:center; }
        .news-hero h1 { font-size:36px; margin-bottom:10px; }
        .news-hero p  { color:#cce8cc; font-size:15px; max-width:560px; margin:0 auto; }
        .news-body { max-width:1200px; margin:0 auto; padding:50px 20px; display:grid; grid-template-columns:1fr 340px; gap:35px; }
        .cat-tabs { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:28px; }
        .cat-tab { padding:7px 18px; border:2px solid #009933; border-radius:25px; background:white; color:#009933; font-size:13px; font-weight:600; cursor:pointer; transition:.2s; text-decoration:none; display:inline-block; }
        .cat-tab.active, .cat-tab:hover { background:#009933; color:white; }
        .featured-post { background:white; overflow:hidden; margin-bottom:28px; display:grid; grid-template-columns:1fr 1fr; border-bottom:1px solid #eee; }
        .featured-img { display:flex; align-items:stretch; min-height:280px; }
        .featured-body { padding:32px; }
        .featured-tag { display:inline-block; font-size:11px; font-weight:700; padding:4px 12px; border-radius:12px; margin-bottom:12px; text-transform:uppercase; letter-spacing:.5px; }
        .featured-body h2 { font-size:22px; color:#1a2e1a; margin-bottom:10px; line-height:1.4; }
        .featured-body p  { color:#666; font-size:14px; line-height:1.7; margin-bottom:18px; }
        .post-meta { font-size:12px; color:#aaa; margin-bottom:18px; }
        .post-meta span { margin-right:14px; }
        .read-btn { display:inline-block; background:#009933; color:white; padding:10px 24px; border-radius:25px; font-size:13px; font-weight:700; text-decoration:none; }
        .read-btn:hover { background:#007a29; }
        .post-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .post-card { background:white; border-radius:14px; overflow:hidden; box-shadow:0 3px 12px rgba(0,0,0,.07); transition:.25s; display:flex; flex-direction:column; }
        .post-card:hover { transform:translateY(-5px); box-shadow:0 10px 26px rgba(0,0,0,.12); }
        .post-thumb { height:140px; display:flex; align-items:center; justify-content:center; font-size:60px; }
        .post-content { padding:18px; flex:1; display:flex; flex-direction:column; }
        .post-tag { display:inline-block; font-size:11px; font-weight:700; padding:3px 10px; border-radius:10px; margin-bottom:9px; text-transform:uppercase; }
        .post-content h3 { font-size:15px; color:#1a2e1a; margin-bottom:7px; line-height:1.4; }
        .post-content p  { font-size:13px; color:#777; line-height:1.6; margin-bottom:12px; flex:1; }
        .post-footer { display:flex; justify-content:space-between; align-items:center; margin-top:auto; }
        .post-date { font-size:12px; color:#bbb; }
        .sidebar-box { background:white; border-radius:14px; padding:22px; box-shadow:0 3px 12px rgba(0,0,0,.07); margin-bottom:22px; }
        .sidebar-box h3 { font-size:15px; font-weight:700; color:#1a2e1a; margin-bottom:16px; border-bottom:2px solid #e8f5e9; padding-bottom:10px; }
        .sidebar-post { display:flex; gap:12px; margin-bottom:14px; align-items:flex-start; }
        .s-thumb { width:52px; height:52px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:26px; flex-shrink:0; background:#e8f5e9; }
        .s-post-title { font-size:13px; font-weight:600; color:#333; line-height:1.4; margin-bottom:4px; }
        .s-post-date  { font-size:11px; color:#bbb; }
        .tag-cloud { display:flex; flex-wrap:wrap; gap:8px; }
        .tag-pill { padding:5px 14px; border-radius:20px; background:#f0f7f0; color:#009933; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; transition:.2s; }
        .tag-pill:hover { background:#009933; color:white; }
        .newsletter-box { background:linear-gradient(135deg,#1a3a2a,#009933); border-radius:14px; padding:24px; color:white; margin-bottom:22px; text-align:center; }
        .newsletter-box h3 { font-size:16px; margin-bottom:8px; }
        .newsletter-box p  { font-size:13px; color:#cce8cc; margin-bottom:16px; }
        .newsletter-box input { width:100%; padding:10px 14px; border-radius:8px; border:none; font-size:13px; margin-bottom:10px; }
        .newsletter-box button { width:100%; background:white; color:#1a6e2a; border:none; padding:10px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }

        /* Admin controls */
        .admin-bar { background:#0e1726; color:white; padding:12px 20px; display:flex; justify-content:space-between; align-items:center; border-radius:12px; margin-bottom:24px; }
        .admin-bar span { font-size:14px; }
        .btn-new-post { background:#009933; color:white; border:none; padding:9px 20px; border-radius:25px; font-size:13px; font-weight:700; cursor:pointer; }
        .btn-new-post:hover { background:#007a29; }
        .admin-actions { display:flex; gap:8px; margin-top:10px; }
        .btn-edit-post   { background:#e8f5e9; color:#2e7d32; border:none; padding:5px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; }
        .btn-delete-post { background:#fce4e4; color:#c0392b; border:none; padding:5px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; }
        .btn-edit-post:hover   { background:#009933; color:white; }
        .btn-delete-post:hover { background:#e74c3c; color:white; }

        /* Modal */
        .post-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:9999; align-items:center; justify-content:center; }
        .post-modal.open { display:flex; }
        .post-modal-box { background:white; border-radius:16px; padding:30px; width:90%; max-width:580px; max-height:90vh; overflow-y:auto; position:relative; }
        .post-modal-box h3 { font-size:18px; font-weight:700; margin-bottom:20px; color:#1a2e1a; }
        .pm-close { position:absolute; top:16px; right:20px; font-size:22px; cursor:pointer; background:none; border:none; color:#aaa; }
        .pm-fg { margin-bottom:16px; }
        .pm-fg label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:6px; }
        .pm-fg input, .pm-fg textarea, .pm-fg select { width:100%; padding:11px 14px; border:1.5px solid #eee; border-radius:10px; font-size:14px; background:#fafafa; font-family:inherit; }
        .pm-fg textarea { min-height:140px; resize:vertical; }
        .pm-fg input:focus, .pm-fg textarea:focus, .pm-fg select:focus { outline:none; border-color:#009933; background:white; }
        .pm-submit { width:100%; padding:13px; background:#009933; color:white; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; margin-top:4px; }
        .pm-submit:hover { background:#007a29; }
        .msg-box { padding:12px 16px; border-radius:8px; margin-bottom:16px; font-weight:600; font-size:14px; }
        .msg-success { background:#e8f5e9; color:#2e7d32; }

        @media(max-width:900px) { .news-body{grid-template-columns:1fr;} .featured-post{grid-template-columns:1fr;} .featured-img{min-height:160px;} .post-grid{grid-template-columns:1fr;} }
    .article-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.65); z-index:9998; align-items:flex-start; justify-content:center; padding:40px 20px; overflow-y:auto; }
        .article-modal.open { display:flex; }
        .article-modal-box { background:white; border-radius:20px; width:100%; max-width:740px; overflow:hidden; position:relative; animation:slideUp 0.3s ease; }
        @keyframes slideUp { from{transform:translateY(40px);opacity:0} to{transform:translateY(0);opacity:1} }
        .amod-banner { padding:44px 32px; text-align:center; position:relative; overflow:hidden; }
        .amod-banner::before { content:''; position:absolute; inset:0; opacity:0.06; background:repeating-linear-gradient(45deg,white 0,white 1px,transparent 0,transparent 50%) 0 0/20px 20px; }
        .amod-close { position:absolute; top:14px; right:16px; background:rgba(255,255,255,0.15); border:none; color:white; width:32px; height:32px; border-radius:50%; font-size:18px; cursor:pointer; display:flex; align-items:center; justify-content:center; z-index:1; }
        .amod-body { padding:32px 36px; }
        .amod-meta { display:flex; gap:16px; color:#aaa; font-size:13px; margin-bottom:20px; flex-wrap:wrap; }
        .amod-meta i { margin-right:5px; }
        .amod-text { font-size:15px; color:#444; line-height:1.9; }
        .amod-text p { margin-bottom:16px; }
        .amod-share { display:flex; gap:10px; padding:20px 36px; border-top:1px solid #f0f0f0; flex-wrap:wrap; align-items:center; }
        .amod-share span { font-size:13px; font-weight:600; color:#888; margin-right:4px; }
        .share-wa { background:#25d366; color:white; padding:8px 18px; border-radius:50px; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .share-fb { background:#1877f2; color:white; padding:8px 18px; border-radius:50px; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:6px; }
        .emoji-opt.selected, .emoji-opt-edit.selected { border-color:#009933 !important; background:#e8f5e9 !important; transform:scale(1.15); }
        .emoji-opt:hover, .emoji-opt-edit:hover { border-color:#009933; background:#f0faf0; }
        </style>
</head>
<body>
<?php include 'includes/navbar.php'; ?>

<div class="news-hero">
    <div style="font-size:50px;margin-bottom:14px;">📰</div>
    <h1>News & Blog</h1>
    <p>Stories from the farm, market updates, and tips for farmers and consumers across Sri Lanka.</p>
</div>

<div class="news-body">
    <div>
        <?php if (!empty($msg) && strpos($msg,'success') === 0): ?>
        <div class="msg-box msg-success">✓ <?= htmlspecialchars(substr($msg,8)) ?></div>
        <?php endif; ?>

        <?php if (hasRole('admin')): ?>
        <div class="admin-bar">
            <span>🛡️ Admin — You can create, edit and delete blog posts</span>
            <button class="btn-new-post" onclick="openPostModal()">+ New Post</button>
        </div>
        <?php endif; ?>

        <!-- Category Tabs -->
        <div class="cat-tabs">
            <a href="news.php" class="cat-tab <?= $filterCat==='all'?'active':'' ?>">All</a>
            <a href="news.php?cat=news"      class="cat-tab <?= $filterCat==='news'?'active':'' ?>">📢 News</a>
            <a href="news.php?cat=tips"      class="cat-tab <?= $filterCat==='tips'?'active':'' ?>">💡 Farmer Tips</a>
            <a href="news.php?cat=market"    class="cat-tab <?= $filterCat==='market'?'active':'' ?>">📊 Market</a>
            <a href="news.php?cat=community" class="cat-tab <?= $filterCat==='community'?'active':'' ?>">🤝 Community</a>
        </div>

        <?php if (empty($posts)): ?>
        <div style="text-align:center;padding:60px;color:#aaa;background:white;border-radius:14px;box-shadow:0 2px 8px rgba(0,0,0,.06);">
            <i class="fa-solid fa-newspaper" style="font-size:40px;margin-bottom:14px;display:block;"></i>
            No posts yet. <?= hasRole('admin') ? 'Click "+ New Post" to publish the first one.' : '' ?>
        </div>
        <?php else: ?>

        <!-- Featured Post -->
        <?php if ($featured): $fc = $catColors[$featured['category']] ?? $catColors['news']; ?>
        <div class="featured-post">
            <div class="featured-img" style="background:linear-gradient(135deg,#0e1726,#1a3a2a,#009933);position:relative;overflow:hidden;">
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;">
                    <img src="DirectFarm logo.png" alt="DirectFarm LK" style="width:90px;height:90px;object-fit:contain;"
                             onerror="this.style.display='none'">
                    <div style="font-size:22px;letter-spacing:2px;color:rgba(255,255,255,0.5);font-weight:600;">DirectFarm LK</div>
                </div>
                <div style="position:absolute;bottom:20px;right:20px;font-size:28px;opacity:0.15;"><?= $featured['emoji'] ?></div>
            </div>
            <div class="featured-body">
                <span class="featured-tag" style="background:<?= $fc['bg'] ?>;color:<?= $fc['color'] ?>;">Featured · <?= $fc['label'] ?></span>
                <h2><?= htmlspecialchars($featured['title']) ?></h2>
                <div class="post-meta">
                    <span><i class="fa-solid fa-user"></i> <?= htmlspecialchars($featured['author']) ?></span>
                    <span><i class="fa-solid fa-calendar"></i> <?= date('M j, Y', strtotime($featured['created_at'])) ?></span>
                </div>
                <p><?= htmlspecialchars(substr($featured['body'], 0, 200)) ?>...</p>
                <button onclick="openArticle(<?= $featured['id'] ?>)" style="display:inline-flex;align-items:center;gap:7px;background:#009933;color:white;padding:10px 22px;border-radius:50px;border:none;cursor:pointer;font-size:14px;font-weight:600;margin-bottom:14px;font-family:inherit;">Read Full Article <i class="fa-solid fa-arrow-right" style="font-size:11px;"></i></button>
                <?php if (hasRole('admin')): ?>
                <div class="admin-actions">
                    <button class="btn-edit-post" onclick="openEditModal(<?= $featured['id'] ?>,'<?= addslashes(htmlspecialchars($featured['title'])) ?>','<?= addslashes(htmlspecialchars($featured['body'])) ?>','<?= $featured['category'] ?>','<?= $featured['emoji'] ?>')">✏️ Edit</button>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this post?')">
                        <input type="hidden" name="act" value="delete">
                        <input type="hidden" name="post_id" value="<?= $featured['id'] ?>">
                        <button type="submit" class="btn-delete-post">🗑️ Delete</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Post Grid -->
        <?php if (!empty($restPosts)): ?>
        <div class="post-grid">
            <?php foreach ($restPosts as $p):
                $pc = $catColors[$p['category']] ?? $catColors['news'];
                $thumbBg = ['news'=>'#fce4e4','tips'=>'#e8f5e9','market'=>'#fff8e1','community'=>'#e3f2fd'][$p['category']] ?? '#f5f5f5';
            ?>
            <div class="post-card">
                <div class="post-thumb" style="background:<?= $thumbBg ?>"><?= $p['emoji'] ?></div>
                <div class="post-content">
                    <span class="post-tag" style="background:<?= $pc['bg'] ?>;color:<?= $pc['color'] ?>;"><?= $pc['label'] ?></span>
                    <h3><?= htmlspecialchars($p['title']) ?></h3>
                    <p><?= htmlspecialchars(substr($p['body'], 0, 120)) ?>...</p>
                    <div class="post-footer" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                        <span class="post-date"><i class="fa-solid fa-calendar"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
                        <button onclick="openArticle(<?= $p['id'] ?>)" style="color:#009933;font-size:13px;font-weight:600;background:none;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:4px;font-family:inherit;padding:0;">Read More <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></button>
                        <?php if (hasRole('admin')): ?>
                        <div style="display:flex;gap:6px;">
                            <button class="btn-edit-post" onclick="openEditModal(<?= $p['id'] ?>,'<?= addslashes(htmlspecialchars($p['title'])) ?>','<?= addslashes(htmlspecialchars($p['body'])) ?>','<?= $p['category'] ?>','<?= $p['emoji'] ?>')">✏️</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="act" value="delete">
                                <input type="hidden" name="post_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="btn-delete-post">🗑️</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Sidebar -->
    <div>
        <div class="newsletter-box">
            <h3>📬 Stay Updated</h3>
            <p>Get weekly market news and farm tips delivered to your inbox.</p>
            <input type="email" id="nlEmail" placeholder="your@email.com">
            <button onclick="subscribeNewsletter()">Subscribe for Free</button>
            <div id="nlMsg" style="margin-top:10px;font-size:12px;color:#cce8cc;display:none;"></div>
        </div>
        <div class="sidebar-box">
            <h3>🕐 Recent Posts</h3>
            <?php foreach ($recent as $r): ?>
            <div class="sidebar-post">
                <div class="s-thumb"><?= $r['emoji'] ?></div>
                <div>
                    <div class="s-post-title"><?= htmlspecialchars(substr($r['title'],0,45)) ?>...</div>
                    <div class="s-post-date"><?= date('M j, Y', strtotime($r['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($recent)): ?><p style="font-size:13px;color:#aaa;">No posts yet.</p><?php endif; ?>
        </div>
        <div class="sidebar-box">
            <h3>🏷️ Tags</h3>
            <div class="tag-cloud">
                <a href="news.php?cat=tips"      class="tag-pill">Farming Tips</a>
                <a href="news.php?cat=market"    class="tag-pill">Market Prices</a>
                <a href="news.php?cat=news"      class="tag-pill">Platform News</a>
                <a href="news.php?cat=community" class="tag-pill">Community</a>
                <a href="news.php"               class="tag-pill">All Posts</a>
            </div>
        </div>
        <div class="sidebar-box">
            <h3>ℹ️ About This Blog</h3>
            <p style="font-size:13px;color:#666;line-height:1.7;">The DirectFarm LK blog shares agricultural news, market insights, farmer success stories, and practical tips. Published by the DirectFarm LK admin team.</p>
        </div>
    </div>
</div>

<?php if (hasRole('admin')): ?>
<!-- Create Post Modal -->
<div class="post-modal" id="createModal">
    <div class="post-modal-box">
        <button class="pm-close" onclick="document.getElementById('createModal').classList.remove('open')">×</button>
        <h3>✍️ New Blog Post</h3>
        <form method="POST">
            <input type="hidden" name="act" value="create">
            <div class="pm-fg">
                <label>Emoji Icon</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;">
                    <?php
                    $emojiOptions = ['📰','📢','💡','📊','🌿','🥕','🍎','🌽','🌶','🥦','🍅','🧅','🧄','🍋','🥭','🍍','🌴','🌱','🌻','💰','🚚','🤝','📋','🔔','⭐'];
                    foreach ($emojiOptions as $em):
                    ?>
                    <button type="button" onclick="document.getElementById('newEmoji').value='<?= $em ?>'; document.querySelectorAll('.emoji-opt').forEach(b=>b.classList.remove('selected')); this.classList.add('selected');"
                        class="emoji-opt" style="font-size:20px;padding:6px 8px;border:1.5px solid #eee;border-radius:8px;cursor:pointer;background:white;transition:all 0.15s;"
                        data-em="<?= $em ?>"><?= $em ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="emoji" id="newEmoji" value="📰">
            </div>
            <div class="pm-fg"><label>Title</label><input type="text" name="title" placeholder="Post title..." required autocomplete="off"></div>
            <div class="pm-fg">
                <label>Category</label>
                <select name="category">
                    <option value="news">📢 News</option>
                    <option value="tips">💡 Farmer Tips</option>
                    <option value="market">📊 Market Update</option>
                    <option value="community">🤝 Community</option>
                </select>
            </div>
            <div class="pm-fg"><label>Content</label><textarea name="body" placeholder="Write your post here..." required></textarea></div>
            <button type="submit" class="pm-submit">Publish Post</button>
        </form>
    </div>
</div>

<!-- Edit Post Modal -->
<div class="post-modal" id="editModal">
    <div class="post-modal-box">
        <button class="pm-close" onclick="document.getElementById('editModal').classList.remove('open')">×</button>
        <h3>✏️ Edit Blog Post</h3>
        <form method="POST">
            <input type="hidden" name="act" value="edit">
            <input type="hidden" name="post_id" id="editId">
            <div class="pm-fg">
                <label>Emoji Icon</label>
                <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;" id="editEmojiPicker">
                    <?php
                    foreach ($emojiOptions as $em):
                    ?>
                    <button type="button" onclick="document.getElementById('editEmoji').value='<?= $em ?>'; document.querySelectorAll('.emoji-opt-edit').forEach(b=>b.classList.remove('selected')); this.classList.add('selected');"
                        class="emoji-opt-edit" style="font-size:20px;padding:6px 8px;border:1.5px solid #eee;border-radius:8px;cursor:pointer;background:white;transition:all 0.15s;"
                        data-em="<?= $em ?>"><?= $em ?></button>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="emoji" id="editEmoji" value="📰">
            </div>
            <div class="pm-fg"><label>Title</label><input type="text" name="title" id="editTitle" required autocomplete="off"></div>
            <div class="pm-fg">
                <label>Category</label>
                <select name="category" id="editCat">
                    <option value="news">📢 News</option>
                    <option value="tips">💡 Farmer Tips</option>
                    <option value="market">📊 Market Update</option>
                    <option value="community">🤝 Community</option>
                </select>
            </div>
            <div class="pm-fg"><label>Content</label><textarea name="body" id="editBody" required></textarea></div>
            <button type="submit" class="pm-submit">Save Changes</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Article Modal -->
<div class="article-modal" id="articleModal" onclick="if(event.target===this)closeArticle()">
    <div class="article-modal-box" id="articleBox">
        <div class="amod-banner" id="amodBanner">
            <button class="amod-close" onclick="closeArticle()">×</button>
            <div id="amodEmoji" style="font-size:72px;line-height:1;margin-bottom:14px;position:relative;z-index:1;"></div>
            <div id="amodCat" style="color:rgba(255,255,255,0.6);font-size:11px;letter-spacing:3px;text-transform:uppercase;font-weight:700;margin-bottom:8px;position:relative;z-index:1;"></div>
            <div id="amodTitle" style="color:white;font-size:clamp(16px,3vw,24px);font-weight:700;line-height:1.3;position:relative;z-index:1;"></div>
        </div>
        <div class="amod-body">
            <div class="amod-meta" id="amodMeta"></div>
            <div class="amod-text" id="amodText"></div>
        </div>
        <div class="amod-share">
            <span>Share:</span>
            <a id="shareWa" href="#" target="_blank" class="share-wa"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
            <a id="shareFb" href="#" target="_blank" class="share-fb"><i class="fa-brands fa-facebook"></i> Facebook</a>
        </div>
    </div>
</div>

<!-- Article data for JS -->
<?php
$allPosts = $db->query("SELECT bp.*,u.name as author FROM blog_posts bp JOIN users u ON bp.admin_id=u.id ORDER BY bp.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$postsJson = json_encode(array_column($allPosts, null, 'id'));
?>
<script>
const POSTS = <?= $postsJson ?>;
const catColors = {
    news:      {from:'#1a237e', to:'#283593', label:'News'},
    tips:      {from:'#1b5e20', to:'#2e7d32', label:'Farmer Tips'},
    market:    {from:'#e65100', to:'#bf360c', label:'Market'},
    community: {from:'#006064', to:'#00838f', label:'Community'},
};

function openArticle(id) {
    const p = POSTS[id];
    if (!p) return;
    const cc = catColors[p.category] || catColors.news;

    document.getElementById('amodBanner').style.background = `linear-gradient(135deg,${cc.from},${cc.to})`;
    // Strip any HTML from emoji, show clean emoji only
    const rawEmoji = p.emoji || '📰';
    const cleanEmoji = rawEmoji.replace(/<[^>]*>/g, '').trim().substring(0, 4);
    document.getElementById('amodEmoji').textContent = cleanEmoji || '📰';
    document.getElementById('amodCat').textContent    = cc.label;
    document.getElementById('amodTitle').textContent  = p.title;
    document.getElementById('amodMeta').innerHTML     = `<span><i class="fa-solid fa-user"></i>${p.author}</span><span><i class="fa-solid fa-calendar"></i>${new Date(p.created_at).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'})}</span><span><i class="fa-solid fa-clock"></i>${Math.max(1,Math.ceil(p.body.split(' ').length/200))} min read</span>`;

    // Format body into paragraphs
    const paras = p.body.split('\n').filter(l => l.trim());
    document.getElementById('amodText').innerHTML = paras.map(l => `<p>${l}</p>`).join('');

    const url = encodeURIComponent(location.href.split('?')[0] + '?view=' + id);
    const title = encodeURIComponent(p.title);
    document.getElementById('shareWa').href = `https://wa.me/?text=${title}%20${url}`;
    document.getElementById('shareFb').href = `https://www.facebook.com/sharer/sharer.php?u=${url}`;

    document.getElementById('articleModal').classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeArticle() {
    document.getElementById('articleModal').classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key==='Escape') closeArticle(); });
</script>

<?php include 'includes/footer.php'; ?>

<script>
function openPostModal() { document.getElementById('createModal').classList.add('open'); }
function openEditModal(id, title, body, cat, emoji) {
    // Highlight correct emoji
    setTimeout(() => {
        document.getElementById('editEmoji').value = emoji;
        document.querySelectorAll('.emoji-opt-edit').forEach(b => {
            b.classList.toggle('selected', b.dataset.em === emoji);
        });
    }, 50);
    document.getElementById('editId').value    = id;
    document.getElementById('editTitle').value = title;
    document.getElementById('editBody').value  = body;
    document.getElementById('editEmoji').value = emoji;
    document.getElementById('editCat').value   = cat;
    document.getElementById('editModal').classList.add('open');
}
window.addEventListener('click', e => {
    if (e.target.id === 'createModal') e.target.classList.remove('open');
    if (e.target.id === 'editModal')   e.target.classList.remove('open');
});
function subscribeNewsletter() {
    const email = document.getElementById('nlEmail').value.trim();
    const msg = document.getElementById('nlMsg');
    if (!email || !email.includes('@')) { msg.style.color='#ffaaaa'; msg.textContent='Please enter a valid email.'; msg.style.display='block'; return; }
    msg.style.color='#cce8cc'; msg.textContent='✓ Subscribed! Thank you.'; msg.style.display='block';
    document.getElementById('nlEmail').value='';
}
</script>
</body>
</html>
