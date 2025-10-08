<?php
session_start();
include "newconn.php"; // your DB connection file

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $role_id = intval($_POST['role_id']);

    // Check in users table
    $sql = "SELECT * FROM player WHERE email='$email' AND password='$password' AND role_id='$role_id' LIMIT 1";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);

        // Store user session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['email'] = $user['email'];

        // Redirect by role
        switch ($role_id) {
            case 1: header("Location: player.php"); break;
            case 2: header("Location: coach.php"); break;
            case 3: header("Location: academy.php"); break;
            case 4: header("Location: indexadmin.php"); break;
            default: header("Location: index.php?error=Invalid role"); break;
        }
        exit;
    } else {
        header("Location: index.php?error=Invalid login credentials");
        exit;
    }
}
?>
