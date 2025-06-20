<?php
// php/api.php
require_once 'database.php';

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
