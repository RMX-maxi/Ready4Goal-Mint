<?php
session_start();
if ($_SESSION['role_id'] != 3) {
    header("Location: login.html?error=Access Denied");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Player Dashboard</title>
</head>
<body>
  <h1>Welcome Player, <?php echo $_SESSION['name']; ?>!</h1>
  <p>Here you can track your training progress and stats.</p>
  <a href="logout.php">Logout</a>
</body>
</html>
