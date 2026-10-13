<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

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

// FETCH SALES
$sql = "SELECT * FROM sales ORDER BY id DESC";
$result = $conn->query($sql);

// FLASH MESSAGE
$flash = "";
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Sales</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">

<style>
    body {
        font-family: "Poppins", "Segoe UI", sans-serif;
        margin: 0;
        padding: 20px;
        color: #fff;
        background: radial-gradient(circle at top left, #0f2027, #203a43, #2c5364);
        background-size: 400% 400%;
        animation: gradientShift 12s ease infinite;
    }
    @keyframes gradientShift {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }
    h1 {
        text-align: center;
        margin-bottom: 30px;
        font-size: 34px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        color: #ffcc00;
        text-shadow: 0 0 15px rgba(255, 204, 0, 0.6);
        border-bottom: 3px solid #ffcc00;
        display: inline-block;
        padding-bottom: 8px;
    }
    .top-actions {
        display: flex;
        justify-content: center;
        gap: 14px;
        margin-bottom: 30px;
    }
    .btn {
        padding: 8px 14px;
        border-radius: 8px;
        color: white;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    .btn-success { background: linear-gradient(90deg, #00b09b, #96c93d); }
    .btn-edit { background: linear-gradient(90deg, #ffcc00, #ffd633); color: #000; }
    .btn-delete { background: linear-gradient(90deg, #ff416c, #ff4b2b); }
    .btn-toggle { background: linear-gradient(90deg, #0072ff, #00c6ff); }
    .btn:hover { transform: translateY(-3px); }
    .table-container {
        background: rgba(255, 255, 255, 0.08);
        border-radius: 15px;
        backdrop-filter: blur(12px);
        padding: 10px;
        overflow-x: auto;
    }
    table { width: 100%; border-collapse: collapse; }
    th, td {
        padding: 12px 14px;
        text-align: center;
        font-size: 14px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        color: #fff;
    }
    th {
        background: rgba(0, 0, 0, 0.4);
        color: #ffcc00;
    }
    .badge {
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 600;
    }
    .bg-success { background: #00b09b; color: #fff; }
    .bg-secondary { background: #555; color: #fff; }
    .alert {
        padding: 10px;
        background: rgba(0,255,0,0.2);
        color: #0f0;
        margin-bottom: 10px;
        text-align: center;
        border-radius: 8px;
    }
</style>

</head>
<body>

<div style="text-align:center;">
    <h1><i class="fa-solid fa-percent"></i> Manage Sales</h1>
</div>

<?php if ($flash): ?>
<div class="alert"><?php echo $flash; ?></div>
<?php endif; ?>

<div class="top-actions">
    <a href="add_sale.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> Add New Sale</a>
    <a href="dashboard.php" class="btn btn-success"><i class="fa-solid fa-house"></i> Home</a>
</div>

<div class="table-container">

<?php if ($result->num_rows > 0): ?>
<table>
<thead>
<tr>
    <th>ID</th>
    <th>Sale Name</th>
    <th>Discount (%)</th>
    <th>Start Date</th>
    <th>End Date</th>
    <th>Status</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php while ($row = $result->fetch_assoc()): ?>
<tr>
    <td><?php echo $row['id']; ?></td>
    <td><?php echo $row['sale_name']; ?></td>
    <td><?php echo $row['discount_percent']; ?>%</td>
    <td><?php echo $row['start_date'] ?: "—"; ?></td>
    <td><?php echo $row['end_date'] ?: "—"; ?></td>

    <td>
        <?php if ($row['status'] == "active"): ?>
            <span class="badge bg-success">Active</span>
        <?php else: ?>
            <span class="badge bg-secondary">Inactive</span>
        <?php endif; ?>
    </td>

    <td>
        <a href="toggle_status.php?id=<?php echo $row['id']; ?>" class="btn btn-toggle">
            <i class="fa fa-sync"></i>
        </a>

        <a href="delete_sale.php?id=<?php echo $row['id']; ?>"
           class="btn btn-delete"
           onclick="return confirm('Are you sure?')">
           <i class="fa fa-trash"></i>
        </a>
    </td>
</tr>
<?php endwhile; ?>

</tbody>
</table>

<?php else: ?>
<p style="text-align:center; color:#ffcc00;">No sales found.</p>
<?php endif; ?>

</div>

</body>
</html>
