<?php
// academy_register.php
include("newconn.php");

$error = "";
$success = "";

if(isset($_POST['register_submit'])) {
    $academyname = $conn->real_escape_string($_POST['academyname']);
    $coachname = $conn->real_escape_string($_POST['coachname']);
    $sincedate = $conn->real_escape_string($_POST['sincedate']);
    $maincity = $conn->real_escape_string($_POST['maincity']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $role_id = 2; // Coach role for academy
    $created_at = date("Y-m-d H:i:s");

    // Password validation
    if(strlen($password) < 5 || !preg_match('/[\W]/', $password)) {
        $error = "Password must be at least 5 characters and include a special symbol.";
    } else {
        // Check if email exists
        $check = $conn->prepare("SELECT ser_id FROM academy WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if($check->num_rows > 0) {
            $error = "Email already exists. Use another email.";
        } else {
            // Insert academy
            $stmt = $conn->prepare("INSERT INTO academy (academyname, coachname, sincedate, maincity, phone, email, password, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $academyname, $coachname, $sincedate, $maincity, $phone, $email, $password, $role_id);
            if($stmt->execute()) {
                $success = "Registration successful! You can now <a href='academy_login.php'>login</a>.";
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
<title>Academy Registration</title>
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
<h2>Academy Registration</h2>

<?php if($error) echo "<div class='error'>$error</div>"; ?>
<?php if($success) echo "<div class='success'>$success</div>"; ?>

<form method="POST" action="">
    <label>Academy Name:</label>
    <input type="text" name="academyname" required>

    <label>Coach Name:</label>
    <input type="text" name="coachname" required>

    <label>Since Date:</label>
    <input type="date" name="sincedate" required>

    <label>Main City:</label>
    <input type="text" name="maincity" required>

    <label>Phone:</label>
    <input type="text" name="phone" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required placeholder="Min 5 chars, special symbol">

    <button type="submit" name="register_submit">Register</button>
</form>
</div>
</body>
</html>
