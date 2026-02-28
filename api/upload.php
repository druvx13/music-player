<?php
// api/upload.php — handles song and cover-art uploads
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Resolve upload directory relative to the project root
$uploadDir = realpath(__DIR__ . '/../uploads') . '/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── Song file ────────────────────────────────────────────────────────────────
if (!isset($_FILES['song']) || $_FILES['song']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No song file provided']);
    exit;
}

$songFile = $_FILES['song'];

// Validate MIME type (not just extension)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$songMime = $finfo->file($songFile['tmp_name']);
if ($songMime !== 'audio/mpeg') {
    echo json_encode(['error' => 'Only MP3 (audio/mpeg) files are allowed']);
    exit;
}

// Use a random hex name to avoid collisions and path-traversal risks
$songFilename = bin2hex(random_bytes(16)) . '.mp3';
$songPath     = $uploadDir . $songFilename;
$songURL      = 'uploads/' . $songFilename;

if (!move_uploaded_file($songFile['tmp_name'], $songPath)) {
    echo json_encode(['error' => 'Song upload failed']);
    exit;
}

// ── Cover image (optional) ───────────────────────────────────────────────────
$coverURL = '';
if (isset($_FILES['cover']) && $_FILES['cover']['error'] === UPLOAD_ERR_OK) {
    $coverFile  = $_FILES['cover'];
    $coverMime  = $finfo->file($coverFile['tmp_name']);
    $mimeExtMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    if (isset($mimeExtMap[$coverMime])) {
        $coverFilename = bin2hex(random_bytes(16)) . '.' . $mimeExtMap[$coverMime];
        $coverPath     = $uploadDir . $coverFilename;

        if (move_uploaded_file($coverFile['tmp_name'], $coverPath)) {
            $coverURL = 'uploads/' . $coverFilename;
        }
    }
}

// ── Database insert ──────────────────────────────────────────────────────────
$conn   = getDbConnection();
$title  = trim($_POST['title']  ?? pathinfo($songFile['name'], PATHINFO_FILENAME));
$artist = trim($_POST['artist'] ?? 'Unknown Artist');
$lyrics = trim($_POST['lyrics'] ?? '');

$stmt = $conn->prepare(
    'INSERT INTO songs (title, file, cover, artist, lyrics) VALUES (?, ?, ?, ?, ?)'
);
$stmt->bind_param('sssss', $title, $songURL, $coverURL, $artist, $lyrics);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    // Roll back uploaded files so the server stays clean
    @unlink($songPath);
    if ($coverURL !== '') {
        @unlink($uploadDir . basename($coverURL));
    }
    echo json_encode(['error' => 'Database error']);
    exit;
}

$newId = (int) $stmt->insert_id;
$stmt->close();

// Return the full newly-inserted song row so the client can add it immediately
$stmt = $conn->prepare(
    'SELECT id, title, artist, file, cover, lyrics FROM songs WHERE id = ?'
);
$stmt->bind_param('i', $newId);
$stmt->execute();
$song = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'song' => $song]);
