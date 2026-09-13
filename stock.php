<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('seller');

$sid = (int)($_SESSION['user_id'] ?? 0);
$generalErr = $successMsg = "";

// handle stock update
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['product_id']) && isset($_POST['stock_qty'])) {
    $pid=(int)($_POST['product_id']??0);
    $qty=trim($_POST['stock_qty']??'');
    if($pid<=0) $generalErr="Invalid product.";
    elseif($qty==='' || !ctype_digit($qty)) $generalErr="Stock must be integer >=0.";
    elseif($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        $q=(int)$qty;
        $stmt=$conn->prepare("UPDATE products SET stock_qty=? WHERE id=? AND seller_id=?");
        if(!$stmt) $generalErr="DB error: ".e($conn->error);
        else { $stmt->bind_param("iii",$q,$pid,$sid); if($stmt->execute() && $stmt->affected_rows>=0) $successMsg="Stock updated #$pid -> $q"; else $generalErr="Update failed."; $stmt->close(); }
    }
}

$products=[];
$pending=[];
if($conn!==null){
    $stmt=$conn->prepare("SELECT id, name, selling_price, cost_price, stock_qty, profit_margin FROM products WHERE seller_id=? ORDER BY id ASC");
    if($stmt){ $stmt->bind_param("i",$sid); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()) $products[]=$row; $stmt->close(); }

    $q=$conn->query("SELECT o.id AS order_id, o.total_amount, o.delivery_status, o.created_at, u.name AS customer_name, oi.qty, oi.price, p.name AS product_name FROM orders o JOIN order_items oi ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id JOIN users u ON o.customer_id=u.id WHERE oi.seller_id=$sid AND o.delivery_status='pending' ORDER BY o.id DESC LIMIT 20");
    if($q) while($row=$q->fetch_assoc()) $pending[]=$row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><h1>Stock Levels</h1><p>Update your product stock levels.</p></div>
    <?php if($generalErr):?><div class="alert alert-error"><?php echo e($generalErr);?></div><?php endif;?>
    <?php if($successMsg):?><div class="alert alert-success"><?php echo e($successMsg);?></div><?php endif;?>

    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($products)):?><p class="muted">No products yet. Add in <a href="margin.php">Margin</a>.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>ID</th><th>Name</th><th>Selling</th><th>Cost</th><th>Margin %</th><th>Stock</th><th>Update</th></tr>
            <?php foreach($products as $p): $low=(int)$p['stock_qty']<=5;?>
                <tr style="<?php echo $low?'background:#fff3d0;':'';?>">
                    <td>#<?php echo e((string)$p['id']);?></td>
                    <td><?php echo e($p['name']);?><?php echo $low?' <span class="small" style="color:#b36b00;">LOW</span>':'';?></td>
                    <td>TK <?php echo e(number_format((float)$p['selling_price'],2));?></td>
                    <td>TK <?php echo e(number_format((float)$p['cost_price'],2));?></td>
                    <td><?php echo $p['profit_margin']!==null ? e(number_format((float)$p['profit_margin'],2))."%" : '<span class="muted">—</span>';?></td>
                    <td><?php echo e((string)$p['stock_qty']);?></td>
                    <td>
                        <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" style="display:inline-block;" novalidate>
                            <input type="hidden" name="product_id" value="<?php echo e((string)$p['id']);?>">
                            <input type="number" min="0" step="1" name="stock_qty" value="<?php echo e((string)$p['stock_qty']);?>" style="width:80px; padding:6px; border:1px solid #cfd3da; border-radius:6px; font-size:13px;">
                            <button type="submit" class="btn btn-primary btn-small">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
</div>

<div class="card">
    <div class="card-header"><h2>Pending Orders</h2><p>Orders containing your products that are still pending.</p></div>
    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($pending)):?><p class="muted">No pending orders.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>Order #</th><th>Customer</th><th>Product</th><th>Qty × Price</th><th>Total</th><th>Status</th><th>Date</th></tr>
            <?php foreach($pending as $o):?>
                <tr>
                    <td>#<?php echo e((string)$o['order_id']);?></td>
                    <td><?php echo e($o['customer_name']);?></td>
                    <td><?php echo e($o['product_name']);?></td>
                    <td><?php echo e((string)$o['qty']);?> × TK <?php echo e(number_format((float)$o['price'],2));?></td>
                    <td>TK <?php echo e(number_format((float)$o['total_amount'],2));?></td>
                    <td><?php echo e($o['delivery_status']);?></td>
                    <td class="small"><?php echo e($o['created_at']);?></td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Dashboard</a> <a class="btn btn-secondary btn-small" href="margin.php">Margin</a> <a class="btn btn-secondary btn-small" href="wishlist.php">Wishlist</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
