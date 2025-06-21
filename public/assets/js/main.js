// public/assets/js/main.js

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
    isRepeating: false, // false: no repeat, true: repeat current song, 'all': repeat playlist
    volume: 0.7,
    timer: null,
    isDraggingProgress: false
};

// --- DOM Elements ---
let playBtn, prevBtn, nextBtn, progress, progressBar, currentTimeDisplay, durationDisplay,
    songTitle, artist, coverArt, waveform, volumeSlider, shuffleBtn, repeatBtn,
    miniPlayer, lyricsBtn, lyricsModal, lyricsText, closeLyricsBtn, playlistElement,
    refreshBtn, uploadModal, uploadBtn, cancelBtn, cancelUploadBtn, uploadForm,
    coverInput, coverFileName, coverPreview, coverPreviewImg, songInput, songFileName,
    toastElement;

// --- API Endpoint ---
// Determine the correct base path for API calls.
// If this script is loaded from public/assets/js/main.js,
// and index.php (which might call api.php) is in public/,
// then api.php is effectively at a path relative to the public root.
// We assume api.php is accessible via a URL like '/api.php' or '/index.php/api' or similar.
// For this project, public/index.php will include src/api.php for API requests.
// So, JS will call public/index.php with query parameters.
const API_ENDPOINT = 'index.php'; // Calls will be made to public/index.php?action=...

// --- Initialization ---
document.addEventListener('DOMContentLoaded', () => {
    // Assign DOM elements after they are loaded
    playBtn = document.getElementById('playBtn');
    prevBtn = document.getElementById('prevBtn');
    nextBtn = document.getElementById('nextBtn');
    progress = document.getElementById('progress');
    progressBar = document.getElementById('progressBar');
    currentTimeDisplay = document.getElementById('currentTime');
    durationDisplay = document.getElementById('duration');
    songTitle = document.getElementById('songTitle');
    artist = document.getElementById('artist');
    coverArt = document.getElementById('coverArt');
    waveform = document.getElementById('waveform'); // The container for waveform bars
    volumeSlider = document.getElementById('volumeSlider');
    shuffleBtn = document.getElementById('shuffleBtn');
    repeatBtn = document.getElementById('repeatBtn');
    miniPlayer = document.getElementById('miniPlayer');
    lyricsBtn = document.getElementById('lyricsBtn');
    lyricsModal = document.getElementById('lyricsModal');
    lyricsText = document.getElementById('lyricsText');
    closeLyricsBtn = document.getElementById('closeLyricsBtn');
    playlistElement = document.getElementById('playlist');
    refreshBtn = document.getElementById('refreshBtn');
    uploadModal = document.getElementById('uploadModal');
    uploadBtn = document.getElementById('uploadBtn'); // Main upload button in playlist section
    cancelBtn = document.getElementById('cancelBtn'); // Cancel button in modal
    cancelUploadBtn = document.getElementById('cancelUploadBtn'); // Secondary cancel button in modal form
    uploadForm = document.getElementById('uploadForm');
    coverInput = document.getElementById('coverInput');
    coverFileName = document.getElementById('coverFileName');
    coverPreview = document.getElementById('coverPreview');
    coverPreviewImg = document.getElementById('coverPreviewImg');
    songInput = document.getElementById('songInput');
    songFileName = document.getElementById('songFileName');
    toastElement = document.getElementById('toast');

    initPlayer();
    attachEventListeners();
});

async function initPlayer() {
    await fetchPlaylist();
    updatePlaylistDisplay();
    createWaveformBars(); // Create static bars for the visualizer

    // Set up audio context on first user interaction (e.g., click)
    // This is important for browser autoplay policies.
    document.body.addEventListener('click', initAudioContext, { once: true });

    audio.volume = state.volume;
    volumeSlider.value = state.volume; // Reflect initial volume on slider

    if ('mediaSession' in navigator) {
        miniPlayer.classList.remove('hidden'); // Show mini-player controls if supported
        setupMediaSession();
    }
}

function initAudioContext() {
    if (audioContext) return; // Already initialized
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        audioContext = new AudioContext();
        analyser = audioContext.createAnalyser();
        analyser.fftSize = 32; // Reduced for fewer bars, matching createWaveformBars
        dataArray = new Uint8Array(analyser.frequencyBinCount);

        const source = audioContext.createMediaElementSource(audio);
        source.connect(analyser);
        analyser.connect(audioContext.destination);

        visualizeWaveform(); // Start the visualizer animation loop
    } catch (e) {
        console.error("AudioContext initialization error:", e);
        showNotification("Could not initialize audio visualizer.", true);
    }
}

// --- Playlist Management ---
async function fetchPlaylist() {
    try {
        playlistElement.innerHTML = `
            <li class="text-center py-10">
                <div class="spinner mx-auto mb-2"></div>
                <p>Loading playlist...</p>
            </li>`;
        const response = await fetch(`${API_ENDPOINT}?action=getPlaylist`);
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: "Unknown error fetching playlist" }));
            throw new Error(errorData.error || `HTTP error ${response.status}`);
        }
        const data = await response.json();
        if (Array.isArray(data)) {
            playlist = data;
            if (playlist.length > 0 && currentSongIndex === -1) {
                // Optionally, pre-select the first song without playing
                // displaySongInfo(playlist[0]);
            }
        } else {
            throw new Error("Playlist data is not in expected format.");
        }
    } catch (error) {
        console.error("Error fetching playlist:", error);
        showNotification(error.message || "Network error loading playlist.", true);
        playlist = []; // Ensure playlist is empty on error
    } finally {
        updatePlaylistDisplay(); // Always update display, even if it's to show "empty"
    }
}

function updatePlaylistDisplay() {
    if (!playlistElement) return;
    if (playlist.length === 0) {
        playlistElement.innerHTML = `
            <li class="text-center py-10 text-white/50">
                <i class="fas fa-compact-disc text-3xl mb-2 fa-spin"></i>
                <p>Playlist is empty. Upload some tunes!</p>
            </li>`;
        // Reset player UI if playlist becomes empty
        resetPlayerUI();
        return;
    }

    playlistElement.innerHTML = playlist.map((song, index) => `
        <li class="song-item bg-white/5 p-3 rounded-lg cursor-pointer hover:bg-white/10 transition-all ${currentSongIndex === index ? 'current-song' : ''}"
             data-index="${index}">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-md overflow-hidden ${song.cover ? '' : 'default-cover flex items-center justify-center'}">
                    ${song.cover ?
                        `<img src="${song.cover}" alt="Cover for ${song.title}" class="w-full h-full object-cover">` :
                        `<i class="fas fa-music text-xl"></i>`}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate" title="${song.title}">${song.title}</p>
                    <p class="text-sm text-white/70 truncate" title="${song.artist || 'Unknown Artist'}">${song.artist || 'Unknown Artist'}</p>
                </div>
                ${/* Placeholder for duration if available, not provided by backend currently
                   <span class="text-xs text-white/50">${formatTime(song.duration || 0)}</span>
                */''}
            </div>
        </li>
    `).join('');

    // Re-attach listeners to newly created playlist items
    document.querySelectorAll('.song-item').forEach(item => {
        item.addEventListener('click', () => playSong(parseInt(item.dataset.index)));
    });
}


function displaySongInfo(song) {
    if (!song) {
        resetPlayerUI();
        return;
    }
    songTitle.textContent = song.title;
    artist.textContent = song.artist || 'Unknown Artist';

    if (song.title.length > 25) { // Simple check for marquee
        songTitle.innerHTML = `<span class="marquee">${song.title}</span>`;
    }

    if (song.cover) {
        coverArt.innerHTML = `<img src="${song.cover}" alt="Cover for ${song.title}" class="w-full h-full object-cover">`;
    } else {
        coverArt.innerHTML = `<div class="w-full h-full default-cover flex items-center justify-center"><i class="fas fa-music text-5xl"></i></div>`;
    }
    lyricsText.textContent = song.lyrics || "No lyrics available for this song.";
    updateMediaSession(song);
}

function resetPlayerUI() {
    songTitle.textContent = "Select a song";
    artist.textContent = "-";
    coverArt.innerHTML = `<div class="w-full h-full default-cover flex items-center justify-center"><i class="fas fa-music text-5xl"></i></div>`;
    lyricsText.textContent = "No lyrics available.";
    currentTimeDisplay.textContent = "0:00";
    durationDisplay.textContent = "0:00";
    progress.style.width = '0%';
    if (playBtn) playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
    if (waveform) waveform.classList.add('hidden');
    if (waveform) waveform.classList.remove('playing');
    if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-music"></i>`;
}


// --- Audio Playback ---
async function playSong(index) {
    if (index < 0 || index >= playlist.length) {
        console.warn("Invalid song index:", index);
        return;
    }
    if (!audioContext && document.body.dispatchEvent) { // Try to init audio context if not done
        document.body.dispatchEvent(new Event('click', {bubbles:true, cancelable:true}));
    }

    currentSongIndex = index;
    const song = playlist[index];

    displaySongInfo(song);

    // Highlight current song in playlist
    document.querySelectorAll('.song-item').forEach((item, i) => {
        item.classList.toggle('current-song', i === index);
    });

    audio.src = song.file; // Ensure song.file is a web-accessible path
    audio.load();
    try {
        await audio.play();
        state.isPlaying = true;
        if (playBtn) playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
        if (waveform) waveform.classList.remove('hidden');
        if (waveform) waveform.classList.add('playing'); // Controls animation state via CSS
        if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-pause"></i>`;
        showNotification(`Now playing: ${song.title}`);
    } catch (error) {
        console.error("Play error:", error);
        state.isPlaying = false;
        if (playBtn) playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
        if (waveform) waveform.classList.remove('playing');
        if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-play"></i>`;
        showNotification(`Error playing ${song.title}. ${error.message}`, true);
    }
}

async function togglePlayPause() {
    if (playlist.length === 0) {
        showNotification('Playlist is empty. Upload some music!', true);
        return;
    }
    if (currentSongIndex === -1) { // No song selected, play the first one
        await playSong(0);
        return;
    }
    if (audio.paused) {
        try {
            await audio.play();
        } catch (error) {
            console.error("Play error:", error);
            showNotification(`Playback error: ${error.message}`, true);
        }
    } else {
        audio.pause();
    }
}

function prevSong() {
    if (playlist.length === 0) return;
    let newIndex = currentSongIndex - 1;
    if (newIndex < 0) newIndex = playlist.length - 1; // Loop to last song
    playSong(newIndex);
}

function nextSong(forceNext = false) { // forceNext ignores repeat current song
    if (playlist.length === 0) return;

    if (state.isRepeating === true && !forceNext && currentSongIndex !== -1) { // Repeat current song
        audio.currentTime = 0;
        audio.play().catch(e => console.error("Repeat play error:", e));
        return;
    }

    let newIndex;
    if (state.isShuffled) {
        // Play a random song, different from current one, if possible
        if (playlist.length <= 1) {
            newIndex = 0;
        } else {
            do {
                newIndex = Math.floor(Math.random() * playlist.length);
            } while (newIndex === currentSongIndex);
        }
    } else {
        newIndex = currentSongIndex + 1;
    }

    if (newIndex >= playlist.length) {
        if (state.isRepeating === 'all') { // Repeat playlist
            newIndex = 0;
        } else { // End of playlist, no repeat all
            state.isPlaying = false;
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
            if (waveform) waveform.classList.remove('playing');
            if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-play"></i>`;
            // Optionally, reset to first song info without playing
            // currentSongIndex = 0; displaySongInfo(playlist[0]); audio.currentTime = 0;
            showNotification("End of playlist.");
            return; // Stop here
        }
    }
    playSong(newIndex);
}


// --- UI Controls ---
function toggleShuffle() {
    state.isShuffled = !state.isShuffled;
    shuffleBtn.classList.toggle('text-[var(--color-primary)]', state.isShuffled); // Use a distinct color
    shuffleBtn.classList.toggle('text-white/50', !state.isShuffled);
    showNotification(state.isShuffled ? 'Shuffle On' : 'Shuffle Off');
    // Note: True shuffle behavior (reordering playlist or picking random next) is in nextSong()
}

function toggleRepeat() {
    if (state.isRepeating === false) { // Off -> Repeat Current
        state.isRepeating = true;
        repeatBtn.innerHTML = '<i class="fas fa-redo-alt text-lg"></i>'; // Icon for repeat one
        repeatBtn.classList.add('text-[var(--color-primary)]');
        repeatBtn.classList.remove('text-white/50');
        showNotification('Repeat Current Song');
    } else if (state.isRepeating === true) { // Repeat Current -> Repeat All
        state.isRepeating = 'all';
        repeatBtn.innerHTML = '<i class="fas fa-infinity text-lg"></i>'; // Or some "repeat all" icon
        // Keep color primary for "repeat all"
        showNotification('Repeat Playlist');
    } else { // Repeat All -> Off
        state.isRepeating = false;
        repeatBtn.innerHTML = '<i class="fas fa-redo text-lg"></i>'; // Original icon
        repeatBtn.classList.remove('text-[var(--color-primary)]');
        repeatBtn.classList.add('text-white/50');
        showNotification('Repeat Off');
    }
}


function updateTimeDisplay() {
    if (isNaN(audio.duration) || state.isDraggingProgress) return;
    const progressPercent = (audio.currentTime / audio.duration) * 100;
    if (progress) progress.style.width = `${progressPercent}%`;
    if (currentTimeDisplay) currentTimeDisplay.textContent = formatTime(audio.currentTime);
    if (durationDisplay && !isNaN(audio.duration)) durationDisplay.textContent = formatTime(audio.duration);
}

// --- Visualizer ---
function createWaveformBars() {
    if (!waveform) return;
    waveform.innerHTML = ''; // Clear existing bars
    waveformBars = [];
    const numBars = analyser ? analyser.frequencyBinCount : 16; // Match analyser.fftSize / 2
    for (let i = 0; i < numBars; i++) {
        const bar = document.createElement('div');
        bar.className = 'waveform-bar';
        // Stagger animation for visual effect even when paused
        bar.style.animationDelay = `${Math.random() * 500}ms`;
        waveform.appendChild(bar);
        waveformBars.push(bar);
    }
}

function visualizeWaveform() {
    if (!state.isPlaying || !analyser || waveformBars.length === 0) {
        // If paused or analyser not ready, keep requesting frame but don't update bars heights from data
        // Or, could set bars to a low, pulsing state.
        // For now, CSS handles paused animation state.
        requestAnimationFrame(visualizeWaveform);
        return;
    }
    analyser.getByteFrequencyData(dataArray);
    waveformBars.forEach((bar, i) => {
        const value = dataArray[i] / 255; // Normalize
        const height = Math.max(10, value * 100); // % height, min 10%
        bar.style.height = `${height}%`;
    });
    requestAnimationFrame(visualizeWaveform);
}


// --- Media Session ---
function setupMediaSession() {
    navigator.mediaSession.setActionHandler('play', togglePlayPause);
    navigator.mediaSession.setActionHandler('pause', togglePlayPause);
    navigator.mediaSession.setActionHandler('previoustrack', prevSong);
    navigator.mediaSession.setActionHandler('nexttrack', () => nextSong(true)); // forceNext=true for media session
    // Add more handlers if needed: stop, seekbackward, seekforward
}

function updateMediaSession(song) {
    if (!('mediaSession' in navigator) || !song) return;
    navigator.mediaSession.metadata = new MediaMetadata({
        title: song.title,
        artist: song.artist || 'Unknown Artist',
        album: 'Neon Wave Music Player', // Optional: add album if you have that data
        artwork: song.cover ? [{ src: song.cover, sizes: '512x512', type: 'image/jpeg' }] : [] // Provide various sizes if available
    });
}

// --- Upload Modal & Form ---
function showUploadModal(show = true) {
    if (uploadModal) uploadModal.style.display = show ? 'flex' : 'none';
    if (!show) { // Reset form if hiding
        uploadForm.reset();
        if(coverPreview) coverPreview.classList.add('hidden');
        if(coverFileName) coverFileName.textContent = 'Choose cover image';
        if(songFileName) songFileName.textContent = 'Choose MP3 file';
    }
}

async function handleUploadFormSubmit(e) {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const originalBtnContent = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-2"></div> Uploading...';
    submitBtn.disabled = true;

    const formData = new FormData(e.target);
    try {
        const response = await fetch(`${API_ENDPOINT}?action=uploadSong`, {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        if (response.ok && result.success) {
            if (result.song) { // If backend sends back the new song object
                playlist.unshift(result.song); // Add to beginning of playlist
                updatePlaylistDisplay();
                 // If this is the first song, display its info
                if (playlist.length === 1 && currentSongIndex === -1) {
                    displaySongInfo(result.song);
                }
            } else { // Fallback: just refetch the whole playlist
                await fetchPlaylist();
                updatePlaylistDisplay();
            }
            showUploadModal(false);
            showNotification(result.success || 'Song uploaded successfully!');
        } else {
            showNotification(result.error || 'Upload failed. Please try again.', true);
        }
    } catch (error) {
        console.error("Upload error:", error);
        showNotification('Network error during upload. Check console.', true);
    } finally {
        submitBtn.innerHTML = originalBtnContent;
        submitBtn.disabled = false;
    }
}

// --- Lyrics Modal ---
function showLyricsModal(show = true) {
    if (lyricsModal) lyricsModal.style.display = show ? 'flex' : 'none';
}

// --- Notifications ---
function showNotification(message, isError = false) {
    if (!toastElement) return;
    toastElement.textContent = message;
    toastElement.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all duration-300`; // Base classes
    toastElement.classList.add(isError ? 'bg-red-600' : 'bg-green-600', 'text-white', 'show');

    clearTimeout(toastElement.timeoutId);
    toastElement.timeoutId = setTimeout(() => {
        toastElement.classList.remove('show');
        // Allow fade out animation by delaying removal of visibility related classes
        setTimeout(() => {
            if (!toastElement.classList.contains('show')) { // check if another toast hasn't taken over
                 toastElement.className = 'fixed bottom-4 right-4 p-4 rounded-lg shadow-lg hidden z-50';
            }
        }, 500);
    }, 3000);
}

// --- Utility Functions ---
function formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

// --- Event Listeners Setup ---
function attachEventListeners() {
    if (playBtn) playBtn.addEventListener('click', togglePlayPause);
    if (prevBtn) prevBtn.addEventListener('click', prevSong);
    if (nextBtn) nextBtn.addEventListener('click', () => nextSong(true)); // forceNext=true for UI button
    if (shuffleBtn) shuffleBtn.addEventListener('click', toggleShuffle);
    if (repeatBtn) repeatBtn.addEventListener('click', toggleRepeat);

    if (progressBar) {
        progressBar.addEventListener('click', (e) => {
            if (isNaN(audio.duration)) return;
            const rect = progressBar.getBoundingClientRect();
            const pos = (e.clientX - rect.left) / rect.width;
            audio.currentTime = pos * audio.duration;
        });
        // Drag handling for progress bar
        let isDragging = false;
        progressBar.addEventListener('mousedown', (e) => {
            if (isNaN(audio.duration)) return;
            isDragging = true;
            state.isDraggingProgress = true; // Signal to updateTimeDisplay
            updateProgressFromEvent(e);
        });
        document.addEventListener('mousemove', (e) => {
            if (isDragging && !isNaN(audio.duration)) {
                updateProgressFromEvent(e);
            }
        });
        document.addEventListener('mouseup', (e) => {
            if (isDragging && !isNaN(audio.duration)) {
                isDragging = false;
                state.isDraggingProgress = false;
                updateProgressFromEvent(e); // Set final position
                audio.currentTime = (parseFloat(progress.style.width) / 100) * audio.duration;
            }
        });
        function updateProgressFromEvent(e) {
            const rect = progressBar.getBoundingClientRect();
            let pos = (e.clientX - rect.left) / rect.width;
            pos = Math.max(0, Math.min(1, pos)); // Clamp between 0 and 1
            progress.style.width = `${pos * 100}%`;
            currentTimeDisplay.textContent = formatTime(pos * audio.duration);
        }
    }

    if (audio) {
        audio.addEventListener('timeupdate', updateTimeDisplay);
        audio.addEventListener('ended', () => nextSong(false)); // Let repeat logic handle it
        audio.addEventListener('play', () => {
            state.isPlaying = true;
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
            if (waveform) { waveform.classList.remove('hidden'); waveform.classList.add('playing'); }
            if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-pause"></i>`;
            if (audioContext && audioContext.state === 'suspended') {
                audioContext.resume();
            }
            visualizeWaveform(); // Ensure visualizer runs
        });
        audio.addEventListener('pause', () => {
            state.isPlaying = false;
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
            if (waveform) waveform.classList.remove('playing');
            if (miniPlayer) miniPlayer.innerHTML = `<i class="fas fa-play"></i>`;
        });
        audio.addEventListener('loadedmetadata', () => { // Update duration when metadata loads
            if (durationDisplay && !isNaN(audio.duration)) {
                durationDisplay.textContent = formatTime(audio.duration);
            }
        });
         audio.addEventListener('error', (e) => {
            console.error("Audio Error:", e, audio.error);
            let errorMsg = "An unknown audio error occurred.";
            if (audio.error) {
                switch (audio.error.code) {
                    case MediaError.MEDIA_ERR_ABORTED: errorMsg = "Playback aborted by user."; break;
                    case MediaError.MEDIA_ERR_NETWORK: errorMsg = "Network error during audio playback."; break;
                    case MediaError.MEDIA_ERR_DECODE: errorMsg = "Audio decoding error. File might be corrupt."; break;
                    case MediaError.MEDIA_ERR_SRC_NOT_SUPPORTED: errorMsg = "Audio format not supported or source unavailable."; break;
                }
            }
            showNotification(errorMsg, true);
            // Try to play next song or stop
            if (currentSongIndex !== -1 && playlist[currentSongIndex]) {
                 showNotification(`Skipping problematic track: ${playlist[currentSongIndex].title}`, true);
            }
            setTimeout(() => nextSong(true), 1000); // Try next song after a short delay
        });
    }

    if (volumeSlider) {
        volumeSlider.addEventListener('input', (e) => {
            audio.volume = parseFloat(e.target.value);
            state.volume = audio.volume;
        });
    }

    if (lyricsBtn) lyricsBtn.addEventListener('click', () => showLyricsModal(true));
    if (closeLyricsBtn) closeLyricsBtn.addEventListener('click', () => showLyricsModal(false));

    if (uploadBtn) uploadBtn.addEventListener('click', () => showUploadModal(true));
    if (cancelBtn) cancelBtn.addEventListener('click', () => showUploadModal(false));
    if (cancelUploadBtn) cancelUploadBtn.addEventListener('click', () => showUploadModal(false));

    if (uploadForm) uploadForm.addEventListener('submit', handleUploadFormSubmit);

    if (coverInput) {
        coverInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && coverFileName && coverPreview && coverPreviewImg) {
                coverFileName.textContent = file.name.length > 20 ? file.name.substring(0,17) + "..." : file.name;
                coverPreview.classList.remove('hidden');
                const reader = new FileReader();
                reader.onload = (event) => { coverPreviewImg.src = event.target.result; };
                reader.readAsDataURL(file);
            } else if (coverFileName) {
                coverFileName.textContent = 'Choose cover image';
                if(coverPreview) coverPreview.classList.add('hidden');
            }
        });
    }
    if (songInput) {
        songInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file && songFileName) {
                 songFileName.textContent = file.name.length > 20 ? file.name.substring(0,17) + "..." : file.name;
            } else if (songFileName) {
                songFileName.textContent = 'Choose MP3 file';
            }
        });
    }

    if (refreshBtn) {
        refreshBtn.addEventListener('click', async () => {
            const originalIcon = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-sync-alt animate-spin"></i>';
            await fetchPlaylist();
            updatePlaylistDisplay(); // This will also reset player if list is empty
            // If a song was playing, try to find it in the new playlist and resume (optional)
            // For simplicity, we'll just refresh. User can re-select.
            refreshBtn.innerHTML = originalIcon;
            showNotification('Playlist refreshed');
        });
    }
    if (miniPlayer) {
         miniPlayer.addEventListener('click', () => {
            const playerSection = document.querySelector('.player-container');
            if (playerSection) {
                playerSection.scrollIntoView({ behavior: 'smooth' });
            }
        });
    }
}

// Expose functions to global scope if needed for inline HTML handlers (though data-attributes are preferred)
// window.playSong = playSong; // Example, if you had <li onclick="playSong(${index})">
// Using data-attributes and attaching listeners in JS (as done in updatePlaylistDisplay) is cleaner.
