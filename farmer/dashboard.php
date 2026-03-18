<?php
require_once '../includes/config.php';
requireRole('farmer', '../index.php');

$db  = getDB();
$uid = $_SESSION['user_id'];
$msg = '';

$action = $_POST['action'] ?? '';

if ($action === 'add_product') {
    $name=$_POST['name']??''; $category=$_POST['category']??''; $desc=$_POST['description']??'';
    $price=(float)($_POST['price']??0); $unit=$_POST['unit']??'kg';
    $stock=(float)($_POST['stock']??0); $district=$_POST['district']??''; $image='';
    if (!empty($_FILES['image']['name'])) {
        $uploadDir='../uploads/products/'; if(!is_dir($uploadDir))mkdir($uploadDir,0777,true);
        $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','webp'])){
            $image='uploads/products/'.uniqid().".$ext";
            move_uploaded_file($_FILES['image']['tmp_name'],"../$image");
        }
    }
    $stmt=$db->prepare("INSERT INTO products (farmer_id,name,category,description,price,unit,stock,district,image) VALUES (?,?,?,?,?,?,?,?,?)");
    $stmt->bind_param("isssdsdss",$uid,$name,$category,$desc,$price,$unit,$stock,$district,$image);
    $stmt->execute();
    header('Location: dashboard.php?tab=products&added=1');
    exit;
}
if ($action === 'update_product') {
    $pid=(int)($_POST['product_id']??0); $name=clean($_POST['name']??'');
    $price=(float)($_POST['price']??0); $stock=(float)($_POST['stock']??0);
    $desc=clean($_POST['description']??''); $avail=(int)isset($_POST['is_available']);
    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $uploadDir='../uploads/products/';
        if(!is_dir($uploadDir)) mkdir($uploadDir,0777,true);
        $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
        if(in_array($ext,['jpg','jpeg','png','webp'])) {
            $newImage='uploads/products/'.uniqid().".$ext";
            move_uploaded_file($_FILES['image']['tmp_name'],"../$newImage");
            $stmt=$db->prepare("UPDATE products SET name=?,price=?,stock=?,description=?,is_available=?,image=? WHERE id=? AND farmer_id=?");
            if($stmt){$stmt->bind_param("sddsssii",$name,$price,$stock,$desc,$avail,$newImage,$pid,$uid);$stmt->execute();}
            $msg='success:Product updated with new image!';
        }
    } else {
        $stmt=$db->prepare("UPDATE products SET name=?,price=?,stock=?,description=?,is_available=? WHERE id=? AND farmer_id=?");
        if($stmt){$stmt->bind_param("sddsiii",$name,$price,$stock,$desc,$avail,$pid,$uid);$stmt->execute();}
        $msg='success:Product updated!';
    }
}
if ($action === 'delete_product') {
    $pid=(int)($_POST['product_id']??0);
    $stmt=$db->prepare("DELETE FROM products WHERE id=? AND farmer_id=?");
    $stmt->bind_param("ii",$pid,$uid); $stmt->execute(); $msg='success:Product deleted.';
}
if ($action === 'update_order_status') {
    $orderId=(int)($_POST['order_id']??0); $status=clean($_POST['status']??'');
    if(in_array($status,['confirmed','processing','shipped','delivered','cancelled'])){
        $check=$db->prepare("SELECT DISTINCT o.id FROM orders o JOIN order_items oi ON o.id=oi.order_id WHERE o.id=? AND oi.farmer_id=?");
        $check->bind_param("ii",$orderId,$uid); $check->execute();
        if($check->get_result()->num_rows>0){
            $upd=$db->prepare("UPDATE orders SET status=? WHERE id=?");
            if($upd){$upd->bind_param("si",$status,$orderId);$upd->execute();}
            $msg='success:Order status updated.';
        }
    }
}
// PROFILE PHOTO UPLOAD
if ($action === 'upload_photo') {
    if (!empty($_FILES['profile_photo']['name'])) {
        $uploadDir = '../uploads/profiles/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            // Delete old photo
            $old = $db->query("SELECT profile_pic FROM users WHERE id=$uid")->fetch_assoc()['profile_pic'];
            if ($old && file_exists("../$old")) unlink("../$old");
            // Save new
            $filename = 'uploads/profiles/' . $uid . '_' . time() . ".$ext";
            move_uploaded_file($_FILES['profile_photo']['tmp_name'], "../$filename");
            $stmt = $db->prepare("UPDATE users SET profile_pic=? WHERE id=?");
            $stmt->bind_param("si", $filename, $uid);
            $stmt->execute();
            $msg = 'success:Profile photo updated!';
        }
    }
}

if ($action === 'update_profile') {
    $name=clean($_POST['name']??''); $phone=clean($_POST['phone']??'');
    $district=clean($_POST['district']??''); $address=clean($_POST['address']??'');
    $stmt=$db->prepare("UPDATE users SET name=?,phone=?,district=?,address=? WHERE id=?");
    $stmt->bind_param("ssssi",$name,$phone,$district,$address,$uid);
    $stmt->execute(); $_SESSION['user_name']=$name; $msg='success:Profile updated!';
}
if ($action === 'send_message') {
    $rid=(int)($_POST['receiver_id']??0); $message=clean($_POST['message']??'');
    if($rid&&$message){
        $stmt=$db->prepare("INSERT INTO messages (sender_id,receiver_id,message) VALUES (?,?,?)");
        $stmt->bind_param("iis",$uid,$rid,$message); $stmt->execute(); $msg='success:Message sent!';
    }
}

$user         = $db->query("SELECT * FROM users WHERE id=$uid")->fetch_assoc();
$products     = $db->query("SELECT p.*,COALESCE(AVG(r.rating),0) as avg_rating FROM products p LEFT JOIN reviews r ON r.product_id=p.id WHERE p.farmer_id=$uid GROUP BY p.id ORDER BY p.created_at DESC");
$orders       = $db->query("SELECT o.*,u.name as consumer_name,COUNT(oi.id) as item_count,SUM(oi.subtotal) as farmer_total FROM orders o JOIN order_items oi ON o.id=oi.order_id JOIN users u ON o.consumer_id=u.id WHERE oi.farmer_id=$uid GROUP BY o.id ORDER BY o.created_at DESC");
$messages     = $db->query("SELECT m.*,u.name as sender_name,u.id as sender_id,u.profile_pic as sender_pic FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid ORDER BY m.sent_at DESC");
$unread       = $db->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND is_read=0")->fetch_assoc()['c'];
$db->query("UPDATE messages SET is_read=1 WHERE receiver_id=$uid");
$totalRevenue = $db->query("SELECT COALESCE(SUM(oi.subtotal),0) as r FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.farmer_id=$uid AND o.status!='cancelled'")->fetch_assoc()['r'];
$totalOrders  = $db->query("SELECT COUNT(DISTINCT o.id) as c FROM orders o JOIN order_items oi ON o.id=oi.order_id WHERE oi.farmer_id=$uid")->fetch_assoc()['c'];
$productCount = $db->query("SELECT COUNT(*) as c FROM products WHERE farmer_id=$uid AND is_available=1")->fetch_assoc()['c'];
$pendingOrders= $db->query("SELECT COUNT(DISTINCT o.id) as c FROM orders o JOIN order_items oi ON o.id=oi.order_id WHERE oi.farmer_id=$uid AND o.status='pending'")->fetch_assoc()['c'];

$tab = $_GET['tab'] ?? 'products';
if (isset($_GET['added'])) $msg = 'success:Product added successfully!';
$editProduct = null;
if (isset($_GET['edit'])) {
    $stmt=$db->prepare("SELECT * FROM products WHERE id=? AND farmer_id=?");
    $editId=(int)$_GET['edit']; $stmt->bind_param("ii",$editId,$uid);
    $stmt->execute(); $editProduct=$stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Farmer Dashboard - DirectFarm LK</title>
    <link rel="stylesheet" href="../style.css?v=5"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <style>
        :root { --green:#009933; --dark:#0e1726; --mid:#1a3a2a; --sidebar-w:260px; }
        * { box-sizing:border-box; }
        body { background:#f3f6f3; font-family:'DM Sans',sans-serif; }
        .dash-layout { display:flex; min-height:calc(100vh - 65px); }

        /* SIDEBAR */
        .sidebar { width:var(--sidebar-w); background:var(--dark); flex-shrink:0; display:flex; flex-direction:column; position:sticky; top:65px; height:calc(100vh - 65px); overflow-y:auto; }
        .sb-profile { padding:28px 24px 22px; border-bottom:1px solid rgba(255,255,255,0.06); }
        .sb-avatar { width:52px; height:52px; border-radius:50%; background:linear-gradient(135deg,var(--green),#006622); display:flex; align-items:center; justify-content:center; font-family:'DM Sans',sans-serif; font-size:20px; font-weight:700; color:white; margin-bottom:12px; }
        .sb-name { color:white; font-weight:600; font-size:15px; margin-bottom:2px; }
        .sb-role { color:rgba(255,255,255,0.4); font-size:11px; letter-spacing:1px; text-transform:uppercase; }
        .sb-nav { padding:12px 0; flex:1; }
        .sb-section { color:rgba(255,255,255,0.25); font-size:10px; letter-spacing:2px; text-transform:uppercase; padding:16px 24px 6px; }
        .sb-link { display:flex; align-items:center; gap:12px; padding:12px 24px; color:rgba(255,255,255,0.6); text-decoration:none; font-size:13px; font-weight:500; transition:all 0.2s; position:relative; }
        .sb-link:hover { color:white; background:rgba(255,255,255,0.05); }
        .sb-link.active { color:white; background:rgba(0,153,51,0.18); }
        .sb-link.active::before { content:''; position:absolute; left:0; top:0; bottom:0; width:3px; background:var(--green); border-radius:0 2px 2px 0; }
        .sb-link i { width:16px; font-size:14px; }
        .sb-badge { margin-left:auto; background:#e74c3c; color:white; border-radius:50px; padding:2px 8px; font-size:10px; font-weight:700; }
        .sb-logout { padding:16px 24px; border-top:1px solid rgba(255,255,255,0.06); }
        .sb-logout button { background:none; border:none; color:rgba(255,100,100,0.7); cursor:pointer; font-size:13px; font-family:'DM Sans',sans-serif; display:flex; align-items:center; gap:10px; padding:0; transition:color 0.2s; }
        .sb-logout button:hover { color:#ff6b6b; }

        /* MAIN */
        .dash-main { flex:1; padding:32px; overflow-x:hidden; }

        /* TOPBAR */
        .dash-topbar { background:linear-gradient(135deg,var(--dark) 0%,var(--mid) 60%,rgba(0,153,51,0.3) 100%); border-radius:20px; padding:28px 32px; margin-bottom:28px; display:flex; align-items:center; justify-content:space-between; position:relative; overflow:hidden; }
        .dash-topbar::before { content:'🌱'; position:absolute; right:24px; top:-10px; font-size:100px; opacity:0.07; }
        .dash-topbar h2 { font-family:'DM Sans',sans-serif; font-size:26px; color:white; font-weight:700; margin-bottom:4px; }
        .dash-topbar p { color:rgba(255,255,255,0.55); font-size:14px; margin:0; }
        .topbar-right { display:flex; flex-direction:column; align-items:flex-end; gap:8px; }
        .topbar-date { color:rgba(255,255,255,0.4); font-size:12px; letter-spacing:0.5px; margin-bottom:8px; }
        .topbar-add-btn { background:var(--green); color:white; padding:10px 20px; border-radius:50px; text-decoration:none; font-size:13px; font-weight:600; display:inline-flex; align-items:center; gap:7px; transition:background 0.2s; }
        .topbar-add-btn:hover { background:#00b33c; }

        /* STATS */
        .stat-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:28px; }
        .stat-card { background:white; border-radius:16px; padding:22px 20px; box-shadow:0 2px 12px rgba(0,0,0,0.05); border:1.5px solid #eef2ee; transition:transform 0.2s, box-shadow 0.2s; cursor:default; }
        .stat-card:hover { transform:translateY(-4px); box-shadow:0 8px 28px rgba(0,0,0,0.09); }
        .stat-icon { width:44px; height:44px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; margin-bottom:14px; }
        .stat-icon.green  { background:#e8f5e9; }
        .stat-icon.blue   { background:#e3f2fd; }
        .stat-icon.orange { background:#fff3e0; }
        .stat-icon.purple { background:#f3e5f5; }
        .stat-num   { font-family:'DM Sans',sans-serif; font-size:26px; font-weight:700; color:var(--dark); line-height:1; margin-bottom:4px; }
        .stat-label { font-size:12px; color:#999; letter-spacing:0.5px; }

        /* PANEL */
        .panel { background:white; border-radius:18px; box-shadow:0 2px 12px rgba(0,0,0,0.05); border:1.5px solid #eef2ee; overflow:hidden; margin-bottom:24px; }
        .panel-head { padding:20px 24px; border-bottom:1px solid #f5f5f5; display:flex; align-items:center; justify-content:space-between; }
        .panel-head h3 { font-family:'DM Sans',sans-serif; font-size:18px; color:var(--dark); font-weight:700; margin:0; }

        /* TABLE */
        .dash-table { width:100%; border-collapse:collapse; }
        .dash-table th { background:#fafbfa; padding:13px 18px; text-align:left; font-size:11px; font-weight:700; color:#aaa; letter-spacing:0.5px; text-transform:uppercase; border-bottom:1px solid #f0f0f0; }
        .dash-table td { padding:14px 18px; font-size:13px; color:#444; border-bottom:1px solid #f8f8f8; vertical-align:middle; }
        .dash-table tr:last-child td { border-bottom:none; }
        .dash-table tr:hover td { background:#fafcfa; }

        /* BADGES */
        .badge { padding:4px 12px; border-radius:50px; font-size:11px; font-weight:700; }
        .badge-pending    { background:#fff8e1; color:#f57f17; }
        .badge-confirmed  { background:#e3f2fd; color:#1565c0; }
        .badge-processing { background:#fce4ec; color:#c62828; }
        .badge-shipped    { background:#e0f2f1; color:#00695c; }
        .badge-delivered  { background:#e8f5e9; color:#2e7d32; }
        .badge-cancelled  { background:#fce4e4; color:#c62828; }
        .badge-active     { background:#e8f5e9; color:#2e7d32; }
        .badge-hidden     { background:#f5f5f5; color:#aaa; }

        /* PRODUCT THUMB */
        .prod-thumb { width:46px; height:46px; object-fit:cover; border-radius:10px; border:1.5px solid #eee; }

        /* BUTTONS */
        .btn-sm { padding:6px 14px; border:none; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600; font-family:'DM Sans',sans-serif; display:inline-flex; align-items:center; gap:5px; transition:all 0.15s; }
        .btn-edit   { background:#e8f5e9; color:#2e7d32; }
        .btn-edit:hover { background:#c8e6c9; }
        .btn-delete { background:#fce4e4; color:#c62828; }
        .btn-delete:hover { background:#ffcdd2; }
        .btn-primary { background:var(--green); color:white; border:none; padding:13px 32px; border-radius:50px; font-size:14px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:background 0.2s, transform 0.2s; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary:hover { background:#00b33c; transform:scale(1.02); }
        .view-link { color:var(--green); font-size:13px; font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px; transition:gap 0.2s; white-space:nowrap; }
        .view-link:hover { gap:8px; }

        /* FORM */
        .form-wrap { padding:28px; }
        .form-grid2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:0; }
        .fg { display:flex; flex-direction:column; gap:6px; margin-bottom:18px; }
        .fg label { font-size:11px; font-weight:700; color:#999; letter-spacing:0.5px; text-transform:uppercase; }
        .fg input, .fg select, .fg textarea { padding:12px 14px; border:1.5px solid #eee; border-radius:12px; font-size:14px; background:#fafafa; font-family:'DM Sans',sans-serif; transition:border-color 0.2s; outline:none; }
        .fg input:focus, .fg select:focus, .fg textarea:focus { border-color:var(--green); background:white; }
        .fg input[readonly] { background:#f5f5f5; color:#aaa; cursor:not-allowed; }
        .file-upload { border:2px dashed #ddd; border-radius:12px; padding:20px; text-align:center; cursor:pointer; transition:border-color 0.2s; background:#fafafa; }
        .file-upload:hover { border-color:var(--green); }
        .file-upload input { display:none; }
        .file-upload-label { color:#aaa; font-size:13px; cursor:pointer; }

        /* MESSAGES */
        .msg-item { padding:20px 24px; border-bottom:1px solid #f5f5f5; display:flex; gap:14px; align-items:flex-start; }
        .msg-item:last-child { border-bottom:none; }
        .msg-avatar { width:40px; height:40px; border-radius:50%; background:linear-gradient(135deg,var(--green),#006622); display:flex; align-items:center; justify-content:center; color:white; font-size:15px; font-weight:700; flex-shrink:0; font-family:'DM Sans',sans-serif; }
        .msg-body { flex:1; }
        .msg-name { font-weight:700; font-size:14px; color:var(--dark); margin-bottom:4px; }
        .msg-text { font-size:13px; color:#555; line-height:1.6; margin-bottom:8px; }
        .msg-time { font-size:11px; color:#bbb; }
        .reply-row { display:flex; gap:8px; margin-top:10px; }
        .reply-input { flex:1; padding:9px 14px; border:1.5px solid #eee; border-radius:50px; font-size:13px; font-family:'DM Sans',sans-serif; outline:none; transition:border-color 0.2s; }
        .reply-input:focus { border-color:var(--green); }
        .reply-btn { background:var(--green); color:white; border:none; padding:9px 18px; border-radius:50px; font-size:13px; font-weight:600; cursor:pointer; font-family:'DM Sans',sans-serif; transition:background 0.2s; white-space:nowrap; }
        .reply-btn:hover { background:#00b33c; }

        /* STATUS DROPDOWN inline */
        .status-select { padding:6px 10px; border:1.5px solid #eee; border-radius:8px; font-size:12px; font-family:'DM Sans',sans-serif; outline:none; background:#fafafa; cursor:pointer; }

        /* ALERT */
        .alert { padding:13px 18px; border-radius:12px; margin-bottom:22px; font-size:14px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success { background:#e8f5e9; color:#2e7d32; border:1px solid #c8e6c9; }
        .alert-error   { background:#fce4e4; color:#c62828; border:1px solid #ffcdd2; }

        /* EMPTY */
        .empty-panel { text-align:center; padding:60px 20px; }
        .empty-panel .e-icon { font-size:52px; opacity:0.25; display:block; margin-bottom:16px; }
        .empty-panel p { color:#bbb; font-size:14px; margin-bottom:18px; }
        .empty-panel a { background:var(--green); color:white; padding:10px 24px; border-radius:50px; text-decoration:none; font-size:13px; font-weight:600; }

        /* EDIT PANEL highlight */
        .panel-edit { border-color:var(--green) !important; }
        .panel-edit .panel-head { background:linear-gradient(135deg,var(--dark),var(--mid)); }
        .panel-edit .panel-head h3 { color:white; }

        /* TOGGLE */
        .toggle-wrap { display:flex; align-items:center; gap:10px; padding:10px 0; }
        .toggle-wrap input[type=checkbox] { width:18px; height:18px; accent-color:var(--green); cursor:pointer; }
        .toggle-wrap label { font-size:14px; color:#555; cursor:pointer; }
    </style>
</head>
<body>
<?php include '../includes/navbar.php'; ?>

<div class="dash-layout">
    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sb-profile">
            <div class="sb-avatar-wrap" style="position:relative;width:60px;height:60px;margin-bottom:12px;">
            <?php
                $picPath = !empty($user['profile_pic']) ? '../'.$user['profile_pic'] : '';
                if ($picPath && file_exists($picPath)): ?>
                <img src="<?= '../'.htmlspecialchars($user['profile_pic']) ?>?t=<?= time() ?>" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.2);" alt="Profile">
            <?php else: ?>
                <div class="sb-avatar"><?= strtoupper(substr($_SESSION['user_name'],0,1)) ?></div>
            <?php endif; ?>
            <label for="quickPhotoInput" style="position:absolute;bottom:0;right:0;width:20px;height:20px;background:var(--green);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--dark);">
                <i class="fa-solid fa-camera" style="font-size:8px;color:white;"></i>
            </label>
        </div>
        <!-- Quick photo upload -->
        <form method="POST" enctype="multipart/form-data" id="quickPhotoForm" style="display:none;">
            <input type="hidden" name="action" value="upload_photo">
            <input type="file" id="quickPhotoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="document.getElementById('quickPhotoForm').submit()">
        </form>
            <div class="sb-name"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
            <div class="sb-role">🧑‍🌱 Farmer</div>
        </div>
        <nav class="sb-nav">
            <div class="sb-section">Products</div>
            <a href="dashboard.php?tab=products" class="sb-link <?= $tab==='products'?'active':'' ?>"><i class="fa-solid fa-seedling"></i> My Products</a>
            <a href="dashboard.php?tab=add"      class="sb-link <?= $tab==='add'     ?'active':'' ?>"><i class="fa-solid fa-plus"></i> Add Product</a>
            <div class="sb-section">Sales</div>
            <a href="dashboard.php?tab=orders"   class="sb-link <?= $tab==='orders'  ?'active':'' ?>"><i class="fa-solid fa-bag-shopping"></i> Orders <?php if($pendingOrders>0): ?><span class="sb-badge"><?= $pendingOrders ?></span><?php endif; ?></a>
            <div class="sb-section">Account</div>
            <a href="dashboard.php?tab=messages" class="sb-link <?= $tab==='messages'?'active':'' ?>">
                <i class="fa-solid fa-envelope"></i> Messages
                <?php if($unread>0): ?><span class="sb-badge"><?= $unread ?></span><?php endif; ?>
            </a>
            <a href="dashboard.php?tab=profile"  class="sb-link <?= $tab==='profile' ?'active':'' ?>"><i class="fa-solid fa-user-pen"></i> My Profile</a>
            <div class="sb-section">More</div>
            <a href="../marketplace.php"      class="sb-link"><i class="fa-solid fa-store"></i> Marketplace</a>
            <a href="../change_password.php"  class="sb-link"><i class="fa-solid fa-lock"></i> Change Password</a>
        </nav>
        <div class="sb-logout">
            <form method="POST" action="../includes/auth.php">
                <input type="hidden" name="action" value="logout">
                <button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Logout</button>
            </form>
        </div>
    </div>

    <!-- MAIN -->
    <div class="dash-main">

        <!-- TOPBAR -->
        <div class="dash-topbar">
            <div>
                <h2>Welcome, <?= htmlspecialchars(explode(' ',$_SESSION['user_name'])[0]) ?>! 🌱</h2>
                <p>Manage your farm products, orders and customers</p>
            </div>
            <div class="topbar-right">
                <div class="topbar-date"><?= date('l, F j, Y') ?></div>
            </div>
        </div>

        <!-- STATS -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fa-solid fa-seedling" style="color:#2e7d32;"></i></div>
                <div class="stat-num"><?= $productCount ?></div>
                <div class="stat-label">Active Products</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fa-solid fa-bag-shopping" style="color:#1565c0;"></i></div>
                <div class="stat-num"><?= $totalOrders ?></div>
                <div class="stat-label">Total Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fa-solid fa-clock" style="color:#e65100;"></i></div>
                <div class="stat-num"><?= $pendingOrders ?></div>
                <div class="stat-label">Pending Orders</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fa-solid fa-coins" style="color:#6a1b9a;"></i></div>
                <div class="stat-num" style="font-size:<?= strlen('Rs.'.number_format($totalRevenue,0))>10?'16px':'20px' ?>;">Rs. <?= number_format($totalRevenue,0) ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>

        <?php if ($msg): list($t,$m)=explode(':',$msg,2); ?>
        <div class="alert alert-<?= $t ?>"><i class="fa-solid fa-<?= $t==='success'?'circle-check':'circle-xmark' ?>"></i><?= htmlspecialchars($m) ?></div>
        <?php endif; ?>

        <!-- PRODUCTS TAB -->
        <?php if ($tab === 'products'): ?>

        <?php if ($editProduct): ?>
        <div class="panel panel-edit" style="margin-bottom:24px;">
            <div class="panel-head">
                <h3><i class="fa-solid fa-pen-to-square" style="font-size:14px;margin-right:6px;"></i> Edit: <?= htmlspecialchars($editProduct['name']) ?></h3>
                <a href="dashboard.php?tab=products" style="color:rgba(255,255,255,0.6);font-size:13px;text-decoration:none;">✕ Cancel</a>
            </div>
            <div class="form-wrap">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_product">
                    <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>">
                    <div class="form-grid2">
                        <div class="fg"><label>Product Name</label><input type="text" name="name" value="<?= htmlspecialchars($editProduct['name']) ?>" required></div>
                        <div class="fg"><label>Price (Rs.)</label><input type="number" name="price" value="<?= $editProduct['price'] ?>" step="0.01" required></div>
                        <div class="fg"><label>Stock (<?= $editProduct['unit'] ?>)</label><input type="number" name="stock" value="<?= $editProduct['stock'] ?>" step="0.1" required></div>
                        <div class="fg"><label>Visibility</label>
                            <div class="toggle-wrap">
                                <input type="checkbox" name="is_available" id="avail" <?= $editProduct['is_available']?'checked':'' ?>>
                                <label for="avail">Show in Marketplace</label>
                            </div>
                        </div>
                    </div>
                    <div class="fg"><label>Description</label><textarea name="description" rows="3"><?= htmlspecialchars($editProduct['description']) ?></textarea></div>
                    <!-- Image upload -->
                    <div class="fg" style="margin-top:4px;">
                        <label>Product Image</label>
                        <div style="display:flex;align-items:center;gap:16px;margin-top:6px;">
                            <?php if (!empty($editProduct['image']) && file_exists('../'.$editProduct['image'])): ?>
                            <img src="../<?= htmlspecialchars($editProduct['image']) ?>" style="width:72px;height:72px;object-fit:cover;border-radius:10px;border:1.5px solid #eee;" id="editImgPreview">
                            <?php else: ?>
                            <div style="width:72px;height:72px;border-radius:10px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:24px;" id="editImgPreview">🖼</div>
                            <?php endif; ?>
                            <div>
                                <label style="background:var(--green);color:white;padding:9px 20px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;text-transform:none;">
                                    <i class="fa-solid fa-upload"></i> Change Photo
                                    <input type="file" name="image" accept="image/*" style="display:none;"
                                        onchange="const r=new FileReader();r.onload=e=>{const p=document.getElementById('editImgPreview');p.outerHTML='<img src=''+e.target.result+'' style='width:72px;height:72px;object-fit:cover;border-radius:10px;border:1.5px solid #eee;' id='editImgPreview'>';};r.readAsDataURL(this.files[0])">
                                </label>
                                <div style="font-size:11px;color:#aaa;margin-top:5px;">JPG, PNG, WEBP — Leave blank to keep current</div>
                            </div>
                        </div>
                    </div>
                    <div style="display:flex;gap:12px;margin-top:18px;">
                        <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
                        <a href="dashboard.php?tab=products" style="background:#f0f0f0;color:#555;padding:13px 24px;border-radius:50px;text-decoration:none;font-size:14px;font-weight:600;">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <div class="panel">
            <div class="panel-head">
                <h3>📦 My Products</h3>
                <a href="dashboard.php?tab=add" style="background:var(--green);color:white;padding:8px 18px;border-radius:50px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;"><i class="fa-solid fa-plus" style="font-size:11px;"></i> Add New</a>
            </div>
            <?php if ($productCount == 0): ?>
            <div class="empty-panel"><span class="e-icon">🌱</span><p>You haven't added any products yet.</p><a href="dashboard.php?tab=add">Add First Product</a></div>
            <?php else: ?>
            <table class="dash-table">
                <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Rating</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php $products->data_seek(0); while ($p=$products->fetch_assoc()): ?>
                    <tr>
                        <td><img src="../<?= htmlspecialchars($p['image']??'placeholder.jpg') ?>" class="prod-thumb" alt=""></td>
                        <td><strong style="color:var(--dark);font-size:14px;"><?= htmlspecialchars($p['name']) ?></strong><br><small style="color:#bbb;"><?= htmlspecialchars($p['district']) ?></small></td>
                        <td><span style="background:#f0f7f0;color:var(--green);padding:3px 10px;border-radius:50px;font-size:11px;font-weight:700;"><?= ucfirst($p['category']) ?></span></td>
                        <td><strong>Rs. <?= number_format($p['price'],2) ?></strong><br><small style="color:#bbb;">/ <?= $p['unit'] ?></small></td>
                        <td><?= $p['stock'] ?> <small style="color:#bbb;"><?= $p['unit'] ?></small></td>
                        <td><?= $p['avg_rating']>0 ? '<span style="color:#f39c12;">⭐</span> '.number_format($p['avg_rating'],1) : '<span style="color:#ddd;">—</span>' ?></td>
                        <td><span class="badge badge-<?= $p['is_available']?'active':'hidden' ?>"><?= $p['is_available']?'Active':'Hidden' ?></span></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <a href="dashboard.php?tab=products&edit=<?= $p['id'] ?>" class="btn-sm btn-edit"><i class="fa-solid fa-pen" style="font-size:10px;"></i> Edit</a>
                                <form method="POST" onsubmit="return confirm('Delete this product?')" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <button type="submit" class="btn-sm btn-delete"><i class="fa-solid fa-trash" style="font-size:10px;"></i> Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- ADD PRODUCT TAB -->
        <?php elseif ($tab === 'add'): ?>
        <div class="panel">
            <div class="panel-head"><h3>➕ Add New Product</h3></div>
            <div class="form-wrap">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="form-grid2">
                        <div class="fg"><label>Product Name *</label><input type="text" name="name" placeholder="e.g. Fresh Carrots" required></div>
                        <div class="fg"><label>Category *</label>
                            <select name="category" required>
                                <option value="">Select Category</option>
                                <option value="vegetables">🥕 Vegetables</option>
                                <option value="fruits">🍎 Fruits</option>
                                <option value="grains">🌽 Grains</option>
                                <option value="spices">🌶 Spices</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="fg"><label>Price (Rs.) *</label><input type="number" name="price" step="0.01" min="1" placeholder="150.00" required></div>
                        <div class="fg"><label>Unit *</label>
                            <select name="unit">
                                <option value="kg">Kilogram (kg)</option>
                                <option value="g">Gram (g)</option>
                                <option value="piece">Piece</option>
                                <option value="bunch">Bunch</option>
                                <option value="liter">Liter</option>
                            </select>
                        </div>
                        <div class="fg"><label>Available Stock *</label><input type="number" name="stock" step="0.1" min="0" placeholder="100" required></div>
                        <div class="fg"><label>District *</label>
                            <select name="district" required>
                                <option value="">Select District</option>
                                <?php foreach (['Colombo','Gampaha','Kalutara','Kandy','Matale','Nuwara Eliya','Galle','Matara','Hambantota','Jaffna','Trincomalee','Batticaloa','Kurunegala','Puttalam','Anuradhapura','Polonnaruwa','Badulla','Monaragala','Ratnapura','Kegalle'] as $d): ?>
                                <option value="<?= $d ?>" <?= $user['district']===$d?'selected':'' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fg"><label>Description</label><textarea name="description" rows="4" placeholder="Describe your product, farming method, freshness, etc."></textarea></div>
                    <div class="fg">
                        <label>Product Image</label>
                        <div class="file-upload" onclick="document.getElementById('imgInput').click()">
                            <input type="file" id="imgInput" name="image" accept="image/*" onchange="previewImg(this)">
                            <div class="file-upload-label">
                                <i class="fa-solid fa-cloud-arrow-up" style="font-size:24px;color:#ddd;display:block;margin-bottom:8px;"></i>
                                <span id="imgLabel">Click to upload product image</span>
                            </div>
                            <img id="imgPreview" style="max-height:120px;border-radius:10px;margin-top:12px;display:none;">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" style="margin-top:8px;"><i class="fa-solid fa-plus"></i> Add Product</button>
                </form>
            </div>
        </div>

        <!-- ORDERS TAB -->
        <?php elseif ($tab === 'orders'): ?>
        <div class="panel">
            <div class="panel-head"><h3>🛒 Customer Orders</h3><span style="font-size:13px;color:#aaa;"><?= $totalOrders ?> total</span></div>
            <?php if ($totalOrders == 0): ?>
            <div class="empty-panel"><span class="e-icon">📦</span><p>No orders yet. Keep adding great products!</p></div>
            <?php else: ?>
            <table class="dash-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Items</th><th>Your Amount</th><th>Status</th><th>Update</th><th></th></tr></thead>
                <tbody>
                    <?php $orders->data_seek(0); while ($o=$orders->fetch_assoc()): ?>
                    <tr>
                        <td><strong style="color:var(--dark);">#<?= $o['id'] ?></strong></td>
                        <td style="font-weight:500;"><?= htmlspecialchars($o['consumer_name']) ?></td>
                        <td style="color:#aaa;font-size:12px;"><?= date('M d, Y', strtotime($o['created_at'])) ?></td>
                        <td><?= $o['item_count'] ?> item<?= $o['item_count']!=1?'s':'' ?></td>
                        <td><strong style="color:var(--green);">Rs. <?= number_format($o['farmer_total'],2) ?></strong></td>
                        <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                        <td>
                            <form method="POST" style="display:flex;gap:6px;align-items:center;">
                                <input type="hidden" name="action" value="update_order_status">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <select name="status" class="status-select">
                                    <?php foreach (['confirmed','processing','shipped','delivered','cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $o['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-sm btn-edit">Update</button>
                            </form>
                        </td>
                        <td><a href="order_detail.php?id=<?= $o['id'] ?>" class="view-link">View <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- MESSAGES TAB -->
        <?php elseif ($tab === 'messages'): ?>
        <div class="panel">
            <div class="panel-head"><h3>💬 Messages from Customers</h3>
                <?php if($unread>0): ?><span style="background:#e74c3c;color:white;padding:3px 12px;border-radius:50px;font-size:12px;font-weight:700;"><?= $unread ?> unread</span><?php endif; ?>
            </div>
            <?php if ($messages->num_rows === 0): ?>
            <div class="empty-panel"><span class="e-icon">✉️</span><p>No messages from customers yet.</p></div>
            <?php else: ?>
                <?php while ($m=$messages->fetch_assoc()): ?>
                <div class="msg-item">
                    <?php if (!empty($m['sender_pic']) && file_exists('../'.$m['sender_pic'])): ?>
                    <img src="../<?= htmlspecialchars($m['sender_pic']) ?>?t=<?= time() ?>" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e8f5e9;flex-shrink:0;">
                    <?php else: ?>
                    <div class="msg-avatar"><?= strtoupper(substr($m['sender_name'],0,1)) ?></div>
                    <?php endif; ?>
                    <div class="msg-body">
                        <div class="msg-name"><?= htmlspecialchars($m['sender_name']) ?></div>
                        <div class="msg-text"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                        <div class="msg-time"><?= date('M d, Y · g:i A', strtotime($m['sent_at'])) ?></div>
                        <form method="POST" class="reply-row">
                            <input type="hidden" name="action" value="send_message">
                            <input type="hidden" name="receiver_id" value="<?= $m['sender_id'] ?>">
                            <input type="text" name="message" class="reply-input" placeholder="Write a reply..." required>
                            <button type="submit" class="reply-btn"><i class="fa-solid fa-paper-plane" style="font-size:12px;"></i> Reply</button>
                        </form>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- PROFILE TAB-->
        <?php elseif ($tab === 'profile'): ?>
        <div class="panel">
            <div class="panel-head"><h3>👤 My Profile</h3></div>
            <div class="form-wrap">
                <!-- Photo upload section -->
                <div style="display:flex;align-items:center;gap:20px;margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid #f0f0f0;">
                    <div style="position:relative;">
                        <?php if (!empty($user['profile_pic']) && file_exists("../".$user['profile_pic'])): ?>
                            <img src="../<?= htmlspecialchars($user['profile_pic']) ?>?t=<?= time() ?>" style="width:80px;height:80px;border-radius:50%;object-fit:cover;border:3px solid #e8f5e9;" alt="Profile">
                        <?php else: ?>
                            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#f5a623,#e67e22);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;color:white;"><?= strtoupper(substr($user['name'],0,1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:600;color:var(--dark);margin-bottom:6px;">Profile Photo</div>
                        <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;">
                            <input type="hidden" name="action" value="upload_photo">
                            <label style="background:var(--green);color:white;padding:8px 18px;border-radius:50px;font-size:13px;font-weight:600;cursor:pointer;">
                                <i class="fa-solid fa-upload" style="margin-right:6px;"></i>Upload Photo
                                <input type="file" name="profile_photo" accept="image/*" style="display:none;" onchange="this.closest('form').submit()">
                            </label>
                            <span style="font-size:12px;color:#aaa;">JPG, PNG, WEBP</span>
                        </form>
                    </div>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid2">
                        <div class="fg"><label>Full Name</label><input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required></div>
                        <div class="fg"><label>Email</label><input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly></div>
                        <div class="fg"><label>Phone</label><input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']??'') ?>"></div>
                        <div class="fg"><label>District</label>
                            <select name="district">
                                <?php foreach (['Colombo','Gampaha','Kalutara','Kandy','Matale','Nuwara Eliya','Galle','Matara','Hambantota','Jaffna','Trincomalee','Batticaloa','Kurunegala','Puttalam','Anuradhapura','Polonnaruwa','Badulla','Monaragala','Ratnapura','Kegalle'] as $d): ?>
                                <option value="<?= $d ?>" <?= $user['district']===$d?'selected':'' ?>><?= $d ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="fg"><label>Farm Address / Description</label><textarea name="address" rows="3"><?= htmlspecialchars($user['address']??'') ?></textarea></div>
                    <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Changes</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /dash-main -->
</div><!-- /dash-layout -->

<?php include '../includes/footer.php'; ?>
<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('imgPreview').src = e.target.result;
            document.getElementById('imgPreview').style.display = 'block';
            document.getElementById('imgLabel').textContent = input.files[0].name;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>
