<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('seller');

$sid = (int)($_SESSION['user_id'] ?? 0);
$generalErr = $successMsg = "";

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['action']) && $_POST['action']==='add' && isset($_POST['vendor_product_id'])) {
        $vpid=(int)($_POST['vendor_product_id']??0);
        if($vpid<=0) $generalErr="Invalid product.";
        elseif($conn===null) $generalErr=($db_error??"Database not available.");
        else {
            $stmt=$conn->prepare("INSERT IGNORE INTO wishlist (seller_id, vendor_product_id) VALUES (?, ?)");
            if(!$stmt) $generalErr="DB error: ".e($conn->error);
            else { $stmt->bind_param("ii",$sid,$vpid); if($stmt->execute()){ if($stmt->affected_rows>0) $successMsg="Added to wishlist #$vpid"; else $generalErr="Already in wishlist."; } else $generalErr="Failed: ".e($stmt->error); $stmt->close(); }
        }
    } elseif (isset($_POST['action']) && $_POST['action']==='remove' && isset($_POST['wishlist_id'])) {
        $wid=(int)($_POST['wishlist_id']??0);
        if($wid<=0) $generalErr="Invalid wishlist item.";
        elseif($conn===null) $generalErr=($db_error??"Database not available.");
        else {
            $stmt=$conn->prepare("DELETE FROM wishlist WHERE id=? AND seller_id=?");
            if(!$stmt) $generalErr="DB error: ".e($conn->error);
            else { $stmt->bind_param("ii",$wid,$sid); if($stmt->execute() && $stmt->affected_rows>0) $successMsg="Removed from wishlist."; else $generalErr="Delete failed."; $stmt->close(); }
        }
    }
}

$wishlist=[];
$vendor_products=[];
if($conn!==null){
    $stmt=$conn->prepare("SELECT w.id AS wid, vp.id AS vpid, vp.name, vp.price, vp.return_policy, u.name AS vendor_name FROM wishlist w JOIN vendor_products vp ON w.vendor_product_id=vp.id JOIN users u ON vp.vendor_id=u.id WHERE w.seller_id=? ORDER BY w.id DESC");
    if($stmt){ $stmt->bind_param("i",$sid); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()) $wishlist[]=$row; $stmt->close(); }

    $res=$conn->query("SELECT vp.id, vp.name, vp.price, u.name AS vendor_name FROM vendor_products vp JOIN users u ON vp.vendor_id=u.id ORDER BY vp.id DESC LIMIT 20");
    if($res) while($row=$res->fetch_assoc()) $vendor_products[]=$row;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><h1>Wishlist</h1><p>Save vendor items to consider stocking later.</p></div>
    <?php if($generalErr):?><div class="alert alert-error"><?php echo e($generalErr);?></div><?php endif;?>
    <?php if($successMsg):?><div class="alert alert-success"><?php echo e($successMsg);?></div><?php endif;?>

    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($wishlist)):?><p class="muted">Wishlist empty. Add from vendor supply below.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>WID</th><th>Vendor Product</th><th>Price</th><th>Vendor</th><th>Policy</th><th>Remove</th></tr>
            <?php foreach($wishlist as $w):?>
                <tr>
                    <td>#<?php echo e((string)$w['wid']);?></td>
                    <td><?php echo e($w['name']);?></td>
                    <td>TK <?php echo e(number_format((float)$w['price'],2));?></td>
                    <td><?php echo e($w['vendor_name']);?></td>
                    <td class="small"><?php echo $w['return_policy']? e(mb_strimwidth($w['return_policy'],0,35,"...")) : '<span class="muted">—</span>';?></td>
                    <td>
                        <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate>
                            <input type="hidden" name="action" value="remove"><input type="hidden" name="wishlist_id" value="<?php echo e((string)$w['wid']);?>"><button type="submit" class="btn btn-secondary btn-small">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
</div>

<div class="card">
    <div class="card-header"><h2>Vendor Supply (pick to wishlist)</h2><p>Shows latest vendor items.</p></div>
    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($vendor_products)):?><p class="muted">No vendor products yet.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>ID</th><th>Name</th><th>Price</th><th>Vendor</th><th>Save</th></tr>
            <?php foreach($vendor_products as $vp):?>
                <tr>
                    <td>#<?php echo e((string)$vp['id']);?></td>
                    <td><?php echo e($vp['name']);?></td>
                    <td>TK <?php echo e(number_format((float)$vp['price'],2));?></td>
                    <td><?php echo e($vp['vendor_name']);?></td>
                    <td>
                        <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate>
                            <input type="hidden" name="action" value="add"><input type="hidden" name="vendor_product_id" value="<?php echo e((string)$vp['id']);?>"><button type="submit" class="btn btn-primary btn-small">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Dashboard</a> <a class="btn btn-secondary btn-small" href="stock.php">Stock</a> <a class="btn btn-secondary btn-small" href="margin.php">Margin</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
