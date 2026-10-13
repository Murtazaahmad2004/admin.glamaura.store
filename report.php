<?php
session_start();

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

// Get selected date from GET request
$selected_date = isset($_GET['date']) ? $_GET['date'] : '';

// -----------------------------
// FILTER CONDITION
// -----------------------------
$date_condition = "";
$params = [];

if ($selected_date) {
    $date_condition = "WHERE DATE(created_at) = ?";
    $params[] = $selected_date;
}

// Total Orders
$sql = "SELECT COUNT(*) AS total_orders FROM orders " . $date_condition;
$stmt = $conn->prepare($sql);
if ($selected_date) $stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();
$total_orders = $result->fetch_assoc()['total_orders'];
$stmt->close();

// Total Sales
$sql = "SELECT IFNULL(SUM(total), 0) AS total_sales FROM orders " . $date_condition;
$stmt = $conn->prepare($sql);
if ($selected_date) $stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();
$total_sales = $result->fetch_assoc()['total_sales'];
$stmt->close();

// Pending Orders
if ($selected_date) {
    $sql = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status='Pending' AND DATE(created_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
} else {
    $sql = "SELECT COUNT(*) AS pending_orders FROM orders WHERE status='Pending'";
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
$pending_orders = $result->fetch_assoc()['pending_orders'];
$stmt->close();

// Sales Over Time (last 30 days if no date selected)
if ($selected_date) {
    $sql = "SELECT DATE(created_at) AS date, SUM(total) AS total FROM orders WHERE DATE(created_at)=? GROUP BY DATE(created_at)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
} else {
    $sql = "SELECT DATE(created_at) AS date, SUM(total) AS total FROM orders WHERE created_at >= CURDATE() - INTERVAL 30 DAY GROUP BY DATE(created_at) ORDER BY DATE(created_at)";
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
$sales_labels = [];
$sales_values = [];
while ($row = $result->fetch_assoc()) {
    $sales_labels[] = date("d M", strtotime($row['date']));
    $sales_values[] = (float)$row['total'];
}
$stmt->close();

// Orders by Status
$sql = "SELECT status, COUNT(*) AS count FROM orders " . $date_condition . " GROUP BY status";
$stmt = $conn->prepare($sql);
if ($selected_date) $stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();
$status_labels = [];
$status_counts = [];
while ($row = $result->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}
$stmt->close();

// Top 5 product
if ($selected_date) {
    $sql = "SELECT items FROM orders WHERE items IS NOT NULL AND items != '' AND DATE(created_at)=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $selected_date);
} else {
    $sql = "SELECT items FROM orders WHERE items IS NOT NULL AND items != ''";
    $stmt = $conn->prepare($sql);
}
$stmt->execute();
$result = $stmt->get_result();
$Product_sales = [];
while ($row = $result->fetch_assoc()) {
    $items = json_decode($row['items'], true);
    if (is_array($items)) {
        foreach ($items as $item) {
            $name = $item['name'] ?? '';
            $qty = $item['quantity'] ?? 1;
            if ($name) $Product_sales[$name] = ($Product_sales[$name] ?? 0) + $qty;
        }
    }
}
$stmt->close();
arsort($Product_sales);
$top_product = array_slice($Product_sales, 0, 5, true);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Glamaura Admin Report</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">
<style>
/* Copy your CSS from Flask template */
         body {
         background: linear-gradient(180deg, #0b0b0b, #1a1a1a);
         color: #f2f2f2;
         margin: 0;
         padding: 0;
         }
         header {
         background: linear-gradient(90deg, #d4af37, #b8860b);
         color: #000;
         padding: 20px;
         text-align: center;
         font-size: 26px;
         font-weight: 700;
         letter-spacing: 2px;
         box-shadow: 0 2px 10px rgba(0,0,0,0.4);
         text-transform: uppercase;
         }
         .filter-bar {
            width: 90%;
            margin: 25px auto 0;
            background: #141414;
            border: 1px solid rgba(255, 215, 0, 0.25);
            border-radius: 12px;
            padding: 15px 25px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
            justify-content: flex-start;
         }
         .filter-bar form {
         display: flex;
         flex-wrap: wrap;
         gap: 10px;
         align-items: center;
         }
         .filter-bar label {
         color: #d4af37;
         font-weight: 500;
         }
         .filter-bar select,
         .filter-bar input[type="date"] {
         background: #1f1f1f;
         color: #fff;
         border: 1px solid #d4af37;
         border-radius: 6px;
         padding: 8px 10px;
         }
         .filter-bar button {
         background: #d4af37;
         border: none;
         color: #000;
         font-weight: 600;
         padding: 8px 14px;
         border-radius: 6px;
         cursor: pointer;
         transition: 0.3s;
         }
         .filter-bar button:hover {
         background: #b8860b;
         }
         .container {
         width: 90%;
         margin: 30px auto 10px;
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
         gap: 25px;
         }
         .card {
         background: radial-gradient(circle at top left, rgba(212, 175, 55, 0.15), #141414);
         border: 1px solid rgba(255, 215, 0, 0.3);
         border-radius: 16px;
         padding: 25px 20px;
         box-shadow: 0 0 25px rgba(212, 175, 55, 0.1);
         text-align: center;
         transition: transform 0.3s ease, box-shadow 0.3s ease;
         }
         .card:hover {
         transform: translateY(-5px);
         box-shadow: 0 0 35px rgba(212, 175, 55, 0.3);
         }
         .card h3 {
         margin: 0;
         font-size: 18px;
         color: #d4af37;
         text-transform: uppercase;
         letter-spacing: 1px;
         }
         .card p {
         font-size: 28px;
         font-weight: 600;
         color: #fff;
         margin-top: 8px;
         text-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
         }
         /* 🟥 Returned orders card special color */
         .card.returned h3 { color: #ff6666; }
         .card.returned p { text-shadow: 0 0 10px rgba(255, 100, 100, 0.5); }
         .charts-section {
         width: 90%;
         margin: 40px auto;
         display: grid;
         grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
         gap: 25px;
         }
         .chart-box {
         background: #141414;
         border: 1px solid rgba(255, 215, 0, 0.25);
         border-radius: 18px;
         padding: 25px;
         box-shadow: 0 0 25px rgba(212, 175, 55, 0.1);
         text-align: center;
         }
         .chart-box h3 {
         margin-bottom: 15px;
         color: #d4af37;
         font-size: 18px;
         font-weight: 600;
         text-transform: uppercase;
         }
         canvas {
         width: 100% !important;
         height: 280px !important;
         }
         .top-products {
         width: 90%;
         margin: 40px auto;
         background: #141414;
         border-radius: 18px;
         padding: 25px;
         border: 1px solid rgba(255, 215, 0, 0.25);
         box-shadow: 0 0 20px rgba(212, 175, 55, 0.1);
         }
         .top-products h3 {
         color: #d4af37;
         margin-bottom: 15px;
         text-transform: uppercase;
         }
         .top-products ul {
         list-style: none;
         padding: 0;
         margin: 0;
         }
         .top-products li {
         padding: 10px 0;
         border-bottom: 1px solid #333;
         font-size: 16px;
         }
         .top-products li span {
         color: #d4af37;
         font-weight: bold;
         }
         .btn-home {
         color:#050401;
         text-decoration:none;
         font-weight:500;
         }
         @media (max-width: 768px) {
         .charts-section {
         grid-template-columns: 1fr;
         }
         }
/* ... rest of CSS ... */
</style>
</head>
<body>
<header><i class="fa-solid fa-chart-line"></i> Glamaura Admin Report</header>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="get" action="">
        <label for="date">Select Date:</label>
        <input type="date" name="date" id="date" value="<?= htmlspecialchars($selected_date) ?>">
        <button type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if ($selected_date): ?>
            <a href="report.php" style="color:#d4af37;text-decoration:none;font-weight:500;">Clear</a>
        <?php endif; ?>
    </form>
    <button><a href="dashboard.php" class="btn-home"><i class="fa-solid fa-house"></i> Home</a></button>
</div>

<!-- Summary Cards -->
<div class="container">
    <div class="card">
        <h3>Total Sales</h3>
        <p>PKR <?= number_format($total_sales) ?></p>
    </div>
    <div class="card">
        <h3>Total Orders</h3>
        <p><?= $total_orders ?></p>
    </div>
    <div class="card">
        <h3>Pending Orders</h3>
        <p><?= $pending_orders ?></p>
    </div>
    <div class="card">
        <h3>Completed Orders</h3>
        <p><?= $status_counts[array_search('Completed', $status_labels)] ?? 0 ?></p>
    </div>
    <div class="card returned">
        <h3>Returned Orders</h3>
        <p><?= $status_counts[array_search('Returned', $status_labels)] ?? 0 ?></p>
    </div>
</div>

<!-- Charts Section -->
<div class="charts-section">
    <div class="chart-box">
        <h3>📈 Sales Over Time</h3>
        <canvas id="salesChart"></canvas>
    </div>
    <div class="chart-box">
        <h3>🧾 Orders by Status</h3>
        <canvas id="statusChart"></canvas>
    </div>
</div>

<!-- Top Products -->
<div class="top-products">
    <h3>🌸 Top 5 product</h3>
    <ul>
        <?php if ($top_product): ?>
            <?php foreach ($top_product as $name => $qty): ?>
                <li><span><?= htmlspecialchars($name) ?></span> — <?= $qty ?> sold</li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>No data available</li>
        <?php endif; ?>
    </ul>
</div>

<script>
const gold = '#d4af37';

// Sales Chart
const salesCtx = document.getElementById('salesChart').getContext('2d');
const gradient = salesCtx.createLinearGradient(0,0,0,300);
gradient.addColorStop(0,'rgba(212,175,55,0.4)');
gradient.addColorStop(1,'rgba(212,175,55,0)');
new Chart(salesCtx,{
    type:'line',
    data:{
        labels: <?= json_encode($sales_labels) ?>,
        datasets:[{
            label:'Sales (PKR)',
            data: <?= json_encode($sales_values) ?>,
            borderColor: gold,
            backgroundColor: gradient,
            fill:true,
            tension:0.4,
            borderWidth:2,
            pointRadius:4,
            pointBackgroundColor: gold
        }]
    },
    options:{
        plugins:{ legend:{ labels:{ color:'#fff' } } },
        scales:{
            x:{ ticks:{ color:'#bbb' }, grid:{ color:'#333' } },
            y:{ ticks:{ color:'#bbb' }, grid:{ color:'#333' } }
        }
    }
});

// Orders by Status
new Chart(document.getElementById('statusChart'), {
    type:'bar',
    data:{
        labels: <?= json_encode($status_labels) ?>,
        datasets:[{
            label:'Orders',
            data: <?= json_encode($status_counts) ?>,
            backgroundColor: ['#d4af37','#22c55e','#ef4444','#ff6666','#999']
        }]
    },
    options:{
        plugins:{ legend:{ display:false } },
        scales:{
            x:{ ticks:{ color:'#bbb' }, grid:{ color:'#333' } },
            y:{ ticks:{ color:'#bbb' }, grid:{ color:'#333' } }
        }
    }
});
</script>
</body>
</html>
