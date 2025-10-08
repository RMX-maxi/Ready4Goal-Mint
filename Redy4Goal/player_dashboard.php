<?php
session_start();
include("newconn.php"); // Database connection

if(!isset($_SESSION['player_id'])) {
    header("Location: player_login.php?error=Please login first");
    exit;
}

$player_id = $_SESSION['player_id'];

// Fetch player details
$player_result = $conn->query("SELECT * FROM player WHERE id = $player_id");
$player = $player_result->fetch_assoc();

// Initialize messages
$update_error = '';
$update_success = '';
$upload_error = '';
$upload_success = '';

// Fetch player details including improvement and academy
$player_details_res = $conn->query("
    SELECT p.*, i.improvement_name, a.academyname
    FROM player p
    LEFT JOIN improvement_types i ON p.improvement_id = i.id
    LEFT JOIN academy a ON p.academy_id = a.id
    WHERE p.id = $player_id
");
$player = $player_details_res->fetch_assoc();

// Check if player can upload new video (previous video must be rated)
$can_upload = true;
$last_video_status = '';

// Get the most recent video uploaded by the player
$last_video_res = $conn->query("
    SELECT v.id, v.title, v.uploaded_at, 
           (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) as rating_count
    FROM player_progress_videos v
    WHERE v.player_id = $player_id
    ORDER BY v.uploaded_at DESC
    LIMIT 1
");

if($last_video_res->num_rows > 0) {
    $last_video = $last_video_res->fetch_assoc();
    if($last_video['rating_count'] == 0) {
        $can_upload = false;
        $last_video_status = "Please wait for your coach to rate your last video '<strong>" . htmlspecialchars($last_video['title']) . "</strong>' before uploading a new one.";
    }
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = $conn->real_escape_string($_POST['name']);
    $age = (int)$_POST['age'];
    $phone = $conn->real_escape_string($_POST['phone']);
    $email = $conn->real_escape_string($_POST['email']);
    $improvement_id = (int)$_POST['improvement_id'];
    $password = $_POST['password'];

    if($password) {
        $sql = "UPDATE player SET name='$name', age=$age, phone='$phone', email='$email', improvement_id=$improvement_id, password='$password' WHERE id=$player_id";
    } else {
        $sql = "UPDATE player SET name='$name', age=$age, phone='$phone', email='$email', improvement_id=$improvement_id WHERE id=$player_id";
    }

    if($conn->query($sql)) {
        $update_success = "Profile updated successfully!";
        // Refresh player details
        $player_details_res = $conn->query("
            SELECT p.*, i.improvement_name, a.academyname
            FROM player p
            LEFT JOIN improvement_types i ON p.improvement_id = i.id
            LEFT JOIN academy a ON p.academy_id = a.id
            WHERE p.id = $player_id
        ");
        $player = $player_details_res->fetch_assoc();
    } else {
        $update_error = "Database error: Could not update profile.";
    }
}

// Handle progress video upload
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_video'])) {
    // Check if player can upload
    if(!$can_upload) {
        $upload_error = "";
    } else {
        $title = $conn->real_escape_string($_POST['title']);
        $improvement_id = (int)$_POST['improvement_id'];
        $file = $_FILES['video_file'];

        // Check for duplicate title for this player
        $check = $conn->prepare("SELECT id FROM player_progress_videos WHERE player_id=? AND title=?");
        $check->bind_param("is", $player_id, $title);
        $check->execute();
        $check->store_result();

        if($check->num_rows > 0) {
            $upload_error = "A video with this title already exists. Please choose a different title.";
        } else {
            if($file['error'] == 0) {
                $allowed = ['mp4','mov','avi','webm'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if(in_array($ext, $allowed)) {
                    $newFileName = 'uploads/progress_'.time().'_'.$file['name'];
                    if(move_uploaded_file($file['tmp_name'], $newFileName)) {
                        $stmt = $conn->prepare("
                            INSERT INTO player_progress_videos (player_id, improvement_id, title, file_path, uploaded_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->bind_param("iiss", $player_id, $improvement_id, $title, $newFileName);

                        if($stmt->execute()) {
                            $upload_success = "Video uploaded successfully! Your coach will review and rate it soon.";
                            // Refresh upload status
                            $can_upload = false;
                            $last_video_status = "Your video '<strong>" . htmlspecialchars($title) . "</strong>' has been uploaded. Please wait for coach rating before uploading another video.";
                        } else {
                            $upload_error = "Database error: Could not save video.";
                        }
                    } else {
                        $upload_error = "Failed to move uploaded file.";
                    }
                } else {
                    $upload_error = "Invalid file type. Allowed: mp4, mov, avi, webm.";
                }
            } else {
                $upload_error = "Error uploading file.";
            }
        }
    }
}

// Fetch coach training videos with coach names
$player_improvement_id = $player['improvement_id'] ?? 0;
$videos = $conn->query("
    SELECT v.*, i.improvement_name, c.name as coach_name 
    FROM videos v 
    JOIN improvement_types i ON v.improvement_id = i.id 
    LEFT JOIN coach c ON v.coach_id = c.id 
    WHERE v.improvement_id = $player_improvement_id
    ORDER BY v.uploaded_at DESC
");

// Fetch categories for upload
$categories = $conn->query("SELECT * FROM improvement_types ORDER BY improvement_name ASC");

// NEW PROGRESS CALCULATION METHOD
// Calculate progress based on rating milestones and consistency
$progress_data = $conn->query("
    SELECT 
        COUNT(*) as total_videos,
        AVG(r.rating) as avg_rating,
        MAX(r.rating) as best_rating,
        COUNT(CASE WHEN r.rating >= 4 THEN 1 END) as good_videos,
        DATEDIFF(NOW(), MIN(v.uploaded_at)) as days_since_first_video
    FROM player_progress_videos v
    LEFT JOIN progress_ratings r ON v.id = r.video_id
    WHERE v.player_id = $player_id
");

$progress_stats = $progress_data->fetch_assoc();
$total_videos = $progress_stats['total_videos'] ?? 0;
$avg_rating = $progress_stats['avg_rating'] ?? 0;
$best_rating = $progress_stats['best_rating'] ?? 0;
$good_videos = $progress_stats['good_videos'] ?? 0;
$days_active = $progress_stats['days_since_first_video'] ?? 0;

// Calculate progress using multiple factors
$progress_score = 0;

if ($total_videos > 0) {
    // Factor 1: Number of videos (max 40%)
    $video_count_score = min(40, ($total_videos / 10) * 40);
    
    // Factor 2: Average rating (max 30%)
    $rating_score = $avg_rating > 0 ? min(30, ($avg_rating / 5) * 30) : 0;
    
    // Factor 3: Consistency - percentage of good videos (max 20%)
    $consistency_score = $total_videos > 0 ? min(20, ($good_videos / $total_videos) * 20) : 0;
    
    // Factor 4: Activity duration (max 10%)
    $activity_score = min(10, ($days_active / 30) * 10);
    
    $progress_score = $video_count_score + $rating_score + $consistency_score + $activity_score;
}

$progress_score = min(100, round($progress_score));

?>

<!DOCTYPE html>
<html>
<head>
    <title>Player Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
        }
        body { 
            margin:0; 
            font-family:Arial; 
            background:#f5f5f5; 
            display:flex; 
            min-height: 100vh;
        }

        .sidebar { 
            width:220px; 
            background:#0099cc; 
            color:#fff; 
            min-height:100vh; 
            padding:20px; 
            position:fixed; 
            left: 0;
            top: 0;
        }
        .sidebar h2 { 
            text-align:center; 
            margin-top:0; 
            margin-bottom: 20px;
        }
        .sidebar a { 
            display:block; 
            padding:12px; 
            margin:8px 0; 
            text-decoration:none; 
            color:#fff; 
            background:#0077aa; 
            border-radius:4px; 
            text-align:center; 
            transition: background 0.3s;
        }
        .sidebar a:hover { 
            background:#005f88; 
        }
        
        .main-content { 
            margin-left:220px; 
            padding:30px; 
            width: calc(100% - 220px);
            min-height: 100vh;
        }
        
        .section { 
            display:none; 
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .active { 
            display:block; 
        }
        
        table { 
            border-collapse:collapse; 
            width:100%; 
            background:#fff; 
            margin-top: 15px;
        }
        th,td { 
            border:1px solid #ccc; 
            padding:12px; 
            text-align:left; 
        }
        th { 
            background:#0099cc; 
            color:#fff; 
        }
        
        input, button, textarea, select { 
            padding:10px; 
            margin:8px 0; 
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input, textarea, select {
            background: #f9f9f9;
        }
        button { 
            background:#0099cc; 
            color:#fff; 
            border:none; 
            border-radius:4px; 
            cursor:pointer; 
            font-weight: bold;
            padding: 12px 20px;
            width: auto;
            min-width: 150px;
        }
        button:hover { 
            background:#0077aa; 
        }
        button:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }
        
        /* Updated Video Grid with Coach-like Borders */
        .video-grid { 
            display:flex; 
            flex-wrap:wrap; 
            gap:25px; 
            margin-top:20px; 
            justify-content: flex-start;
        }
        .video-thumb { 
            width: calc(25% - 25px); 
            cursor:pointer; 
            border-radius:12px; 
            overflow:hidden; 
            background:#fff; 
            box-shadow:0 4px 15px rgba(0,0,0,0.1); 
            transition:transform 0.3s, box-shadow 0.3s;
            border: 3px solid #e9ecef;
        }
        .video-thumb:hover { 
            transform:translateY(-5px); 
            box-shadow:0 8px 25px rgba(0,0,0,0.15);
            border-color: #0099cc;
        }
        .video-thumb video { 
            width:100%; 
            height:200px; 
            object-fit:cover; 
            display: block;
            border-bottom: 2px solid #e9ecef;
        }
        .video-thumb .video-info { 
            padding: 15px;
        }
        .video-thumb p, .video-thumb small { 
            margin:5px 0; 
            font-size:14px; 
            color:#333; 
            line-height: 1.4;
        }
        .video-thumb p {
            font-weight: bold;
            font-size: 16px;
        }
        .uploader-info {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .uploader-name {
            font-weight: bold;
            color: #0099cc;
        }
        
        .upload-form { 
            background:#f9f9f9; 
            padding:25px; 
            border-radius:8px; 
            border: 1px solid #ddd;
            margin-top:20px; 
            max-width:700px; 
        }
        .upload-status {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .upload-allowed {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .message { 
            margin:15px 0; 
            padding:12px; 
            border-radius:6px; 
            border: 1px solid transparent;
        }
        .error { 
            background:#f8d7da; 
            color:#721c24; 
            border-color: #f5c6cb;
        }
        .success { 
            background:#d4edda; 
            color:#155724; 
            border-color: #c3e6cb;
        }
        
        /* Enhanced Video Modal Styling */
        .video-modal { 
            display:none; 
            position:fixed; 
            top:0; 
            left:0; 
            width:100%; 
            height:100%; 
            background:rgba(0,0,0,0.95); 
            justify-content:center; 
            align-items:center; 
            z-index:1000; 
        }
        .modal-container {
            background: #fff;
            border-radius: 12px;
            width: 95%;
            max-width: 1200px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
        .modal-header {
            background: linear-gradient(135deg, #0099cc, #0077aa);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: bold;
        }
        .modal-content {
            display: flex;
            flex: 1;
            min-height: 500px;
        }
        .modal-video-section {
            flex: 2;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        .modal-video-section video {
            width: 100%;
            max-width: 800px;
            height: auto;
            max-height: 500px;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            border: 2px solid #333;
        }
        .modal-details-section {
            flex: 1;
            padding: 25px;
            background: #f8f9fa;
            overflow-y: auto;
            border-left: 1px solid #e9ecef;
            min-width: 350px;
        }
        .back-btn { 
            background:#ff6b6b; 
            color:#fff; 
            border:none; 
            border-radius:6px; 
            padding:10px 20px; 
            cursor:pointer; 
            font-weight:bold;
            transition: background 0.3s;
        }
        .back-btn:hover { 
            background:#ff5252; 
        }
        
        /* Enhanced Progress System */
        .progress-breakdown {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e9ecef;
        }
        .progress-factor {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .progress-factor:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .factor-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
        }
        .factor-bar {
            background: #e9ecef;
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }
        .factor-progress {
            background: #28a745;
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }
        .factor-value {
            font-size: 14px;
            color: #666;
        }
        
        .simple-progress {
            background: #e0e0e0;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
            border: 2px solid #ddd;
        }
        .simple-progress-bar {
            background: linear-gradient(135deg, #0099cc, #0077aa);
            height: 100%;
            border-radius: 8px;
            text-align: center;
            color: white;
            font-size: 12px;
            line-height: 20px;
            transition: width 0.5s ease;
            font-weight: bold;
        }
        
        .dashboard-stats {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex: 1;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        .stat-card h3 {
            margin-top: 0;
            color: #0099cc;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }
        
        .chat-container {
            display: flex;
            gap: 20px;
            height: 500px;
        }
        .chat-contacts {
            width: 280px;
            background: #0077aa;
            color: white;
            padding: 15px;
            border-radius: 8px;
            overflow-y: auto;
        }
        .chat-contacts h3 {
            margin-top: 0;
            text-align: center;
        }
        .chat-contacts ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .chat-contacts li {
            margin-bottom: 10px;
        }
        .chat-contacts a {
            display: block;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            cursor: pointer;
        }
        .chat-contacts a:hover {
            background: rgba(255,255,255,0.2);
        }
        .chat-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .chat-header {
            background: #0099cc;
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
        }
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            border-bottom: 1px solid #eee;
            background: #f9f9f9;
        }
        .message-item {
            margin-bottom: 15px;
            padding: 10px;
            border-radius: 8px;
            max-width: 80%;
        }
        .message-sent {
            background: #0099cc;
            color: white;
            margin-left: auto;
            text-align: right;
        }
        .message-received {
            background: white;
            border: 1px solid #ddd;
            margin-right: auto;
        }
        .message-sender {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .message-time {
            font-size: 10px;
            opacity: 0.7;
            margin-top: 5px;
        }
        .chat-input {
            display: flex;
            padding: 15px;
            gap: 10px;
            background: white;
        }
        .chat-input input {
            flex: 1;
            margin: 0;
        }
        .chat-input button {
            width: auto;
            margin: 0;
        }
        
        @media (max-width: 1200px) {
            .video-thumb {
                width: calc(33.333% - 25px);
            }
            .modal-content {
                flex-direction: column;
            }
            .modal-details-section {
                border-left: none;
                border-top: 1px solid #e9ecef;
                min-width: auto;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                min-height: auto;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            .video-thumb {
                width: calc(50% - 25px);
            }
            .dashboard-stats {
                flex-direction: column;
            }
            .chat-container {
                flex-direction: column;
                height: auto;
            }
            .chat-contacts {
                width: 100%;
                height: 200px;
            }
        }
    </style>
    <script>
        function showSection(sectionId){
            document.querySelectorAll('.section').forEach(div=>div.classList.remove('active'));
            document.getElementById(sectionId).classList.add('active');
        }

        function openModal(modalId){ 
            document.getElementById(modalId).style.display='flex'; 
        }
        function closeModal(modalId){ 
            document.getElementById(modalId).style.display='none';
            const video = document.getElementById(modalId).querySelector('video');
            if(video) {
                video.pause();
                video.currentTime = 0;
            }
        }

        window.onload = () => { showSection('dashboard'); }
    </script>
</head>
<body>
<div class="sidebar">
    <h2>Player Panel</h2>
    <a href="#" onclick="showSection('dashboard')">Dashboard</a>
    <a href="#" onclick="showSection('view-profile')">View Profile</a>
    <a href="#" onclick="showSection('edit-profile')">Edit Profile</a>
    <a href="#" onclick="showSection('training-videos')">Training Videos</a>
    <a href="#" onclick="showSection('upload-video')">Upload Video</a>
    <a href="#" onclick="showSection('chat-section')">Chat</a>
    <a href="player_logout.php">Logout</a>
</div>

<div class="main-content">
    <!-- Dashboard -->
    <div id="dashboard" class="section active">
        <h1>Welcome, <?php echo htmlspecialchars($_SESSION['player_name']); ?>!</h1>
        <p>This is your player dashboard where you can track your progress and access training materials.</p>

        <?php
        // Player improvement ID
        $player_improvement_id = $player['improvement_id'] ?? 0;

        // Leaderboard: all players in same improvement type
        $leaderboard_res = $conn->query("
            SELECT p.id AS player_id, p.name AS player_name, 
                   IFNULL(AVG(r.rating),0) AS avg_rating
            FROM player_progress_videos v
            JOIN player p ON v.player_id = p.id
            LEFT JOIN progress_ratings r ON v.id = r.video_id
            WHERE v.improvement_id = $player_improvement_id
            GROUP BY p.id
            ORDER BY avg_rating DESC
        ");

        $player_data = [];
        while($row = $leaderboard_res->fetch_assoc()){
            $player_data[] = $row;
        }

        // Player progress based on videos uploaded vs coach videos
        $player_videos_res = $conn->query("
            SELECT COUNT(*) AS total_player_videos
            FROM player_progress_videos
            WHERE player_id = $player_id
        ");
        $total_player_videos = $player_videos_res->fetch_assoc()['total_player_videos'] ?? 0;

        $coach_videos_res = $conn->query("
            SELECT COUNT(*) AS total_coach_videos
            FROM videos
            WHERE improvement_id = $player_improvement_id
        ");
        $total_coach_videos = $coach_videos_res->fetch_assoc()['total_coach_videos'] ?? 0;
        ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Your Videos</h3>
                <div class="stat-number"><?= $total_player_videos ?></div>
                <p>Progress videos uploaded</p>
            </div>
            <div class="stat-card">
                <h3>Coach Videos</h3>
                <div class="stat-number"><?= $total_coach_videos ?></div>
                <p>Available training videos</p>
            </div>
            <div class="stat-card">
                <h3>Your Progress</h3>
                <div class="stat-number"><?= $progress_score ?>%</div>
                <p>Overall performance score</p>
                <!-- Enhanced Progress Bar -->
                <div class="simple-progress">
                    <div class="simple-progress-bar" style="width:<?= $progress_score ?>%;">
                        <?= $progress_score ?>%
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Breakdown 
        <div class="progress-breakdown">
            <h4>Progress Breakdown</h4>
            
            <?php
            // Calculate individual factor scores for display
            $video_count_score = min(40, ($total_videos / 10) * 40);
            $rating_score = $avg_rating > 0 ? min(30, ($avg_rating / 5) * 30) : 0;
            $consistency_score = $total_videos > 0 ? min(20, ($good_videos / $total_videos) * 20) : 0;
            $activity_score = min(10, ($days_active / 30) * 10);
            ?>
            <!--
            <div class="progress-factor">
                <div class="factor-label">Video Count (<?= $total_videos ?> videos)</div>
                <div class="factor-bar">
                    <div class="factor-progress" style="width: <?= ($video_count_score / 40) * 100 ?>%"></div>
                </div>
                <div class="factor-value"><?= number_format($video_count_score, 1) ?> / 40 points</div>
            </div>
            
            <div class="progress-factor">
                <div class="factor-label">Average Rating (<?= $avg_rating ? number_format($avg_rating, 1) : '0.0' ?>/5)</div>
                <div class="factor-bar">
                    <div class="factor-progress" style="width: <?= ($rating_score / 30) * 100 ?>%"></div>
                </div>
                <div class="factor-value"><?= number_format($rating_score, 1) ?> / 30 points</div>
            </div>
            
            <div class="progress-factor">
                <div class="factor-label">Consistency (<?= $good_videos ?>/<?= $total_videos ?> good videos)</div>
                <div class="factor-bar">
                    <div class="factor-progress" style="width: <?= ($consistency_score / 20) * 100 ?>%"></div>
                </div>
                <div class="factor-value"><?= number_format($consistency_score, 1) ?> / 20 points</div>
            </div>
            
            <div class="progress-factor">
                <div class="factor-label">Activity Duration (<?= $days_active ?> days)</div>
                <div class="factor-bar">
                    <div class="factor-progress" style="width: <?= ($activity_score / 10) * 100 ?>%"></div>
                </div>
                <div class="factor-value"><?= number_format($activity_score, 1) ?> / 10 points</div>
            </div>
            
        </div>
        -->

        <!-- Upload Status Indicator -->
        <div class="upload-status <?php echo $can_upload ? 'upload-allowed' : ''; ?>" style="margin: 20px 0;">
            <h4>Video Upload Status:</h4>
            <?php if($can_upload): ?>
                <p>✅ <strong>You can upload a new video.</strong> Your previous video has been rated by the coach.</p>
            <?php else: ?>
                <p>⏳ <strong>Please wait:</strong> <?php echo $last_video_status; ?></p>
                <?php if($total_player_videos == 0): ?>
                    <p>You haven't uploaded any videos yet. Upload your first video to get started!</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Leaderboard -->
        <div style="margin-top:30px;">
            <h3>Leaderboard (Your Improvement Group)</h3>
            <?php if(!empty($player_data)) { ?>
                <table>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <th>Average Rating</th>
                    </tr>
                    <?php 
                    $rank = 1;
                    foreach($player_data as $row) {
                        $avg = $row['avg_rating'] ? number_format($row['avg_rating'],1) : '0.0';
                        ?>
                        <tr>
                            <td><?= $rank ?></td>
                            <td><?= htmlspecialchars($row['player_name']) ?></td>
                            <td><?= $avg ?>/5</td>
                        </tr>
                    <?php 
                    $rank++;
                    } ?>
                </table>
            <?php } else { ?>
                <p>No leaderboard data available yet. Upload videos to appear on the leaderboard!</p>
            <?php } ?>
        </div>
    </div>

    <!-- View Profile -->
    <div id="view-profile" class="section">
        <h2>My Profile</h2>
        <table>
            <tr><th>ID</th><td><?php echo $player['id']; ?></td></tr>
            <tr><th>Name</th><td><?php echo htmlspecialchars($player['name']); ?></td></tr>
            <tr><th>Age</th><td><?php echo $player['age']; ?></td></tr>
            <tr><th>Phone</th><td><?php echo htmlspecialchars($player['phone']); ?></td></tr>
            <tr><th>Email</th><td><?php echo htmlspecialchars($player['email']); ?></td></tr>
            <tr><th>Improvement Area</th><td><?php echo htmlspecialchars($player['improvement_name']); ?></td></tr>
            <tr><th>Academy</th><td><?php echo htmlspecialchars($player['academyname'] ?? 'Not assigned'); ?></td></tr>
            <tr><th>Member Since</th><td><?php echo $player['created_at']; ?></td></tr>
        </table>
    </div>
    
    <!-- Edit Profile -->
    <div id="edit-profile" class="section">
        <h2>Edit Profile</h2>
        <?php if($update_error) echo "<div class='message error'>$update_error</div>"; ?>
        <?php if($update_success) echo "<div class='message success'>$update_success</div>"; ?>
        
        <form method="POST">
            <input type="hidden" name="update_profile" value="1">
            
            <div class="form-group">
                <label>Name:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($player['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Age:</label>
                <input type="number" name="age" value="<?php echo $player['age']; ?>" required min="10" max="100">
            </div>
            
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($player['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($player['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Improvement Area:</label>
                <select name="improvement_id" required>
                    <option value="">Select Improvement Area</option>
                    <?php
                    $categories->data_seek(0);
                    while($cat = $categories->fetch_assoc()) {
                        $selected = ($cat['id'] == $player['improvement_id']) ? 'selected' : '';
                        echo "<option value='{$cat['id']}' $selected>" . htmlspecialchars($cat['improvement_name']) . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Password (leave blank to keep current):</label>
                <input type="password" name="password" placeholder="Enter new password">
            </div>
            
            <div class="form-group">
                <label>Member Since:</label>
                <input type="text" value="<?php echo $player['created_at']; ?>" readonly>
            </div>
            
            <button type="submit">Update Profile</button>
        </form>
    </div>


    <!-- Training Videos -->
    <div id="training-videos" class="section">
        <h2>Coach Training Videos</h2>
        <p>Browse training videos from your coaches to improve your skills.</p>
        
        <?php if($videos->num_rows > 0): ?>
            <div class="video-grid">
                <?php foreach($videos as $v){ 
                    $upload_date = date('M j, Y g:i A', strtotime($v['uploaded_at']));
                ?>
                    <div class="video-thumb" onclick="openModal('modal-training-<?= $v['id'] ?>')">
                        <video src="<?php echo $v['file_path']; ?>" preload="metadata"></video>
                        <div class="video-info">
                            <p><?php echo htmlspecialchars($v['title']); ?></p>
                            <div class="uploader-info">
                                <span class="uploader-name">Uploaded by: <?php echo htmlspecialchars($v['coach_name'] ?? 'Coach'); ?></span>
                            </div>
                            <small><?php echo htmlspecialchars($v['improvement_name']); ?></small><br>
                            <small><?php echo $upload_date; ?></small>
                        </div>
                    </div>

                    <!-- Enhanced Modal -->
                    <div id="modal-training-<?= $v['id'] ?>" class="video-modal">
                        <div class="modal-container">
                            <div class="modal-header">
                                <h3><?php echo htmlspecialchars($v['title']); ?></h3>
                                <button class="back-btn" onclick="closeModal('modal-training-<?= $v['id'] ?>')">✕ Close</button>
                            </div>
                            <div class="modal-content">
                                <div class="modal-video-section">
                                    <video controls>
                                        <source src="<?php echo $v['file_path']; ?>" type="video/mp4">
                                    </video>
                                </div>
                                <div class="modal-details-section">
                                    <div class="video-info-item">
                                        <div class="info-label">Coach</div>
                                        <div class="info-value"><?php echo htmlspecialchars($v['coach_name'] ?? 'Coach'); ?></div>
                                    </div>
                                    <div class="video-info-item">
                                        <div class="info-label">Category</div>
                                        <div class="info-value"><?php echo htmlspecialchars($v['improvement_name']); ?></div>
                                    </div>
                                    <div class="video-info-item">
                                        <div class="info-label">Uploaded</div>
                                        <div class="info-value"><?php echo $upload_date; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php else: ?>
            <p>No training videos available for your improvement area yet.</p>
        <?php endif; ?>
    </div>

    <!-- Upload Progress Video -->
    <div id="upload-video" class="section">
        <h2>Upload Your Progress Video</h2>
        
        <!-- Upload Status -->
        <div class="upload-status <?php echo $can_upload ? 'upload-allowed' : ''; ?>">
            <?php if($can_upload): ?>
                <p>✅ <strong>Upload Allowed:</strong> You can upload a new video. Your previous video has been rated.</p>
            <?php else: ?>
                <p>⏳ <strong>Upload Restricted:</strong> <?php echo $last_video_status; ?></p>
            <?php endif; ?>
        </div>
        
        <?php if($upload_error) echo "<div class='message error'>$upload_error</div>"; ?>
        <?php if($upload_success) echo "<div class='message success'>$upload_success</div>"; ?>
        
        <div class="upload-form">
            <form method="POST" enctype="multipart/form-data" id="videoUploadForm">
                <input type="hidden" name="upload_video" value="1">
                
                <div class="form-group">
                    <label>Video Title:</label>
                    <input type="text" name="title" required placeholder="Enter a descriptive title for your video" <?php echo !$can_upload ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label>Category:</label>
                    <input type="text" value="<?php echo htmlspecialchars($player['improvement_name']); ?>" readonly>
                    <input type="hidden" name="improvement_id" value="<?php echo $player['improvement_id']; ?>">
                </div>
                
                <div class="form-group">
                    <label>Choose Video File:</label>
                    <input type="file" name="video_file" accept="video/*" required <?php echo !$can_upload ? 'disabled' : ''; ?>>
                    <small>Allowed formats: MP4, MOV, AVI, WebM. Maximum file size may be limited by server settings.</small>
                </div>
                
                <button type="submit" <?php echo !$can_upload ? 'disabled' : ''; ?>>
                    <?php echo $can_upload ? 'Upload Video' : 'Upload Disabled - Wait for Coach Rating'; ?>
                </button>
                
                <?php if(!$can_upload): ?>
                    <p style="color: #666; font-size: 14px; margin-top: 10px;">
                        <strong>Note:</strong> You can only upload a new video after your coach has rated your previous submission. 
                        This ensures you receive proper feedback before continuing your practice.
                    </p>
                <?php endif; ?>
            </form>
        </div>

        <h3 style="margin-top:40px;">Your Progress Videos</h3>
        <div class="video-grid">
            <?php
            // Fetch player's progress videos with player name and ratings
            $player_progress_videos = $conn->query("
                SELECT v.*, i.improvement_name, p.name as player_name,
                       (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) as rating_count,
                       (SELECT AVG(rating) FROM progress_ratings WHERE video_id = v.id) as avg_rating
                FROM player_progress_videos v
                JOIN improvement_types i ON v.improvement_id = i.id
                JOIN player p ON v.player_id = p.id
                WHERE v.player_id = $player_id
                ORDER BY v.uploaded_at DESC
            ");
            
            if($player_progress_videos->num_rows > 0):
                while($pv = $player_progress_videos->fetch_assoc()) { 
                    $vid_id = $pv['id'];
                    $has_rating = $pv['rating_count'] > 0;
                    $avg_display = $has_rating ? number_format($pv['avg_rating'], 1)."/5" : "Awaiting rating";
                    $upload_date = date('M j, Y g:i A', strtotime($pv['uploaded_at']));
            ?>
                <div class="video-thumb" onclick="openModal('modal-progress-<?= $vid_id ?>')">
                    <video src="<?php echo $pv['file_path']; ?>" preload="metadata"></video>
                    <div class="video-info">
                        <p><?php echo htmlspecialchars($pv['title']); ?></p>
                        <div class="uploader-info">
                            <span class="uploader-name">Uploaded by: <?php echo htmlspecialchars($pv['player_name']); ?></span>
                        </div>
                        <small><?php echo htmlspecialchars($pv['improvement_name']); ?></small><br>
                        <small><?php echo $upload_date; ?></small><br>
                        <small><strong>Coach Rating: <?php echo $avg_display; ?></strong></small>
                        <br>
                        <small style="color: <?php echo $has_rating ? '#28a745' : '#ffc107'; ?>; font-weight: bold;">
                            <?php echo $has_rating ? '✅ Rated' : '⏳ Pending Rating'; ?>
                        </small>
                    </div>
                </div>

                <!-- Enhanced Modal for Progress Videos -->
                <div id="modal-progress-<?= $vid_id ?>" class="video-modal">
                    <div class="modal-container">
                        <div class="modal-header">
                            <h3><?php echo htmlspecialchars($pv['title']); ?></h3>
                            <button class="back-btn" onclick="closeModal('modal-progress-<?= $vid_id ?>')">✕ Close</button>
                        </div>
                        <div class="modal-content">
                            <div class="modal-video-section">
                                <video controls>
                                    <source src="<?php echo $pv['file_path']; ?>" type="video/mp4">
                                </video>
                            </div>
                            <div class="modal-details-section">
                                <div class="video-info-item">
                                    <div class="info-label">Player</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pv['player_name']); ?></div>
                                </div>
                                <div class="video-info-item">
                                    <div class="info-label">Category</div>
                                    <div class="info-value"><?php echo htmlspecialchars($pv['improvement_name']); ?></div>
                                </div>
                                <div class="video-info-item">
                                    <div class="info-label">Uploaded</div>
                                    <div class="info-value"><?php echo $upload_date; ?></div>
                                </div>
                                <div class="video-info-item">
                                    <div class="info-label">Coach Rating</div>
                                    <div class="info-value"><?php echo $avg_display; ?></div>
                                </div>
                                <div class="video-info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value" style="color: <?php echo $has_rating ? '#28a745' : '#ffc107'; ?>; font-weight: bold;">
                                        <?php echo $has_rating ? '✅ Rated' : '⏳ Pending Rating'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php 
                }
            else: ?>
                <p>You haven't uploaded any progress videos yet. Upload your first video to track your progress!</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chat Section -->
    <div id="chat-section" class="section">
        <h2>Chat</h2>

        <div style="display:flex; gap:20px;">
            <!-- Chat Users List -->
            <div style="width:250px; background:#0077aa; color:white; padding:10px; border-radius:8px;">
                <h3>Chat With</h3>
                <ul style="list-style:none; padding:0;">
                    <?php
                    // Fetch possible chat contacts
                    $coach_res = $conn->query("SELECT id, name FROM coach WHERE academy_id = " . (int)$player['academy_id']);
                    while($coach = $coach_res->fetch_assoc()) {
                        echo "<li><a href='#' style='color:white;text-decoration:none;' onclick=\"openChat({$coach['id']}, 'coach', '{$coach['name']}')\">Coach: {$coach['name']}</a></li>";
                    }

                    // Player's academy
                    $academy_res = $conn->query("SELECT ser_id, academyname FROM academy WHERE ser_id = " . (int)$player['academy_id']);
                    while($a = $academy_res->fetch_assoc()) {
                        echo "<li><a href='#' style='color:white;text-decoration:none;' onclick=\"openChat({$a['ser_id']}, 'academy', '{$a['academyname']}')\">Academy: {$a['academyname']}</a></li>";
                    }

                    // Admin chat option
                    $admin_res = $conn->query("SELECT id, email FROM admin");
                    while($adm = $admin_res->fetch_assoc()) {
                        echo "<li><a href='#' style='color:white;text-decoration:none;' onclick=\"openChat({$adm['id']}, 'admin', '{$adm['email']}')\">Admin: {$adm['email']}</a></li>";
                    }
                    ?>
                </ul>
            </div>

            <!-- Chat Box -->
            <div style="flex:1; background:white; border-radius:8px; padding:15px; box-shadow:0 0 5px rgba(0,0,0,0.1); display:flex; flex-direction:column;">
                <h3 id="chatWith">Select a contact</h3>
                <div id="chatBox" style="flex:1; overflow-y:auto; border:1px solid #ccc; border-radius:5px; padding:10px; margin-bottom:10px; height:400px;"></div>

                <div style="display:flex;">
                    <input type="text" id="chatMessage" placeholder="Type your message..." style="flex:1;padding:10px;border:1px solid #ccc;border-radius:5px;">
                    <button onclick="sendChat()" style="margin-left:10px;">Send</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let receiverId = null;
let receiverRole = null;

function openChat(id, role, name) {
    receiverId = id;
    receiverRole = role;
    document.getElementById('chatWith').innerText = "Chat with " + name;
    loadChat();
}

function loadChat() {
    if (!receiverId) return;
    fetch("fetch_messages.php?chat_with=" + receiverId + "&role=" + receiverRole)
        .then(res => res.text())
        .then(data => {
            document.getElementById("chatBox").innerHTML = data;
            let box = document.getElementById("chatBox");
            box.scrollTop = box.scrollHeight;
        });
}

function sendChat() {
    let msg = document.getElementById("chatMessage").value.trim();
    if (msg === '' || !receiverId) return;
    fetch("send_message.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "receiver_id=" + receiverId + "&receiver_role=" + receiverRole + "&message=" + encodeURIComponent(msg)
    }).then(() => {
        document.getElementById("chatMessage").value = "";
        loadChat();
    });
}

setInterval(loadChat, 3000);
</script>

</body>
</html>