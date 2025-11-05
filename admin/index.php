
<?php
session_start();
include '../dbConfig.php';

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
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

  $_SESSION['flash'] = ['title' => 'Success!', 'text' => 'Product added successfully.', 'icon' => 'success'];
  $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by, date_added)
                         VALUES (?, ?, ?, ?, ?, NOW())");
  $stmt->execute([$name, $category, $price, $imagePath, $added_by]);
  header("Location: index.php");
  exit;
}

if (isset($_GET['delete'])) {
  $id = intval($_GET['delete']);
  $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]);
  $_SESSION['flash'] = ['title' => 'Deleted!', 'text' => 'Product deleted successfully.', 'icon' => 'success'];
  header("Location: index.php");
  exit;
}

if (isset($_POST['checkout'])) {
  $items = $_POST['items'];
  $total = $_POST['total'];
  $order_type = $_POST['order_type'];
  $cashier = $_SESSION['user']['username'];

  $stmt = $pdo->prepare("INSERT INTO transactions (items, total, order_type, cashier, date_added)
                         VALUES (?, ?, ?, ?, NOW())");
  $stmt->execute([$items, $total, $order_type, $cashier]);

  echo json_encode(['status' => 'success']);
  exit;
}

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';

$query = "SELECT * FROM transactions";
$params = [];

if (!empty($start) && !empty($end)) {
  $query .= " WHERE DATE(date_added) BETWEEN ? AND ?";
  $params = [$start, $end];
}

$query .= " ORDER BY date_added DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$products = $pdo->query("SELECT * FROM products ORDER BY date_added DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Haven & Crumb</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <link rel="stylesheet" href="../css/admin_index.css">


</head>
<body>

<?php if (isset($_SESSION['flash'])): ?>
<script>
Swal.fire({
  title: "<?= $_SESSION['flash']['title'] ?>",
  text: "<?= $_SESSION['flash']['text'] ?>",
  icon: "<?= $_SESSION['flash']['icon'] ?>",
  timer: 1500,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['flash']); endif; ?>

<div id="welcomeScreen" class="screen active">
  <img src="../images/logo.png" style="width:300px;height:auto;margin:0 auto 20px;">
  <h1>Welcome to Haven & Crumb</h1>
  <p style="max-width:600px;margin:0 auto;font-size:16px;font-style:italic;">
    Haven & Crumb is a warm little escape from the rush of everyday life — a place where comfort meets craft. Nestled in soft tones and the aroma of freshly baked pastries, it’s a haven for dreamers, readers, and coffee lovers alike. Every cup is brewed with care, and every pastry is made from scratch, golden and flaky just like the mornings we all crave. Whether you’re here to unwind with a latte, share stories over croissants, or simply enjoy a quiet moment to yourself, Haven & Crumb invites you to slow down, savor the sweetness, and feel at home in every sip and bite.
  </p>
  <button id="startOrderBtn" style="margin-top:20px;">Start Order</button>
</div>

<div id="orderTypeScreen" class="screen">
  <div>
    <h1>Choose Order Type</h1>
    <p>Select Order Type:</p>
    <div class="order-type-buttons">
      <button id="dineInBtn">Dine-In</button>
      <button id="takeOutBtn">Take-Out</button>
    </div>
  </div>
</div>


<div id="mainPOS" class="screen container text-start">
  <nav class="navbar navbar-light bg-white shadow-sm mb-3">
    <div class="container-fluid">
      <span class="navbar-brand">Haven & Crumb — Admin</span>
      <div class="d-flex align-items-center gap-3">
        <span class="text-muted"><?= htmlspecialchars($_SESSION['user']['username']) ?> (Admin)</span>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Logout</a>
      </div>
    </div>
  </nav>

  <div class="row">
    
    <div class="col-md-8">

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
                      <button class="btn btn-primary btn-sm addToCart"
                        data-id="<?= $p['id'] ?>"
                        data-name="<?= htmlspecialchars($p['name']) ?>"
                        data-price="<?= $p['price'] ?>">Add to order</button>
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
                  <td><a href="?delete=<?= $p['id'] ?>" class="btn btn-danger btn-sm deleteBtn">Delete</a></td>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const welcomeScreen=document.getElementById('welcomeScreen');
const orderTypeScreen=document.getElementById('orderTypeScreen');
const mainPOS=document.getElementById('mainPOS');
let orderType='';

document.getElementById('startOrderBtn').onclick=()=>{
  welcomeScreen.classList.remove('active');
  orderTypeScreen.classList.add('active');
};
document.getElementById('dineInBtn').onclick=()=>{
  orderType='Dine-In';
  orderTypeScreen.classList.remove('active');
  mainPOS.classList.add('active');
};
document.getElementById('takeOutBtn').onclick=()=>{
  orderType='Take-Out';
  orderTypeScreen.classList.remove('active');
  mainPOS.classList.add('active');
};

document.querySelectorAll('.deleteBtn').forEach(btn=>{
  btn.addEventListener('click', e=>{
    e.preventDefault();
    Swal.fire({title:'Delete this product?',text:'This cannot be undone.',icon:'warning',showCancelButton:true})
      .then(res=>{if(res.isConfirmed) window.location=btn.href;});
  });
});

let cart=[];
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
    const id=b.dataset.id,name=b.dataset.name,price=parseFloat(b.dataset.price),qty=parseInt(b.parentElement.querySelector('.qty').value);
    const ex=cart.find(i=>i.id===id);
    if(ex) ex.qty+=qty; else cart.push({id,name,price,qty});
    renderCart();
  }
});
document.getElementById('clearCartBtn').onclick=()=>{cart=[];renderCart();}

document.getElementById('checkoutBtn').onclick=()=>{
  if(!orderType){Swal.fire('Please select order type first!','','warning');return;}
  if(cart.length===0){Swal.fire('Empty','Add items first','warning');return;}
  const cash=parseFloat(document.getElementById('cashInput').value);
  const total=cart.reduce((a,b)=>a+b.price*b.qty,0);
  if(isNaN(cash)||cash<total){Swal.fire('Insufficient','Not enough cash','error');return;}
  const change=(cash-total).toFixed(2);
  Swal.fire({title:'Confirm Checkout?',text:`Order: ${orderType} | Total: ₱${total.toFixed(2)} | Change: ₱${change}`,icon:'question',showCancelButton:true})
  .then(res=>{
    if(res.isConfirmed){
      const data=new FormData();
      data.append('checkout','1');
      data.append('items',cart.map(i=>`${i.name} (${i.qty})`).join(', '));
      data.append('total',total);
      data.append('order_type',orderType);
      fetch('index.php',{method:'POST',body:data})
      .then(r=>r.json()).then(res=>{
        if(res.status==='success'){
          Swal.fire('Success',`Change: ₱${change}`,'success').then(()=>location.reload());
        }
      });
    }
  });
};

document.getElementById('exportPDF').onclick = () => {
  function decodeHtml(html) {
    const txt = document.createElement('textarea');
    txt.innerHTML = html;
    return txt.value;
  }

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF('p','mm','a4');
  doc.setFontSize(14);
  doc.text("Haven and Crumb - Admin Sales Report", 10, 10);

  let y = 20;
  doc.setFontSize(9);

  // X positions — tweak these until layout looks right
  const x = [10, 34, 60, 85, 120, 135, 150, 170, 185, 200, 215, 230];

  // Optional: print header row (use your real headers)
  const headers = ["Date","Time","Order Type","Items","Qty","Price","Total","Cashier","Pay Type","Ref","Disc","Net"];
  headers.forEach((h,i) => doc.text(h, x[i], y));
  y += 6;
  doc.line(10, y, 200, y);
  y += 5;

  document.querySelectorAll('#reportTable tbody tr').forEach(tr => {
    const tds = Array.from(tr.children).map(td => decodeHtml(td.innerHTML).trim());

    // skip empty rows
    if (!tds.length || tds.every(s => s === "")) return;

    // If this is TOTAL row, we'll handle later
    if (tds.join(' ').toLowerCase().includes('total')) {
      // store to use after loop
      window._pdf_total_row = tds;
      return;
    }

    for (let i = 0; i < tds.length; i++) {
      const text = tds[i] || "";
      if (i === 3) { // Items column -> wrap
        const wrapped = doc.splitTextToSize(text, 45); // width in mm
        doc.text(wrapped, x[i], y);
        // adjust max height increase depending on wrapped lines
        const lineCount = wrapped.length;
        if (lineCount > 1) {
          y += (lineCount - 1) * 4;
        }
      } else {
        doc.text(text, x[i], y);
      }
    }

    y += 6;
    if (y > 280) {
      doc.addPage();
      y = 15;
    }
  });

  // Draw TOTAL if found
  if (window._pdf_total_row) {
    y += 6;
    doc.setFontSize(10);
    doc.text("TOTAL", 10, y);
    const last = window._pdf_total_row[window._pdf_total_row.length - 1] || "";
    doc.text(last.replace(/[^0-9.,₱-]/g,''), 60, y);
  }

  doc.save("Admin_Sales_Report.pdf");
};


document.getElementById('viewReport').onclick=()=>{
  const tableHTML=document.getElementById('reportTable').outerHTML;
  Swal.fire({title:'Sales Report',html:`<div style='max-height:60vh;overflow:auto;'>${tableHTML}</div>`,width:'80%',showCloseButton:true});
};
</script>
</body>
</html>
