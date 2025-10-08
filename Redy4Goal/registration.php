<!DOCTYPE html>
<html>
<head>
  <title>Home Page - Ready4Goal</title>
  <style>
    body {
      margin: 0;
      height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Arial', sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    .container {
      text-align: center;
      background: white;
      padding: 40px;
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }
    h1 {
      color: #2e8b57;
      margin-bottom: 30px;
    }
    .buttons {
      display: flex;
      gap: 20px;
      flex-wrap: wrap;
      justify-content: center;
    }
    .btn {
      padding: 15px 30px;
      font-size: 16px;
      background-color: #2e8b57;
      color: #fff;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      transition: all 0.3s;
      min-width: 120px;
    }
    .btn:hover {
      background-color: #3cb371;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(46, 139, 87, 0.3);
    }
    .btn-user {
      background-color: #0099cc;
    }
    .btn-user:hover {
      background-color: #0077aa;
    }
    .btn-coach {
      background-color: rgba(246, 12, 12, 0.93);
    }
    .btn-coach:hover {
      background-color: #ff5252;
    }
    .btn-academy {
      background-color: #2e8b57;
    }
    .btn-academy:hover {
      background-color: #3cb371;
    }
    .btn-admin {
      background-color: #6c757d;
    }
    .btn-admin:hover {
      background-color: #5a6268;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Welcome to Ready4Goal</h1>
    <p style="color: #666; margin-bottom: 30px;">Select your role to continue</p>
    <div class="buttons">
      <a href="player_login.php" class="btn btn-user">User</a>
      <a href="coach_login.php" class="btn btn-coach">Coach</a>
      <a href="index.php" class="btn btn-academy">Academy</a>
      <a href="admin_login.php" class="btn btn-admin">Admin</a>
    </div>
  </div>
</body>
</html>