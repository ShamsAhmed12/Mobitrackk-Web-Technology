<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$generalErr = $successMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $cid = (int)($_POST['complaint_id'] ?? 0);
    $reply = cleanInput($_POST['reply'] ?? '');
    if ($cid <= 0) {
        $generalErr = "Invalid complaint ID.";
    } elseif ($reply === "") {
        $generalErr = "Reply cannot be empty.";
    } elseif ($conn === null) {
        $generalErr = ($db_error ?? "Database not available.") . " Start XAMPP MySQL.";
    } else {
        $stmt = $conn->prepare("UPDATE complaints SET reply=?, status='resolved' WHERE id=?");
        if (!$stmt) {
            $generalErr = "DB error: " . e($conn->error);
        } else {
            $stmt->bind_param("si", $reply, $cid);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                $successMsg = "Complaint #$cid resolved.";
            } else {
                $generalErr = "Update failed or already resolved. " . e($stmt->error);
            }
            $stmt->close();
        }
    }
}

// fetch complaints with customer name
$complaints = [];
if ($conn !== null) {
    $res = $conn->query("SELECT c.id, c.customer_id, c.message, c.status, c.reply, c.created_at, u.name AS customer_name, u.email AS customer_email FROM complaints c JOIN users u ON c.customer_id = u.id ORDER BY c.id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $complaints[] = $row;
    } else {
        $generalErr = $generalErr ?: "Failed to load complaints: " . e($conn->error);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <h1>Customer Complaints</h1>
        <p>List all complaints — reply and mark as resolved.</p>
    </div>

    <?php if ($generalErr): ?><div class="alert alert-error"><?php echo e($generalErr); ?></div><?php endif; ?>
    <?php if ($successMsg): ?><div class="alert alert-success"><?php echo e($successMsg); ?></div><?php endif; ?>

    <?php if ($conn === null): ?>
        <div class="alert alert-error">Database not available. Start XAMPP MySQL and reload.</div>
    <?php elseif (empty($complaints)): ?>
        <p class="muted">No complaints yet.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>ID</th><th>Customer</th><th>Message</th><th>Status</th><th>Reply</th><th>Date</th><th>Action</th></tr>
            <?php foreach ($complaints as $c): ?>
                <tr>
                    <td>#<?php echo e((string)$c['id']); ?></td>
                    <td><?php echo e($c['customer_name']); ?><br><span class="small muted"><?php echo e($c['customer_email']); ?></span></td>
                    <td><?php echo e($c['message']); ?></td>
                    <td><?php echo e($c['status']); ?></td>
                    <td><?php echo $c['reply'] ? e($c['reply']) : '<span class="muted">—</span>'; ?></td>
                    <td class="small"><?php echo e($c['created_at']); ?></td>
                    <td>
                        <?php if ($c['status'] === 'open'): ?>
                            <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF'])); ?>" novalidate>
                                <input type="hidden" name="complaint_id" value="<?php echo e((string)$c['id']); ?>">
                                <div class="field" style="margin-bottom:8px;">
                                    <textarea name="reply" rows="2" placeholder="Write reply..." style="min-height:60px; font-size:13px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-small">Resolve</button>
                            </form>
                        <?php else: ?>
                            <span class="small muted">Resolved</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Back to Dashboard</a> <a class="btn btn-secondary btn-small" href="notices.php">Notices</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
