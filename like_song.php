<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the request contains the necessary parameters
if (!isset($_POST['song_id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$song_id = (int)$_POST['song_id'];
$action = $_POST['action'];

// Validate song_id
if ($song_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid song ID']);
    exit();
}

if ($action === 'like') {
    // Check if song exists in songs table
    $song_check = mysqli_query($conn, "SELECT song_id FROM songs WHERE song_id = $song_id");
    if (!$song_check || mysqli_num_rows($song_check) === 0) {
        echo json_encode(['success' => false, 'message' => 'Song not found']);
        exit();
    }
    
    // Check if song is already liked
    $check_sql = "SELECT * FROM liked_songs WHERE user_id = $user_id AND song_id = $song_id";
    $check_result = mysqli_query($conn, $check_sql);
    
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        // Song is already liked
        echo json_encode(['success' => true, 'action' => 'none', 'message' => 'Song is already liked']);
        exit();
    }
    
    // Like the song
    $like_sql = "INSERT INTO liked_songs (user_id, song_id, created_at) VALUES ($user_id, $song_id, NOW())";
    if (mysqli_query($conn, $like_sql)) {
        echo json_encode(['success' => true, 'action' => 'liked', 'message' => 'Song liked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
} elseif ($action === 'unlike') {
    // Unlike the song
    $unlike_sql = "DELETE FROM liked_songs WHERE user_id = $user_id AND song_id = $song_id";
    if (mysqli_query($conn, $unlike_sql)) {
        echo json_encode(['success' => true, 'action' => 'unliked', 'message' => 'Song unliked successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . mysqli_error($conn)]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>