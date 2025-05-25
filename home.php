<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Fetch songs with artist and album information
$sql = "SELECT 
            s.song_id, 
            s.title AS song_title, 
            s.file_path, 
            s.cover_art, 
            s.duration,
            a.name AS artist_name,
            al.title AS album_title,
            al.album_id
        FROM songs s
        LEFT JOIN song_artists sa ON s.song_id = sa.song_id
        LEFT JOIN artists a ON sa.artist_id = a.artist_id
        LEFT JOIN albums al ON s.album_id = al.album_id
        WHERE sa.is_primary = 1 OR sa.is_primary IS NULL
        ORDER BY s.song_id DESC
        LIMIT 20";

$result = mysqli_query($conn, $sql);
$songs = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
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
    <title>MusicStream - Your Music Streaming Platform</title>
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
                    <a href="home.php" class="text-white flex items-center font-semibold no-underline">
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
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-book mr-4 text-xl"></i> Your Library
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-plus-square mr-4 text-xl"></i> Create Playlist
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
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
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl mb-4">Your Music</h2>
            </div>

            <div class="w-full overflow-x-auto">
                <?php if (count($songs) > 0): ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">#</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Title</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm hidden md:table-cell">Album</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm"><i class="far fa-clock"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($songs as $index => $song): ?>
                                <tr class="song-row hover:bg-white hover:bg-opacity-10 border-b border-gray-700" 
                                    data-song-id="<?= $song['song_id'] ?>" 
                                    data-file="<?= htmlspecialchars($song['file_path']) ?>"
                                    data-album-cover="<?= !empty($song['cover_art']) ? htmlspecialchars($song['cover_art']) : 'uploads/covers/hollywood.jpg' ?>">
                                    <td class="py-2 md:py-3 px-1 md:px-2">
                                        <div class="flex items-center">
                                            <button class="play-button bg-transparent border-0 text-white cursor-pointer text-sm flex items-center justify-center w-4 mr-2 md:mr-4">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <span class="text-gray-400 mr-2 md:mr-4 w-4 text-right"><?= $index + 1 ?></span>
                                        </div>
                                    </td>
                                    <td class="py-2 md:py-3 px-1 md:px-2">
                                        <div class="flex items-center">
                                            <div class="w-8 h-8 md:w-10 md:h-10 mr-2 md:mr-4 flex-shrink-0">
                                                <img src="<?= !empty($song['cover_art']) ? $song['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" alt="Cover" class="w-full h-full object-cover">
                                            </div>
                                            <div class="flex flex-col">
                                                <div class="song-title text-white text-sm md:text-base truncate max-w-[150px] md:max-w-none"><?= htmlspecialchars($song['song_title']) ?></div>
                                                <div class="song-artist text-xs md:text-sm text-gray-400 truncate max-w-[150px] md:max-w-none"><?= htmlspecialchars($song['artist_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 md:py-3 px-1 md:px-2 text-gray-400 hidden md:table-cell">
                                        <?php if (!empty($song['album_title'])): ?>
                                            <a href="album.php?id=<?= $song['album_id'] ?>" class="text-gray-400 no-underline hover:text-green-500 hover:underline transition-colors duration-200">
                                                <?= htmlspecialchars($song['album_title']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 md:py-3 px-1 md:px-2 text-gray-400 text-xs md:text-sm"><?= formatTime($song['duration']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-center py-8">No songs available. Add some music first!</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Player Bar - Fixed for proper centering -->
        <div class="md:col-span-2 bg-gray-800 border-t border-gray-700 p-4 flex flex-col md:flex-row items-center space-y-4 md:space-y-0">
            <!-- Left section - Song info (smaller width) -->
            <div class="flex items-center w-full md:w-1/4">
                <div class="w-12 h-12 md:w-14 md:h-14 mr-3 md:mr-4 flex-shrink-0">
                    <img id="current-cover" src="uploads/covers/hollywood.jpg" alt="Now playing" class="w-full h-full object-cover">
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

    <!-- Include the external player script -->
    <script src="player.js"></script>
    
    <!-- Mobile menu script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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