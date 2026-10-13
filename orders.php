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

// Fetch all orders
$sql = "SELECT * FROM orders ORDER BY created_at DESC";
$result = $conn->query($sql);

$orders = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $row['items'] = !empty($row['items']) ? json_decode($row['items'], true) : [];
        $orders[] = $row;
    }
}

// Flash message
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Orders - Glamaura product</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" type="image/png" sizes="32x32" href="https://wristwin.shop/static/icon.png">
<link rel="icon" type="image/png" sizes="16x16" href="https://wristwin.shop/static/icon.png">
<style>
body {
    margin:0; padding:0; background: linear-gradient(135deg,#0f172a,#1e293b,#334155); color:#f8fafc;
    background-size:400% 400%; animation:gradientShift 15s ease infinite; min-height:100vh; overflow-x:hidden;
}
@keyframes gradientShift {0%{background-position:0% 50%;}50%{background-position:100% 50%;}100%{background-position:0% 50%;}}
header{text-align:center; padding:30px 20px;}
header h1 {font-size: 2.2em;font-weight: 700;text-transform: uppercase;letter-spacing: 2px;
    background: linear-gradient(90deg, #00c6ff, #0072ff);-webkit-background-clip: text;background-clip: text;-webkit-text-fill-color: transparent;color: transparent;margin: 0;}
.top-actions{display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; padding:0 30px 20px;}
.search-box{display:flex; align-items:center; gap:8px; background:rgba(255,255,255,0.1); padding:8px 12px; border-radius:10px; backdrop-filter:blur(8px);}
.search-box input{background:transparent; border:none; outline:none; color:#fff; padding:8px; font-size:15px; width:250px;}
.search-box input::placeholder{color:rgba(255,255,255,0.6);}
.search-box button{background:linear-gradient(90deg,#22c55e,#16a34a); border:none; color:white; padding:8px 14px; border-radius:8px; cursor:pointer; font-weight:600; transition:all 0.3s ease;}
.search-box button:hover{transform:scale(1.05);}
.btn-home{background:linear-gradient(90deg,#facc15,#eab308); color:#000; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:600; transition:all 0.3s ease;}
.btn-home:hover{transform:scale(1.05);}
.table-container{margin:0 30px; background:rgba(255,255,255,0.05); backdrop-filter:blur(10px); border-radius:14px; box-shadow:0 4px 30px rgba(0,0,0,0.3); overflow-x:auto; padding:10px;}
table{width:100%; border-collapse:collapse; min-width:1300px;}
th,td{padding:12px 16px; text-align:center; border-bottom:1px solid rgba(255,255,255,0.1); font-size:14px;}
th{background:rgba(255,255,255,0.08); color:#93c5fd; font-weight:600; text-transform:uppercase; letter-spacing:0.5px;}
tr:hover{background:rgba(255,255,255,0.08); transition:0.2s;}
.status-dropdown{padding:6px 10px; border-radius:8px; border:none; background:rgba(255,255,255,0.15); color:#fff; font-weight:600; cursor:pointer;}
.modal{display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(8px); z-index:999; justify-content:center; align-items:center;}
.modal-content{background:#f9fafb; color:#111; padding:25px; border-radius:10px; max-width:450px; width:90%; position:relative; box-shadow:0 4px 20px rgba(0,0,0,0.4);}
.modal-content h3{margin-top:0; color:#0f172a;}
.close-btn{position:absolute; top:10px; right:10px; background:red; border:none; color:white; font-weight:bold; border-radius:50%; width:28px; height:28px; cursor:pointer;}
.total-summary{margin:30px auto; text-align:center; background:rgba(255,255,255,0.08); padding:16px 25px; border-radius:12px; width:fit-content; font-size:18px; color:#facc15; font-weight:600; box-shadow:0 3px 10px rgba(0,0,0,0.3);}
.alert{margin:0 30px 15px; padding:10px; border-radius:8px; background:rgba(0,255,0,0.2); color:#0f0;}
</style>
</head>
<body>

<header><h1>Admin Orders Dashboard</h1></header>

<div class="top-actions">
    <div class="search-box">
        <input type="text" id="searchInput" placeholder="Search orders by customer, city, or status...">
        <button onclick="filterOrders()"><i class="fa-solid fa-search"></i> Search</button>
    </div>
    <a href="dashboard.php" class="btn-home"><i class="fa-solid fa-house"></i> Home</a>
</div>

<?php if($flash): ?>
<div class="alert"><?php echo $flash; ?></div>
<?php endif; ?>

<div class="table-container">
<table class="paginated-table" id="ordersTable">
<thead>
<tr>
    <th>ID</th>
    <th>Customer</th>
    <th>Email</th>
    <th>Contact</th>
    <th>Country</th>
    <th>City</th>
    <th>Postal</th>
    <th>Address</th>
    <th>Total</th>
    <th>Sale Status</th>
    <th>Payment</th>
    <th>Items</th>
    <th>Status</th>
    <th>Created At</th>
    <th>Action</th>
</tr>
</thead>
<tbody>
<?php if(count($orders)>0): ?>
<?php foreach($orders as $index=>$order): ?>
<tr>
<td><?php echo $index+1; ?></td>
<td><?php echo htmlspecialchars($order['user_name']); ?></td>
<td><?php echo htmlspecialchars($order['email']); ?></td>
<td><?php echo htmlspecialchars($order['phone']); ?></td>
<td><?php echo htmlspecialchars($order['country']); ?></td>
<td><?php echo htmlspecialchars($order['city']); ?></td>
<td><?php echo htmlspecialchars($order['postal']); ?></td>
<td><?php echo htmlspecialchars($order['address']); ?></td>
<td class="order-total"><?php echo $order['total']; ?></td>
<td><?php echo htmlspecialchars($order['sale_status']); ?></td>
<td><?php echo htmlspecialchars($order['payment_method']); ?></td>
<td>
<button class="btn-view" style="background:linear-gradient(90deg,#2563eb,#60a5fa);border:none;padding:6px 10px;border-radius:6px;color:white;cursor:pointer;"
onclick='viewItems(<?php echo json_encode($order["items"]); ?>)'><i class="fa-solid fa-eye"></i></button>
</td>
<td>
<select class="status-dropdown" onchange="updateStatus(<?php echo $order['id']; ?>, this.value)">
<option value="Pending" <?php echo ($order['status']=='Pending')?'selected':''; ?>>Pending</option>
<option value="Processing" <?php echo ($order['status']=='Processing')?'selected':''; ?>>Processing</option>
<option value="Shipped" <?php echo ($order['status']=='Shipped')?'selected':''; ?>>Shipped</option>
<option value="Completed" <?php echo ($order['status']=='Completed')?'selected':''; ?>>Completed</option>
<option value="Cancelled" <?php echo ($order['status']=='Cancelled')?'selected':''; ?>>Cancelled</option>
<option value="Returned" <?php echo ($order['status']=='Returned')?'selected':''; ?>>Returned</option>
</select>
</td>
<td><?php echo $order['created_at']; ?></td>
<td>
<button onclick="deleteOrder(<?php echo $order['id']; ?>)" 
style="background:linear-gradient(90deg,#ef4444,#dc2626);border:none;padding:6px 10px;border-radius:6px;color:white;cursor:pointer;">
<i class="fa-solid fa-trash"></i>
</button>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="15" style="color:#facc15;padding:18px;">No orders found.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

<div class="total-summary">Total Sales: PKR <span id="totalSales">0</span></div>

<!-- Modal -->
<div id="itemModal" class="modal">
<div class="modal-content">
<button class="close-btn" onclick="closeModal()">×</button>
<h3>Ordered product</h3>
<div id="itemsList"></div>
</div>
</div>

<script>
function viewItems(items){
    const container=document.getElementById('itemsList');
    container.innerHTML="";
    if(!items || items.length===0){
        container.innerHTML="<p>No items found in this order.</p>";
    } else {
        items.forEach(it=>container.innerHTML+=`<p><strong>${it.name}</strong> — ${it.quantity} pcs @ PKR ${it.display_price}</p>`);
    }
    document.getElementById('itemModal').style.display='flex';
}
function closeModal(){ document.getElementById('itemModal').style.display='none'; }

function updateStatus(orderId,newStatus){
    fetch(`update_order_status.php?id=${orderId}&status=${newStatus}`)
    .then(res=>res.json())
    .then(data=>{
        if(data.success) alert("Status updated successfully!");
        else alert("Failed to update status: "+(data.error||"Unknown"));
    })
    .catch(err=>alert("Error: "+err));
}

function deleteOrder(orderId){
    if(!confirm("Are you sure you want to delete this order?")) return;

    fetch(`delete_order.php?id=${orderId}`)
    .then(res=>res.json())
    .then(data=>{
        if(data.success){
            alert("Order deleted successfully!");
            location.reload();
        } else {
            alert("Failed to delete order: "+(data.error||"Unknown"));
        }
    })
    .catch(err=>alert("Error: "+err));
}

function calculateTotalSales(){
    const totals=document.querySelectorAll('.order-total');
    let sum=0;
    totals.forEach(td=>sum+=parseFloat(td.textContent)||0);
    document.getElementById('totalSales').textContent=sum.toLocaleString();
}

function filterOrders(){
    const filter=document.getElementById('searchInput').value.toLowerCase();
    const rows=document.querySelectorAll('.paginated-table tbody tr');
    rows.forEach(row=>{
        row.style.display=row.textContent.toLowerCase().includes(filter)?'':'none';
    });
    calculateTotalSales();
}

document.addEventListener("DOMContentLoaded",function(){
    calculateTotalSales();
});
</script>

</body>
</html>
