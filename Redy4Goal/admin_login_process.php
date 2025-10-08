<?php
session_start();
include("newconn.php"); // Your database connection file

if(isset($_POST['login_submit'])) {

    $email = $_POST['email'];
    $password = $_POST['password'];

    // Prepare statement to avoid SQL injection
    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.password, r.role_name 
                            FROM admin u
                            JOIN roles r ON u.role_id = r.id
                            WHERE u.email = ? AND r.role_name = 'admin'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password (if hashed in DB)
        if($password === $user['password']) { // use password_verify($password, $user['password']) if hashed
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['admin_name'] = $user['name'];
            header("Location: admin_dashboard.php"); // redirect to admin dashboard
            exit();
        } else {
            $error = "Incorrect password!";
        }
    } else {
        $error = "Admin not found!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login</title>
<style>
body { font-family: Arial; background:#f5f5f5; display:flex; justify-content:center; align-items:center; height:100vh; }
.login-box { background:#fff; padding:2rem; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:300px; }
h2 { text-align:center; }
label { display:block; margin-top:10px; }
input { width:100%; padding:0.6rem; margin-top:5px; border:1px solid #ccc; border-radius:5px; }
button { margin-top:1rem; width:100%; padding:0.7rem; background:#0099cc; border:none; color:#fff; border-radius:5px; cursor:pointer; }
button:hover { background:#0077aa; }
.error { color:red; margin-top:10px; text-align:center; }
</style>
</head>
<body>
<div class="login-box">
    <h2>Admin Login</h2>
    <form method="POST" action="">
        <label>Email:</label>
        <input type="email" name="email" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <button type="submit" name="login_submit">Login</button>
    </form>
    <div class="error">
        <?php if(isset($error)) echo htmlspecialchars($error); ?>
    </div>
</div>
</body>
</html>
