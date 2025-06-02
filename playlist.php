<?php
// filepath: c:\xampp\htdocs\WebProg\ALP\playlist.php
// Start session
session_start();

include 'sqlConnect.php';

// Ensure playlist covers directory exists
$playlistCoversDir = 'uploads/playlists/';
if (!file_exists($playlistCoversDir)) {
    mkdir($playlistCoversDir, 0777, true);
}

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Check if playlist ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: library.php');
    exit();
}

$playlist_id = (int)$_GET['id'];

// Fetch playlist details
$playlist_sql = "SELECT 
                p.playlist_id,
                p.name,
                p.description,
                p.is_public,
                p.created_at,
                p.cover_image,
                u.username as creator
              FROM playlists p
              JOIN users u ON p.user_id = u.user_id
              WHERE p.playlist_id = $playlist_id";

$playlist_result = mysqli_query($conn, $playlist_sql);

if (!$playlist_result || mysqli_num_rows($playlist_result) == 0) {
    header('Location: library.php');
    exit();
}

$playlist = mysqli_fetch_assoc($playlist_result);

// Check if the playlist is private and user is not owner
if (!$playlist['is_public'] && (!$logged_in || $playlist['creator'] !== $_SESSION['username'])) {
    header('Location: library.php');
    exit();
}

// Handle edit/update operations if submitted
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_playlist'])) {
    if (!$logged_in || $playlist['creator'] !== $_SESSION['username']) {
        $error_message = "You don't have permission to edit this playlist.";
    } else {
        $new_name = mysqli_real_escape_string($conn, $_POST['playlist_name']);
        $new_description = mysqli_real_escape_string($conn, $_POST['description']);
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        
        $coverPath = $playlist['cover_image']; // Keep existing cover by default

        // Process cover image upload if provided
        if (isset($_FILES["cover_image"]) && $_FILES["cover_image"]["error"] == 0) {
            $targetDir = "uploads/playlists/";
            
            // Create directory if it doesn't exist
            if (!file_exists($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) {
                    $error_message = "Failed to create upload directory. Check folder permissions.";
                }
            }
            
            // Only proceed if directory exists/was created
            if (empty($error_message)) {
                $imageFileName = uniqid() . "_" . basename($_FILES["cover_image"]["name"]);
                $imageTargetFile = $targetDir . $imageFileName;
                $imageFileType = strtolower(pathinfo($imageTargetFile, PATHINFO_EXTENSION));
                
                // Check if image file is an actual image
                $check = @getimagesize($_FILES["cover_image"]["tmp_name"]);
                if ($check !== false) {
                    // Upload image
                    if (move_uploaded_file($_FILES["cover_image"]["tmp_name"], $imageTargetFile)) {
                        $coverPath = $imageTargetFile;
                    } else {
                        $error_message = "Sorry, there was an error uploading your image. Error code: " . $_FILES["cover_image"]["error"] . 
                                         ". Make sure the uploads directory is writable.";
                    }
                } else {
                    $error_message = "File is not a valid image.";
                }
            }
        }
        
        // Update the SQL query to include cover_image
        if (empty($error_message)) {
            $update_sql = "UPDATE playlists SET 
                          name = '$new_name', 
                          description = '$new_description', 
                          cover_image = '$coverPath',
                          is_public = $is_public 
                          WHERE playlist_id = $playlist_id";
            
            if (mysqli_query($conn, $update_sql)) {
                // Refresh playlist data after update
                $playlist['name'] = $new_name;
                $playlist['description'] = $new_description;
                $playlist['is_public'] = $is_public;
                $playlist['cover_image'] = $coverPath;
                $success_message = "Playlist updated successfully!";
            } else {
                $error_message = "Error updating playlist: " . mysqli_error($conn);
            }
        }
    }
}

// Handle song removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_song'])) {
    if (!$logged_in || $playlist['creator'] !== $_SESSION['username']) {
        $error_message = "You don't have permission to modify this playlist.";
    } else {
        $song_id = (int)$_POST['song_id'];
        
        $remove_sql = "DELETE FROM playlist_songs WHERE playlist_id = $playlist_id AND song_id = $song_id";
        
        if (mysqli_query($conn, $remove_sql)) {
            $success_message = "Song removed from playlist.";
        } else {
            $error_message = "Error removing song: " . mysqli_error($conn);
        }
    }
}

// Fetch playlist songs
$songs_sql = "SELECT 
                s.song_id, 
                s.title AS song_title, 
                s.file_path, 
                s.cover_art, 
                s.duration,
                GROUP_CONCAT(a.name SEPARATOR ', ') AS artist_name,
                ps.position
            FROM playlist_songs ps
            JOIN songs s ON ps.song_id = s.song_id
            LEFT JOIN song_artists sa ON s.song_id = sa.song_id
            LEFT JOIN artists a ON sa.artist_id = a.artist_id
            WHERE ps.playlist_id = $playlist_id
            GROUP BY s.song_id
            ORDER BY ps.position ASC";

$songs_result = mysqli_query($conn, $songs_sql);
$songs = [];

if ($songs_result && mysqli_num_rows($songs_result) > 0) {
    while ($row = mysqli_fetch_assoc($songs_result)) {
        $songs[] = $row;
    }
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

// Format time helper
function formatTime($seconds) {
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}

// Format date helper
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('F j, Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($playlist['name']) ?> - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
            }
        }
        
        /* Modal styles */
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
            max-width: 500px;
            padding: 24px;
        }
    </style>
</head>

<body class="font-sans bg-gray-900 text-white m-0 p-0 has-player">
    <!-- Mobile menu button -->
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
                
                <?php if ($logged_in): ?>
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

        <!-- Main Content Area -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <?php if (!empty($success_message)): ?>
                <div class="p-4 mb-5 bg-green-900 bg-opacity-40 text-green-400 rounded">
                    <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="p-4 mb-5 bg-red-900 bg-opacity-40 text-red-400 rounded">
                    <?= $error_message ?>
                </div>
            <?php endif; ?>
            
            <!-- Playlist Header -->
            <div class="flex flex-col md:flex-row items-end mb-10">
                <!-- Playlist Icon -->
                <div class="w-40 h-40 md:w-56 md:h-56 flex-shrink-0 mb-4 md:mb-0 md:mr-6 shadow-lg overflow-hidden">
                    <?php if (!empty($playlist['cover_image']) && file_exists($playlist['cover_image'])): ?>
                        <img src="<?= htmlspecialchars($playlist['cover_image']) ?>" 
                            alt="<?= htmlspecialchars($playlist['name']) ?>" 
                            class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center">
                            <i class="fas fa-music text-5xl text-gray-600"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Playlist Info -->
                <div class="flex-1">
                    <div class="text-xs font-bold uppercase mb-1 tracking-wide">Playlist</div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-4"><?= htmlspecialchars($playlist['name']) ?></h1>
                    
                    <?php if (!empty($playlist['description'])): ?>
                        <p class="text-gray-300 mb-3"><?= htmlspecialchars($playlist['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="text-sm text-gray-400">
                        <span class="text-white font-medium">Created by: <?= htmlspecialchars($playlist['creator']) ?></span>
                        <span class="mx-1">•</span>
                        <span><?= formatDate($playlist['created_at']) ?></span>
                        <span class="mx-1">•</span>
                        <span><?= count($songs) ?> songs</span>
                        <span class="mx-1">•</span>
                        <span class="<?= $playlist['is_public'] ? 'text-green-400' : 'text-gray-500' ?>">
                            <i class="<?= $playlist['is_public'] ? 'fas fa-globe' : 'fas fa-lock' ?>"></i>
                            <?= $playlist['is_public'] ? 'Public' : 'Private' ?>
                        </span>
                    </div>
                </div>
                
                <?php if ($logged_in && $playlist['creator'] === $_SESSION['username']): ?>
                    <div class="mt-4 md:mt-0 md:ml-4">
                        <button id="edit-playlist-btn" class="bg-transparent hover:bg-gray-700 text-white font-semibold py-2 px-4 border border-gray-600 rounded transition-colors">
                            <i class="fas fa-edit mr-2"></i> Edit Playlist
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Playlist Actions -->
            <div class="mb-6">
                <button id="play-all" class="w-14 h-14 bg-green-500 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-105 transition duration-200 ease-in-out">
                    <i class="fas fa-play text-xl"></i>
                </button>
            </div>
            
            <!-- Songs List -->
            <div class="w-full">
                <?php if(count($songs) > 0): ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">#</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Title</th>
                                <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm"><i class="far fa-clock"></i></th>
                                <?php if ($logged_in && $playlist['creator'] === $_SESSION['username']): ?>
                                    <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($songs as $index => $song): ?>
                                <tr class="song-row hover:bg-white hover:bg-opacity-10 border-b border-gray-700" 
                                    data-song-id="<?= $song['song_id'] ?>" 
                                    data-file="<?= htmlspecialchars($song['file_path']) ?>"
                                    data-album-cover="<?= !empty($song['cover_art']) ? htmlspecialchars($song['cover_art']) : 'uploads/covers/default_cover.jpg' ?>">
                                    <td class="py-3 px-2 w-12">
                                        <div class="flex items-center">
                                            <button class="play-button bg-transparent border-0 text-white cursor-pointer text-sm mr-3 w-4 flex items-center justify-center">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <span class="text-gray-400"><?= $index + 1 ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 mr-4 flex-shrink-0">
                                                <img src="<?= !empty($song['cover_art']) ? $song['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                                                    alt="Cover" class="w-full h-full object-cover">
                                            </div>
                                            <div class="song-title-artist">
                                                <div class="text-white font-medium song-title"><?= htmlspecialchars($song['song_title']) ?></div>
                                                <div class="text-gray-400 text-sm song-artist"><?= htmlspecialchars($song['artist_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 text-right text-gray-400 text-sm"><?= formatTime($song['duration']) ?></td>
                                    <td class="py-3 px-2 text-right">
                                        <?php
                                        // Check if song is liked by current user
                                        $is_liked = false;
                                        if ($logged_in) {
                                            $like_check = mysqli_query($conn, "SELECT * FROM liked_songs WHERE user_id = " . $_SESSION['user_id'] . " AND song_id = " . $song['song_id']);
                                            $is_liked = ($like_check && mysqli_num_rows($like_check) > 0);
                                        }
                                        ?>
                                        <button class="like-button text-gray-400 hover:text-white bg-transparent border-0 cursor-pointer" 
                                                data-song-id="<?= $song['song_id'] ?>">
                                            <i class="<?= $is_liked ? 'fas' : 'far' ?> fa-heart <?= $is_liked ? 'text-green-500' : '' ?>"></i>
                                        </button>
                                    </td>
                                    <?php if ($logged_in && $playlist['creator'] === $_SESSION['username']): ?>
                                        <td class="py-3 px-2 text-right">
                                            <form method="post" class="inline-block" onsubmit="return confirm('Remove this song from the playlist?');">
                                                <input type="hidden" name="song_id" value="<?= $song['song_id'] ?>">
                                                <button type="submit" name="remove_song" class="text-red-400 hover:text-red-300 bg-transparent border-0">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </form>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-12 bg-gray-800 bg-opacity-20 rounded-lg">
                        <i class="fas fa-music text-5xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl mb-2">This playlist is empty</h3>
                        <p class="text-gray-400 mb-4">Add some songs to get started</p>
                        <?php if ($logged_in && $playlist['creator'] === $_SESSION['username']): ?>
                            <a href="search.php" class="py-2 px-6 bg-green-500 rounded-full text-white font-bold hover:bg-green-400 transition-colors">
                                Find Songs
                            </a>
                        <?php endif; ?>
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

    <!-- Edit Playlist Modal -->
    <?php if ($logged_in && $playlist['creator'] === $_SESSION['username']): ?>
        <div id="edit-modal" class="modal">
            <div class="modal-content">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-bold">Edit Playlist</h3>
                    <button id="close-modal" class="text-gray-400 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="edit-playlist-form">
                    <div class="mb-4">
                        <label for="playlist_name" class="block mb-2 font-semibold text-gray-400">Playlist Name *</label>
                        <input type="text" id="playlist_name" name="playlist_name" value="<?= htmlspecialchars($playlist['name']) ?>" 
                            class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600" required>
                    </div>
                    
                    <div class="mb-4">
                        <label for="description" class="block mb-2 font-semibold text-gray-400">Description</label>
                        <textarea id="description" name="description" 
                            class="w-full p-3 bg-gray-700 border-none rounded text-white focus:outline-none focus:bg-gray-600 h-24 resize-y"><?= htmlspecialchars($playlist['description']) ?></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-semibold text-gray-400">Playlist Cover</label>
                        <div class="playlist-cover-preview mb-3">
                            <img id="edit-cover-preview" src="<?= !empty($playlist['cover_image']) ? $playlist['cover_image'] : 'uploads/playlists/default_playlist.jpg' ?>" alt="Playlist cover">
                            <div class="overlay">
                                <span class="text-white"><i class="fas fa-camera text-xl"></i></span>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <input type="file" id="edit_cover_image" name="cover_image" accept="image/*" 
                                   class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                            <div class="flex items-center">
                                <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                    Change Cover Image
                                </button>
                                <div id="edit-file-name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                    Current image
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Recommended size: 300x300 pixels</p>
                    </div>
                    
                    <div class="mb-6">
                        <div class="flex items-center">
                            <input type="checkbox" id="is_public" name="is_public" class="mr-2" <?= $playlist['is_public'] ? 'checked' : '' ?>>
                            <label for="is_public" class="text-gray-300">Make this playlist public</label>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Public playlists can be viewed and searched by other users</p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-md transition-colors mr-2">
                            Cancel
                        </button>
                        <button type="submit" name="update_playlist" class="px-4 py-2 bg-green-600 hover:bg-green-500 rounded-md transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
            
            // Play all button
            const playAllButton = document.getElementById('play-all');
            if (playAllButton) {
                playAllButton.addEventListener('click', function() {
                    // Find the first song and play it
                    const firstSongRow = document.querySelector('.song-row');
                    if (firstSongRow) {
                        firstSongRow.click();
                    }
                });
            }
            
            // Edit playlist modal
            const editButton = document.getElementById('edit-playlist-btn');
            const editModal = document.getElementById('edit-modal');
            const closeModalBtn = document.getElementById('close-modal');
            const cancelEditBtn = document.getElementById('cancel-edit');
            
            if (editButton && editModal) {
                editButton.addEventListener('click', function() {
                    editModal.style.display = 'flex';
                });
                
                // Close modal when clicking the X button
                if (closeModalBtn) {
                    closeModalBtn.addEventListener('click', function() {
                        editModal.style.display = 'none';
                    });
                }
                
                // Close modal when clicking the Cancel button
                if (cancelEditBtn) {
                    cancelEditBtn.addEventListener('click', function() {
                        editModal.style.display = 'none';
                    });
                }
                
                // Close modal when clicking outside of it
                editModal.addEventListener('click', function(e) {
                    if (e.target === editModal) {
                        editModal.style.display = 'none';
                    }
                });
            }
            
            // Image preview functionality for edit form
            const editCoverInput = document.getElementById('edit_cover_image');
            const editCoverPreview = document.getElementById('edit-cover-preview');
            const editFileNameDisplay = document.getElementById('edit-file-name');

            if (editCoverInput) {
                editCoverInput.addEventListener('change', function() {
                    // Validate file size (5MB limit)
                    if (this.files[0] && this.files[0].size > 5 * 1024 * 1024) {
                        alert('File is too large. Please select an image under 5MB.');
                        this.value = ''; // Clear the input
                        editFileNameDisplay.textContent = 'Current image';
                        return;
                    }
                    
                    // Check file type
                    if (this.files[0] && !this.files[0].type.match('image.*')) {
                        alert('Please select a valid image file (JPEG, PNG, GIF, etc.)');
                        this.value = '';
                        editFileNameDisplay.textContent = 'Current image';
                        return;
                    }
                    
                    // If all is well, show the preview
                    if (this.files && this.files[0]) {
                        const reader = new FileReader();
                        
                        reader.onload = function(e) {
                            editCoverPreview.src = e.target.result;
                        };
                        
                        reader.readAsDataURL(this.files[0]);
                        editFileNameDisplay.textContent = this.files[0].name;
                    } else {
                        editFileNameDisplay.textContent = 'Current image';
                    }
                });
            }
        });
        
        // Like/Unlike functionality
        const likeButtons = document.querySelectorAll('.like-button');

        likeButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation(); // Prevent row click
                const songId = this.dataset.songId;
                const heartIcon = this.querySelector('i');
                const isLiked = heartIcon.classList.contains('fas');
                
                // Determine action based on current state
                const action = isLiked ? 'unlike' : 'like';
                
                // AJAX request to like/unlike the song
                fetch('like_song.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'song_id=' + songId + '&action=' + action
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update heart icon
                        if (action === 'like') {
                            heartIcon.classList.replace('far', 'fas');
                            heartIcon.classList.add('text-green-500');
                        } else {
                            heartIcon.classList.replace('fas', 'far');
                            heartIcon.classList.remove('text-green-500');
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });
    </script>
</body>
</html>