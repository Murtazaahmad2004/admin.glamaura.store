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

if (!isset($_GET['id'])) {
    header("Location: manage_sales.php");
    exit();
}

$sale_id = intval($_GET['id']);

// Get current status
$sql = "SELECT status FROM sales WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sale_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo "<script>alert('Sale not found!'); window.location='manage_sales.php';</script>";
    exit();
}

$current_status = $row['status'];
$new_status = ($current_status === "active") ? "inactive" : "active";

// Update new status
$update = "UPDATE sales SET status = ? WHERE id = ?";
$stmt2 = $conn->prepare($update);
$stmt2->bind_param("si", $new_status, $sale_id);
$stmt2->execute();

echo "<script>alert('Sale status changed to $new_status'); window.location='manage_sales.php';</script>";

// Close connections
$stmt->close();
$stmt2->close();
$conn->close();
?>
