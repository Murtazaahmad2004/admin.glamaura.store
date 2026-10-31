<?php
session_start();

// ⚠️ Apne admin login ke hisab se yeh check badal lein
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Database connection
$servername = "gateway01.ap-northeast-1.prod.aws.tidbcloud.com";
$username = getenv("DB_USERNAME");
$password = getenv("DB_PASSWORD");
$dbname = "ecommerece";
$dbport = 4000;
$ssl_ca = __DIR__ . "/ca.pem";

$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, $ssl_ca, NULL, NULL);
mysqli_real_connect($conn, $servername, $username, $password, $dbname, $dbport, NULL, MYSQLI_CLIENT_SSL);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Invalid request.");
    }
    $id = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $stmt = $conn->prepare(
            "UPDATE sales SET status = IF(status='active','inactive','active') WHERE id = ?"
        );
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $msg = "Status update ho gaya.";
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM sales WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $msg = "Sale delete ho gayi.";
    }
}

$result = $conn->query("SELECT * FROM sales ORDER BY id DESC");
$conn->close();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Sales</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: "Poppins", sans-serif; background: linear-gradient(135deg,#1b2735,#2c3e50,#243b55); color:#eaeaea; margin:0; padding:30px; }
    h2 { text-align:center; }
    .box { max-width:1000px; margin:0 auto 20px; background:rgba(255,255,255,.06); padding:20px; border-radius:16px; }
    .top { display:flex; flex-wrap:wrap; gap:10px; justify-content:center; }
    .link, .btn { border:0; border-radius:8px; padding:9px 14px; color:#fff; cursor:pointer; text-decoration:none; font-size:14px; display:inline-block; }
    .blue { background:linear-gradient(90deg,#00c6ff,#0072ff); }
    .gold { background:linear-gradient(90deg,#f7971e,#c9801a); }
    .grey { background:rgba(255,255,255,.15); }
    .red { background:#c0392b; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px 8px; text-align:left; border-bottom:1px solid rgba(255,255,255,.1); }
    td form { display:inline; margin:0; }
    .badge { padding:3px 8px; border-radius:10px; font-size:12px; }
    .on { background:rgba(0,255,0,.2); color:#7dff7d; }
    .off { background:rgba(255,255,255,.12); color:#ccc; }
    .alert { background:rgba(0,255,0,.2); color:#7dff7d; text-align:center; padding:10px; border-radius:8px; }
    .table-wrap { overflow-x:auto; }
</style>
</head>
<body>

<h2><i class="fa-solid fa-fire"></i> Manage Sales</h2>

<?php if ($msg): ?>
    <div class="box alert"><?= e($msg) ?></div>
<?php endif; ?>

<div class="box top">
    <a class="link blue" href="add_sale.php"><i class="fa-solid fa-plus"></i> Add Global Sale</a>
    <a class="link gold" href="manage_product_sales.php"><i class="fa-solid fa-tags"></i> Product Wise Sales</a>
</div>

<div class="box table-wrap">
    <strong>Global Sales (sab products par)</strong>
    <table>
        <tr><th>Name</th><th>Discount</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($s = $result->fetch_assoc()): ?>
            <tr>
                <td><?= e($s['sale_name']) ?></td>
                <td><?= (int)$s['discount_percent'] ?>%</td>
                <td><?= e($s['start_date'] ?: '—') ?></td>
                <td><?= e($s['end_date'] ?: '—') ?></td>
                <td><span class="badge <?= $s['status'] === 'active' ? 'on' : 'off' ?>"><?= e($s['status']) ?></span></td>
                <td>
                    <form method="POST">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                        <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                        <button class="btn grey" name="action" value="toggle">
                            <?= $s['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                        </button>
                        <button class="btn red" name="action" value="delete" onclick="return confirm('Yeh sale delete karni hai?')">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6">Abhi koi global sale nahi hai.</td></tr>
        <?php endif; ?>
    </table>
</div>

</body>
</html>