<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// --- routing ---
$raw_role = $_GET['role'] ?? '';
$role = sanitize_role($raw_role);
$page = $_GET['page'] ?? 'login';
$allowed_pages = ['login', 'register', 'logout'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'login';
}

// helper: dashboard target per role
if (!function_exists('role_dashboard')) {
    function role_dashboard($r) {
        if ($r === 'admin') return 'admin/dashboard.php';
        if ($r === 'vendor') return 'vendor/dashboard.php';
        if ($r === 'seller') return 'seller/dashboard.php';
        if ($r === 'customer') return 'customer/browse.php';
        return 'index.php';
    }
}

// logout: works regardless of role / login state
if ($page === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    session_destroy();
    redirect('index.php');
}

// role is required for login/register (except logout)
if (!$role) {
    // default to customer login if no role supplied
    $role = 'customer';
}

// admin cannot register
if ($page === 'register' && $role === 'admin') {
    $page = 'login';
    $admin_block_msg = "Admin accounts are pre-seeded. Please contact the administrator.";
} else {
    $admin_block_msg = "";
}

// --- form state ---
$name = $email = $shop_name = "";
$nameErr = $emailErr = $passwordErr = $confirmErr = $shopErr = $loginErr = $generalErr = "";
$successMsg = "";

if ($admin_block_msg !== "") {
    $generalErr = $admin_block_msg;
}

// if already logged in, handle role switch — must logout first
if (is_logged_in()) {
    $cur = current_role();
    if ($role === $cur) {
        redirect(role_dashboard($cur));
    } else {
        $msg = "You are already logged in as '" . e($cur) . "'. Please logout to switch to " . e($role) . ".";
        $generalErr = $generalErr ? $generalErr . " " . $msg : $msg;
    }
}

// handle POST - validate first, then DB (so field errors show even without MySQL)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($page === 'register') {
            // only vendor/seller/customer allowed
            if ($role === 'admin') {
                $generalErr = "Admin registration is not allowed.";
            } else {
                // name
                if (empty($_POST['name'])) {
                    $nameErr = "Name is required";
                } else {
                    $name = cleanInput($_POST['name']);
                    if (!preg_match("/^[a-zA-Z-' ]+$/", $name)) {
                        $nameErr = "Only letters, spaces, hyphen and apostrophe allowed";
                    }
                }
                // email
                if (empty($_POST['email'])) {
                    $emailErr = "Email is required";
                } else {
                    $email = cleanInput($_POST['email']);
                    if (!is_valid_email($email)) {
                        $emailErr = "Invalid email format";
                    }
                }
                // shop_name optional but if provided validate
                $shop_name = isset($_POST['shop_name']) ? cleanInput($_POST['shop_name']) : "";
                if ($shop_name !== "" && strlen($shop_name) < 2) {
                    $shopErr = "Shop name must be at least 2 characters";
                }
                // password
                $password = $_POST['password'] ?? "";
                $confirm = $_POST['confirm'] ?? "";
                if (empty($password)) {
                    $passwordErr = "Password is required";
                } elseif (strlen($password) < 8) {
                    $passwordErr = "Password must be at least 8 characters";
                }
                if (empty($confirm)) {
                    $confirmErr = "Confirm password is required";
                } elseif ($password !== $confirm) {
                    $confirmErr = "Passwords do not match";
                }

                // if no validation errors, attempt DB insert
                if (!$nameErr && !$emailErr && !$passwordErr && !$confirmErr && !$shopErr) {
                    if ($conn === null) {
                        $generalErr = ($db_error ?? "Database not available.") . " Start XAMPP MySQL and reload.";
                    } else {
                    // check duplicate email using prepared statement
                    $chk = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    if (!$chk) {
                        $generalErr = "DB error: " . e($conn->error);
                    } else {
                        $chk->bind_param("s", $email);
                        $chk->execute();
                        $chk->store_result();
                        if ($chk->num_rows > 0) {
                            $emailErr = "Email already registered";
                            $chk->close();
                        } else {
                            $chk->close();
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            // choose column for shop_name/business_name based on role
                            if ($role === 'vendor') {
                                $ins = $conn->prepare("INSERT INTO users (name, email, password, role, shop_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
                                if (!$ins) {
                                    $generalErr = "DB error: " . e($conn->error);
                                } else {
                                    $ins->bind_param("sssss", $name, $email, $hash, $role, $shop_name);
                                    if ($ins->execute()) {
                                        $ins->close();
                                        $successMsg = "Registration successful. Please login.";
                                        $page = "login";
                                        $name = $email = $shop_name = "";
                                    } else {
                                        $generalErr = "Registration failed: " . e($ins->error);
                                        $ins->close();
                                    }
                                }
                            } elseif ($role === 'seller') {
                                $ins = $conn->prepare("INSERT INTO users (name, email, password, role, shop_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
                                if (!$ins) {
                                    $generalErr = "DB error: " . e($conn->error);
                                } else {
                                    $ins->bind_param("sssss", $name, $email, $hash, $role, $shop_name);
                                    if ($ins->execute()) {
                                        $ins->close();
                                        $successMsg = "Registration successful. Please login.";
                                        $page = "login";
                                        $name = $email = $shop_name = "";
                                    } else {
                                        $generalErr = "Registration failed: " . e($ins->error);
                                        $ins->close();
                                    }
                                }
                            } else { // customer
                                $ins = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                                if (!$ins) {
                                    $generalErr = "DB error: " . e($conn->error);
                                } else {
                                    $ins->bind_param("ssss", $name, $email, $hash, $role);
                                    if ($ins->execute()) {
                                        $ins->close();
                                        $successMsg = "Registration successful. Please login.";
                                        $page = "login";
                                        $name = $email = $shop_name = "";
                                    } else {
                                        $generalErr = "Registration failed: " . e($ins->error);
                                        $ins->close();
                                    }
                                }
                            }
                        }
                    }
                    }
                }
            }
        } elseif ($page === 'login') {
            // email
            if (empty($_POST['email'])) {
                $emailErr = "Email is required";
            } else {
                $email = cleanInput($_POST['email']);
                if (!is_valid_email($email)) {
                    $emailErr = "Invalid email format";
                }
            }
            $password = $_POST['password'] ?? "";
            if (empty($password)) {
                $passwordErr = "Password is required";
            }

            if (!$emailErr && !$passwordErr) {
                $stmt = $conn->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = ?");
                if (!$stmt) {
                    $generalErr = "DB error: " . e($conn->error);
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res->fetch_assoc();
                    $stmt->close();

                    if (!$row) {
                        $loginErr = "Invalid email or password";
                    } elseif ($row['status'] !== 'active') {
                        $loginErr = "Account is inactive. Contact admin.";
                    } elseif ($row['role'] !== $role) {
                        $loginErr = "Role mismatch. This account is registered as '" . e($row['role']) . "'. Use " . e($row['role']) . " login.";
                    } elseif (!password_verify($password, $row['password'])) {
                        $loginErr = "Invalid email or password";
                    } else {
                        // success
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int)$row['id'];
                        $_SESSION['name']    = $row['name'];
                        $_SESSION['email']   = $row['email'];
                        $_SESSION['role']    = $row['role'];
                        // init cart for customer
                        if ($row['role'] === 'customer' && !isset($_SESSION['cart'])) {
                            $_SESSION['cart'] = [];
                        }
                        redirect(role_dashboard($row['role']));
                    }
                }
            }
            // keep email filled
        }
    }

require_once __DIR__ . '/includes/header.php';
?>

<div class="card" style="max-width:520px; margin: 0 auto;">
    <div class="card-header">
        <?php if ($page === 'login'): ?>
            <h1 style="font-size:28px;"><?php echo e(ucfirst($role)); ?> Login</h1>
            <p>Enter your credentials.</p>
        <?php else: ?>
            <h1 style="font-size:28px;"><?php echo e(ucfirst($role)); ?> Register</h1>
            <p>Create a new <?php echo e($role); ?> account. All fields required.</p>
        <?php endif; ?>
    </div>

    <?php if ($generalErr): ?>
        <div class="alert alert-error"><?php echo e($generalErr); ?></div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
        <div class="alert alert-success"><?php echo e($successMsg); ?></div>
    <?php endif; ?>
    <?php if ($loginErr): ?>
        <div class="alert alert-error"><?php echo e($loginErr); ?></div>
    <?php endif; ?>

    <?php if ($page === 'login'): ?>
        <form method="post" action="<?php echo e($_SERVER['PHP_SELF'] . '?role=' . $role . '&page=login'); ?>" novalidate>
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo e($email); ?>">
                <?php if ($emailErr): ?><span class="error"><?php echo e($emailErr); ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters">
                <?php if ($passwordErr): ?><span class="error"><?php echo e($passwordErr); ?></span><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Login</button>
            <?php if ($role !== 'admin'): ?>
                <p class="small muted text-center mt">No account? <a href="<?php echo e('auth.php?role=' . $role . '&page=register'); ?>">Create <?php echo e($role); ?> account</a></p>
            <?php endif; ?>
            <p class="small muted text-center" style="margin-top:8px;"><a href="index.php">Back to home</a></p>
        </form>
    <?php else: /* register */ ?>
        <form method="post" action="<?php echo e($_SERVER['PHP_SELF'] . '?role=' . $role . '&page=register'); ?>" novalidate>
            <div class="field">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" placeholder="Jane Doe" value="<?php echo e($name); ?>">
                <?php if ($nameErr): ?><span class="error"><?php echo e($nameErr); ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo e($email); ?>">
                <?php if ($emailErr): ?><span class="error"><?php echo e($emailErr); ?></span><?php endif; ?>
            </div>
            <?php if ($role === 'vendor' || $role === 'seller'): ?>
                <div class="field">
                    <label for="shop_name"><?php echo $role === 'vendor' ? 'Shop / Business Name (optional)' : 'Shop Name (optional)'; ?></label>
                    <input type="text" id="shop_name" name="shop_name" placeholder="<?php echo $role === 'vendor' ? 'Vendor Traders' : 'My Mobile Shop'; ?>" value="<?php echo e($shop_name); ?>">
                    <?php if ($shopErr): ?><span class="error"><?php echo e($shopErr); ?></span><?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="field">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters">
                <?php if ($passwordErr): ?><span class="error"><?php echo e($passwordErr); ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label for="confirm">Confirm Password</label>
                <input type="password" id="confirm" name="confirm" placeholder="Repeat password">
                <?php if ($confirmErr): ?><span class="error"><?php echo e($confirmErr); ?></span><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Register</button>
            <p class="small muted text-center mt">Already have an account? <a href="<?php echo e('auth.php?role=' . $role . '&page=login'); ?>">Login</a></p>
            <p class="small muted text-center" style="margin-top:8px;"><a href="index.php">Back to home</a></p>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
