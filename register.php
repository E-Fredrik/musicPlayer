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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="font-sans bg-gradient-to-br from-gray-700 to-gray-900 text-white min-h-screen flex items-center justify-center p-5">
    <div class="w-full max-w-md p-10 bg-gray-800 rounded-lg shadow-xl">
        <div class="text-center mb-8">
            <h1 class="text-green-500 text-3xl font-bold mb-2">MusicStream</h1>
            <p class="text-gray-400 text-base">Sign up for free music streaming</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-500 bg-opacity-10 text-red-500 p-3 rounded mb-5 text-sm text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="bg-green-500 bg-opacity-10 text-green-500 p-3 rounded mb-5 text-sm text-center">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="mb-5">
                <label for="username" class="block mb-2 text-gray-400 text-sm font-medium">Username</label>
                <input type="text" id="username" name="username" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <div class="mb-5">
                <label for="email" class="block mb-2 text-gray-400 text-sm font-medium">Email</label>
                <input type="email" id="email" name="email" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <div class="mb-5">
                <label for="password" class="block mb-2 text-gray-400 text-sm font-medium">Password</label>
                <input type="password" id="password" name="password" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <div class="mb-5">
                <label for="confirm_password" class="block mb-2 text-gray-400 text-sm font-medium">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <button type="submit" 
                    class="w-full py-3.5 px-4 bg-green-500 text-white border-0 rounded-full text-base font-bold cursor-pointer transition-colors hover:bg-green-400">
                SIGN UP
            </button>
        </form>
        
        <div class="mt-7 text-center text-gray-400 text-sm">
            <p>Already have an account? <a href="login.php" class="text-white no-underline font-semibold hover:underline">Log in</a></p>
        </div>
    </div>
</body>
</html>