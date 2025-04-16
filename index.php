<?php
// index.php
// Database configuration
$host = "localhost";
$db = "db_name";
$user = "user_name";
$pass = "user_pass";

// Create connection using MySQLi
$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// API endpoints
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Get playlist endpoint
    if ($action === 'getPlaylist') {
        header("Content-Type: application/json");
        $sql = "SELECT * FROM songs ORDER BY uploaded_at DESC";
        $result = $conn->query($sql);
        $songs = array();
        while ($row = $result->fetch_assoc()) {
            $songs[] = $row;
        }
        echo json_encode($songs);
        $conn->close();
        exit;
    }
    
    // Upload song endpoint
    if ($action === 'uploadSong') {
        header("Content-Type: application/json");

        // Process song file upload
        if (isset($_FILES['song'])) {
            $uploadDir = 'uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $songName = basename($_FILES['song']['name']);
            $targetSong = $uploadDir . $songName;
            $songType = strtolower(pathinfo($targetSong, PATHINFO_EXTENSION));

            // Validate song file type
            if ($songType !== "mp3") {
                echo json_encode(array("error" => "Only MP3 files allowed"));
                exit;
            }
            if (move_uploaded_file($_FILES['song']['tmp_name'], $targetSong)) {
                $songURL = $targetSong;
            } else {
                echo json_encode(array("error" => "Song upload failed"));
                exit;
            }
        } else {
            echo json_encode(array("error" => "No song file provided"));
            exit;
        }

        // Process cover image upload
        $coverURL = '';
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] == UPLOAD_ERR_OK) {
            $coverName = basename($_FILES['cover']['name']);
            $targetCover = $uploadDir . $coverName;
            $coverType = strtolower(pathinfo($targetCover, PATHINFO_EXTENSION));

            // Validate cover image type
            if (!in_array($coverType, ['jpg', 'jpeg', 'png', 'gif'])) {
                echo json_encode(array("error" => "Invalid cover image format"));
                exit;
            }
            if (move_uploaded_file($_FILES['cover']['tmp_name'], $targetCover)) {
                $coverURL = $targetCover;
            }
        }

        // Get additional data from the POST request
        $title = $conn->real_escape_string($_POST['title'] ?? $songName);
        $artist = $conn->real_escape_string($_POST['artist'] ?? 'Uploaded Artist');
        $lyrics = $conn->real_escape_string($_POST['lyrics'] ?? '');

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO songs (title, file, cover, artist, lyrics) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $title, $songURL, $coverURL, $artist, $lyrics);
        if ($stmt->execute()) {
            echo json_encode(array("success" => "Song uploaded successfully"));
        } else {
            echo json_encode(array("error" => "Database error: " . $conn->error));
        }
        $stmt->close();
        $conn->close();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neon Wave Music Player</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --color-primary: hsl(201, 63%, 54%);
            --color-secondary: hsl(261, 77%, 57%);
            --color-accent: hsl(21, 88%, 78%);
            --color-background: hsl(51, 86%, 78%);
            --color-text: hsl(81, 84%, 75%);
            --color-dark: hsl(220, 13%, 18%);
            --color-success: hsl(145, 63%, 49%);
            --color-error: hsl(0, 82%, 68%);
        }
        body {
            background: linear-gradient(135deg, var(--color-dark), hsl(220, 13%, 25%));
            color: white;
            min-height: 100vh;
            font-family: 'Space Mono', monospace;
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        .neon-text {
            text-shadow: 0 0 10px rgba(64, 160, 212, 0.7);
        }
        .neon-shadow {
            box-shadow: 0 0 20px rgba(64, 160, 212, 0.3);
        }
        .progress-bar {
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            cursor: pointer;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-primary), var(--color-accent));
            border-radius: 3px;
            transition: width 0.1s linear;
            position: relative;
        }
        .progress-fill::after {
            content: '';
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            width: 12px;
            height: 12px;
            background: white;
            border-radius: 50%;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .progress-bar:hover .progress-fill::after {
            opacity: 1;
        }
        .playlist-container {
            scrollbar-width: thin;
            scrollbar-color: var(--color-primary) transparent;
        }
        .playlist-container::-webkit-scrollbar {
            width: 8px;
        }
        .playlist-container::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 4px;
        }
        .playlist-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .song-item:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.1) !important;
        }
        .song-item {
            transition: all 0.2s ease;
        }
        .current-song {
            background: linear-gradient(90deg, rgba(64, 160, 212, 0.2), transparent) !important;
            border-left: 3px solid var(--color-primary);
        }
        .waveform {
            position: relative;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
        }
        .waveform-bar {
            width: 4px;
            background: var(--color-primary);
            border-radius: 3px;
            animation: equalize 1.5s infinite ease-in-out;
            animation-play-state: paused;
            transition: height 0.1s ease-out;
        }
        @keyframes equalize {
            0%, 100% { height: 10%; }
            50% { height: 100%; }
        }
        .volume-slider {
            -webkit-appearance: none;
            width: 100px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            cursor: pointer;
        }
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px;
            height: 14px;
            background: white;
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .volume-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
        .modal-overlay {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 100;
        }
        .upload-btn {
            background: linear-gradient(90deg, var(--color-primary), var(--color-secondary));
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(64, 160, 212, 0.3);
        }
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(64, 160, 212, 0.4);
        }
        .control-btn {
            transition: all 0.2s ease;
        }
        .control-btn:hover {
            transform: scale(1.1);
            color: var(--color-primary) !important;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(64, 160, 212, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(64, 160, 212, 0); }
            100% { box-shadow: 0 0 0 0 rgba(64, 160, 212, 0); }
        }
        /* Toast Styles */
        #toast {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            padding: 1rem 1.5rem;
            border-radius: 8px;
            max-width: 300px;
            text-align: center;
            z-index: 1000;
            transform: translateY(20px);
            opacity: 0;
        }
        #toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        /* Default Cover */
        .default-cover {
            background: linear-gradient(135deg, #2c3e50, #4ca1af);
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.5);
        }
        /* Waveform animation when playing */
        .playing .waveform-bar {
            animation-play-state: running;
        }
        /* Error state */
        .error {
            color: var(--color-error);
        }
        /* Floating action button */
        .fab {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(64, 160, 212, 0.4);
            cursor: pointer;
            z-index: 50;
            transition: all 0.3s ease;
        }
        .fab:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 25px rgba(64, 160, 212, 0.5);
        }
        /* Lyrics Modal */
        .lyrics-container {
            max-height: 60vh;
            overflow-y: auto;
            1.8;
            font-size: 1.1rem;
            text-align: center;
        }
        /* Loading Spinner */
        .spinner {
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        /* Floating Visualizer */
        .floating-visualizer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            opacity: 0.1;
            overflow: hidden;
        }
        .visualizer-circle {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, var(--color-primary), transparent);
            filter: blur(20px);
            animation: float 15s infinite linear;
        }
        @keyframes float {
            0% { transform: translate(0, 0) scale(1); opacity: 0.3; }
            50% { transform: translate(50px, 50px) scale(1.5); opacity: 0.1; }
            100% { transform: translate(0, 0) scale(1); opacity: 0.3; }
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .player-container {
                flex-direction: column;
            }
            .album-art {
                width: 100% !important;
                max-width: 300px;
                margin: 0 auto;
            }
            .controls {
                margin-top: 1.5rem;
            }
            .fab {
                bottom: 1rem;
                right: 1rem;
            }
        }
        /* Custom Scrollbar for Lyrics */
        .lyrics-container::-webkit-scrollbar {
            width: 6px;
        }
        .lyrics-container::-webkit-scrollbar-thumb {
            background: var(--color-primary);
            border-radius: 3px;
        }
        /* Marquee Effect for Long Song Titles */
        .marquee {
            display: inline-block;
            white-space: nowrap;
            animation: marquee 10s linear infinite;
            padding-left: 100%;
        }
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-100%); }
        }
        /* Equalizer Effect for Play Button */
        .equalizer {
            display: flex;
            align-items: flex-end;
            height: 20px;
            gap: 3px;
        }
        .equalizer-bar {
            width: 3px;
            background: white;
            border-radius: 3px;
            animation: equalizer-animation 1.5s infinite ease-in-out;
        }
        .equalizer-bar:nth-child(1) { animation-delay: 0s; height: 30%; }
        .equalizer-bar:nth-child(2) { animation-delay: 0.2s; height: 60%; }
        .equalizer-bar:nth-child(3) { animation-delay: 0.4s; height: 40%; }
        .equalizer-bar:nth-child(4) { animation-delay: 0.6s; height: 80%; }
        @keyframes equalizer-animation {
            0%, 100% { height: 30%; }
            50% { height: 100%; }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Floating Background Visualizer -->
    <div class="floating-visualizer">
        <div class="visualizer-circle" style="width: 300px; height: 300px; top: 10%; left: 10%;"></div>
        <div class="visualizer-circle" style="width: 200px; height: 200px; top: 60%; left: 70%;"></div>
        <div class="visualizer-circle" style="width: 400px; height: 400px; top: 30%; left: 50%;"></div>
    </div>
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Player Section -->
        <div class="glass-effect rounded-2xl p-6 neon-shadow mb-8">
            <div class="flex flex-col md:flex-row gap-6 player-container">
                <!-- Album Art -->
                <div class="w-full md:w-1/3 aspect-square rounded-xl overflow-hidden relative album-art">
                    <div id="coverArt" class="w-full h-full object-cover default-cover">
                        <i class="fas fa-music text-5xl"></i>
                    </div>
                    <div id="waveform" class="waveform absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent hidden">
                        <!-- Waveform bars will be generated dynamically -->
                    </div>
                    <div id="lyricsBtn" class="absolute top-2 right-2 bg-black/50 rounded-full w-8 h-8 flex items-center justify-center cursor-pointer hover:bg-black/70" title="Show Lyrics">
                        <i class="fas fa-align-left text-sm"></i>
                    </div>
                </div>
                <!-- Player Controls -->
                <div class="flex-1 flex flex-col controls">
                    <div class="mb-4">
                        <h2 id="songTitle" class="text-2xl font-bold mb-1 neon-text truncate max-w-full">Select a song</h2>
                        <p id="artist" class="text-white/70">-</p>
                    </div>
                    <div class="progress-bar mb-4" id="progressBar">
                        <div id="progress" class="progress-fill w-0"></div>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span id="currentTime" class="text-sm text-white/70">0:00</span>
                        <span id="duration" class="text-sm text-white/70">0:00</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button id="shuffleBtn" class="control-btn text-white/50 hover:text-white" title="Shuffle">
                                <i class="fas fa-random text-lg"></i>
                            </button>
                            <button id="prevBtn" class="control-btn p-2" title="Previous">
                                <i class="fas fa-step-backward text-xl"></i>
                            </button>
                        </div>
                        <button id="playBtn" class="control-btn bg-white/10 rounded-full w-14 h-14 flex items-center justify-center hover:bg-white/20 pulse" title="Play">
                            <i class="fas fa-play text-2xl"></i>
                        </button>
                        <div class="flex items-center gap-2">
                            <button id="nextBtn" class="control-btn p-2" title="Next">
                                <i class="fas fa-step-forward text-xl"></i>
                            </button>
                            <button id="repeatBtn" class="control-btn text-white/50 hover:text-white" title="Repeat">
                                <i class="fas fa-redo text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        <i class="fas fa-volume-down text-white/70"></i>
                        <input type="range" id="volumeSlider" class="volume-slider" min="0" max="1" step="0.01" value="0.7">
                        <i class="fas fa-volume-up text-white/70"></i>
                    </div>
                </div>
            </div>
        </div>
        <!-- Playlist + Upload -->
        <div class="glass-effect rounded-2xl p-6 neon-shadow">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Playlist</h3>
                <div class="flex gap-3">
                    <button id="refreshBtn" class="bg-white/10 px-3 py-1 rounded-lg hover:bg-white/20" title="Refresh playlist">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button id="uploadBtn" class="upload-btn px-4 py-2 rounded-lg text-white font-medium flex items-center" title="Upload">
                        <i class="fas fa-upload mr-2"></i>Upload
                    </button>
                </div>
            </div>
            <!-- Playlist -->
            <div class="playlist-container h-72 overflow-y-auto pr-2">
                <ul id="playlist" class="space-y-2">
                    <!-- Playlist items will be added here dynamically -->
                    <li class="text-center py-10 text-white/50">
                        <i class="fas fa-music text-3xl mb-2"></i>
                        <p>No songs in playlist</p>
                    </li>
                </ul>
            </div>
            <!-- Footer Credit -->
            <div class="mt-4 text-center text-white/50 text-sm">
                Made with <span class="text-red-400">❤️</span> by DK.
            </div>
        </div>
    </div>
    <!-- Floating Action Button -->
    <div class="fab hidden" id="miniPlayer">
        <i class="fas fa-music"></i>
    </div>
    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Upload New Song</h3>
                <button id="cancelBtn" class="text-white/50 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form id="uploadForm" class="space-y-4" enctype="multipart/form-data">
                <div>
                    <label class="block mb-2 text-sm font-medium">Song Title</label>
                    <input type="text" name="title" required
                            class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-white/50 placeholder-white/30"
                            placeholder="Enter song title">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">Artist</label>
                    <input type="text" name="artist" required
                            class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-white/50 placeholder-white/30"
                            placeholder="Enter artist name">
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">Lyrics (Optional)</label>
                    <textarea name="lyrics" rows="3"
                              class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-white/50 placeholder-white/30"
                              placeholder="Enter song lyrics"></textarea>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">Cover Art (Optional)</label>
                    <div class="flex items-center gap-3">
                        <label for="coverInput" class="cursor-pointer bg-white/10 rounded-lg p-3 flex-1 text-center hover:bg-white/20">
                            <i class="fas fa-image mr-2"></i>
                            <span id="coverFileName">Choose cover image</span>
                            <input type="file" id="coverInput" name="cover" accept="image/*" class="hidden">
                        </label>
                        <div id="coverPreview" class="w-16 h-16 bg-white/5 rounded-lg overflow-hidden hidden">
                            <img id="coverPreviewImg" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">Song File (MP3)</label>
                    <label for="songInput" class="cursor-pointer bg-white/10 rounded-lg p-3 flex text-center hover:bg-white/20">
                        <i class="fas fa-music mr-2"></i>
                        <span id="songFileName">Choose MP3 file</span>
                        <input type="file" id="songInput" name="song" accept="audio/mp3" required class="hidden">
                    </label>
                </div>
                <div class="flex gap-4 pt-2">
                    <button type="submit" class="flex-1 bg-white/10 px-4 py-3 rounded-lg hover:bg-white/20 font-medium flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>Upload
                    </button>
                    <button type="button" id="cancelUploadBtn" class="flex-1 bg-red-500/20 px-4 py-3 rounded-lg hover:bg-red-500/30 font-medium">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Lyrics Modal -->
    <div id="lyricsModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4 max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Lyrics</h3>
                <button id="closeLyricsBtn" class="text-white/50 hover:text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="lyrics-container flex-1 overflow-y-auto py-2">
                <p id="lyricsText" class="text-white/80">No lyrics available for this song.</p>
            </div>
        </div>
    </div>
    <!-- Notification Toast -->
    <div id="toast" class="fixed bottom-4 right-4 p-4 rounded-lg shadow-lg hidden z-50"></div>
    <script>
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

</script>
</body>
</html>
