document.addEventListener('DOMContentLoaded', function() {
    const audioPlayer = document.getElementById('audio-player');
    const playPauseBtn = document.getElementById('play-pause');
    const currentTitle = document.getElementById('current-title');
    const currentArtist = document.getElementById('current-artist');
    const currentCover = document.getElementById('current-cover');
    const volumeLevel = document.getElementById('volume-level');
    const progress = document.getElementById('progress');
    const currentTimeDisplay = document.getElementById('current-time');
    const totalTimeDisplay = document.getElementById('total-time');
    // Get proper references to the shuffle and loop buttons (with fallbacks)
    const shuffleBtn = document.getElementById('shuffle-button') || document.querySelector('.fa-random')?.parentElement;
    const loopBtn = document.getElementById('loop-button') || document.querySelector('.fa-repeat')?.parentElement;

    // Save player state more frequently and on page navigation
    function savePlayerState() {
        if (!audioPlayer.src || audioPlayer.src === '') return;
        
        // Store volume as a definite value between 0 and 1
        const currentVolume = audioPlayer.volume;
        
        localStorage.setItem('musicstream_player_state', JSON.stringify({
            src: audioPlayer.src,
            currentTime: audioPlayer.currentTime,
            duration: audioPlayer.duration,
            isPlaying: !audioPlayer.paused,
            title: currentTitle.textContent,
            artist: currentArtist.textContent,
            cover: currentCover.src,
            volume: currentVolume, // Store the exact volume value
            volumePercent: (currentVolume * 100) + '%', // Store as percentage string for visual display
            isShuffleOn: shuffleBtn ? shuffleBtn.classList.contains('text-green-500') : false,
            isLoopOn: loopBtn ? loopBtn.classList.contains('text-green-500') : false
        }));
        
        console.log("Saved volume: " + currentVolume); // Debug log
    }
    
    // Save state more frequently for smoother transitions
    setInterval(savePlayerState, 500);
    window.addEventListener('beforeunload', savePlayerState);
    
    // Optimized player state loading for smoother playback
    function loadPlayerState() {
        const savedState = localStorage.getItem('musicstream_player_state');
        if (!savedState) return;
        
        try {
            const state = JSON.parse(savedState);
            
            // Set audio source and begin loading immediately with high priority
            audioPlayer.src = state.src;
            
            // Set volume directly as the saved value (absolute, not relative)
            if (state.volume !== undefined && state.volume >= 0 && state.volume <= 1) {
                audioPlayer.volume = state.volume; // Set exact volume value
                volumeLevel.style.width = (state.volume * 100) + '%'; // Update visual display
                console.log("Restored volume: " + state.volume); // Debug log
            } else {
                // Fallback to default volume
                audioPlayer.volume = 0.5;
                volumeLevel.style.width = '50%';
            }
            
            audioPlayer.load(); // Start loading the audio right away
            
            // Update UI elements asynchronously to not block audio loading
            setTimeout(() => {
                // Update UI
                currentTitle.textContent = state.title;
                currentArtist.textContent = state.artist;
                currentCover.src = state.cover;
                
                // Set time displays immediately
                currentTimeDisplay.textContent = formatTime(state.currentTime);
                totalTimeDisplay.textContent = formatTime(state.duration);
                
                // Update progress bar
                const percent = (state.currentTime / state.duration) * 100;
                progress.style.width = percent + '%';
                
                // Restore shuffle and loop states
                if (state.isShuffleOn && shuffleBtn) {
                    shuffleBtn.classList.add('text-green-500');
                    shuffleBtn.classList.remove('text-gray-400');
                }
                
                if (state.isLoopOn && loopBtn) {
                    loopBtn.classList.add('text-green-500');
                    loopBtn.classList.remove('text-gray-400');
                }
            }, 0);
            
            // Use canplay event for faster playback
            audioPlayer.addEventListener('canplay', function onCanPlay() {
                // Set current time
                audioPlayer.currentTime = state.currentTime;
                
                // Resume playing ASAP if it was playing
                if (state.isPlaying) {
                    const playPromise = audioPlayer.play();
                    if (playPromise !== undefined) {
                        playPromise.then(() => {
                            playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                        }).catch(err => {
                            // Auto-play might be blocked, try again with user interaction
                            console.warn('Auto-play blocked, will retry after user interaction');
                        });
                    }
                }
                
                audioPlayer.removeEventListener('canplay', onCanPlay);
            });
        } catch(e) {
            console.error("Error restoring player state:", e);
        }
    }
    
    // Format time function
    function formatTime(seconds) {
        if (isNaN(seconds) || seconds === Infinity) return "0:00";
        return Math.floor(seconds / 60) + ":" + String(Math.floor(seconds % 60)).padStart(2, "0");
    }
    
    // Load state on page load
    loadPlayerState();
    
    // Also update volume change handler in player.js
    const volumeBar = document.getElementById('volume-bar');
    if (volumeBar) {
        volumeBar.addEventListener('click', function(e) {
            const percent = (e.offsetX / volumeBar.offsetWidth);
            audioPlayer.volume = percent; // Set absolute volume value
            volumeLevel.style.width = (percent * 100) + '%';
            
            // Save volume state immediately on change
            try {
                const currentState = JSON.parse(localStorage.getItem('musicstream_player_state') || '{}');
                currentState.volume = percent;
                localStorage.setItem('musicstream_player_state', JSON.stringify(currentState));
                console.log("Volume changed and saved: " + percent);
            } catch(e) {
                console.error("Error saving volume state:", e);
            }
        });
    }
});