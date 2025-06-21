<?php
// public/index.php
// Main entry point for the application.
// This file primarily serves the HTML structure.
// API requests are handled by including api.php.

// If an 'action' parameter is present, assume it's an API call
// and delegate to api.php.
if (isset($_GET['action'])) {
    // Ensure api.php is in the expected location relative to this file.
    $api_file_path = __DIR__ . '/../src/api.php';
    if (file_exists($api_file_path)) {
        require_once $api_file_path;
    } else {
        http_response_code(500);
        header("Content-Type: application/json");
        echo json_encode(["error" => "API handler not found. Server configuration issue."]);
    }
    exit; // API calls should not render HTML below.
}

// Define a base path for assets.
// Assumes that if served from a subdirectory (e.g. localhost/music-player/),
// the web server root is pointing to the project's root, and URL is /music-player/public/
// Or, if virtual host points to public/, then URLs are relative from there.
// For simplicity, using relative paths from the current dir (public/) for assets.
$assets_base = '.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neon Wave Music Player</title>

    <!-- Tailwind CSS from CDN -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome from CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Custom Styles -->
    <link rel="stylesheet" href="<?php echo $assets_base; ?>/assets/css/style.css">

    <!-- Favicon (optional, create a favicon.ico in public/assets/images/) -->
    <!-- <link rel="icon" href="<?php echo $assets_base; ?>/assets/images/favicon.ico" type="image/x-icon"> -->

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
                    <div id="coverArt" class="w-full h-full object-cover default-cover flex items-center justify-center">
                        <i class="fas fa-music text-5xl"></i>
                    </div>
                    <div id="waveform" class="waveform absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent hidden">
                        <!-- Waveform bars will be generated dynamically by JS -->
                    </div>
                    <div id="lyricsBtn" class="absolute top-2 right-2 bg-black/50 rounded-full w-8 h-8 flex items-center justify-center cursor-pointer hover:bg-black/70 transition-colors" title="Show Lyrics">
                        <i class="fas fa-align-left text-sm"></i>
                    </div>
                </div>

                <!-- Player Controls -->
                <div class="flex-1 flex flex-col controls">
                    <div class="mb-4">
                        <h2 id="songTitle" class="text-2xl font-bold mb-1 neon-text truncate max-w-full" title="Select a song">Select a song</h2>
                        <p id="artist" class="text-white/70">-</p>
                    </div>
                    <div class="progress-bar mb-4" id="progressBar">
                        <div id="progress" class="progress-fill w-0"></div>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span id="currentTime" class="text-sm text-white/70">0:00</span>
                        <span id="duration" class="text-sm text-white/70">0:00</span>
                    </div>
                    <div class="flex items-center justify-center md:justify-between">
                        <div class="flex items-center gap-2">
                            <button id="shuffleBtn" class="control-btn text-white/50 hover:text-[var(--color-primary)] p-2 transition-colors" title="Shuffle">
                                <i class="fas fa-random text-lg"></i>
                            </button>
                            <button id="prevBtn" class="control-btn p-2 text-white hover:text-[var(--color-primary)] transition-colors" title="Previous">
                                <i class="fas fa-step-backward text-xl"></i>
                            </button>
                        </div>
                        <button id="playBtn" class="control-btn bg-white/10 rounded-full w-14 h-14 flex items-center justify-center hover:bg-white/20 pulse mx-4 md:mx-0 transition-colors text-white" title="Play">
                            <i class="fas fa-play text-2xl"></i>
                        </button>
                        <div class="flex items-center gap-2">
                            <button id="nextBtn" class="control-btn p-2 text-white hover:text-[var(--color-primary)] transition-colors" title="Next">
                                <i class="fas fa-step-forward text-xl"></i>
                            </button>
                            <button id="repeatBtn" class="control-btn text-white/50 hover:text-[var(--color-primary)] p-2 transition-colors" title="Repeat">
                                <i class="fas fa-redo text-lg"></i> <!-- Default: repeat off -->
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-6 justify-center">
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
                <h3 class="text-xl font-bold neon-text">Playlist</h3>
                <div class="flex gap-3">
                    <button id="refreshBtn" class="bg-white/10 px-3 py-2 rounded-lg hover:bg-white/20 transition-colors text-white" title="Refresh playlist">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                    <button id="uploadBtn" class="upload-btn px-4 py-2 rounded-lg text-white font-medium flex items-center" title="Upload New Song">
                        <i class="fas fa-upload mr-2"></i>Upload
                    </button>
                </div>
            </div>

            <div class="playlist-container h-72 overflow-y-auto pr-2">
                <ul id="playlist" class="space-y-2">
                    <!-- Playlist items will be added here by main.js -->
                    <li class="text-center py-10 text-white/50">
                        <i class="fas fa-compact-disc text-3xl mb-2 fa-spin"></i>
                        <p>Loading playlist...</p>
                    </li>
                </ul>
            </div>

            <div class="mt-6 text-center text-white/50 text-sm">
                Made with <span class="text-red-400 animate-pulse">❤️</span> by DK.
            </div>
        </div>
    </div>

    <!-- Floating Action Button for Mini Player (controlled by JS) -->
    <div class="fab hidden" id="miniPlayer">
        <i class="fas fa-music"></i>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 hidden items-center justify-center z-[100] modal-overlay">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold neon-text">Upload New Song</h3>
                <button id="cancelBtn" class="text-white/50 hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <form id="uploadForm" class="space-y-4" enctype="multipart/form-data">
                <div>
                    <label for="formTitle" class="block mb-1 text-sm font-medium text-white/80">Song Title</label>
                    <input type="text" id="formTitle" name="title" required
                            class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] placeholder-white/40 text-white"
                            placeholder="Enter song title">
                </div>
                <div>
                    <label for="formArtist" class="block mb-1 text-sm font-medium text-white/80">Artist</label>
                    <input type="text" id="formArtist" name="artist"
                            class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] placeholder-white/40 text-white"
                            placeholder="Enter artist name (optional)">
                </div>
                <div>
                    <label for="formLyrics" class="block mb-1 text-sm font-medium text-white/80">Lyrics (Optional)</label>
                    <textarea id="formLyrics" name="lyrics" rows="3"
                              class="w-full bg-white/10 rounded-lg p-3 focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] placeholder-white/40 text-white"
                              placeholder="Enter song lyrics"></textarea>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-white/80">Cover Art (Optional)</label>
                    <div class="flex items-center gap-3">
                        <label for="coverInput" class="cursor-pointer bg-white/10 rounded-lg p-3 flex-1 text-center hover:bg-white/20 transition-colors text-white/80">
                            <i class="fas fa-image mr-2"></i>
                            <span id="coverFileName">Choose cover image</span>
                            <input type="file" id="coverInput" name="cover" accept="image/jpeg,image/png,image/gif" class="hidden">
                        </label>
                        <div id="coverPreview" class="w-16 h-16 bg-white/5 rounded-lg overflow-hidden hidden">
                            <img id="coverPreviewImg" src="#" alt="Cover preview" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <div>
                    <label class="block mb-1 text-sm font-medium text-white/80">Song File (MP3 Required)</label>
                    <label for="songInput" class="cursor-pointer bg-white/10 rounded-lg p-3 flex text-center hover:bg-white/20 transition-colors text-white/80">
                        <i class="fas fa-file-audio mr-2"></i>
                        <span id="songFileName">Choose MP3 file</span>
                        <input type="file" id="songInput" name="song" accept="audio/mp3" required class="hidden">
                    </label>
                </div>
                <div class="flex gap-4 pt-3">
                    <button type="submit" class="flex-1 upload-btn px-4 py-3 rounded-lg font-medium flex items-center justify-center text-white">
                        <i class="fas fa-cloud-upload-alt mr-2"></i>Upload Song
                    </button>
                    <button type="button" id="cancelUploadBtn" class="flex-1 bg-red-500/30 px-4 py-3 rounded-lg hover:bg-red-500/40 font-medium text-red-300 hover:text-red-200 transition-colors">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lyrics Modal -->
    <div id="lyricsModal" class="fixed inset-0 hidden items-center justify-center z-[100] modal-overlay">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-lg neon-shadow mx-4 max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold neon-text">Lyrics</h3>
                <button id="closeLyricsBtn" class="text-white/50 hover:text-white transition-colors">
                    <i class="fas fa-times text-2xl"></i>
                </button>
            </div>
            <div class="lyrics-container flex-1 overflow-y-auto py-2 pr-2 text-white/90 leading-relaxed">
                <p id="lyricsText" class="whitespace-pre-wrap">No lyrics available for this song.</p>
            </div>
        </div>
    </div>

    <!-- Notification Toast (controlled by JS) -->
    <div id="toast" class="fixed bottom-4 right-4 p-4 rounded-lg shadow-lg hidden z-[1000]">
        <!-- Content will be set by JS -->
    </div>

    <!-- Main JavaScript for Player Logic -->
    <script src="<?php echo $assets_base; ?>/assets/js/main.js"></script>
</body>
</html>
