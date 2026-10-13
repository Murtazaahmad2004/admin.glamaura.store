<?php
// ------------------
// ERROR REPORTING (DEBUG)
// ------------------
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$servername = "gateway01.ap-northeast-1.prod.aws.tidbcloud.com";
$username = getenv("DB_USERNAME");
$password = getenv("DB_PASSWORD");
$dbname = "ecommerece";
$dbport = 4000;

// TiDB Cloud TLS configuration
$ssl_ca = __DIR__ . "/ca.pem";

$conn = mysqli_init();

mysqli_ssl_set(
    $conn,
    NULL,       // client key
    NULL,       // client certificate
    $ssl_ca,    // CA certificate
    NULL,
    NULL
);

mysqli_real_connect(
    $conn,
    $servername,
    $username,
    $password,
    $dbname,
    $dbport,
    NULL,
    MYSQLI_CLIENT_SSL
);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// ------------------------
// FETCH DASHBOARD COUNTS
// ------------------------

// Total product
$total_product = $conn->query("SELECT COUNT(*) AS count FROM product")
                      ->fetch_assoc()['count'];

// Total Orders
$total_orders = $conn->query("SELECT COUNT(*) AS count FROM orders")
                     ->fetch_assoc()['count'];

// Pending Orders
$pending_orders = $conn->query("SELECT COUNT(*) AS count FROM orders WHERE status='pending'")
                       ->fetch_assoc()['count'];

// Total Sales Amount
$total_sales = $conn->query("SELECT IFNULL(SUM(total),0) AS total FROM orders")
                    ->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Glamaura Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">

<style>
/* ===== Your Original CSS ===== */
body {
margin: 0;
color: #fff;
min-height: 100vh;
display: flex;
flex-direction: column;
align-items: center;
text-align: center;
background:
linear-gradient(180deg, rgba(0, 0, 0, 0.9) 10%, rgba(26, 26, 26, 0.95) 50%);
background-size: cover;
background-position: center;
background-attachment: fixed;
overflow: hidden;
}

.navbar {
background: rgba(20,20,20,0.9);
padding: 15px 40px;
width: 100%;
display: flex;
justify-content: space-between;
border-bottom: 1px solid rgba(255,204,0,0.3);
}

.navbar h2 { color: #ffcc00; }

.navbar ul {
list-style: none;
display: flex;
gap: 20px;
padding: 0;
}

.navbar ul li a {
color: #fff;
text-decoration: none;
padding: 8px 14px;
}

.cards {
display: grid;
grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
gap: 25px;
width: 90%;
max-width: 1000px;
}

.card {
background: rgba(20,20,20,0.7);
border-radius: 15px;
padding: 25px;
text-align: center;
border: 1px solid rgba(255,255,255,0.12);
}

.card i {
font-size: 40px;
color: #ffcc00;
}

.card h4 { color: #fff; }

.card p {
color: #ffcc00;
font-size: 22px;
font-weight: bold;
}
</style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <h2><i class="fa-solid fa-crown"></i> Glamaura</h2>
    <ul>
        <li><a href="add_sale.php"><i class="fa-solid fa-tag"></i> Add Sale</a></li>
        <li><a href="manage_sales.php"><i class="fa-solid fa-basket-shopping"></i> Manage Sales</a></li>
        <li><a href="admin.php"><i class="fa-solid fa-clock"></i> product</a></li>
        <li><a href=" orders.php"><i class="fa-solid fa-receipt"></i> Orders</a></li>
        <li><a href="report.php"><i class="fa-solid fa-chart-line"></i> Reports</a></li>
    </ul>
</div>

<h3 style="color:#ffcc00; margin-top:40px;">Glamaura Admin Dashboard</h3>

<div class="cards">

    <div class="card">
        <i class="fa-solid fa-box"></i>
        <h4>Total product</h4>
        <p><?php echo $total_product; ?></p>
    </div>

    <div class="card">
        <i class="fa-solid fa-receipt"></i>
        <h4>Total Orders</h4>
        <p><?php echo $total_orders; ?></p>
    </div>

    <div class="card">
        <i class="fa-solid fa-spinner"></i>
        <h4>Pending Orders</h4>
        <p><?php echo $pending_orders; ?></p>
    </div>

    <div class="card">
        <i class="fa-solid fa-sack-dollar"></i>
        <h4>Total Amount (PKR)</h4>
        <p><?php echo number_format($total_sales); ?></p>
    </div>

</div>

</body>
</html>
