<?php
<?php
require_once 'config.php'; // Include the configuration file

// Helper function to send JSON responses
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// index.php
// Database configuration is now in config.php
$charset = 'utf8mb4'; // Charset can remain here or be moved to config.php if preferred

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = null; // Initialize $pdo to null

// API endpoints
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    try {
        // Establish PDO connection only when an action is requested
        $pdo = new PDO($dsn, $user, $pass, $options);

        // Get playlist endpoint
        if ($action === 'getPlaylist') {
            header("Content-Type: application/json");
            $sql = "SELECT id, title, file, cover, artist, lyrics, album, track_order, DATE_FORMAT(uploaded_at, '%Y-%m-%d %H:%i:%s') AS uploaded_at FROM songs ORDER BY track_order ASC, id ASC";
            $stmt = $pdo->query($sql);
            $songs = $stmt->fetchAll();
            sendJsonResponse($songs);
        }

        // Upload song endpoint
        elseif ($action === 'uploadSong') {
            // Process song file upload
            if (isset($_FILES['song']) && $_FILES['song']['error'] == UPLOAD_ERR_OK) {
                // $uploadDir is now defined in config.php
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        sendJsonResponse(["error" => "Failed to create upload directory."], 500);
                    }
                }

                $songTmpPath = $_FILES['song']['tmp_name'];
                $songOriginalName = basename($_FILES['song']['name']);
                $songExtension = strtolower(pathinfo($songOriginalName, PATHINFO_EXTENSION));

                $songMimeType = mime_content_type($songTmpPath);
                if ($songMimeType !== 'audio/mpeg' && $songMimeType !== 'audio/mp3') {
                    sendJsonResponse(["error" => "Invalid song file type. Only MP3 audio is allowed. Detected: " . $songMimeType], 400);
                }
                if ($songExtension !== "mp3") {
                    sendJsonResponse(["error" => "Invalid song file extension. Only .mp3 is allowed."], 400);
                }

                $songName = pathinfo($songOriginalName, PATHINFO_FILENAME) . '_' . time() . '.' . $songExtension;
                $targetSong = $uploadDir . $songName;

                if (!move_uploaded_file($songTmpPath, $targetSong)) {
                    sendJsonResponse(["error" => "Song upload failed, could not move file. Check permissions or file size."], 500);
                }
                $songURL = $targetSong;
            } else {
                sendJsonResponse(["error" => "No song file provided or upload error."], 400);
            }

            // Process cover image upload
            $coverURL = null;
            if (isset($_FILES['cover']) && $_FILES['cover']['error'] == UPLOAD_ERR_OK) {
                $coverTmpPath = $_FILES['cover']['tmp_name'];
                $coverOriginalName = basename($_FILES['cover']['name']);
                $coverExtension = strtolower(pathinfo($coverOriginalName, PATHINFO_EXTENSION));

                $coverMimeType = mime_content_type($coverTmpPath);
                $allowedImageMimes = ['image/jpeg', 'image/png', 'image/gif'];
                if (!in_array($coverMimeType, $allowedImageMimes)) {
                    sendJsonResponse(["error" => "Invalid cover image type. Allowed: JPEG, PNG, GIF. Detected: " . $coverMimeType], 400);
                }
                $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array($coverExtension, $allowedImageExtensions)) {
                     sendJsonResponse(["error" => "Invalid cover image extension. Allowed: jpg, jpeg, png, gif."], 400);
                }

                $coverName = pathinfo($coverOriginalName, PATHINFO_FILENAME) . '_' . time() . '.' . $coverExtension;
                $targetCover = $uploadDir . $coverName;

                if (move_uploaded_file($coverTmpPath, $targetCover)) {
                    $coverURL = $targetCover;
                } else {
                     error_log("Cover upload failed for " . $coverOriginalName . " but proceeding without cover.");
                }
            }

            $title = trim((string) ($_POST['title'] ?? pathinfo($songOriginalName, PATHINFO_FILENAME)));
            $artist = trim((string) ($_POST['artist'] ?? 'Unknown Artist'));
            $lyrics = (string) ($_POST['lyrics'] ?? ''); // Trim handled by client if needed, or here
            $album = isset($_POST['album']) ? trim((string)$_POST['album']) : null;
            if (empty($album)) $album = null;

            if (empty($title)) sendJsonResponse(['error' => 'Title cannot be empty.'], 400);
            if (empty($artist)) sendJsonResponse(['error' => 'Artist cannot be empty.'], 400);

            $sql = "INSERT INTO songs (title, file, cover, artist, lyrics, album) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $songURL, $coverURL, $artist, $lyrics, $album]);

            $lastId = $pdo->lastInsertId();
            $selectStmt = $pdo->prepare("SELECT id, title, file, cover, artist, lyrics, album, track_order, DATE_FORMAT(uploaded_at, '%Y-%m-%d %H:%i:%s') as uploaded_at FROM songs WHERE id = ?");
            $selectStmt->execute([$lastId]);
            $newSong = $selectStmt->fetch();

            sendJsonResponse(["success" => "Song uploaded successfully", "song" => $newSong]);
        }

        elseif ($action === 'updatePlaylistOrder') {
            $input = json_decode(file_get_contents('php://input'), true);
            $songIds = $input['songIds'] ?? null;

            if (!is_array($songIds) || empty($songIds)) {
                sendJsonResponse(['error' => 'Invalid input. songIds must be a non-empty array.'], 400);
            }

            $pdo->beginTransaction();
            try {
                $sql = "UPDATE songs SET track_order = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);

                foreach ($songIds as $index => $songId) {
                    if (!is_numeric($songId) || (int)$songId <= 0) { // Ensure positive integer
                        throw new Exception("Invalid song ID format or value: " . $songId);
                    }
                    $stmt->execute([$index, (int)$songId]);
                }
                $pdo->commit();
                sendJsonResponse(['success' => 'Playlist order updated successfully.']);
            } catch (Exception $e) {
                 if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Error updating playlist order: " . $e->getMessage());
                sendJsonResponse(['error' => $e->getMessage()], ($e instanceof PDOException ? 500 : 400) );
            }
        }

        elseif ($action === 'updateSongMetadata') {
            $input = json_decode(file_get_contents('php://input'), true);

            $songId = filter_var($input['songId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $title = trim((string)($input['title'] ?? ''));
            $artist = trim((string)($input['artist'] ?? ''));
            $album = isset($input['album']) ? trim((string)$input['album']) : null;
            if (empty($album)) $album = null;

            if (!$songId) {
                sendJsonResponse(['error' => 'Invalid Song ID.'], 400);
            }
            if (empty($title)) {
                sendJsonResponse(['error' => 'Title cannot be empty.'], 400);
            }
            if (empty($artist)) {
                sendJsonResponse(['error' => 'Artist cannot be empty.'], 400);
            }

            $sql = "UPDATE songs SET title = ?, artist = ?, album = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $artist, $album, $songId]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse(['success' => 'Song metadata updated successfully.']);
            } else {
                sendJsonResponse(['success' => 'Metadata updated (or values were the same). No rows changed.']);
            }
        }
        else {
            sendJsonResponse(["error" => "Invalid action specified."], 400);
        }

    } catch (PDOException $e) {
        error_log("PDO Exception: " . $e->getMessage());
        sendJsonResponse(["error" => "Database connection error: " . $e->getMessage()], 500);
    } catch (Exception $e) {
        error_log("General Exception: " . $e->getMessage());
        sendJsonResponse(["error" => "An unexpected error occurred: " . $e->getMessage()], 500);
    } finally {
        $pdo = null; // Close connection
    }
    exit; // Important to stop script execution after handling API request
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>
    <title>Neon Wave Music Player - Accessible Music Experience</title>
    <style>
        :root { /* Default to dark theme variables */
            --color-primary: hsl(201, 63%, 54%);
            --color-secondary: hsl(261, 77%, 57%);
            --color-accent: hsl(21, 88%, 78%);
            /* --color-background: hsl(51, 86%, 78%); /* Not directly used for body anymore */
            /* --color-text: hsl(81, 84%, 75%); /* Not directly used for body anymore */
            --color-dark-bg-start: hsl(220, 13%, 18%); /* For gradient if needed, or base */
            --color-dark-bg-end: hsl(220, 13%, 25%); /* For gradient if needed */
            --color-success: hsl(145, 63%, 49%);
            --color-error: hsl(0, 82%, 68%);

            --color-background-body: hsl(220, 13%, 18%);
            --color-text-body: white;
            --color-text-muted: rgba(255, 255, 255, 0.7);
            --color-text-muted-light: rgba(255, 255, 255, 0.5);
            --color-glass-bg: rgba(255, 255, 255, 0.08);
            --color-glass-border: rgba(255, 255, 255, 0.08);
            --color-input-bg: rgba(255, 255, 255, 0.1);
            --color-input-ring: rgba(255, 255, 255, 0.5);
            --color-input-placeholder: rgba(255, 255, 255, 0.3);
            --color-button-bg: rgba(255, 255, 255, 0.1);
            --color-button-hover-bg: rgba(255, 255, 255, 0.2);
            --color-current-song-bg: linear-gradient(90deg, hsla(201, 63%, 54%, 0.2), transparent);
            --color-progress-bar-bg: rgba(255, 255, 255, 0.1);
            --color-default-cover-bg: linear-gradient(135deg, #2c3e50, #4ca1af);
            --color-modal-overlay-bg: rgba(0, 0, 0, 0.7);
        }

        body.light-theme {
            --color-primary: hsl(201, 63%, 45%);
            --color-secondary: hsl(261, 77%, 50%);
            --color-accent: hsl(21, 88%, 70%);
            --color-dark-bg-start: hsl(220, 20%, 90%);
            --color-dark-bg-end: hsl(220, 20%, 95%);
            --color-success: hsl(145, 63%, 40%);
            --color-error: hsl(0, 82%, 60%);

            --color-background-body: hsl(220, 25%, 96%);
            --color-text-body: hsl(220, 13%, 25%);
            --color-text-muted: rgba(0, 0, 0, 0.6);
            --color-text-muted-light: rgba(0, 0, 0, 0.4);
            --color-glass-bg: rgba(255, 255, 255, 0.7); /* More opaque for light theme */
            --color-glass-border: rgba(0, 0, 0, 0.08);
            --color-input-bg: rgba(0, 0, 0, 0.05);
            --color-input-ring: rgba(0, 0, 0, 0.4);
            --color-input-placeholder: rgba(0, 0, 0, 0.4);
            --color-button-bg: rgba(0, 0, 0, 0.05);
            --color-button-hover-bg: rgba(0, 0, 0, 0.1);
            --color-current-song-bg: linear-gradient(90deg, hsla(201, 63%, 45%, 0.15), transparent);
            --color-progress-bar-bg: rgba(0, 0, 0, 0.1);
            --color-default-cover-bg: linear-gradient(135deg, #bdc3c7, #dce0e2);
            --color-modal-overlay-bg: rgba(0, 0, 0, 0.5);
        }

        body {
            background-color: var(--color-background-body);
            color: var(--color-text-body);
            min-height: 100vh;
            font-family: 'Space Mono', monospace;
            transition: background-color 0.3s ease, color 0.3s ease;
        }
        .glass-effect {
            background: var(--color-glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--color-glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2); /* Shadow might need theme adjustment if too harsh */
        }
        .neon-text { /* This class might need to be toned down or adjusted for light theme */
            text-shadow: 0 0 10px var(--color-primary); /* Adjusted to use theme color */
        }
        .neon-shadow { /* This class might need to be toned down or adjusted for light theme */
            box-shadow: 0 0 20px hsla(var(--color-primary-hsl), 0.3); /* Assuming primary is HSL */
        }
        .progress-bar {
            height: 6px;
            background: var(--color-progress-bar-bg);
            border-radius: 3px;
            cursor: pointer;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--color-primary), var(--color-accent)); /* Gradient uses theme colors */
            border-radius: 3px;
            /* transition: width 0.1s linear; Progress fill width transition is handled by JS */
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
            background: transparent; /* Or a themed variable */
        }
        .song-item:hover {
            transform: translateX(5px);
            background: var(--color-button-hover-bg) !important; /* Use themed variable */
        }
        .song-item {
            transition: all 0.2s ease;
            background-color: var(--color-button-bg); /* Added for consistent base */
        }
        .current-song {
            background: var(--color-current-song-bg) !important;
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
            background: var(--color-progress-bar-bg); /* Themed */
            border-radius: 2px;
            cursor: pointer;
        }
        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 14px;
            height: 14px;
            background: var(--color-text-body); /* Themed */
            border-radius: 50%;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .volume-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }
        .modal-overlay {
            background: var(--color-modal-overlay-bg);
            backdrop-filter: blur(5px);
            z-index: 100;
            transition: opacity 0.2s ease-in-out, transform 0.2s ease-in-out;
            opacity: 0;
            transform: scale(0.95);
        }
        .modal-overlay.active {
            opacity: 1;
            transform: scale(1);
        }
        .upload-btn { /* This specific button has a gradient, may not theme well or need specific theme versions */
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
            color: var(--color-primary) !important; /* Primary color for hover icon */
        }
        /* Generic button theming for buttons that use bg-white/10 or similar */
        .themed-button {
            background-color: var(--color-button-bg);
            color: var(--color-text-body);
        }
        .themed-button:hover {
            background-color: var(--color-button-hover-bg);
        }
        .text-muted { /* Helper class for muted text */
            color: var(--color-text-muted);
        }
        .text-muted-light {
             color: var(--color-text-muted-light);
        }
        .input-themed {
            background-color: var(--color-input-bg) !important; /* Important to override Tailwind if needed */
            /* Tailwind's focus:ring-white/50 needs to be themed too if we want full control */
        }
        .input-themed::placeholder {
            color: var(--color-input-placeholder);
        }
        .input-themed:focus {
            --tw-ring-color: var(--color-input-ring) !important; /* Override Tailwind focus ring */
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
            background: var(--color-default-cover-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-muted); /* Muted text for icon */
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
            line-height: 1.8; /* Corrected line-height */
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
        }
        /* SortableJS helper classes */
        .sortable-ghost {
            opacity: 0.4;
            background: #4a5568; /* Tailwind gray-700 */
        }
        .sortable-chosen {
            background: #2d3748; /* Tailwind gray-800 */
            /* Add a bit more visual feedback for the chosen item */
            transform: scale(1.02);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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
    <div class="floating-visualizer" aria-hidden="true">
        <div class="visualizer-circle" style="width: 300px; height: 300px; top: 10%; left: 10%;"></div>
        <div class="visualizer-circle" style="width: 200px; height: 200px; top: 60%; left: 70%;"></div>
        <div class="visualizer-circle" style="width: 400px; height: 400px; top: 30%; left: 50%;"></div>
    </div>
    <main class="container mx-auto px-4 py-8 max-w-4xl">
        <h1 id="main-heading" class="sr-only">Neon Wave Music Player</h1> <!-- Screen-reader only main heading -->
        <!-- Player Section -->
        <section class="glass-effect rounded-2xl p-6 neon-shadow mb-8" aria-labelledby="player-section-heading">
            <h2 id="player-section-heading" class="sr-only">Music Player Controls and Display</h2>
            <div class="flex flex-col md:flex-row gap-6 player-container">
                <!-- Album Art -->
                <div class="w-full md:w-1/3 aspect-square rounded-xl overflow-hidden relative album-art">
                    <div id="coverArt" class="w-full h-full object-cover default-cover">
                        <i class="fas fa-music text-5xl" aria-hidden="true"></i>
                    </div>
                    <div id="waveform" class="waveform absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/70 to-transparent hidden" aria-hidden="true">
                        <!-- Waveform bars will be generated dynamically -->
                    </div>
                    <button id="lyricsBtn" aria-label="Show lyrics" class="absolute top-2 right-2 bg-black/50 rounded-full w-8 h-8 flex items-center justify-center cursor-pointer hover:bg-black/70" title="Show Lyrics">
                        <i class="fas fa-align-left text-sm" aria-hidden="true"></i>
                    </button>
                </div>
                <!-- Player Controls -->
                <div class="flex-1 flex flex-col controls">
                    <div class="mb-4">
                        <h2 id="songTitle" class="text-2xl font-bold mb-1 neon-text truncate max-w-full" aria-live="polite">Select a song</h2>
                        <p id="artist" class="text-white/70" aria-live="polite">-</p>
                        <p id="currentAlbum" class="text-sm text-white/60 truncate" aria-live="polite">-</p>
                    </div>
                    <div class="progress-bar mb-4" id="progressBar" role="slider" aria-label="Song progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="0:00 of 0:00">
                        <div id="progress" class="progress-fill w-0"></div>
                    </div>
                    <div class="flex items-center justify-between mb-4">
                        <span id="currentTime" class="text-sm text-white/70">0:00</span>
                        <span id="duration" class="text-sm text-white/70">0:00</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <button id="shuffleBtn" aria-label="Shuffle playlist" class="control-btn text-white/50 hover:text-white" title="Shuffle">
                                <i class="fas fa-random text-lg" aria-hidden="true"></i>
                            </button>
                            <button id="prevBtn" aria-label="Previous song" class="control-btn p-2" title="Previous">
                                <i class="fas fa-step-backward text-xl" aria-hidden="true"></i>
                            </button>
                        </div>
                        <button id="playBtn" aria-label="Play song" class="control-btn bg-white/10 rounded-full w-14 h-14 flex items-center justify-center hover:bg-white/20 pulse" title="Play">
                            <i class="fas fa-play text-2xl" aria-hidden="true"></i>
                        </button>
                        <div class="flex items-center gap-2">
                            <button id="nextBtn" aria-label="Next song" class="control-btn p-2" title="Next">
                                <i class="fas fa-step-forward text-xl" aria-hidden="true"></i>
                            </button>
                            <button id="repeatBtn" aria-label="Repeat playlist" class="control-btn text-white/50 hover:text-white" title="Repeat">
                                <i class="fas fa-redo text-lg" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 mt-4">
                        <i class="fas fa-volume-down text-white/70" aria-hidden="true"></i>
                        <input type="range" id="volumeSlider" aria-label="Volume control" class="volume-slider" min="0" max="1" step="0.01" value="0.7">
                        <i class="fas fa-volume-up text-white/70" aria-hidden="true"></i>
                    </div>
                </div>
            </div>
        </section>
        <!-- Playlist + Upload -->
        <section class="glass-effect rounded-2xl p-6 neon-shadow" aria-labelledby="playlist-heading">
            <div class="flex justify-between items-center mb-4">
                <h3 id="playlist-heading" class="text-xl font-bold">Playlist</h3>
                <div class="flex gap-3 items-center">
                    <button id="themeToggleBtn" class="themed-button px-3 py-1 rounded-lg" title="Toggle theme">
                        <i class="fas fa-sun" aria-hidden="true"></i>
                    </button>
                    <button id="refreshBtn" aria-label="Refresh playlist" class="themed-button px-3 py-1 rounded-lg" title="Refresh playlist">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i>
                    </button>
                    <button id="uploadBtn" aria-label="Open upload modal" class="upload-btn px-4 py-2 rounded-lg text-white font-medium flex items-center" title="Upload">
                        <i class="fas fa-upload mr-2" aria-hidden="true"></i>Upload
                    </button>
                </div>
            </div>
            <div class="mb-4"> <!-- Search input container -->
              <input type="search" id="playlistSearchInput"
                     class="w-full p-3 rounded-lg focus:outline-none focus:ring-2 input-themed"
                     placeholder="Search playlist (title, artist, album)...">
            </div>
            <!-- Playlist -->
            <div class="playlist-container h-72 overflow-y-auto pr-2">
                <ul id="playlist" class="space-y-2">
                    <!-- Playlist items will be added here dynamically -->
                    <li class="text-center py-10 text-white/50">
                        <i class="fas fa-music text-3xl mb-2" aria-hidden="true"></i>
                        <p>No songs in playlist</p>
                    </li>
                </ul>
            </div>
            <footer class="mt-4 text-center text-sm text-muted">
                Made with <span class="text-red-400" aria-hidden="true">❤️</span> by DK. |
                <a href="#" id="viewLicenseLink" class="hover:text-white underline">View License</a>
            </footer>
        </section>
    </main>
    <!-- Floating Action Button -->
    <button class="fab hidden" id="miniPlayer" aria-label="Open mini player" title="Show Full Player">
        <i class="fas fa-music" aria-hidden="true"></i>
    </button>
    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay" role="dialog" aria-modal="true" aria-labelledby="uploadModalHeading" aria-hidden="true">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 id="uploadModalHeading" class="text-xl font-bold">Upload New Song</h3>
                <button id="cancelBtn" aria-label="Close upload modal" class="text-white/50 hover:text-white themed-button">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <form id="uploadForm" class="space-y-4" enctype="multipart/form-data">
                <div>
                    <label for="uploadTitle" class="block mb-2 text-sm font-medium">Song Title</label>
                    <input type="text" id="uploadTitle" name="title" required aria-required="true"
                            class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed"
                            placeholder="Enter song title">
                </div>
                <div>
                    <label for="uploadArtist" class="block mb-2 text-sm font-medium">Artist</label>
                    <input type="text" id="uploadArtist" name="artist" required aria-required="true"
                            class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed"
                            placeholder="Enter artist name">
                </div>
                <div>
                    <label for="albumUploadInput" class="block mb-2 text-sm font-medium">Album (Optional)</label>
                    <input type="text" id="albumUploadInput" name="album"
                           class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed"
                           placeholder="Enter album name">
                </div>
                <div>
                    <label for="uploadLyrics" class="block mb-2 text-sm font-medium">Lyrics (Optional)</label>
                    <textarea id="uploadLyrics" name="lyrics" rows="3"
                              class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed"
                              placeholder="Enter song lyrics"></textarea>
                </div>
                <div>
                    <label class="block mb-2 text-sm font-medium">Cover Art (Optional)</label> <!-- No direct input, label for group -->
                    <div class="flex items-center gap-3">
                        <label for="coverInput" class="cursor-pointer themed-button rounded-lg p-3 flex-1 text-center">
                            <i class="fas fa-image mr-2" aria-hidden="true"></i>
                            <span id="coverFileName">Choose cover image</span>
                            <input type="file" id="coverInput" name="cover" accept="image/*" class="hidden">
                        </label>
                        <div id="coverPreview" class="w-16 h-16 bg-white/5 rounded-lg overflow-hidden hidden" aria-label="Cover art preview">
                            <img id="coverPreviewImg" src="" alt="Cover preview" class="w-full h-full object-cover">
                        </div>
                    </div>
                </div>
                <div>
                    <label for="songInput" class="block mb-2 text-sm font-medium">Song File (MP3)</label>
                    <label for="songInput" class="cursor-pointer themed-button rounded-lg p-3 flex text-center">
                        <i class="fas fa-music mr-2" aria-hidden="true"></i>
                        <span id="songFileName">Choose MP3 file</span>
                        <input type="file" id="songInput" name="song" accept="audio/mp3" required aria-required="true" class="hidden">
                    </label>
                </div>
                <div class="flex gap-4 pt-2">
                    <button type="submit" class="flex-1 themed-button px-4 py-3 rounded-lg font-medium flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt mr-2" aria-hidden="true"></i>Upload
                    </button>
                    <button type="button" id="cancelUploadBtn" aria-label="Cancel song upload" class="flex-1 bg-red-500/20 px-4 py-3 rounded-lg hover:bg-red-500/30 font-medium themed-button">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    <!-- Lyrics Modal -->
    <div id="lyricsModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay" role="dialog" aria-modal="true" aria-labelledby="lyricsModalHeading" aria-hidden="true">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4 max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 id="lyricsModalHeading" class="text-xl font-bold">Lyrics</h3>
                <button id="closeLyricsModalBtn" aria-label="Close lyrics modal" class="text-white/50 hover:text-white themed-button">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <div class="lyrics-container flex-1 overflow-y-auto py-2 text-sm">
                <p id="lyricsText" class="text-muted">No lyrics available for this song.</p>
            </div>
        </div>
    </div>
    <!-- Notification Toast -->
    <div id="toast" role="alert" aria-live="assertive" class="fixed bottom-4 right-4 p-4 rounded-lg shadow-lg hidden z-50"></div>

    <!-- Edit Song Modal -->
    <div id="editSongModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay" role="dialog" aria-modal="true" aria-labelledby="editSongModalTitle" aria-hidden="true">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-md neon-shadow mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 id="editSongModalTitle" class="text-xl font-bold">Edit Song Details</h3>
                <button id="cancelEditSongBtn" aria-label="Close edit dialog" class="text-white/50 hover:text-white themed-button">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <form id="editSongForm" class="space-y-4">
                <input type="hidden" name="songId" id="editSongIdInput">
                <div>
                    <label for="editSongTitleInput" class="block mb-2 text-sm font-medium">Title</label>
                    <input type="text" id="editSongTitleInput" name="title" required aria-required="true" class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed" placeholder="Enter song title">
                </div>
                <div>
                    <label for="editSongArtistInput" class="block mb-2 text-sm font-medium">Artist</label>
                    <input type="text" id="editSongArtistInput" name="artist" required aria-required="true" class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed" placeholder="Enter artist name">
                </div>
                <div>
                    <label for="editSongAlbumInput" class="block mb-2 text-sm font-medium">Album (Optional)</label>
                    <input type="text" id="editSongAlbumInput" name="album" class="w-full rounded-lg p-3 focus:outline-none focus:ring-2 input-themed" placeholder="Enter album name">
                </div>
                <div class="flex gap-4 pt-2">
                    <button type="submit" class="flex-1 upload-btn px-4 py-3 rounded-lg text-white font-medium flex items-center justify-center"> <!-- upload-btn has specific gradient, might keep or theme -->
                        <i class="fas fa-save mr-2" aria-hidden="true"></i>Save Changes
                    </button>
                    <button type="button" id="closeEditSongModalBtn" class="flex-1 bg-red-500/20 px-4 py-3 rounded-lg hover:bg-red-500/30 font-medium themed-button">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- License Modal -->
    <div id="licenseModal" class="fixed inset-0 hidden items-center justify-center z-50 modal-overlay" role="dialog" aria-modal="true" aria-labelledby="licenseModalTitle" aria-hidden="true">
        <div class="glass-effect rounded-2xl p-6 w-full max-w-2xl neon-shadow mx-4 max-h-[80vh] flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 id="licenseModalTitle" class="text-xl font-bold">License Information</h3>
                <button id="closeLicenseModalBtn" aria-label="Close license dialog" class="text-white/50 hover:text-white themed-button">
                    <i class="fas fa-times text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <div id="licenseTextContainer" class="lyrics-container flex-1 overflow-y-auto py-2 text-sm">
                <p>Loading license...</p>
            </div>
        </div>
    </div>

    <script>
        // Utility Functions
        const Utils = {
            formatTime: function(seconds = 0) {
                if (isNaN(seconds)) seconds = 0;
                const mins = Math.floor(seconds / 60);
                const secs = Math.floor(seconds % 60);
                return `${mins}:${secs.toString().padStart(2, '0')}`;
            },
            escapeHTML: function(str) {
                const p = document.createElement('p');
                p.appendChild(document.createTextNode(str || '')); // Handle null or undefined strings
                return p.innerHTML;
            }
        };

        // Global Audio Object and State
        const audio = new Audio();
        let currentSongIndex = -1;
        let playlist = [];
        let audioContext;
        let analyser;
        let dataArray;
        let waveformBars = [];
        let sortableInstance; // For SortableJS instance
        const state = {
            isPlaying: false,
            isShuffled: false,
            isRepeating: false,
            volume: 0.7,
            timer: null,
            isDraggingProgress: false,
        };

        // DOM Elements
        const UIElements = {
            playBtn: document.getElementById('playBtn'),
            prevBtn: document.getElementById('prevBtn'),
            nextBtn: document.getElementById('nextBtn'),
            progress: document.getElementById('progress'),
            progressBar: document.getElementById('progressBar'),
            currentTimeDisplay: document.getElementById('currentTime'),
            durationDisplay: document.getElementById('duration'),
            songTitle: document.getElementById('songTitle'),
            artist: document.getElementById('artist'),
            currentAlbum: document.getElementById('currentAlbum'), // Added for main player album display
            coverArt: document.getElementById('coverArt'),
            waveform: document.getElementById('waveform'),
            volumeSlider: document.getElementById('volumeSlider'),
            shuffleBtn: document.getElementById('shuffleBtn'),
            repeatBtn: document.getElementById('repeatBtn'),
            miniPlayer: document.getElementById('miniPlayer'),
            lyricsBtn: document.getElementById('lyricsBtn'),
            lyricsModal: document.getElementById('lyricsModal'),
            lyricsText: document.getElementById('lyricsText'),
            closeLyricsBtn: document.getElementById('closeLyricsBtn'),
            playlistElement: document.getElementById('playlist'),
            refreshBtn: document.getElementById('refreshBtn'),
            themeToggleBtn: document.getElementById('themeToggleBtn'), // Added theme toggle button
            uploadModal: document.getElementById('uploadModal'),
            uploadBtn: document.getElementById('uploadBtn'),
            cancelBtn: document.getElementById('cancelBtn'),
            cancelUploadBtn: document.getElementById('cancelUploadBtn'),
            uploadForm: document.getElementById('uploadForm'),
            coverInput: document.getElementById('coverInput'),
            coverFileName: document.getElementById('coverFileName'),
            coverPreview: document.getElementById('coverPreview'),
            coverPreviewImg: document.getElementById('coverPreviewImg'),
            songInput: document.getElementById('songInput'),
            songFileName: document.getElementById('songFileName'),
            toast: document.getElementById('toast'),
            // Edit Song Modal Elements
            editSongModal: document.getElementById('editSongModal'),
            editSongForm: document.getElementById('editSongForm'),
            editSongIdInput: document.getElementById('editSongIdInput'),
            editSongTitleInput: document.getElementById('editSongTitleInput'),
            editSongArtistInput: document.getElementById('editSongArtistInput'),
            editSongAlbumInput: document.getElementById('editSongAlbumInput'),
            cancelEditSongBtn: document.getElementById('cancelEditSongBtn'),
            closeEditSongModalBtn: document.getElementById('closeEditSongModalBtn'),
            // License Modal Elements
            licenseModal: document.getElementById('licenseModal'),
            closeLicenseModalBtn: document.getElementById('closeLicenseModalBtn'),
            viewLicenseLink: document.getElementById('viewLicenseLink'),
            licenseTextContainer: document.getElementById('licenseTextContainer'),
            playlistSearchInput: document.getElementById('playlistSearchInput'), // Added search input
        };

        // Initialization
        // --- Initialization Functions ---
        function _loadThemePreference() {
            const savedTheme = localStorage.getItem('musicPlayerTheme');
            if (savedTheme === 'light') {
                document.body.classList.add('light-theme');
                if (UIElements.themeToggleBtn) UIElements.themeToggleBtn.innerHTML = '<i class="fas fa-moon" aria-hidden="true"></i>';
            } else {
                if (UIElements.themeToggleBtn) UIElements.themeToggleBtn.innerHTML = '<i class="fas fa-sun" aria-hidden="true"></i>';
            }
        }

        function _loadVolumePreference() {
            const savedVolume = localStorage.getItem('musicPlayerVolume');
            if (savedVolume !== null) {
                state.volume = parseFloat(savedVolume);
                audio.volume = state.volume;
                if (UIElements.volumeSlider) UIElements.volumeSlider.value = state.volume;
            }
             // Apply initial volume to slider UI (if not already set by localStorage load)
            if (UIElements.volumeSlider && savedVolume === null) { // If no saved volume, ensure slider reflects default state
                UIElements.volumeSlider.value = state.volume;
            }
            if (UIElements.volumeSlider) { // Set ARIA attribute regardless
                 UIElements.volumeSlider.setAttribute('aria-valuetext', `Volume: ${Math.round(UIElements.volumeSlider.value * 100)}%`);
            }
        }

        function _loadShuffleRepeatPreferences() {
            const savedShuffle = localStorage.getItem('musicPlayerShuffle');
            if (savedShuffle !== null) {
                state.isShuffled = savedShuffle === 'true';
                if (UIElements.shuffleBtn) {
                    UIElements.shuffleBtn.classList.toggle('text-white', state.isShuffled);
                    UIElements.shuffleBtn.classList.toggle('text-muted-light', !state.isShuffled);
                }
            }
            const savedRepeat = localStorage.getItem('musicPlayerRepeat');
            if (savedRepeat !== null) {
                state.isRepeating = savedRepeat === 'true';
                 if (UIElements.repeatBtn) {
                    UIElements.repeatBtn.classList.toggle('text-white', state.isRepeating);
                    UIElements.repeatBtn.classList.toggle('text-muted-light', !state.isRepeating);
                }
            }
        }

        async function init() {
            _loadThemePreference();
            _loadVolumePreference();
            _loadShuffleRepeatPreferences();

            await fetchPlaylist();
            createWaveformBars();
            bindEventListeners();
            _setupAudioContextOnce();

            if ('mediaSession' in navigator) {
                UIElements.miniPlayer.classList.remove('hidden');
                setupMediaSession();
            }
            // Last played song logic is now inside fetchPlaylist's finally block
        }

        function _setupAudioContextOnce() {
            const initAudio = () => {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    audioContext = new AudioContext();
                    analyser = audioContext.createAnalyser();
                    analyser.fftSize = 64; // Smaller FFT size for less detailed but faster waveform
                    dataArray = new Uint8Array(analyser.frequencyBinCount);
                    const source = audioContext.createMediaElementSource(audio);
                    source.connect(analyser);
                    analyser.connect(audioContext.destination);
                    visualize();
                } catch (e) {
                    console.error("AudioContext error:", e);
                    showNotification("Could not initialize audio visualizer.", true);
                }
            };
            document.addEventListener('click', initAudio, { once: true });
        }

        // --- Playlist Management ---
        function _loadLastPlayedSong() {
            const lastSongId = localStorage.getItem('musicPlayerLastSongId');
            const lastSongTime = localStorage.getItem('musicPlayerLastSongTime');

            if (lastSongId !== null && playlist.length > 0) {
                const songIndexToResume = playlist.findIndex(s => s.id.toString() === lastSongId);
                if (songIndexToResume !== -1) {
                    currentSongIndex = songIndexToResume;
                    const song = playlist[currentSongIndex];
                    audio.src = song.file;

                    _updateMainPlayerUI(song); // Update UI to show this song is ready
                    updateMediaSession(song);

                    audio.onloadedmetadata = () => {
                        if (lastSongTime !== null) audio.currentTime = parseFloat(lastSongTime);
                        updateTimeDisplay();
                    };
                    const songItems = document.querySelectorAll('.song-item');
                    songItems.forEach((item, i) => item.classList.toggle('current-song', i === songIndexToResume));
                    showNotification(`Ready: ${Utils.escapeHTML(song.title)}. Press play.`, false);
                }
            }
        }

        async function fetchPlaylist() {
            UIElements.playlistElement.innerHTML = `
                <li class="text-center py-10">
                    <div class="spinner mx-auto mb-2"></div> <p>Loading playlist...</p>
                </li>`;
            try {
                const response = await fetch('index.php?action=getPlaylist');
                if (!response.ok) {
                    let errorMsg = "Failed to load playlist. Server error.";
                    try {
                        const errorData = await response.json();
                        errorMsg = errorData.error || errorMsg;
                    } catch (e) { /* Ignore if error response is not JSON */ }
                    throw new Error(errorMsg);
                }
                const data = await response.json();
                if (Array.isArray(data)) {
                    playlist = data;
                } else {
                    console.error("Playlist data is not an array:", data);
                    throw new Error("Received invalid playlist data from server.");
                }
            } catch (error) {
                console.error("Error fetching playlist:", error);
                playlist = [];
                showNotification(error.message || "Network error loading playlist.", true);
            } finally {
                updatePlaylistDisplay();
                _loadLastPlayedSong(); // Attempt to load last played song after playlist is processed
            }
        }

        function _initializePlaylistSortable() {
            if (sortableInstance) {
                sortableInstance.destroy();
            }
            if (playlist.length > 0 && UIElements.playlistElement) {
                sortableInstance = new Sortable(UIElements.playlistElement, {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    dragClass: 'sortable-drag',
                    onEnd: function (evt) {
                        const songIdsInOrder = Array.from(evt.target.children).map(item => item.dataset.songId);

                        fetch('index.php?action=updatePlaylistOrder', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ songIds: songIdsInOrder })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showNotification('Playlist order saved!');
                                const newPlaylist = songIdsInOrder.map(id => playlist.find(song => song.id.toString() === id));
                                playlist = newPlaylist.filter(song => song !== undefined);
                                if(currentSongIndex !== -1 && playlist[currentSongIndex]){
                                   const currentPlayingSongId = playlist[currentSongIndex].id; // This might be stale if item was moved
                                   currentSongIndex = playlist.findIndex(song => song.id.toString() === currentPlayingSongId.toString());
                                }
                            } else {
                                showNotification(data.error || 'Failed to save playlist order.', true);
                                // Re-fetch to revert optimistic UI (or more complex revert)
                                fetchPlaylist();
                            }
                        })
                        .catch(error => {
                            console.error('Error updating playlist order:', error);
                            showNotification('Error saving playlist order.', true);
                            fetchPlaylist(); // Re-fetch to revert
                        });
                    }
                });
            }
        }

        function createSongListItemHTML(song, index) {
            const { id, title, artist: songArtist, album, cover, duration } = song;
            const isActive = currentSongIndex === index;
            return `
                <li class="song-item p-3 rounded-lg transition-all ${isActive ? 'current-song' : ''}"
                     data-song-id="${id}" >
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-md overflow-hidden ${cover ? '' : 'default-cover'} cursor-pointer" onclick="playSong(${index})">
                            ${cover ? `<img src="${Utils.escapeHTML(cover)}" class="w-full h-full object-cover" alt="${Utils.escapeHTML(title)} cover art">`
                                   : `<i class="fas fa-music w-full h-full flex items-center justify-center" aria-hidden="true"></i>`}
                        </div>
                        <div class="flex-1 min-w-0 cursor-pointer" onclick="playSong(${index})">
                            <p class="font-medium truncate">${Utils.escapeHTML(title)}</p>
                            <p class="text-sm text-muted truncate">${Utils.escapeHTML(songArtist)}</p>
                            <p class="text-xs text-muted-light truncate">${album ? Utils.escapeHTML(album) : ''}</p>
                        </div>
                        <span class="text-xs text-muted-light pr-2">${Utils.formatTime(duration)}</span>
                        <button class="edit-song-btn text-muted-light hover:text-white p-1" data-song-id="${id}" aria-label="Edit ${Utils.escapeHTML(title)}">
                            <i class="fas fa-pencil-alt text-xs" aria-hidden="true"></i>
                        </button>
                    </div>
                </li>`;
        }

        function updatePlaylistDisplay() {
            if (playlist.length === 0) {
                UIElements.playlistElement.innerHTML = `
                    <li class="text-center py-10 text-muted no-songs-in-playlist-message">
                        <i class="fas fa-music text-3xl mb-2" aria-hidden="true"></i> <p>No songs in playlist</p>
                    </li>`;
                if (sortableInstance) {
                    sortableInstance.destroy();
                    sortableInstance = null;
                }
                return;
            }
            UIElements.playlistElement.innerHTML = playlist.map((song, index) => createSongListItemHTML(song, index)).join('');
            _initializePlaylistSortable();
        }

        // --- Playback Controls & UI ---
        function _updateMainPlayerUI(song) {
            if (!song) return;
            const { title, artist: songArtist, album, cover, lyrics } = song;
            UIElements.songTitle.textContent = Utils.escapeHTML(title);
            UIElements.artist.textContent = Utils.escapeHTML(songArtist);
            UIElements.currentAlbum.textContent = album ? Utils.escapeHTML(album) : '-';
            UIElements.songTitle.innerHTML = title.length > 20 ? `<span class="marquee">${Utils.escapeHTML(title)}</span>` : Utils.escapeHTML(title);
            UIElements.coverArt.innerHTML = cover ? `<img src="${Utils.escapeHTML(cover)}" class="w-full h-full object-cover" alt="${Utils.escapeHTML(title)} cover">` : `<i class="fas fa-music text-5xl"></i>`;
            UIElements.lyricsText.textContent = lyrics ? Utils.escapeHTML(lyrics) : "No lyrics available for this song.";
        }

        async function playSong(index) {
            if (index < 0 || index >= playlist.length) return;
            currentSongIndex = index;
            const song = playlist[index];

            _updateMainPlayerUI(song); // Update main player display
            localStorage.setItem('musicPlayerLastSongId', song.id); // Save last played song ID

            document.querySelectorAll('.song-item').forEach((item, i) => {
                item.classList.toggle('current-song', i === index);
            });

            updateMediaSession(song);
            audio.src = song.file;
            audio.load();
            try {
                await audio.play();
                // UI updates for play/pause state are handled by audio event listeners
                showNotification(`Now playing: ${Utils.escapeHTML(song.title)}`);
            } catch (error) {
                console.error("Play error:", error);
                showNotification('Audio playback error. Click anywhere to try initializing audio.', true);
            }
        }

        async function togglePlayPause() {
            if (playlist.length === 0) {
                showNotification('No songs in playlist.', true);
                return;
            }
            if (currentSongIndex === -1) {
                await playSong(0); // Play first song if none selected
                return;
            }
            if (audio.paused) {
                try {
                    await audio.play();
                    // UI updates handled by audio 'play' event listener
                } catch (error) {
                    console.error("Play error:", error);
                    showNotification('Could not play audio.', true);
                }
            } else {
                audio.pause();
                // UI updates handled by audio 'pause' event listener
            }
        }

        function prevSong() {
            if (playlist.length === 0) return;
            let newIndex = state.isShuffled ? Math.floor(Math.random() * playlist.length) : currentSongIndex - 1;
            if (newIndex < 0) newIndex = playlist.length - 1;
            playSong(newIndex);
        }

        function nextSong() {
            if (playlist.length === 0) return;
            let newIndex = state.isShuffled ? Math.floor(Math.random() * playlist.length) : currentSongIndex + 1;
            if (newIndex >= playlist.length) newIndex = 0;
            playSong(newIndex);
        }

        // UI Updates & Helpers
        function formatTime(seconds = 0) { // Default parameter
            if (isNaN(seconds)) seconds = 0;
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        function updateTimeDisplay() {
            if (isNaN(audio.duration) || state.isDraggingProgress) return;
            const progressPercent = (audio.currentTime / audio.duration) * 100;
            UIElements.progress.style.width = `${progressPercent}%`;
            UIElements.currentTimeDisplay.textContent = Utils.formatTime(audio.currentTime);
            UIElements.durationDisplay.textContent = Utils.formatTime(audio.duration);
            UIElements.progressBar.setAttribute('aria-valuenow', progressPercent.toFixed(0));
            UIElements.progressBar.setAttribute('aria-valuetext', `Song progress: ${Utils.formatTime(audio.currentTime)} of ${Utils.formatTime(audio.duration)}`);

            // Save current time for "last played position"
            if (!state.isDraggingProgress && audio.currentTime > 0 && currentSongIndex !== -1) {
                localStorage.setItem('musicPlayerLastSongTime', audio.currentTime.toString());
            }
        }

        function showNotification(message, isError = false) {
            const { toast } = UIElements;
            toast.textContent = message;
            toast.className = `fixed bottom-4 right-4 p-4 rounded-lg shadow-lg text-white ${
                isError ? 'bg-red-500' : 'bg-green-500'
            } show`;
            clearTimeout(toast.timeoutId);
            toast.timeoutId = setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Media Session & Visualizer
        function setupMediaSession() {
            if (!('mediaSession' in navigator)) return;
            navigator.mediaSession.setActionHandler('play', togglePlayPause);
            navigator.mediaSession.setActionHandler('pause', togglePlayPause);
            navigator.mediaSession.setActionHandler('previoustrack', prevSong);
            navigator.mediaSession.setActionHandler('nexttrack', nextSong);
            UIElements.miniPlayer.addEventListener('click', () => {
                document.querySelector('.player-container').scrollIntoView({ behavior: 'smooth' });
            });
        }

        function updateMediaSession(song) {
            if (!('mediaSession' in navigator) || !song) return;
            const { title, artist: songArtist, album, cover } = song;
            navigator.mediaSession.metadata = new MediaMetadata({
                title: Utils.escapeHTML(title),
                artist: Utils.escapeHTML(songArtist),
                album: album ? Utils.escapeHTML(album) : 'Unknown Album',
                artwork: cover ? [
                    { src: Utils.escapeHTML(cover), sizes: '96x96', type: 'image/jpeg' },
                    { src: Utils.escapeHTML(cover), sizes: '128x128', type: 'image/jpeg' },
                    { src: cover, sizes: '192x192', type: 'image/jpeg' },
                    { src: cover, sizes: '256x256', type: 'image/jpeg' },
                    { src: cover, sizes: '384x384', type: 'image/jpeg' },
                    { src: cover, sizes: '512x512', type: 'image/jpeg' }
                ] : []
            });
        }

        function createWaveformBars() {
            UIElements.waveform.innerHTML = ''; // Clear existing bars
            waveformBars = []; // Reset array
            for (let i = 0; i < 16; i++) { // Number of bars
                const bar = document.createElement('div');
                bar.className = 'waveform-bar';
                bar.style.animationDelay = `${i * 50}ms`; // Stagger animation
                UIElements.waveform.appendChild(bar);
                waveformBars.push(bar);
            }
        }

        function visualize() {
            if (!analyser || !UIElements.waveform.classList.contains('playing')) {
                // Stop visualization if not playing or analyser not ready
                waveformBars.forEach(bar => bar.style.height = `10%`); // Reset bars
                requestAnimationFrame(visualize); // Still need to keep the loop going
                return;
            }

            analyser.getByteFrequencyData(dataArray);
            waveformBars.forEach((bar, i) => {
                const value = dataArray[i % dataArray.length] / 255; // Use modulo for safety if bars > dataArray.length
                const height = 10 + (value * 90); // Scale height (10% to 100%)
                bar.style.height = `${height}%`;
            });
            requestAnimationFrame(visualize);
        }

        // Event Handlers Setup
        function bindEventListeners() {
            UIElements.playBtn.addEventListener('click', togglePlayPause);
            UIElements.prevBtn.addEventListener('click', prevSong);
            UIElements.nextBtn.addEventListener('click', nextSong);

            UIElements.shuffleBtn.addEventListener('click', () => {
                state.isShuffled = !state.isShuffled;
                UIElements.shuffleBtn.classList.toggle('text-white', state.isShuffled);
                UIElements.shuffleBtn.classList.toggle('text-muted-light', !state.isShuffled); // Use themed muted class
                localStorage.setItem('musicPlayerShuffle', state.isShuffled ? 'true' : 'false');
                showNotification(state.isShuffled ? 'Shuffle on' : 'Shuffle off');
                // If shuffle turned off, might want to revert to original fetched order (or last saved manual order)
                // For now, it just stops random selection for 'next'. If playlist was shuffled client-side, it remains so.
                if (!state.isShuffled && playlist.length > 0) {
                     fetchPlaylist(); // Re-fetch to get server-defined order
                }
            });

            UIElements.repeatBtn.addEventListener('click', () => {
                state.isRepeating = !state.isRepeating;
                UIElements.repeatBtn.classList.toggle('text-white', state.isRepeating);
                UIElements.repeatBtn.classList.toggle('text-muted-light', !state.isRepeating); // Use themed muted class
                localStorage.setItem('musicPlayerRepeat', state.isRepeating ? 'true' : 'false');
                showNotification(state.isRepeating ? 'Repeat on' : 'Repeat off');
            });

            UIElements.progressBar.addEventListener('click', (e) => {
                if (isNaN(audio.duration)) return;
                const rect = UIElements.progressBar.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                audio.currentTime = pos * audio.duration;
            });

            // Progress bar drag handling
            const stopDragging = () => {
                if (state.isDraggingProgress && !isNaN(audio.duration)) {
                    const pos = parseFloat(UIElements.progress.style.width) / 100;
                    audio.currentTime = pos * audio.duration;
                    state.isDraggingProgress = false;
                }
            };
            UIElements.progressBar.addEventListener('mousedown', () => { state.isDraggingProgress = true; });
            document.addEventListener('mousemove', (e) => {
                if (!state.isDraggingProgress || isNaN(audio.duration)) return;
                const rect = UIElements.progressBar.getBoundingClientRect();
                let pos = (e.clientX - rect.left) / rect.width;
                pos = Math.min(Math.max(pos, 0), 1);
                UIElements.progress.style.width = `${pos * 100}%`;
                UIElements.currentTimeDisplay.textContent = Utils.formatTime(pos * audio.duration);
            });
            document.addEventListener('mouseup', stopDragging);
            document.addEventListener('mouseleave', stopDragging);

            audio.addEventListener('timeupdate', updateTimeDisplay);
            audio.addEventListener('ended', () => { state.isRepeating ? playSong(currentSongIndex) : nextSong(); });
            audio.addEventListener('play', () => {
            UIElements.playBtn.innerHTML = '<i class="fas fa-pause text-2xl" aria-hidden="true"></i>';
            UIElements.playBtn.setAttribute('aria-label', 'Pause song');
                state.isPlaying = true;
                UIElements.waveform.classList.add('playing');
            UIElements.miniPlayer.innerHTML = `<i class="fas fa-pause" aria-hidden="true"></i>`;
            });
            audio.addEventListener('pause', () => {
            UIElements.playBtn.innerHTML = '<i class="fas fa-play text-2xl" aria-hidden="true"></i>';
            UIElements.playBtn.setAttribute('aria-label', 'Play song');
                state.isPlaying = false;
                UIElements.waveform.classList.remove('playing');
            UIElements.miniPlayer.innerHTML = `<i class="fas fa-play" aria-hidden="true"></i>`;
            });

            UIElements.volumeSlider.addEventListener('input', (e) => {
                audio.volume = e.target.value;
                state.volume = e.target.value;
                localStorage.setItem('musicPlayerVolume', e.target.value);
                e.target.setAttribute('aria-valuetext', `Volume: ${Math.round(e.target.value * 100)}%`);
            });

            // Modal Controls - openModal and closeModal are already defined and handle transitions
            UIElements.lyricsBtn.addEventListener('click', () => { openModal(UIElements.lyricsModal); });
            if(UIElements.closeLyricsModalBtn) UIElements.closeLyricsModalBtn.addEventListener('click', () => { closeModal(UIElements.lyricsModal); }); // Ensure this ID is correct
            else if(UIElements.closeLyricsBtn) UIElements.closeLyricsBtn.addEventListener('click', () => { closeModal(UIElements.lyricsModal); });


            UIElements.uploadBtn.addEventListener('click', () => { openModal(UIElements.uploadModal); });
            UIElements.cancelBtn.addEventListener('click', () => { closeModal(UIElements.uploadModal); }); // For main upload modal
            UIElements.cancelUploadBtn.addEventListener('click', () => { closeModal(UIElements.uploadModal); }); // For main upload modal

            // Edit Song Modal Controls
            UIElements.cancelEditSongBtn.addEventListener('click', () => { closeModal(UIElements.editSongModal); });
            UIElements.closeEditSongModalBtn.addEventListener('click', () => { closeModal(UIElements.editSongModal); });

            // Event delegation for edit buttons on playlist items
            UIElements.playlistElement.addEventListener('click', function(event) {
                const editButton = event.target.closest('.edit-song-btn');
                if (editButton) {
                    const songId = editButton.dataset.songId;
                    const songToEdit = playlist.find(s => s.id.toString() === songId);
                    if (songToEdit) {
                        UIElements.editSongIdInput.value = songToEdit.id;
                        UIElements.editSongTitleInput.value = songToEdit.title;
                        UIElements.editSongArtistInput.value = songToEdit.artist;
                        UIElements.editSongAlbumInput.value = songToEdit.album || '';
                        openModal(UIElements.editSongModal);
                    }
                }
            });

            // File Input Handlers
            UIElements.coverInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                UIElements.coverFileName.textContent = file.name;
                UIElements.coverPreview.classList.remove('hidden');
                const reader = new FileReader();
                reader.onload = (event) => { UIElements.coverPreviewImg.src = event.target.result; };
                reader.readAsDataURL(file);
            });
            UIElements.songInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                UIElements.songFileName.textContent = file.name;
                // Optional: const tempAudio = new Audio(URL.createObjectURL(file)); tempAudio.onloadedmetadata = () => { console.log(tempAudio.duration); /* store if needed */ };
            });

            // Upload Form Submission
            UIElements.uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = UIElements.uploadForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<div class="spinner mr-2"></div> Uploading...';
                submitBtn.disabled = true;

                const formData = new FormData(e.target);
                try {
                    const response = await fetch('index.php?action=uploadSong', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (!response.ok) { // Check for HTTP errors (4xx, 5xx)
                        throw new Error(result.error || `Server error: ${response.status}`);
                    }

                    if (result.success && result.song) {
                        playlist.push(result.song);
                        updatePlaylistDisplay(); // Refresh playlist display
                        closeModal(UIElements.uploadModal);
                        UIElements.uploadForm.reset();
                        UIElements.coverPreview.classList.add('hidden');
                        UIElements.coverFileName.textContent = 'Choose cover image';
                        UIElements.songFileName.textContent = 'Choose MP3 file';
                        showNotification('Song uploaded successfully!');
                    } else {
                        // Server responded with success:false or missing song data
                        throw new Error(result.error || 'Upload failed due to an unknown server issue.');
                    }
                } catch (error) {
                    console.error("Upload error:", error);
                    showNotification(error.message || 'Network error or server unavailable.', true);
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });

            UIElements.editSongForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const submitBtn = UIElements.editSongForm.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<div class="spinner mr-2"></div> Saving...';
                submitBtn.disabled = true;

                const songId = UIElements.editSongIdInput.value;
                const title = UIElements.editSongTitleInput.value;
                const artist = UIElements.editSongArtistInput.value;
                const album = UIElements.editSongAlbumInput.value;

                try {
                    const response = await fetch('index.php?action=updateSongMetadata', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ songId, title, artist, album })
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.error || `Server error: ${response.status}`);
                    }

                    if (result.success) {
                        closeModal(UIElements.editSongModal);
                        showNotification('Song details updated successfully!');

                        // Update data in the local playlist array
                        const songIndex = playlist.findIndex(s => s.id.toString() === songId);
                        if (songIndex !== -1) {
                            playlist[songIndex].title = title;
                            playlist[songIndex].artist = artist;
                            playlist[songIndex].album = album;

                            // If the edited song is currently playing, update the main player UI
                            if (currentSongIndex === songIndex) {
                                UIElements.songTitle.textContent = Utils.escapeHTML(title);
                                UIElements.artist.textContent = Utils.escapeHTML(artist);
                                UIElements.currentAlbum.textContent = album ? Utils.escapeHTML(album) : '-';
                                if (title.length > 20) {
                                   UIElements.songTitle.innerHTML = `<span class="marquee">${Utils.escapeHTML(title)}</span>`;
                                }
                                updateMediaSession(playlist[currentSongIndex]);
                            }
                        }
                        updatePlaylistDisplay();
                    } else {
                        throw new Error(result.error || 'Failed to update song details.');
                    }
                } catch (error) {
                    console.error("Error updating song metadata:", error);
                    showNotification(error.message || 'Error updating details.', true);
                } finally {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            });


            UIElements.refreshBtn.addEventListener('click', async () => {
                UIElements.refreshBtn.innerHTML = '<i class="fas fa-sync-alt animate-spin"></i>';
                await fetchPlaylist();
                // updatePlaylistDisplay is called in fetchPlaylist's finally block
                UIElements.refreshBtn.innerHTML = '<i class="fas fa-sync-alt" aria-hidden="true"></i>';
                showNotification('Playlist refreshed');
            });

            if (UIElements.themeToggleBtn) {
                UIElements.themeToggleBtn.addEventListener('click', () => {
                    document.body.classList.toggle('light-theme');
                    const isLight = document.body.classList.contains('light-theme');
                    localStorage.setItem('musicPlayerTheme', isLight ? 'light' : 'dark');
                    UIElements.themeToggleBtn.innerHTML = `<i class="fas ${isLight ? 'fa-moon' : 'fa-sun'}" aria-hidden="true"></i>`;
                });
            }

            if (UIElements.playlistSearchInput) {
                UIElements.playlistSearchInput.addEventListener('input', handlePlaylistSearch);
            }

            if (UIElements.viewLicenseLink) {
                UIElements.viewLicenseLink.addEventListener('click', async (event) => {
                    event.preventDefault();
                    UIElements.licenseTextContainer.innerHTML = '<p>Loading license...</p>';
                    openModal(UIElements.licenseModal);
                    try {
                        const response = await fetch('LICENSE');
                        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                        const licenseText = await response.text();
                        UIElements.licenseTextContainer.innerHTML = `<pre class="whitespace-pre-wrap">${Utils.escapeHTML(licenseText)}</pre>`;
                    } catch (error) {
                        console.error('Error fetching license:', error);
                        UIElements.licenseTextContainer.innerHTML = '<p class="text-red-400">Could not load license information.</p>';
                    }
                });
            }
            // Ensure correct close button for license modal
            const actualCloseLicenseModalBtn = document.getElementById('closeLicenseModalBtn'); // Could be different from UIElements.closeLyricsBtn
            if (actualCloseLicenseModalBtn) {
                actualCloseLicenseModalBtn.addEventListener('click', () => {
                    closeModal(UIElements.licenseModal);
                });
            }
        }

        function handlePlaylistSearch(event) {
            const searchTerm = event.target.value.toLowerCase().trim();
            const songItems = UIElements.playlistElement.getElementsByTagName('li');
            let visibleCount = 0;

            const existingNoResultsMessage = UIElements.playlistElement.querySelector('.no-results-message');
            if (existingNoResultsMessage) existingNoResultsMessage.remove();

            for (let item of songItems) {
                if (item.classList.contains('no-songs-in-playlist-message')) {
                    if (playlist.length === 0) item.style.display = '';
                    else item.style.display = searchTerm === '' ? '' : 'none';
                    if(item.style.display === '') visibleCount++;
                    continue;
                }
                if (!item.dataset.songId) continue;

                const songId = item.dataset.songId;
                const song = playlist.find(s => s.id.toString() === songId);

                if (song) {
                    const title = (song.title || '').toLowerCase();
                    const artist = (song.artist || '').toLowerCase();
                    const album = (song.album || '').toLowerCase();

                    if (title.includes(searchTerm) || artist.includes(searchTerm) || album.includes(searchTerm)) {
                        item.style.display = '';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                }
            }

            if (visibleCount === 0 && searchTerm !== '' && playlist.length > 0) {
                let messageLi = document.createElement('li');
                messageLi.className = 'text-center py-10 text-muted no-results-message';
                messageLi.innerHTML = `<i class="fas fa-search text-3xl mb-2" aria-hidden="true"></i><p>No songs match "${Utils.escapeHTML(searchTerm)}"</p>`;
                UIElements.playlistElement.appendChild(messageLi);
            }
        }

        // --- Main Setup ---
        window.addEventListener('DOMContentLoaded', init);
        // Expose playSong globally for inline onclick attributes in playlist items
        window.playSong = playSong;
    </script>
</body>
</html>
