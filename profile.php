<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_role('seller');

$uid = (int)($_SESSION['user_id'] ?? 0);
$generalErr = $successMsg = "";
$name=$email=$shop_name=$address="";
if ($conn!==null && $uid>0) {
    $stmt=$conn->prepare("SELECT name,email,shop_name,address FROM users WHERE id=?");
    if($stmt){ $stmt->bind_param("i",$uid); $stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc(); if($row){ $name=$row['name']; $email=$row['email']; $shop_name=$row['shop_name']??""; $address=$row['address']??""; } $stmt->close(); }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='profile') {
    $n_name = cleanInput($_POST['name'] ?? '');
    $n_email = cleanInput($_POST['email'] ?? '');
    $n_shop = cleanInput($_POST['shop_name'] ?? '');
    $n_addr = cleanInput($_POST['address'] ?? '');
    $errName=$errEmail="";
    if($n_name==='') $errName="Name is required";
    elseif(!preg_match("/^[a-zA-Z-' ]+$/",$n_name)) $errName="Only letters, spaces, hyphen and apostrophe allowed";
    if($n_email==='') $errEmail="Email is required";
    elseif(!is_valid_email($n_email)) $errEmail="Invalid email format";
    if($errName) $generalErr=$errName;
    elseif($errEmail) $generalErr=$errEmail;
    elseif($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        $chk=$conn->prepare("SELECT id FROM users WHERE email=? AND id<>?");
        if($chk){ $chk->bind_param("si",$n_email,$uid); $chk->execute(); $chk->store_result(); if($chk->num_rows>0){ $generalErr="Email already used."; $chk->close(); } else { $chk->close();
            $stmt=$conn->prepare("UPDATE users SET name=?, email=?, shop_name=?, address=? WHERE id=?");
            if(!$stmt) $generalErr="DB error: ".e($conn->error);
            else { $stmt->bind_param("ssssi",$n_name,$n_email,$n_shop,$n_addr,$uid); if($stmt->execute()){ $successMsg="Profile updated."; $name=$n_name; $email=$n_email; $shop_name=$n_shop; $address=$n_addr; $_SESSION['name']=$n_name; $_SESSION['email']=$n_email; } else $generalErr="Update failed: ".e($stmt->error); $stmt->close(); }
        }}
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action']) && $_POST['action']==='password') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if($cur==='') $generalErr="Current password is required.";
    elseif($new==='') $generalErr="New password is required.";
    elseif(strlen($new)<8) $generalErr="New password must be at least 8 characters.";
    elseif($new!==$confirm) $generalErr="New passwords do not match.";
    elseif($conn===null) $generalErr=($db_error??"Database not available.");
    else {
        $stmt=$conn->prepare("SELECT password FROM users WHERE id=?");
        if(!$stmt) $generalErr="DB error: ".e($conn->error);
        else { $stmt->bind_param("i",$uid); $stmt->execute(); $res=$stmt->get_result(); $row=$res->fetch_assoc(); $stmt->close();
            if(!$row || !password_verify($cur,$row['password'])) $generalErr="Current password is incorrect.";
            else { $hash=password_hash($new,PASSWORD_DEFAULT); $stmt=$conn->prepare("UPDATE users SET password=? WHERE id=?"); if(!$stmt) $generalErr="DB error: ".e($conn->error); else { $stmt->bind_param("si",$hash,$uid); if($stmt->execute()) $successMsg="Password changed."; else $generalErr="Update failed: ".e($stmt->error); $stmt->close(); } }
        }
    }
}
require_once __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:650px; margin:0 auto;">
    <div class="card-header"><h1>Seller Profile</h1><p>Update your basic information.</p></div>
    <?php if($generalErr):?><div class="alert alert-error"><?php echo e($generalErr);?></div><?php endif;?>
    <?php if($successMsg):?><div class="alert alert-success"><?php echo e($successMsg);?></div><?php endif;?>
    <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate>
        <input type="hidden" name="action" value="profile">
        <div class="field"><label for="name">Full Name</label><input type="text" id="name" name="name" value="<?php echo e($name);?>"></div>
        <div class="field"><label for="email">Email Address</label><input type="email" id="email" name="email" value="<?php echo e($email);?>"></div>
        <div class="field"><label for="shop_name">Shop Name (optional)</label><input type="text" id="shop_name" name="shop_name" value="<?php echo e($shop_name);?>"></div>
        <div class="field"><label for="address">Address (optional)</label><textarea id="address" name="address" rows="2"><?php echo e($address);?></textarea></div>
        <button type="submit" class="btn btn-primary btn-block">Update Profile</button>
    </form>
</div>
<div class="card" style="max-width:650px; margin:0 auto;">
    <div class="card-header"><h2>Change Password</h2></div>
    <form method="post" action="<?php echo e(htmlspecialchars($_SERVER['PHP_SELF']));?>" novalidate>
        <input type="hidden" name="action" value="password">
        <div class="field"><label for="cur">Current Password</label><input type="password" id="cur" name="current_password"></div>
        <div class="field"><label for="new">New Password (min 8)</label><input type="password" id="new" name="new_password"></div>
        <div class="field"><label for="confirm">Confirm New Password</label><input type="password" id="confirm" name="confirm_password"></div>
        <button type="submit" class="btn btn-primary btn-block">Change Password</button>
    </form>
    <p class="mt"><a class="btn btn-secondary btn-small" href="dashboard.php">Back to Dashboard</a></p>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
