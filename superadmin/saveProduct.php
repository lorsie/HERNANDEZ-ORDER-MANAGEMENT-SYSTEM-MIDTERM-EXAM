
<?php
session_start();
include '../dbConfig.php';

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success'=>false,'message'=>'Invalid data']); exit; }

$stmt = $pdo->prepare("INSERT INTO products (name, category, price, image, added_by) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$data['name'], $data['cat'], $data['price'], $data['img'], $_SESSION['user']['username']]);

echo json_encode(['success'=>true]);
