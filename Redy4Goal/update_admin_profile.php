<?php
session_start();
include("newconn.php");

if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php?error=Please login first");
    exit;
}

$id = $_POST['id'];
$name = $_POST['name'];
$email = $_POST['email'];
$password = $_POST['password'];

if(!empty($password)){
    $password = password_hash($password, PASSWORD_DEFAULT);
    $sql = "UPDATE admin SET name='$name', email='$email', password='$password' WHERE id=$id";
} else {
    $sql = "UPDATE admin SET name='$name', email='$email' WHERE id=$id";
}

if($conn->query($sql)){
    $_SESSION['admin_name'] = $name; // update session name
    header("Location: admin_dashboard.php?success=Profile updated");
} else {
    echo "Error: " . $conn->error;
}
?>
