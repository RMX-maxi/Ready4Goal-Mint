<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please login first");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
</head>
<body>
  <h1>Welcome Admin, <?php echo $_SESSION['name']; ?>!</h1>
  <p>This is the admin dashboard.</p>
  <a href="logout.php">Logout</a>
</body>
</html>
