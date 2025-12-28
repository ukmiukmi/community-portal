<?php 
session_start();
include './pages/db.php';

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ezza North Citizenship Portal</title>
    <link rel="stylesheet" href="./css/index.css">
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
<?php if (isset($_SESSION['logout_message'])): ?>
<script>
Swal.fire({
  title: 'Logout Successful!',
  text: '<?= addslashes($_SESSION['logout_message']); ?>',
  icon: 'success',
  confirmButtonColor: '#0ea5e9',
  timer: 2500,
  showConfirmButton: false
});
</script>
<?php unset($_SESSION['logout_message']); endif; ?>
    <!-- ===== Navbar ===== -->
    <header class="navbar">
    <div class="container">
        <h1 class="logo">Ezza North Portal</h1>

        <!-- Hamburger menu for mobile -->
        <div class="hamburger" id="hamburger">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <nav>
            <ul class="nav-links" id="navLinks">
                <li><a href="#">Home</a></li>
                <li><a href="#">Communities</a></li>
                <li><a href="#">Villages</a></li>
                <li><a href="#">News</a></li>
                <li><a href="login.php" class="btn-link">Login</a></li>
                <li><a href="register.php" class="btn-link register-btn">Register</a></li>
            </ul>
        </nav>
    </div>
</header>



    <!-- ===== Hero Section ===== -->
<section class="hero">
    <div class="hero-content">
        <h2>Welcome to Ezza North Citizenship Portal</h2>
        <p>Register, verify, and discover citizens and communities of Ezza North with ease.</p>
        <form class="search-bar" method="GET" action="">
            <input type="text" name="query" placeholder="Search by name, phone, or ID number..." />
            <button type="submit">Search</button>
        </form>
    </div>
</section>


    <!-- ===== Search Results ===== -->
    <section class="results">
        <div class="container">
            <?php
            if (isset($_GET['query'])) {
                $query = trim($_GET['query']);
                $querySafe = $conn->real_escape_string($query);

                $sql = "
                    SELECT c.*, com.name AS community_name, v.name AS village_name
                    FROM citizens c
                    LEFT JOIN communities com ON c.community_id = com.id
                    LEFT JOIN villages v ON c.village_id = v.id
                    WHERE 
                        c.first_name LIKE '%$querySafe%' 
                        OR c.last_name LIKE '%$querySafe%'
                        OR c.phone LIKE '%$querySafe%'
                        OR c.citizen_id LIKE '%$querySafe%'
                ";

                $result = $conn->query($sql);

                echo "<h3>Search Results for '<span style='color:#00aaff;'>$query</span>'</h3>";

                if ($result->num_rows > 0) {
                    echo "<div class='citizen-grid'>";
                    while ($row = $result->fetch_assoc()) {
                        $image = $row['image'] ? 'uploads/'.$row['image'] : 'assets/images/default-user.png';
                        echo "
                        <div class='citizen-card'>
                            <img src='$image' alt='Citizen Image'>
                            <h4>{$row['first_name']} {$row['last_name']}</h4>
                            <p><strong>ID:</strong> {$row['citizen_id']}</p>
                            <p><strong>Community:</strong> {$row['community_name']}</p>
                            <p><strong>Village:</strong> {$row['village_name']}</p>
                            <p><strong>Type:</strong> {$row['citizen_type']}</p>
                            <p><strong>State of Origin:</strong> {$row['state_of_origin']}</p>
                            <p><strong>Address:</strong> {$row['house_address']}</p>
                            <p><strong>Phone:</strong> {$row['phone']}</p>
                        </div>";
                    }
                    echo "</div>";
                } else {
                    echo "<p class='no-result'>No citizen found matching your search.</p>";
                }
            }
            ?>
        </div>
    </section>

    <!-- ===== Footer ===== -->
    <footer>
        <div class="container footer-content">
            <p>&copy; <?php echo date('Y'); ?> Ezza North Local Government. All Rights Reserved.</p>
            <p>Designed & Developed by UkmiTech</p>
        </div>
    </footer>
<script src="./js/index.js"></script>

