<?php
// src/api.php
// Main API handler for the music player.

// Include database connection helper
require_once __DIR__ . '/config/db.php';

// --- Helper Functions ---

/**
 * Sends a JSON response.
 * @param mixed $data Data to encode as JSON.
 * @param int $statusCode HTTP status code.
 */
function send_json_response($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

/**
 * Handles file uploads.
 * @param array $file The $_FILES entry for the uploaded file.
 * @param string $type 'song' or 'cover'.
 * @param mysqli $conn Database connection.
 * @return string|null The path to the uploaded file or null on failure.
 */
function handle_file_upload(array $file, string $type, mysqli $conn): ?string {
    // Use UPLOADS_DIR from config, default to 'uploads/'
    // The path stored in DB will be relative to the public directory.
    // The actual file system path needs to be relative to this script's location (src/)
    // or an absolute path. For simplicity, we'll construct it relative to the public dir,
    // assuming api.php is called from public/index.php or similar context where DOC_ROOT is public.
    $publicUploadsDir = defined('UPLOADS_DIR') ? UPLOADS_DIR : 'uploads/';
    $filesystemUploadDir = __DIR__ . '/../public/' . $publicUploadsDir; // Adjusted path for filesystem operations

    if (!is_dir($filesystemUploadDir)) {
        if (!mkdir($filesystemUploadDir, 0775, true)) { // Use 0775 for better security
            send_json_response(["error" => "Failed to create upload directory."], 500);
            return null;
        }
    }

    $fileName = basename($file['name']);
    $targetFile = $filesystemUploadDir . $fileName;
    $fileExtension = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $dbFilePath = $publicUploadsDir . $fileName; // Path to store in DB

    // Validate file type
    if ($type === 'song') {
        if ($fileExtension !== "mp3") {
            send_json_response(["error" => "Only MP3 files are allowed for songs."], 400);
            return null;
        }
    } elseif ($type === 'cover') {
        $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedImageTypes)) {
            send_json_response(["error" => "Invalid cover image format. Allowed: JPG, JPEG, PNG, GIF."], 400);
            return null;
        }
    }

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        send_json_response(["error" => "File upload error code: " . $file['error']], 500);
        return null;
    }

    // Check if file already exists (optional, can be handled by overwriting or renaming)
    // For simplicity, we'll overwrite.
    // if (file_exists($targetFile)) {
    //     send_json_response(["error" => "File already exists: " . $fileName], 409);
    //     return null;
    // }

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return $dbFilePath; // Return the path relative to public for DB storage
    } else {
        send_json_response(["error" => ucfirst($type) . " upload failed. Check server logs and permissions for " . $filesystemUploadDir], 500);
        return null;
    }
}


// --- API Endpoint Logic ---

$action = $_GET['action'] ?? null;

if ($action === null) {
    send_json_response(["error" => "No action specified."], 400);
}

$conn = get_db_connection();

if ($conn === null) {
    // get_db_connection() already logs the error.
    // If it returns null, it means config is missing or connection failed.
    send_json_response(["error" => "Database connection failed. Please check server configuration and logs."], 503);
}


switch ($action) {
    case 'getPlaylist':
        try {
            $sql = "SELECT id, title, file, cover, artist, lyrics, uploaded_at FROM songs ORDER BY uploaded_at DESC";
            $result = $conn->query($sql);
            if (!$result) {
                 send_json_response(["error" => "Failed to retrieve playlist: " . $conn->error], 500);
            }
            $songs = [];
            while ($row = $result->fetch_assoc()) {
                // Ensure file paths are correctly prefixed if needed, or are stored as web-accessible paths.
                // Assuming 'file' and 'cover' are stored as paths relative to the web root (e.g., 'uploads/song.mp3')
                $songs[] = $row;
            }
            send_json_response($songs);
        } catch (Exception $e) {
            error_log("Error in getPlaylist: " . $e->getMessage());
            send_json_response(["error" => "An internal server error occurred while fetching the playlist."], 500);
        }
        break;

    case 'uploadSong':
        // Check if request method is POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            send_json_response(["error" => "Invalid request method. Only POST is allowed for uploads."], 405);
        }

        $songURL = null;
        $coverURL = null; // Initialize as null, not empty string

        // Process song file upload
        if (isset($_FILES['song']) && $_FILES['song']['error'] !== UPLOAD_ERR_NO_FILE) {
            $songURL = handle_file_upload($_FILES['song'], 'song', $conn);
            if ($songURL === null) {
                // handle_file_upload already sent a response and exited.
                // This part should not be reached if upload failed.
                // However, as a safeguard:
                if (http_response_code() === 200) { // if handle_file_upload didn't send error for some reason
                     send_json_response(["error" => "Song upload processing failed."], 500);
                }
                exit;
            }
        } else {
            send_json_response(["error" => "No song file provided or file upload error."], 400);
        }

        // Process cover image upload (optional)
        if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['cover']['error'] === UPLOAD_ERR_OK) { // Ensure there's no other error like size exceeded
                $coverURL = handle_file_upload($_FILES['cover'], 'cover', $conn);
                 if ($coverURL === null && http_response_code() === 200) {
                    send_json_response(["error" => "Cover upload processing failed."], 500);
                    exit;
                }
            } else {
                // Handle other potential upload errors for cover if needed, e.g., log them
                error_log("Cover upload error code: " . $_FILES['cover']['error']);
                // Don't necessarily fail the whole request if cover upload has an issue but song is fine,
                // unless cover is mandatory. For now, it's optional.
            }
        }

        // Get additional data from the POST request
        $songNameFromUpload = isset($_FILES['song']['name']) ? basename($_FILES['song']['name']) : 'Unknown Song';
        $title = $_POST['title'] ?? $songNameFromUpload;
        $artist = $_POST['artist'] ?? 'Unknown Artist'; // Changed default
        $lyrics = $_POST['lyrics'] ?? '';

        // Sanitize inputs for database
        $escapedTitle = $conn->real_escape_string($title);
        $escapedArtist = $conn->real_escape_string($artist);
        $escapedLyrics = $conn->real_escape_string($lyrics);
        // $songURL and $coverURL are paths, generally safe but ensure they are what's expected.

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO songs (title, file, cover, artist, lyrics) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            send_json_response(["error" => "Database statement preparation failed: " . $conn->error], 500);
        }

        // Bind parameters: s for string. If coverURL is null, it will be inserted as NULL.
        $stmt->bind_param("sssss", $escapedTitle, $songURL, $coverURL, $escapedArtist, $escapedLyrics);

        if ($stmt->execute()) {
            $newSongId = $stmt->insert_id;
            // Fetch the newly inserted song to return it in the response
            $newSongQuery = $conn->query("SELECT id, title, file, cover, artist, lyrics, uploaded_at FROM songs WHERE id = $newSongId");
            if ($newSongQuery && $newSong = $newSongQuery->fetch_assoc()) {
                 send_json_response([
                    "success" => "Song uploaded successfully.",
                    "song" => $newSong // Send back the full song object
                ]);
            } else {
                 send_json_response(["success" => "Song uploaded successfully, but could not retrieve new song details."]);
            }
        } else {
            send_json_response(["error" => "Database error during song insertion: " . $stmt->error], 500);
        }
        $stmt->close();
        break;

    default:
        send_json_response(["error" => "Invalid action specified: " . htmlspecialchars($action)], 400);
        break;
}

if ($conn) {
    $conn->close();
}
?>
