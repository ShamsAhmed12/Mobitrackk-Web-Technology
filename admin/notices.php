<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$generalErr = $successMsg = "";
$message = "";
$target_role = "all";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = cleanInput($_POST['message'] ?? '');
    $target_role = cleanInput($_POST['target_role'] ?? 'all');
    $allowed = ['seller', 'vendor', 'all'];
    if (!in_array($target_role, $allowed, true)) $target_role = 'all';

    if ($message === "") {
        $generalErr = "Notice message cannot be empty.";
    } elseif (strlen($message) < 5) {
        $generalErr = "Message must be at least 5 characters.";
    } elseif ($conn === null) {
        $generalErr = ($db_error ?? "Database not available.") . " Start XAMPP MySQL.";
    } else {
        $stmt = $conn->prepare("INSERT INTO notices (admin_id, message, target_role) VALUES (?, ?, ?)");
        if (!$stmt) {
            $generalErr = "DB error: " . e($conn->error);
        } else {
            $admin_id = (int)$_SESSION['user_id'];
            $stmt->bind_param("iss", $admin_id, $message, $target_role);
            if ($stmt->execute()) {
                $displayTo = $target_role==='all' ? 'All(Sellers+Vendors)' : e($target_role);
                $successMsg = "Notice posted to '" . $displayTo . "'.";
                $message = "";
                $target_role = "all";
            } else {
                $generalErr = "Failed to post: " . e($stmt->error);
            }
            $stmt->close();
        }
    }
}

// fetch notices
$notices = [];
if ($conn !== null) {
    $res = $conn->query("SELECT n.id, n.message, n.target_role, n.created_at, u.name AS admin_name FROM notices n JOIN users u ON n.admin_id = u.id ORDER BY n.id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) $notices[] = $row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card" style="max-width:700px; margin: 0 auto;">
    <div class="card-header">
        <h1>Post Notice</h1>
        <p>Send a message to sellers, vendors, or everyone.</p>
    </div>

    <?php if ($generalErr): ?><div class="alert alert-error"><?php echo e($generalErr); ?></div><?php endif; ?>
    <?php if ($successMsg): ?><div class="alert alert-success"><?php echo e($successMsg); ?></div><?php endif; ?>

    <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF'])); ?>" novalidate>
        <div class="field">
            <label for="message">Notice Message</label>
            <textarea id="message" name="message" rows="4" placeholder="e.g. Shop will be closed on Sunday for stock audit"><?php echo e($message); ?></textarea>
        </div>
        <div class="field">
            <label for="target_role">To</label>
            <select id="target_role" name="target_role">
                <option value="all" <?php echo $target_role==='all'?'selected':''; ?>>All(Sellers+Vendors)</option>
                <option value="seller" <?php echo $target_role==='seller'?'selected':''; ?>>Sellers only</option>
                <option value="vendor" <?php echo $target_role==='vendor'?'selected':''; ?>>Vendors only</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Post Notice</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Notices</h2>
        <p>Newest first. Customers do not see these — sellers/vendors do.</p>
    </div>
    <?php if ($conn === null): ?>
        <div class="alert alert-error">Database not available.</div>
    <?php elseif (empty($notices)): ?>
        <p class="muted">No notices yet.</p>
    <?php else: ?>
        <table class="data-table">
            <tr><th>ID</th><th>Message</th><th>To</th><th>By</th><th>Date</th></tr>
            <?php foreach ($notices as $n): ?>
                <tr>
                    <td>#<?php echo e((string)$n['id']); ?></td>
                    <td><?php echo e($n['message']); ?></td>
                    <td><?php echo e($n['target_role']==='all' ? 'All(Sellers+Vendors)' : $n['target_role']); ?></td>
                    <td><?php echo e($n['admin_name']); ?></td>
                    <td class="small"><?php echo e($n['created_at']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Back to Dashboard</a> <a class="btn btn-secondary btn-small" href="complaints.php">Complaints</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
