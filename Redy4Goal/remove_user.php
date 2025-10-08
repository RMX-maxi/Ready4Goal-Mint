<?php
session_start();
include("newconn.php"); // your DB connection

if(isset($_POST['user_id']) && isset($_POST['user_type'])) {
    $user_id = $_POST['user_id'];
    $user_type = $_POST['user_type'];

    switch($user_type) {
        case 'player': $table = 'player'; break;
        case 'coach': $table = 'coach'; break;
        case 'academy': $table = 'academy'; break;
        default: die("Invalid type.");
    }

    $stmt = $conn->prepare("DELETE FROM $table WHERE ".($user_type=='academy'?'ser_id':'id')." = ?");
    $stmt->bind_param("i", $user_id);
    if($stmt->execute()) {
        header("Location: academy_dashboard.php?msg=User removed successfully");
        exit;
    } else {
        echo "Error deleting: ".$conn->error;
    }
}
?>
