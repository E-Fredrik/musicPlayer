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

// Handle playlist deletion
if (isset($_POST['delete_playlist']) && isset($_POST['playlist_id'])) {
    $playlist_id = (int)$_POST['playlist_id'];
    $user_id = $_SESSION['user_id'];
    
    // Verify ownership before deletion
    $verify_sql = "SELECT * FROM playlists WHERE playlist_id = $playlist_id AND user_id = $user_id";
    $verify_result = mysqli_query($conn, $verify_sql);
    
    if ($verify_result && mysqli_num_rows($verify_result) > 0) {
        // Delete playlist
        $delete_sql = "DELETE FROM playlists WHERE playlist_id = $playlist_id";
        if (mysqli_query($conn, $delete_sql)) {
            $success_message = "Playlist deleted successfully!";
        } else {
            $error_message = "Error deleting playlist: " . mysqli_error($conn);
        }
    } else {
        $error_message = "You don't have permission to delete this playlist.";
    }
}

// Get all playlists created by the user
$playlists = [];
$playlist_sql = "SELECT 
                p.playlist_id, 
                p.name, 
                p.description, 
                p.cover_image,
                p.is_public, 
                p.created_at, 
                COUNT(ps.song_id) as song_count
                FROM playlists p
                LEFT JOIN playlist_songs ps ON p.playlist_id = ps.playlist_id
                WHERE p.user_id = " . $_SESSION['user_id'] . "
                GROUP BY p.playlist_id
                ORDER BY p.created_at DESC";

$playlist_result = mysqli_query($conn, $playlist_sql);

if ($playlist_result && mysqli_num_rows($playlist_result) > 0) {
    while ($row = mysqli_fetch_assoc($playlist_result)) {
        $playlists[] = $row;
    }
}

// Get favorite or liked songs
$liked_songs_count = 0;
$liked_songs_sql = "SELECT COUNT(*) as count FROM liked_songs WHERE user_id = " . $_SESSION['user_id'];
$liked_songs_result = mysqli_query($conn, $liked_songs_sql);
if ($liked_songs_result && mysqli_num_rows($liked_songs_result) > 0) {
    $liked_songs_data = mysqli_fetch_assoc($liked_songs_result);
    $liked_songs_count = $liked_songs_data['count'];
}

function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M d, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Library - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        /* Additional styles to fix the mobile overlap */
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
            }
        }
        /* Playlist card hover effect */
        .playlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        /* Confirmation modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #282828;
            border-radius: 8px;
            width: 90%;
            max-width: 400px;
            padding: 24px;
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

    <!-- Content grid layout -->
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
                    <a href="library.php" class="text-white flex items-center font-semibold no-underline">
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
                <h2 class="text-2xl md:text-3xl mb-2">Your Library</h2>
                <p class="text-gray-400">Manage your music collections</p>
            </div>

            <?php if (isset($success_message)): ?>
                <div class="p-4 mb-5 bg-green-900 bg-opacity-40 text-green-400 rounded">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="p-4 mb-5 bg-red-900 bg-opacity-40 text-red-400 rounded">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <!-- Liked Songs Section -->
            <div class="mb-10">
                <h3 class="text-xl font-bold mb-4">Liked Songs</h3>
                <div class="bg-gradient-to-r from-indigo-900 to-blue-700 rounded-lg p-4 shadow-lg flex items-center">
                    <div class="w-16 h-16 md:w-20 md:h-20 flex-shrink-0 bg-gradient-to-br from-indigo-600 to-blue-400 rounded-md flex items-center justify-center shadow-lg">
                        <i class="fas fa-heart text-white text-2xl md:text-3xl"></i>
                    </div>
                    <div class="ml-4 md:ml-6 flex-1">
                        <h4 class="text-lg md:text-xl font-bold">Liked Songs</h4>
                        <p class="text-sm md:text-base"><?= $liked_songs_count ?> songs</p>
                    </div>
                    <div>
                        <button class="bg-white text-black rounded-full w-10 h-10 flex items-center justify-center hover:scale-105 transition-transform">
                            <i class="fas fa-play"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Your Playlists Section -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Your Playlists</h3>
                    <a href="addPlaylist.php" class="text-green-500 hover:text-green-400 flex items-center">
                        <i class="fas fa-plus mr-2"></i> Create Playlist
                    </a>
                </div>
                
                <?php if (count($playlists) > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php foreach ($playlists as $playlist): ?>
                            <div class="playlist-card bg-gray-800 rounded-lg overflow-hidden shadow-md transition-all duration-300">
                                <a href="playlist.php?id=<?= $playlist['playlist_id'] ?>" class="block">
                                    <div class="w-full aspect-square">
                                        <img src="<?= !empty($playlist['cover_image']) ? $playlist['cover_image'] : 'uploads/playlists/default_playlist.jpg' ?>" 
                                            alt="<?= htmlspecialchars($playlist['name']) ?>" 
                                            class="w-full h-full object-cover">
                                    </div>
                                </a>
                                <div class="p-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="text-lg font-semibold mb-1 truncate"><?= htmlspecialchars($playlist['name']) ?></h4>
                                            <p class="text-gray-400 text-xs mb-2"><?= $playlist['song_count'] ?> songs</p>
                                            
                                            <?php if (!empty($playlist['description'])): ?>
                                                <p class="text-gray-300 text-sm mb-2 line-clamp-2"><?= htmlspecialchars($playlist['description']) ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="flex items-center text-xs text-gray-500">
                                                <span class="<?= $playlist['is_public'] ? 'text-green-400' : 'text-gray-500' ?>">
                                                    <i class="<?= $playlist['is_public'] ? 'fas fa-globe' : 'fas fa-lock' ?> mr-1"></i>
                                                    <?= $playlist['is_public'] ? 'Public' : 'Private' ?>
                                                </span>
                                                <span class="mx-2">•</span>
                                                <span>Created <?= formatDate($playlist['created_at']) ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="dropdown relative">
                                            <button class="text-gray-400 hover:text-white p-1 rounded-full focus:outline-none dropdown-toggle">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-gray-800 rounded-md shadow-lg z-10">
                                                <a href="playlist.php?id=<?= $playlist['playlist_id'] ?>" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-700 rounded-t-md">
                                                    <i class="fas fa-play mr-2"></i> Play
                                                </a>
                                                <a href="playlist.php?id=<?= $playlist['playlist_id'] ?>" class="block px-4 py-2 text-sm text-gray-200 hover:bg-gray-700">
                                                    <i class="fas fa-pencil-alt mr-2"></i> Edit
                                                </a>
                                                <button 
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-gray-700 rounded-b-md delete-btn"
                                                    data-playlist-id="<?= $playlist['playlist_id'] ?>"
                                                    data-playlist-name="<?= htmlspecialchars($playlist['name']) ?>"
                                                >
                                                    <i class="fas fa-trash-alt mr-2"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <a href="playlist.php?id=<?= $playlist['playlist_id'] ?>" class="block">
                                    <div class="relative h-12 bg-gradient-to-r from-green-900 to-gray-700 flex items-center">
                                        <div class="absolute bottom-3 right-3">
                                            <button class="bg-green-500 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg hover:bg-green-400 transition-colors">
                                                <i class="fas fa-play"></i>
                                            </button>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12 bg-gray-800 bg-opacity-40 rounded-lg">
                        <i class="fas fa-music text-5xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl mb-2">No playlists yet</h3>
                        <p class="text-gray-400 mb-4">Create your first playlist to organize your music</p>
                        <a href="addPlaylist.php" class="py-2 px-6 bg-green-500 rounded-full text-white font-bold hover:bg-green-400 transition-colors">
                            Create Playlist
                        </a>
                    </div>
                <?php endif; ?>
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

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Delete Playlist</h3>
            <p class="mb-6">Are you sure you want to delete "<span id="playlist-name-display"></span>"? This action cannot be undone.</p>
            <div class="flex justify-end space-x-3">
                <button id="cancel-delete" class="px-4 py-2 bg-gray-600 hover:bg-gray-500 rounded-md transition-colors">Cancel</button>
                <form method="POST" id="delete-form">
                    <input type="hidden" name="playlist_id" id="playlist-id-input">
                    <input type="hidden" name="delete_playlist" value="1">
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 rounded-md transition-colors">Delete</button>
                </form>
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
            
            // Dropdown menus
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const menu = this.nextElementSibling;
                    menu.classList.toggle('hidden');
                    
                    // Close other open menus
                    document.querySelectorAll('.dropdown-menu').forEach(otherMenu => {
                        if (otherMenu !== menu && !otherMenu.classList.contains('hidden')) {
                            otherMenu.classList.add('hidden');
                        }
                    });
                });
            });
            
            // Close dropdowns when clicking elsewhere
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.add('hidden');
                });
            });
            
            // Delete confirmation
            const deleteModal = document.getElementById('delete-modal');
            const playlistIdInput = document.getElementById('playlist-id-input');
            const playlistNameDisplay = document.getElementById('playlist-name-display');
            const cancelDeleteBtn = document.getElementById('cancel-delete');
            const deleteButtons = document.querySelectorAll('.delete-btn');
            
            deleteButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const playlistId = this.dataset.playlistId;
                    const playlistName = this.dataset.playlistName;
                    
                    playlistIdInput.value = playlistId;
                    playlistNameDisplay.textContent = playlistName;
                    deleteModal.style.display = 'flex';
                });
            });
            
            cancelDeleteBtn.addEventListener('click', function() {
                deleteModal.style.display = 'none';
            });
            
            deleteModal.addEventListener('click', function(e) {
                if (e.target === deleteModal) {
                    deleteModal.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>