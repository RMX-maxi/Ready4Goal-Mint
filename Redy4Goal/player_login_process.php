<?php
session_start();
include("newconn.php"); // Database connection

if(isset($_POST['login_submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Query the users table for players (role_id = 3)
    $sql = "SELECT * FROM player WHERE email=? AND role_id=1 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {
        $player = $result->fetch_assoc();

        // If password is hashed
        // if(password_verify($password, $player['password'])) { ... }

        // For plain text password (not recommended)
        if($password === $player['password']) {
            $_SESSION['player_id'] = $player['id'];
            $_SESSION['player_name'] = $player['name'];

            // Redirect to player dashboard
            header("Location: player_dashboard.php");
            exit;
        } else {
            header("Location: player_login.php?error=Invalid password");
            exit;
        }
    } else {
        header("Location: player_login.php?error=Email not found or not a player");
        exit;
    }
}
?>
