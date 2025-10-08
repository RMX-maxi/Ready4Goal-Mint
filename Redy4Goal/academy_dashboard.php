<?php
session_start();
include("newconn.php"); // DB connection

// Check if academy is logged in
if(!isset($_SESSION['academy_id'])) {
    header("Location: academy_login.php?error=Please login first");
    exit;
}
$academy_id = $_SESSION['academy_id'];

// =========================== Drill Library Upload ===========================
$drill_msg = '';
if(isset($_POST['upload_drill'])){
    $title = $conn->real_escape_string($_POST['title']);

    if(isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error']==0){
        $target_dir = "uploads/drills/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $file_name = basename($_FILES['pdf_file']['name']);
        $target_file = $target_dir.$file_name;

        // Check if file already exists in DB for this academy
        $check = $conn->query("SELECT id FROM drill_library WHERE academy_id=$academy_id AND file_path='$target_file'");
        if($check->num_rows > 0){
            $drill_msg = "This PDF is already uploaded!";
        } else {
            if(move_uploaded_file($_FILES['pdf_file']['tmp_name'], $target_file)){
                $sql = "INSERT INTO drill_library (academy_id, title, file_path) VALUES ($academy_id, '$title', '$target_file')";
                if($conn->query($sql)) $drill_msg="Drill uploaded successfully!";
                else $drill_msg="Database error: Could not save drill.";
            } else $drill_msg="Failed to move uploaded file.";
        }
    } else $drill_msg="Please select a PDF file.";
}

// =========================== Delete Match ===========================
if(isset($_GET['delete_match'])) {
    $match_id = (int)$_GET['delete_match'];
    
    // Delete the match
    $conn->query("DELETE FROM matches WHERE id=$match_id AND academy_id=$academy_id");
    
    header("Location: academy_dashboard.php?success=Match deleted successfully");
    exit;
}

// =========================== Fetch Academy Info ===========================
$academy_res = $conn->query("SELECT * FROM academy WHERE ser_id=$academy_id");
$academy = $academy_res->fetch_assoc();

// =========================== Player & Leaderboard ===========================
$total_players_res = $conn->query("SELECT COUNT(*) AS total_players FROM player WHERE academy_id=$academy_id");
$total_players = $total_players_res->fetch_assoc()['total_players'] ?? 0;

$total_coaches_res = $conn->query("SELECT COUNT(*) AS total_coaches FROM coach WHERE academy_id=$academy_id");
$total_coaches = $total_coaches_res->fetch_assoc()['total_coaches'] ?? 0;

// Players grouped by improvement type
$improvement_counts_res = $conn->query("
    SELECT i.improvement_name, COUNT(p.id) AS player_count
    FROM player p
    LEFT JOIN improvement_types i ON p.improvement_id = i.id
    WHERE p.academy_id=$academy_id
    GROUP BY p.improvement_id
");
$improvement_counts = [];
while($row = $improvement_counts_res->fetch_assoc()) $improvement_counts[] = $row;

// Leaderboard
$leaderboard_res = $conn->query("
    SELECT p.id AS player_id, p.name AS player_name, 
           IFNULL(AVG(r.rating),0) AS avg_rating
    FROM player p
    LEFT JOIN player_progress_videos v ON v.player_id=p.id
    LEFT JOIN progress_ratings r ON v.id=r.video_id
    WHERE p.academy_id=$academy_id
    GROUP BY p.id
    ORDER BY avg_rating DESC
");
$leaderboard=[];
while($row=$leaderboard_res->fetch_assoc()) $leaderboard[]=$row;

// =========================== Fetch Drills ===========================
$drills_res = $conn->query("SELECT * FROM drill_library WHERE academy_id=$academy_id ORDER BY uploaded_at DESC");

// =========================== Fetch Matches ===========================
$matches_res = $conn->query("SELECT * FROM matches WHERE academy_id=$academy_id ORDER BY match_date DESC, match_time DESC");

// =========================== Fetch Users (Players & Coaches) ===========================
$players_list_res = $conn->query("SELECT id, name, email, phone, improvement_id FROM player WHERE academy_id=$academy_id ORDER BY name");
$coaches_list_res = $conn->query("SELECT id, name, email, phone FROM coach WHERE academy_id=$academy_id ORDER BY name");
?>

<!DOCTYPE html>
<html>
<head>
<title>Academy Dashboard</title>
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
        .video-thumb { 
            width: calc(25% - 20px); 
            cursor:pointer; 
            border-radius:12px; 
            overflow:hidden; 
            background:#fff; 
            box-shadow:0 4px 12px rgba(0,0,0,0.15); 
            transition:transform 0.3s, box-shadow 0.3s;
            border: 3px solid #e9ecef;
        }
        .video-thumb:hover { 
            transform:translateY(-5px); 
            box-shadow:0 8px 20px rgba(0,0,0,0.2);
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
        
        /* Drill Library Styling */
        .drill-grid { 
            display:flex; 
            flex-wrap:wrap; 
            gap:20px; 
            margin-top:20px; 
            justify-content: flex-start;
        }
        .drill-thumb { 
            width: calc(25% - 20px); 
            border-radius:12px; 
            overflow:hidden; 
            background:#fff; 
            box-shadow:0 4px 12px rgba(0,0,0,0.15); 
            transition:transform 0.3s, box-shadow 0.3s;
            border: 3px solid #e9ecef;
            text-align: center;
            padding: 20px;
        }
        .drill-thumb:hover { 
            transform:translateY(-5px); 
            box-shadow:0 8px 20px rgba(0,0,0,0.2);
            border-color: #0099cc;
        }
        .drill-thumb a {
            display: inline-block;
            background: #0099cc;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 10px;
        }
        .drill-thumb a:hover {
            background: #0077aa;
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
        
        /* Users Section Styling */
        .users-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        .users-column {
            flex: 1;
        }
        .user-card {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
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
        }
        .user-card p {
            margin: 5px 0;
            color: #555;
        }
        .user-type {
            display: inline-block;
            background: #0099cc;
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            margin-bottom: 8px;
        }
        
        /* Delete Button Styling */
        .delete-btn { 
            color:#fff; 
            background:#cc0000; 
            padding:8px 15px; 
            border-radius:4px; 
            text-decoration:none; 
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .delete-btn:hover { 
            background:#990000; 
        }
        
        @media (max-width: 1200px) {
            .video-thumb, .drill-thumb {
                width: calc(33.333% - 20px);
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
            .video-thumb, .drill-thumb {
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
            .users-container {
                flex-direction: column;
            }
        }
</style>
<script>
function showSection(sectionId){
    document.querySelectorAll('.section').forEach(div => div.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
}

function confirmDelete(matchId) {
    if(confirm("Are you sure you want to delete this match? This action cannot be undone.")) {
        window.location.href = "academy_dashboard.php?delete_match=" + matchId;
    }
}

window.onload = () => { showSection('dashboard'); }

// Chat functionality
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
    fetch("fetch_messages_academy.php?chat_with=" + receiverId + "&role=" + receiverRole)
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
    fetch("send_message_academy.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "receiver_id=" + receiverId + "&receiver_role=" + receiverRole + "&message=" + encodeURIComponent(msg)
    }).then(() => {
        document.getElementById("chatMessage").value = "";
        loadChat();
    });
}

// Auto-refresh chat every 3 seconds
setInterval(() => {
    if (receiverId) loadChat();
}, 3000);
</script>
</head>
<body>

<div class="sidebar">
    <h2>Academy Panel</h2>
    <a href="#" onclick="showSection('dashboard')">Dashboard</a>
    <a href="#" onclick="showSection('academy-profile')">Academy Profile</a>
    <a href="#" onclick="showSection('edit-profile')">Edit Profile</a>
    <a href="#" onclick="showSection('users-section')">Users</a>
    <a href="#" onclick="showSection('match-details')">Match Details</a>
    <a href="#" onclick="showSection('player-progress')">Player Progress</a>
    <a href="#" onclick="showSection('drill-library')">Drill Library</a>
    <a href="#" onclick="showSection('chat-section')">Chat</a>
    <a href="academy_logout.php">Logout</a>
</div>

<div class="main-content">

<!-- Dashboard -->
<div id="dashboard" class="section active">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['academy_name']); ?>!</h1>
    <p>Manage your academy, track player progress, and coordinate with coaches.</p>

    <div class="dashboard-stats">
        <div class="stat-card">
            <h3>Total Players</h3>
            <div class="stat-number"><?php echo $total_players; ?></div>
            <p>Registered players</p>
        </div>
        <div class="stat-card">
            <h3>Total Coaches</h3>
            <div class="stat-number"><?php echo $total_coaches; ?></div>
            <p>Active coaches</p>
        </div>
        <div class="stat-card">
            <h3>Improvement Areas</h3>
            <div class="stat-number"><?php echo count($improvement_counts); ?></div>
            <p>Training categories</p>
        </div>
    </div>

    <div style="display: flex; gap: 30px; margin-top: 30px;">
        <!-- Players by Improvement -->
        <div style="flex: 1;">
            <h3>Players by Improvement Area</h3>
            <table>
                <tr><th>Improvement Type</th><th>Number of Players</th></tr>
                <?php foreach($improvement_counts as $imp){ ?>
                <tr>
                    <td><?php echo htmlspecialchars($imp['improvement_name']); ?></td>
                    <td><?php echo $imp['player_count']; ?></td>
                </tr>
                <?php } ?>
            </table>
        </div>

        <!-- Leaderboard -->
        <div style="flex: 1;">
            <h3>Player Leaderboard</h3>
            <?php if(!empty($leaderboard)){ ?>
            <table>
                <tr><th>Player Name</th><th>Average Rating</th></tr>
                <?php foreach($leaderboard as $p){ 
                    $avg = $p['avg_rating'] ? number_format($p['avg_rating'],1) : '0.0'; 
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($p['player_name']); ?></td>
                    <td><?php echo $avg; ?>/5</td>
                </tr>
                <?php } ?>
            </table>
            <?php } else { ?>
                <p>No player ratings available yet.</p>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Academy Profile -->
<div id="academy-profile" class="section">
    <h2>Academy Profile</h2>
    <table>
        <tr><th>Academy Name</th><td><?php echo htmlspecialchars($academy['academyname']); ?></td></tr>
        <tr><th>Coach Name</th><td><?php echo htmlspecialchars($academy['coachname']); ?></td></tr>
        <tr><th>Since Date</th><td><?php echo $academy['sincedate']; ?></td></tr>
        <tr><th>Main City</th><td><?php echo htmlspecialchars($academy['maincity']); ?></td></tr>
        <tr><th>Email</th><td><?php echo htmlspecialchars($academy['email']); ?></td></tr>
        <tr><th>Phone</th><td><?php echo htmlspecialchars($academy['phone']); ?></td></tr>
    </table>
</div>

<!-- Edit Profile -->
<div id="edit-profile" class="section">
    <h2>Edit Profile</h2>
    <div class="upload-form">
        <form method="POST" action="update_academy_profile.php">
            <div class="form-group">
                <label>Academy Name:</label>
                <input type="text" name="academyname" value="<?php echo htmlspecialchars($academy['academyname']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Coach Name:</label>
                <input type="text" name="coachname" value="<?php echo htmlspecialchars($academy['coachname']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email:</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($academy['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Phone:</label>
                <input type="text" name="phone" value="<?php echo htmlspecialchars($academy['phone']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>New Password (optional):</label>
                <input type="password" name="password" placeholder="New password">
            </div>
            
            <button type="submit">Update Profile</button>
        </form>
    </div>
</div>

<!-- Users Section -->
<div id="users-section" class="section">
    <h2>Users Management</h2>
    <p>View all players and coaches in your academy.</p>
    
    <div class="users-container">
        <!-- Players Column -->
        <div class="users-column">
            <h3 style="color: #0099cc; border-bottom: 2px solid #0099cc; padding-bottom: 10px;">Players (<?php echo $total_players; ?>)</h3>
            
            <?php if($players_list_res->num_rows > 0): ?>
                <?php while($player = $players_list_res->fetch_assoc()): ?>
                    <div class="user-card">
                        <span class="user-type">Player</span>
                        <h4><?php echo htmlspecialchars($player['name']); ?></h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($player['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($player['phone']); ?></p>
                        <p><strong>Improvement ID:</strong> <?php echo $player['improvement_id'] ? $player['improvement_id'] : 'Not assigned'; ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="user-card">
                    <p>No players found in your academy.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Coaches Column -->
        <div class="users-column">
            <h3 style="color: #0099cc; border-bottom: 2px solid #0099cc; padding-bottom: 10px;">Coaches (<?php echo $total_coaches; ?>)</h3>
            
            <?php if($coaches_list_res->num_rows > 0): ?>
                <?php while($coach = $coaches_list_res->fetch_assoc()): ?>
                    <div class="user-card">
                        <span class="user-type">Coach</span>
                        <h4><?php echo htmlspecialchars($coach['name']); ?></h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($coach['email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($coach['phone']); ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="user-card">
                    <p>No coaches found in your academy.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Match Details -->
<div id="match-details" class="section">
    <h2>Match Details</h2>
    
    <div class="upload-form">
        <h3>Add New Match</h3>
        <?php
        if(isset($_POST['add_match'])){
            $team_a = $conn->real_escape_string($_POST['team_a']);
            $team_b = $conn->real_escape_string($_POST['team_b']);
            $result = $conn->real_escape_string($_POST['result']);
            $venue = $conn->real_escape_string($_POST['venue']);
            $match_date = $_POST['match_date'];
            $match_time = $_POST['match_time'];

            // Check duplicate match
            $check = $conn->query("SELECT id FROM matches WHERE academy_id=$academy_id AND team_a='$team_a' AND team_b='$team_b' AND match_date='$match_date' AND match_time='$match_time'");
            if($check->num_rows > 0){
                echo "<div class='message error'>This match already exists!</div>";
            } else {
                $conn->query("INSERT INTO matches (academy_id, team_a, team_b, result, venue, match_date, match_time) VALUES ($academy_id, '$team_a', '$team_b', '$result', '$venue', '$match_date', '$match_time')");
                echo "<div class='message success'>Match added successfully!</div>";
                echo "<meta http-equiv='refresh' content='0'>";
            }
        }
        ?>
        <form method="POST">
            <input type="hidden" name="add_match" value="1">
            
            <div class="form-group">
                <label>Team A:</label>
                <input type="text" name="team_a" required>
            </div>
            
            <div class="form-group">
                <label>Team B:</label>
                <input type="text" name="team_b" required>
            </div>
            
            <div class="form-group">
                <label>Result:</label>
                <input type="text" name="result" placeholder="e.g. 2-1">
            </div>
            
            <div class="form-group">
                <label>Venue:</label>
                <input type="text" name="venue">
            </div>
            
            <div class="form-group">
                <label>Date:</label>
                <input type="date" name="match_date">
            </div>
            
            <div class="form-group">
                <label>Time:</label>
                <input type="time" name="match_time">
            </div>
            
            <button type="submit">Add Match</button>
        </form>
    </div>

    <h3 style="margin-top: 30px;">Match History</h3>
    <table>
        <tr>
            <th>Teams</th>
            <th>Result</th>
            <th>Venue</th>
            <th>Date</th>
            <th>Time</th>
            <th>Action</th>
        </tr>
        <?php 
        $matches_res = $conn->query("SELECT * FROM matches WHERE academy_id=$academy_id ORDER BY match_date DESC, match_time DESC");
        while($match=$matches_res->fetch_assoc()){ 
        ?>
        <tr>
            <td><?php echo htmlspecialchars($match['team_a']." vs ".$match['team_b']); ?></td>
            <td><?php echo htmlspecialchars($match['result']); ?></td>
            <td><?php echo htmlspecialchars($match['venue']); ?></td>
            <td><?php echo $match['match_date']; ?></td>
            <td><?php echo $match['match_time']; ?></td>
            <td>
                <button class="delete-btn" onclick="confirmDelete(<?php echo $match['id']; ?>)">Delete</button>
            </td>
        </tr>
        <?php } ?>
    </table>
</div>

<!-- Player Progress Videos -->
<div id="player-progress" class="section">
    <h2>Player Progress Videos</h2>
    <p>Review progress videos from all players in your academy.</p>
    
    <?php
    $players_res = $conn->query("SELECT id, name FROM player WHERE academy_id=$academy_id");
    if($players_res->num_rows > 0){
        while($player = $players_res->fetch_assoc()){
            $player_id = $player['id'];
            echo "<h3 style='margin-top: 30px; color: #0099cc;'>Player: " . htmlspecialchars($player['name']) . "</h3>";

            $videos_res = $conn->query("
                SELECT v.id, v.title, v.file_path, v.uploaded_at,
                       IFNULL(AVG(r.rating), 0) AS avg_rating
                FROM player_progress_videos v
                LEFT JOIN progress_ratings r ON v.id = r.video_id
                WHERE v.player_id=$player_id
                GROUP BY v.id
                ORDER BY v.uploaded_at DESC
            ");

            if($videos_res->num_rows > 0){
                echo "<div class='video-grid'>";
                while($v = $videos_res->fetch_assoc()){
                    $avg_display = $v['avg_rating'] ? number_format($v['avg_rating'], 1) . "/5" : "Not rated yet";
                    $upload_date = date('M j, Y g:i A', strtotime($v['uploaded_at']));
                    echo "<div class='video-thumb'>
                            <video src='" . htmlspecialchars($v['file_path']) . "' preload='metadata'></video>
                            <div class='video-info'>
                                <p>" . htmlspecialchars($v['title']) . "</p>
                                <small>Uploaded: " . $upload_date . "</small><br>
                                <small><strong>Rating: " . $avg_display . "</strong></small>
                            </div>
                          </div>";
                }
                echo "</div>";
            } else {
                echo "<p>No progress videos uploaded yet for this player.</p>";
            }
        }
    } else {
        echo "<p>No players found in your academy.</p>";
    }
    ?>
</div>

<!-- Drill Library -->
<div id="drill-library" class="section">
    <h2>Drill Library</h2>
    <p>Upload and manage training drills for your academy.</p>
    
    <?php if($drill_msg) echo "<div class='message " . (strpos($drill_msg, 'successfully') !== false ? 'success' : 'error') . "'>$drill_msg</div>"; ?>
    
    <div class="upload-form">
        <h3>Upload New Drill (PDF)</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Title:</label>
                <input type="text" name="title" required>
            </div>
            
            <div class="form-group">
                <label>PDF File:</label>
                <input type="file" name="pdf_file" accept="application/pdf" required>
            </div>
            
            <button type="submit" name="upload_drill">Upload Drill</button>
        </form>
    </div>

    <h3 style="margin-top: 40px;">All Drills</h3>
    <?php if($drills_res->num_rows > 0){ ?>
    <div class="drill-grid">
        <?php while($d = $drills_res->fetch_assoc()){ 
            $upload_date = date('M j, Y g:i A', strtotime($d['uploaded_at']));
        ?>
        <div class="drill-thumb">
            <h4><?php echo htmlspecialchars($d['title']); ?></h4>
            <small>Uploaded: <?php echo $upload_date; ?></small><br>
            <a href="<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank">📄 View PDF</a>
        </div>
        <?php } ?>
    </div>
    <?php } else { ?>
        <p>No drills uploaded yet.</p>
    <?php } ?>
</div>

<!-- Chat System -->
<div id="chat-section" class="section">
    <h2>Chat System</h2>
    <p>Communicate with players, coaches, and administrators.</p>

    <div class="chat-container">
        <!-- Left Side: Contact List -->
        <div class="chat-contacts">
            <h3>Chat With</h3>
            <ul>
                <?php
                // Players in same academy
                $players = $conn->query("SELECT id, name FROM player WHERE academy_id=$academy_id");
                while($p = $players->fetch_assoc()){
                    echo "<li><a href='#' onclick=\"openChat({$p['id']}, 'player', '{$p['name']}')\">Player: {$p['name']}</a></li>";
                }

                // Coaches in same academy
                $coaches = $conn->query("SELECT id, name FROM coach WHERE academy_id=$academy_id");
                while($c = $coaches->fetch_assoc()){
                    echo "<li><a href='#' onclick=\"openChat({$c['id']}, 'coach', '{$c['name']}')\">Coach: {$c['name']}</a></li>";
                }

                // Admins
                $admins = $conn->query("SELECT id, email FROM admin");
                while($a = $admins->fetch_assoc()){
                    echo "<li><a href='#' onclick=\"openChat({$a['id']}, 'admin', '{$a['email']}')\">Admin: {$a['email']}</a></li>";
                }
                ?>
            </ul>
        </div>

        <!-- Right Side: Chat Box -->
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

</div>
</body>
</html>