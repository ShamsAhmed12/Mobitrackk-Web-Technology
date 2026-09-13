    <?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('seller');

$sid = (int)($_SESSION['user_id'] ?? 0);
$product_count = $low_stock = $pending_orders = $wishlist_count = 0;
$notices = [];

if ($conn !== null && $sid > 0) {
    $r = $conn->query("SELECT COUNT(*) AS c FROM products WHERE seller_id=$sid");
    if ($r) $product_count = (int)$r->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM products WHERE seller_id=$sid AND stock_qty <= 5");
    if ($r) $low_stock = (int)$r->fetch_assoc()['c'];
    // pending orders via order_items
    $res = $conn->query("SELECT COUNT(DISTINCT o.id) AS c FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE oi.seller_id=$sid AND o.delivery_status='pending'");
    if ($res) $pending_orders = (int)$res->fetch_assoc()['c'];
    $r = $conn->query("SELECT COUNT(*) AS c FROM wishlist WHERE seller_id=$sid");
    if ($r) $wishlist_count = (int)$r->fetch_assoc()['c'];
    $nr = $conn->query("SELECT message, created_at FROM notices WHERE target_role IN ('seller','all') ORDER BY id DESC LIMIT 3");
    if ($nr) while($row=$nr->fetch_assoc()) $notices[]=$row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><h1>Seller Dashboard</h1><p>Stock, orders, wishlist and profit tools.</p></div>
    <div class="stats">
        <div class="stat-box"><span class="num"><?php echo e((string)$product_count);?></span><span class="lbl">Products Listed</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$low_stock);?></span><span class="lbl">Low Stock (&le;5)</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$pending_orders);?></span><span class="lbl">Pending Orders</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$wishlist_count);?></span><span class="lbl">Wishlist Items</span></div>
    </div>
    <p class="text-center mt">
        <a class="btn btn-primary btn-small" href="stock.php">Stock & Orders</a>
        <a class="btn btn-primary btn-small" href="margin.php">Margin Calculator</a>
        <a class="btn btn-primary btn-small" href="wishlist.php">Wishlist</a>
        <a class="btn btn-secondary btn-small" href="profile.php">My Profile</a>
    </p>
</div>

<?php if (!empty($notices)): ?>
<div class="card">
    <div class="card-header"><h2>Notices from Admin</h2></div>
    <table class="data-table"><tr><th>Message</th><th>Date</th></tr>
        <?php foreach($notices as $n): ?><tr><td><?php echo e($n['message']);?></td><td class="small"><?php echo e($n['created_at']);?></td></tr><?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
