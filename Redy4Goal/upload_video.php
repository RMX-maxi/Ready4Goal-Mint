<?php
include("newconn.php");
session_start();

if (!isset($_SESSION['coach_id'])) {
    header("Location: coach_login.php?error=Please login first");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $improvement_id = (int)$_POST['improvement_id'];
    $coach_id = (int)$_SESSION['coach_id'];

    $upload_folder = "uploads/videos/";
    $server_folder = __DIR__ . "/" . $upload_folder; // full server path

    // Create folder if not exists
    if (!is_dir($server_folder)) {
        mkdir($server_folder, 0777, true);
    }

    // Generate unique filename
    $file_name = basename($_FILES["video"]["name"]);
    $unique_name = uniqid("vid_", true) . "_" . preg_replace("/[^a-zA-Z0-9_\.-]/", "_", $file_name);
    $server_path = $server_folder . $unique_name;
    $db_path = $upload_folder . $unique_name; // relative path saved to DB

    if (move_uploaded_file($_FILES["video"]["tmp_name"], $server_path)) {
        $stmt = $conn->prepare("INSERT INTO videos (title, improvement_id, coach_id, file_path, uploaded_at)
                                VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("siis", $title, $improvement_id, $coach_id, $db_path);

        if ($stmt->execute()) {
            header("Location: coach_dashboard.php?msg=Video uploaded successfully");
            exit;
        } else {
            echo "Database insert error: " . $stmt->error;
        }
    } else {
        echo "Error moving uploaded file.";
    }
}
?>
