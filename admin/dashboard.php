<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

// metrics defaults
$total_sales = $total_revenue = $vendor_count = $seller_count = $customer_count = $order_count = $open_complaints = $notice_count = 0;
$recent_orders = [];

if ($conn !== null) {
    // total revenue and sales count from orders
    $q = $conn->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS rev FROM orders");
    if ($q) {
        $r = $q->fetch_assoc();
        $order_count = (int)($r['cnt'] ?? 0);
        $total_revenue = (float)($r['rev'] ?? 0);
        $total_sales = $order_count;
    }
    // vendor count active
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='vendor' AND status='active'");
    if ($res) $vendor_count = (int)$res->fetch_assoc()['c'];
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='seller' AND status='active'");
    if ($res) $seller_count = (int)$res->fetch_assoc()['c'];
    $res = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role='customer' AND status='active'");
    if ($res) $customer_count = (int)$res->fetch_assoc()['c'];
    $res = $conn->query("SELECT COUNT(*) AS c FROM complaints WHERE status='open'");
    if ($res) $open_complaints = (int)$res->fetch_assoc()['c'];
    $res = $conn->query("SELECT COUNT(*) AS c FROM notices");
    if ($res) $notice_count = (int)$res->fetch_assoc()['c'];

    // recent 5 orders
    $rq = $conn->query("SELECT o.id, o.total_amount, o.delivery_status, o.created_at, u.name AS customer_name FROM orders o JOIN users u ON o.customer_id=u.id ORDER BY o.id DESC LIMIT 5");
    if ($rq) {
        while ($row = $rq->fetch_assoc()) $recent_orders[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Admin Dashboard</h1>
        <p>Overview of the shop — revenue, users and system health.</p>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Sales Overview</h2>
        <p>Revenue and order totals.</p>
    </div>
    <div class="stats">
        <div class="stat-box"><span class="num"><?php echo e(number_format($total_revenue, 2)); ?></span><span class="lbl">Total Revenue (TK)</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$order_count); ?></span><span class="lbl">Total Orders</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$total_sales); ?></span><span class="lbl">Total Sales</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$notice_count); ?></span><span class="lbl">Notices Sent</span></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>User Overview</h2>
        <p>Active users by role and open support.</p>
    </div>
    <div class="stats">
        <div class="stat-box"><span class="num"><?php echo e((string)$vendor_count); ?></span><span class="lbl">Active Vendors</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$seller_count); ?></span><span class="lbl">Active Sellers</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$customer_count); ?></span><span class="lbl">Customers</span></div>
        <div class="stat-box"><span class="num"><?php echo e((string)$open_complaints); ?></span><span class="lbl">Open Complaints</span></div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Recent Orders</h2>
        <p>Last 5 orders across all customers.</p>
    </div>
    <?php if (empty($recent_orders)): ?>
        <p class="muted">No orders yet.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>ID</th><th>Customer</th><th>Amount</th><th>Status</th><th>Date</th></tr>
            <?php foreach ($recent_orders as $o): ?>
                <tr>
                    <td>#<?php echo e((string)$o['id']); ?></td>
                    <td><?php echo e($o['customer_name']); ?></td>
                    <td>TK <?php echo e(number_format((float)$o['total_amount'],2)); ?></td>
                    <td><?php echo e($o['delivery_status']); ?></td>
                    <td><?php echo e($o['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-header">
        <h2>Manage</h2>
        <p>Basic admin actions.</p>
    </div>
    <div style="text-align:center; font-size:0; white-space:nowrap;">
        <div style="display:inline-block; width:195px; height:132px; margin:6px; vertical-align:top; background:#fff; border:1px solid #dddbd4; border-radius:12px; padding:16px 12px; text-align:center; font-size:14px; position:relative; white-space:normal;">
            <div style="font-weight:700; color:#2b3a4f; margin-bottom:6px;">Users</div>
            <div class="small muted" style="min-height:30px; margin-bottom:0;">View and manage all users</div>
            <div style="position:absolute; bottom:14px; left:12px; right:12px;"><a class="btn btn-primary btn-small" href="users.php" style="display:block; width:auto; box-sizing:border-box;">Manage Users</a></div>
        </div>
        <div style="display:inline-block; width:195px; height:132px; margin:6px; vertical-align:top; background:#fff; border:1px solid #dddbd4; border-radius:12px; padding:16px 12px; text-align:center; font-size:14px; position:relative; white-space:normal;">
            <div style="font-weight:700; color:#2b3a4f; margin-bottom:6px;">Complaints</div>
            <div class="small muted" style="min-height:30px; margin-bottom:0;">Reply and resolve support</div>
            <div style="position:absolute; bottom:14px; left:12px; right:12px;"><a class="btn btn-primary btn-small" href="complaints.php" style="display:block; width:auto; box-sizing:border-box;">Manage Complaints</a></div>
        </div>
        <div style="display:inline-block; width:195px; height:132px; margin:6px; vertical-align:top; background:#fff; border:1px solid #dddbd4; border-radius:12px; padding:16px 12px; text-align:center; font-size:14px; position:relative; white-space:normal;">
            <div style="font-weight:700; color:#2b3a4f; margin-bottom:6px;">Notices</div>
            <div class="small muted" style="min-height:30px; margin-bottom:0;">Broadcast to sellers/vendors</div>
            <div style="position:absolute; bottom:14px; left:12px; right:12px;"><a class="btn btn-primary btn-small" href="notices.php" style="display:block; width:auto; box-sizing:border-box;">Post Notice</a></div>
        </div>
        <div style="display:inline-block; width:195px; height:132px; margin:6px; vertical-align:top; background:#fff; border:1px solid #dddbd4; border-radius:12px; padding:16px 12px; text-align:center; font-size:14px; position:relative; white-space:normal;">
            <div style="font-weight:700; color:#2b3a4f; margin-bottom:6px;">Profile</div>
            <div class="small muted" style="min-height:30px; margin-bottom:0;">Update your account</div>
            <div style="position:absolute; bottom:14px; left:12px; right:12px;"><a class="btn btn-secondary btn-small" href="profile.php" style="display:block; width:auto; box-sizing:border-box;">My Profile</a></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
