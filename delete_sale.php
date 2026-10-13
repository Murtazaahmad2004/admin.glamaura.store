<?php
// Show errors (remove after debugging)
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

// 3. Check if ID exists in URL
if (!isset($_GET['id'])) {
    header("Location: manage_sales.php");
    exit();
}

$sale_id = intval($_GET['id']);

// 4. Delete query
$sql = "DELETE FROM sales WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $sale_id);

if ($stmt->execute()) {
    echo "<script>alert('Sale deleted successfully!'); window.location='manage_sales.php';</script>";
} else {
    echo "<script>alert('Failed to delete sale!'); window.location='manage_sales.php';</script>";
}

// 5. Close connection
$stmt->close();
$conn->close();
?>
