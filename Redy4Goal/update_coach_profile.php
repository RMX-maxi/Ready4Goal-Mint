<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    header("Location: coach_login.php?error=Please login first");
    exit;
}

if (isset($_POST['id'], $_POST['name'], $_POST['age'], $_POST['position'], $_POST['phone'], $_POST['email'])) {
    $id = (int)$_POST['id'];
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $position = $conn->real_escape_string($_POST['position']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);

    // Server-side age validation
    if ($age < 0) $age = 0;

    // Handle password if entered (no hashing)
    if (!empty($_POST['password'])) {
        $password = $conn->real_escape_string($_POST['password']);
        $sql = "UPDATE coach SET name='$name', age=$age, position='$position', phone='$phone', email='$email', password='$password' WHERE id=$id";
    } else {
        $sql = "UPDATE coach SET name='$name', age=$age, position='$position', phone='$phone', email='$email' WHERE id=$id";
    }

    if ($conn->query($sql)) {
        $_SESSION['coach_name'] = $name; // update session name
        header("Location: coach_dashboard.php?success=Profile updated successfully");
    } else {
        echo "Error updating profile: " . $conn->error;
    }
} else {
    echo "Invalid request";
}
?>
