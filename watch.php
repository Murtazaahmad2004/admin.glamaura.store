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

$ssl_ca = __DIR__ . "/ca.pem";

$conn = mysqli_init();

mysqli_ssl_set(
    $conn,
    NULL,
    NULL,
    $ssl_ca,
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

// Supabase configuration
$supabase_url = getenv("SUPABASE_URL");
$supabase_key = getenv("SUPABASE_SECRET_KEY");

if (!$supabase_url || !$supabase_key) {
    die("Supabase environment variables are missing.");
}

$supabase_url = rtrim($supabase_url, "/");
$bucket_name = "watches";

// Allowed image types
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = $_POST['price'] ?? 0;

    if (
        $name === '' ||
        $description === '' ||
        $price <= 0 ||
        !isset($_FILES['image'])
    ) {
        $_SESSION['message'] = "❌ Please fill all required fields.";
        header("Location: watch.php");
        exit;
    }

    $img_path = null;

    if (
        isset($_FILES['image']) &&
        $_FILES['image']['error'] === UPLOAD_ERR_OK
    ) {
        $file_name = $_FILES['image']['name'];
        $tmp_name = $_FILES['image']['tmp_name'];
        $file_size = $_FILES['image']['size'];

        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_extensions, true)) {
            $_SESSION['message'] = "❌ Invalid image type!";
            header("Location: watch.php");
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);

        if (!in_array($mime_type, $allowed_mime_types, true)) {
            $_SESSION['message'] = "❌ Invalid image file!";
            header("Location: watch.php");
            exit;
        }

        $max_file_size = 5 * 1024 * 1024;

        if ($file_size > $max_file_size) {
            $_SESSION['message'] = "❌ Image must be less than 5 MB.";
            header("Location: watch.php");
            exit;
        }

        $new_filename = "Product_" . bin2hex(random_bytes(16)) . "." . $ext;
        $storage_path = $new_filename;

        $file_contents = file_get_contents($tmp_name);

        if ($file_contents === false) {
            $_SESSION['message'] = "❌ Could not read uploaded image.";
            header("Location: watch.php");
            exit;
        }

        $upload_url = $supabase_url . "/storage/v1/object/" . $bucket_name . "/" . $storage_path;

        $ch = curl_init($upload_url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $file_contents);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $supabase_key,
            "apikey: " . $supabase_key,
            "Content-Type: " . $mime_type,
            "Cache-Control: 3600"
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);

        curl_close($ch);

        if ($response === false || $curl_error) {
            $_SESSION['message'] = "❌ Image upload failed: " . $curl_error;
            header("Location: watch.php");
            exit;
        }

        if ($http_code < 200 || $http_code >= 300) {
            $_SESSION['message'] = "❌ Supabase upload failed. HTTP Code: " . $http_code . " Response: " . $response;
            header("Location: watch.php");
            exit;
        }

        $img_path = $supabase_url . "/storage/v1/object/public/" . $bucket_name . "/" . $storage_path;
    }

    $stmt = $conn->prepare(
        "INSERT INTO product (name, image, description, price)
        VALUES (?, ?, ?, ?)"
    );

    if (!$stmt) {
        $_SESSION['message'] = "❌ Database prepare error: " . $conn->error;
        header("Location: watch.php");
        exit;
    }

    $stmt->bind_param(
        "sssd",
        $name,
        $img_path,
        $description,
        $price
    );

    if ($stmt->execute()) {
        $_SESSION['message'] = "✅ Product added successfully!";
        $stmt->close();
        $conn->close();
        header("Location: admin.php");
        exit;
    } else {
        $_SESSION['message'] = "❌ Database error: " . $stmt->error;
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
    <title>Add product</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

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
            overflow-x: hidden;
        }

        @keyframes gradientFlow {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        .form-container {
            margin-top: 50px;
            background: rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 18px;
            padding: 40px 45px;
            max-width: 750px;
            width: 100%;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.6s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        input,
        textarea,
        select {
            width: 350px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: 0.3s;
        }

        input:focus,
        textarea:focus,
        select:focus {
            border-color: #00c6ff;
            box-shadow: 0 0 6px rgba(0, 198, 255, 0.4);
        }

        textarea {
            resize: none;
            height: 90px;
        }

        .btn-group {
            grid-column: span 2;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 10px;
        }

        .btn-submit {
            flex: 1;
            max-width: 250px;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            border: none;
            background: linear-gradient(90deg, #00c6ff, #0072ff);
            color: #fff;
            box-shadow: 0 5px 20px rgba(0, 198, 255, 0.35);
            transition: 0.3s;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 198, 255, 0.45);
        }

        .btn-back {
            flex: 1;
            max-width: 250px;
            text-align: center;
            padding: 12px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
        }

        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        @media(max-width: 600px) {
            form {
                grid-template-columns: 1fr;
            }

            .btn-group {
                grid-column: span 1;
                flex-direction: column;
            }

            .btn-submit,
            .btn-back {
                max-width: 100%;
                width: 100%;
            }

            input,
            textarea,
            select {
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
</head>

<body>

    <?php
    if (isset($_SESSION['message'])) {
        echo '<div class="alert" style="position:fixed;top:20px;right:20px;background:rgba(0,0,0,0.7);color:#00ff00;padding:12px 18px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);z-index:1000;">' .
            htmlspecialchars($_SESSION['message']) .
            '</div>';

        unset($_SESSION['message']);
    }
    ?>

    <div class="form-container">
        <h2>
            <i class="fa-solid fa-clock"></i>
            Add New Product
        </h2>

        <form method="POST" enctype="multipart/form-data">
            <div>
                <label for="name">
                    <i class="fa-solid fa-font"></i>
                    Product Name
                </label>
                <input type="text" name="name" id="name" placeholder="Enter Product Name" required>
            </div>

            <div>
                <label for="price">
                    <i class="fa-solid fa-tag"></i>
                    Price
                </label>
                <input type="number" step="0.01" name="price" id="price" placeholder="Enter Price" required>
            </div>

            <div style="grid-column: span 2;">
                <label for="description">
                    <i class="fa-solid fa-align-left"></i>
                    Description
                </label>
                <textarea name="description" id="description" placeholder="Enter Description" required></textarea>
            </div>

            <div style="grid-column: span 2;">
                <label for="image">
                    <i class="fa-solid fa-image"></i>
                    Product Image
                </label>
                <input type="file" name="image" id="image" accept="image/*" required>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-submit">
                    <i class="fa-solid fa-upload"></i>
                    Upload Product
                </button>

                <a href="admin.php" class="btn-back">
                    <i class="fa-solid fa-arrow-left"></i>
                    Back to List
                </a>
            </div>
        </form>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const alerts = document.querySelectorAll(".alert");

            alerts.forEach(alert => {
                setTimeout(() => alert.remove(), 3000);
            });
        });
    </script>
</body>
</html>