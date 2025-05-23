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


function formatTime($seconds) {
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #121212;
            color: #ffffff;
        }
        .container {
            display: grid;
            grid-template-columns: 240px 1fr;
            grid-template-rows: 1fr 90px;
            height: 100vh;
        }
        .sidebar {
            background-color: #000000;
            grid-row: 1;
            grid-column: 1;
            padding: 24px 0;
        }
        .logo {
            padding: 0 24px;
            margin-bottom: 24px;
        }
        .logo h1 {
            color: #1DB954;
            font-size: 24px;
        }
        .nav-menu {
            list-style: none;
        }
        .nav-menu li {
            padding: 8px 24px;
        }
        .nav-menu li a {
            color: #b3b3b3;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-weight: 600;
        }
        .nav-menu li a:hover, .nav-menu li a.active {
            color: #ffffff;
        }
        .nav-menu li a i {
            margin-right: 16px;
            font-size: 20px;
        }
        .main-content {
            grid-row: 1;
            grid-column: 2;
            padding: 24px;
            overflow-y: auto;
            background: linear-gradient(to bottom, #404040, #121212);
        }
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h2 {
            font-size: 32px;
            margin-bottom: 16px;
        }
        .songs-container {
            width: 100%;
        }
        .song-list {
            width: 100%;
            border-collapse: collapse;
        }
        .song-list th {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 1px solid #333;
            color: #b3b3b3;
            font-weight: normal;
            font-size: 14px;
        }
        .song-list td {
            padding: 12px 8px;
            border-bottom: 1px solid #333;
        }
        .song-list tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .song-item {
            display: flex;
            align-items: center;
        }
        .song-number {
            margin-right: 16px;
            width: 16px;
            text-align: right;
            color: #b3b3b3;
        }
        .song-cover {
            width: 40px;
            height: 40px;
            margin-right: 16px;
        }
        .song-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .song-title-artist {
            display: flex;
            flex-direction: column;
        }
        .song-title {
            color: white;
        }
        .song-artist {
            font-size: 14px;
            color: #b3b3b3;
        }
        .song-album, .song-duration {
            color: #b3b3b3;
        }
        .play-button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 16px;
            margin-right: 16px;
        }
        .player-bar {
            grid-row: 2;
            grid-column: 1 / span 2;
            background-color: #181818;
            border-top: 1px solid #282828;
            padding: 16px;
            display: flex;
            align-items: center;
        }
        .now-playing {
            flex: 1;
            display: flex;
            align-items: center;
        }
        .now-playing-cover {
            width: 56px;
            height: 56px;
            margin-right: 16px;
        }
        .now-playing-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .now-playing-info {
            display: flex;
            flex-direction: column;
        }
        .now-playing-title {
            color: white;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 4px;
        }
        .now-playing-artist {
            color: #b3b3b3;
            font-size: 12px;
        }
        .player-controls {
            flex: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .control-buttons {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .control-button {
            background: none;
            border: none;
            color: #b3b3b3;
            cursor: pointer;
            font-size: 16px;
            margin: 0 8px;
        }
        .play-pause-button {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: white;
            color: black;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            margin: 0 16px;
        }
        .progress-container {
            width: 100%;
            display: flex;
            align-items: center;
        }
        .time {
            font-size: 11px;
            color: #b3b3b3;
            min-width: 40px;
            text-align: center;
        }
        .progress-bar {
            flex: 1;
            height: 4px;
            background-color: #535353;
            border-radius: 2px;
            margin: 0 8px;
            cursor: pointer;
            position: relative;
        }
        .progress {
            height: 100%;
            width: 0%;
            background-color: #b3b3b3;
            border-radius: 2px;
            position: relative;
        }
        .progress-handle {
            position: absolute;
            right: -6px;
            top: -4px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: white;
            display: none;
        }
        .progress-bar:hover .progress {
            background-color: #1DB954;
        }
        .progress-bar:hover .progress-handle {
            display: block;
        }
        .volume-controls {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }
        .volume-icon {
            color: #b3b3b3;
            margin-right: 8px;
            font-size: 16px;
        }
        .volume-bar {
            width: 100px;
            height: 4px;
            background-color: #535353;
            border-radius: 2px;
            cursor: pointer;
            position: relative;
        }
        .volume-level {
            height: 100%;
            width: 50%;
            background-color: #b3b3b3;
            border-radius: 2px;
        }
        .volume-bar:hover .volume-level {
            background-color: #1DB954;
        }
        .user-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        .user-info a {
            display: flex;
            align-items: center;
        }
        .album-link {
            color: #b3b3b3;
            text-decoration: none;
            transition: color 0.2s;
        }
        .album-link:hover {
            color: #1DB954;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar Navigation -->
        <div class="sidebar">
            <div class="logo">
                <h1>MusicStream</h1>
            </div>
            
            <ul class="nav-menu">
                <li><a href="home.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="#"><i class="fas fa-search"></i> Search</a></li>
                
                <?php if ($logged_in): ?>
                    <li><a href="#"><i class="fas fa-book"></i> Your Library</a></li>
                    <li><a href="#"><i class="fas fa-plus-square"></i> Create Playlist</a></li>
                    <li><a href="#"><i class="fas fa-heart"></i> Liked Songs</a></li>
                    <li class="user-info">
                        <a href="#">
                            <i class="fas fa-user"></i> 
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                    </li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Main Content Area -->
        <div class="main-content">
            <div class="page-header">
                <h2>Your Music</h2>
            </div>
            
            <div class="songs-container">
                <?php if(count($songs) > 0): ?>
                    <table class="song-list">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Title</th>
                                <th>Album</th>
                                <th><i class="far fa-clock"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($songs as $index => $song): ?>
                                <tr class="song-row" data-song-id="<?= $song['song_id'] ?>" data-file="<?= htmlspecialchars($song['file_path']) ?>">
                                    <td>
                                        <div class="song-item">
                                            <button class="play-button">
                                                <i class="fas fa-play"></i>
                                            </button>
                                            <span class="song-number"><?= $index + 1 ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="song-item">
                                            <div class="song-cover">
                                                <img src="<?= !empty($song['cover_art']) ? $song['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" alt="Cover">
                                            </div>
                                            <div class="song-title-artist">
                                                <div class="song-title"><?= htmlspecialchars($song['song_title']) ?></div>
                                                <div class="song-artist"><?= htmlspecialchars($song['artist_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="song-album">
                                        <?php if (!empty($song['album_title'])): ?>
                                            <a href="album.php?id=<?= $song['album_id'] ?>" class="album-link">
                                                <?= htmlspecialchars($song['album_title']) ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td class="song-duration"><?= formatTime($song['duration']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No songs available. Add some music first!</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Player Bar -->
        <div class="player-bar">
            <div class="now-playing">
                <div class="now-playing-cover">
                    <img id="current-cover" src="uploads/covers/default_cover.jpg" alt="Now playing">
                </div>
                <div class="now-playing-info">
                    <div id="current-title" class="now-playing-title">No song selected</div>
                    <div id="current-artist" class="now-playing-artist">-</div>
                </div>
            </div>
            
            <div class="player-controls">
                <div class="control-buttons">
                    <button class="control-button"><i class="fas fa-random"></i></button>
                    <button id="prev-button" class="control-button"><i class="fas fa-step-backward"></i></button>
                    <button id="play-pause" class="play-pause-button">
                        <i class="fas fa-play"></i>
                    </button>
                    <button id="next-button" class="control-button"><i class="fas fa-step-forward"></i></button>
                    <button class="control-button"><i class="fas fa-repeat"></i></button>
                </div>
                
                <div class="progress-container">
                    <div id="current-time" class="time">0:00</div>
                    <div id="progress-bar" class="progress-bar">
                        <div class="progress" id="progress">
                            <div class="progress-handle"></div>
                        </div>
                    </div>
                    <div id="total-time" class="time">0:00</div>
                </div>
            </div>
            
            <div class="volume-controls">
                <i class="fas fa-volume-up volume-icon"></i>
                <div id="volume-bar" class="volume-bar">
                    <div class="volume-level" id="volume-level"></div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Audio Element -->
    <audio id="audio-player"></audio>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const audioPlayer = document.getElementById('audio-player');
            const playPauseBtn = document.getElementById('play-pause');
            const prevBtn = document.getElementById('prev-button');
            const nextBtn = document.getElementById('next-button');
            const progressBar = document.getElementById('progress-bar');
            const progress = document.getElementById('progress');
            const currentTimeDisplay = document.getElementById('current-time');
            const totalTimeDisplay = document.getElementById('total-time');
            const volumeBar = document.getElementById('volume-bar');
            const volumeLevel = document.getElementById('volume-level');
            const currentTitle = document.getElementById('current-title');
            const currentArtist = document.getElementById('current-artist');
            const currentCover = document.getElementById('current-cover');
            const songRows = document.querySelectorAll('.song-row');
            
            let currentSongIndex = -1;
            let songs = [];
            
            // Initialize songs array from the table
            songRows.forEach((row, index) => {
                songs.push({
                    id: row.dataset.songId,
                    file: row.dataset.file,
                    title: row.querySelector('.song-title').textContent,
                    artist: row.querySelector('.song-artist').textContent,
                    cover: row.querySelector('.song-cover img').src,
                });
                
                // Add click event to each row
                row.addEventListener('click', function() {
                    playSong(index);
                });
                
                // Add click event to the play button in each row
                const playButton = row.querySelector('.play-button');
                playButton.addEventListener('click', function(event) {
                    event.stopPropagation(); // Prevent row click
                    playSong(index);
                });
            });
            
            // Play/Pause button
            playPauseBtn.addEventListener('click', function() {
                if (currentSongIndex < 0 && songs.length > 0) {
                    // No song is currently selected, play the first one
                    playSong(0);
                } else if (audioPlayer.paused) {
                    audioPlayer.play();
                    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                } else {
                    audioPlayer.pause();
                    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Previous button
            prevBtn.addEventListener('click', function() {
                if (currentSongIndex > 0) {
                    playSong(currentSongIndex - 1);
                } else if (songs.length > 0) {
                    playSong(songs.length - 1);
                }
            });
            
            // Next button
            nextBtn.addEventListener('click', function() {
                if (currentSongIndex < songs.length - 1) {
                    playSong(currentSongIndex + 1);
                } else if (songs.length > 0) {
                    playSong(0);
                }
            });
            
            // Progress bar click
            progressBar.addEventListener('click', function(e) {
                if (audioPlayer.src) {
                    const percent = (e.offsetX / progressBar.offsetWidth);
                    audioPlayer.currentTime = percent * audioPlayer.duration;
                }
            });
            
            // Volume bar click
            volumeBar.addEventListener('click', function(e) {
                const percent = (e.offsetX / volumeBar.offsetWidth);
                audioPlayer.volume = percent;
                volumeLevel.style.width = (percent * 100) + '%';
            });
            
            // Update progress as song plays
            audioPlayer.addEventListener('timeupdate', function() {
                const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
                progress.style.width = percent + '%';
                
                // Update current time display
                currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
            });
            
            // When song metadata is loaded
            audioPlayer.addEventListener('loadedmetadata', function() {
                totalTimeDisplay.textContent = formatTime(audioPlayer.duration);
            });
            
            // When song ends
            audioPlayer.addEventListener('ended', function() {
                // Play next song
                if (currentSongIndex < songs.length - 1) {
                    playSong(currentSongIndex + 1);
                } else {
                    // We're at the end of the playlist
                    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Play a song from the list
            function playSong(index) {
                if (index >= 0 && index < songs.length) {
                    currentSongIndex = index;
                    
                    // Update audio source
                    audioPlayer.src = songs[index].file;
                    audioPlayer.load();
                    
                    // Start playback
                    audioPlayer.play().then(() => {
                        // Update play button icon
                        playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                        
                        // Update current song display
                        currentTitle.textContent = songs[index].title;
                        currentArtist.textContent = songs[index].artist;
                        currentCover.src = songs[index].cover;
                        
                    }).catch(error => {
                        console.error('Error playing audio:', error);
                    });
                    
                    // Highlight current song in the list
                    songRows.forEach((row, i) => {
                        if (i === currentSongIndex) {
                            row.classList.add('playing');
                        } else {
                            row.classList.remove('playing');
                        }
                    });
                }
            }
            
            // Format time in seconds to MM:SS format
            function formatTime(seconds) {
                seconds = Math.floor(seconds);
                const minutes = Math.floor(seconds / 60);
                seconds = seconds % 60;
                return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
            }
            
            // Set initial volume
            audioPlayer.volume = 0.5;
            volumeLevel.style.width = '50%';
            
            // Handle keyboard shortcuts
            document.addEventListener('keydown', function(event) {
                // Check if spacebar was pressed
                if (event.code === 'Space' || event.keyCode === 32) {
                    // Prevent default spacebar behavior (page scrolling)
                    event.preventDefault();
                    
                    // Trigger play/pause
                    if (currentSongIndex < 0 && songs.length > 0) {
                        // No song is currently selected, play the first one
                        playSong(0);
                    } else if (audioPlayer.paused) {
                        audioPlayer.play();
                        playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    } else {
                        audioPlayer.pause();
                        playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                    }
                }
            });
        });
    </script>
</body>
</html>