<?php
// Enable error reporting
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

// -------------------------------------------------------
// If no ID → redirect
// -------------------------------------------------------
if (!isset($_GET['id'])) {
    header("Location: admin.php");
    exit();
}

$id = intval($_GET['id']);

// -------------------------------------------------------
// Fetch existing Product record
// -------------------------------------------------------
$sql = "SELECT * FROM product WHERE id = ?";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("SQL Prepare Error: " . $conn->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$Product_item = $result->fetch_assoc();

if (!$Product_item) {
    echo "<script>alert('❌ Product not found!'); window.location='admin.php';</script>";
    exit();
}

// -------------------------------------------------------
// UPDATE Product Data (Form Submitted)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price       = floatval($_POST['price']);
    $sale_price  = floatval($_POST['sale_price']);
    $stock       = intval($_POST['stock']);
    $category    = trim($_POST['category']);

    if (empty($name) || empty($price)) {
        echo "<script>alert('❌ Name and Price required!');</script>";
    } else {

        // ----------- Image upload -----------
        $img_path = $Product_item['image']; // keep old if not replaced

        if (!empty($_FILES['image']['name'])) {

            $filename = time() . "_" . basename($_FILES['image']['name']);
            $upload_path = "uploads/" . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {

                // delete old
                if (!empty($Product_item['image']) && file_exists($Product_item['image'])) {
                    unlink($Product_item['image']);
                }

                $img_path = $upload_path;
            }
        }

        // ----------- Update Query -----------
        $update = "UPDATE product SET 
                    name=?, image=?, description=?, price=?, sale_price=?, 
                    stock=?, category=? WHERE id=?";

        $stmt2 = $conn->prepare($update);

        if (!$stmt2) {
            die("SQL Update Prepare Error: " . $conn->error);
        }

        $stmt2->bind_param(
            "sssdssss",
            $name,
            $img_path,
            $description,
            $price,
            $sale_price,
            $stock,
            $category,
            $id
        );

        if ($stmt2->execute()) {
            echo "<script>alert('✔ Product updated successfully!'); window.location='admin.php';</script>";
        } else {
            echo "<script>alert('❌ Update failed!');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Product</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">

<style>
    body {
        font-family: "Poppins", sans-serif;
        margin: 0;
        padding: 40px 20px;
        min-height: 100vh;
        background: linear-gradient(135deg, #1b2735, #2c3e50, #243b55);
        background-size: 400% 400%;
        animation: gradientFlow 18s ease infinite;
        display: flex;
        justify-content: center;
        align-items: flex-start;
        color: #eaeaea;
    }

    @keyframes gradientFlow {
        0% { background-position: 0% 50%; }
        50% { background-position: 100% 50%; }
        100% { background-position: 0% 50%; }
    }

    .form-container {
        background: rgba(255, 255, 255, 0.06);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 18px;
        padding: 40px 45px;
        max-width: 750px;
        width: 100%;
        box-shadow: 0 10px 35px rgba(0, 0, 0, 0.6);
    }

    h2 {
        text-align: center;
        color: #00c6ff;
        font-size: 28px;
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 25px;
        text-shadow: 0 0 18px rgba(0, 198, 255, 0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 20px 25px;
    }

    label {
        font-size: 13px;
        font-weight: 600;
        color: #f0f0f0;
        display: block;
        margin-bottom: 6px;
    }

    input, textarea, select {
        width: 100%;
        padding: 10px 12px;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.18);
        background: rgba(255,255,255,0.08);
        color: #fff;
        font-size: 14px;
        outline: none;
    }

    textarea {
        height: 90px;
        resize: none;
    }

    .current-image {
        grid-column: span 2;
        text-align: center;
        margin-top: 15px;
    }

    .current-image img {
        width: 120px;
        height: 120px;
        border-radius: 10px;
        object-fit: cover;
        box-shadow: 0 3px 10px rgba(0,0,0,0.3);
    }

    .btn-submit {
        grid-column: span 2;
        padding: 12px;
        background: linear-gradient(90deg, #00c6ff, #0072ff);
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        text-transform: uppercase;
        margin-top: 10px;
    }

    .btn-submit:hover {
        opacity: 0.9;
    }
</style>
</head>

<body>

    <div class="form-container">
        <h2><i class="fa-solid fa-pen-to-square"></i> Edit Product</h2>

        <form method="POST" enctype="multipart/form-data">

            <label>Product Name</label>
            <input type="text" name="name" value="<?= $Product_item['name'] ?>" required>

            <label>Price</label>
            <input type="number" step="0.01" name="price" value="<?= $Product_item['price'] ?>" required>

            <label>Sale Price</label>
            <input type="number" step="0.01" name="sale_price" value="<?= $Product_item['sale_price'] ?>">

            <label>Stock</label>
            <input type="number" name="stock" value="<?= $Product_item['stock'] ?>" required>

            <label>Category</label>
            <select name="category">
                <option value="Men"   <?= $Product_item['category'] == "Men" ? "selected" : "" ?>>Men</option>
                <option value="Women" <?= $Product_item['category'] == "Women" ? "selected" : "" ?>>Women</option>
            </select>

            <label>Description</label>
            <textarea name="description" required><?= $Product_item['description'] ?></textarea>

            <div class="current-image">
                <p>Current Image</p>
                <?php if (!empty($Product_item['image'])): ?>
                    <img src="<?= $Product_item['image'] ?>">
                <?php else: ?>
                    <p>No image uploaded</p>
                <?php endif; ?>
            </div>

            <label>Upload New Image (optional)</label>
            <input type="file" name="image" accept="image/*">

            <button class="btn-submit" type="submit">Update Product</button>

        </form>
    </div>

</body>
</html>
