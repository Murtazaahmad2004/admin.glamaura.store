<?php
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

$sql = "SELECT * FROM sales WHERE status='active' ORDER BY discount_percent DESC";
$result = $conn->query($sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Active Sales</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">
    <style>
        body { font-family: Arial; background:#f5f5f5; padding:20px; }
        .sale-box {
            background:white;
            padding:15px;
            margin:10px 0;
            border-radius:6px;
            box-shadow:0 0 10px rgba(0,0,0,0.1);
        }
        .discount { color: red; font-weight:bold; }
        .title { font-size:20px; font-weight:bold; }
    </style>
</head>
<body>

<h2>🔥 Active Sales</h2>

<?php while($sale = $result->fetch_assoc()): ?>
    <div class="sale-box">
        <div class="title"><?= $sale['title'] ?></div>
        <p><?= $sale['description'] ?></p>
        <p class="discount">Discount: <?= $sale['discount_percent'] ?>%</p>
        <p>Valid From: <?= $sale['start_date'] ?> — To: <?= $sale['end_date'] ?></p>
    </div>
<?php endwhile; ?>

</body>
</html>
