<?php
session_start();
include('./pages/db.php');

$error = '';
$success = '';

if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    $assigned_community_id = $_POST['assigned_community_id'] ?? null;

    // Check if username exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $error = "Username already exists.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $insert = $conn->prepare("INSERT INTO users (full_name, username, password_hash, role, assigned_community_id, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("sssis", $full_name, $username, $password_hash, $role, $assigned_community_id);
        if ($insert->execute()) {
            $success = "User registered successfully.";
        } else {
            $error = "Error registering user.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register User - Ezza North LGA</title>
<link rel="stylesheet" href="assets/css/dashboard.css">
<style>
/* ===== REGISTER PAGE STYLING ===== */
body {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: #f4f6f9;
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.register-container {
    background: #fff;
    padding: 40px 30px;
    border-radius: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.1);
    width: 100%;
    max-width: 450px;
    text-align: center;
}

.register-container h2 {
    margin-bottom: 25px;
    color: #1e293b;
}

.register-container input, .register-container select {
    width: 100%;
    padding: 12px 15px;
    margin: 10px 0;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    font-size: 1rem;
}

.register-container button {
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

.register-container button:hover {
    background: #0284c7;
}

.success-msg {
    color: #16a34a;
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.error-msg {
    color: #ef4444;
    margin-bottom: 10px;
    font-size: 0.95rem;
}
</style>
</head>
<body>

<div class="register-container">
    <h2>Register New User</h2>

    <?php if ($error) echo "<div class='error-msg'>$error</div>"; ?>
    <?php if ($success) echo "<div class='success-msg'>$success</div>"; ?>

    <form method="POST" action="">
        <input type="text" name="full_name" placeholder="Full Name" required>
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="admin">Admin</option>
            <option value="registrar">Registrar</option>
        </select>
        <select name="assigned_community_id">
            <option value="">Assign Community (for registrar)</option>
            <?php
            $comm_result = $conn->query("SELECT id, name FROM communities ORDER BY name ASC");
            while ($comm = $comm_result->fetch_assoc()) {
                echo "<option value='{$comm['id']}'>" . htmlspecialchars($comm['name']) . "</option>";
            }
            ?>
        </select>
        <button type="submit" name="register">Register</button>
    </form>
</div>

</body>
</html>
