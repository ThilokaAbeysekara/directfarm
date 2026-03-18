<?php

require_once 'includes/config.php';
$db = getDB();


$db->query("CREATE TABLE IF NOT EXISTS forum_posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT NOT NULL,
    category    VARCHAR(50) DEFAULT 'general',
    views       INT DEFAULT 0,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");
$db->query("CREATE TABLE IF NOT EXISTS forum_replies (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    post_id     INT NOT NULL,
    user_id     INT NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$msg    = '';
$action = $_GET['action'] ?? 'list';
$postId = (int)($_GET['post'] ?? 0);

// Categories (global scope)
$cats = ['all'=>'All','general'=>'General','farming'=>'🌾 Farming','prices'=>'💰 Prices','help'=>'🆘 Help','success'=>'🌟 Stories'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isLoggedIn()) {
    $act = $_POST['act'] ?? '';

    if ($act === 'new_post') {
        $title = clean($_POST['title'] ?? '');
        $body  = clean($_POST['body']  ?? '');
        $cat   = clean($_POST['category'] ?? 'general');
        if ($title && $body) {
            $stmt = $db->prepare("INSERT INTO forum_posts (user_id, title, body, category) VALUES (?,?,?,?)");
            $stmt->bind_param("isss", $_SESSION['user_id'], $title, $body, $cat);
            $stmt->execute();
            header("Location: forum.php");
            exit;
        }
        $msg = 'Please fill in both the title and message.';
    }

    if ($act === 'reply') {
        $pid  = (int)($_POST['post_id'] ?? 0);
        $body = clean($_POST['body'] ?? '');
        if ($pid && $body) {
            $stmt = $db->prepare("INSERT INTO forum_replies (post_id, user_id, body) VALUES (?,?,?)");
            $stmt->bind_param("iis", $pid, $_SESSION['user_id'], $body);
            $stmt->execute();
            header("Location: forum.php?action=view&post=$pid");
            exit;
        }
    }

    // Edit post
    if ($act === 'edit_post') {
        $pid   = (int)($_POST['post_id'] ?? 0);
        $title = clean($_POST['title'] ?? '');
        $body  = clean($_POST['body']  ?? '');
        $s = $db->prepare("UPDATE forum_posts SET title=?, body=? WHERE id=? AND user_id=?");
        if ($s) { $s->bind_param("ssii", $title, $body, $pid, $_SESSION['user_id']); $s->execute(); }
        header("Location: forum.php?action=view&post=$pid"); exit;
    }

    // Delete post
    if ($act === 'delete_post') {
        $pid = (int)($_POST['post_id'] ?? 0);
        $s = $db->prepare("DELETE FROM forum_posts WHERE id=? AND user_id=?");
        if ($s) { $s->bind_param("ii", $pid, $_SESSION['user_id']); $s->execute(); }
        header("Location: forum.php"); exit;
    }

    // Edit reply
    if ($act === 'edit_reply') {
        $rid  = (int)($_POST['reply_id'] ?? 0);
        $pid  = (int)($_POST['post_id']  ?? 0);
        $body = clean($_POST['body'] ?? '');
        $s = $db->prepare("UPDATE forum_replies SET body=? WHERE id=? AND user_id=?");
        if ($s) { $s->bind_param("sii", $body, $rid, $_SESSION['user_id']); $s->execute(); }
        header("Location: forum.php?action=view&post=$pid"); exit;
    }

    // Delete reply
    if ($act === 'delete_reply') {
        $rid = (int)($_POST['reply_id'] ?? 0);
        $pid = (int)($_POST['post_id']  ?? 0);
        $s = $db->prepare("DELETE FROM forum_replies WHERE id=? AND user_id=?");
        if ($s) { $s->bind_param("ii", $rid, $_SESSION['user_id']); $s->execute(); }
        header("Location: forum.php?action=view&post=$pid"); exit;
    }
}

// Fetch single post + replies
$singlePost = null;
$replies    = [];
if ($action === 'view' && $postId) {
    $db->query("UPDATE forum_posts SET views = views + 1 WHERE id = $postId");
    $stmt = $db->prepare("
        SELECT fp.*, u.name as author, u.role as author_role
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        WHERE fp.id = ?
    ");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $singlePost = $stmt->get_result()->fetch_assoc();

    $rstmt = $db->prepare("
        SELECT fr.*, u.name as author, u.role as author_role
        FROM forum_replies fr
        JOIN users u ON fr.user_id = u.id
        WHERE fr.post_id = ?
        ORDER BY fr.created_at ASC
    ");
    $rstmt->bind_param("i", $postId);
    $rstmt->execute();
    $replies = $rstmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch post list
$filterCat = clean($_GET['cat'] ?? 'all');
$posts = [];
if ($action === 'list') {
    $catWhere = $filterCat !== 'all' ? "AND fp.category = '$filterCat'" : '';
    $result = $db->query("
        SELECT fp.*, u.name as author, u.role as author_role,
               COUNT(fr.id) as reply_count
        FROM forum_posts fp
        JOIN users u ON fp.user_id = u.id
        LEFT JOIN forum_replies fr ON fp.id = fr.post_id
        WHERE 1=1 $catWhere
        GROUP BY fp.id
        ORDER BY fp.created_at DESC
    ");
    $posts = $result->fetch_all(MYSQLI_ASSOC);
}

//colors
$catColors = [
    'general'   => ['#e8f5e9','#2e7d32'],
    'farming'   => ['#fff8e1','#f57f17'],
    'prices'    => ['#fce4ec','#c62828'],
    'help'      => ['#e3f2fd','#1565c0'],
    'success'   => ['#f3e5f5','#6a1b9a'],
];
function catBadge($cat, $colors) {
    [$bg,$fg] = $colors[$cat] ?? ['#eee','#555'];
    return "<span style='display:inline-block;background:{$bg};color:{$fg};font-size:11px;font-weight:700;padding:3px 10px;border-radius:12px;text-transform:uppercase;letter-spacing:.4px;'>".ucfirst(htmlspecialchars($cat))."</span>";
}
function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return (int)($diff/60) . ' min ago';
    if ($diff < 86400)  return (int)($diff/3600) . ' hr ago';
    if ($diff < 604800) return (int)($diff/86400) . ' days ago';
    return date('M d, Y', strtotime($datetime));
}
function roleBadge($role) {
    if ($role === 'farmer') return '<span style="background:#e8f5e9;color:#2e7d32;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;">🌾 Farmer</span>';
    if ($role === 'admin')  return '<span style="background:#fce4ec;color:#c62828;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;">⚙️ Admin</span>';
    return '<span style="background:#e3f2fd;color:#1565c0;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;">🛒 Consumer</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Community Forum - DirectFarm LK</title>
    <link rel="stylesheet" href="style.css?v=4"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .forum-hero {
            background: linear-gradient(135deg, #0e1726 0%, #1a3a2a 60%, #56ad58 100%);
            color: white;
            padding: 60px 5%;
            text-align: center;
        }
        .forum-hero h1 { font-size: 36px; margin-bottom: 10px; }
        .forum-hero p  { color: #cce8cc; font-size: 15px; max-width: 540px; margin: 0 auto; }

        .forum-body { max-width: 1200px; margin: 0 auto; padding: 40px 20px; display: grid; grid-template-columns: 1fr 300px; gap: 30px; }

        /* ── Toolbar ── */
        .forum-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; }
        .cat-tabs { display: flex; gap: 8px; flex-wrap: wrap; }
        .cat-tab { padding: 7px 16px; border: 2px solid #56ad58; border-radius: 25px; background: white; color: #56ad58; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; transition: .2s; }
        .cat-tab:hover, .cat-tab.active { background: #56ad58; color: white; }
        .new-post-btn { background: #56ad58; color: white; border: none; padding: 10px 22px; border-radius: 25px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: .2s; }
        .new-post-btn:hover { background: #3f8e44; }

        /* ── Post List ── */
        .post-item {
            background: white;
            border-radius: 14px;
            padding: 20px 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.07);
            margin-bottom: 14px;
            display: flex;
            gap: 18px;
            align-items: flex-start;
            transition: .2s;
            text-decoration: none;
        }
        .post-item:hover { transform: translateX(4px); box-shadow: 0 4px 18px rgba(0,0,0,0.11); }
        .post-avatar { width: 46px; height: 46px; border-radius: 50%; background: linear-gradient(135deg,#56ad58,#0e1726); color: white; font-weight: 700; font-size: 16px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .post-main { flex: 1; min-width: 0; }
        .post-title { font-size: 15px; font-weight: 700; color: #1a2e1a; margin-bottom: 5px; }
        .post-excerpt { font-size: 13px; color: #888; line-height: 1.5; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .post-meta-row { display: flex; align-items: center; gap: 14px; font-size: 12px; color: #aaa; flex-wrap: wrap; }
        .post-stats { display: flex; flex-direction: column; align-items: center; gap: 6px; flex-shrink: 0; }
        .post-stats .stat { text-align: center; }
        .post-stats .num { font-size: 18px; font-weight: 800; color: #1a2e1a; line-height: 1; }
        .post-stats .lbl { font-size: 10px; color: #aaa; }

        /* ── Single Post View ── */
        .back-link { display: inline-flex; align-items: center; gap: 8px; color: #56ad58; font-size: 14px; font-weight: 600; text-decoration: none; margin-bottom: 20px; }
        .back-link:hover { text-decoration: underline; }
        .post-full { background: white; border-radius: 16px; padding: 32px; box-shadow: 0 4px 18px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .post-full h2 { font-size: 22px; color: #1a2e1a; margin-bottom: 14px; line-height: 1.4; }
        .post-full .body-text { color: #444; font-size: 15px; line-height: 1.8; white-space: pre-wrap; }
        .author-row { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
        .author-avatar { width: 42px; height: 42px; border-radius: 50%; background: linear-gradient(135deg,#56ad58,#0e1726); color: white; font-weight: 700; font-size: 15px; display: flex; align-items: center; justify-content: center; }
        .author-info .name { font-size: 14px; font-weight: 700; color: #222; }
        .author-info .time { font-size: 12px; color: #aaa; }

        /* ── Replies ── */
        .replies-section h3 { font-size: 17px; color: #1a2e1a; margin-bottom: 18px; }
        .reply-item { background: white; border-radius: 12px; padding: 18px 22px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin-bottom: 12px; display: flex; gap: 14px; }
        .reply-body { flex: 1; }
        .reply-body .text { font-size: 14px; color: #444; line-height: 1.7; white-space: pre-wrap; }
        .reply-body .meta { font-size: 12px; color: #aaa; margin-top: 6px; }
        .reply-form { background: white; border-radius: 14px; padding: 22px; box-shadow: 0 2px 10px rgba(0,0,0,0.07); margin-top: 20px; }
        .reply-form h4 { font-size: 15px; margin-bottom: 14px; color: #1a2e1a; }
        .reply-form textarea { width: 100%; padding: 12px 14px; border: 1.5px solid #eee; border-radius: 10px; font-size: 14px; resize: vertical; min-height: 100px; font-family: inherit; transition: .2s; }
        .reply-form textarea:focus { outline: none; border-color: #56ad58; }
        .reply-form .submit-btn2 { margin-top: 12px; background: #56ad58; color: white; border: none; padding: 11px 28px; border-radius: 10px; font-size: 14px; font-weight: 700; cursor: pointer; transition: .2s; }
        .reply-form .submit-btn2:hover { background: #3f8e44; }

        /* ── New Post Modal ── */
        .modal-overlay2 { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 999; align-items: center; justify-content: center; }
        .modal-overlay2.open { display: flex; }
        .new-post-modal { background: white; border-radius: 18px; padding: 36px; width: 100%; max-width: 580px; box-shadow: 0 10px 40px rgba(0,0,0,.2); position: relative; }
        .new-post-modal h2 { font-size: 20px; margin-bottom: 20px; color: #1a2e1a; }
        .fg { margin-bottom: 16px; }
        .fg label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .fg input, .fg textarea, .fg select { width: 100%; padding: 11px 14px; border: 1.5px solid #eee; border-radius: 10px; font-size: 14px; font-family: inherit; background: #fafafa; transition: .2s; }
        .fg input:focus, .fg textarea:focus, .fg select:focus { outline: none; border-color: #56ad58; background: white; }
        .fg textarea { resize: vertical; min-height: 120px; }
        .modal-close { position: absolute; top: 16px; right: 20px; font-size: 22px; cursor: pointer; color: #aaa; background: none; border: none; }
        .modal-close:hover { color: #333; }
        .alert-err { background: #ffe0e0; color: #c0392b; padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }

        /* ── Sidebar ── */
        .sidebar-box { background: white; border-radius: 14px; padding: 22px; box-shadow: 0 3px 12px rgba(0,0,0,.07); margin-bottom: 20px; }
        .sidebar-box h3 { font-size: 15px; font-weight: 700; color: #1a2e1a; margin-bottom: 14px; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px; }
        .sidebar-box ul { list-style: none; padding: 0; margin: 0; }
        .sidebar-box ul li { padding: 8px 0; border-bottom: 1px solid #f5f5f5; font-size: 13px; color: #444; display: flex; justify-content: space-between; }
        .sidebar-box ul li:last-child { border-bottom: none; }
        .sidebar-box ul li span { background: #e8f5e9; color: #2e7d32; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; }
        .rules-list { padding: 0; margin: 0; list-style: none; }
        .rules-list li { font-size: 13px; color: #555; padding: 6px 0; border-bottom: 1px solid #f5f5f5; display: flex; gap: 8px; }
        .rules-list li:last-child { border-bottom: none; }

        .login-prompt { background: #f0f7f0; border-radius: 12px; padding: 20px; text-align: center; margin-bottom: 16px; }
        .login-prompt p { font-size: 14px; color: #555; margin-bottom: 12px; }
        .login-prompt a { background: #56ad58; color: white; padding: 10px 24px; border-radius: 25px; text-decoration: none; font-size: 14px; font-weight: 700; }

        @media(max-width: 900px) { .forum-body { grid-template-columns: 1fr; } }
        .btn-edit-small { background: #e8f5e9; color: #2e7d32; border: none; padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition:.2s; }
        .btn-edit-small:hover { background: #009933; color: white; }
        .btn-delete-small { background: #fce4e4; color: #c0392b; border: none; padding: 5px 12px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; transition:.2s; }
        .btn-delete-small:hover { background: #e74c3c; color: white; }
        .edit-modal { display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center; }
        .edit-modal.open { display:flex; }
        .edit-modal-box { background:white; border-radius:16px; padding:28px; width:90%; max-width:540px; position:relative; }
        .edit-modal-box h3 { margin-bottom:16px; font-size:17px; }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<div class="forum-hero">
    <div style="font-size:50px; margin-bottom:14px;">💬</div>
    <h1>Community Forum</h1>
    <p>Ask questions, share knowledge, and connect with farmers and consumers across Sri Lanka.</p>
</div>

<div class="forum-body">
    <div>

<?php if ($action === 'view' && $singlePost): ?>

        
        <a href="forum.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Forum</a>

        <div class="post-full">
            <div style="margin-bottom:16px;"><?= catBadge($singlePost['category'], $catColors) ?></div>
            <h2><?= htmlspecialchars($singlePost['title']) ?></h2>
            <div class="author-row">
                <div class="author-avatar"><?= strtoupper(substr($singlePost['author'],0,1)) ?></div>
                <div class="author-info">
                    <div class="name"><?= htmlspecialchars($singlePost['author']) ?><?= roleBadge($singlePost['author_role']) ?></div>
                    <div class="time"><?= timeAgo($singlePost['created_at']) ?> · <?= $singlePost['views'] ?> views</div>
                </div>
            </div>
            <div class="body-text"><?= htmlspecialchars($singlePost['body']) ?></div>
            <?php if (isLoggedIn() && $_SESSION['user_id'] == $singlePost['user_id']): ?>
            <div style="margin-top:16px; display:flex; gap:10px;">
                <button onclick="openEditPost(<?= $singlePost['id'] ?>, '<?= addslashes(htmlspecialchars($singlePost['title'])) ?>', '<?= addslashes(htmlspecialchars($singlePost['body'])) ?>')" class="btn-edit-small">✏️ Edit Post</button>
                <form method="POST" onsubmit="return confirm('Delete this post and all its replies?')">
                    <input type="hidden" name="act" value="delete_post">
                    <input type="hidden" name="post_id" value="<?= $singlePost['id'] ?>">
                    <button type="submit" class="btn-delete-small">🗑️ Delete Post</button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- Replies -->
        <div class="replies-section">
            <h3><i class="fa-solid fa-comments"></i> <?= count($replies) ?> <?= count($replies) === 1 ? 'Reply' : 'Replies' ?></h3>

            <?php if (empty($replies)): ?>
            <div style="text-align:center; padding:30px; color:#aaa; background:white; border-radius:14px; box-shadow:0 2px 8px rgba(0,0,0,.06);">
                <i class="fa-solid fa-comment-slash" style="font-size:32px; margin-bottom:10px; display:block;"></i>
                No replies yet. Be the first to respond!
            </div>
            <?php else: ?>
            <?php foreach ($replies as $r): ?>
            <div class="reply-item">
                <div class="author-avatar" style="width:38px;height:38px;font-size:13px;flex-shrink:0;"><?= strtoupper(substr($r['author'],0,1)) ?></div>
                <div class="reply-body">
                    <div style="font-size:13px; font-weight:700; color:#222; margin-bottom:5px;">
                        <?= htmlspecialchars($r['author']) ?><?= roleBadge($r['author_role']) ?>
                    </div>
                    <div class="text" id="reply-text-<?= $r['id'] ?>"><?= htmlspecialchars($r['body']) ?></div>
                    <div class="meta" style="display:flex; align-items:center; gap:12px;">
                        <span><?= timeAgo($r['created_at']) ?></span>
                        <?php if (isLoggedIn() && $_SESSION['user_id'] == $r['user_id']): ?>
                        <button onclick="openEditReply(<?= $r['id'] ?>, <?= $singlePost['id'] ?>, '<?= addslashes(htmlspecialchars($r['body'])) ?>')" class="btn-edit-small">✏️ Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this reply?')">
                            <input type="hidden" name="act" value="delete_reply">
                            <input type="hidden" name="reply_id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="post_id" value="<?= $singlePost['id'] ?>">
                            <button type="submit" class="btn-delete-small">🗑️ Delete</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
            <div class="reply-form">
                <h4>✍️ Write a Reply</h4>
                <form method="POST">
                    <input type="hidden" name="act" value="reply">
                    <input type="hidden" name="post_id" value="<?= $singlePost['id'] ?>">
                    <textarea name="body" placeholder="Share your thoughts or answer…" required></textarea>
                    <button type="submit" class="submit-btn2">Post Reply</button>
                </form>
            </div>
            <?php else: ?>
            <div class="login-prompt">
                <p>You need to be logged in to reply.</p>
                <a href="#" onclick="openAuth('login')">Login to Reply</a>
            </div>
            <?php endif; ?>
        </div>

<?php else: ?>

        <div class="forum-toolbar">
            <div class="cat-tabs">
                <?php
                foreach ($cats as $k => $label):
                    $active = ($filterCat === $k) ? 'active' : '';
                ?>
                <a href="forum.php?cat=<?= $k ?>" class="cat-tab <?= $active ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>
            <?php if (isLoggedIn()): ?>
            <button class="new-post-btn" onclick="document.getElementById('newPostModal').classList.add('open')">
                <i class="fa-solid fa-plus"></i> New Post
            </button>
            <?php else: ?>
            <a href="#" onclick="openAuth('login')" class="new-post-btn"><i class="fa-solid fa-plus"></i> New Post</a>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?>
        <div class="alert-err"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
        <div style="text-align:center; padding:60px; color:#aaa; background:white; border-radius:16px;">
            <i class="fa-solid fa-comments" style="font-size:44px; margin-bottom:14px; display:block;"></i>
            No posts yet in this category. Be the first to start a discussion!
        </div>
        <?php else: ?>
        <?php foreach ($posts as $post): ?>
        <a href="forum.php?action=view&post=<?= $post['id'] ?>" class="post-item">
            <div class="post-avatar"><?= strtoupper(substr($post['author'],0,1)) ?></div>
            <div class="post-main">
                <div style="margin-bottom:6px;"><?= catBadge($post['category'], $catColors) ?></div>
                <div class="post-title"><?= htmlspecialchars($post['title']) ?></div>
                <div class="post-excerpt"><?= htmlspecialchars($post['body']) ?></div>
                <div class="post-meta-row">
                    <span><?= htmlspecialchars($post['author']) ?><?= roleBadge($post['author_role']) ?></span>
                    <span><i class="fa-solid fa-clock"></i> <?= timeAgo($post['created_at']) ?></span>
                    <span><i class="fa-solid fa-eye"></i> <?= $post['views'] ?> views</span>
                </div>
            </div>
            <div class="post-stats">
                <div class="stat"><div class="num"><?= $post['reply_count'] ?></div><div class="lbl">Replies</div></div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>

<?php endif; ?>
    </div>

    <!-- ── SIDEBAR-->
    <div>
        <?php
        $totalPosts   = $db->query("SELECT COUNT(*) as c FROM forum_posts")->fetch_assoc()['c'];
        $totalReplies = $db->query("SELECT COUNT(*) as c FROM forum_replies")->fetch_assoc()['c'];
        $totalMembers = $db->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];
        ?>
        <div class="sidebar-box" style="text-align:center;">
            <h3>📊 Forum Stats</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-top:10px;">
                <div><div style="font-size:24px;font-weight:800;color:#56ad58;"><?= $totalPosts ?></div><div style="font-size:11px;color:#aaa;">Posts</div></div>
                <div><div style="font-size:24px;font-weight:800;color:#56ad58;"><?= $totalReplies ?></div><div style="font-size:11px;color:#aaa;">Replies</div></div>
                <div><div style="font-size:24px;font-weight:800;color:#56ad58;"><?= $totalMembers ?></div><div style="font-size:11px;color:#aaa;">Members</div></div>
            </div>
        </div>

        <?php if (!isLoggedIn()): ?>
        <div class="login-prompt">
            <p>Join the conversation — login or register to post.</p>
            <a href="#" onclick="openAuth('login')">Login / Register</a>
        </div>
        <?php endif; ?>

        <div class="sidebar-box">
            <h3>🔥 Popular Topics</h3>
            <ul>
                <?php
                $popular = $db->query("SELECT title, views, id FROM forum_posts ORDER BY views DESC LIMIT 5");
                if ($popular && $popular->num_rows > 0):
                    while ($p = $popular->fetch_assoc()):
                ?>
                <li>
                    <a href="forum.php?action=view&post=<?= $p['id'] ?>" style="color:#333;text-decoration:none;font-size:13px;"><?= htmlspecialchars(substr($p['title'],0,38)) ?>…</a>
                    <span><?= $p['views'] ?> views</span>
                </li>
                <?php endwhile; else: ?>
                <li style="color:#aaa; font-size:13px;">No posts yet.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="sidebar-box">
            <h3>📋 Forum Rules</h3>
            <ul class="rules-list">
                <li><span style="color:#56ad58;">✓</span> Be respectful to all members</li>
                <li><span style="color:#56ad58;">✓</span> Keep posts relevant to farming</li>
                <li><span style="color:#56ad58;">✓</span> No spam or advertisements</li>
                <li><span style="color:#56ad58;">✓</span> Use Sinhala, Tamil, or English</li>
                <li><span style="color:#56ad58;">✓</span> Share knowledge generously</li>
            </ul>
        </div>

        <div class="sidebar-box">
            <h3>📂 Categories</h3>
            <ul>
                <?php foreach ($cats as $k => $label): if ($k === 'all') continue;
                    $count = $db->query("SELECT COUNT(*) as c FROM forum_posts WHERE category='$k'")->fetch_assoc()['c'];
                ?>
                <li>
                    <a href="forum.php?cat=<?= $k ?>" style="color:#444;text-decoration:none;"><?= $label ?></a>
                    <span><?= $count ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>

<!-- NEW POST MODAL-->
<?php if (isLoggedIn()): ?>
<div class="modal-overlay2" id="newPostModal">
    <div class="new-post-modal">
        <button class="modal-close" onclick="document.getElementById('newPostModal').classList.remove('open')">&times;</button>
        <h2>✍️ Start a New Discussion</h2>
        <form method="POST">
            <input type="hidden" name="act" value="new_post">
            <div class="fg">
                <label>Title <span style="color:#e74c3c;">*</span></label>
                <input type="text" name="title" placeholder="e.g. Best price for carrots in Kandy?" required>
            </div>
            <div class="fg">
                <label>Category</label>
                <select name="category">
                    <option value="general">General</option>
                    <option value="farming">🌾 Farming</option>
                    <option value="prices">💰 Prices</option>
                    <option value="help">🆘 Help</option>
                    <option value="success">🌟 Success Story</option>
                </select>
            </div>
            <div class="fg">
                <label>Message <span style="color:#e74c3c;">*</span></label>
                <textarea name="body" placeholder="Share your question, experience, or tip…" required></textarea>
            </div>
            <button type="submit" class="submit-btn2" style="width:100%;background:#56ad58;color:white;border:none;padding:13px;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer;">Post Discussion</button>
        </form>
    </div>
</div>
<script>
window.addEventListener('click', e => {
    const modal = document.getElementById('newPostModal');
    if (e.target === modal) modal.classList.remove('open');
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<!-- Edit Post Modal -->
<div class="edit-modal" id="editPostModal">
    <div class="edit-modal-box">
        <button class="modal-close" onclick="document.getElementById('editPostModal').classList.remove('open')">×</button>
        <h3>✏️ Edit Post</h3>
        <form method="POST">
            <input type="hidden" name="act" value="edit_post">
            <input type="hidden" name="post_id" id="editPostId">
            <div class="fg"><label>Title</label><input type="text" name="title" id="editPostTitle" required></div>
            <div class="fg"><label>Body</label><textarea name="body" id="editPostBody" rows="5" required></textarea></div>
            <button type="submit" class="submit-btn2">Save Changes</button>
        </form>
    </div>
</div>

<!-- Edit Reply Modal -->
<div class="edit-modal" id="editReplyModal">
    <div class="edit-modal-box">
        <button class="modal-close" onclick="document.getElementById('editReplyModal').classList.remove('open')">×</button>
        <h3>✏️ Edit Reply</h3>
        <form method="POST">
            <input type="hidden" name="act" value="edit_reply">
            <input type="hidden" name="reply_id" id="editReplyId">
            <input type="hidden" name="post_id" id="editReplyPostId">
            <div class="fg"><label>Reply</label><textarea name="body" id="editReplyBody" rows="4" required></textarea></div>
            <button type="submit" class="submit-btn2">Save Changes</button>
        </form>
    </div>
</div>

<script>
function openEditPost(id, title, body) {
    document.getElementById('editPostId').value    = id;
    document.getElementById('editPostTitle').value = title;
    document.getElementById('editPostBody').value  = body;
    document.getElementById('editPostModal').classList.add('open');
}
function openEditReply(rid, pid, body) {
    document.getElementById('editReplyId').value     = rid;
    document.getElementById('editReplyPostId').value = pid;
    document.getElementById('editReplyBody').value   = body;
    document.getElementById('editReplyModal').classList.add('open');
}
window.addEventListener('click', e => {
    if (e.target.id === 'editPostModal')  e.target.classList.remove('open');
    if (e.target.id === 'editReplyModal') e.target.classList.remove('open');
});
</script>
</body>
</html>
