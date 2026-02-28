<?php
// api/playlist.php — returns all songs as a JSON array
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$conn = getDbConnection();
$result = $conn->query(
    'SELECT id, title, artist, file, cover, lyrics FROM songs ORDER BY uploaded_at DESC'
);

$songs = [];
while ($row = $result->fetch_assoc()) {
    $songs[] = $row;
}

echo json_encode($songs);
$conn->close();
