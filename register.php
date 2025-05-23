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
$success = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if username already exists
        $check_username = mysqli_query($conn, "SELECT user_id FROM users WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            $error = 'Username already taken.';
        } else {
            // Check if email already exists
            $check_email = mysqli_query($conn, "SELECT user_id FROM users WHERE email = '$email'");
            if (mysqli_num_rows($check_email) > 0) {
                $error = 'Email already registered.';
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user
                $query = "INSERT INTO users (username, email, password, created_at) 
                          VALUES ('$username', '$email', '$hashed_password', NOW())";
                
                if (mysqli_query($conn, $query)) {
                    $success = 'Registration successful! You can now log in.';
                    // Optionally, auto-login the user
                    // $_SESSION['user_id'] = mysqli_insert_id($conn);
                    // $_SESSION['username'] = $username;
                    // header('Location: home.php');
                    // exit();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MusicStream</title>
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-container {
            width: 100%;
            max-width: 450px;
            padding: 40px;
            background-color: #181818;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .register-header h1 {
            color: #1DB954;
            font-size: 28px;
            margin-bottom: 10px;
        }
        .register-header p {
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
        .success-message {
            background-color: rgba(29, 185, 84, 0.1);
            color: #1DB954;
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
        .register-footer {
            margin-top: 30px;
            text-align: center;
            color: #b3b3b3;
            font-size: 14px;
        }
        .register-footer a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 600;
        }
        .register-footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>MusicStream</h1>
            <p>Sign up for free music streaming</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="button">SIGN UP</button>
        </form>
        
        <div class="register-footer">
            <p>Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</body>
</html>