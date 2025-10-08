<?php
session_start();
include("newconn.php");

// Check if admin is logged in
if(!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php?error=Please login first");
    exit;
}

// Drill library deletion
if(isset($_GET['delete_drill'])) {
    $drill_id = (int)$_GET['delete_drill'];

    // Optional: delete the file from server
    $file_res = $conn->query("SELECT file_path FROM drill_library WHERE id=$drill_id");
    if($file_res->num_rows > 0) {
        $file_path = $file_res->fetch_assoc()['file_path'];
        if(file_exists($file_path)) unlink($file_path); // remove PDF from server
    }

    // Delete from database
    $conn->query("DELETE FROM drill_library WHERE id=$drill_id");

    // Redirect to avoid resubmission
    header("Location: admin_dashboard.php?success=Drill deleted successfully");
    exit;
}

// Delete progress video
if(isset($_GET['delete_id']) && isset($_GET['type'])) {
    $id = (int)$_GET['delete_id'];
    $type = $_GET['type'];

    if($type === 'player') {
        $conn->query("DELETE FROM player WHERE id=$id");
    } elseif($type === 'coach') {
        $conn->query("DELETE FROM coach WHERE id=$id");
    } elseif($type === 'academy') {
        $conn->query("DELETE FROM academy WHERE id=$id");
    } elseif($type === 'video') {
        $conn->query("DELETE FROM videos WHERE id=$id");
        $conn->query("DELETE FROM comments WHERE video_id=$id"); // remove associated comments
    } elseif($type === 'comment') {
        $conn->query("DELETE FROM comments WHERE id=$id");
    } elseif($type === 'player-progress') {
        $conn->query("DELETE FROM player_progress_videos WHERE id=$id"); // delete progress video
    } elseif($type === 'improvement') {
        $conn->query("DELETE FROM improvement_types WHERE id=$id");
    }
    header("Location: admin_dashboard.php"); // redirect to avoid resubmission
    exit;
}

// Handle adding new improvement type
if(isset($_POST['add_improvement'])) {
    $improvement_name = $conn->real_escape_string($_POST['improvement_name']);
    if(!empty($improvement_name)){
        $conn->query("INSERT INTO improvement_types (improvement_name) VALUES ('$improvement_name')");
        header("Location: admin_dashboard.php?success=Improvement added successfully");
        exit;
    }
}

// Handle profile update
if(isset($_POST['update_profile'])) {
    $admin_id = (int)$_POST['admin_id'];
    $name = $conn->real_escape_string($_POST['name']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    if(!empty($password)) {
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $conn->query("UPDATE admin SET name='$name', email='$email', password='$password_hashed' WHERE id=$admin_id");
    } else {
        $conn->query("UPDATE admin SET name='$name', email='$email' WHERE id=$admin_id");
    }

    $_SESSION['admin_name'] = $name; // update session name
    header("Location: admin_dashboard.php?success=Profile updated successfully");
    exit;
}

// Fetch total counts
$total_players = $conn->query("SELECT COUNT(*) as total FROM player")->fetch_assoc()['total'];
$total_coaches = $conn->query("SELECT COUNT(*) as total FROM coach")->fetch_assoc()['total'];
$total_academies = $conn->query("SELECT COUNT(*) as total FROM academy")->fetch_assoc()['total'];
$total_videos = $conn->query("SELECT COUNT(*) as total FROM videos")->fetch_assoc()['total'];
$total_comments = $conn->query("SELECT COUNT(*) as total FROM comments")->fetch_assoc()['total'];
$total_drills = $conn->query("SELECT COUNT(*) as total FROM drill_library")->fetch_assoc()['total'];
$total_improvements = $conn->query("SELECT COUNT(*) as total FROM improvement_types")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard</title>
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
th, td { 
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

/* Dashboard Stats */
.dashboard-stats {
    display: flex;
    gap: 20px;
    margin: 20px 0;
    flex-wrap: wrap;
}
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    flex: 1;
    min-width: 200px;
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

/* Video Grid */
.video-grid { 
    display:flex; 
    flex-wrap:wrap; 
    gap:20px; 
    margin-top:20px; 
    justify-content: flex-start;
}
.video-card { 
    width: calc(25% - 20px); 
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
.video-card p, .video-card small { 
    margin:5px 0; 
    font-size:14px; 
    color:#333; 
    line-height: 1.4;
}
.video-card p {
    font-weight: bold;
    font-size: 16px;
}

/* Drill Library */
.drill-grid { 
    display:flex; 
    flex-wrap:wrap; 
    gap:20px; 
    margin-top:20px; 
    justify-content: flex-start;
}
.drill-card { 
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
.drill-card:hover { 
    transform:translateY(-5px); 
    box-shadow:0 8px 20px rgba(0,0,0,0.2);
    border-color: #0099cc;
}

/* Chat System */
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

/* Action Buttons */
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

.upload-form { 
    background:#f9f9f9; 
    padding:25px; 
    border-radius:8px; 
    border: 1px solid #ddd;
    margin-top:20px; 
    max-width:700px; 
}

@media (max-width: 1200px) {
    .video-card, .drill-card {
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
    .video-card, .drill-card {
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
}
</style>
<script>
function showSection(sectionId) {
    document.querySelectorAll('.section').forEach(div => div.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    localStorage.setItem('activeSection', sectionId);
}

function confirmDelete(id, type) {
    if(confirm("Are you sure you want to delete this "+type+"?")) {
        window.location.href = "admin_dashboard.php?delete_id="+id+"&type="+type;
    }
}

window.onload = () => {
    let active = localStorage.getItem('activeSection') || 'dashboard';
    showSection(active);
};

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
    fetch("fetch_messages_admin.php?chat_with=" + receiverId + "&role=" + receiverRole)
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
    fetch("send_message_admin.php", {
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
    <h2>Admin Panel</h2>
    <a href="#" onclick="showSection('dashboard')">Dashboard</a>
    <a href="#" onclick="showSection('players')">Players</a>
    <a href="#" onclick="showSection('coaches')">Coaches</a>
    <a href="#" onclick="showSection('academies')">Academies</a>
    <a href="#" onclick="showSection('videos')">Videos</a>
    <a href="#" onclick="showSection('player-progress')">Player Progress</a>
    <a href="#" onclick="showSection('improvement-types')">Improvement Types</a>
    <a href="#" onclick="showSection('drill-library')">Drill Library</a>
    <a href="#" onclick="showSection('chat-section')">Chat System</a>
    <a href="#" onclick="showSection('view-profile')">View Profile</a>
    <a href="admin_logout.php">Logout</a>
</div>

<div class="main-content">

    <!-- Dashboard Section -->
    <div id="dashboard" class="section active">
        <h1>Welcome Admin <?php echo $_SESSION['admin_name']; ?>!</h1>
        <p>Manage the entire platform from this admin dashboard.</p>

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
                <h3>Total Academies</h3>
                <div class="stat-number"><?php echo $total_academies; ?></div>
                <p>Football academies</p>
            </div>
            <div class="stat-card">
                <h3>Training Videos</h3>
                <div class="stat-number"><?php echo $total_videos; ?></div>
                <p>Uploaded videos</p>
            </div>
            <div class="stat-card">
                <h3>Drill Library</h3>
                <div class="stat-number"><?php echo $total_drills; ?></div>
                <p>Training drills</p>
            </div>
            <div class="stat-card">
                <h3>Improvement Areas</h3>
                <div class="stat-number"><?php echo $total_improvements; ?></div>
                <p>Training categories</p>
            </div>
        </div>
    </div>

    <!-- Players Section -->
    <div id="players" class="section">
        <h2>All Players</h2>
        <table>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Academy</th><th>Action</th></tr>
            <?php
            $res = $conn->query("
                SELECT p.*, a.academyname 
                FROM player p 
                LEFT JOIN academy a ON p.academy_id = a.id
            ");
            while($row = $res->fetch_assoc()){
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['academyname']}</td>
                        <td><button class='delete-btn' onclick='confirmDelete({$row['id']}, \"player\")'>Delete</button></td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Coaches Section -->
    <div id="coaches" class="section">
        <h2>All Coaches</h2>
        <table>
            <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Academy</th><th>Action</th></tr>
            <?php
            $res = $conn->query("
                SELECT c.*, a.academyname 
                FROM coach c 
                LEFT JOIN academy a ON c.academy_id = a.id
            ");
            while($row = $res->fetch_assoc()){
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['name']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['academyname']}</td>
                        <td><button class='delete-btn' onclick='confirmDelete({$row['id']}, \"coach\")'>Delete</button></td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Academies Section -->
    <div id="academies" class="section">
        <h2>All Academies</h2>
        <table>
            <tr><th>ID</th><th>Academy Name</th><th>Coach Name</th><th>Email</th><th>Phone</th><th>City</th><th>Action</th></tr>
            <?php
            $res = $conn->query("SELECT * FROM academy");
            while($row = $res->fetch_assoc()){
                echo "<tr>
                        <td>{$row['id']}</td>
                        <td>{$row['academyname']}</td>
                        <td>{$row['coachname']}</td>
                        <td>{$row['email']}</td>
                        <td>{$row['phone']}</td>
                        <td>{$row['maincity']}</td>
                        <td><button class='delete-btn' onclick='confirmDelete({$row['id']}, \"academy\")'>Delete</button></td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Improvement Types Section -->
    <div id="improvement-types" class="section">
        <h2>Manage Improvement Types</h2>

        <div class="upload-form">
            <h3>Add New Improvement Type</h3>
            <form method="POST" action="admin_dashboard.php">
                <div class="form-group">
                    <label>Improvement Type Name:</label>
                    <input type="text" name="improvement_name" placeholder="Enter improvement name" required>
                </div>
                <button type="submit" name="add_improvement">Add Improvement</button>
            </form>
        </div>

        <h3 style="margin-top: 30px;">Existing Improvement Types</h3>
        <table>
            <tr><th>ID</th><th>Improvement Name</th><th>Action</th></tr>
            <?php
            $improvements = $conn->query("SELECT * FROM improvement_types ORDER BY id DESC");
            while($imp = $improvements->fetch_assoc()){
                echo "<tr>
                        <td>{$imp['id']}</td>
                        <td>{$imp['improvement_name']}</td>
                        <td><button class='delete-btn' onclick='confirmDelete({$imp['id']}, \"improvement\")'>Delete</button></td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Drill Library Section -->
    <div id="drill-library" class="section">
        <h2>Drill Library</h2>
        <table>
            <tr><th>ID</th><th>Title</th><th>Academy</th><th>Uploaded At</th><th>Action</th></tr>
            <?php
            $drills = $conn->query("
                SELECT d.*, a.academyname 
                FROM drill_library d 
                LEFT JOIN academy a ON d.academy_id = a.id 
                ORDER BY uploaded_at DESC
            ");
            while($d = $drills->fetch_assoc()) {
                echo "<tr>
                        <td>{$d['id']}</td>
                        <td>{$d['title']}</td>
                        <td>{$d['academyname']}</td>
                        <td>{$d['uploaded_at']}</td>
                        <td>
                            <button class='delete-btn' onclick='if(confirm(\"Are you sure?\")) window.location.href=\"?delete_drill={$d['id']}\"'>Delete</button>
                        </td>
                      </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Player Progress Videos Section -->
    <div id="player-progress" class="section">
        <h2>Player Progress Videos</h2>
        <?php
        $progress_videos = $conn->query("
            SELECT v.id, v.title, v.file_path, v.uploaded_at, p.name AS player_name, a.academyname
            FROM player_progress_videos v
            JOIN player p ON v.player_id = p.id
            LEFT JOIN academy a ON p.academy_id = a.id
            ORDER BY v.uploaded_at DESC
        ");

        if($progress_videos->num_rows > 0){
            echo "<div class='video-grid'>";
            while($v = $progress_videos->fetch_assoc()){
                echo "<div class='video-card'>
                        <video controls>
                            <source src='".htmlspecialchars($v['file_path'])."' type='video/mp4'>
                        </video>
                        <div class='video-info'>
                            <p>".htmlspecialchars($v['title'])."</p>
                            <small>Player: ".htmlspecialchars($v['player_name'])."</small><br>
                            <small>Academy: ".htmlspecialchars($v['academyname'])."</small><br>
                            <small>Uploaded: ".$v['uploaded_at']."</small><br>
                            <button class='delete-btn' onclick='confirmDelete({$v['id']}, \"player-progress\")'>Delete Video</button>
                        </div>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<p>No player progress videos uploaded yet.</p>";
        }
        ?>
    </div>

    <!-- Videos Section -->
    <div id="videos" class="section">
        <h2>All Training Videos</h2>
        <?php
        $videos = $conn->query("
            SELECT v.*, c.name as coach_name, i.improvement_name, a.academyname
            FROM videos v
            JOIN coach c ON v.coach_id = c.id
            JOIN improvement_types i ON v.improvement_id = i.id
            LEFT JOIN academy a ON c.academy_id = a.id
            ORDER BY v.uploaded_at DESC
        ");

        if ($videos->num_rows > 0) {
            echo "<div class='video-grid'>";
            while ($video = $videos->fetch_assoc()) {
                echo "<div class='video-card'>
                        <video controls>
                            <source src='{$video['file_path']}' type='video/mp4'>
                        </video>
                        <div class='video-info'>
                            <p>{$video['title']}</p>
                            <small>Coach: {$video['coach_name']}</small><br>
                            <small>Academy: {$video['academyname']}</small><br>
                            <small>Improvement: {$video['improvement_name']}</small><br>
                            <small>Uploaded: {$video['uploaded_at']}</small><br>
                            <button class='delete-btn' onclick='confirmDelete({$video['id']}, \"video\")'>Delete Video</button>
                        </div>
                      </div>";
            }
            echo "</div>";
        } else {
            echo "<p>No videos uploaded yet.</p>";
        }
        ?>
    </div>

    <!-- View Profile Section -->
    <div id="view-profile" class="section">
        <h2>Admin Profile</h2>
        <?php
        $admin_res = $conn->query("SELECT * FROM admin WHERE id={$_SESSION['admin_id']}");
        $admin = $admin_res->fetch_assoc();
        ?>
        <table>
            <tr><th>ID</th><td><?php echo $admin['id']; ?></td></tr>
            <tr><th>Name</th><td><?php echo $admin['name']; ?></td></tr>
            <tr><th>Email</th><td><?php echo $admin['email']; ?></td></tr>
            <tr><th>Role ID</th><td><?php echo $admin['role_id']; ?></td></tr>
        </table>
        <br>
        <button onclick="showSection('edit-profile')">Edit Profile</button>
    </div>

    <!-- Edit Profile Section -->
    <div id="edit-profile" class="section">
        <h2>Edit Admin Profile</h2>
        <div class="upload-form">
            <form method="POST" action="admin_dashboard.php">
                <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                
                <div class="form-group">
                    <label>Name:</label>
                    <input type="text" name="name" value="<?php echo $admin['name']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo $admin['email']; ?>" required>
                </div>
                
                <div class="form-group">
                    <label>New Password (leave blank to keep current):</label>
                    <input type="password" name="password">
                </div>
                
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
    </div>

    <!-- Chat System Section -->
    <div id="chat-section" class="section">
        <h2>Admin Chat System</h2>
        <p>Communicate with players, coaches, and academies.</p>

        <div class="chat-container">
            <!-- Left Side: Contact List -->
            <div class="chat-contacts">
                <h3>Chat With</h3>
                <ul>
                    <?php
                    // Fetch Players
                    $players = $conn->query("SELECT id, name FROM player");
                    while($p = $players->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$p['id']}, 'player', '{$p['name']}')\">Player: {$p['name']}</a></li>";
                    }

                    // Fetch Coaches
                    $coaches = $conn->query("SELECT id, name FROM coach");
                    while($c = $coaches->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$c['id']}, 'coach', '{$c['name']}')\">Coach: {$c['name']}</a></li>";
                    }

                    // Fetch Academies
                    $academies = $conn->query("SELECT id, academyname FROM academy");
                    while($a = $academies->fetch_assoc()) {
                        echo "<li><a href='#' onclick=\"openChat({$a['id']}, 'academy', '{$a['academyname']}')\">Academy: {$a['academyname']}</a></li>";
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