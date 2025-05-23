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
    const playAllBtn = document.getElementById('play-all');
    
    let currentSongIndex = -1;
    let songs = [];
    
    // Initialize songs array from the table
    songRows.forEach((row, index) => {
        songs.push({
            id: row.dataset.songId,
            file: row.dataset.file,
            title: row.querySelector('.song-title').textContent,
            artist: row.querySelector('.song-artist').textContent,
            cover: row.dataset.albumCover || 'uploads/covers/default_cover.jpg'
        });
        
        // Add click event to each row
        row.addEventListener('click', function() {
            playSong(index);
        });
        
        // Add click event to the play button in each row
        const playButton = row.querySelector('.play-button');
        if (playButton) {
            playButton.addEventListener('click', function(event) {
                event.stopPropagation(); // Prevent row click
                playSong(index);
            });
        }
    });
    
    // Play all button
    if (playAllBtn) {
        playAllBtn.addEventListener('click', function() {
            if (songs.length > 0) {
                playSong(0);
            }
        });
    }
    
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