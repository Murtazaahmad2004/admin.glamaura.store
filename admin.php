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

// Fetch product
$sql = "SELECT * FROM product";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Product Admin Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body {
    margin:0; 
    padding:20px; 
    color:#fff; 
    background: radial-gradient(circle at top left, #0f2027, #203a43, #2c5364); 
    background-size:400% 400%; 
    animation:gradientShift 12s ease infinite; 
    font-family:'Poppins',sans-serif;
}
@keyframes gradientShift{
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}
h1{
    text-align:center; 
    margin-bottom:30px; 
    font-size:34px; 
    font-weight:700; 
    text-transform:uppercase; 
    color:#ffcc00; 
    border-bottom:3px solid #ffcc00; 
    display:inline-block; 
    padding-bottom:8px;
}
.table-container{
    background: rgba(255,255,255,0.08); 
    padding:10px; 
    border-radius:15px; 
    overflow-x:auto;
}
table{
    width:100%; 
    border-collapse:collapse;
}
th, td{
    padding:12px 14px; 
    text-align:center; 
    font-size:14px; 
    color:#fff; 
    border-bottom:1px solid rgba(255,255,255,0.15);
}
th{
    background: rgba(0,0,0,0.4); 
    color:#ffcc00;
}
td img{
    width:65px; 
    height:65px; 
    border-radius:8px; 
    object-fit:cover;
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
    background: rgba(255, 255, 255, 0.08);
    padding: 8px 12px;
    border-radius: 10px;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.4);
}
.search-box input {
    background: transparent;
    border: none;
    color: #fff;
    outline: none;
    font-size: 14px;
    width: 240px;
    padding: 6px 10px;
}
.btn {
    padding: 8px 14px;
    border-radius: 8px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}
.btn-success { background: linear-gradient(90deg, #00b09b, #96c93d); }
.btn:hover { transform: translateY(-3px); }
/* Action Buttons */
.btn-edit,
.btn-delete {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 6px;
    margin-right: 5px;
    text-decoration: none;
    color: white;
    font-size: 16px;
    cursor: pointer;
}

/* Edit Button (Yellow/Black like pencil icon) */
.btn-edit {
    background: #f1c40f;
}

/* Delete Button (Red) */
.btn-delete {
    background: #e74c3c;
}

.btn-edit:hover {
    background: #d4ac0d;
}

.btn-delete:hover {
    background: #c0392b;
}

/* Icons fixed size */
.btn-edit i,
.btn-delete i {
    font-size: 15px;
    color: white;
}

</style>
</head>
<body>

<h1>⌚ Product Admin Dashboard</h1>

<div class="top-actions">
    <div class="search-box">
        <form method="GET" id="searchForm">
            <input type="text" id="searchInput" placeholder="Search Product by name or price...">
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-magnifying-glass"></i> Search
            </button>
        </form>
    </div>
    <a href="watch.php" class="btn btn-success"><i class="fa-solid fa-plus"></i> Add Product</a>
    <a href="dashboard.php" class="btn btn-success"><i class="fa-solid fa-house"></i> Home</a>
</div>

<div class="table-container">
<table>
<thead>
<tr>
<th>ID</th>
<th>Image</th>
<th>Name</th>
<th>Description</th>
<th>Price</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php
if ($result && $result->num_rows > 0) {
    $i = 1;
    while ($w = $result->fetch_assoc()) {

    // Image URL is already stored in database
    // Supabase public URL
        $supabase_url = rtrim(
            getenv("SUPABASE_URL") ?: "",
            "/"
        );

        $bucket_name = "watches";

        if (!empty($w['image'])) {
            $imagePath = $w['image'];
        } else {
            $imagePath = "";
        }

    echo "<tr>";
    echo "<td>".$i++."</td>";

    echo "<td>
            <img src='".htmlspecialchars($imagePath)."' 
                 alt='".htmlspecialchars($w['name'])."'>
          </td>";

    echo "<td>".htmlspecialchars($w['name'])."</td>";
    echo "<td>".htmlspecialchars($w['description'])."</td>";
    echo "<td>".htmlspecialchars($w['price'])."</td>";

    echo "<td>
            <a class='btn-edit' href='edit_watch.php?id=".htmlspecialchars($w['id'])."'>
                <i class='fa fa-pen'></i>
            </a>

            <a class='btn-delete' 
               href='delete_watch.php?id=".htmlspecialchars($w['id'])."' 
               onclick='return confirm(\"Are you sure?\")'>
                <i class='fa fa-trash'></i>
            </a>
          </td>";

    echo "</tr>";
    }
} else {
    echo "<tr><td colspan='9' style='color:#ffcc00;'>No product found.</td></tr>";
}

?>
</tbody>
</table>
</div>

</body>
</html>
