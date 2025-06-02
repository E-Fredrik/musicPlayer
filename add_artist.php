<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Profile picture logic
$profile_picture = 'uploads/profiles/default_profile.jpg'; // Default profile picture
if ($logged_in) {
    $profile_query = "SELECT profile_picture FROM users WHERE user_id = " . $_SESSION['user_id'];
    $profile_result = mysqli_query($conn, $profile_query);
    if ($profile_result && mysqli_num_rows($profile_result) > 0) {
        $profile_data = mysqli_fetch_assoc($profile_result);
        if (!empty($profile_data['profile_picture'])) {
            $profile_picture = $profile_data['profile_picture'];
        }
    }
}

// Redirect to login if not logged in
if (!$logged_in) {
    header('Location: login.php');
    exit();
}

$message = '';
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    
    // Validate required fields
    if (empty($name)) {
        $error = "Please provide the artist name.";
    } else {
        // Check if artist already exists
        $check = mysqli_query($conn, "SELECT artist_id FROM artists WHERE name = '$name'");
        if (mysqli_num_rows($check) > 0) {
            $error = "An artist with this name already exists.";
        } else {
            // Process artist image upload
            $imagePath = 'default_artist.jpg'; // Default image path
            
            if(isset($_FILES["artist_image"]) && $_FILES["artist_image"]["error"] == 0) {
                $targetDir = "uploads/artists/";
                
                // Create directory if it doesn't exist
                if (!file_exists($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }
                
                $imageFileName = uniqid() . "_" . basename($_FILES["artist_image"]["name"]);
                $imageTargetFile = $targetDir . $imageFileName;
                $imageFileType = strtolower(pathinfo($imageTargetFile, PATHINFO_EXTENSION));
                
                // Check if image file is an actual image
                $check = getimagesize($_FILES["artist_image"]["tmp_name"]);
                if($check !== false) {
                    // Upload image
                    if (move_uploaded_file($_FILES["artist_image"]["tmp_name"], $imageTargetFile)) {
                        $imagePath = $imageTargetFile;
                    } else {
                        $error = "Sorry, there was an error uploading your image.";
                    }
                } else {
                    $error = "File is not an image.";
                }
            }
            
            // If no errors, insert artist into database
            if (empty($error)) {
                $sql = "INSERT INTO artists (name, bio, image) VALUES ('$name', '$bio', '$imagePath')";
                
                if (mysqli_query($conn, $sql)) {
                    $message = "Artist added successfully!";
                } else {
                    $error = "Error: " . mysqli_error($conn);
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
    <title>Add Artist - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        /* Additional styles to fix the mobile overlap */
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important; /* Ensure enough space for the hamburger menu */
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
        <!-- Sidebar Navigation - same as home.php -->
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
                    <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-search mr-4 text-xl"></i> Search
                    </a>
                </li>

                <?php if ($logged_in): ?>
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
                        <a href="add_artist.php" class="text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user-plus mr-4 text-xl"></i> Add Artist
                        </a>
                    </li>
                    <li class="mt-5 pt-5 border-t border-gray-700 py-2 px-6">
                        <a href="profile.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
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
                <?php else: ?>
                    <li class="py-2 px-6">
                        <a href="login.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-sign-in-alt mr-4 text-xl"></i> Login
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="register.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user-plus mr-4 text-xl"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Main Content Area - Using Tailwind classes -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl mb-4">Add New Artist</h2>
            </div>

            <?php if (!empty($message)): ?>
                <div class="p-4 mb-5 bg-opacity-10 bg-green-500 text-green-500 rounded">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="p-4 mb-5 bg-opacity-10 bg-red-500 text-red-400 rounded">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-black bg-opacity-20 rounded-lg p-6 max-w-lg">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-5">
                        <label for="name" class="block mb-2 font-semibold text-gray-400">Artist Name *</label>
                        <input type="text" id="name" name="name" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600" required>
                    </div>
                    
                    <div class="mb-5">
                        <label for="bio" class="block mb-2 font-semibold text-gray-400">Biography</label>
                        <textarea id="bio" name="bio" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600 h-32 resize-y"></textarea>
                    </div>
                    
                    <div class="mb-5">
                        <label for="artist_image" class="block mb-2 font-semibold text-gray-400">Artist Image</label>
                        <div class="relative">
                            <input type="file" id="artist_image" name="artist_image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                            <div class="flex items-center">
                                <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                    Choose File
                                </button>
                                <div id="file-name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                    No file selected
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Recommended size: 300x300 pixels</p>
                    </div>
                    
                    <button type="submit" class="py-3 px-6 bg-green-500 text-white border-none rounded-full text-base font-bold cursor-pointer hover:bg-green-400 transition-colors">Add Artist</button>
                </form>
            </div>
        </div>

        <!-- Player Bar - Fixed for proper centering -->
        <div class="md:col-span-2 bg-gray-800 border-t border-gray-700 p-4 flex flex-col md:flex-row items-center space-y-4 md:space-y-0">
            <!-- Left section - Song info (smaller width) -->
            <div class="flex items-center w-full md:w-1/4">
                <div class="w-12 h-12 md:w-14 md:h-14 mr-3 md:mr-4 flex-shrink-0">
                    <img id="current-cover" src="uploads/covers/default_cover.jpg" alt="Now playing" class="w-full h-full object-cover">
                </div>
                <div class="flex flex-col truncate">
                    <div id="current-title" class="text-white text-xs md:text-sm font-medium mb-1 truncate">No song selected</div>
                    <div id="current-artist" class="text-gray-400 text-xs truncate">-</div>
                </div>
            </div>

            <!-- Center section - Player controls (fixed width and centered) -->
            <div class="flex flex-col items-center w-full md:w-1/2">
                <!-- Control buttons with increased spacing -->
                <div class="flex items-center justify-center w-full mb-2">
                    <button id="shuffle-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
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
                    <button id="loop-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
                        <i class="fas fa-repeat"></i>
                    </button>
                </div>

                <!-- Progress bar with wider margins -->
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

    <!-- Include the external player script -->
    <script src="player.js"></script>
    <script src="playerState.js"></script>
    
    <!-- Display file name when selected -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('artist_image');
            const fileNameDisplay = document.getElementById('file-name');
            
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    fileNameDisplay.textContent = this.files[0].name;
                } else {
                    fileNameDisplay.textContent = 'No file selected';
                }
            });
            
            // Mobile menu script
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