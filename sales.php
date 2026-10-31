<?php
session_start();

// ⚠️ Apne admin login ke hisab se yeh check badal lein
if (empty($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

// CSRF token
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
$msgClass = '';

function validPercent($p) {
    return ctype_digit((string)$p) && (int)$p >= 1 && (int)$p <= 100;
}

function saveSale($conn, $pid, $percent, $start, $end, $status) {
    $stmt = $conn->prepare(
        "INSERT INTO product_sales (product_id, discount_percent, start_date, end_date, status)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE discount_percent=VALUES(discount_percent),
            start_date=VALUES(start_date), end_date=VALUES(end_date), status=VALUES(status)"
    );
    $stmt->bind_param("sisss", $pid, $percent, $start, $end, $status);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        die("Invalid request.");
    }

    $action  = $_POST['action'] ?? '';
    $start   = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end     = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status  = ($_POST['status'] ?? 'active') === 'active' ? 'active' : 'inactive';
    $percent = $_POST['discount_percent'] ?? '';

    if ($start && $end && $end < $start) {
        $msg = "End date start date se pehle nahi ho sakti.";
        $msgClass = "danger";
    } elseif ($action === 'save') {
        $pid = (string)($_POST['product_id'] ?? '');
        if ($pid === '' || !validPercent($percent)) {
            $msg = "Discount 1 se 100 ke darmiyan hona chahiye.";
            $msgClass = "danger";
        } else {
            saveSale($conn, $pid, (int)$percent, $start, $end, $status);
            $msg = "Sale save ho gayi.";
            $msgClass = "success";
        }
    } elseif ($action === 'remove') {
        $pid = (string)($_POST['product_id'] ?? '');
        $stmt = $conn->prepare("DELETE FROM product_sales WHERE product_id = ?");
        $stmt->bind_param("s", $pid);
        $stmt->execute();
        $stmt->close();
        $msg = "Sale hata di gayi.";
        $msgClass = "success";
    } elseif ($action === 'bulk_apply') {
        if (!validPercent($percent)) {
            $msg = "Discount 1 se 100 ke darmiyan hona chahiye.";
            $msgClass = "danger";
        } else {
            $res = $conn->query("SELECT id FROM product");
            $n = 0;
            while ($r = $res->fetch_assoc()) {
                saveSale($conn, (string)$r['id'], (int)$percent, $start, $end, $status);
                $n++;
            }
            $msg = "$n products par sale laga di gayi.";
            $msgClass = "success";
        }
    } elseif ($action === 'bulk_remove') {
        $conn->query("DELETE FROM product_sales");
        $msg = "Tamam product sales hata di gayin.";
        $msgClass = "success";
    }
}

// Products + unki sale
$rows = $conn->query(
    "SELECT p.*, s.discount_percent AS sale_percent, s.start_date AS sale_start,
            s.end_date AS sale_end, s.status AS sale_status
     FROM product p
     LEFT JOIN product_sales s ON s.product_id = p.id
     ORDER BY p.id DESC"
);
$conn->close();

function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Product Sales</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
    body { font-family: "Poppins", sans-serif; background: linear-gradient(135deg,#1b2735,#2c3e50,#243b55); color:#eaeaea; margin:0; padding:30px; }
    h2 { text-align:center; }
    .box { max-width:1150px; margin:0 auto 20px; background:rgba(255,255,255,.06); padding:20px; border-radius:16px; }
    .bulk { display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end; }
    .bulk label { font-size:12px; display:block; margin-bottom:4px; }
    input, select { padding:8px; border-radius:8px; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.08); color:#fff; }
    select option { color:#000; }
    table { width:100%; border-collapse:collapse; }
    th, td { padding:10px 8px; text-align:left; border-bottom:1px solid rgba(255,255,255,.1); vertical-align:middle; }
    td form { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin:0; }
    td input[type=number] { width:70px; }
    .btn { border:0; border-radius:8px; padding:8px 12px; color:#fff; cursor:pointer; }
    .save { background:linear-gradient(90deg,#00c6ff,#0072ff); }
    .del { background:#c0392b; }
    .badge { padding:3px 8px; border-radius:10px; font-size:12px; }
    .on { background:rgba(0,255,0,.2); color:#7dff7d; }
    .off { background:rgba(255,255,255,.12); color:#ccc; }
    .alert { padding:10px; border-radius:8px; margin-bottom:15px; text-align:center; }
    .alert.success { background:rgba(0,255,0,.2); color:#7dff7d; }
    .alert.danger { background:rgba(255,0,0,.2); color:#ff8a8a; }
    .table-wrap { overflow-x:auto; }
</style>
</head>
<body>

<h2><i class="fa-solid fa-tags"></i> Product Wise Sales</h2>

<?php if ($msg): ?>
    <div class="box alert <?= e($msgClass) ?>"><?= e($msg) ?></div>
<?php endif; ?>

<div class="box">
    <strong>Sab products par ek saath sale</strong>
    <form method="POST" class="bulk" style="margin-top:10px">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div><label>Discount %</label><input type="number" name="discount_percent" min="1" max="100" required></div>
        <div><label>Start</label><input type="date" name="start_date"></div>
        <div><label>End</label><input type="date" name="end_date"></div>
        <div><label>Status</label>
            <select name="status"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <button class="btn save" name="action" value="bulk_apply" onclick="return confirm('Sab products par sale lag jayegi. Theek hai?')">Apply to All</button>
        <button class="btn del" name="action" value="bulk_remove" formnovalidate onclick="return confirm('Sab product sales hata di jayengi?')">Remove All</button>
    </form>
</div>

<div class="box table-wrap">
    <table>
        <tr><th>Product</th><th>Price</th><th>Current Sale</th><th>Set / Update Sale</th></tr>
        <?php while ($p = $rows->fetch_assoc()): ?>
        <tr>
            <td><?= e($p['name'] ?? '') ?></td>
            <td>Rs. <?= number_format((float)($p['price'] ?? 0), 2) ?></td>
            <td>
                <?php if ($p['sale_percent'] !== null): ?>
                    <span class="badge <?= $p['sale_status'] === 'active' ? 'on' : 'off' ?>">
                        <?= (int)$p['sale_percent'] ?>% — <?= e($p['sale_status']) ?>
                    </span>
                    <?php if ($p['sale_status'] === 'active'): ?>
                        <br><small>Sale price: Rs. <?= number_format(round((float)$p['price'] * (1 - $p['sale_percent'] / 100), 2), 2) ?></small>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge off">No sale</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="POST">
                    <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
                    <input type="hidden" name="product_id" value="<?= e($p['id']) ?>">
                    <input type="number" name="discount_percent" min="1" max="100" placeholder="%" value="<?= e($p['sale_percent'] ?? '') ?>" required>
                    <input type="date" name="start_date" value="<?= e($p['sale_start'] ?? '') ?>">
                    <input type="date" name="end_date" value="<?= e($p['sale_end'] ?? '') ?>">
                    <select name="status">
                        <option value="active" <?= ($p['sale_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($p['sale_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <button class="btn save" name="action" value="save">Save</button>
                    <?php if ($p['sale_percent'] !== null): ?>
                        <button class="btn del" name="action" value="remove" formnovalidate>Remove</button>
                    <?php endif; ?>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>

</body>
</html>