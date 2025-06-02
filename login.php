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
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background-color: #333333;
        }
    </style>
</head>
<body class="font-sans bg-gradient-to-br from-gray-700 to-gray-900 text-white h-screen flex items-center justify-center">
    <div class="w-full max-w-md p-10 bg-gray-800 rounded-lg shadow-xl">
        <div class="text-center mb-8">
            <h1 class="text-green-500 text-3xl font-bold mb-2">MusicStream</h1>
            <p class="text-gray-400 text-base">Log in to continue</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="bg-red-500 bg-opacity-10 text-red-500 p-3 rounded mb-5 text-sm text-center">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted']) && $_GET['deleted'] == 1): ?>
            <div class="bg-green-500 bg-opacity-10 text-green-500 p-3 rounded mb-5 text-sm text-center">
                Your account has been permanently deleted.
            </div>
        <?php endif; ?>
        
        <form method="POST" action="login.php">
            <div class="mb-5">
                <label for="username" class="block mb-2 text-gray-400 text-sm font-medium">Email or username</label>
                <input type="text" id="username" name="username" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <div class="mb-5">
                <label for="password" class="block mb-2 text-gray-400 text-sm font-medium">Password</label>
                <input type="password" id="password" name="password" required 
                       class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <button type="submit" 
                    class="w-full py-3.5 px-4 bg-green-500 text-white border-0 rounded-full text-base font-bold cursor-pointer transition-colors hover:bg-green-400">
                LOG IN
            </button>
        </form>
        
        <div class="flex items-center my-7 text-gray-400 divider">
            <span class="px-3 text-sm">OR</span>
        </div>
        
        <div class="mt-7 text-center text-gray-400 text-sm">
            <p>Don't have an account? <a href="register.php" class="text-white no-underline font-semibold hover:underline">Sign up for MusicStream</a></p>
        </div>
    </div>
</body>
</html>