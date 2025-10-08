<?php
session_start();
if ($_SESSION['role_id'] != 4) {
    header("Location: login.html?error=Access Denied");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Academy Dashboard</title>
</head>
<body>
  <h1>Welcome Academy, <?php echo $_SESSION['name']; ?>!</h1>
  <p>Here you can manage your coaches, players, and schedules.</p>
  <a href="logout.php">Logout</a>
</body>
</html>
