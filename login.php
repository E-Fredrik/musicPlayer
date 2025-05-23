<?php
// Start session
session_start();

// If already logged in, redirect to home
if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

// Include database connection
include 'sqlConnect.php';

$error = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        // Check if user exists
        $query = "SELECT user_id, username, password FROM users WHERE username = '$username' OR email = '$username'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            // Verify password (assuming password is hashed)
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                
                // Redirect to home page
                header('Location: home.php');
                exit();
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom right, #333333, #121212);
            color: #ffffff;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            background-color: #181818;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-header h1 {
            color: #1DB954;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #b3b3b3;
            font-size: 16px;
        }
        .error-message {
            background-color: rgba(255, 0, 0, 0.1);
            color: #ff4a4a;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: center;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
            font-size: 14px;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            background-color: #333333;
            border: none;
            border-radius: 4px;
            color: #ffffff;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            background-color: #404040;
        }
        .button {
            width: 100%;
            padding: 14px;
            background-color: #1DB954;
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #1ed760;
        }
        .login-footer {
            margin-top: 30px;
            text-align: center;
            color: #b3b3b3;
            font-size: 14px;
        }
        .login-footer a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: #b3b3b3;
        }
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #333333;
        }
        .divider span {
            padding: 0 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>MusicStream</h1>
            <p>Log in to continue</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="username">Email or username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="button">LOG IN</button>
        </form>
        
        <div class="divider"><span>OR</span></div>
        
        <div class="login-footer">
            <p>Don't have an account? <a href="register.php">Sign up for MusicStream</a></p>
        </div>
    </div>
</body>
</html>