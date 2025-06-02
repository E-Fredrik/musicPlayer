<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Check if artist ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: home.php');
    exit();
}

$artist_id = (int)$_GET['id'];

// Fetch artist details
$artist_sql = "SELECT 
                artist_id,
                name,
                bio,
                image
              FROM artists 
              WHERE artist_id = $artist_id";

$artist_result = mysqli_query($conn, $artist_sql);

if (!$artist_result || mysqli_num_rows($artist_result) == 0) {
    header('Location: home.php');
    exit();
}

$artist = mysqli_fetch_assoc($artist_result);

// Fetch artist albums
$albums = [];
$albums_sql = "SELECT 
               al.album_id,
               al.title,
               al.cover_art,
               al.release_date
             FROM albums al
             JOIN album_artists aa ON al.album_id = aa.album_id
             WHERE aa.artist_id = $artist_id
             ORDER BY al.release_date DESC";

$albums_result = mysqli_query($conn, $albums_sql);
if ($albums_result && mysqli_num_rows($albums_result) > 0) {
    while ($row = mysqli_fetch_assoc($albums_result)) {
        $albums[] = $row;
    }
}

// Fetch artist songs
$songs = [];
$songs_sql = "SELECT 
              s.song_id, 
              s.title AS song_title, 
              s.file_path, 
              s.cover_art, 
              s.duration,
              GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') AS artist_names,
              al.title AS album_title,
              al.album_id
            FROM songs s
            JOIN song_artists sa ON s.song_id = sa.song_id
            JOIN artists a ON sa.artist_id = a.artist_id
            LEFT JOIN albums al ON s.album_id = al.album_id
            WHERE sa.artist_id = $artist_id
            GROUP BY s.song_id
            ORDER BY s.title";

$songs_result = mysqli_query($conn, $songs_sql);
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

// Count total songs
$total_songs = count($songs);

// Format time helper function
function formatTime($seconds)
{
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}

// Format date helper function
function formatDate($dateString)
{
    if (empty($dateString) || $dateString == "0000-00-00") {
        return "Unknown";
    }
    $date = new DateTime($dateString);
    return $date->format('Y');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($artist['name']) ?> - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
            }
        }
        
        .artist-header {
            background: linear-gradient(to bottom, rgba(48, 48, 48, 0.8), rgba(18, 18, 18, 1));
        }
        
        .section-divider {
            width: 100%;
            height: 1px;
            background-color: rgba(255, 255, 255, 0.1);
            margin: 2rem 0;
        }
        
        .album-card:hover {
            background-color: rgba(255, 255, 255, 0.1);
            transform: translateY(-4px);
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

        <!-- Main Content Area -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <!-- Artist Header -->
            <div class="artist-header rounded-lg p-6 mb-8 flex flex-col md:flex-row items-center md:items-end">
                <!-- Artist Image -->
                <div class="w-40 h-40 md:w-56 md:h-56 rounded-full overflow-hidden mb-4 md:mb-0 md:mr-6 shadow-lg border-2 border-gray-800">
                    <img 
                        src="<?= !empty($artist['image']) ? $artist['image'] : 'uploads/artists/default_artist.jpg' ?>" 
                        alt="<?= htmlspecialchars($artist['name']) ?>" 
                        class="w-full h-full object-cover"
                    >
                </div>
                
                <!-- Artist Info -->
                <div class="flex-1 text-center md:text-left">
                    <div class="text-xs font-bold uppercase mb-2 tracking-wide">Artist</div>
                    <h1 class="text-4xl md:text-6xl font-bold mb-4"><?= htmlspecialchars($artist['name']) ?></h1>
                    <div class="text-sm text-gray-400">
                        <span><?= count($albums) ?> albums</span>
                        <span class="mx-2">•</span>
                        <span><?= $total_songs ?> songs</span>
                    </div>
                </div>
            </div>
            
            <!-- Play Songs Button -->
            <?php if(count($songs) > 0): ?>
                <div class="mb-6">
                    <button id="play-all" class="w-14 h-14 bg-green-500 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-105 transition duration-200 ease-in-out">
                        <i class="fas fa-play text-xl"></i>
                    </button>
                </div>
            <?php endif; ?>
            
            <!-- Artist Bio Section -->
            <?php if (!empty($artist['bio'])): ?>
                <div class="mb-8">
                    <h2 class="text-2xl font-bold mb-4">About</h2>
                    <div class="text-gray-300 leading-relaxed bg-black bg-opacity-20 p-4 rounded-md">
                        <?= nl2br(htmlspecialchars($artist['bio'])) ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Albums Section -->
            <?php if(count($albums) > 0): ?>
                <div class="mb-10">
                    <h2 class="text-2xl font-bold mb-4">Albums</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <?php foreach($albums as $album): ?>
                            <a href="album.php?id=<?= $album['album_id'] ?>" class="album-card bg-black bg-opacity-30 p-3 rounded hover:shadow-lg transition-all duration-300 no-underline">
                                <div class="w-full aspect-square mb-3">
                                    <img 
                                        src="<?= !empty($album['cover_art']) ? $album['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                                        alt="<?= htmlspecialchars($album['title']) ?>" 
                                        class="w-full h-full object-cover shadow-md rounded"
                                    >
                                </div>
                                <div class="text-white font-medium truncate"><?= htmlspecialchars($album['title']) ?></div>
                                <div class="text-gray-400 text-sm"><?= formatDate($album['release_date']) ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="section-divider"></div>
            
            <!-- Songs Section -->
            <div class="mb-10">
                <h2 class="text-2xl font-bold mb-4">Popular Songs</h2>
                <?php if(count($songs) > 0): ?>
                    <div class="w-full overflow-x-auto">
                        <table class="w-full border-collapse">
                            <thead>
                                <tr>
                                    <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">#</th>
                                    <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Title</th>
                                    <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm hidden md:table-cell">Album</th>
                                    <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm"><i class="far fa-clock"></i></th>
                                    <?php if ($logged_in): ?>
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
                                                    <div class="text-gray-400 text-sm song-artist"><?= htmlspecialchars($song['artist_names']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-2 text-gray-400 hidden md:table-cell">
                                            <?php if (!empty($song['album_title'])): ?>
                                                <a href="album.php?id=<?= $song['album_id'] ?>" class="text-gray-400 no-underline hover:text-green-500 hover:underline">
                                                    <?= htmlspecialchars($song['album_title']) ?>
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-2 text-right text-gray-400 text-sm"><?= formatTime($song['duration']) ?></td>
                                        <?php if ($logged_in): ?>
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
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center py-8 text-gray-400">No songs available from this artist.</p>
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
            const playAllBtn = document.getElementById('play-all');
            if (playAllBtn) {
                playAllBtn.addEventListener('click', function() {
                    const firstSongRow = document.querySelector('.song-row');
                    if (firstSongRow) {
                        firstSongRow.click();
                    }
                });
            }
            
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
        });
    </script>
</body>
</html>