
<?php
session_start();
include '../dbConfig.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {

  case 'get_products':
    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($products);
    break;

  case 'add_product':
    if (!isset($_SESSION['user'])) { echo json_encode(['error'=>'Not logged in']); exit; }
    $data = $_POST;
    $added_by = $_SESSION['user']['username'];

    $imgPath = '';
    if (!empty($_FILES['image']['name'])) {
      $imgPath = '../uploads/' . time() . '_' . basename($_FILES['image']['name']);
      move_uploaded_file($_FILES['image']['tmp_name'], $imgPath);
    }

    $stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data['name'], $data['category'], $data['price'], $imgPath, $added_by]);
    echo json_encode(['success'=>true]);
    break;

  case 'checkout':
    $data = json_decode(file_get_contents("php://input"), true);
    if (!$data) { echo json_encode(['error'=>'Invalid data']); exit; }

    $stmt = $pdo->prepare("INSERT INTO transactions (order_type, items, total, cashier) VALUES (?, ?, ?, ?)");
    $stmt->execute([$data['order_type'], json_encode($data['items']), $data['total'], $data['cashier']]);
    echo json_encode(['success'=>true]);
    break;

  case 'get_reports':
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;

    $sql = "SELECT * FROM transactions WHERE 1";
    $params = [];

    if ($start) {
      $sql .= " AND date_added >= ?";
      $params[] = $start;
    }
    if ($end) {
      $sql .= " AND date_added <= ?";
      $params[] = $end;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows);
    break;

  default:
    echo json_encode(['error'=>'Invalid action']);
}
