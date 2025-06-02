<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Check if album ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: home.php');
    exit();
}

$album_id = (int)$_GET['id'];

// Fetch album details
$album_sql = "SELECT 
                al.album_id,
                al.title,
                al.cover_art,
                al.release_date,
                GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS artists
            FROM albums al
            LEFT JOIN album_artists aa ON al.album_id = aa.album_id
            LEFT JOIN artists a ON aa.artist_id = a.artist_id
            WHERE al.album_id = $album_id
            GROUP BY al.album_id";

$album_result = mysqli_query($conn, $album_sql);

if (!$album_result || mysqli_num_rows($album_result) == 0) {
    header('Location: home.php');
    exit();
}

$album = mysqli_fetch_assoc($album_result);

// Fetch album songs
$songs_sql = "SELECT 
                s.song_id, 
                s.title AS song_title, 
                s.file_path, 
                s.cover_art, 
                s.duration,
                GROUP_CONCAT(a.name SEPARATOR ', ') AS artist_name
            FROM songs s
            LEFT JOIN song_artists sa ON s.song_id = sa.song_id
            LEFT JOIN artists a ON sa.artist_id = a.artist_id
            WHERE s.album_id = $album_id
            GROUP BY s.song_id
            ORDER BY s.song_id ASC";

$songs_result = mysqli_query($conn, $songs_sql);
$songs = [];

if ($songs_result && mysqli_num_rows($songs_result) > 0) {
    while ($row = mysqli_fetch_assoc($songs_result)) {
        $songs[] = $row;
    }
}

function formatTime($seconds) {
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}

// Format release date
$release_date = !empty($album['release_date']) ? date('F j, Y', strtotime($album['release_date'])) : 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($album['title']) ?> - MusicStream</title>
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
        <!-- Sidebar Navigation - unchanged -->
        <div id="sidebar" class="sidebar-mobile md:static bg-black sidebar-width py-6 overflow-y-auto">
            <!-- Sidebar content remains the same -->
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
                        <a href="add_artist.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user-plus mr-4 text-xl"></i> Add Artist
                        </a>
                    </li>
                    <li class="mt-5 pt-5 border-t border-gray-700 py-2 px-6">
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user mr-4 text-xl"></i>
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

        <!-- Main Content Area - Fixed top padding for mobile -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <!-- Album Header -->
            <div class="flex flex-col md:flex-row items-end mb-10">
                <!-- Album Cover -->
                <div class="w-40 h-40 md:w-56 md:h-56 flex-shrink-0 mb-4 md:mb-0 md:mr-6 shadow-lg">
                    <img 
                        src="<?= !empty($album['cover_art']) ? $album['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                        alt="<?= htmlspecialchars($album['title']) ?>" 
                        class="w-full h-full object-cover"
                    >
                </div>
                
                <!-- Album Info -->
                <div class="flex-1">
                    <div class="text-xs font-bold uppercase mb-1 tracking-wide">Album</div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-4"><?= htmlspecialchars($album['title']) ?></h1>
                    <div class="text-sm text-gray-400">
                        <span class="text-white font-medium"><?= htmlspecialchars($album['artists']) ?></span>
                        <span class="mx-1">•</span>
                        <span><?= $release_date ?></span>
                        <span class="mx-1">•</span>
                        <span><?= count($songs) ?> songs</span>
                    </div>
                </div>
            </div>
            
            <!-- Album Actions -->
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($songs as $index => $song): ?>
                                <tr class="song-row hover:bg-white hover:bg-opacity-10 border-b border-gray-700" data-song-id="<?= $song['song_id'] ?>" data-file="<?= htmlspecialchars($song['file_path']) ?>">
                                    <td class="py-3 px-2 w-12">
                                        <div class="flex items-center">
                                            <button class="play-button bg-transparent border-0 text-white cursor-pointer text-sm mr-3 w-4 flex items-center justify-center">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <span class="text-gray-400"><?= $index + 1 ?></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2">
                                        <div class="song-title-artist">
                                            <div class="text-white font-medium"><?= htmlspecialchars($song['song_title']) ?></div>
                                            <div class="text-gray-400 text-sm"><?= htmlspecialchars($song['artist_name']) ?></div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-2 text-right text-gray-400 text-sm"><?= formatTime($song['duration']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center py-8 text-gray-400">No songs available in this album.</p>
                <?php endif; ?>
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
                    <!-- Shuffle button -->
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
                    <!-- Loop button -->
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

    <!-- Include the player scripts -->
    <script src="player.js"></script>
    <script src="playerState.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add album cover data to all song rows
            const songRows = document.querySelectorAll('.song-row');
            const albumCover = '<?= !empty($album['cover_art']) ? $album['cover_art'] : 'uploads/covers/default_cover.jpg' ?>';
            
            songRows.forEach(row => {
                row.dataset.albumCover = albumCover;
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