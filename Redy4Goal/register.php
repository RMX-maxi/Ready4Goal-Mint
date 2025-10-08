<?php
// register.php
include("newconn.php"); // your database connection

$error = "";
$success = "";

if(isset($_POST['register_submit'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; // plain password
    $role_id = 4; // default role
    $created_at = date("Y-m-d H:i:s");

    // Validate password: minimum 5 characters and a special symbol
    if(strlen($password) < 5 || !preg_match('/[\W]/', $password)) {
        $error = "";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM admin WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0) {
            $error = "";
        } else {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO admin (name, email, password, role_id, created_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssds", $name, $email, $password, $role_id, $created_at);
            if($stmt->execute()) {
                $success = "Registration successful! You can now <a href='login.php'>login</a>.";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>User Registration</title>
<style>
body { font-family: Arial; background:#f5f5f5; display:flex; justify-content:center; align-items:center; height:100vh; }
.register-box { background:#fff; padding:2rem; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:400px; }
h2 { text-align:center; }
label { display:block; margin-top:10px; }
input { width:100%; padding:0.6rem; margin-top:5px; border:1px solid #ccc; border-radius:5px; }
button { margin-top:1rem; width:100%; padding:0.7rem; background:#0099cc; border:none; color:#fff; border-radius:5px; cursor:pointer; }
button:hover { background:#0077aa; }
.error { color:red; text-align:center; margin-top:10px; }
.success { color:green; text-align:center; margin-top:10px; }
</style>
</head>
<body>

<div class="register-box">
<h2>User Registration</h2>

<?php if(!empty($error)) echo "<div class='error'>$error</div>"; ?>
<?php if(!empty($success)) echo "<div class='success'>$success</div>"; ?>

<form method="POST" action="">
    <label>Name:</label>
    <input type="text" name="name" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required placeholder="Min 5 chars & special symbol">

    <button type="submit" name="register_submit">Register</button>
</form>
</div>

</body>
</html>
