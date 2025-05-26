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

    // Save player state more frequently and on page navigation
    function savePlayerState() {
        if (!audioPlayer.src || audioPlayer.src === '') return;
        
        localStorage.setItem('musicstream_player_state', JSON.stringify({
            src: audioPlayer.src,
            currentTime: audioPlayer.currentTime,
            duration: audioPlayer.duration,
            isPlaying: !audioPlayer.paused,
            title: currentTitle.textContent,
            artist: currentArtist.textContent,
            cover: currentCover.src,
            volume: audioPlayer.volume
        }));
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
            audioPlayer.volume = state.volume || 0.5;
            audioPlayer.load(); // Start loading the audio right away
            
            // Update UI elements asynchronously to not block audio loading
            setTimeout(() => {
                // Update UI
                currentTitle.textContent = state.title;
                currentArtist.textContent = state.artist;
                currentCover.src = state.cover;
                volumeLevel.style.width = (state.volume * 100) + '%';
                
                // Set time displays immediately
                currentTimeDisplay.textContent = formatTime(state.currentTime);
                totalTimeDisplay.textContent = formatTime(state.duration);
                
                // Update progress bar
                const percent = (state.currentTime / state.duration) * 100;
                progress.style.width = percent + '%';
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
                            
                            // Set up a one-time click handler to play audio
                            document.body.addEventListener('click', function playOnFirstInteraction() {
                                audioPlayer.play().then(() => {
                                    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                                });
                                document.body.removeEventListener('click', playOnFirstInteraction);
                            }, { once: true });
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
        seconds = Math.floor(seconds || 0);
        const minutes = Math.floor(seconds / 60);
        seconds = seconds % 60;
        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }
    
    // Load state on page load
    loadPlayerState();
});