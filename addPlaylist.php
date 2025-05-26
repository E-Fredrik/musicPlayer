<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Redirect to login if not logged in
if (!$logged_in) {
    header('Location: login.php');
    exit();
}

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

$message = '';
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $playlist_name = mysqli_real_escape_string($conn, $_POST['playlist_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $user_id = $_SESSION['user_id'];
    $is_public = isset($_POST['is_public']) ? 1 : 0; // Check if playlist should be public
    
    // Validate playlist name
    if (empty($playlist_name)) {
        $error = "Please enter a playlist name.";
    } else {
        // Process cover image upload if provided
        $coverPath = 'uploads/playlists/default_playlist.jpg'; // Default playlist cover
        
        if (isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] == 0) {
            $targetDir = "uploads/playlists/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                mkdir($targetDir, 0777, true);
            }
            
            $imageFileName = uniqid() . "_" . basename($_FILES["cover_image"]["name"]);
            $imageTargetFile = $targetDir . $imageFileName;
            $imageFileType = strtolower(pathinfo($imageTargetFile, PATHINFO_EXTENSION));
            
            // Check if image file is an actual image
            $check = getimagesize($_FILES["cover_image"]["tmp_name"]);
            if ($check !== false) {
                // Upload image
                if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $imageTargetFile)) {
                    $coverPath = $imageTargetFile;
                } else {
                    $error = "Sorry, there was an error uploading your image.";
                }
            } else {
                $error = "File is not an image.";
            }
        }
        
        // If no errors, insert playlist into database
        if (empty($error)) {
            // Create the playlist
            $sql = "INSERT INTO playlists (name, description, cover_image, user_id, created_at, is_public) 
                    VALUES ('$playlist_name', '$description', '$coverPath', $user_id, NOW(), $is_public)";
            
            if (mysqli_query($conn, $sql)) {
                $playlist_id = mysqli_insert_id($conn);
                
                // Add songs to the playlist if any were selected
                if (isset($_POST['songs']) && is_array($_POST['songs']) && !empty($_POST['songs'])) {
                    $position = 1;
                    
                    foreach ($_POST['songs'] as $song_id) {
                        $song_id = (int)$song_id; // Ensure integer
                        
                        $insert_song_sql = "INSERT INTO playlist_songs (playlist_id, song_id, position) 
                                          VALUES ($playlist_id, $song_id, $position)";
                        
                        if (mysqli_query($conn, $insert_song_sql)) {
                            $position++;
                        }
                    }
                    
                    $message = "Playlist created successfully with " . ($position - 1) . " songs!";
                } else {
                    $message = "Playlist created successfully! You can add songs later.";
                }
            } else {
                $error = "Error creating playlist: " . mysqli_error($conn);
            }
        }
    }
}

// Get all available songs for selection
$songs = [];
$songs_query = "SELECT s.song_id, s.title, s.cover_art, a.name AS artist_name 
               FROM songs s 
               LEFT JOIN song_artists sa ON s.song_id = sa.song_id 
               LEFT JOIN artists a ON sa.artist_id = a.artist_id 
               WHERE sa.is_primary = 1 OR sa.is_primary IS NULL
               ORDER BY s.title";
$songs_result = mysqli_query($conn, $songs_query);

if ($songs_result) {
    while ($row = mysqli_fetch_assoc($songs_result)) {
        $songs[] = $row;
    }
}

function formatTime($seconds)
{
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Playlist - MusicStream</title>
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
        /* Custom checkbox styling */
        .song-checkbox:checked + div {
            background-color: rgba(29, 185, 84, 0.1);
            border-color: #1db954;
        }
        /* Playlist cover preview */
        .playlist-cover-preview {
            width: 180px;
            height: 180px;
            background-color: #333;
            margin-bottom: 20px;
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .playlist-cover-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .playlist-cover-preview .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            opacity: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: opacity 0.2s ease;
        }
        .playlist-cover-preview:hover .overlay {
            opacity: 1;
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
                    <a href="addPlaylist.php" class="text-white flex items-center font-semibold no-underline">
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
            </ul>
        </div>

        <!-- Main Content Area -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl mb-4">Create New Playlist</h2>
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
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Playlist Details Form -->
                <div class="col-span-1 bg-black bg-opacity-20 rounded-lg p-6">
                    <form method="POST" id="playlist-form" enctype="multipart/form-data">
                        <!-- Cover Image Preview Area -->
                        <div class="mb-5">
                            <label class="block mb-2 font-semibold text-gray-400">Playlist Cover</label>
                            <div class="playlist-cover-preview mb-3">
                                <img id="cover-preview" src="uploads/playlists/default_playlist.jpg" alt="Playlist cover">
                                <div class="overlay">
                                    <span class="text-white"><i class="fas fa-camera"></i> Upload Image</span>
                                </div>
                            </div>
                            <input type="file" name="cover_image" id="cover_image" accept="image/*" class="hidden">
                            <button type="button" id="upload-cover-button" class="w-full py-2 px-4 bg-green-500 text-white rounded-md hover:bg-green-400 transition-colors">
                                Change Cover Image
                            </button>
                        </div>
                        
                        <div class="mb-5">
                            <label for="playlist_name" class="block mb-2 font-semibold text-gray-400">Playlist Name *</label>
                            <input type="text" id="playlist_name" name="playlist_name" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600" required>
                        </div>
                        
                        <div class="mb-5">
                            <label for="description" class="block mb-2 font-semibold text-gray-400">Description</label>
                            <textarea id="description" name="description" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600 h-24 resize-y"></textarea>
                        </div>
                        
                        <div class="mb-5">
                            <div class="flex items-center">
                                <input type="checkbox" id="is_public" name="is_public" class="mr-2">
                                <label for="is_public" class="text-gray-300">Make this playlist public</label>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">Public playlists can be viewed and searched by other users</p>
                        </div>
                        
                        <div class="mb-5">
                            <div class="flex items-center">
                                <input type="checkbox" id="select-all-songs" class="mr-2">
                                <label for="select-all-songs" class="text-gray-300 text-sm">Select All Songs</label>
                            </div>
                        </div>
                        
                        <button type="submit" class="py-3 px-6 bg-green-500 text-white border-none rounded-full text-base font-bold cursor-pointer hover:bg-green-400 transition-colors">
                            Create Playlist
                        </button>
                    </form>
                </div>
                
                <!-- Song Selection -->
                <div class="col-span-1 md:col-span-2 bg-black bg-opacity-20 rounded-lg p-6 max-h-[800px] overflow-y-auto">
                    <h3 class="text-xl mb-4">Select Songs</h3>
                    
                    <?php if (count($songs) > 0): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <?php foreach ($songs as $song): ?>
                                <label class="cursor-pointer">
                                    <input type="checkbox" name="songs[]" value="<?= $song['song_id'] ?>" class="song-checkbox hidden" form="playlist-form">
                                    <div class="flex items-center p-3 border border-gray-700 rounded-md hover:bg-gray-800 transition-colors">
                                        <div class="w-10 h-10 mr-3 flex-shrink-0">
                                            <img src="<?= !empty($song['cover_art']) ? $song['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                                                alt="Cover" class="w-full h-full object-cover rounded">
                                        </div>
                                        <div class="flex-1 overflow-hidden">
                                            <div class="text-white text-sm truncate"><?= htmlspecialchars($song['title']) ?></div>
                                            <div class="text-xs text-gray-400 truncate"><?= htmlspecialchars($song['artist_name']) ?></div>
                                        </div>
                                        <div class="text-gray-400 ml-2 flex items-center justify-center">
                                            <i class="fas fa-check text-transparent song-check-icon"></i>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-gray-400 py-10">No songs available. Upload some music first!</p>
                    <?php endif; ?>
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

    <!-- Include the player scripts -->
    <script src="player.js"></script>
    <script src="playerState.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select all songs functionality
            const selectAllCheckbox = document.getElementById('select-all-songs');
            const songCheckboxes = document.querySelectorAll('.song-checkbox');
            
            selectAllCheckbox.addEventListener('change', function() {
                songCheckboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                    updateCheckboxStyle(checkbox);
                });
            });
            
            // Individual song selection
            songCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateCheckboxStyle(this);
                    
                    // Update "select all" checkbox state
                    let allChecked = true;
                    songCheckboxes.forEach(cb => {
                        if (!cb.checked) allChecked = false;
                    });
                    selectAllCheckbox.checked = allChecked;
                });
                
                // Initial styling
                updateCheckboxStyle(checkbox);
            });
            
            function updateCheckboxStyle(checkbox) {
                const checkIcon = checkbox.parentNode.querySelector('.song-check-icon');
                if (checkbox.checked) {
                    checkIcon.classList.remove('text-transparent');
                    checkIcon.classList.add('text-green-500');
                } else {
                    checkIcon.classList.add('text-transparent');
                    checkIcon.classList.remove('text-green-500');
                }
            }
            
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
            
            // Cover image upload preview
            const coverImageInput = document.getElementById('cover_image');
            const coverPreview = document.getElementById('cover-preview');
            const uploadCoverButton = document.getElementById('upload-cover-button');
            
            uploadCoverButton.addEventListener('click', function() {
                coverImageInput.click();
            });
            
            coverImageInput.addEventListener('change', function() {
                const file = this.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        coverPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(file);
                }
            });
        });
    </script>
</body>
</html>