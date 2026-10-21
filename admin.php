<?php
session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Database connection
$servername = "gateway01.ap-northeast-1.prod.aws.tidbcloud.com";
$username   = getenv("DB_USERNAME");
$password   = getenv("DB_PASSWORD");
$dbname     = "ecommerece";
$dbport     = 4000;

// TiDB Cloud TLS configuration
$ssl_ca = __DIR__ . "/ca.pem";

$conn = mysqli_init();

if (!$conn) {
    die("Database initialization failed.");
}

mysqli_ssl_set(
    $conn,
    null,
    null,
    $ssl_ca,
    null,
    null
);

$connected = mysqli_real_connect(
    $conn,
    $servername,
    $username,
    $password,
    $dbname,
    $dbport,
    null,
    MYSQLI_CLIENT_SSL
);

if (!$connected) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Safely escape values before displaying them in HTML
function e($value): string
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Fetch products
$sql = "SELECT * FROM product ORDER BY id DESC";
$result = $conn->query($sql);

if (!$result) {
    die("Product query failed: " . e($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Product Admin Dashboard</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;
            color: #fff;
            background: radial-gradient(
                circle at top left,
                #0f2027,
                #203a43,
                #2c5364
            );
            background-size: 400% 400%;
            animation: gradientShift 12s ease infinite;
            font-family: Arial, sans-serif;
        }

        @keyframes gradientShift {
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

        h1 {
            display: block;
            text-align: center;
            margin: 10px 0 30px;
            font-size: 34px;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffcc00;
            padding-bottom: 12px;
            border-bottom: 3px solid #ffcc00;
        }

        .top-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 30px;
        }

        .search-box form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            background: rgba(255, 255, 255, 0.08);
            padding: 8px 12px;
            border-radius: 10px;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
        }

        .search-box input {
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 14px;
            width: 240px;
            padding: 8px 10px;
        }

        .search-box input::placeholder {
            color: #ddd;
        }

        .btn {
            padding: 9px 14px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: linear-gradient(90deg, #00b09b, #96c93d);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .table-container {
            background: rgba(255, 255, 255, 0.08);
            padding: 10px;
            border-radius: 15px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 900px;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px 14px;
            text-align: center;
            font-size: 14px;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            vertical-align: middle;
        }

        th {
            background: rgba(0, 0, 0, 0.4);
            color: #ffcc00;
            white-space: nowrap;
        }

        .product-images {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            min-width: 90px;
            max-width: 300px;
            margin: 0 auto;
        }

        .product-images img {
            width: 65px;
            height: 65px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .no-image {
            color: #ffcc00;
            font-size: 12px;
        }

        .btn-edit,
        .btn-delete {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            margin: 3px;
            text-decoration: none;
            color: white;
            font-size: 15px;
            transition: 0.3s ease;
        }

        .btn-edit {
            background: #f1c40f;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-edit:hover {
            background: #d4ac0d;
        }

        .btn-delete:hover {
            background: #c0392b;
        }

        .empty-row {
            color: #ffcc00;
            padding: 25px;
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            h1 {
                font-size: 24px;
            }

            .search-box input {
                width: 180px;
            }
        }
    </style>
</head>

<body>

    <h1>⌚ Product Admin Dashboard</h1>

    <div class="top-actions">

        <div class="search-box">
            <form id="searchForm" onsubmit="return false;">
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search product by name or price..."
                    autocomplete="off"
                >

                <button type="button" class="btn btn-success" id="searchButton">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Search
                </button>
            </form>
        </div>

        <a href="watch.php" class="btn btn-success">
            <i class="fa-solid fa-plus"></i>
            Add Product
        </a>

        <a href="dashboard.php" class="btn btn-success">
            <i class="fa-solid fa-house"></i>
            Home
        </a>

    </div>

    <div class="table-container">

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Images</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody id="productTableBody">

                <?php if ($result->num_rows > 0): ?>

                    <?php
                    $i = 1;

                    while ($w = $result->fetch_assoc()):

                        $productId = (int)$w['id'];
                        $productName = $w['name'] ?? '';
                        $description = $w['description'] ?? '';
                        $price = $w['price'] ?? '';
                        $stock = $w['stock'] ?? '';

                        /*
                         * Fetch all images for this product.
                         * The image URLs should already be stored
                         * in product_images.image_url.
                         */
                        $productImages = [];

                        $image_stmt = $conn->prepare(
                            "SELECT image_url
                             FROM product_images
                             WHERE product_id = ?
                             ORDER BY id ASC"
                        );

                        if ($image_stmt) {

                            $image_stmt->bind_param("i", $productId);

                            if ($image_stmt->execute()) {

                                $image_result = $image_stmt->get_result();

                                while ($image_row = $image_result->fetch_assoc()) {

                                    $url = trim(
                                        (string)($image_row['image_url'] ?? '')
                                    );

                                    if (
                                        $url !== '' &&
                                        !in_array($url, $productImages, true)
                                    ) {
                                        $productImages[] = $url;
                                    }
                                }
                            }

                            $image_stmt->close();
                        }

                        /*
                         * Backward compatibility:
                         * If an older product has an image in the
                         * product table, include it as well.
                         */
                        $oldImage = trim(
                            (string)($w['image'] ?? '')
                        );

                        if (
                            $oldImage !== '' &&
                            !in_array($oldImage, $productImages, true)
                        ) {
                            array_unshift($productImages, $oldImage);
                        }
                    ?>

                        <tr class="product-row">

                            <td>
                                <?= $i++ ?>
                            </td>

                            <!-- All product images appear in this one cell -->
                            <td>
                                <div class="product-images">

                                    <?php if (!empty($productImages)): ?>

                                        <?php foreach ($productImages as $imagePath): ?>

                                            <img
                                                src="<?= e($imagePath) ?>"
                                                alt="<?= e($productName) ?>"
                                                loading="lazy"
                                                title="<?= e($productName) ?>"
                                            >

                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <span class="no-image">
                                            No image
                                        </span>

                                    <?php endif; ?>

                                </div>
                            </td>

                            <td class="product-name">
                                <?= e($productName) ?>
                            </td>

                            <td>
                                <?= e($description) ?>
                            </td>

                            <td class="product-price">
                                <?= e($price) ?>
                            </td>

                            <td>
                                <?= e($stock) ?>
                            </td>

                            <td>
                                <a
                                    class="btn-edit"
                                    href="edit_watch.php?id=<?= $productId ?>"
                                    title="Edit product"
                                >
                                    <i class="fa fa-pen"></i>
                                </a>

                                <a
                                    class="btn-delete"
                                    href="delete_watch.php?id=<?= $productId ?>"
                                    title="Delete product"
                                    onclick="return confirm('Are you sure you want to delete this product?');"
                                >
                                    <i class="fa fa-trash"></i>
                                </a>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="7" class="empty-row">
                            No product found.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>
        </table>

        <p id="noSearchResults" style="display:none; text-align:center; color:#ffcc00;">
            No matching product found.
        </p>

    </div>

    <script>
        const searchInput = document.getElementById("searchInput");
        const searchButton = document.getElementById("searchButton");
        const productRows = document.querySelectorAll(".product-row");
        const noSearchResults = document.getElementById("noSearchResults");

        function searchProducts() {
            const searchText = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            productRows.forEach(function(row) {
                const name = row.querySelector(".product-name")
                    ?.textContent.toLowerCase() || "";

                const price = row.querySelector(".product-price")
                    ?.textContent.toLowerCase() || "";

                const matches =
                    name.includes(searchText) ||
                    price.includes(searchText);

                row.style.display = matches ? "" : "none";

                if (matches) {
                    visibleCount++;
                }
            });

            noSearchResults.style.display =
                visibleCount === 0 && productRows.length > 0
                    ? "block"
                    : "none";
        }

        searchButton.addEventListener("click", searchProducts);

        searchInput.addEventListener("input", searchProducts);

        searchInput.addEventListener("keydown", function(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                searchProducts();
            }
        });
    </script>

</body>
</html>

<?php
$conn->close();
?>