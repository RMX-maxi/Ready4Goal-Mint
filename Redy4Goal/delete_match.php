<?php
session_start();
include("newconn.php");

if(!isset($_SESSION['academy_id'])) {
    header("Location: academy_login.php");
    exit;
}

if(isset($_GET['id'])){
    $match_id = intval($_GET['id']);
    $academy_id = $_SESSION['academy_id'];
    
    // Verify ownership
    $verify = $conn->query("SELECT id FROM matches WHERE id=$match_id AND academy_id=$academy_id");
    if($verify->num_rows > 0){
        $conn->query("DELETE FROM matches WHERE id=$match_id");
        $_SESSION['message'] = "Match deleted successfully!";
        $_SESSION['message_type'] = "success";
    } else {
        $_SESSION['message'] = "Match not found or access denied!";
        $_SESSION['message_type'] = "error";
    }
}

header("Location: academy_dashboard.php?section=match-details");
exit;
?>