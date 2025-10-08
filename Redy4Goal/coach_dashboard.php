<?php
session_start();
include("newconn.php");

if (!isset($_SESSION['coach_id'])) {
    header("Location: coach_login.php?error=Please login first");
    exit;
}

// Total players
$result = $conn->query("SELECT COUNT(*) as total FROM player");
$row = $result->fetch_assoc();
$total_players = $row['total'];

// Coach details
$coach_id = $_SESSION['coach_id'];
$result = $conn->query("
    SELECT c.*, 
           a.academyname, 
           i.improvement_name 
    FROM coach c
    LEFT JOIN academy a ON c.academy_id = a.id
    LEFT JOIN improvement_types i ON c.improvement_id = i.id
    WHERE c.id = $coach_id
");
$coach = $result->fetch_assoc();

// Handle rating submission
if(isset($_POST['progress_rating'], $_POST['progress_video_id'])){
    $vid = (int)$_POST['progress_video_id'];
    $rate = (int)$_POST['progress_rating'];
    
    // Remove previous rating by this coach for this video
    $conn->query("DELETE FROM progress_ratings WHERE coach_id=$coach_id AND video_id=$vid");
    
    // Insert new rating
    $conn->query("INSERT INTO progress_ratings (video_id, coach_id, rating, created_at) 
                  VALUES ($vid, $coach_id, $rate, NOW())");
    
    // Update video status to 'rated'
    $conn->query("UPDATE player_progress_videos SET status='rated' WHERE id=$vid");
    
    $open_modal_id = $vid;
    
    $rating_success = "✅ Rating submitted successfully! Player can now upload their next video.";
}

// =========================== Fetch Users (Players in same academy) ===========================
$coach_academy_id = $coach['academy_id'];
$players_list_res = $conn->query("
    SELECT p.id, p.name, p.email, p.phone, p.improvement_id, i.improvement_name
    FROM player p 
    LEFT JOIN improvement_types i ON p.improvement_id = i.id
    WHERE p.academy_id = $coach_academy_id 
    ORDER BY p.name
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Coach Dashboard</title>
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
        
        .form-group {
            margin-bottom: 15px;
        }
        label {
            font-weight: bold;
            margin-bottom: 5px;
            display: block;
            color: #333;
        }
        
        /* Video Grid Styling */
        .video-grid { 
            display:flex; 
            flex-wrap:wrap; 
            gap:20px; 
            margin-top:20px; 
            justify-content: flex-start;
        }
        .video-card { 
            width: calc(25% - 20px); 
            cursor:pointer; 
            border-radius:12px; 
            overflow:hidden; 
            background:#fff; 
            box-shadow:0 4px 12px rgba(0,0,0,0.15); 
            transition:transform 0.3s, box-shadow 0.3s;
            border: 3px solid #e9ecef;
        }
        .video-card:hover { 
            transform:translateY(-5px); 
            box-shadow:0 8px 20px rgba(0,0,0,0.2);
            border-color: #0099cc;
        }
        .video-card video { 
            width:100%; 
            height:200px; 
            object-fit:cover; 
            display: block;
            border-bottom: 2px solid #e9ecef;
        }
        .video-card .video-info { 
            padding: 15px;
        }
        .video-card h3, .video-card small { 
            margin:5px 0; 
            font-size:14px; 
            color:#333; 
            line-height: 1.4;
        }
        .video-card h3 {
            font-weight: bold;
            font-size: 16px;
        }
        
        .upload-form { 
            background:#f9f9f9; 
            padding:25px; 
            border-radius:8px; 
            border: 1px solid #ddd;
            margin-top:20px; 
            max-width:700px; 
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
        .warning { 
            background:#fff3cd; 
            color:#856404; 
            border-color: #ffeaa7;
        }
        .info { 
            background:#d1ecf1; 
            color:#0c5460; 
            border-color: #bee5eb;
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
        
        /* Simple 5-Star Rating System */
        .rating-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-top: 20px;
            border: 2px solid #e9ecef;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .rating-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin: 15px 0;
        }
        .star-rating input {
            display: none;
        }
        .star-rating label {
            font-size: 40px;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s;
            padding: 5px;
        }
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #ffc107 !important;
        }
        .star-rating input:checked ~ label {
            color: #ffc107;
        }
        .star-rating input:checked + label {
            color: #ffc107;
        }
        
        .rating-text {
            text-align: center;
            font-size: 16px;
            color: #666;
            margin-top: 10px;
        }
        
        .video-info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .video-info-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        .info-label {
            font-weight: bold;
            color: #555;
            font-size: 14px;
            margin-bottom: 5px;
        }
        .info-value {
            color: #333;
            font-size: 16px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-rated {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .current-rating {
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            color: #ffc107;
            margin: 10px 0;
        }
        .current-rating .stars {
            font-size: 28px;
            letter-spacing: 2px;
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
        
        /* View Videos Section Specific Styling */
        .training-video-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 25px;
            margin-top: 20px;
        }
        .training-video-card {
            width: calc(33.333% - 25px);
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 3px solid #e9ecef;
        }
        .training-video-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #0099cc;
        }
        .training-video-card video {
            width: 100%;
            height: 220px;
            object-fit: cover;
            display: block;
            border-bottom: 2px solid #e9ecef;
        }
        .training-video-info {
            padding: 18px;
        }
        .training-video-info h3 {
            margin: 0 0 8px 0;
            font-size: 16px;
            color: #333;
            font-weight: bold;
        }
        .training-video-info small {
            color: #666;
            font-size: 13px;
            display: block;
            margin-bottom: 5px;
        }
        
        /* Users Section Styling */
        .users-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }
        .user-card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-color: #0099cc;
        }
        .user-card h4 {
            margin: 0 0 10px 0;
            color: #0099cc;
            font-size: 18px;
        }
        .user-card p {
            margin: 5px 0;
            color: #555;
        }
        .user-type {
            display: inline-block;
            background: #0099cc;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-bottom: 8px;
        }
        .improvement-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-left: 10px;
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
            .video-card, .training-video-card {
                width: calc(33.333% - 20px);
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
            .video-card, .training-video-card {
                width: calc(50% - 20px);
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
            .star-rating label {
                font-size: 30px;
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

        function updateRatingText(rating) {
            const ratingText = document.querySelector('.rating-text');
            const texts = [
                "Select a rating",
                "Poor - Needs significant improvement",
                "Fair - Below average performance", 
                "Good - Meets basic expectations",
                "Very Good - Above average performance",
                "Excellent - Outstanding performance"
            ];
            
            if (ratingText && rating >= 0 && rating <= 5) {
                ratingText.textContent = texts[rating];
            }
        }

        window.onload = () => { showSection('dashboard'); }
    </script>
</head>
<body>
<div class="sidebar">
    <h2>Coach Panel</h2>
    <a href="#" onclick="showSection('dashboard')">Dashboard</a>
    <a href="#" onclick="showSection('upload-video')">Upload Video</a>
    <a href="#" onclick="showSection('view-videos')">View Videos</a>
    <a href="#" onclick="showSection('player-progress')">Player Progress</a>
    <a href="#" onclick="showSection('users-section')">Users</a>
    <a href="#" onclick="showSection('drill-library')">Drill Library</a>
    <a href="#" onclick="showSection('profile')">Profile</a>
    <a href="#" onclick="showSection('edit-profile')">Edit Profile</a>
    <a href="#" onclick="showSection('chat-section')">Chat</a>
    <a href="coach_logout.php">Logout</a>
</div>

<div class="main-content">
    <!-- Dashboard Section -->
    <div id="dashboard" class="section active">
        <h1>Welcome Coach <?php echo htmlspecialchars($_SESSION['coach_name']); ?>!</h1>
        <p>This is your coaching dashboard where you can manage training videos and track player progress.</p>

        <?php
        $coach_id = $_SESSION['coach_id'];

        // Get coach's improvement type
        $coach_query = "SELECT improvement_id FROM coach WHERE id=$coach_id";
        $coach_result = $conn->query($coach_query);
        $coach_data = $coach_result->fetch_assoc();

        if ($coach_data && isset($coach_data['improvement_id'])) {
            $improvement_id = $coach_data['improvement_id'];

            // Pending videos count
            $pending_count_res = $conn->query("
                SELECT COUNT(*) as pending_count 
                FROM player_progress_videos 
                WHERE improvement_id=$improvement_id 
                AND (status IS NULL OR status != 'rated')
            ");
            $pending_count = $pending_count_res->fetch_assoc()['pending_count'];

            // Total players in this improvement type
            $players_count_res = $conn->query("
                SELECT COUNT(*) as players_count 
                FROM player 
                WHERE improvement_id=$improvement_id
            ");
            $players_count = $players_count_res->fetch_assoc()['players_count'];

            // Leaderboard data
            $leaderboard = $conn->query("
                SELECT p.id AS player_id, p.name AS player_name, 
                       IFNULL(AVG(r.rating), 0) AS avg_rating
                FROM player_progress_videos v
                JOIN player p ON v.player_id = p.id
                LEFT JOIN progress_ratings r ON v.id = r.video_id
                WHERE v.improvement_id = $improvement_id
                GROUP BY p.id
                ORDER BY avg_rating DESC
            ");

            $player_data = [];
            while ($row = $leaderboard->fetch_assoc()) {
                $player_data[] = $row;
            }
        ?>
            <div class="dashboard-stats">
                <div class="stat-card">
                    <h3>Total Players</h3>
                    <div class="stat-number"><?= $total_players ?></div>
                    <p>All academy players</p>
                </div>
                <div class="stat-card">
                    <h3>Your Players</h3>
                    <div class="stat-number"><?= $players_count ?></div>
                    <p>In your improvement area</p>
                </div>
                <div class="stat-card">
                    <h3>Videos to Rate</h3>
                    <div class="stat-number"><?= $pending_count ?></div>
                    <p>Awaiting your review</p>
                </div>
            </div>

            <?php if($pending_count > 0): ?>
                <div class="message warning">
                    <strong>⚠️ Attention:</strong> You have <strong><?= $pending_count ?></strong> videos waiting for your rating. 
                    Players cannot upload new videos until you rate their previous ones.
                </div>
            <?php endif; ?>

            <!-- Leaderboard -->
            <div style="margin-top:30px;">
                <h3>Player Leaderboard (Your Improvement Area)</h3>
                <?php if(!empty($player_data)): ?>
                    <table>
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Average Rating</th>
                        </tr>
                        <?php 
                        $rank = 1;
                        foreach($player_data as $row):
                            $avg = $row['avg_rating'] ? number_format($row['avg_rating'], 1) : '0.0';
                        ?>
                            <tr>
                                <td><?= $rank ?></td>
                                <td><?= htmlspecialchars($row['player_name']) ?></td>
                                <td><?= $avg ?>/5</td>
                            </tr>
                        <?php 
                        $rank++;
                        endforeach; 
                        ?>
                    </table>
                <?php else: ?>
                    <p>No player ratings available yet for your improvement area.</p>
                <?php endif; ?>
            </div>
        <?php
        } else {
            echo "<div class='message warning'>No improvement type assigned. Please contact admin to assign you an improvement category.</div>";
        }
        ?>
    </div>

    <!-- Users Section -->
    <div id="users-section" class="section">
        <h2>Players in Your Academy</h2>
        <p>View all players belonging to your academy: <strong><?php echo htmlspecialchars($coach['academyname']); ?></strong></p>
        
        <div class="users-container">
            <?php
            if($players_list_res->num_rows > 0):
                while($player = $players_list_res->fetch_assoc()):
            ?>
                <div class="user-card">
                    <span class="user-type">Player</span>
                    <h4><?php echo htmlspecialchars($player['name']); ?></h4>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($player['email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($player['phone']); ?></p>
                    <p><strong>Improvement Area:</strong> 
                        <?php if($player['improvement_name']): ?>
                            <span class="improvement-badge"><?php echo htmlspecialchars($player['improvement_name']); ?></span>
                        <?php else: ?>
                            <span style="color: #666;">Not assigned</span>
                        <?php endif; ?>
                    </p>
                </div>
            <?php
                endwhile;
            else:
            ?>
                <div class="user-card">
                    <p>No players found in your academy.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Videos Section -->
    <div id="view-videos" class="section">
        <h2>Training Videos</h2>
        <p>Your uploaded training videos for players.</p>
        
        <?php
        $videos = $conn->query("SELECT v.*, i.improvement_name 
                                FROM videos v 
                                JOIN improvement_types i ON v.improvement_id=i.id 
                                WHERE v.coach_id = $coach_id
                                ORDER BY v.uploaded_at DESC");
        if($videos->num_rows > 0){
            echo "<div class='training-video-grid'>";
            while($video = $videos->fetch_assoc()){
                $file_path = htmlspecialchars($video['file_path']);
                $upload_date = date('M j, Y g:i A', strtotime($video['uploaded_at']));
                echo "<div class='training-video-card' onclick=\"openModal('modal-training-{$video['id']}')\">
                        <video preload='metadata'>
                            <source src='{$file_path}' type='video/mp4'>
                        </video>
                        <div class='training-video-info'>
                            <h3>".htmlspecialchars($video['title'])."</h3>
                            <small>".htmlspecialchars($video['improvement_name'])."</small>
                            <small>Uploaded: {$upload_date}</small>
                        </div>
                      </div>";

                // Modal for this video
                echo "<div id='modal-training-{$video['id']}' class='video-modal'>
                        <div class='modal-container'>
                            <div class='modal-header'>
                                <h3>".htmlspecialchars($video['title'])."</h3>
                                <button class='back-btn' onclick=\"closeModal('modal-training-{$video['id']}')\">✕ Close</button>
                            </div>
                            <div class='modal-content'>
                                <div class='modal-video-section'>
                                    <video controls>
                                        <source src='{$file_path}' type='video/mp4'>
                                        Your browser does not support the video tag.
                                    </video>
                                </div>
                                <div class='modal-details-section'>
                                    <div class='video-info-item'>
                                        <div class='info-label'>Improvement Area</div>
                                        <div class='info-value'>".htmlspecialchars($video['improvement_name'])."</div>
                                    </div>
                                    <div class='video-info-item'>
                                        <div class='info-label'>Upload Date</div>
                                        <div class='info-value'>{$upload_date}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<p>No training videos uploaded yet.</p>";
        }
        ?>
    </div>

    <!-- Player Progress Section -->
    <div id="player-progress" class="section">
        <h2>Player Progress Videos</h2>
        <p>Review and rate player progress videos in your improvement area.</p>
        
        <?php
        if(isset($rating_success)) {
            echo "<div class='message success'>$rating_success</div>";
        }

        // Fetch coach improvement
        $coach_query = "SELECT improvement_id FROM coach WHERE id=$coach_id";
        $coach_result = $conn->query($coach_query);
        $coach_data = $coach_result->fetch_assoc();

        if($coach_data && isset($coach_data['improvement_id'])){
            $improvement_id = $coach_data['improvement_id'];
            
            // Get pending videos count
            $pending_count_res = $conn->query("
                SELECT COUNT(*) as pending_count 
                FROM player_progress_videos 
                WHERE improvement_id=$improvement_id 
                AND (status IS NULL OR status != 'rated')
            ");
            $pending_count = $pending_count_res->fetch_assoc()['pending_count'];
            
            if($pending_count > 0) {
                echo "<div class='message warning'>
                        ⚠️ You have <strong>$pending_count</strong> videos waiting for your rating. Players cannot upload new videos until you rate their previous ones.
                      </div>";
            }
            
            $stmt = $conn->prepare("
                SELECT v.*, i.improvement_name, p.name AS player_name,
                       (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) as rating_count,
                       (SELECT AVG(rating) FROM progress_ratings WHERE video_id = v.id) as avg_rating
                FROM player_progress_videos v
                JOIN improvement_types i ON v.improvement_id=i.id
                JOIN player p ON v.player_id=p.id
                WHERE v.improvement_id=?
                ORDER BY 
                    CASE WHEN (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) = 0 THEN 0 ELSE 1 END,
                    v.uploaded_at DESC
            ");
            $stmt->bind_param("i", $improvement_id);
            $stmt->execute();
            $videos = $stmt->get_result();

            if($videos->num_rows > 0){
                echo "<div class='video-grid'>";
                while($video = $videos->fetch_assoc()):
                    $vid_id = $video['id'];
                    $video_src = htmlspecialchars($video['file_path']); 
                    $has_rating = $video['rating_count'] > 0;
                    $avg_rating = $video['avg_rating'];

                    // Format upload date
                    $upload_date = date('M j, Y g:i A', strtotime($video['uploaded_at']));
                    
                    // Status indicator
                    $status_color = $has_rating ? '#28a745' : '#ffc107';
                    $status_text = $has_rating ? '✅ Rated' : '⏳ Awaiting Rating';
                    
                    // Generate star display for current rating
                    $star_display = '';
                    if($avg_rating > 0) {
                        $full_stars = floor($avg_rating);
                        $half_star = ($avg_rating - $full_stars) >= 0.5;
                        $empty_stars = 5 - $full_stars - ($half_star ? 1 : 0);
                        
                        $star_display = str_repeat('⭐', $full_stars) . 
                                       ($half_star ? '⭐' : '') . 
                                       str_repeat('☆', $empty_stars);
                    } else {
                        $star_display = '☆☆☆☆☆';
                    }
                ?>
                <div class="video-card" onclick="openModal('modal-progress-<?= $vid_id ?>')">
                    <video preload='metadata'>
                        <source src="<?= $video_src ?>" type="video/mp4">
                    </video>
                    <div class="video-info">
                        <h3><?= htmlspecialchars($video['title']) ?></h3>
                        <small>Player: <?= htmlspecialchars($video['player_name']) ?></small><br>
                        <small>Uploaded: <?= $upload_date ?></small><br>
                        <small>Rating: <?= $avg_rating ? number_format($avg_rating,1)."/5" : "No rating yet" ?></small><br>
                        <small style="color: <?= $status_color ?>; font-weight: bold;">
                            <?= $status_text ?>
                        </small>
                    </div>
                </div>

                <!-- Enhanced Modal -->
                <div id="modal-progress-<?= $vid_id ?>" class="video-modal">
                    <div class="modal-container">
                        <div class="modal-header">
                            <h3><?= htmlspecialchars($video['title']) ?></h3>
                            <button class="back-btn" onclick="closeModal('modal-progress-<?= $vid_id ?>')">✕ Close</button>
                        </div>
                        <div class="modal-content">
                            <div class="modal-video-section">
                                <video controls>
                                    <source src="<?= $video_src ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            <div class="modal-details-section">
                                <div class="video-info-item">
                                    <div class="info-label">Player Name</div>
                                    <div class="info-value"><?= htmlspecialchars($video['player_name']) ?></div>
                                </div>
                                
                                <div class="video-info-item">
                                    <div class="info-label">Improvement Area</div>
                                    <div class="info-value"><?= htmlspecialchars($video['improvement_name']) ?></div>
                                </div>
                                
                                <div class="video-info-item">
                                    <div class="info-label">Upload Date</div>
                                    <div class="info-value"><?= $upload_date ?></div>
                                </div>
                                
                                <div class="video-info-item">
                                    <div class="info-label">Current Rating</div>
                                    <div class="current-rating">
                                        <div class="stars"><?= $star_display ?></div>
                                        <div><?= $avg_rating ? number_format($avg_rating,1)."/5" : "No ratings yet" ?></div>
                                    </div>
                                </div>
                                
                                <div class="video-info-item">
                                    <div class="info-label">Status</div>
                                    <div class="info-value">
                                        <span class="status-badge <?= $has_rating ? 'status-rated' : 'status-pending' ?>">
                                            <?= $status_text ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Simple 5-Star Rating Section -->
                                <div class="rating-section">
                                    <h4 style="margin-top: 0; margin-bottom: 15px; text-align: center;">Rate This Video</h4>
                                    <form method="POST" class="rating-form">
                                        <input type="hidden" name="progress_video_id" value="<?= $vid_id ?>">
                                        
                                        <div class="star-rating">
                                            <input type="radio" id="star5-<?= $vid_id ?>" name="progress_rating" value="5" onchange="updateRatingText(5)">
                                            <label for="star5-<?= $vid_id ?>" title="5 stars">★</label>
                                            
                                            <input type="radio" id="star4-<?= $vid_id ?>" name="progress_rating" value="4" onchange="updateRatingText(4)">
                                            <label for="star4-<?= $vid_id ?>" title="4 stars">★</label>
                                            
                                            <input type="radio" id="star3-<?= $vid_id ?>" name="progress_rating" value="3" onchange="updateRatingText(3)">
                                            <label for="star3-<?= $vid_id ?>" title="3 stars">★</label>
                                            
                                            <input type="radio" id="star2-<?= $vid_id ?>" name="progress_rating" value="2" onchange="updateRatingText(2)">
                                            <label for="star2-<?= $vid_id ?>" title="2 stars">★</label>
                                            
                                            <input type="radio" id="star1-<?= $vid_id ?>" name="progress_rating" value="1" onchange="updateRatingText(1)">
                                            <label for="star1-<?= $vid_id ?>" title="1 star">★</label>
                                        </div>
                                        
                                        <div class="rating-text">Select a rating</div>
                                        
                                        <button type="submit" style="background: #28a745; margin-top: 10px; padding: 15px; font-size: 16px;">
                                            ✅ Submit Rating & Unlock Next Upload
                                        </button>
                                    </form>
                                    
                                    <?php if($has_rating): ?>
                                        <div class="message success" style="margin-top: 15px; margin-bottom: 0;">
                                            <strong>✅ Rated:</strong> This player can now upload their next video.
                                        </div>
                                    <?php else: ?>
                                        <div class="message warning" style="margin-top: 15px; margin-bottom: 0;">
                                            <strong>⏳ Pending:</strong> This player cannot upload new videos until you rate this one.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                endwhile;
                echo "</div>";
            } else {
                echo "<p>No progress videos uploaded yet by players in your improvement category.</p>";
            }
            $stmt->close();
        } else {
            echo "<div class='message warning'>No improvement type assigned. Please contact admin to assign you an improvement category.</div>";
        }
        ?>
    </div>

    <!-- Other sections (Upload Video, Drill Library, Profile, Edit Profile, Chat) remain the same -->
    
     <div id="upload-video" class="section">
        <h2>Upload Training Video</h2>
        <?php
        // Fetch coach improvement type
        $coach_id = $_SESSION['coach_id'];
        $coach_query = "SELECT i.id AS improvement_id, i.improvement_name 
                        FROM coach c 
                        JOIN improvement_types i ON c.improvement_id = i.id 
                        WHERE c.id = $coach_id";
        $coach_result = $conn->query($coach_query);
        $coach_data = $coach_result->fetch_assoc();

        if (!$coach_data) {
            echo "<div class='message error'>No improvement type assigned to this coach yet. Please contact admin.</div>";
        } else {
            $coach_improvement_id = $coach_data['improvement_id'];
            $coach_improvement_name = $coach_data['improvement_name'];
        ?>
            <div class="upload-form">
                <form action="upload_video.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Title:</label>
                        <input type="text" name="title" required placeholder="Enter video title">
                    </div>
                    
                    <div class="form-group">
                        <label>Improvement Type:</label>
                        <input type="text" value="<?= htmlspecialchars($coach_improvement_name) ?>" readonly>
                        <input type="hidden" name="improvement_id" value="<?= $coach_improvement_id ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Select Video:</label>
                        <input type="file" name="video" accept="video/*" required>
                        <small>Allowed formats: MP4, MOV, AVI, WebM</small>
                    </div>
                    
                    <button type="submit">Upload Video</button>
                </form>
            </div>
        <?php } ?>
    </div>

    <!-- View Videos -->
    <div id="view-videos" class="section">
        <h2>Training Videos</h2>
        <p>Your uploaded training videos for players.</p>
        
        <?php
        $videos = $conn->query("SELECT v.*, i.improvement_name 
                                FROM videos v 
                                JOIN improvement_types i ON v.improvement_id=i.id 
                                WHERE v.coach_id = $coach_id
                                ORDER BY v.uploaded_at DESC");
        if($videos->num_rows > 0){
            echo "<div class='video-grid'>";
            while($video = $videos->fetch_assoc()){
                $file_path = htmlspecialchars($video['file_path']);
                echo "<div class='video-card' onclick=\"openModal('modal-training-{$video['id']}')\">
                        <video preload='metadata'>
                            <source src='{$file_path}' type='video/mp4'>
                        </video>
                        <div class='video-info'>
                            <h3>".htmlspecialchars($video['title'])."</h3>
                            <small>".htmlspecialchars($video['improvement_name'])."</small><br>
                            <small>".htmlspecialchars($video['uploaded_at'])."</small>
                        </div>
                      </div>";

                // Modal for this video
                echo "<div id='modal-training-{$video['id']}' class='video-modal'>
                        <button class='back-btn' onclick=\"closeModal('modal-training-{$video['id']}')\">← Back</button>
                        <div class='video-container'>
                            <video controls>
                                <source src='{$file_path}' type='video/mp4'>
                            </video>
                            <div class='video-details'>
                                <h3>".htmlspecialchars($video['title'])."</h3>
                                <p>Category: ".htmlspecialchars($video['improvement_name'])."</p>
                                <p>Uploaded: ".htmlspecialchars($video['uploaded_at'])."</p>
                            </div>
                        </div>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<p>No training videos uploaded yet.</p>";
        }
        ?>
    </div>

    <!-- Player Progress -->
    <div id="player-progress" class="section">
        <h2>Player Progress Videos</h2>
        <p>Review and rate player progress videos in your improvement area.</p>
        
        <?php
        if(isset($rating_success)) {
            echo "<div class='message success'>$rating_success</div>";
        }

        // Fetch coach improvement
        $coach_query = "SELECT improvement_id FROM coach WHERE id=$coach_id";
        $coach_result = $conn->query($coach_query);
        $coach_data = $coach_result->fetch_assoc();

        if($coach_data && isset($coach_data['improvement_id'])){
            $improvement_id = $coach_data['improvement_id'];
            
            // Get pending videos count
            $pending_count_res = $conn->query("
                SELECT COUNT(*) as pending_count 
                FROM player_progress_videos 
                WHERE improvement_id=$improvement_id 
                AND (status IS NULL OR status != 'rated')
            ");
            $pending_count = $pending_count_res->fetch_assoc()['pending_count'];
            
            if($pending_count > 0) {
                echo "<div class='message warning'>
                        ⚠️ You have <strong>$pending_count</strong> videos waiting for your rating. Players cannot upload new videos until you rate their previous ones.
                      </div>";
            }
            
            $stmt = $conn->prepare("
                SELECT v.*, i.improvement_name, p.name AS player_name,
                       (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) as rating_count
                FROM player_progress_videos v
                JOIN improvement_types i ON v.improvement_id=i.id
                JOIN player p ON v.player_id=p.id
                WHERE v.improvement_id=?
                ORDER BY 
                    CASE WHEN (SELECT COUNT(*) FROM progress_ratings WHERE video_id = v.id) = 0 THEN 0 ELSE 1 END,
                    v.uploaded_at DESC
            ");
            $stmt->bind_param("i", $improvement_id);
            $stmt->execute();
            $videos = $stmt->get_result();

            if($videos->num_rows > 0){
                echo "<div class='video-grid'>";
                while($video = $videos->fetch_assoc()):
                    $vid_id = $video['id'];
                    $video_src = htmlspecialchars($video['file_path']); 
                    $has_rating = $video['rating_count'] > 0;

                    // Get average rating
                    $avg_rating_res = $conn->query("SELECT AVG(rating) AS avg_rating FROM progress_ratings WHERE video_id=$vid_id");
                    $avg_rating = $avg_rating_res->fetch_assoc()['avg_rating'];
                    $avg_display = $avg_rating ? number_format($avg_rating,1)."/5" : "No rating yet";
                    
                    // Status indicator
                    $status_color = $has_rating ? '#28a745' : '#ffc107';
                    $status_text = $has_rating ? '✅ Rated' : '⏳ Awaiting Rating';
                ?>
                <div class="video-card" onclick="openModal('modal-progress-<?= $vid_id ?>')">
                    <video preload='metadata'>
                        <source src="<?= $video_src ?>" type="video/mp4">
                    </video>
                    <div class="video-info">
                        <h3><?= htmlspecialchars($video['title']) ?></h3>
                        <small>Player: <?= htmlspecialchars($video['player_name']) ?></small><br>
                        <small>Rating: <?= $avg_display ?></small><br>
                        <small style="color: <?= $status_color ?>; font-weight: bold;">
                            <?= $status_text ?>
                        </small>
                    </div>
                </div>

                <!-- Modal -->
                <div id="modal-progress-<?= $vid_id ?>" class="video-modal">
                    <button class="back-btn" onclick="closeModal('modal-progress-<?= $vid_id ?>')">← Back</button>
                    <div class="video-container">
                        <video controls>
                            <source src="<?= $video_src ?>" type="video/mp4">
                        </video>
                        <div class="video-details">
                            <h3><?= htmlspecialchars($video['title']) ?></h3>
                            <p>Player: <?= htmlspecialchars($video['player_name']) ?></p>
                            <p>Category: <?= htmlspecialchars($video['improvement_name']) ?></p>
                            <p>Uploaded: <?= $video['uploaded_at'] ?></p>
                            <p>Current Rating: <?= $avg_display ?></p>
                            <p style="color: <?= $status_color ?>;">Status: <?= $status_text ?></p>
                            
                            <!-- Rating Form -->
                            <div style="margin-top: 15px; padding: 15px; background: rgba(255,255,255,0.1); border-radius: 8px;">
                                <h4 style="margin-top: 0;">Rate this video</h4>
                                <form method="POST" style="display: flex; flex-direction: column; gap: 10px;">
                                    <input type="hidden" name="progress_video_id" value="<?= $vid_id ?>">
                                    <select name="progress_rating" required style="padding: 8px; border-radius: 4px; border: none;">
                                        <option value="">Select Rating (1-5 stars)</option>
                                        <option value="1">⭐ (1) - Needs Improvement</option>
                                        <option value="2">⭐⭐ (2) - Below Average</option>
                                        <option value="3">⭐⭐⭐ (3) - Average</option>
                                        <option value="4">⭐⭐⭐⭐ (4) - Good</option>
                                        <option value="5">⭐⭐⭐⭐⭐ (5) - Excellent</option>
                                    </select>
                                    <button type="submit" style="background: #28a745; color: white; border: none; padding: 10px; border-radius: 4px; cursor: pointer;">
                                        ✅ Submit Rating & Unlock Next Upload
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                endwhile;
                echo "</div>";
            } else {
                echo "<p>No progress videos uploaded yet by players in your improvement category.</p>";
            }
            $stmt->close();
        } else {
            echo "<div class='message warning'>No improvement type assigned. Please contact admin to assign you an improvement category.</div>";
        }
        ?>
    </div>

    <!-- Drill Library -->
    <div id="drill-library" class="section">
        <h2>Drill Library</h2>
        <p>Training materials and resources.</p>
        <?php
        $drills = $conn->query("SELECT * FROM drill_library ORDER BY uploaded_at DESC");
        if($drills->num_rows > 0){
            echo "<div class='video-grid'>";
            while($d = $drills->fetch_assoc()){
                $file_path = htmlspecialchars($d['file_path']);
                $title = htmlspecialchars($d['title']);
                $ext = pathinfo($file_path, PATHINFO_EXTENSION);
                
                echo "<div class='video-card'>";
                if(in_array(strtolower($ext), ['mp4','webm','ogg'])){
                    echo "<video controls>
                            <source src='{$file_path}' type='video/mp4'>
                          </video>";
                } elseif(strtolower($ext) === 'pdf'){
                    echo "<a href='{$file_path}' target='_blank' style='display:block;padding:20px;background:#f0f0f0;border-radius:5px;text-align:center;text-decoration:none;color:#333;'>
                            📄 View PDF
                          </a>";
                } else {
                    echo "<a href='{$file_path}' target='_blank' style='display:block;padding:20px;background:#f0f0f0;border-radius:5px;text-align:center;text-decoration:none;color:#333;'>
                            📎 Download File
                          </a>";
                }
                echo "<div class='video-info'>
                        <h3>{$title}</h3>
                        <small>Uploaded: {$d['uploaded_at']}</small>
                      </div>";
                echo "</div>";
            }
            echo "</div>";
        } else {
            echo "<p>No drill library files uploaded yet.</p>";
        }
        ?>
    </div>

    <!-- Profile -->
    <div id="profile" class="section">
        <h2>My Profile</h2>
        <table>
            <tr><th>ID</th><td><?php echo $coach['id']; ?></td></tr>
            <tr><th>Name</th><td><?php echo htmlspecialchars($coach['name']); ?></td></tr>
            <tr><th>Age</th><td><?php echo htmlspecialchars($coach['age']); ?></td></tr>
            <tr><th>Position</th><td><?php echo htmlspecialchars($coach['position']); ?></td></tr>
            <tr><th>Phone</th><td><?php echo htmlspecialchars($coach['phone']); ?></td></tr>
            <tr><th>Email</th><td><?php echo htmlspecialchars($coach['email']); ?></td></tr>
            <tr><th>Academy</th><td><?php echo $coach['academyname'] ? htmlspecialchars($coach['academyname']) : 'Not assigned'; ?></td></tr>
            <tr><th>Improvement Type</th><td><?php echo $coach['improvement_name'] ? htmlspecialchars($coach['improvement_name']) : 'Not assigned'; ?></td></tr>
            <tr><th>Member Since</th><td><?php echo htmlspecialchars($coach['created_at']); ?></td></tr>
        </table>
    </div>

    <!-- Edit Profile -->
    <div id="edit-profile" class="section">
        <h2>Edit Profile</h2>
        <div class="upload-form">
            <form method="POST" action="update_coach_profile.php">
                <input type="hidden" name="id" value="<?php echo $coach['id']; ?>">
                
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($coach['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Age:</label>
                    <input type="number" name="age" value="<?php echo htmlspecialchars($coach['age']); ?>" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>Position:</label>
                    <input type="text" name="position" value="<?php echo htmlspecialchars($coach['position']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone:</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($coach['phone']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($coach['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Password (leave blank to keep current):</label>
                    <input type="password" name="password" placeholder="Enter new password">
                </div>
                
                <button type="submit">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Chat Section -->
    <div id="chat-section" class="section">
        <h2>Chat</h2>
        <p>Communicate with players, academy staff, and administrators.</p>

        <div class="chat-container">
            <!-- Chat Contacts List -->
            <div class="chat-contacts">
                <h3>Chat With</h3>
                <ul>
                    <?php
                    // Get academy info for this coach
                    $coach_academy_id = (int)$coach['academy_id'];
                    $academy_res = $conn->query("SELECT ser_id, academyname FROM academy WHERE ser_id = $coach_academy_id");
                    while($a = $academy_res->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$a['ser_id']}, 'academy', '{$a['academyname']}')\">Academy: {$a['academyname']}</a></li>";
                    }

                    // Get all players under same academy
                    $players = $conn->query("SELECT id, name FROM player WHERE academy_id = $coach_academy_id");
                    while($p = $players->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$p['id']}, 'player', '{$p['name']}')\">Player: {$p['name']}</a></li>";
                    }

                    // Admin chat
                    $admin_res = $conn->query("SELECT id, email FROM admin");
                    while($adm = $admin_res->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$adm['id']}, 'admin', '{$adm['email']}')\">Admin: {$adm['email']}</a></li>";
                    }
                    ?>
                </ul>
            </div>

            <!-- Chat Box -->
            <div class="chat-box">
                <div class="chat-header" id="chatWith">Select a contact to start chatting</div>
                <div class="chat-messages" id="chatBox">
                    <div style="text-align: center; color: #666; padding: 20px;">
                        Select a contact from the list to start a conversation
                    </div>
                </div>
                <div class="chat-input">
                    <input type="text" id="chatMessage" placeholder="Type your message..." disabled>
                    <button onclick="sendChat()" disabled id="sendButton">Send</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ... (rest of the code remains unchanged) ... -->

</div>

<!-- JavaScript for Chat -->
<script>
let receiverId = null;
let receiverRole = null;

function openChat(id, role, name) {
    receiverId = id;
    receiverRole = role;
    document.getElementById('chatWith').innerText = "Chat with " + name;
    document.getElementById('chatMessage').disabled = false;
    document.getElementById('sendButton').disabled = false;
    document.getElementById('chatMessage').focus();
    loadChat();
}

function loadChat() {
    if (!receiverId) return;
    fetch("fetch_messages_coach.php?chat_with=" + receiverId + "&role=" + receiverRole)
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
    fetch("send_message_coach.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "receiver_id=" + receiverId + "&receiver_role=" + receiverRole + "&message=" + encodeURIComponent(msg)
    }).then(() => {
        document.getElementById("chatMessage").value = "";
        loadChat();
    });
}

// Auto-refresh every 3 seconds
setInterval(() => {
    if (receiverId) loadChat();
}, 3000);
</script>

</body>
</html>