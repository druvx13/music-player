const audio = new Audio();
        let currentSongIndex = -1;
        let playlist = [];
        let audioContext;
        let analyser;
        let dataArray;
        let waveformBars = [];
        const state = {
            isPlaying: false,
            isShuffled: false,
            isRepeating: false,
            volume: 0.7,
            timer: null,
            isDraggingProgress: false
        };

        // Player Elements
        const playBtn = document.getElementById('playBtn');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const progress = document.getElementById('progress');
        const progressBar = document.getElementById('progressBar');
        const currentTimeDisplay = document.getElementById('currentTime');
        const durationDisplay = document.getElementById('duration');
        const songTitle = document.getElementById('songTitle');
        const artist = document.getElementById('artist');
        const coverArt = document.getElementById('coverArt');
        const waveform = document.getElementById('waveform');
        const volumeSlider = document.getElementById('volumeSlider');
        const shuffleBtn = document.getElementById('shuffleBtn');
        const repeatBtn = document.getElementById('repeatBtn');
        const miniPlayer = document.getElementById('miniPlayer');
        const lyricsBtn = document.getElementById('lyricsBtn');
        const lyricsModal = document.getElementById('lyricsModal');
        const lyricsText = document.getElementById('lyricsText');
        const closeLyricsBtn = document.getElementById('closeLyricsBtn');
        // Playlist Elements
        const playlistElement = document.getElementById('playlist');
        const refreshBtn = document.getElementById('refreshBtn');
        // Upload Elements
        const uploadModal = document.getElementById('uploadModal');
        const uploadBtn = document.getElementById('uploadBtn');
        const cancelBtn = document.getElementById('cancelBtn');
        const cancelUploadBtn = document.getElementById('cancelUploadBtn');
        const uploadForm = document.getElementById('uploadForm');

        // Initialize player
        async function init() {
            await fetchPlaylist();
            updatePlaylistDisplay();
            createWaveformBars();
            // Set up audio context on first user interaction
            document.addEventListener('click', function initAudio() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    audioContext = new AudioContext();
                    analyser = audioContext.createAnalyser();
                    analyser.fftSize = 64;
                    dataArray = new Uint8Array(analyser.frequencyBinCount);
                    const source = audioContext.createMediaElementSource(audio);
                    source.connect(analyser);
                    analyser.connect(audioContext.destination);
                    visualize();
                    document.removeEventListener('click', initAudio);
                } catch (e) {
                    console.error("AudioContext error:", e);
                }
            }, { once: true });
            // Set initial volume
            audio.volume = state.volume;
            // Check for mini player support
            if ('mediaSession' in navigator) {
                miniPlayer.classList.remove('hidden');
                setupMediaSession();
            }
        }

        // Fetch playlist from PHP endpoint
        async function fetchPlaylist() {
            try {
                // Show loading state
                playlistElement.innerHTML = `
                    <li class="text-center py-10">
                        <div class="spinner mx-auto mb-2"></div>
                        <p>Loading playlist...</p>
                    </li>
                `;
                const response = await fetch('index.php?action=getPlaylist');
                const data = await response.json();
                if (Array.isArray(data)) {
                    playlist = data;
                } else {
                    console.error("Playlist fetch failed");
                    showNotification("Failed to load playlist", true);
                }
            } catch (error) {
                console.error("Error fetching playlist:", error);
                showNotification("Network error loading playlist", true);
            }
        }

        // Update playlist display
        function updatePlaylistDisplay() {
            if (playlist.length === 0) {
                playlistElement.innerHTML = `
                    <li class="text-center py-10 text-white/50">
                        <i class="fas fa-music text-3xl mb-2"></i>
                        <p>No songs in playlist</p>
                    </li>
                `;
                return;
            }
            playlistElement.innerHTML = playlist.map((song, index) => `
                <li class="song-item bg-white/5 p-3 rounded-lg cursor-pointer hover:bg-white/10 transition-all ${currentSongIndex === index ? 'current-song' : ''}"
                     onclick="playSong(${index})">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-md overflow-hidden ${song.cover ? '' : 'default-cover'}">
                            ${song.cover ?
                                `<img src="${song.cover}" class="w-full h-full object-cover">` :
                                `<i class="fas fa-music w-full h-full flex items-center justify-center"></i>`}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">${song.title}</p>
                            <p class="text-sm text-white/70 truncate">${song.artist}</p>
                        </div>
                        <span class="text-xs text-white/50">${formatTime(song.duration)}</span>
                    </div>
                </li>
            `).join('');
        }

        // Helper function to format time
        function formatTime(seconds) {
            if (isNaN(seconds)) return "0:00";
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        // Create waveform bars
        function createWaveformBars() {
            waveform.innerHTML = '';
            waveformBars = [];
            for (let i = 0; i < 16; i++) {
                const bar = document.createElement('div');
                bar.className = 'waveform-bar';
                bar.style.animationDelay = `${i * 50}ms`;
                waveform.appendChild(bar);
                waveformBars.push(bar);
            }
        }

        // Setup media session for mini player
        function setupMediaSession() {
            navigator.mediaSession.setActionHandler('play', togglePlayPause);
            navigator.mediaSession.setActionHandler('pause', togglePlayPause);
            navigator.mediaSession.setActionHandler('previoustrack', prevSong);
            navigator.mediaSession.setActionHandler('nexttrack', nextSong);
            miniPlayer.addEventListener('click', () => {
                // Scroll to player
                document.querySelector('.player-container').scrollIntoView({ behavior: 'smooth' });
            });
        }

        // Update media session metadata
        function updateMediaSession(song) {
            if (!('mediaSession' in navigator)) return;
            navigator.mediaSession.metadata = new MediaMetadata({
                title: song.title,
                artist: song.artist,
                artwork: song.cover ? [
                    { src: song.cover, sizes: '96x96', type: 'image/jpeg' },
                    { src: song.cover, sizes: '128x128', type: 'image/jpeg' },
                    { src: song.cover, sizes: '192x192', type: 'image/jpeg' },
                    { src: song.cover, sizes: '256x256', type: 'image/jpeg' },
                    { src: song.cover, sizes: '384x384', type: 'image/jpeg' },
                    { src: song.cover, sizes: '512x512', type: 'image/jpeg' }
                ] : []
            });
        }

        // Play song
        async function playSong(index) {
            if (index < 0 || index >= playlist.length) return;
            currentSongIndex = index;
            const song = playlist[index];
            // Update UI
            songTitle.textContent = song.title;
            artist.textContent = song.artist;
            // Handle long song titles with marquee effect
            if (song.title.length > 20) {
                songTitle.innerHTML = `<span class="marquee">${song.title}</span>`;
            } else {
                songTitle.textContent = song.title;
            }
            if (song.cover) {
                coverArt.innerHTML = `<img src="${song.cover}" class="w-full h-full object-cover">`;
            } else {
                coverArt.innerHTML = `<i class="fas fa-music text-5xl"></i>`;
            }
            // Update lyrics
            lyricsText.textContent = song.lyrics || "No lyrics available for this song.";
            // Highlight current song in playlist
            const songItems = document.querySelectorAll('.song-item');
            songItems.forEach((item, i) => {
                if (i === index) {
                    item.classList.add('current-song');
                } else {
                    item.classList.remove('current-song');
                }
            });
            // Update media session
            updateMediaSession(song);
            // Load and play audio
            audio.src = song.file;
            audio.load();
            try {
                await audio.play();
                playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
                state.isPlaying = true;
                waveform.classList.remove('hidden');
                waveform.classList.add('playing');
                showNotification(`Now playing: ${song.title}`);
                // Update mini player
                miniPlayer.innerHTML = `<i class="fas fa-pause"></i>`;
            } catch (error) {
                console.error("Play error:", error);
                showNotification('Click anywhere to play', true);
            }
        }

        // Toggle play/pause
async function togglePlayPause() {
    if (playlist.length === 0) {
        showNotification('No songs in playlist', true);
        return;
    }
    if (currentSongIndex === -1) {
        await playSong(0);
        return;
    }
    if (audio.paused) {
        try {
            await audio.play();
            playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
            state.isPlaying = true;
            waveform.classList.add('playing');
            miniPlayer.innerHTML = `<i class="fas fa-pause"></i>`;
        } catch (error) {
            console.error("Play error:", error);
            showNotification('Click anywhere to play', true);
        }
    } else {
        audio.pause();
        playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
        state.isPlaying = false;
        waveform.classList.remove('playing');
        miniPlayer.innerHTML = `<i class="fas fa-play"></i>`;
    }
}
// Previous song
function prevSong() {
    if (playlist.length === 0) return;
    let newIndex = currentSongIndex - 1;
    if (newIndex < 0) newIndex = playlist.length - 1;
    playSong(newIndex);
}
// Next song
function nextSong() {
    if (playlist.length === 0) return;
    let newIndex = currentSongIndex + 1;
    if (newIndex >= playlist.length) newIndex = 0;
    playSong(newIndex);
}
// Toggle shuffle
function toggleShuffle() {
    state.isShuffled = !state.isShuffled;
    shuffleBtn.classList.toggle('text-white', state.isShuffled);
    shuffleBtn.classList.toggle('text-white/50', !state.isShuffled);
    if (state.isShuffled) {
        // Shuffle the playlist (except current song)
        const currentSong = playlist[currentSongIndex];
        const shuffledPlaylist = [...playlist];
        shuffledPlaylist.splice(currentSongIndex, 1);
        // Fisher-Yates shuffle algorithm
        for (let i = shuffledPlaylist.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffledPlaylist[i], shuffledPlaylist[j]] = [shuffledPlaylist[j], shuffledPlaylist[i]];
        }
        // Reinsert current song at the beginning
        shuffledPlaylist.unshift(currentSong);
        playlist = shuffledPlaylist;
        currentSongIndex = 0;
        updatePlaylistDisplay();
        showNotification('Playlist shuffled');
    } else {
        // Fetch fresh playlist from server
        fetchPlaylist().then(() => {
            updatePlaylistDisplay();
            currentSongIndex = playlist.findIndex(song => song.title === songTitle.textContent);
            showNotification('Shuffle off');
        });
    }
}
// Toggle repeat
function toggleRepeat() {
    state.isRepeating = !state.isRepeating;
    repeatBtn.classList.toggle('text-white', state.isRepeating);
    repeatBtn.classList.toggle('text-white/50', !state.isRepeating);
    showNotification(state.isRepeating ? 'Repeat on' : 'Repeat off');
}
// Update progress bar and time display
function updateTimeDisplay() {
    if (isNaN(audio.duration) || state.isDraggingProgress) return;
    const progressPercent = (audio.currentTime / audio.duration) * 100;
    progress.style.width = `${progressPercent}%`;
    currentTimeDisplay.textContent = formatTime(audio.currentTime);
    durationDisplay.textContent = formatTime(audio.duration);
}
// Visualizer
function visualize() {
    function draw() {
        requestAnimationFrame(draw);
        if (!analyser || !waveform) return;
        analyser.getByteFrequencyData(dataArray);
        waveformBars.forEach((bar, i) => {
            const value = dataArray[i] / 255;
            const height = 10 + (value * 50);
            bar.style.height = `${height}%`;
        });
    }
    draw();
}
// Show notification toast
function showNotification(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg ${
        isError ? 'bg-red-500' : 'bg-green-500'
    } text-white`;
    // Add show class
    toast.classList.add('show');
    // Remove after delay
    clearTimeout(toast.timeoutId);
    toast.timeoutId = setTimeout(() => {
        toast.classList.remove('show');
    }, 3000);
}
// Event Listeners
playBtn.addEventListener('click', togglePlayPause);
prevBtn.addEventListener('click', prevSong);
nextBtn.addEventListener('click', nextSong);
shuffleBtn.addEventListener('click', toggleShuffle);
repeatBtn.addEventListener('click', toggleRepeat);
// Progress bar click to seek
progressBar.addEventListener('click', (e) => {
    if (isNaN(audio.duration)) return;
    const rect = progressBar.getBoundingClientRect();
    const pos = (e.clientX - rect.left) / rect.width;
    audio.currentTime = pos * audio.duration;
});
// Progress bar drag
progressBar.addEventListener('mousedown', () => {
    state.isDraggingProgress = true;
});
document.addEventListener('mousemove', (e) => {
    if (state.isDraggingProgress && !isNaN(audio.duration)) {
        const rect = progressBar.getBoundingClientRect();
        const pos = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1);
        progress.style.width = `${pos * 100}%`;
        currentTimeDisplay.textContent = formatTime(pos * audio.duration);
    }
});
document.addEventListener('mouseup', () => {
    if (state.isDraggingProgress && !isNaN(audio.duration)) {
        const pos = parseFloat(progress.style.width) / 100;
        audio.currentTime = pos * audio.duration;
        state.isDraggingProgress = false;
    }
});
audio.addEventListener('timeupdate', updateTimeDisplay);
audio.addEventListener('ended', () => {
    if (state.isRepeating) {
        audio.currentTime = 0;
        audio.play();
    } else {
        nextSong();
    }
});
audio.addEventListener('play', () => {
    playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
    state.isPlaying = true;
    waveform.classList.add('playing');
    miniPlayer.innerHTML = `<i class="fas fa-pause"></i>`;
});
audio.addEventListener('pause', () => {
    playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
    state.isPlaying = false;
    waveform.classList.remove('playing');
    miniPlayer.innerHTML = `<i class="fas fa-play"></i>`;
});
volumeSlider.addEventListener('input', (e) => {
    audio.volume = e.target.value;
    state.volume = e.target.value;
});
// Lyrics modal
lyricsBtn.addEventListener('click', () => {
    lyricsModal.style.display = 'flex';
});
closeLyricsBtn.addEventListener('click', () => {
    lyricsModal.style.display = 'none';
});
// Upload modal handling
uploadBtn.addEventListener('click', () => {
    uploadModal.style.display = 'flex';
});
cancelBtn.addEventListener('click', () => {
    uploadModal.style.display = 'none';
});
cancelUploadBtn.addEventListener('click', () => {
    uploadModal.style.display = 'none';
});
// Handle file selection for cover
document.getElementById('coverInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('coverFileName').textContent = file.name;
    document.getElementById('coverPreview').classList.remove('hidden');
    const reader = new FileReader();
    reader.onload = (event) => {
        document.getElementById('coverPreviewImg').src = event.target.result;
    };
    reader.readAsDataURL(file);
});
// Handle file selection for song
document.getElementById('songInput').addEventListener('change', (e) => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('songFileName').textContent = file.name;
    // Try to extract duration
    const audio = new Audio();
    audio.src = URL.createObjectURL(file);
    audio.addEventListener('loadedmetadata', () => {
        console.log("Duration:", audio.duration);
    });
});
// Handle form submission
uploadForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-2"></div> Uploading...';
    submitBtn.disabled = true;
    const formData = new FormData(e.target);
    try {
        const response = await fetch('index.php?action=uploadSong', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (result.success) {
            playlist.push(result.song);
            updatePlaylistDisplay();
            uploadModal.style.display = 'none';
            uploadForm.reset();
            document.getElementById('coverPreview').classList.add('hidden');
            document.getElementById('coverFileName').textContent = 'Choose cover image';
            document.getElementById('songFileName').textContent = 'Choose MP3 file';
            showNotification('Song added to playlist!');
        } else {
            showNotification(result.error || 'Upload failed', true);
        }
    } catch (error) {
        console.error("Upload error:", error);
        showNotification('Network error during upload', true);
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
});
// Refresh playlist
refreshBtn.addEventListener('click', async () => {
    refreshBtn.innerHTML = '<i class="fas fa-sync-alt animate-spin"></i>';
    await fetchPlaylist();
    updatePlaylistDisplay();
    refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
    showNotification('Playlist refreshed');
});
// Initialize on load
window.addEventListener('DOMContentLoaded', init);
// Expose functions to global scope for inline event handlers
window.playSong = playSong;
