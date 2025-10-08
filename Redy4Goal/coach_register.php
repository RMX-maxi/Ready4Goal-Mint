<?php
// coach_register.php
include("newconn.php");

$error = "";
$success = "";

if(isset($_POST['register_submit'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $position = $conn->real_escape_string($_POST['position']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password']; 
    $academy_id = (int)$_POST['academy_id'];
    $improvement_id = (int)$_POST['improvement_id'];
    $role_id = 3; // coach role
    $created_at = date("Y-m-d H:i:s");

    // Validate password
    if(strlen($password) < 5 || !preg_match('/[\W]/', $password)) {
        $error = "Password must be at least 5 characters and include a special symbol.";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM coach WHERE email=?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        if($check->num_rows > 0) {
            $error = "";
        } else {
            // Insert new coach
            $stmt = $conn->prepare("INSERT INTO coach (name, age, position, phone, email, password, academy_id, improvement_id, created_at, role_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sissssiiii", $name, $age, $position, $phone, $email, $password, $academy_id, $improvement_id, $created_at, $role_id);
            if($stmt->execute()) {
                $success = "Registration successful! You can now <a href='coach_login.php'>login</a>.";
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
<title>Coach Registration</title>
<style>
body { font-family: Arial; background:#f5f5f5; display:flex; justify-content:center; align-items:center; height:100vh; }
.register-box { background:#fff; padding:2rem; border-radius:10px; box-shadow:0 2px 8px rgba(0,0,0,0.2); width:400px; }
h2 { text-align:center; }
label { display:block; margin-top:10px; }
input, select { width:100%; padding:0.6rem; margin-top:5px; border:1px solid #ccc; border-radius:5px; }
button { margin-top:1rem; width:100%; padding:0.7rem; background:#0099cc; border:none; color:#fff; border-radius:5px; cursor:pointer; }
button:hover { background:#0077aa; }
.error { color:red; text-align:center; margin-top:10px; }
.success { color:green; text-align:center; margin-top:10px; }
</style>
</head>
<body>

<div class="register-box">
<h2>Coach Registration</h2>

<?php if($error) echo "<div class='error'>$error</div>"; ?>
<?php if($success) echo "<div class='success'>$success</div>"; ?>

<form method="POST" action="">
    <label>Name:</label>
    <input type="text" name="name" required>

    <label>Age:</label>
    <input type="number" name="age" required >

    <label>Position:</label>
    <input type="text" name="position" required>

    <label>Phone:</label>
    <input type="text" name="phone" required>

    <label>Email:</label>
    <input type="email" name="email" required>

    <label>Password:</label>
    <input type="password" name="password" required placeholder="">

    <label>Academy:</label>
    <select name="academy_id" required>
        <option value="">Select Academy</option>
        <?php
        $res = $conn->query("SELECT ser_id, academyname FROM academy");
        while($row = $res->fetch_assoc()) {
            echo "<option value='{$row['ser_id']}'>{$row['academyname']}</option>";
        }
        ?>
    </select>

    <label>Improvement:</label>
    <select name="improvement_id" required>
        <option value="">Select Improvement</option>
        <?php
        $res = $conn->query("SELECT id, improvement_name FROM improvement_types");
        while($row = $res->fetch_assoc()) {
            echo "<option value='{$row['id']}'>{$row['improvement_name']}</option>";
        }
        ?>
    </select>

    <button type="submit" name="register_submit">Register</button>
</form>
</div>

</body>
</html>
