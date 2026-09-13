<?php
// includes/header.php - shared <head>, <style>, nav bar (session-aware)
// Must be included after session_start() + require config.php + functions.php

// Ensure session is started (safe to call even if already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Compute prefix for relative links (root vs sub-folder)
$_self = $_SERVER['PHP_SELF'] ?? '';
$_in_sub = (strpos($_self, '/admin/') !== false || strpos($_self, '/vendor/') !== false || strpos($_self, '/seller/') !== false || strpos($_self, '/customer/') !== false);
$_is_index = (basename($_self) === 'index.php');
$_prefix = $_in_sub ? '../' : '';
$_role = $_SESSION['role'] ?? null;
$_name = $_SESSION['name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MobiTrackk - A Reliable Management System</title>
    <link rel="preload" href="/uploads/DebugFreeTrial-MVdYB.otf" as="font" type="font/otf" crossorigin>
    <link rel="preload" href="/uploads/Robus-BWqOd.otf" as="font" type="font/otf" crossorigin>
    <style>
        @font-face {
            font-family: 'Robus';
            src: url('/uploads/Robus-BWqOd.otf') format('opentype');
            font-weight: 400 700;
            font-style: normal;
            font-display: block;
        }
        @font-face {
            font-family: 'Debug';
            src: url('/uploads/DebugFreeTrial-MVdYB.otf') format('opentype'),
                 url('/uploads/misc/DEBUG FREE TRIAL-5b92.woff') format('woff'),
                 url('/uploads/misc/DEBUG FREE TRIAL-ba81.woff2') format('woff2');
            font-weight: 400 700;
            font-style: normal;
            font-display: block;
        }
        /* Reset + box model (slides teach box model, position, float, inline-block) */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Valley Sans', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #EEEAD7;
            color: #2D0000;
            font-size: 18px;
            line-height: 1.6;
        }
        a { color: #6D0808; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* Top bar - uses float + overflow hidden to clear (no flex) */
        .topbar {
            background: #6D0808;
            border-bottom: 3px solid #2D0000;
            overflow: hidden;
        }
        .topbar-inner {
            width: 960px;
            max-width: 95%;
            margin: 0 auto;
            overflow: hidden;
            padding: 14px 0;
        }
        .brand {
            float: left;
            color: #ffffff;
            font-size: 32px;
            font-weight: 500;
            letter-spacing: 1px;
            text-decoration: none;
            padding: 4px 0;
            line-height: 1.1;
            font-family: 'Debug', 'Robus', 'Valley Sans', sans-serif;
        }
        .brand:hover { text-decoration: none; opacity: 0.9; }
        .brand span { display: block; font-weight: 400; font-size: 11px; opacity: 0.85; letter-spacing: 0.6px; margin: 1px 0 0 1px; line-height: 1; font-family: 'Valley Sans', sans-serif; }

        .nav {
            float: right;
            padding-top: 4px;
        }
        .nav a, .nav span {
            display: inline-block;
            color: #ffffff;
            font-size: 15px;
            font-weight: 500;
            padding: 9px 13px;
            margin-left: 4px;
            border: 1px solid transparent;
            border-radius: 6px;
            text-decoration: none;
            vertical-align: middle;
        }
        .nav a:hover {
            background: #ffffff;
            color: #6D0808;
            text-decoration: none;
        }
        .nav .nav-user {
            color: #EEEAD7;
            font-weight: 800;
            font-size: 18px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            border: none;
            padding-left: 8px;
        }
        .nav .nav-logout {
            border-color: #ffffff;
        }
        .nav .nav-logout:hover {
            background: #ff4d4d;
            border-color: #ff4d4d;
            color: #ffffff;
        }

        /* Clearfix helper */
        .clearfix { clear: both; }
        .clear { clear: both; }

        /* Page container - centered via margin auto (box model) */
        .container {
            width: 960px;
            max-width: 95%;
            margin: 30px auto;
            overflow: hidden;
        }

        /* Card - like Lab/form2.css card but without flex */
        .card {
            background: #FFFFFF;
            border: 1px solid #757D6F;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header {
            border-bottom: 3px solid #6D0808;
            padding-bottom: 16px;
            margin-bottom: 24px;
            overflow: hidden;
        }
        .card-header h1 {
            color: #6D0808;
            font-size: 34px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .card-header h2 {
            color: #6D0808;
            font-size: 26px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .card-header p {
            color: #2D0000;
            font-size: 16px;
        }

        /* Landing hero - MobiTrackk heading in Debug - MORE bigger, first page only */
        .hero { text-align: center; padding: 52px 0 24px 0; }
        .hero h1 { color: #6D0808; font-size: 84px; margin-bottom: 4px; font-family: 'Debug', 'Robus', 'Valley Sans', sans-serif; font-weight: 500; letter-spacing: 2px; line-height: 1; text-shadow: 0 2px 4px rgba(42,58,143,0.12); }
        .hero p { color: #2D0000; font-size: 16px; max-width: 560px; margin: 0 auto; }

        /* Role grid - uses inline-block (no flex/grid) - single row */
        .role-grid {
            text-align: center;
            font-size: 0;
            margin-top: 54px;
            white-space: nowrap;
            overflow: hidden;
        }
        .role-card {
            display: inline-block;
            width: 215px;
            height: 270px;
            background: #FFFFFF;
            border: 1px solid #757D6F;
            border-radius: 16px;
            padding: 28px 20px 20px 20px;
            margin: 8px;
            vertical-align: top;
            font-size: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
            white-space: normal;
        }
        .role-card h3 {
            color: #6D0808;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .role-card p {
            color: #2D0000;
            font-size: 15px;
            line-height: 1.6;
            min-height: 60px;
            margin-bottom: 0;
        }
        .role-card .btn {
            position: absolute;
            bottom: 24px;
            left: 20px;
            right: 20px;
            width: auto;
            height: 46px;
            line-height: 44px;
            padding: 0 22px;
            font-size: 16px;
            box-sizing: border-box;
        }
        .role-card .btn.btn-block {
            width: auto;
        }
        .role-card .small {
            position: absolute;
            bottom: 10px;
            left: 18px;
            right: 18px;
            margin: 0;
            text-align: center;
            font-size: 11px;
            min-height: auto;
            line-height: 1.4;
        }
        /* Buttons */
        .btn {
            display: inline-block;
            border-radius: 6px;
            padding: 13px 24px;
            font-size: 16px;
            font-weight: 500;
            font-family: inherit;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            border: 1px solid #6D0808;
        }
        .btn-primary {
            background: #6D0808;
            color: #ffffff;
            border-color: #6D0808;
        }
        .btn-primary:hover { background: #2D0000; border-color: #2D0000; color: #ffffff; text-decoration: none; }
        .btn-secondary {
            background: #ffffff;
            color: #6D0808;
        }
        .btn-secondary:hover { background: #eef0ff; text-decoration: none; }
        .btn-block { display: block; width: 100%; }
        .btn-small { padding: 8px 14px; font-size: 13px; }

        /* Forms - plain block layout */
        .field { margin-bottom: 18px; }
        .field label {
            display: block;
            font-size: 15px;
            font-weight: 500;
            color: #2D0000;
            margin-bottom: 6px;
        }
        .field input, .field select, .field textarea {
            width: 100%;
            background: #ffffff;
            border: 1px solid #757D6F;
            border-radius: 8px;
            padding: 13px 15px;
            font-size: 16px;
            font-family: inherit;
            color: #2D0000;
        }
        .field textarea { resize: vertical; min-height: 80px; }
        .field input:focus, .field select:focus, .field textarea:focus { outline: 2px solid #6D0808; }
        .error { display: block; color: #cc0000; font-size: 13px; margin-top: 6px; }
        .success { display: block; color: #0a7a0a; font-size: 13px; margin-top: 6px; background: #e6f5e6; border: 1px solid #b6d7b6; padding: 10px 12px; border-radius: 6px; }
        .alert { padding: 12px 14px; border-radius: 8px; font-size: 14px; margin-bottom: 18px; }
        .alert-error { background: #ffeaea; border: 1px solid #e5a0a0; color: #8a0000; }
        .alert-success { background: #e6f5e6; border: 1px solid #b6d7b6; color: #0a5a0a; }
        .alert-info { background: #EEEAD7; border: 1px solid #757D6F; color: #2D0000; }

        /* Tables */
        .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .data-table th, .data-table td {
            padding: 11px 13px;
            border: 1px solid #757D6F;
            font-size: 15px;
            text-align: left;
        }
        .data-table th { background: #EEEAD7; color: #2D0000; font-weight: 600; }
        .data-table td { background: #ffffff; }
        .data-table tr:nth-child(even) td { background: #FFFFFF; }

        /* Stats - inline-block cards - single row */
        .stats { font-size: 0; text-align: center; margin-bottom: 10px; white-space: nowrap; overflow: hidden; }
        .stat-box {
            display: inline-block;
            width: 205px;
            background: #ffffff;
            border: 1px solid #757D6F;
            border-radius: 12px;
            padding: 18px 14px;
            margin: 6px;
            vertical-align: top;
            font-size: 14px;
            text-align: center;
            white-space: normal;
        }
        .stat-box .num { font-size: 30px; font-weight: 600; color: #6D0808; display: block; }
        .stat-box .lbl { color: #2D0000; font-size: 13px; }

        /* Two column via inline-block */
        .col-half {
            display: inline-block;
            width: 48%;
            vertical-align: top;
            margin-right: 2%;
            font-size: 16px;
        }
        .col-half:last-child { margin-right: 0; }

        /* Utilities */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .muted { color: #2D0000; }
        .small { font-size: 13px; }
        .mt { margin-top: 16px; }
        .mb { margin-bottom: 16px; }
        .hidden { display: none; }

        /* Footer */
        .footer {
            text-align: center;
            color: #757D6F;
            font-size: 13px;
            padding: 20px 0 30px 0;
            border-top: 1px solid #757D6F;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <?php if (!$_is_index): ?>
    <div class="topbar">
        <div class="topbar-inner">
            <?php
                $brand_href = $_prefix . 'index.php';
                if ($_role === 'admin') $brand_href = $_prefix . 'admin/dashboard.php';
                elseif ($_role === 'vendor') $brand_href = $_prefix . 'vendor/dashboard.php';
                elseif ($_role === 'seller') $brand_href = $_prefix . 'seller/dashboard.php';
                elseif ($_role === 'customer') $brand_href = $_prefix . 'customer/browse.php';
            ?>
            <a class="brand" href="<?php echo e($brand_href); ?>">MobiTrackk<span>A Reliable Management System</span></a>
            <div class="nav">
                <?php if ($_role): ?>
                    <span class="nav-user"><?php echo e(strtoupper($_role)); ?></span>
                    <?php if ($_role === 'admin'): ?>
                        <a href="<?php echo e($_prefix . 'admin/dashboard.php'); ?>">Dashboard</a>
                        <a href="<?php echo e($_prefix . 'admin/users.php'); ?>">Users</a>
                        <a href="<?php echo e($_prefix . 'admin/complaints.php'); ?>">Complaints</a>
                        <a href="<?php echo e($_prefix . 'admin/notices.php'); ?>">Notices</a>
                        <a href="<?php echo e($_prefix . 'admin/profile.php'); ?>">Profile</a>
                    <?php elseif ($_role === 'vendor'): ?>
                        <a href="<?php echo e($_prefix . 'vendor/dashboard.php'); ?>">Dashboard</a>
                        <a href="<?php echo e($_prefix . 'vendor/pricing.php'); ?>">Pricing</a>
                        <a href="<?php echo e($_prefix . 'vendor/delivery.php'); ?>">Delivery</a>
                        <a href="<?php echo e($_prefix . 'vendor/policy.php'); ?>">Policy</a>
                        <a href="<?php echo e($_prefix . 'vendor/profile.php'); ?>">Profile</a>
                    <?php elseif ($_role === 'seller'): ?>
                        <a href="<?php echo e($_prefix . 'seller/dashboard.php'); ?>">Dashboard</a>
                        <a href="<?php echo e($_prefix . 'seller/stock.php'); ?>">Stock</a>
                        <a href="<?php echo e($_prefix . 'seller/margin.php'); ?>">Margin</a>
                        <a href="<?php echo e($_prefix . 'seller/wishlist.php'); ?>">Wishlist</a>
                        <a href="<?php echo e($_prefix . 'seller/profile.php'); ?>">Profile</a>
                    <?php elseif ($_role === 'customer'): ?>
                        <a href="<?php echo e($_prefix . 'customer/browse.php'); ?>">Browse</a>
                        <a href="<?php echo e($_prefix . 'customer/cart.php'); ?>">Cart</a>
                        <a href="<?php echo e($_prefix . 'customer/orders.php'); ?>">Orders</a>
                        <a href="<?php echo e($_prefix . 'customer/reviews.php'); ?>">Reviews</a>
                        <a href="<?php echo e($_prefix . 'customer/visit.php'); ?>">Visit</a>
                        <a href="<?php echo e($_prefix . 'customer/complaints.php'); ?>">Complaints</a>
                        <a href="<?php echo e($_prefix . 'customer/profile.php'); ?>">Profile</a>
                    <?php endif; ?>
                    <a class="nav-logout" href="<?php echo e($_prefix . 'auth.php?page=logout'); ?>">Logout</a>
                <?php else: ?>
                    <a href="<?php echo e($_prefix . 'auth.php?role=admin&page=login'); ?>">Admin</a>
                    <a href="<?php echo e($_prefix . 'auth.php?role=vendor&page=login'); ?>">Vendor</a>
                    <a href="<?php echo e($_prefix . 'auth.php?role=seller&page=login'); ?>">Seller</a>
                    <a href="<?php echo e($_prefix . 'auth.php?role=customer&page=login'); ?>">Customer</a>
                <?php endif; ?>
            </div>
            <div class="clear"></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if (!empty($db_error)): ?>
            <div class="alert alert-error">
                <strong>Database warning:</strong> <?php echo e($db_error); ?> — run XAMPP MySQL and reload.
            </div>
        <?php endif; ?>
