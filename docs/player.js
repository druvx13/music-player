// docs/player.js — static / GitHub Pages build
// Songs are stored in the browser's IndexedDB — no server required.

// ── IndexedDB helpers ─────────────────────────────────────────────────────────
const DB_NAME    = 'NeonWaveMusicPlayer';
const DB_VERSION = 1;
const STORE_NAME = 'songs';

function openDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, DB_VERSION);
        req.onupgradeneeded = e => {
            const db = e.target.result;
            if (!db.objectStoreNames.contains(STORE_NAME)) {
                db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
            }
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

async function getAllSongsFromDB() {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const req = db.transaction(STORE_NAME, 'readonly')
                      .objectStore(STORE_NAME).getAll();
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}

async function addSongToDB(record) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const req = db.transaction(STORE_NAME, 'readwrite')
                      .objectStore(STORE_NAME).add(record);
        req.onsuccess = e => resolve(e.target.result); // returns generated id
        req.onerror   = e => reject(e.target.error);
    });
}

async function deleteSongFromDB(id) {
    const db = await openDB();
    return new Promise((resolve, reject) => {
        const req = db.transaction(STORE_NAME, 'readwrite')
                      .objectStore(STORE_NAME).delete(id);
        req.onsuccess = () => resolve();
        req.onerror   = e => reject(e.target.error);
    });
}

// ── Player globals ────────────────────────────────────────────────────────────
const audio = new Audio();
let currentSongIndex = -1;
let playlist = [];
let audioContext;
let analyser;
let dataArray;
let waveformBars = [];

const state = {
    isPlaying:        false,
    isShuffled:       false,
    isRepeating:      false,
    volume:           0.7,
    isDraggingProgress: false
};

// ── DOM references ────────────────────────────────────────────────────────────
const playBtn         = document.getElementById('playBtn');
const prevBtn         = document.getElementById('prevBtn');
const nextBtn         = document.getElementById('nextBtn');
const progress        = document.getElementById('progress');
const progressBar     = document.getElementById('progressBar');
const currentTimeDisp = document.getElementById('currentTime');
const durationDisp    = document.getElementById('duration');
const songTitle       = document.getElementById('songTitle');
const artistEl        = document.getElementById('artist');
const coverArt        = document.getElementById('coverArt');
const waveform        = document.getElementById('waveform');
const volumeSlider    = document.getElementById('volumeSlider');
const shuffleBtn      = document.getElementById('shuffleBtn');
const repeatBtn       = document.getElementById('repeatBtn');
const miniPlayer      = document.getElementById('miniPlayer');
const lyricsBtn       = document.getElementById('lyricsBtn');
const lyricsModal     = document.getElementById('lyricsModal');
const lyricsText      = document.getElementById('lyricsText');
const closeLyricsBtn  = document.getElementById('closeLyricsBtn');
const playlistElement = document.getElementById('playlist');
const refreshBtn      = document.getElementById('refreshBtn');
const uploadModal     = document.getElementById('uploadModal');
const uploadBtn       = document.getElementById('uploadBtn');
const cancelBtn       = document.getElementById('cancelBtn');
const cancelUploadBtn = document.getElementById('cancelUploadBtn');
const uploadForm      = document.getElementById('uploadForm');

// ── Initialisation ────────────────────────────────────────────────────────────
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

// ── Playlist loading (from IndexedDB) ─────────────────────────────────────────
async function fetchPlaylist() {
    playlistElement.innerHTML = `
        <li class="text-center py-10">
            <div class="spinner mx-auto mb-2"></div>
            <p>Loading library…</p>
        </li>`;
    try {
        const rows = await getAllSongsFromDB();
        // newest first
        rows.sort((a, b) => new Date(b.uploadedAt) - new Date(a.uploadedAt));
        playlist = rows.map(row => ({
            id:     row.id,
            title:  row.title,
            artist: row.artist,
            lyrics: row.lyrics || '',
            file:   URL.createObjectURL(row.audioBlob),
            cover:  row.coverBlob ? URL.createObjectURL(row.coverBlob) : null,
        }));
    } catch (err) {
        console.error('Error loading library:', err);
        showNotification('Error loading library', true);
        playlist = [];
    }
}

// ── Playlist display ──────────────────────────────────────────────────────────
function updatePlaylistDisplay() {
    if (playlist.length === 0) {
        playlistElement.innerHTML = `
            <li class="text-center py-10 text-white/50">
                <i class="fas fa-music text-3xl mb-2" aria-hidden="true"></i>
                <p>No songs yet. Click <strong>Upload</strong> to add songs from your device.</p>
            </li>`;
        return;
    }

    playlistElement.innerHTML = playlist.map((song, index) => `
        <li class="song-item bg-white/5 p-3 rounded-lg cursor-pointer hover:bg-white/10 transition-all ${currentSongIndex === index ? 'current-song' : ''}"
             data-index="${index}">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-md overflow-hidden flex-shrink-0 ${song.cover ? '' : 'default-cover'}">
                    ${song.cover
                        ? `<img src="${escHtml(song.cover)}" class="w-full h-full object-cover" loading="lazy" alt="">`
                        : `<i class="fas fa-music w-full h-full flex items-center justify-center" aria-hidden="true"></i>`}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-medium truncate">${escHtml(song.title)}</p>
                    <p class="text-sm text-white/70 truncate">${escHtml(song.artist)}</p>
                </div>
                <button class="delete-btn text-white/30 hover:text-red-400 transition-colors px-2 py-1 rounded"
                        data-id="${song.id}" data-index="${index}"
                        aria-label="Remove ${escHtml(song.title)} from library"
                        title="Remove from library">
                    <i class="fas fa-trash-can text-sm" aria-hidden="true"></i>
                </button>
            </div>
        </li>`
    ).join('');

    // Event delegation — avoids inline onclick and XSS risk
    playlistElement.querySelectorAll('.song-item').forEach(item => {
        item.addEventListener('click', e => {
            if (e.target.closest('.delete-btn')) return; // handled separately
            playSong(Number(item.dataset.index));
        });
    });

    playlistElement.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation();
            removeSong(Number(btn.dataset.id), Number(btn.dataset.index));
        });
    });
}

// ── Remove a song from the library ───────────────────────────────────────────
async function removeSong(dbId, index) {
    try {
        await deleteSongFromDB(dbId);

        const song = playlist[index];
        if (song?.file)  URL.revokeObjectURL(song.file);
        if (song?.cover) URL.revokeObjectURL(song.cover);
        playlist.splice(index, 1);

        if (currentSongIndex === index) {
            // The playing song was removed — stop playback and reset UI
            audio.pause();
            audio.src = '';
            currentSongIndex = -1;
            songTitle.textContent  = 'Select a song';
            artistEl.textContent   = '—';
            lyricsText.textContent = 'No lyrics available for this song.';
            coverArt.innerHTML     = '<i class="fas fa-music text-5xl" aria-hidden="true"></i>';
            progress.style.width   = '0';
            currentTimeDisp.textContent = '0:00';
            durationDisp.textContent    = '0:00';
        } else if (currentSongIndex > index) {
            currentSongIndex--;
        }

        updatePlaylistDisplay();
        showNotification('Song removed from library');
    } catch (err) {
        console.error('Delete error:', err);
        showNotification('Error removing song', true);
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
/** Escape untrusted HTML to prevent XSS when inserting into innerHTML */
function escHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

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

// ── Media Session ─────────────────────────────────────────────────────────────
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

// ── Playback ──────────────────────────────────────────────────────────────────
async function playSong(index) {
    if (index < 0 || index >= playlist.length) return;
    currentSongIndex = index;
    const song = playlist[index];

    if (song.title.length > 20) {
        songTitle.innerHTML = `<span class="marquee">${escHtml(song.title)}</span>`;
    } else {
        songTitle.textContent = song.title;
    }
    artistEl.textContent   = song.artist;
    lyricsText.textContent = song.lyrics || 'No lyrics available for this song.';

    coverArt.innerHTML = song.cover
        ? `<img src="${escHtml(song.cover)}" class="w-full h-full object-cover" alt="">`
        : `<i class="fas fa-music text-5xl" aria-hidden="true"></i>`;

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
    if (playlist.length === 0) { showNotification('No songs in library', true); return; }
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

// ── Shuffle / Repeat ──────────────────────────────────────────────────────────
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

// ── Progress bar (mouse + touch) ──────────────────────────────────────────────
function seekTo(clientX) {
    if (isNaN(audio.duration)) return;
    const rect = progressBar.getBoundingClientRect();
    const pos  = Math.min(Math.max((clientX - rect.left) / rect.width, 0), 1);
    audio.currentTime = pos * audio.duration;
}

function updateTimeDisplay() {
    if (isNaN(audio.duration) || state.isDraggingProgress) return;
    progress.style.width        = `${(audio.currentTime / audio.duration) * 100}%`;
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
    const rect = progressBar.getBoundingClientRect();
    seekTo(parseFloat(progress.style.width) / 100 * rect.width + rect.left);
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

// ── Visualizer ────────────────────────────────────────────────────────────────
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

// ── Toast notification ────────────────────────────────────────────────────────
function showNotification(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className   = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg ${
        isError ? 'bg-red-500' : 'bg-green-500'
    } text-white show`;
    clearTimeout(toast._timeout);
    toast._timeout = setTimeout(() => toast.classList.remove('show'), 3000);
}

// ── Audio events ──────────────────────────────────────────────────────────────
audio.addEventListener('timeupdate', updateTimeDisplay);
audio.addEventListener('ended', () => {
    if (state.isRepeating) { audio.currentTime = 0; audio.play(); }
    else                     nextSong();
});
audio.addEventListener('play', () => {
    playBtn.innerHTML = '<i class="fas fa-pause text-2xl" aria-hidden="true"></i>';
    state.isPlaying   = true;
    waveform.classList.add('playing');
    miniPlayer.innerHTML = '<i class="fas fa-pause" aria-hidden="true"></i>';
});
audio.addEventListener('pause', () => {
    playBtn.innerHTML = '<i class="fas fa-play text-2xl" aria-hidden="true"></i>';
    state.isPlaying   = false;
    waveform.classList.remove('playing');
    miniPlayer.innerHTML = '<i class="fas fa-play" aria-hidden="true"></i>';
});

// ── Control buttons ───────────────────────────────────────────────────────────
playBtn.addEventListener('click',    togglePlayPause);
prevBtn.addEventListener('click',    prevSong);
nextBtn.addEventListener('click',    nextSong);
shuffleBtn.addEventListener('click', toggleShuffle);
repeatBtn.addEventListener('click',  toggleRepeat);
volumeSlider.addEventListener('input', e => {
    audio.volume = state.volume = parseFloat(e.target.value);
});

// ── Lyrics modal ──────────────────────────────────────────────────────────────
lyricsBtn.addEventListener('click',      () => { lyricsModal.style.display = 'flex'; });
closeLyricsBtn.addEventListener('click', () => { lyricsModal.style.display = 'none'; });
lyricsModal.addEventListener('click', e => {
    if (e.target === lyricsModal) lyricsModal.style.display = 'none';
});

// ── Upload modal ──────────────────────────────────────────────────────────────
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
    // Pre-fill title from filename if the field is empty
    const titleInput = document.getElementById('titleInput');
    if (!titleInput.value) {
        titleInput.value = file.name.replace(/\.[^.]+$/, '');
    }
});

// ── Upload form (saves to IndexedDB instead of a remote server) ───────────────
uploadForm.addEventListener('submit', async e => {
    e.preventDefault();
    const submitBtn = e.target.querySelector('button[type="submit"]');
    const origHtml  = submitBtn.innerHTML;
    submitBtn.innerHTML = '<div class="spinner mr-2 inline-block"></div> Saving…';
    submitBtn.disabled  = true;

    try {
        const titleVal  = document.getElementById('titleInput').value.trim();
        const artistVal = document.getElementById('artistInput').value.trim();
        const lyricsVal = document.getElementById('lyricsInput').value.trim();
        const songFile  = document.getElementById('songInput').files[0];
        const coverFile = document.getElementById('coverInput').files[0] || null;

        if (!songFile) {
            showNotification('Please select an MP3 file', true);
            return;
        }

        const record = {
            title:      titleVal  || songFile.name.replace(/\.[^.]+$/, ''),
            artist:     artistVal || 'Unknown Artist',
            lyrics:     lyricsVal,
            audioBlob:  songFile,
            coverBlob:  coverFile,
            uploadedAt: new Date().toISOString(),
        };

        const newId = await addSongToDB(record);

        // Build an in-memory song entry with blob object URLs for immediate playback
        const newSong = {
            id:     newId,
            title:  record.title,
            artist: record.artist,
            lyrics: record.lyrics,
            file:   URL.createObjectURL(songFile),
            cover:  coverFile ? URL.createObjectURL(coverFile) : null,
        };

        playlist.push(newSong);
        updatePlaylistDisplay();
        uploadModal.style.display = 'none';
        uploadForm.reset();
        document.getElementById('coverPreview').classList.add('hidden');
        document.getElementById('coverFileName').textContent = 'Choose cover image';
        document.getElementById('songFileName').textContent  = 'Choose MP3 file';
        showNotification(`'${record.title}' added to library!`);
    } catch (err) {
        console.error('Save error:', err);
        showNotification('Error saving song to library', true);
    } finally {
        submitBtn.innerHTML = origHtml;
        submitBtn.disabled  = false;
    }
});

// ── Refresh library ───────────────────────────────────────────────────────────
refreshBtn.addEventListener('click', async () => {
    refreshBtn.innerHTML = '<i class="fas fa-arrows-rotate animate-spin" aria-hidden="true"></i>';
    await fetchPlaylist();
    updatePlaylistDisplay();
    refreshBtn.innerHTML = '<i class="fas fa-arrows-rotate" aria-hidden="true"></i>';
    showNotification('Library refreshed');
});

// ── Bootstrap ─────────────────────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', init);
