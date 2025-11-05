
<?php
session_start();
include '../dbConfig.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'superadmin') {
  header("Location: login.php");
  exit;
}

if (isset($_POST['add_product'])) {
  $name = trim($_POST['name']);
  $category = trim($_POST['category']);
  $price = floatval($_POST['price']);
  $added_by = $_SESSION['user']['username'];
  $imagePath = '';

  if (!empty($_FILES['image']['name'])) {
    $uploadDir = '../uploads/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
    $fileName = time() . '_' . basename($_FILES['image']['name']);
    $target = $uploadDir . $fileName;
    if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
      $imagePath = 'uploads/' . $fileName;
    }
  }

  $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by, date_added)
                         VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$name, $category, $price, $imagePath, $added_by]);
  $_SESSION['swal'] = ['Product Added', 'New product successfully added!', 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $_SESSION['swal'] = ['Deleted', 'Product deleted successfully!', 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_POST['create_admin'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);
  if ($username && $password) {
    $check = $pdo->prepare("SELECT * FROM users WHERE username=?");
    $check->execute([$username]);
    if ($check->fetch()) {
      $_SESSION['swal'] = ['Error', 'Username already exists!', 'error'];
    } else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $pdo->prepare("INSERT INTO users (username, password, role, suspended, date_added)
                     VALUES (?, ?, 'admin', 0, NOW())")->execute([$username, $hashed]);
      $_SESSION['swal'] = ['Admin Created', 'New admin account created!', 'success'];
    }
  }
  header("Location: index.php");
  exit;
}

if (isset($_GET['toggle'])) {
  $id = intval($_GET['toggle']);
  $user = $pdo->query("SELECT suspended FROM users WHERE id=$id")->fetch();
  if ($user) {
    $new = $user['suspended'] ? 0 : 1;
    $pdo->prepare("UPDATE users SET suspended=? WHERE id=?")->execute([$new, $id]);
    $_SESSION['swal'] = ['Updated', 'User suspension status changed.', 'success'];
  }
  header("Location: index.php");
  exit;
}

if (isset($_POST['checkout'])) {
  $items = $_POST['items'];
  $total = $_POST['total'];
  $order_type = $_POST['order_type'] ?? 'Dine-In';
  $cashier = $_SESSION['user']['username'];

  $stmt = $pdo->prepare("INSERT INTO transactions (items, total, order_type, cashier, date_added)
                         VALUES (?, ?, ?, ?, NOW())");
  $stmt->execute([$items, $total, $order_type, $cashier]);

  echo json_encode(['status' => 'success']);
  exit;
}

$where = "";
if (!empty($_GET['start']) && !empty($_GET['end'])) {
  $start = $_GET['start'] . " 00:00:00";
  $end = $_GET['end'] . " 23:59:59";
  $where = "WHERE date_added BETWEEN '$start' AND '$end'";
}

$products = $pdo->query("SELECT * FROM products ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
$users = $pdo->query("SELECT * FROM users ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
$transactions = $pdo->query("SELECT * FROM transactions $where ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Superadmin Dashboard - Haven & Crumb</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <link rel="stylesheet" href="../css/superadmin_index.css">
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm mb-3">
  <div class="container-fluid">
    <span class="navbar-brand">Haven & Crumb — SuperAdmin</span>
    <div class="d-flex align-items-center gap-3">
      <span class="text-muted"><?= htmlspecialchars($_SESSION['user']['username']) ?> (SuperAdmin)</span>
      <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
    </div>
  </div>
</nav>

<div class="container">
  <div class="row">
    <div class="col-md-8">
      <div class="card mb-3">
        <div class="card-header"><strong>Order Type</strong></div>
        <div class="card-body text-center">
          <div class="btn-group" role="group">
            <button id="dineInBtn" class="btn btn-primary">Dine-In</button>
            <button id="takeOutBtn" class="btn btn-outline-primary">Take-Out</button>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header"><strong>Menu</strong></div>
        <div class="card-body">
          <div class="row g-3">
            <?php foreach ($products as $p): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card">
                  <img src="../<?= htmlspecialchars($p['image']) ?>" class="product-img" onerror="this.src='https://placehold.co/300x200?text=No+Image'">
                  <div class="card-body">
                    <h5><?= htmlspecialchars($p['name']) ?></h5>
                    <p class="small text-muted"><?= htmlspecialchars($p['category']) ?> · Added by: <?= htmlspecialchars($p['added_by']) ?></p>
                    <p><strong>₱<?= number_format($p['price'], 2) ?></strong></p>
                    <div class="d-flex gap-2">
                      <input type="number" class="form-control form-control-sm qty" min="1" value="1">
                      <button class="btn btn-primary btn-sm addToCart" data-name="<?= htmlspecialchars($p['name']) ?>" data-price="<?= $p['price'] ?>">Add to order</button>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Products Management</span>
          <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">Add Product</button>
        </div>
        <div class="card-body">
          <table class="table table-sm align-middle">
            <thead><tr><th>Img</th><th>Name</th><th>Category</th><th>Price</th><th>Added By</th><th>Date Added</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($products as $p): ?>
                <tr>
                  <td><img src="../<?= htmlspecialchars($p['image']) ?>" class="small-img" onerror="this.src='https://placehold.co/80x60?text=No+Img'"></td>
                  <td><?= htmlspecialchars($p['name']) ?></td>
                  <td><?= htmlspecialchars($p['category']) ?></td>
                  <td>₱<?= number_format($p['price'],2) ?></td>
                  <td><?= htmlspecialchars($p['added_by']) ?></td>
                  <td><?= date('m/d/Y, g:i:s A', strtotime($p['date_added'])) ?></td>
                  <td><a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm delBtn">Delete</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>User Management (SuperAdmin)</span>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addAdminModal">Create Admin</button>
        </div>
        <div class="card-body">
          <table class="table table-sm align-middle">
            <thead><tr><th>Username</th><th>Role</th><th>Suspended</th><th>Date Added</th><th>Actions</th></tr></thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><?= htmlspecialchars($u['username']) ?></td>
                  <td><?= htmlspecialchars($u['role']) ?></td>
                  <td><?= $u['suspended'] ? 'Yes' : 'No' ?></td>
                  <td><?= date('m/d/Y, g:i:s A', strtotime($u['date_added'])) ?></td>
                  <td>
                    <?php if ($u['role'] == 'admin'): ?>
                      <a href="?toggle=<?= $u['id'] ?>" class="btn btn-sm btn-<?= $u['suspended'] ? 'success' : 'danger' ?>">
                        <?= $u['suspended'] ? 'Unsuspend' : 'Suspend' ?>
                      </a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-md-4 order-panel">
      <div class="card mb-3">
        <div class="card-header"><strong>Ordered Items</strong></div>
        <div class="card-body">
          <div id="cartList" class="small text-muted" style="min-height:150px;">Cart is empty</div>
          <div class="d-flex justify-content-between mt-3">
            <strong>Total:</strong> <strong id="cartTotal">₱0.00</strong>
          </div>
          <div class="mt-3">
            <input id="cashInput" class="form-control mb-2" placeholder="Enter amount here" type="number" min="0" step="0.01">
            <div class="d-grid gap-2">
              <button id="checkoutBtn" class="btn btn-success">Checkout</button>
              <button id="clearCartBtn" class="btn btn-outline-secondary">Clear</button>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><strong>Reports</strong></div>
        <div class="card-body">
          <form class="mb-3 d-flex flex-column gap-2" method="GET">
            <input type="date" name="start" value="<?= $_GET['start'] ?? '' ?>" class="form-control form-control-sm">
            <input type="date" name="end" value="<?= $_GET['end'] ?? '' ?>" class="form-control form-control-sm">
            <div class="d-flex gap-2">
              <button class="btn btn-primary btn-sm">Filter</button>
              <button type="button" id="exportPDF" class="btn btn-outline-secondary btn-sm">Export PDF</button>
              <button type="button" id="viewReport" class="btn btn-outline-success btn-sm">View</button>
            </div>
          </form>
          <div style="max-height:200px; overflow:auto;">
            <table class="table table-sm" id="reportTable">
              <thead><tr><th>Date</th><th>Order Type</th><th>Items</th><th>Amount</th><th>Cashier</th></tr></thead>
              <tbody>
                <?php $total=0; foreach ($transactions as $t): $total += $t['total']; ?>
                  <tr>
                    <td><?= date('m/d/Y, g:i:s A', strtotime($t['date_added'])) ?></td>
                    <td><?= htmlspecialchars($t['order_type']) ?></td>
                    <td><?= htmlspecialchars($t['items']) ?></td>
                    <td>₱<?= number_format($t['total'],2) ?></td>
                    <td><?= htmlspecialchars($t['cashier']) ?></td>
                  </tr>
                <?php endforeach; ?>
                <tr class="table-info"><td colspan="3"><strong>TOTAL</strong></td><td colspan="2"><strong>₱<?= number_format($total,2) ?></strong></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Add Product</h5></div>
      <div class="modal-body">
        <input name="name" class="form-control mb-2" placeholder="Name" required>
        <input name="category" class="form-control mb-2" placeholder="Category" required>
        <input name="price" type="number" class="form-control mb-2" placeholder="Price" required>
        <input name="image" type="file" class="form-control mb-2" accept="image/*" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" name="add_product">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- ADD ADMIN MODAL -->
<div class="modal fade" id="addAdminModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Create Admin</h5></div>
      <div class="modal-body">
        <input name="username" class="form-control mb-2" placeholder="Username" required>
        <input name="password" type="password" class="form-control mb-2" placeholder="Password" required>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" name="create_admin">Create</button>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if (!empty($_SESSION['swal'])): ?>
Swal.fire('<?= $_SESSION['swal'][0] ?>','<?= $_SESSION['swal'][1] ?>','<?= $_SESSION['swal'][2] ?>');
<?php unset($_SESSION['swal']); endif; ?>

// CART + ORDER TYPE SYSTEM
let cart = [];
let orderType = 'Dine-In';
const dineBtn = document.getElementById('dineInBtn');
const takeBtn = document.getElementById('takeOutBtn');

dineBtn.addEventListener('click',()=>{orderType='Dine-In';dineBtn.classList.replace('btn-outline-primary','btn-primary');takeBtn.classList.replace('btn-primary','btn-outline-primary');});
takeBtn.addEventListener('click',()=>{orderType='Take-Out';takeBtn.classList.replace('btn-outline-primary','btn-primary');dineBtn.classList.replace('btn-primary','btn-outline-primary');});

function renderCart(){
  const list=document.getElementById('cartList');
  const totalEl=document.getElementById('cartTotal');
  list.innerHTML=''; let total=0;
  if(cart.length===0){list.innerHTML='<div class="small text-muted">Cart is empty</div>';}
  cart.forEach((i,idx)=>{total+=i.price*i.qty;
    const div=document.createElement('div');
    div.className='d-flex justify-content-between mb-1';
    div.innerHTML=`<div><strong>${i.name}</strong><br><small>₱${i.price.toFixed(2)} × ${i.qty}</small></div>
    <div><button class='btn btn-sm btn-outline-secondary' onclick='changeQty(${idx},-1)'>-</button>
    <button class='btn btn-sm btn-outline-secondary' onclick='changeQty(${idx},1)'>+</button>
    <button class='btn btn-sm btn-outline-danger' onclick='removeItem(${idx})'>x</button></div>`;
    list.appendChild(div);
  });
  totalEl.textContent='₱'+total.toFixed(2);
}
function changeQty(i,d){cart[i].qty+=d;if(cart[i].qty<=0)cart.splice(i,1);renderCart();}
function removeItem(i){cart.splice(i,1);renderCart();}
document.querySelectorAll('.addToCart').forEach(b=>{
  b.onclick=()=>{
    const name=b.dataset.name, price=parseFloat(b.dataset.price), qty=parseInt(b.parentElement.querySelector('.qty').value);
    const ex=cart.find(i=>i.name===name);
    if(ex) ex.qty+=qty; else cart.push({name,price,qty});
    renderCart();
  }
});
document.getElementById('clearCartBtn').onclick=()=>{cart=[];renderCart();}

document.getElementById('checkoutBtn').onclick=()=>{
  if(cart.length===0){Swal.fire('Empty','Add items first','warning');return;}
  const cash=parseFloat(document.getElementById('cashInput').value);
  const total=cart.reduce((a,b)=>a+b.price*b.qty,0);
  if(isNaN(cash)||cash<total){Swal.fire('Insufficient','Not enough cash','error');return;}
  const change=(cash-total).toFixed(2);

  const itemsText=cart.map(i=>`${i.name} (${i.qty})`).join(', ');
  const fd=new FormData();
  fd.append('checkout',1);
  fd.append('items',itemsText);
  fd.append('total',total);
  fd.append('order_type',orderType);

  fetch('', {method:'POST', body:fd})
  .then(r=>r.json())
  .then(res=>{
    if(res.status==='success'){
      Swal.fire('Paid',`Change: ₱${change}<br>Order Type: <strong>${orderType}</strong>`,'success');
      cart=[];renderCart();document.getElementById('cashInput').value='';
    }
  });
};

// EXPORT PDF
document.getElementById('exportPDF').addEventListener('click',()=>{
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.setFontSize(14);
  doc.text("Haven & Crumb - Sales Report", 10, 10);
  let y=20;
  document.querySelectorAll("#reportTable tbody tr").forEach(tr=>{
    doc.setFontSize(10);
    const rowText = Array.from(tr.children).map(td=>td.innerText).join(" | ");
    doc.text(rowText, 10, y);
    y+=8;
    if(y>280){ doc.addPage(); y=10; }
  });
  doc.save("Superadmin_Sales_Report.pdf");
});

// VIEW FULL REPORT - SweetAlert
document.getElementById('viewReport').addEventListener('click', () => {
  const tableHTML = document.getElementById('reportTable').outerHTML;
  Swal.fire({
    title: 'Full Sales Report',
    html: `<div style="max-height:60vh; overflow:auto;">${tableHTML}</div>`,
    width: '80%',
    showCloseButton: true,
    confirmButtonText: 'Close',
    customClass: { popup: 'p-0' }
  });
});
</script>
</body>
</html>
