
<?php
session_start();
include '../dbConfig.php';

if (isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);
  $confirm  = trim($_POST['confirm']);

  if (empty($username) || empty($password) || empty($confirm)) {
    $error = "All fields are required.";
  } elseif ($password !== $confirm) {
    $error = "Passwords do not match.";
  } else {
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetchColumn() > 0) {
      $error = "Username already exists.";
    } else {
      $hashed = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("INSERT INTO users (username, password, role, date_added) VALUES (?, ?, 'superadmin', NOW())");
      $stmt->execute([$username, $hashed]);

      echo "
      <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
      <script>
      Swal.fire({
        icon: 'success',
        title: 'Superadmin Registered!',
        text: 'You can now log in to your account.',
        confirmButtonColor: '#28a745'
      }).then(() => {
        window.location = 'login.php';
      });
      </script>";
      exit;
    }
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register Superadmin - Whisk & Brew</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="../css/superadmin_register.css">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
  <div class="card shadow p-4" style="width: 400px;">
    <h4 class="text-center mb-3">Register Superadmin</h4>

    <?php if (!empty($error)): ?>
      <script>
        Swal.fire({
          icon: 'error',
          title: 'Registration Failed',
          text: '<?= htmlspecialchars($error) ?>',
          confirmButtonColor: '#d33'
        });
      </script>
    <?php endif; ?>

    <form method="POST">
      <div class="mb-2">
        <label class="form-label">Username</label>
        <input name="username" class="form-control" required>
      </div>
      <div class="mb-2">
        <label class="form-label">Password</label>
        <input name="password" type="password" class="form-control" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Confirm Password</label>
        <input name="confirm" type="password" class="form-control" required>
      </div>
      <button class="btn btn-success w-100">Register</button>
      <div class="text-center mt-3">
        <a href="login.php">Already have an account? Login</a>
      </div>
    </form>
  </div>
</body>
</html>
