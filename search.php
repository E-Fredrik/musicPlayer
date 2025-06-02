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

$songs = [];
$artists = [];
$albums = [];
$playlists = []; // Added playlists array
$search_query = '';

// Process search
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['query'])) {
    $search_query = mysqli_real_escape_string($conn, $_GET['query']);
    
    if (!empty($search_query)) {
        // Search songs
        $song_sql = "SELECT 
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
                WHERE (s.title LIKE '%$search_query%' OR a.name LIKE '%$search_query%' OR al.title LIKE '%$search_query%')
                AND (sa.is_primary = 1 OR sa.is_primary IS NULL)
                GROUP BY s.song_id
                ORDER BY CASE
                    WHEN s.title LIKE '$search_query%' THEN 1
                    WHEN s.title LIKE '%$search_query%' THEN 2
                    ELSE 3
                END
                LIMIT 20";

        $song_result = mysqli_query($conn, $song_sql);
        if ($song_result && mysqli_num_rows($song_result) > 0) {
            while ($row = mysqli_fetch_assoc($song_result)) {
                $songs[] = $row;
            }
        }
        
        // Search artists
        $artist_sql = "SELECT 
                     artist_id, 
                     name,
                     image
                     FROM artists 
                     WHERE name LIKE '%$search_query%'
                     ORDER BY CASE
                        WHEN name LIKE '$search_query%' THEN 1
                        ELSE 2
                     END
                     LIMIT 10";
        
        $artist_result = mysqli_query($conn, $artist_sql);
        if ($artist_result && mysqli_num_rows($artist_result) > 0) {
            while ($row = mysqli_fetch_assoc($artist_result)) {
                $artists[] = $row;
            }
        }
        
        // Search albums
        $album_sql = "SELECT 
                    al.album_id,
                    al.title,
                    al.cover_art,
                    GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS artists
                    FROM albums al
                    LEFT JOIN album_artists aa ON al.album_id = aa.album_id
                    LEFT JOIN artists a ON aa.artist_id = a.artist_id
                    WHERE al.title LIKE '%$search_query%'
                    GROUP BY al.album_id
                    ORDER BY CASE
                        WHEN al.title LIKE '$search_query%' THEN 1
                        ELSE 2
                    END
                    LIMIT 8";
        
        $album_result = mysqli_query($conn, $album_sql);
        if ($album_result && mysqli_num_rows($album_result) > 0) {
            while ($row = mysqli_fetch_assoc($album_result)) {
                $albums[] = $row;
            }
        }
        
        // Search playlists
        // Only show public playlists OR private playlists created by the current user
        $playlist_sql = "SELECT 
                    p.playlist_id,
                    p.name,
                    p.description,
                    p.cover_image,
                    p.created_at,
                    p.is_public,
                    u.username as creator_name,
                    COUNT(ps.song_id) as song_count
                    FROM playlists p
                    LEFT JOIN playlist_songs ps ON p.playlist_id = ps.playlist_id
                    JOIN users u ON p.user_id = u.user_id
                    WHERE p.name LIKE '%$search_query%' 
                    AND (p.is_public = 1";
        
        // Add condition to show private playlists only for their creator
        if ($logged_in) {
            $current_user_id = $_SESSION['user_id'];
            $playlist_sql .= " OR p.user_id = $current_user_id";
        }
        
        $playlist_sql .= ")
                    GROUP BY p.playlist_id
                    ORDER BY CASE
                        WHEN p.name LIKE '$search_query%' THEN 1
                        ELSE 2
                    END
                    LIMIT 8";
        
        $playlist_result = mysqli_query($conn, $playlist_sql);
        if ($playlist_result && mysqli_num_rows($playlist_result) > 0) {
            while ($row = mysqli_fetch_assoc($playlist_result)) {
                $playlists[] = $row;
            }
        }
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
    <title>Search - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
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
                    <a href="search.php" class="text-white flex items-center font-semibold no-underline">
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

        <!-- Main Content Area - Fixed top padding for mobile -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl mb-4">Search</h2>
            </div>

            <!-- Search Form -->
            <form action="search.php" method="GET" class="mb-8">
                <div class="relative">
                    <input 
                        type="text" 
                        name="query" 
                        placeholder="Search for songs, artists, albums or playlists" 
                        value="<?= htmlspecialchars($search_query) ?>"
                        class="w-full p-4 pl-12 bg-gray-800 border border-gray-700 rounded-full text-white focus:outline-none focus:border-green-500"
                        autocomplete="off"
                        autofocus
                    >
                    <i class="fas fa-search absolute left-4 top-5 text-gray-400"></i>
                    <?php if (!empty($search_query)): ?>
                        <button type="reset" class="absolute right-4 top-4 text-gray-400 hover:text-white" onclick="window.location='search.php'">
                            <i class="fas fa-times"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </form>

            <?php if (!empty($search_query)): ?>
                <!-- Playlists Section (NEW) -->
                <?php if (count($playlists) > 0): ?>
                    <section class="mb-10">
                        <h3 class="text-xl font-bold mb-4">Playlists</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($playlists as $playlist): ?>
                                <a href="playlist.php?id=<?= $playlist['playlist_id'] ?>" class="bg-gray-800 bg-opacity-40 p-3 rounded hover:bg-opacity-60 transition-all transform hover:scale-105 no-underline">
                                    <div class="w-full aspect-square mb-3 bg-gray-700 flex items-center justify-center overflow-hidden">
                                        <?php if (!empty($playlist['cover_image'])): ?>
                                            <img 
                                                src="<?= $playlist['cover_image'] ?>" 
                                                alt="<?= htmlspecialchars($playlist['name']) ?>" 
                                                class="w-full h-full object-cover shadow-md"
                                            >
                                        <?php else: ?>
                                            <i class="fas fa-music text-3xl text-gray-500"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-white font-medium truncate"><?= htmlspecialchars($playlist['name']) ?></div>
                                    <div class="text-gray-400 text-sm truncate">
                                        <?= $playlist['song_count'] ?> songs • By <?= htmlspecialchars($playlist['creator_name']) ?>
                                        <?php if (!$playlist['is_public']): ?>
                                            <span class="ml-1 text-gray-500"><i class="fas fa-lock text-xs"></i></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Albums Section -->
                <?php if (count($albums) > 0): ?>
                    <section class="mb-10">
                        <h3 class="text-xl font-bold mb-4">Albums</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                            <?php foreach ($albums as $album): ?>
                                <a href="album.php?id=<?= $album['album_id'] ?>" class="bg-gray-800 bg-opacity-40 p-3 rounded hover:bg-opacity-60 transition-all transform hover:scale-105 no-underline">
                                    <div class="w-full aspect-square mb-3">
                                        <img 
                                            src="<?= !empty($album['cover_art']) ? $album['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                                            alt="<?= htmlspecialchars($album['title']) ?>" 
                                            class="w-full h-full object-cover shadow-md"
                                        >
                                    </div>
                                    <div class="text-white font-medium truncate"><?= htmlspecialchars($album['title']) ?></div>
                                    <div class="text-gray-400 text-sm truncate"><?= htmlspecialchars($album['artists']) ?></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Artists Section -->
                <?php if (count($artists) > 0): ?>
                    <section class="mb-10">
                        <h3 class="text-xl font-bold mb-4">Artists</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            <?php foreach ($artists as $artist): ?>
                                <a href="artist.php?id=<?= $artist['artist_id'] ?>" class="bg-gray-800 bg-opacity-40 p-3 rounded hover:bg-opacity-60 transition-all transform hover:scale-105 no-underline">
                                    <div class="w-full aspect-square rounded-full overflow-hidden mb-3">
                                        <img 
                                            src="<?= !empty($artist['image']) ? $artist['image'] : 'uploads/artists/default_artist.jpg' ?>" 
                                            alt="<?= htmlspecialchars($artist['name']) ?>" 
                                            class="w-full h-full object-cover shadow-md"
                                        >
                                    </div>
                                    <div class="text-white font-medium truncate text-center"><?= htmlspecialchars($artist['name']) ?></div>
                                    <div class="text-gray-400 text-sm truncate text-center">Artist</div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Songs Section -->
                <?php if (count($songs) > 0): ?>
                    <section>
                        <h3 class="text-xl font-bold mb-4">Songs</h3>
                        <div class="w-full overflow-x-auto">
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
                                            data-album-cover="<?= !empty($song['cover_art']) ? htmlspecialchars($song['cover_art']) : 'uploads/covers/default_cover.jpg' ?>">
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
                        </div>
                    </section>
                <?php elseif (!empty($search_query) && count($albums) == 0 && count($artists) == 0 && count($playlists) == 0): ?>
                    <div class="text-center py-10">
                        <i class="fas fa-search text-5xl text-gray-600 mb-4"></i>
                        <p class="text-lg text-gray-400">No results found for "<?= htmlspecialchars($search_query) ?>"</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Initial search page state -->
                <div class="text-center py-16">
                    <i class="fas fa-search text-6xl text-gray-600 mb-6"></i>
                    <p class="text-xl text-gray-400">Search for your favorite songs, artists, albums and playlists</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Player Bar - Fixed for proper centering -->
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
    <script>
    // Override space key behavior specifically for the search input
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.querySelector('input[name="query"]');
        
        if (searchInput) {
            // Prevent event bubbling for space key when in search field
            searchInput.addEventListener('keydown', function(event) {
                // Check if spacebar was pressed
                if (event.key === ' ' || event.code === 'Space' || event.keyCode === 32) {
                    // Stop event from reaching the global handler
                    event.stopPropagation();
                }
            }, true);
            
            // Ensure focus works properly
            searchInput.focus();
        }
    });
    </script>
</body>
</html>