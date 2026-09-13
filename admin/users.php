<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('admin');

$generalErr = $successMsg = "";
$filter_role = cleanInput($_GET['role'] ?? '');
$allowed_roles = ['admin','vendor','seller','customer'];
if ($filter_role!=='' && !in_array($filter_role,$allowed_roles,true)) $filter_role='';

// handle status toggle
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['user_id']) && isset($_POST['new_status'])) {
    $uid=(int)($_POST['user_id']??0);
    $ns = cleanInput($_POST['new_status']??'');
    if(!in_array($ns,['active','inactive'],true)) $generalErr="Invalid status.";
    elseif($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        // prevent self-deactivate?
        if($uid === (int)($_SESSION['user_id'] ?? 0)) $generalErr="Cannot change your own status.";
        else {
            $stmt=$conn->prepare("UPDATE users SET status=? WHERE id=?");
            if(!$stmt) $generalErr="DB error: ".e($conn->error);
            else { $stmt->bind_param("si",$ns,$uid); if($stmt->execute() && $stmt->affected_rows>=0) $successMsg="User #$uid status -> $ns."; else $generalErr="Update failed: ".e($stmt->error); $stmt->close(); }
        }
    }
}

$users=[];
if($conn!==null){
    if($filter_role!==''){
        $stmt=$conn->prepare("SELECT id,name,email,role,shop_name,status,created_at FROM users WHERE role=? ORDER BY id DESC");
        if($stmt){ $stmt->bind_param("s",$filter_role); $stmt->execute(); $res=$stmt->get_result(); while($row=$res->fetch_assoc()) $users[]=$row; $stmt->close(); }
    } else {
        $res=$conn->query("SELECT id,name,email,role,shop_name,status,created_at FROM users ORDER BY id DESC");
        if($res) while($row=$res->fetch_assoc()) $users[]=$row;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <div class="card-header"><h1>Manage Users</h1><p>View all users and toggle active/inactive status.</p></div>
    <?php if($generalErr):?><div class="alert alert-error"><?php echo e($generalErr);?></div><?php endif;?>
    <?php if($successMsg):?><div class="alert alert-success"><?php echo e($successMsg);?></div><?php endif;?>

    <p class="small">
        Filter:
        <a class="btn btn-secondary btn-small" href="users.php">All</a>
        <a class="btn btn-secondary btn-small" href="users.php?role=vendor">Vendors</a>
        <a class="btn btn-secondary btn-small" href="users.php?role=seller">Sellers</a>
        <a class="btn btn-secondary btn-small" href="users.php?role=customer">Customers</a>
        <a class="btn btn-secondary btn-small" href="users.php?role=admin">Admins</a>
        <?php if($filter_role):?> <span class="muted">showing: <?php echo e($filter_role);?></span> <?php endif;?>
    </p>

    <?php if($conn===null):?><div class="alert alert-error">Database not available.</div>
    <?php elseif(empty($users)):?><p class="muted">No users found.</p>
    <?php else:?>
        <table class="data-table">
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Shop</th><th>Status</th><th>Joined</th><th>Action</th></tr>
            <?php foreach($users as $u):?>
                <tr>
                    <td>#<?php echo e((string)$u['id']);?></td>
                    <td><?php echo e($u['name']);?></td>
                    <td class="small"><?php echo e($u['email']);?></td>
                    <td><?php echo e($u['role']);?></td>
                    <td><?php echo $u['shop_name']? e($u['shop_name']):'<span class="muted">—</span>';?></td>
                    <td><?php echo e($u['status']);?></td>
                    <td class="small"><?php echo e($u['created_at']);?></td>
                    <td>
                        <?php if((int)$u['id'] === (int)($_SESSION['user_id']??0)):?>
                            <span class="small muted">you</span>
                        <?php else:?>
                            <?php if($u['status']==='active'):?>
                                <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF'].'?role='.urlencode($filter_role)));?>" style="display:inline-block;" novalidate onsubmit="return confirm('Deactivate #<?php echo e((string)$u['id']);?> ?');">
                                    <input type="hidden" name="user_id" value="<?php echo e((string)$u['id']);?>"><input type="hidden" name="new_status" value="inactive"><button type="submit" class="btn btn-secondary btn-small">Deactivate</button>
                                </form>
                            <?php else:?>
                                <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF'].'?role='.urlencode($filter_role)));?>" style="display:inline-block;" novalidate>
                                    <input type="hidden" name="user_id" value="<?php echo e((string)$u['id']);?>"><input type="hidden" name="new_status" value="active"><button type="submit" class="btn btn-primary btn-small">Activate</button>
                                </form>
                            <?php endif;?>
                        <?php endif;?>
                    </td>
                </tr>
            <?php endforeach;?>
        </table>
    <?php endif;?>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Back to Dashboard</a> <a class="btn btn-secondary btn-small" href="profile.php">My Profile</a></p>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
