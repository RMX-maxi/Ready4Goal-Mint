<?php
session_start();
include("newconn.php"); // your DB connection

if(!isset($_SESSION['player_id'])){
    header("Location: player_login.php?error=Please login first");
    exit;
}

$player_id = $_SESSION['player_id'];

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'] ?? '';
    $improvement_id = isset($_POST['improvement_id']) ? (int)$_POST['improvement_id'] : null;

    // Prepare update query
    if(!empty($password)){
        // Hash password if changed
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE player SET name=?, age=?, phone=?, email=?, password=?, improvement_id=? WHERE id=?");
        $stmt->bind_param("sissiii", $name, $age, $phone, $email, $hashed_password, $improvement_id, $player_id);
    } else {
        // Without password
        $stmt = $conn->prepare("UPDATE player SET name=?, age=?, phone=?, email=?, improvement_id=? WHERE id=?");
        $stmt->bind_param("sissii", $name, $age, $phone, $email, $improvement_id, $player_id);
    }

    if($stmt->execute()){
        $_SESSION['player_name'] = $name; // update session name
        header("Location: player_dashboard.php?success=Profile updated successfully");
        exit;
    } else {
        die("Database error: ".$conn->error);
    }
} else {
    header("Location: player_dashboard.php");
    exit;
}
?>
