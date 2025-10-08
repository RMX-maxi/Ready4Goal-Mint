<?php
session_start();
include("newconn.php"); // your database connection

if(isset($_POST['login_submit'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Check academy table
    $sql = "SELECT * FROM academy WHERE email=? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows === 1) {
        $academy = $result->fetch_assoc();

        // If passwords are plain text (not recommended)
        if($password === $academy['password']) {
            // Or use password_verify if hashed
            $_SESSION['academy_id'] = $academy['ser_id'];
            $_SESSION['academy_name'] = $academy['academyname'];

            // Redirect to academy dashboard
            header("Location: academy_dashboard.php");
            exit;
        } else {
            header("Location: index.php?error=Invalid password");
            exit;
        }
    } else {
        header("Location: index.php?error=Email not found");
        exit;
    }
}
?>
