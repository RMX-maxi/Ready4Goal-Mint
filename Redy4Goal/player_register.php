<?php
include("newconn.php"); // Database connection

$msg = "";

if (isset($_POST['register'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = $_POST['password']; // ✅ Not hashed
    $role_id = 1; // ✅ Fixed as Player
    $improvement_id = (int)$_POST['improvement_id'];
    $experience = $conn->real_escape_string($_POST['experience']);
    $academy_id = (int)$_POST['academy_id'];
    $created_at = date('Y-m-d H:i:s');

    // ✅ Password validation
    if (strlen($password) < 5 || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $msg = "⚠️ Password must be at least 5 characters long and contain at least one special symbol!";
    } else {
        // Check if email already exists
        $check = $conn->query("SELECT * FROM player WHERE email='$email'");
        if ($check->num_rows > 0) {
            $msg = "⚠️ Email already registered!";
        } else {
            $sql = "INSERT INTO player (name, age, email, phone, password, role_id, improvement_id, experience, created_at, academy_id)
                    VALUES ('$name', $age, '$email', '$phone', '$password', $role_id, $improvement_id, '$experience', '$created_at', $academy_id)";
            if ($conn->query($sql)) {
                $msg = "✅ Registration successful!";
            } else {
                $msg = "❌ Error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Player Registration</title>
<style>
body {
  font-family: Arial, sans-serif;
  background: #f0f2f5;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
}
.form-box {
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0,0,0,0.2);
  width: 700px;
}
h2 {
  text-align: center;
  color: #0099cc;
  margin-bottom: 20px;
}
.msg {
  text-align: center;
  color: green;
  margin-bottom: 10px;
}
.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 20px 30px;
}
label {
  display: block;
  margin-bottom: 5px;
  font-weight: bold;
}
input, select, textarea {
  width: 100%;
  padding: 8px;
  border: 1px solid #ccc;
  border-radius: 5px;
}
textarea {
  resize: none;
  height: 60px;
}
.full-width {
  grid-column: span 2;
}
button {
  width: 100%;
  padding: 10px;
  background: #0099cc;
  color: white;
  border: none;
  border-radius: 5px;
  cursor: pointer;
}
button:hover {
  background: #0077aa;
}
</style>
</head>
<body>

<div class="form-box">
  <h2>Player Registration</h2>
  <?php if($msg) echo "<p class='msg'>$msg</p>"; ?>

  <form method="POST" class="form-grid">
    
    <div>
      <label>Full Name</label>
      <input type="text" name="name" required>
    </div>

    <div>
      <label>Age</label>
      <input type="number" name="age" required>
    </div>

    <div>
      <label>Email</label>
      <input type="email" name="email" required>
    </div>

    <div>
      <label>Phone</label>
      <input type="text" name="phone" required>
    </div>

    <div>
      <label>Password</label>
      <input type="password" name="password" required placeholder="Min 5 chars & 1 symbol">
    </div>

    <div>
      <label>Improvement Type</label>
      <select name="improvement_id" required>
        <option value="">Select Improvement</option>
        <?php
        $improvements = $conn->query("SELECT id, improvement_name FROM improvement_types");
        while($imp = $improvements->fetch_assoc()) {
            echo "<option value='{$imp['id']}'>{$imp['improvement_name']}</option>";
        }
        ?>
      </select>
    </div>

    <div class="full-width">
      <label>Experience</label>
      <textarea name="experience" placeholder="Enter your football experience"></textarea>
    </div>

    <div class="full-width">
      <label>Academy</label>
      <select name="academy_id" required>
        <option value="">Select Academy</option>
        <?php
        $academies = $conn->query("SELECT ser_id, academyname FROM academy");
        while($a = $academies->fetch_assoc()) {
            echo "<option value='{$a['ser_id']}'>{$a['academyname']}</option>";
        }
        ?>
      </select>
    </div>

    <div class="full-width">
      <button type="submit" name="register">Register</button>
    </div>
  </form>
</div>

</body>
</html>
