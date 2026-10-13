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

// ✅ Flash message handling
$flash_message = '';
$flash_class = '';

// ✅ Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['sale_name'] ?? '');
    $percent = trim($_POST['discount_percent'] ?? '');
    $start = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $status = $_POST['status'] ?? 'inactive';

    // Validation
    if (empty($name) || empty($percent)) {
        $flash_message = "Sale name and discount percent are required.";
        $flash_class = "danger";
    } elseif (!is_numeric($percent) || intval($percent) < 1 || intval($percent) > 100) {
        $flash_message = "Discount percent must be an integer between 1 and 100.";
        $flash_class = "danger";
    } else {
        // ✅ Insert into database
        $stmt = $conn->prepare("INSERT INTO sales (sale_name, discount_percent, start_date, end_date, status) VALUES (?, ?, ?, ?, ?)");
        $percent_int = intval($percent);
        $stmt->bind_param("sisss", $name, $percent_int, $start, $end, $status);

        if ($stmt->execute()) {
            $flash_message = "Sale added successfully.";
            $flash_class = "success";
        } else {
            $flash_message = "Error: " . $stmt->error;
            $flash_class = "danger";
        }

        $stmt->close();
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Sale</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">
    <style>
        body {
            font-family: "Poppins", sans-serif;
            background: linear-gradient(135deg, #1b2735, #2c3e50, #243b55);
            color: #eaeaea;
            padding: 40px;
            margin: 0;
        }

        .form-container {
            max-width: 700px;
            margin: auto;
            background: rgba(255, 255, 255, 0.06);
            padding: 40px;
            border-radius: 18px;
            backdrop-filter: blur(14px);
        }

        h2 {
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        label {
            display: block;
            margin-bottom: 6px;
            margin-top: 12px;
        }

        input,
        select {
            width: 100%;
            padding: 10px;
            margin-bottom: 5px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        input::placeholder {
            color: #d0d0d0;
        }

        .btn-submit {
            background: linear-gradient(90deg, #00c6ff, #0072ff);
            color: #fff;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
        }

        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 15px;
            display: inline-block;
            text-align: center;
        }

        .button-row {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        .alert {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 8px;
            font-weight: 500;
        }

        .alert.success {
            background-color: rgba(0, 255, 0, 0.2);
            color: #0f0;
        }

        .alert.danger {
            background-color: rgba(255, 0, 0, 0.2);
            color: #f00;
        }
    </style>
</head>

<body>

<div class="form-container">
    <h2><i class="fa-solid fa-tag"></i> Add New Sale</h2>

    <?php if (!empty($flash_message)): ?>
        <div class="alert <?php echo $flash_class; ?>">
            <?php echo $flash_message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        
        <label for="sale_name">
            <i class="fa-solid fa-font"></i> Sale Name
        </label>
        <input type="text" name="sale_name" id="sale_name" placeholder="Enter Sale Name" required>

        <label for="discount_percent">
            <i class="fa-solid fa-percent"></i> Discount Percent (%)
        </label>
        <input type="number" name="discount_percent" id="discount_percent" placeholder="e.g. 50" min="1" max="100" required>

        <label for="start_date">
            <i class="fa-solid fa-calendar-days"></i> Start Date
        </label>
        <input type="date" name="start_date" id="start_date">

        <label for="end_date">
            <i class="fa-solid fa-calendar-check"></i> End Date
        </label>
        <input type="date" name="end_date" id="end_date">

        <label for="status">
            <i class="fa-solid fa-power-off"></i> Status
        </label>
        <select name="status" id="status">
            <option value="inactive" selected>Inactive</option>
            <option value="active">Active</option>
        </select>

        <div class="button-row">
            <button type="submit" class="btn-submit">
                <i class="fa-solid fa-save"></i> Save Sale
            </button>

            <a href="manage_sales.php" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>
    </form>
</div>

</body>
</html>
