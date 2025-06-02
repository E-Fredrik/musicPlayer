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

// Get all liked songs by the user
$songs = [];
$songs_sql = "SELECT 
            s.song_id, 
            s.title AS song_title, 
            s.file_path, 
            s.cover_art, 
            s.duration,
            GROUP_CONCAT(a.name SEPARATOR ', ') AS artist_name,
            al.title AS album_title,
            al.album_id,
            ls.created_at AS liked_at
          FROM liked_songs ls
          JOIN songs s ON ls.song_id = s.song_id
          LEFT JOIN song_artists sa ON s.song_id = sa.song_id
          LEFT JOIN artists a ON sa.artist_id = a.artist_id
          LEFT JOIN albums al ON s.album_id = al.album_id
          WHERE ls.user_id = " . $_SESSION['user_id'] . "
          GROUP BY s.song_id
          ORDER BY ls.created_at DESC";

$songs_result = mysqli_query($conn, $songs_sql);
if ($songs_result && mysqli_num_rows($songs_result) > 0) {
    while ($row = mysqli_fetch_assoc($songs_result)) {
        $songs[] = $row;
    }
}

// Format time helper function
function formatTime($seconds) {
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}

// Format date helper function
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
    <title>Liked Songs - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
            }
        }
        .liked-song-hover:hover .unlike-btn {
            opacity: 1;
        }
        .unlike-btn {
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .heart-icon.active {
            color: #1db954;
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
                    <a href="liked_songs.php" class="text-white flex items-center font-semibold no-underline">
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
            <!-- Liked Songs Header -->
            <div class="flex flex-col md:flex-row items-end mb-10">
                <div class="w-40 h-40 md:w-56 md:h-56 flex-shrink-0 mb-4 md:mb-0 md:mr-6 shadow-lg bg-gradient-to-br from-indigo-600 to-blue-400 flex items-center justify-center">
                    <i class="fas fa-heart text-white text-5xl"></i>
                </div>
                
                <div class="flex-1">
                    <div class="text-xs font-bold uppercase mb-1 tracking-wide">Playlist</div>
                    <h1 class="text-3xl md:text-4xl font-bold mb-4">Liked Songs</h1>
                    <div class="text-sm text-gray-400">
                        <span class="text-white font-medium"><?= htmlspecialchars($_SESSION['username']) ?></span>
                        <span class="mx-1">•</span>
                        <span><?= count($songs) ?> songs</span>
                    </div>
                </div>
            </div>
            
            <!-- Liked Songs Actions -->
            <div class="mb-6">
                <?php if (count($songs) > 0): ?>
                    <button id="play-all" class="w-14 h-14 bg-green-500 rounded-full flex items-center justify-center text-white shadow-lg hover:scale-105 transition duration-200 ease-in-out">
                        <i class="fas fa-play text-xl"></i>
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Songs List -->
            <div class="w-full">
                <?php if(count($songs) > 0): ?>
                    <table class="w-full border-collapse">
                        <thead>
                            <tr>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">#</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Title</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm hidden md:table-cell">Album</th>
                                <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm hidden md:table-cell">Date Added</th>
                                <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm"><i class="far fa-clock"></i></th>
                                <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($songs as $index => $song): ?>
                                <tr class="song-row liked-song-hover hover:bg-white hover:bg-opacity-10 border-b border-gray-700"
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
                                                <div class="song-title text-white font-medium"><?= htmlspecialchars($song['song_title']) ?></div>
                                                <div class="song-artist text-gray-400 text-sm"><?= htmlspecialchars($song['artist_name']) ?></div>
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
                                    <td class="py-3 px-2 text-gray-400 text-sm hidden md:table-cell">
                                        <?= formatDate($song['liked_at']) ?>
                                    </td>
                                    <td class="py-3 px-2 text-right text-gray-400 text-sm"><?= formatTime($song['duration']) ?></td>
                                    <td class="py-3 px-2 text-right">
                                        <button class="unlike-btn text-green-400 hover:text-green-300 bg-transparent border-0 cursor-pointer" 
                                                data-song-id="<?= $song['song_id'] ?>">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                    </td>
                                    <!-- Add to Playlist dropdown button -->
                                    <td class="py-3 px-2 text-right">
                                        <div class="relative inline-block">
                                            <button class="text-gray-400 hover:text-white add-to-playlist-btn" data-song-id="<?= $song['song_id'] ?>">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-12 bg-gray-800 bg-opacity-20 rounded-lg">
                        <i class="far fa-heart text-5xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl mb-2">No liked songs yet</h3>
                        <p class="text-gray-400 mb-4">Like some songs to add them to your collection</p>
                        <a href="home.php" class="py-2 px-6 bg-green-500 rounded-full text-white font-bold hover:bg-green-400 transition-colors">
                            Browse Music
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

    <!-- Add to Playlist Modal -->
    <div id="add-to-playlist-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 p-6 rounded-lg max-w-md w-full">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Add to Playlist</h3>
                <button id="close-playlist-modal" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div id="playlist-list" class="max-h-80 overflow-y-auto mb-4">
                <?php
                // Get user's playlists
                $user_playlists_query = "SELECT playlist_id, name FROM playlists WHERE user_id = " . $_SESSION['user_id'] . " ORDER BY created_at DESC";
                $user_playlists_result = mysqli_query($conn, $user_playlists_query);
                
                if ($user_playlists_result && mysqli_num_rows($user_playlists_result) > 0) {
                    while ($playlist = mysqli_fetch_assoc($user_playlists_result)) {
                        echo '<div class="playlist-item p-3 hover:bg-gray-700 rounded cursor-pointer mb-1" data-playlist-id="' . $playlist['playlist_id'] . '">';
                        echo '<div class="flex items-center">';
                        echo '<i class="fas fa-music mr-3 text-gray-400"></i>';
                        echo '<span>' . htmlspecialchars($playlist['name']) . '</span>';
                        echo '</div></div>';
                    }
                } else {
                    echo '<p class="text-center text-gray-400 py-4">You don\'t have any playlists yet.</p>';
                    echo '<a href="addPlaylist.php" class="block text-center text-green-500 hover:underline">Create a playlist</a>';
                }
                ?>
            </div>
        </div>
    </div>

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

            // Unlike song functionality
            const unlikeButtons = document.querySelectorAll('.unlike-btn');
            unlikeButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const songId = this.dataset.songId;
                    const songRow = this.closest('tr');
                    
                    // AJAX request to unlike the song
                    fetch('like_song.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'song_id=' + songId + '&action=unlike'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove the song row with animation
                            songRow.style.transition = 'opacity 0.3s ease';
                            songRow.style.opacity = '0';
                            setTimeout(() => {
                                songRow.remove();
                                
                                // Update song count
                                const songCount = document.querySelectorAll('.song-row.liked-song-hover').length;
                                document.querySelector('.text-sm.text-gray-400 span:last-child').textContent = songCount + ' songs';
                                
                                // Show empty state if no songs left
                                if (songCount === 0) {
                                    const table = document.querySelector('table');
                                    const emptyState = `
                                        <div class="text-center py-12 bg-gray-800 bg-opacity-20 rounded-lg">
                                            <i class="far fa-heart text-5xl text-gray-600 mb-4"></i>
                                            <h3 class="text-xl mb-2">No liked songs yet</h3>
                                            <p class="text-gray-400 mb-4">Like some songs to add them to your collection</p>
                                            <a href="home.php" class="py-2 px-6 bg-green-500 rounded-full text-white font-bold hover:bg-green-400 transition-colors">
                                                Browse Music
                                            </a>
                                        </div>
                                    `;
                                    table.parentNode.innerHTML = emptyState;
                                    document.getElementById('play-all').style.display = 'none';
                                }
                            }, 300);
                        }
                    })
                    .catch(error => console.error('Error:', error));
                });
            });

            // Add to Playlist functionality
            let currentSongId = null;
            const addToPlaylistBtns = document.querySelectorAll('.add-to-playlist-btn');
            const addToPlaylistModal = document.getElementById('add-to-playlist-modal');
            const closePlaylistModalBtn = document.getElementById('close-playlist-modal');
            const playlistItems = document.querySelectorAll('.playlist-item');
            
            addToPlaylistBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    currentSongId = this.getAttribute('data-song-id');
                    addToPlaylistModal.classList.remove('hidden');
                });
            });
            
            if (closePlaylistModalBtn) {
                closePlaylistModalBtn.addEventListener('click', function() {
                    addToPlaylistModal.classList.add('hidden');
                });
            }
            
            // Close when clicking outside modal
            if (addToPlaylistModal) {
                addToPlaylistModal.addEventListener('click', function(e) {
                    if (e.target === addToPlaylistModal) {
                        addToPlaylistModal.classList.add('hidden');
                    }
                });
            }
            
            // Handle playlist selection
            playlistItems.forEach(item => {
                item.addEventListener('click', function() {
                    const playlistId = this.getAttribute('data-playlist-id');
                    
                    if (currentSongId) {
                        // Add song to selected playlist
                        fetch('add_to_playlist.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `song_id=${currentSongId}&playlist_id=${playlistId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message
                                alert('Song added to playlist successfully!');
                                addToPlaylistModal.classList.add('hidden');
                            } else {
                                alert(data.message || 'Failed to add song to playlist');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('An error occurred when trying to add the song to the playlist');
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>