// assets/js/player.js

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
    isDraggingProgress: false
};

// ── DOM references ────────────────────────────────────────────────────────
const playBtn          = document.getElementById('playBtn');
const prevBtn          = document.getElementById('prevBtn');
const nextBtn          = document.getElementById('nextBtn');
const progress         = document.getElementById('progress');
const progressBar      = document.getElementById('progressBar');
const currentTimeDisp  = document.getElementById('currentTime');
const durationDisp     = document.getElementById('duration');
const songTitle        = document.getElementById('songTitle');
const artistEl         = document.getElementById('artist');
const coverArt         = document.getElementById('coverArt');
const waveform         = document.getElementById('waveform');
const volumeSlider     = document.getElementById('volumeSlider');
const shuffleBtn       = document.getElementById('shuffleBtn');
const repeatBtn        = document.getElementById('repeatBtn');
const miniPlayer       = document.getElementById('miniPlayer');
const lyricsBtn        = document.getElementById('lyricsBtn');
const lyricsModal      = document.getElementById('lyricsModal');
const lyricsText       = document.getElementById('lyricsText');
const closeLyricsBtn   = document.getElementById('closeLyricsBtn');
const playlistElement  = document.getElementById('playlist');
const refreshBtn       = document.getElementById('refreshBtn');
const uploadModal      = document.getElementById('uploadModal');
const uploadBtn        = document.getElementById('uploadBtn');
const cancelBtn        = document.getElementById('cancelBtn');
const cancelUploadBtn  = document.getElementById('cancelUploadBtn');
const uploadForm       = document.getElementById('uploadForm');

// ── Initialisation ────────────────────────────────────────────────────────
async function init() {
    await fetchPlaylist();
    updatePlaylistDisplay();
    createWaveformBars();

    // AudioContext must be created after a user gesture
    document.addEventListener('click', function initAudio() {
        try {
            const AC = window.AudioContext || window.webkitAudioContext;
            audioContext = new AC();
            analyser = audioContext.createAnalyser();
            analyser.fftSize = 64;
            dataArray = new Uint8Array(analyser.frequencyBinCount);
            const source = audioContext.createMediaElementSource(audio);
            source.connect(analyser);
            analyser.connect(audioContext.destination);
            visualize();
        } catch (e) {
            console.error('AudioContext error:', e);
        }
    }, { once: true });

    audio.volume = state.volume;

    if ('mediaSession' in navigator) {
        miniPlayer.classList.remove('hidden');
        setupMediaSession();
    }
}

// ── Playlist fetching ─────────────────────────────────────────────────────
async function fetchPlaylist() {
    playlistElement.innerHTML = `
        <li class="text-center py-10">
            <div class="spinner mx-auto mb-2"></div>
            <p>Loading playlist…</p>
        </li>`;
    try {
        const response = await fetch('api/playlist.php');
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        playlist = Array.isArray(data) ? data : [];
        if (!Array.isArray(data)) showNotification('Failed to load playlist', true);
    } catch (err) {
        console.error('Error fetching playlist:', err);
        showNotification('Network error loading playlist', true);
        playlist = [];
    }
}

// ── Playlist display ──────────────────────────────────────────────────────
function updatePlaylistDisplay() {
    if (playlist.length === 0) {
        playlistElement.innerHTML = `
            <li class="text-center py-10 text-white/50">
                <i class="fas fa-music text-3xl mb-2"></i>
                <p>No songs in playlist</p>
            </li>`;
        return;
    }
    playlistElement.innerHTML = playlist.map((song, index) => `
        <li class="song-item bg-white/5 p-3 rounded-lg cursor-pointer hover:bg-white/10 transition-all ${currentSongIndex === index ? 'current-song' : ''}"
             data-index="${index}">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-md overflow-hidden flex-shrink-0 ${song.cover ? '' : 'default-cover'}">
                    ${song.cover
                        ? `<img src="${escHtml(song.cover)}" class="w-full h-full object-cover" loading="lazy">`
                        : `<i class="fas fa-music w-full h-full flex items-center justify-center"></i>`}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">${escHtml(song.title)}</p>
                    <p class="text-sm text-white/70 truncate">${escHtml(song.artist)}</p>
                </div>
            </div>
        </li>`
    ).join('');

    // Use event delegation — avoids inline onclick and XSS risk
    playlistElement.querySelectorAll('.song-item').forEach(item => {
        item.addEventListener('click', () => playSong(Number(item.dataset.index)));
    });
}

/** Escape untrusted HTML to prevent XSS when inserting into innerHTML */
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ── Helpers ───────────────────────────────────────────────────────────────
function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
}

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

// ── Media Session ─────────────────────────────────────────────────────────
function setupMediaSession() {
    navigator.mediaSession.setActionHandler('play',          togglePlayPause);
    navigator.mediaSession.setActionHandler('pause',         togglePlayPause);
    navigator.mediaSession.setActionHandler('previoustrack', prevSong);
    navigator.mediaSession.setActionHandler('nexttrack',     nextSong);
    miniPlayer.addEventListener('click', () => {
        document.querySelector('.player-container')
            .scrollIntoView({ behavior: 'smooth' });
    });
}

function updateMediaSession(song) {
    if (!('mediaSession' in navigator)) return;
    navigator.mediaSession.metadata = new MediaMetadata({
        title:   song.title,
        artist:  song.artist,
        artwork: song.cover
            ? ['96x96','128x128','192x192','256x256','384x384','512x512'].map(s => ({
                src: song.cover, sizes: s, type: 'image/jpeg'
              }))
            : []
    });
}

// ── Playback ──────────────────────────────────────────────────────────────
async function playSong(index) {
    if (index < 0 || index >= playlist.length) return;
    currentSongIndex = index;
    const song = playlist[index];

    if (song.title.length > 20) {
        songTitle.innerHTML = `<span class="marquee">${escHtml(song.title)}</span>`;
    } else {
        songTitle.textContent = song.title;
    }
    artistEl.textContent = song.artist;
    lyricsText.textContent = song.lyrics || 'No lyrics available for this song.';

    coverArt.innerHTML = song.cover
        ? `<img src="${escHtml(song.cover)}" class="w-full h-full object-cover">`
        : `<i class="fas fa-music text-5xl"></i>`;

    document.querySelectorAll('.song-item').forEach((item, i) => {
        item.classList.toggle('current-song', i === index);
    });

    updateMediaSession(song);
    audio.src = song.file;
    audio.load();

    try {
        await audio.play();
        showNotification(`Now playing: ${song.title}`);
    } catch (err) {
        console.error('Play error:', err);
        showNotification('Click anywhere to play', true);
    }
}

async function togglePlayPause() {
    if (playlist.length === 0) { showNotification('No songs in playlist', true); return; }
    if (currentSongIndex === -1) { await playSong(0); return; }

    if (audio.paused) {
        try {
            await audio.play();
        } catch (err) {
            console.error('Play error:', err);
            showNotification('Click anywhere to play', true);
        }
    } else {
        audio.pause();
    }
}

function prevSong() {
    if (playlist.length === 0) return;
    playSong((currentSongIndex - 1 + playlist.length) % playlist.length);
}

function nextSong() {
    if (playlist.length === 0) return;
    playSong((currentSongIndex + 1) % playlist.length);
}

// ── Shuffle / Repeat ──────────────────────────────────────────────────────
function toggleShuffle() {
    state.isShuffled = !state.isShuffled;
    shuffleBtn.classList.toggle('text-white',    state.isShuffled);
    shuffleBtn.classList.toggle('text-white/50', !state.isShuffled);

    if (state.isShuffled) {
        const current = playlist[currentSongIndex];
        const rest    = playlist.filter((_, i) => i !== currentSongIndex);
        for (let i = rest.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [rest[i], rest[j]] = [rest[j], rest[i]];
        }
        playlist = [current, ...rest];
        currentSongIndex = 0;
        updatePlaylistDisplay();
        showNotification('Playlist shuffled');
    } else {
        fetchPlaylist().then(() => {
            updatePlaylistDisplay();
            showNotification('Shuffle off');
        });
    }
}

function toggleRepeat() {
    state.isRepeating = !state.isRepeating;
    repeatBtn.classList.toggle('text-white',    state.isRepeating);
    repeatBtn.classList.toggle('text-white/50', !state.isRepeating);
    showNotification(state.isRepeating ? 'Repeat on' : 'Repeat off');
}

// ── Progress bar (mouse + touch) ──────────────────────────────────────────
function seekTo(clientX) {
    if (isNaN(audio.duration)) return;
    const rect = progressBar.getBoundingClientRect();
    const pos  = Math.min(Math.max((clientX - rect.left) / rect.width, 0), 1);
    audio.currentTime = pos * audio.duration;
}

function updateTimeDisplay() {
    if (isNaN(audio.duration) || state.isDraggingProgress) return;
    progress.style.width = `${(audio.currentTime / audio.duration) * 100}%`;
    currentTimeDisp.textContent = formatTime(audio.currentTime);
    durationDisp.textContent    = formatTime(audio.duration);
}

// Mouse
progressBar.addEventListener('click', e => seekTo(e.clientX));
progressBar.addEventListener('mousedown', () => { state.isDraggingProgress = true; });
document.addEventListener('mousemove', e => {
    if (!state.isDraggingProgress || isNaN(audio.duration)) return;
    const rect = progressBar.getBoundingClientRect();
    const pos  = Math.min(Math.max((e.clientX - rect.left) / rect.width, 0), 1);
    progress.style.width        = `${pos * 100}%`;
    currentTimeDisp.textContent = formatTime(pos * audio.duration);
});
document.addEventListener('mouseup', () => {
    if (!state.isDraggingProgress) return;
    state.isDraggingProgress = false;
    seekTo(parseFloat(progress.style.width) / 100 * progressBar.getBoundingClientRect().width
           + progressBar.getBoundingClientRect().left);
});

// Touch (mobile scrubbing)
progressBar.addEventListener('touchstart', e => {
    state.isDraggingProgress = true;
    e.preventDefault();
}, { passive: false });
progressBar.addEventListener('touchmove', e => {
    if (!state.isDraggingProgress || isNaN(audio.duration)) return;
    e.preventDefault();
    const touch = e.touches[0];
    const rect  = progressBar.getBoundingClientRect();
    const pos   = Math.min(Math.max((touch.clientX - rect.left) / rect.width, 0), 1);
    progress.style.width        = `${pos * 100}%`;
    currentTimeDisp.textContent = formatTime(pos * audio.duration);
}, { passive: false });
progressBar.addEventListener('touchend', e => {
    if (!state.isDraggingProgress) return;
    state.isDraggingProgress = false;
    const touch = e.changedTouches[0];
    seekTo(touch.clientX);
});

// ── Visualizer ────────────────────────────────────────────────────────────
function visualize() {
    function draw() {
        requestAnimationFrame(draw);
        if (!analyser) return;
        analyser.getByteFrequencyData(dataArray);
        waveformBars.forEach((bar, i) => {
            const value = dataArray[i] / 255;
            bar.style.height = `${10 + value * 50}%`;
        });
    }
    draw();
}

// ── Toast notification ────────────────────────────────────────────────────
function showNotification(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className   = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg ${
        isError ? 'bg-red-500' : 'bg-green-500'
    } text-white show`;
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => toast.classList.remove('show'), 3000);
}

// ── Audio events ──────────────────────────────────────────────────────────
audio.addEventListener('timeupdate', updateTimeDisplay);
audio.addEventListener('ended', () => {
    if (state.isRepeating) { audio.currentTime = 0; audio.play(); }
    else                     nextSong();
});
audio.addEventListener('play', () => {
    playBtn.innerHTML = '<i class="fas fa-pause text-2xl"></i>';
    state.isPlaying = true;
    waveform.classList.add('playing');
    miniPlayer.innerHTML = '<i class="fas fa-pause"></i>';
});
audio.addEventListener('pause', () => {
    playBtn.innerHTML = '<i class="fas fa-play text-2xl"></i>';
    state.isPlaying = false;
    waveform.classList.remove('playing');
    miniPlayer.innerHTML = '<i class="fas fa-play"></i>';
});

// ── Control buttons ───────────────────────────────────────────────────────
playBtn.addEventListener('click',   togglePlayPause);
prevBtn.addEventListener('click',   prevSong);
nextBtn.addEventListener('click',   nextSong);
shuffleBtn.addEventListener('click', toggleShuffle);
repeatBtn.addEventListener('click',  toggleRepeat);
volumeSlider.addEventListener('input', e => {
    audio.volume = state.volume = parseFloat(e.target.value);
});

// ── Lyrics modal ──────────────────────────────────────────────────────────
lyricsBtn.addEventListener('click',      () => { lyricsModal.style.display = 'flex'; });
closeLyricsBtn.addEventListener('click', () => { lyricsModal.style.display = 'none'; });
lyricsModal.addEventListener('click', e => {
    if (e.target === lyricsModal) lyricsModal.style.display = 'none';
});

// ── Upload modal ──────────────────────────────────────────────────────────
uploadBtn.addEventListener('click',       () => { uploadModal.style.display = 'flex'; });
cancelBtn.addEventListener('click',       () => { uploadModal.style.display = 'none'; });
cancelUploadBtn.addEventListener('click', () => { uploadModal.style.display = 'none'; });
uploadModal.addEventListener('click', e => {
    if (e.target === uploadModal) uploadModal.style.display = 'none';
});

document.getElementById('coverInput').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('coverFileName').textContent = file.name;
    document.getElementById('coverPreview').classList.remove('hidden');
    const reader = new FileReader();
    reader.onload = ev => { document.getElementById('coverPreviewImg').src = ev.target.result; };
    reader.readAsDataURL(file);
});

document.getElementById('songInput').addEventListener('change', e => {
    const file = e.target.files[0];
    if (!file) return;
    document.getElementById('songFileName').textContent = file.name;
});

// ── Upload form submission ────────────────────────────────────────────────
uploadForm.addEventListener('submit', async e => {
    e.preventDefault();
    const submitBtn  = e.target.querySelector('button[type="submit"]');
    const origHtml   = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-2 inline-block"></div> Uploading…';
    submitBtn.disabled  = true;

    try {
        const response = await fetch('api/upload.php', {
            method: 'POST',
            body: new FormData(e.target)
        });
        const result = await response.json();

        if (result.success && result.song) {
            playlist.push(result.song);
            updatePlaylistDisplay();
            uploadModal.style.display = 'none';
            uploadForm.reset();
            document.getElementById('coverPreview').classList.add('hidden');
            document.getElementById('coverFileName').textContent = 'Choose cover image';
            document.getElementById('songFileName').textContent  = 'Choose MP3 file';
            showNotification('Song added to playlist!');
        } else {
            showNotification(result.error || 'Upload failed', true);
        }
    } catch (err) {
        console.error('Upload error:', err);
        showNotification('Network error during upload', true);
    } finally {
        submitBtn.innerHTML = origHtml;
        submitBtn.disabled  = false;
    }
});

// ── Refresh playlist ──────────────────────────────────────────────────────
refreshBtn.addEventListener('click', async () => {
    refreshBtn.innerHTML = '<i class="fas fa-arrows-rotate animate-spin"></i>';
    await fetchPlaylist();
    updatePlaylistDisplay();
    refreshBtn.innerHTML = '<i class="fas fa-arrows-rotate"></i>';
    showNotification('Playlist refreshed');
});

// ── Bootstrap ─────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', init);
