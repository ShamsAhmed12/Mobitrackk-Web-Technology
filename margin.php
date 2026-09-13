<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('seller');

$sid = (int)($_SESSION['user_id'] ?? 0);
$generalErr = $successMsg = "";

// handle add product + margin calc (no image)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='add') {
    $name = cleanInput($_POST['name'] ?? '');
    $cost = trim($_POST['cost_price'] ?? '');
    $sell = trim($_POST['selling_price'] ?? '');
    $stock = trim($_POST['stock_qty'] ?? '');
    $desc = cleanInput($_POST['description'] ?? '');
    if ($name==='') $generalErr="Name is required.";
    elseif ($cost==='' || !is_numeric($cost) || (float)$cost<=0) $generalErr="Valid cost price required (>0).";
    elseif ($sell==='' || !is_numeric($sell) || (float)$sell<=0) $generalErr="Valid selling price required (>0).";
    elseif ((float)$sell < (float)$cost) $generalErr="Selling price should be >= cost (or margin negative).";
    elseif ($stock==='' || !ctype_digit($stock)) $generalErr="Stock must be integer >=0.";
    elseif ($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        $costF=(float)$cost; $sellF=(float)$sell; $stockI=(int)$stock;
        $margin = $sellF>0 ? round((($sellF-$costF)/$sellF)*100,2) : null;

        if ($generalErr==="") {
            $stmt=$conn->prepare("INSERT INTO products (seller_id, name, selling_price, cost_price, profit_margin, stock_qty, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if(!$stmt) $generalErr="DB error: ".e($conn->error);
            else {
                $stmt->bind_param("isdddis", $sid, $name, $sellF, $costF, $margin, $stockI, $desc);
                if($stmt->execute()) $successMsg="Added '".e($name)."' — margin ".e(number_format($margin,2))."%";
                else $generalErr="Insert failed: ".e($stmt->error);
                $stmt->close();
            }
        }
    }
}

// handle delete product
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='delete') {
    $pid=(int)($_POST['product_id']??0);
    if($pid<=0) $generalErr="Invalid product.";
    elseif($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        $stmt=$conn->prepare("DELETE FROM products WHERE id=? AND seller_id=?");
        if(!$stmt) $generalErr="DB error: ".e($conn->error);
        else { $stmt->bind_param("ii",$pid,$sid); if($stmt->execute() && $stmt->affected_rows>0){ $successMsg="Deleted product #$pid"; } else $generalErr="Delete failed."; $stmt->close(); }
    }
}

$products=[];
if($conn!==null){
    $stmt=$conn->prepare("SELECT id, name, selling_price, cost_price, profit_margin, stock_qty FROM products WHERE seller_id=? ORDER BY id ASC");
    if($stmt){ $stmt->bind_param("i",$sid); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()) $products[]=$row; $stmt->close(); }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:700px; margin:0 auto;">
    <div class="card-header"><h1>Profit Margin Calculator</h1><p>Calculate profit margin instantly.</p></div>
    <?php if($generalErr):?><div class="alert alert-error"><?php echo e($generalErr);?></div><?php endif;?>
    <?php if($successMsg):?><div class="alert alert-success"><?php echo e($successMsg);?></div><?php endif;?>

    <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate>
        <input type="hidden" name="action" value="add">
        <div class="field"><label for="name">Product Name</label><input type="text" id="name" name="name" placeholder="e.g. Samsung A54 8/128"></div>
        <div class="col-half">
            <div class="field"><label for="cost_price">Cost Price (TK)</label><input type="number" step="0.01" min="0" id="cost_price" name="cost_price" placeholder="30000" oninput="calcMargin()"></div>
        </div>
        <div class="col-half">
            <div class="field"><label for="selling_price">Selling Price (TK)</label><input type="number" step="0.01" min="0" id="selling_price" name="selling_price" placeholder="35000" oninput="calcMargin()"></div>
        </div>
        <div class="clear"></div>
        <div class="alert alert-info" style="text-align:center;">Margin: <strong id="marginOut">—</strong> <span class="small muted" id="marginHint">(enter both prices)</span></div>
        <div class="field"><label for="stock_qty">Stock Qty</label><input type="number" min="0" step="1" id="stock_qty" name="stock_qty" placeholder="20" value="10"></div>
        <div class="field"><label for="description">Description (optional)</label><textarea id="description" name="description" rows="2" placeholder="Short description"></textarea></div>
        <button type="submit" class="btn btn-primary btn-block">Add Product</button>
    </form>
    <script>
        // plain JS calculator - slides DOM manipulation, no jQuery
        function calcMargin(){
            var cost = document.getElementById('cost_price').value;
            var sell = document.getElementById('selling_price').value;
            var out = document.getElementById('marginOut');
            var hint = document.getElementById('marginHint');
            cost = parseFloat(cost); sell = parseFloat(sell);
            if (isNaN(cost) || isNaN(sell) || sell<=0) { out.textContent='—'; hint.textContent='(enter both prices)'; return; }
            var m = ((sell - cost) / sell * 100);
            out.textContent = m.toFixed(2) + '%';
            if (m < 0) hint.textContent = '(loss!)';
            else if (m < 10) hint.textContent = '(low)';
            else if (m > 50) hint.textContent = '(high)';
            else hint.textContent = '(ok)';
        }
    </script>
</div>

<div class="card">
    <div class="card-header"><h2>Your Products</h2></div>
    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($products)):?><p class="muted">No products yet.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>ID</th><th>Name</th><th>Cost → Sell</th><th>Margin</th><th>Stock</th><th>Action</th></tr>
            <?php foreach($products as $p):?>
                <tr>
                    <td>#<?php echo e((string)$p['id']);?></td>
                    <td><?php echo e($p['name']);?></td>
                    <td>TK <?php echo e(number_format((float)$p['cost_price'],2));?> → TK <?php echo e(number_format((float)$p['selling_price'],2));?></td>
                    <td><?php echo $p['profit_margin']!==null ? e(number_format((float)$p['profit_margin'],2))."%" : '<span class="muted">—</span>';?></td>
                    <td><?php echo e((string)$p['stock_qty']);?></td>
                    <td>
                        <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate onsubmit="return confirm('Delete #<?php echo e((string)$p['id']);?> ?');">
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="product_id" value="<?php echo e((string)$p['id']);?>"><button type="submit" class="btn btn-secondary btn-small">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Dashboard</a> <a class="btn btn-secondary btn-small" href="stock.php">Stock</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
