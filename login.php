<?php
session_start();
include('./pages/db.php');

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['assigned_community_id'] = $user['assigned_community_id'];
            $_SESSION['login_success'] = "Welcome back, " . htmlspecialchars($user['username']) . "!";
            header("Location: pages/dashboard.php");
            exit;
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Ezza North Citizenship</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ===== LOGIN PAGE STYLING ===== */
body {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: #f4f6f9;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.login-container {
    background: #fff;
    padding: 40px 30px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 400px;
    text-align: center;
}

.login-container h2 {
    margin-bottom: 25px;
    color: #1e293b;
}

.login-container input {
    width: 100%;
    padding: 12px 15px;
    margin: 10px 0;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 1rem;
}

.login-container button {
    width: 100%;
    padding: 12px;
    background: #0ea5e9;
    border: none;
    border-radius: 8px;
    color: #fff;
    font-size: 1rem;
    cursor: pointer;
    margin-top: 10px;
}

.login-container button:hover {
    background: #0284c7;
}

.error-msg {
    color: #ef4444;
    margin-bottom: 10px;
    font-size: 0.9rem;
}
</style>
</head>
<body>


<?php if (isset($_SESSION['login_success'])): ?>
<script>
Swal.fire({
  title: 'Login Successful!',
  text: '<?= addslashes($_SESSION['login_success']); ?>',
  icon: 'success',
  confirmButtonColor: '#0ea5e9',
  timer: 2500,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['login_success']); endif; ?>

<?php if (isset($_SESSION['login_error'])): ?>
<script>
Swal.fire({
  title: 'Login Failed!',
  text: '<?= addslashes($_SESSION['login_error']); ?>',
  icon: 'error',
  confirmButtonColor: '#ef4444',
});
</script>
<?php unset($_SESSION['login_error']); endif; ?>


<div class="login-container">
    <h2>Ezza North LGA Login</h2>

    <?php if ($error): ?>
        <div class="error-msg"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required autofocus>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit" name="login">Login</button>
    </form>
</div>

</body>
</html>
