<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';

// If already logged in, go to dashboard — landing page only after logout
if (is_logged_in()) {
    $r = $_SESSION['role'];
    if ($r === 'admin') { header("Location: admin/dashboard.php"); exit; }
    elseif ($r === 'vendor') { header("Location: vendor/dashboard.php"); exit; }
    elseif ($r === 'seller') { header("Location: seller/dashboard.php"); exit; }
    elseif ($r === 'customer') { header("Location: customer/browse.php"); exit; }
}
require_once __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <h1>MobiTrackk</h1>
    <p style="color:#2D0000; font-size:16px; margin-top:2px; line-height:1;">A Reliable Management System</p>
</div>

<div class="role-grid">
    <!-- Admin -->
    <div class="role-card">
        <h3>Admin</h3>
        <p>View total sales, active sellers &amp; vendors, revenue; handle complaints &amp; post notices.</p>
        <a class="btn btn-primary btn-block" href="auth.php?role=admin&page=login">Admin Login</a>
    </div>

    <!-- Vendor -->
    <div class="role-card">
        <h3>Vendor</h3>
        <p>Manage supply items &amp; dynamic pricing, update delivery status, set return policy.</p>
        <a class="btn btn-primary btn-block" href="auth.php?role=vendor&page=login">Vendor Login</a>
    </div>

    <!-- Seller -->
    <div class="role-card">
        <h3>Seller</h3>
        <p>Track stock &amp; pending orders, calculate profit margin, wishlist vendor products.</p>
        <a class="btn btn-primary btn-block" href="auth.php?role=seller&page=login">Seller Login</a>
    </div>

    <!-- Customer -->
    <div class="role-card">
        <h3>Customer</h3>
        <p>Browse &amp; search, cart &amp; checkout, write reviews, request store visits.</p>
        <a class="btn btn-primary btn-block" href="auth.php?role=customer&page=login">Customer Login</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
