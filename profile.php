<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Fetch current user data
$user_query = "SELECT username, email, profile_picture FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($conn, $user_query);

if ($user_result && mysqli_num_rows($user_result) > 0) {
    $user = mysqli_fetch_assoc($user_result);
} else {
    header('Location: logout.php');
    exit();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Username
    if (isset($_POST['update_username'])) {
        $new_username = mysqli_real_escape_string($conn, $_POST['username']);
        
        if (empty($new_username)) {
            $error = "Username cannot be empty.";
        } else {
            // Check if username already exists for another user
            $check_query = "SELECT user_id FROM users WHERE username = '$new_username' AND user_id != $user_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Username already taken.";
            } else {
                $update_query = "UPDATE users SET username = '$new_username' WHERE user_id = $user_id";
                if (mysqli_query($conn, $update_query)) {
                    $_SESSION['username'] = $new_username;
                    $message = "Username updated successfully!";
                    $user['username'] = $new_username;
                } else {
                    $error = "Failed to update username.";
                }
            }
        }
    }
    
    // Update Email
    if (isset($_POST['update_email'])) {
        $new_email = mysqli_real_escape_string($conn, $_POST['email']);
        
        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email already exists for another user
            $check_query = "SELECT user_id FROM users WHERE email = '$new_email' AND user_id != $user_id";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                $error = "Email already in use.";
            } else {
                $update_query = "UPDATE users SET email = '$new_email' WHERE user_id = $user_id";
                if (mysqli_query($conn, $update_query)) {
                    $message = "Email updated successfully!";
                    $user['email'] = $new_email;
                } else {
                    $error = "Failed to update email.";
                }
            }
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password hash
        $pass_query = "SELECT password FROM users WHERE user_id = $user_id";
        $pass_result = mysqli_query($conn, $pass_query);
        $pass_data = mysqli_fetch_assoc($pass_result);
        
        if (!password_verify($current_password, $pass_data['password'])) {
            $error = "Current password is incorrect.";
        } elseif (strlen($new_password) < 6) {
            $error = "New password must be at least 6 characters.";
        } elseif ($new_password !== $confirm_password) {
            $error = "New passwords do not match.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_query = "UPDATE users SET password = '$hashed_password' WHERE user_id = $user_id";
            
            if (mysqli_query($conn, $update_query)) {
                $message = "Password updated successfully!";
            } else {
                $error = "Failed to update password.";
            }
        }
    }
    
    // Update Profile Picture
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif');
        if (!in_array($_FILES['profile_picture']['type'], $allowed_types)) {
            $error = "Only JPG, PNG, and GIF files are allowed.";
        } else {
            $upload_dir = "uploads/profiles/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $filename = uniqid() . "_" . basename($_FILES['profile_picture']['name']);
            $target_file = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $update_query = "UPDATE users SET profile_picture = '$target_file' WHERE user_id = $user_id";
                
                if (mysqli_query($conn, $update_query)) {
                    $message = "Profile picture updated successfully!";
                    $user['profile_picture'] = $target_file;
                } else {
                    $error = "Failed to update profile picture in database.";
                }
            } else {
                $error = "Failed to upload profile picture.";
            }
        }
    }
}

// Get default profile picture if none exists
$profile_picture = !empty($user['profile_picture']) ? $user['profile_picture'] : 'uploads/profiles/default_profile.jpg';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
            }
        }
    </style>
</head>

<body class="font-sans bg-gray-900 text-white m-0 p-0 has-player">
    <!-- Mobile menu button (only visible on small screens) -->
    <div class="md:hidden fixed top-4 left-4 z-50">
        <button id="mobile-menu-button" class="text-white text-2xl focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- Mobile menu overlay -->
    <div id="mobile-overlay" class="mobile-menu-overlay"></div>

    <!-- Switch to a proper grid layout -->
    <div class="content-grid">
        <!-- Sidebar Navigation -->
        <div id="sidebar" class="sidebar-mobile md:static bg-black sidebar-width py-6 overflow-y-auto">
            <div class="px-6 mb-6">
                <h1 class="text-green-500 text-2xl">MusicStream</h1>
            </div>

            <ul class="list-none">
                <li class="py-2 px-6">
                    <a href="home.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-home mr-4 text-xl"></i> Home
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="search.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-search mr-4 text-xl"></i> Search
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="library.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-book mr-4 text-xl"></i> Your Library
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="addPlaylist.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-plus-square mr-4 text-xl"></i> Create Playlist
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="liked_songs.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-heart mr-4 text-xl"></i> Liked Songs
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="upload_song.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-upload mr-4 text-xl"></i> Upload Song
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="add_artist.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-user-plus mr-4 text-xl"></i> Add Artist
                    </a>
                </li>
                <li class="mt-5 pt-5 border-t border-gray-700 py-2 px-6">
                    <a href="profile.php" class="text-white flex items-center font-semibold no-underline">
                        <div class="w-8 h-8 rounded-full overflow-hidden mr-4 flex-shrink-0">
                            <img src="<?= $profile_picture ?>" alt="Profile" class="w-full h-full object-cover">
                        </div>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="logout.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-sign-out-alt mr-4 text-xl"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <div class="mb-8">
                <h2 class="text-3xl font-bold mb-2">Your Profile</h2>
                <p class="text-gray-400">Manage your account information</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="p-4 mb-6 bg-green-500 bg-opacity-10 text-green-500 rounded">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="p-4 mb-6 bg-red-500 bg-opacity-10 text-red-400 rounded">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <!-- Profile Picture Section -->
            <div class="mb-10 bg-black bg-opacity-30 p-6 rounded-lg">
                <h3 class="text-xl mb-4">Profile Picture</h3>
                
                <div class="flex flex-col md:flex-row items-center">
                    <div class="w-32 h-32 rounded-full overflow-hidden mb-6 md:mb-0 md:mr-8">
                        <img src="<?= $profile_picture ?>" alt="Profile" class="w-full h-full object-cover" id="profile-preview">
                    </div>
                    
                    <form method="POST" enctype="multipart/form-data" class="flex-1">
                        <div class="mb-4">
                            <div class="relative">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                                <div class="flex items-center">
                                    <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                        Choose File
                                    </button>
                                    <div id="file-name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                        No file selected
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Recommended: Square image, at least 200x200 pixels</p>
                            </div>
                        </div>
                        <button type="submit" class="py-2 px-6 bg-green-500 hover:bg-green-400 text-white font-medium rounded-full transition-colors">
                            Update Picture
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Account Information Sections -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Username Section -->
                <div class="bg-black bg-opacity-30 p-6 rounded-lg">
                    <h3 class="text-xl mb-4">Change Username</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="username" class="block mb-2 text-gray-400">Username</label>
                            <input type="text" id="username" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                                class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600">
                        </div>
                        <button type="submit" name="update_username" value="1" 
                            class="py-2 px-6 bg-green-500 hover:bg-green-400 text-white font-medium rounded-full transition-colors">
                            Update Username
                        </button>
                    </form>
                </div>
                
                <!-- Email Section -->
                <div class="bg-black bg-opacity-30 p-6 rounded-lg">
                    <h3 class="text-xl mb-4">Change Email</h3>
                    <form method="POST">
                        <div class="mb-4">
                            <label for="email" class="block mb-2 text-gray-400">Email Address</label>
                            <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                                class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600">
                        </div>
                        <button type="submit" name="update_email" value="1" 
                            class="py-2 px-6 bg-green-500 hover:bg-green-400 text-white font-medium rounded-full transition-colors">
                            Update Email
                        </button>
                    </form>
                </div>
                
                <!-- Password Section -->
                <div class="bg-black bg-opacity-30 p-6 rounded-lg md:col-span-2">
                    <h3 class="text-xl mb-4">Change Password</h3>
                    <form method="POST">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="mb-4">
                                <label for="current_password" class="block mb-2 text-gray-400">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                    class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600">
                            </div>
                            <div class="mb-4">
                                <label for="new_password" class="block mb-2 text-gray-400">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                    class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600">
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="block mb-2 text-gray-400">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                    class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600">
                            </div>
                        </div>
                        <button type="submit" name="update_password" value="1" 
                            class="py-2 px-6 bg-green-500 hover:bg-green-400 text-white font-medium rounded-full transition-colors">
                            Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Player Bar -->
        <div class="md:col-span-2 bg-gray-800 border-t border-gray-700 p-4 flex flex-col md:flex-row items-center space-y-4 md:space-y-0">
            <!-- Left section - Song info -->
            <div class="flex items-center w-full md:w-1/4">
                <div class="w-12 h-12 md:w-14 md:h-14 mr-3 md:mr-4 flex-shrink-0">
                    <img id="current-cover" src="uploads/covers/default_cover.jpg" alt="Now playing" class="w-full h-full object-cover">
                </div>
                <div class="flex flex-col truncate">
                    <div id="current-title" class="text-white text-xs md:text-sm font-medium mb-1 truncate">No song selected</div>
                    <div id="current-artist" class="text-gray-400 text-xs truncate">-</div>
                </div>
            </div>

            <!-- Center section - Player controls -->
            <div class="flex flex-col items-center w-full md:w-1/2">
                <!-- Control buttons -->
                <div class="flex items-center justify-center w-full mb-2">
                    <button class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
                        <i class="fas fa-random"></i>
                    </button>
                    <button id="prev-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-lg mx-4">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    <button id="play-pause" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-white text-black flex items-center justify-center cursor-pointer mx-5">
                        <i class="fas fa-play"></i>
                    </button>
                    <button id="next-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-lg mx-4">
                        <i class="fas fa-step-forward"></i>
                    </button>
                    <button class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
                        <i class="fas fa-repeat"></i>
                    </button>
                </div>

                <!-- Progress bar -->
                <div class="w-full flex items-center px-4 md:px-8 lg:px-12">
                    <div id="current-time" class="text-xs text-gray-400 min-w-[40px] text-center">0:00</div>
                    <div id="progress-bar" class="progress-bar flex-1 h-2 bg-gray-600 rounded mx-3 cursor-pointer relative">
                        <div class="progress h-full w-0 bg-gray-400 rounded relative" id="progress">
                            <div class="progress-handle"></div>
                        </div>
                    </div>
                    <div id="total-time" class="text-xs text-gray-400 min-w-[40px] text-center">0:00</div>
                </div>
            </div>

            <!-- Right section - Volume control -->
            <div class="items-center justify-end hidden md:flex md:w-1/4">
                <i class="fas fa-volume-up text-gray-400 mr-3 text-base"></i>
                <div id="volume-bar" class="volume-bar w-24 h-2 bg-gray-600 rounded cursor-pointer relative">
                    <div class="volume-level h-full w-1/2 bg-gray-400 rounded" id="volume-level"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Element -->
    <audio id="audio-player"></audio>

    <!-- Scripts -->
    <script src="player.js"></script>
    <script src="playerState.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // File input display
            const fileInput = document.getElementById('profile_picture');
            const fileNameDisplay = document.getElementById('file-name');
            const imagePreview = document.getElementById('profile-preview');
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                    
                    // Show preview of selected image
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                    };
                    reader.readAsDataURL(this.files[0]);
                } else {
                    fileNameDisplay.textContent = 'No file selected';
                }
            });
            
            // Mobile menu
            const menuButton = document.getElementById('mobile-menu-button');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');
            
            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            });
            
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            });
        });
    </script>
</body>
</html>