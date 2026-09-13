<?php
// config.php - mysqli OOP connection + auto CREATE DATABASE / TABLES + seed admin
// Uses new mysqli() + ->prepare()/bind_param() where user input touches SQL (slides style)

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'shop_db');

$conn = null;
$db_error = null;

if (!class_exists('mysqli')) {
    $db_error = "mysqli extension not loaded (enable in php.ini). Running without DB for now.";
} else {
// Step 1: connect without DB to create database if not exists
$tmp = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($tmp->connect_error) {
    $db_error = "Database connection failed: " . $tmp->connect_error;
} else {
    $tmp->query("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $tmp->close();

    // Step 2: connect with DB
    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $db_error = "Database selection failed: " . $conn->connect_error;
        $conn = null;
    } else {
        $conn->set_charset("utf8mb4");

        // Step 3: create tables IF NOT EXISTS (order respects FKs - users first)
        $tables = [];

        $tables[] = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','vendor','seller','customer') NOT NULL,
            shop_name VARCHAR(150) NULL,
            business_name VARCHAR(150) NULL,
            address TEXT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS vendor_products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            vendor_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            return_policy TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS seller_stock (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            vendor_product_id INT NULL,
            qty INT NOT NULL DEFAULT 0,
            cost_price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_product_id) REFERENCES vendor_products(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            name VARCHAR(150) NOT NULL,
            selling_price DECIMAL(10,2) NOT NULL,
            cost_price DECIMAL(10,2) NOT NULL,
            profit_margin DECIMAL(5,2) NULL,
            image VARCHAR(255) NULL,
            stock_qty INT NOT NULL DEFAULT 0,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            total_amount DECIMAL(10,2) NOT NULL,
            delivery_status ENUM('pending','shipped','delivered','cancelled') DEFAULT 'pending',
            assigned_vendor_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (assigned_vendor_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NOT NULL,
            seller_id INT NOT NULL,
            qty INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS complaints (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            message TEXT NOT NULL,
            status ENUM('open','resolved') DEFAULT 'open',
            reply TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS notices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            message TEXT NOT NULL,
            target_role ENUM('seller','vendor','all') DEFAULT 'all',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            product_id INT NOT NULL,
            rating TINYINT NOT NULL,
            comment TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
            CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS store_visits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            seller_id INT NOT NULL,
            visit_date DATE NOT NULL,
            message TEXT NULL,
            status ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        $tables[] = "CREATE TABLE IF NOT EXISTS wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            seller_id INT NOT NULL,
            vendor_product_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (vendor_product_id) REFERENCES vendor_products(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_seller_vendor (seller_id, vendor_product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        foreach ($tables as $sql) {
            if (!$conn->query($sql)) {
                $db_error = "Table creation failed: " . $conn->error;
                break;
            }
        }

        // Step 4: seed admin if not exists (using prepared statement)
        if ($db_error === null) {
            $admin_email = "admin@shop.local";
            $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($check) {
                $check->bind_param("s", $admin_email);
                $check->execute();
                $check->store_result();
                if ($check->num_rows === 0) {
                    $check->close();
                    $admin_name = "Admin";
                    $admin_role = "admin";
                    $admin_hash = password_hash("Admin@123", PASSWORD_DEFAULT);
                    $ins = $conn->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')");
                    if ($ins) {
                        $ins->bind_param("ssss", $admin_name, $admin_email, $admin_hash, $admin_role);
                        $ins->execute();
                        $ins->close();
                    }
                } else {
                    $check->close();
                }
            }
            // Step 5: auto-seed demo data for ALL tables to at least 35 rows (all fields populated)
            $target_users = 35; $target_vp = 35; $target_ss = 35; $target_products = 35;
            $target_orders = 30; $target_oitems = 50; $target_complaints = 30;
            $target_notices = 30; $target_reviews = 30; $target_visits = 30; $target_wishlist = 30;
            $needSeed = false;
            $checks = [
                'users' => $target_users, 'vendor_products' => $target_vp, 'seller_stock' => $target_ss,
                'products' => $target_products, 'orders' => $target_orders, 'order_items' => $target_oitems,
                'complaints' => $target_complaints, 'notices' => $target_notices, 'reviews' => $target_reviews,
                'store_visits' => $target_visits, 'wishlist' => $target_wishlist
            ];
            foreach ($checks as $tbl=>$need) {
                $chk = $conn->query("SELECT COUNT(*) AS c FROM `$tbl`");
                if ($chk && (int)$chk->fetch_assoc()['c'] < $need) { $needSeed = true; break; }
            }
            if ($needSeed) {
                // helper to get ids
                $vendors=[]; $r=$conn->query("SELECT id FROM users WHERE role='vendor'"); if($r) while($row=$r->fetch_assoc()) $vendors[]=(int)$row['id'];
                $sellers=[]; $r=$conn->query("SELECT id FROM users WHERE role='seller'"); if($r) while($row=$r->fetch_assoc()) $sellers[]=(int)$row['id'];
                $customers=[]; $r=$conn->query("SELECT id FROM users WHERE role='customer'"); if($r) while($row=$r->fetch_assoc()) $customers[]=(int)$row['id'];
                $admins=[]; $r=$conn->query("SELECT id FROM users WHERE role='admin'"); if($r) while($row=$r->fetch_assoc()) $admins[]=(int)$row['id'];
                if (empty($vendors)) $vendors=[2];
                if (empty($sellers)) $sellers=[3];
                if (empty($customers)) $customers=[4];
                if (empty($admins)) $admins=[1];
                // refresh vendor_products and products ids after potential inserts
                $getVpIds = function() use ($conn) { $a=[]; $r=$conn->query("SELECT id FROM vendor_products"); if($r) while($row=$r->fetch_assoc()) $a[]=(int)$row['id']; return $a; };
                $getProdIds = function() use ($conn) { $a=[]; $r=$conn->query("SELECT id FROM products"); if($r) while($row=$r->fetch_assoc()) $a[]=(int)$row['id']; return $a; };
                $getOrderIds = function() use ($conn) { $a=[]; $r=$conn->query("SELECT id FROM orders"); if($r) while($row=$r->fetch_assoc()) $a[]=(int)$row['id']; return $a; };

                // users to target_users (ALL FIELDS: shop_name, business_name, address, status)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()['c'];
                $roles=['vendor','seller','customer'];
                $streets=['Gulshan','Banani','Dhanmondi','Mirpur','Uttara','Mohakhali','Farmgate'];
                for($i=$cnt+1; $i<=$target_users; $i++){
                    $role=$roles[($i-1)%3]; $name=ucfirst($role)." User $i"; $email=strtolower($role)."_$i@test.local";
                    $chk2=$conn->prepare("SELECT id FROM users WHERE email=?"); if(!$chk2) continue; $chk2->bind_param("s",$email); $chk2->execute(); $chk2->store_result(); if($chk2->num_rows>0){ $chk2->close(); continue; } $chk2->close();
                    $hash=password_hash(ucfirst($role).'@123', PASSWORD_DEFAULT);
                    $street=$streets[array_rand($streets)]; $addr="House $i, Road ".rand(1,50).", $street, Dhaka-".rand(1000,1299).", Bangladesh";
                    $status = (rand(1,10) <= 9) ? 'active' : 'inactive'; // 90% active
                    if($role==='customer'){
                        $ins=$conn->prepare("INSERT INTO users (name,email,password,role,address,status) VALUES (?,?,?,?,?,'active')");
                        // keep status active for customers for demo unless random inactive
                        $ins->bind_param("sssss",$name,$email,$hash,$role,$addr); $ins->execute(); $ins->close();
                        // also update status if inactive chosen
                        if($status==='inactive'){ $conn->query("UPDATE users SET status='inactive' WHERE email='".$conn->real_escape_string($email)."'"); }
                    } else {
                        $shop=ucfirst($role)." Shop $i - $street";
                        $biz=ucfirst($role)." Business Ltd. $i";
                        $ins=$conn->prepare("INSERT INTO users (name,email,password,role,shop_name,business_name,address,status) VALUES (?,?,?,?,?,?,?,?)");
                        $ins->bind_param("ssssssss",$name,$email,$hash,$role,$shop,$biz,$addr,$status); $ins->execute(); $ins->close();
                    }
                }
                // refresh ids after user inserts
                $vendors=[]; $r=$conn->query("SELECT id FROM users WHERE role='vendor'"); if($r) while($row=$r->fetch_assoc()) $vendors[]=(int)$row['id'];
                $sellers=[]; $r=$conn->query("SELECT id FROM users WHERE role='seller'"); if($r) while($row=$r->fetch_assoc()) $sellers[]=(int)$row['id'];
                $customers=[]; $r=$conn->query("SELECT id FROM users WHERE role='customer'"); if($r) while($row=$r->fetch_assoc()) $customers[]=(int)$row['id'];

                // vendor_products to target_vp (ALL FIELDS: vendor_id, name, price, return_policy)
                $vpIds=$getVpIds(); $cnt=count($vpIds);
                $vpNames=['iPhone 15 Pro','Galaxy S24 Ultra','Pixel 8 Pro','Redmi Note 13','OnePlus 11','Oppo Find X6','Vivo X90','Realme GT 5','Infinix Note 30','Tecno Camon 20'];
                $policies=['7 days replacement if sealed box','15 days warranty + 3 days return','30 days vendor warranty, return within 7 days if defective','No return after opening, 1 year service warranty','7 days return, 15 days replacement for manufacturing defect','Damage must be reported within 24h, 15 days money back','Sealed pack only return, 7 days','1 year brand warranty, 7 days return'];
                for($i=$cnt+1; $i<=$target_vp; $i++){
                    $vid=$vendors[array_rand($vendors)];
                    $base=$vpNames[array_rand($vpNames)]; $name="$base - 128GB"; $price=15000 + $i*800 + rand(0,5000);
                    $policy=$policies[array_rand($policies)];
                    $stmt=$conn->prepare("INSERT INTO vendor_products (vendor_id,name,price,return_policy) VALUES (?,?,?,?)");
                    $stmt->bind_param("isds",$vid,$name,$price,$policy); $stmt->execute(); $stmt->close();
                }
                $vpIds=$getVpIds();

                // seller_stock to target_ss (ALL FIELDS: seller_id, vendor_product_id, qty, cost_price)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM seller_stock")->fetch_assoc()['c'];
                for($i=$cnt+1; $i<=$target_ss; $i++){
                    $sid=$sellers[array_rand($sellers)];
                    $vpid=$vpIds[array_rand($vpIds)];
                    $qty=rand(10,100); $cost= 8000 + rand(0,15000) + $i*100;
                    $stmt=$conn->prepare("INSERT INTO seller_stock (seller_id,vendor_product_id,qty,cost_price) VALUES (?,?,?,?)");
                    $stmt->bind_param("iiid",$sid,$vpid,$qty,$cost); $stmt->execute(); $stmt->close();
                }

                // products to target_products (ALL FIELDS: seller_id, name, selling_price, cost_price, profit_margin, stock_qty, description) - no image
                $prodNames=['MobiTrackk Alpha','MobiTrackk Pro','MobiTrackk Lite','Galaxy Edge','Pixel Force','Redmi Power','OnePlus Nord','Oppo Reno','Vivo V','Realme Narzo'];
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM products")->fetch_assoc()['c'];
                for($i=$cnt+1; $i<=$target_products; $i++){
                    $sid=$sellers[array_rand($sellers)];
                    $pname=$prodNames[array_rand($prodNames)]; $cost=18000+$i*600+rand(0,3000); $sell=$cost+rand(3000,8000); $margin=round((($sell-$cost)/$sell)*100,2); $stock=rand(5,80);
                    $desc="Brand new $pname with official warranty. Features 6.5\" AMOLED, 5000mAh battery, 108MP camera. Imported via MobiTrackk. Stock available at seller #$sid. Ideal for retail.";
                    $stmt=$conn->prepare("INSERT INTO products (seller_id,name,selling_price,cost_price,profit_margin,stock_qty,description) VALUES (?,?,?,?,?,?,?)");
                    $stmt->bind_param("isdddis",$sid,$pname,$sell,$cost,$margin,$stock,$desc); $stmt->execute(); $stmt->close();
                }
                $prodIds=$getProdIds();
                // ensure products ids refreshed
                if(empty($prodIds)) $prodIds=[1];

                // orders to target_orders (ALL FIELDS: customer_id, total_amount, delivery_status, assigned_vendor_id)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM orders")->fetch_assoc()['c'];
                $statuses=['pending','shipped','delivered','cancelled'];
                for($i=$cnt+1; $i<=$target_orders; $i++){
                    $cid=$customers[array_rand($customers)];
                    $status=$statuses[array_rand($statuses)];
                    $total= rand(20000,80000);
                    $assigned = (rand(1,3)==1) ? $vendors[array_rand($vendors)] : null;
                    if($assigned===null){
                        $stmt=$conn->prepare("INSERT INTO orders (customer_id,total_amount,delivery_status,assigned_vendor_id) VALUES (?,?,?,NULL)");
                        $stmt->bind_param("ids",$cid,$total,$status); $stmt->execute(); $stmt->close();
                    } else {
                        $stmt=$conn->prepare("INSERT INTO orders (customer_id,total_amount,delivery_status,assigned_vendor_id) VALUES (?,?,?,?)");
                        $stmt->bind_param("idsi",$cid,$total,$status,$assigned); $stmt->execute(); $stmt->close();
                    }
                }
                $orderIds=$getOrderIds();

                // order_items to target_oitems (ALL FIELDS: order_id, product_id, seller_id, qty, price)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM order_items")->fetch_assoc()['c'];
                for($i=$cnt+1; $i<=$target_oitems; $i++){
                    $oid=$orderIds[array_rand($orderIds)];
                    $pid=$prodIds[array_rand($prodIds)];
                    // fetch seller_id for product for consistency
                    $rr=$conn->query("SELECT seller_id, selling_price FROM products WHERE id=$pid LIMIT 1");
                    $srow=$rr ? $rr->fetch_assoc() : null;
                    $sid=$srow ? (int)$srow['seller_id'] : $sellers[array_rand($sellers)];
                    $price=$srow ? (float)$srow['selling_price'] : rand(20000,40000);
                    $qty=rand(1,3);
                    $stmt=$conn->prepare("INSERT INTO order_items (order_id,product_id,seller_id,qty,price) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("iiiid",$oid,$pid,$sid,$qty,$price); $stmt->execute(); $stmt->close();
                }
                // fix orders total_amount to match sum of items (ALL FIELDS consistency)
                $conn->query("UPDATE orders o SET total_amount = COALESCE((SELECT SUM(qty*price) FROM order_items oi WHERE oi.order_id=o.id), o.total_amount)");

                // complaints to target_complaints (ALL FIELDS: customer_id, message, status, reply)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM complaints")->fetch_assoc()['c'];
                $complaintMsgs=['Phone heating issue','Battery draining fast','Screen flickering after update','Charger not working','Camera blurry','Speaker low volume','Network dropping frequently','Late delivery complaint','Wrong product received','Payment not reflected'];
                for($i=$cnt+1; $i<=$target_complaints; $i++){
                    $cid=$customers[array_rand($customers)];
                    $msg=$complaintMsgs[array_rand($complaintMsgs)]." - case $i. Order related issue, need support.";
                    $status=(rand(1,3)==1) ? 'resolved' : 'open';
                    $reply = ($status==='resolved') ? "We have resolved your complaint #$i. Please visit service center or contact support. Thank you for choosing MobiTrackk." : null;
                    if($reply===null){
                        $stmt=$conn->prepare("INSERT INTO complaints (customer_id,message,status,reply) VALUES (?,?,?,NULL)");
                        $stmt->bind_param("iss",$cid,$msg,$status); $stmt->execute(); $stmt->close();
                    } else {
                        $stmt=$conn->prepare("INSERT INTO complaints (customer_id,message,status,reply) VALUES (?,?,?,?)");
                        $stmt->bind_param("isss",$cid,$msg,$status,$reply); $stmt->execute(); $stmt->close();
                    }
                }

                // notices to target_notices (ALL FIELDS: admin_id, message, target_role)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM notices")->fetch_assoc()['c'];
                $noticeMsgs=[
                    "System maintenance on Sunday 10PM-2AM. Please save your work.",
                    "New commission policy effective from next month: 5% for sellers.",
                    "MobiTrackk festival sale starts next week - prepare inventory!",
                    "All vendors must update return policy by month end.",
                    "Reminder: Monthly sales report submission deadline is 5th.",
                    "Welcome to MobiTrackk - A Reliable Management System. Updated policies available.",
                    "Seller rating update: Top 10 sellers will get bonus this quarter.",
                    "Vendor pricing audit scheduled - ensure all prices updated.",
                    "Customer review campaign: Encourage buyers to leave reviews.",
                    "Security update: Please change passwords every 90 days."
                ];
                $targets=['all','seller','vendor'];
                for($i=$cnt+1; $i<=$target_notices; $i++){
                    $aid=$admins[array_rand($admins)];
                    $msg=$noticeMsgs[array_rand($noticeMsgs)]." (Notice #$i)";
                    $tgt=$targets[array_rand($targets)];
                    $stmt=$conn->prepare("INSERT INTO notices (admin_id,message,target_role) VALUES (?,?,?)");
                    $stmt->bind_param("iss",$aid,$msg,$tgt); $stmt->execute(); $stmt->close();
                }

                // reviews to target_reviews (ALL FIELDS: customer_id, product_id, rating, comment)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM reviews")->fetch_assoc()['c'];
                $revComments=['Excellent phone, highly recommended!','Value for money, good performance.','Battery life good, camera average.','Amazing display and speed!','Average product, expected better.','Outstanding build quality!','Fast delivery, product as described.','Satisfied with purchase.','Not as expected, but okay.','Best in this price range!'];
                for($i=$cnt+1; $i<=$target_reviews; $i++){
                    $cid=$customers[array_rand($customers)];
                    $pid=$prodIds[array_rand($prodIds)];
                    // avoid duplicate review by same customer for same product (check)
                    $chkDup=$conn->prepare("SELECT id FROM reviews WHERE customer_id=? AND product_id=?");
                    $chkDup->bind_param("ii",$cid,$pid); $chkDup->execute(); $chkDup->store_result();
                    if($chkDup->num_rows>0){ $chkDup->close(); continue; } $chkDup->close();
                    $rating=rand(1,5); $comment=$revComments[array_rand($revComments)]." (Review $i)";
                    $stmt=$conn->prepare("INSERT INTO reviews (customer_id,product_id,rating,comment) VALUES (?,?,?,?)");
                    $stmt->bind_param("iiis",$cid,$pid,$rating,$comment); $stmt->execute(); $stmt->close();
                }

                // store_visits to target_visits (ALL FIELDS: customer_id, seller_id, visit_date, message, status)
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM store_visits")->fetch_assoc()['c'];
                $visitStatuses=['pending','approved','rejected'];
                for($i=$cnt+1; $i<=$target_visits; $i++){
                    $cid=$customers[array_rand($customers)];
                    $sid=$sellers[array_rand($sellers)];
                    $days=rand(-10,30); $date=date('Y-m-d', strtotime("$days days"));
                    $msg="Visit request #$i to see stock and discuss bulk purchase. Preferred time: ".rand(10,18).":00.";
                    $status=$visitStatuses[array_rand($visitStatuses)];
                    $stmt=$conn->prepare("INSERT INTO store_visits (customer_id,seller_id,visit_date,message,status) VALUES (?,?,?,?,?)");
                    $stmt->bind_param("iisss",$cid,$sid,$date,$msg,$status); $stmt->execute(); $stmt->close();
                }

                // wishlist to target_wishlist (ALL FIELDS: seller_id, vendor_product_id) - ensure unique
                $cnt=(int)$conn->query("SELECT COUNT(*) AS c FROM wishlist")->fetch_assoc()['c'];
                $attempts=0;
                while($cnt < $target_wishlist && $attempts < 200){
                    $sid=$sellers[array_rand($sellers)];
                    $vpid=$vpIds[array_rand($vpIds)];
                    $chkW=$conn->prepare("SELECT id FROM wishlist WHERE seller_id=? AND vendor_product_id=?");
                    $chkW->bind_param("ii",$sid,$vpid); $chkW->execute(); $chkW->store_result();
                    if($chkW->num_rows==0){
                        $chkW->close();
                        $stmt=$conn->prepare("INSERT INTO wishlist (seller_id,vendor_product_id) VALUES (?,?)");
                        $stmt->bind_param("ii",$sid,$vpid); $stmt->execute(); $stmt->close(); $cnt++;
                    } else { $chkW->close(); }
                    $attempts++;
                }
            }
        }
    }
}
} // end mysqli exists guard
?>
