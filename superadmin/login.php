
<?php
session_start();
include '../dbConfig.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = $_POST['username'];
  $password = $_POST['password'];

  $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'superadmin'");
  $stmt->execute([$username]);
  $user = $stmt->fetch();

  if ($user && password_verify($password, $user['password'])) {
    if ($user['suspended']) {
      echo "<script>alert('Account suspended.'); window.location='login.php';</script>";
    } else {
      $_SESSION['user'] = $user;
      header("Location: index.php");
      exit;
    }
  } else {
    echo "<script>alert('Invalid credentials'); window.location='login.php';</script>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Superadmin Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="../css/superadmin_login.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
  <div class="card p-4 shadow" style="width: 350px;">
    <h4 class="text-center mb-3">Superadmin Login</h4>
    <form method="POST">
      <input name="username" class="form-control mb-2" placeholder="Username" required>
      <input name="password" type="password" class="form-control mb-3" placeholder="Password" required>
      <button class="btn btn-primary w-100">Login</button>
            <div class='text-center mt-3'>
        <a href='../superadmin/register.php'>Register as superadmin</a>
      </div> 
      <div class='text-center mt-3'>
        <a href='../admin/login.php'>Login as Admin</a>
      </div> 
    </form>
  </div>
</body>
</html>
