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
    
    // Get proper references to the shuffle and loop buttons
    const shuffleBtn = document.getElementById('shuffle-button') || document.querySelector('.fa-random')?.parentElement;
    const loopBtn = document.getElementById('loop-button') || document.querySelector('.fa-repeat')?.parentElement;
    
    let currentSongIndex = -1;
    let songs = [];
    let isShuffleOn = false;
    let isLoopOn = false;
    
    // Initialize songs array from the table
    songRows.forEach((row, index) => {
        songs.push({
            id: row.dataset.songId,
            file: row.dataset.file,
            title: row.querySelector('.song-title') ? row.querySelector('.song-title').textContent : 'Unknown Title',
            artist: row.querySelector('.song-artist') ? row.querySelector('.song-artist').textContent : 'Unknown Artist',
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
    
    // Play/Pause button - Fixed to prevent rewinding
    playPauseBtn.addEventListener('click', function() {
        // Store the current time to prevent rewinding
        const currentTime = audioPlayer.currentTime;
        
        if (currentSongIndex < 0 && songs.length > 0) {
            // No song is currently selected, play the first one
            playSong(0);
        } else if (audioPlayer.paused) {
            // Fixed playback resume - don't reset currentTime
            audioPlayer.play().then(() => {
                playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
            }).catch(error => {
                console.error('Error playing audio:', error);
            });
        } else {
            audioPlayer.pause();
            playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            // Make sure currentTime is not reset
            audioPlayer.currentTime = currentTime;
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
        playNextSong();
    });
    
    // Function to play the next song based on shuffle and loop settings
    function playNextSong() {
        if (songs.length === 0) return;
        
        if (isShuffleOn) {
            // Play a random song excluding the current one
            let randomIndex;
            if (songs.length > 1) {
                do {
                    randomIndex = Math.floor(Math.random() * songs.length);
                } while (randomIndex === currentSongIndex);
                playSong(randomIndex);
            } else {
                playSong(0);
            }
        } else {
            // Normal sequential playback
            if (currentSongIndex < songs.length - 1) {
                playSong(currentSongIndex + 1);
            } else if (isLoopOn) {
                // Loop back to the first song when reaching the end
                playSong(0);
            } else {
                // End of playlist, just reset UI
                playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            }
        }
    }
    
    // Add shuffle functionality
    if (shuffleBtn) {
        // Initialize shuffle button state from localStorage if available
        const savedPlayerState = localStorage.getItem('musicstream_player_state');
        if (savedPlayerState) {
            try {
                const state = JSON.parse(savedPlayerState);
                isShuffleOn = state.isShuffleOn || false;
                if (isShuffleOn) {
                    shuffleBtn.classList.add('text-green-500');
                    shuffleBtn.classList.remove('text-gray-400');
                }
            } catch(e) {
                console.error("Error restoring shuffle state:", e);
            }
        }
        
        shuffleBtn.addEventListener('click', function() {
            isShuffleOn = !isShuffleOn;
            if (isShuffleOn) {
                shuffleBtn.classList.add('text-green-500');
                shuffleBtn.classList.remove('text-gray-400');
            } else {
                shuffleBtn.classList.remove('text-green-500');
                shuffleBtn.classList.add('text-gray-400');
            }
        });
    }
    
    // Add loop functionality
    if (loopBtn) {
        // Initialize loop button state from localStorage if available
        const savedPlayerState = localStorage.getItem('musicstream_player_state');
        if (savedPlayerState) {
            try {
                const state = JSON.parse(savedPlayerState);
                isLoopOn = state.isLoopOn || false;
                if (isLoopOn) {
                    loopBtn.classList.add('text-green-500');
                    loopBtn.classList.remove('text-gray-400');
                    audioPlayer.loop = true;  // Enable looping on the audio element
                }
            } catch(e) {
                console.error("Error restoring loop state:", e);
            }
        }
        
        loopBtn.addEventListener('click', function() {
            isLoopOn = !isLoopOn;
            
            if (isLoopOn) {
                loopBtn.classList.add('text-green-500');
                loopBtn.classList.remove('text-gray-400');
                audioPlayer.loop = true;  // Enable looping when button is clicked
            } else {
                loopBtn.classList.remove('text-green-500');
                loopBtn.classList.add('text-gray-400');
                audioPlayer.loop = false;  // Disable looping
            }
            
            // Save state to localStorage immediately
            try {
                const currentState = JSON.parse(localStorage.getItem('musicstream_player_state') || '{}');
                currentState.isLoopOn = isLoopOn;
                localStorage.setItem('musicstream_player_state', JSON.stringify(currentState));
            } catch(e) {
                console.error("Error saving loop state:", e);
            }
        });
    } else {
        console.warn("Loop button not found in the DOM");
    }
    
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
        // Fix for duration display: Check for valid duration value
        if (isFinite(audioPlayer.duration) && !isNaN(audioPlayer.duration)) {
            const percent = (audioPlayer.currentTime / audioPlayer.duration) * 100;
            progress.style.width = percent + '%';
            
            // Update current time display - make sure it's a valid number
            if (isFinite(audioPlayer.currentTime)) {
                currentTimeDisplay.textContent = formatTime(audioPlayer.currentTime);
            }
        }
    });
    
    // When song metadata is loaded
    audioPlayer.addEventListener('loadedmetadata', function() {
        // Fix for duration display: Ensure duration is valid
        if (isFinite(audioPlayer.duration) && !isNaN(audioPlayer.duration)) {
            totalTimeDisplay.textContent = formatTime(audioPlayer.duration);
            
            // Apply loop setting to the new song
            audioPlayer.loop = isLoopOn;
        }
    });
    
    // When song ends
    audioPlayer.addEventListener('ended', function() {
        // The loop attribute will automatically loop a single song if enabled
        // Only call playNextSong if loop is not enabled on the audio element
        if (!audioPlayer.loop) {
            playNextSong();
        }
    });
    
    // Play a song from the list
    function playSong(index) {
        if (index >= 0 && index < songs.length) {
            currentSongIndex = index;
            
            // Update audio source
            audioPlayer.src = songs[index].file;
            audioPlayer.load();
            
            // Apply loop setting before starting playback
            audioPlayer.loop = isLoopOn;
            
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
        if (isNaN(seconds) || !isFinite(seconds)) return "0:00";
        seconds = Math.floor(seconds);
        const minutes = Math.floor(seconds / 60);
        seconds = seconds % 60;
        return minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
    }
    
    // Set initial volume
    audioPlayer.volume = 0.5;
    volumeLevel.style.width = '50%';
    
    // Handle keyboard shortcuts
    document.addEventListener('keydown', function(event) {
        // Don't capture keyboard events when user is typing in form elements
        if (event.target.tagName === 'INPUT' || 
            event.target.tagName === 'TEXTAREA' || 
            event.target.isContentEditable) {
            return; // Let the default behavior happen for input elements
        }
        
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