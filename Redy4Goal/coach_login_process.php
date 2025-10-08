<?php
session_start();
include("newconn.php"); // your database connection

if(isset($_POST['login_submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check users table for coaches (role_id = 2)
    $sql = "SELECT * FROM coach WHERE email=? AND role_id=2 LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {
        $coach = $result->fetch_assoc();

        // For plain text password (not recommended)
        if($password === $coach['password']) {
            $_SESSION['coach_id'] = $coach['id'];
            $_SESSION['coach_name'] = $coach['name'];
            $_SESSION['position'] = $coach['position'];

            // Redirect to coach dashboard
            header("Location: coach_dashboard.php");
            exit;
        } else {
            header("Location: coach_login.php?error=Invalid password");
            exit;
        }
    } else {
        header("Location: coach_login.php?error=Email not found or not a coach");
        exit;
    }
}
?>
