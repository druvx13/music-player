<?php require_once 'php/api.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neon Wave Music Player</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
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
    <script src="js/script.js"></script>
</body>
</html>
