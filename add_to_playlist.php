<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to add songs to playlists']);
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false];

// Handle adding song to playlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['song_id']) && isset($_POST['playlist_id'])) {
    $song_id = (int)$_POST['song_id'];
    $playlist_id = (int)$_POST['playlist_id'];
    
    // Verify playlist belongs to user
    $verify_query = "SELECT * FROM playlists WHERE playlist_id = $playlist_id AND user_id = $user_id";
    $verify_result = mysqli_query($conn, $verify_query);
    
    if (mysqli_num_rows($verify_result) > 0) {
        // Check if song is already in playlist
        $check_query = "SELECT * FROM playlist_songs WHERE playlist_id = $playlist_id AND song_id = $song_id";
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $response = ['success' => false, 'message' => 'Song is already in this playlist'];
        } else {
            // Get the highest position in the playlist
            $position_query = "SELECT MAX(position) as max_pos FROM playlist_songs WHERE playlist_id = $playlist_id";
            $position_result = mysqli_query($conn, $position_query);
            $position_data = mysqli_fetch_assoc($position_result);
            $position = ($position_data['max_pos'] !== null) ? $position_data['max_pos'] + 1 : 1;
            
            // Add song to playlist
            $insert_query = "INSERT INTO playlist_songs (playlist_id, song_id, position) VALUES ($playlist_id, $song_id, $position)";
            
            if (mysqli_query($conn, $insert_query)) {
                $response = ['success' => true, 'message' => 'Song added to playlist successfully'];
            } else {
                $response = ['success' => false, 'message' => 'Error adding song to playlist'];
            }
        }
    } else {
        $response = ['success' => false, 'message' => 'You do not have permission to modify this playlist'];
    }
}

echo json_encode($response);
?>