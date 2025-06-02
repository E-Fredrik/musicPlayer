<?php
// Start session
session_start();

include 'sqlConnect.php';

// Check if user is logged in
$logged_in = isset($_SESSION['user_id']);

// Redirect to login if not logged in
if (!$logged_in) {
    header('Location: login.php');
    exit();
}

// Profile picture logic
$profile_picture = 'uploads/profiles/default_profile.jpg'; // Default profile picture
if ($logged_in) {
    $profile_query = "SELECT profile_picture FROM users WHERE user_id = " . $_SESSION['user_id'];
    $profile_result = mysqli_query($conn, $profile_query);
    if ($profile_result && mysqli_num_rows($profile_result) > 0) {
        $profile_data = mysqli_fetch_assoc($profile_result);
        if (!empty($profile_data['profile_picture'])) {
            $profile_picture = $profile_data['profile_picture'];
        }
    }
}

$message = '';
$error = '';

// Function to get audio duration (requires getID3 library)
function getAudioDuration($filePath)
{
    // In a production environment, use getID3 library
    // Here's a placeholder function that simulates getting duration
    // You would need to install getID3 library: https://github.com/JamesHeinrich/getID3

    // Sample implementation with getID3:
    /*
    require_once 'path/to/getid3/getid3.php';
    $getID3 = new getID3;
    $fileInfo = $getID3->analyze($filePath);
    if (isset($fileInfo['playtime_seconds'])) {
        return round($fileInfo['playtime_seconds']);
    }
    */

    // For now, attempt to get duration using native methods
    if (function_exists('shell_exec')) {
        // Try using ffmpeg command line
        $command = "ffmpeg -i \"$filePath\" 2>&1";
        $output = shell_exec($command);
        if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})/', $output, $matches)) {
            $hours = (int)$matches[1];
            $minutes = (int)$matches[2];
            $seconds = (int)$matches[3];
            return $hours * 3600 + $minutes * 60 + $seconds;
        }
    }

    // Fallback - ask user to input duration manually
    return 180; // Default to 3 minutes as fallback
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if this is a delete request first
    if (isset($_POST['delete_song']) && isset($_POST['delete_song_id'])) {
        // Handle song deletion
        $song_id = (int)$_POST['delete_song_id'];
        
        // Check if song belongs to current user
        $check_query = "SELECT * FROM songs WHERE song_id = $song_id AND uploaded_by = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $song_data = mysqli_fetch_assoc($check_result);
            
            mysqli_begin_transaction($conn);
            
            try {
                // Remove song from all playlists
                mysqli_query($conn, "DELETE FROM playlist_songs WHERE song_id = $song_id");
                
                // Remove song from liked songs
                mysqli_query($conn, "DELETE FROM liked_songs WHERE song_id = $song_id");
                
                // Remove song artist relationships
                mysqli_query($conn, "DELETE FROM song_artists WHERE song_id = $song_id");
                
                // Delete the song itself
                mysqli_query($conn, "DELETE FROM songs WHERE song_id = $song_id");
                
                // Commit transaction
                mysqli_commit($conn);
                
                // Delete the actual file
                if (!empty($song_data['file_path']) && file_exists($song_data['file_path'])) {
                    unlink($song_data['file_path']);
                }
                
                // Delete cover art if it's not the default and it exists
                if (!empty($song_data['cover_art']) && $song_data['cover_art'] != 'uploads/covers/default_cover.jpg' && file_exists($song_data['cover_art'])) {
                    unlink($song_data['cover_art']);
                }
                
                $message = "Song deleted successfully!";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Failed to delete song: " . $e->getMessage();
            }
        } else {
            $error = "You don't have permission to delete this song.";
        }
    } 
    // Handle song editing
    else if (isset($_POST['edit_song']) && isset($_POST['edit_song_id'])) {
        // Handle song edit
        $song_id = (int)$_POST['edit_song_id'];
        $song_title = mysqli_real_escape_string($conn, $_POST['edit_song_title']);
        
        // Check if song belongs to current user
        $check_query = "SELECT * FROM songs WHERE song_id = $song_id AND uploaded_by = " . $_SESSION['user_id'];
        $check_result = mysqli_query($conn, $check_query);
        
        if (mysqli_num_rows($check_result) > 0) {
            $song_data = mysqli_fetch_assoc($check_result);
            $updateFields = [];
            
            // Always update title
            $updateFields[] = "title = '$song_title'";
            
            // Process cover image if uploaded
            if (isset($_FILES["edit_cover_art"]) && $_FILES["edit_cover_art"]["error"] == 0) {
                $coverDir = "uploads/covers/";
                
                // Create directory if it doesn't exist
                if (!file_exists($coverDir)) {
                    mkdir($coverDir, 0777, true);
                }
                
                $coverFileName = uniqid() . "_" . basename($_FILES["edit_cover_art"]["name"]);
                $coverTargetFile = $coverDir . $coverFileName;
                $coverFileType = strtolower(pathinfo($coverTargetFile, PATHINFO_EXTENSION));
                
                // Check if image file is an actual image
                $check = getimagesize($_FILES["edit_cover_art"]["tmp_name"]);
                if ($check !== false) {
                    // Upload image
                    if (move_uploaded_file($_FILES["edit_cover_art"]["tmp_name"], $coverTargetFile)) {
                        // Add cover_art to update fields
                        $updateFields[] = "cover_art = '$coverTargetFile'";
                        
                        // Delete old cover if it's not the default and not used by other songs
                        if (!empty($song_data['cover_art']) && $song_data['cover_art'] != 'uploads/covers/default_cover.jpg') {
                            // Check if other songs use the same cover
                            $cover_check_query = "SELECT COUNT(*) as count FROM songs WHERE cover_art = '{$song_data['cover_art']}' AND song_id != $song_id";
                            $cover_check_result = mysqli_query($conn, $cover_check_query);
                            $cover_check_data = mysqli_fetch_assoc($cover_check_result);
                            
                            if ($cover_check_data['count'] == 0 && file_exists($song_data['cover_art'])) {
                                unlink($song_data['cover_art']);
                            }
                        }
                    } else {
                        $error = "Sorry, there was an error uploading your cover image.";
                    }
                } else {
                    $error = "File is not an image.";
                }
            }
            
            // Update the song in the database
            if (!empty($updateFields) && empty($error)) {
                $update_sql = "UPDATE songs SET " . implode(", ", $updateFields) . " WHERE song_id = $song_id";
                
                if (mysqli_query($conn, $update_sql)) {
                    $message = "Song updated successfully!";
                } else {
                    $error = "Error updating song: " . mysqli_error($conn);
                }
            }
        } else {
            $error = "You don't have permission to edit this song.";
        }
    } 
    else {
        // This is a song upload request
        // Get form data (only if they exist)
        $title = isset($_POST['title']) ? mysqli_real_escape_string($conn, $_POST['title']) : '';
        $artist_option = isset($_POST['artist_option']) ? $_POST['artist_option'] : '';
        $album_option = isset($_POST['album_option']) ? $_POST['album_option'] : '';

        // Process song file upload first to get duration
        $targetDir = "uploads/songs/";
        $duration = isset($_POST['detected_duration']) && is_numeric($_POST['detected_duration']) ? 
            (int)$_POST['detected_duration'] : 0;
        $songTargetFile = "";

        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        // Check if a song file was uploaded
        if (isset($_FILES["song_file"]) && $_FILES["song_file"]["error"] == 0) {
            // Generate a unique filename
            $songFileName = uniqid() . "_" . basename($_FILES["song_file"]["name"]);
            $songTargetFile = $targetDir . $songFileName;
            $songFileType = strtolower(pathinfo($songTargetFile, PATHINFO_EXTENSION));

            // Check if file is an actual audio file
            $allowedExtensions = array("mp3", "wav", "ogg", "flac");
            if (!in_array($songFileType, $allowedExtensions)) {
                $error = "Sorry, only MP3, WAV, OGG, and FLAC files are allowed.";
            } else {
                // Upload file
                if (move_uploaded_file($_FILES["song_file"]["tmp_name"], $songTargetFile)) {
                    // Use client-side detected duration if available, otherwise try server detection
                    if (isset($_POST['detected_duration']) && is_numeric($_POST['detected_duration']) && (int)$_POST['detected_duration'] > 0) {
                        $duration = (int)$_POST['detected_duration'];
                    } else {
                        // Only fall back to server-side detection if client-side failed
                        $duration = getAudioDuration($songTargetFile);
                    }

                    // Process cover image if uploaded
                    $coverPath = NULL;
                    if (isset($_FILES["cover_art"]) && $_FILES["cover_art"]["error"] == 0) {
                        $coverDir = "uploads/covers/";

                        // Create directory if it doesn't exist
                        if (!file_exists($coverDir)) {
                            mkdir($coverDir, 0777, true);
                        }

                        $coverFileName = uniqid() . "_" . basename($_FILES["cover_art"]["name"]);
                        $coverTargetFile = $coverDir . $coverFileName;
                        $coverFileType = strtolower(pathinfo($coverTargetFile, PATHINFO_EXTENSION));

                        // Check if image file is an actual image
                        $check = getimagesize($_FILES["cover_art"]["tmp_name"]);
                        if ($check !== false) {
                            // Upload image
                            if (move_uploaded_file($_FILES["cover_art"]["tmp_name"], $coverTargetFile)) {
                                $coverPath = $coverTargetFile;
                            } else {
                                $error = "Sorry, there was an error uploading your cover image.";
                            }
                        } else {
                            $error = "File is not an image.";
                        }
                    } else {
                        // No song cover uploaded, try to use album cover instead
                        if ($album_option == 'existing' && isset($_POST['album_id']) && !empty($_POST['album_id'])) {
                            // Fetch the existing album's cover
                            $album_id = (int)$_POST['album_id'];
                            $album_cover_query = "SELECT cover_art FROM albums WHERE album_id = $album_id";
                            $album_cover_result = mysqli_query($conn, $album_cover_query);
                            if ($album_cover_result && mysqli_num_rows($album_cover_result) > 0) {
                                $album_data = mysqli_fetch_assoc($album_cover_result);
                                if (!empty($album_data['cover_art'])) {
                                    $coverPath = $album_data['cover_art'];
                                }
                            }
                        }
                    }
                    // For new album, we'll set coverPath after processing the album cover
                }
                

                // If no errors, continue processing
                if (empty($error)) {
                    // Process artist - either existing or new
                    $artist_id = null;

                    if ($artist_option == 'existing' && isset($_POST['artist_id'])) {
                        $artist_id = (int)$_POST['artist_id'];
                    } else if ($artist_option == 'new' && !empty($_POST['new_artist_name'])) {
                        $new_artist_name = mysqli_real_escape_string($conn, $_POST['new_artist_name']);
                        $new_artist_bio = mysqli_real_escape_string($conn, $_POST['new_artist_bio']);

                        // Check if artist already exists
                        $check_artist = mysqli_query($conn, "SELECT artist_id FROM artists WHERE name = '$new_artist_name'");
                        if (mysqli_num_rows($check_artist) > 0) {
                            $artist_row = mysqli_fetch_assoc($check_artist);
                            $artist_id = $artist_row['artist_id'];
                        } else {
                            // Insert new artist
                            $artist_image = 'default_artist.jpg';

                            // Process artist image if uploaded
                            if (isset($_FILES['new_artist_image']) && $_FILES['new_artist_image']['error'] == 0) {
                                $artistDir = "uploads/artists/";
                                if (!file_exists($artistDir)) {
                                    mkdir($artistDir, 0777, true);
                                }

                                $artistFileName = uniqid() . "_" . basename($_FILES['new_artist_image']['name']);
                                $artistTargetFile = $artistDir . $artistFileName;

                                if (move_uploaded_file($_FILES['new_artist_image']['tmp_name'], $artistTargetFile)) {
                                    $artist_image = $artistTargetFile;
                                }
                            }

                            $insert_artist_sql = "INSERT INTO artists (name, bio, image) VALUES ('$new_artist_name', '$new_artist_bio', '$artist_image')";
                            if (mysqli_query($conn, $insert_artist_sql)) {
                                $artist_id = mysqli_insert_id($conn);
                            } else {
                                $error = "Error creating new artist: " . mysqli_error($conn);
                            }
                        }
                    } else {
                        $error = "Please select an existing artist or add a new artist.";
                    }

                    // Process album - either existing, new, or none
                    $album_id = null;

                    if ($album_option == 'existing' && isset($_POST['album_id']) && !empty($_POST['album_id'])) {
                        $album_id = (int)$_POST['album_id'];
                    } else if ($album_option == 'new' && !empty($_POST['new_album_title'])) {
                        $new_album_title = mysqli_real_escape_string($conn, $_POST['new_album_title']);
                        $new_album_release_date = mysqli_real_escape_string($conn, $_POST['new_album_release_date']);

                        // Check if album already exists
                        $check_album = mysqli_query($conn, "SELECT album_id FROM albums WHERE title = '$new_album_title'");
                        if (mysqli_num_rows($check_album) > 0) {
                            $album_row = mysqli_fetch_assoc($check_album);
                            $album_id = $album_row['album_id'];
                        } else {
                            // Process album cover if uploaded or use song cover
                            $album_cover = $coverPath ? $coverPath : 'uploads/covers/default_cover.jpg';

                            if (isset($_FILES['new_album_cover']) && $_FILES['new_album_cover']['error'] == 0) {
                                $albumCoverDir = "uploads/covers/";
                                if (!file_exists($albumCoverDir)) {
                                    mkdir($albumCoverDir, 0777, true);
                                }

                                $albumCoverName = uniqid() . "_" . basename($_FILES['new_album_cover']['name']);
                                $albumCoverFile = $albumCoverDir . $albumCoverName;

                                if (move_uploaded_file($_FILES['new_album_cover']['tmp_name'], $albumCoverFile)) {
                                    $album_cover = $albumCoverFile;
                                }
                            }

                            // Insert new album
                            $insert_album_sql = "INSERT INTO albums (title, cover_art, release_date) VALUES 
                                               ('$new_album_title', '$album_cover', " .
                                (!empty($new_album_release_date) ? "'$new_album_release_date'" : "NULL") . ")";

                            if (mysqli_query($conn, $insert_album_sql)) {
                                $album_id = mysqli_insert_id($conn);

                                // Link album to primary artist using prepared statements
                                if ($artist_id) {
                                    $link_album_sql = "INSERT INTO album_artists (album_id, artist_id, is_primary) VALUES (?, ?, ?)";
                                    $album_artist_stmt = mysqli_prepare($conn, $link_album_sql);
                                    
                                    if ($album_artist_stmt) {
                                        $is_primary = 1; // This is the primary artist
                                        mysqli_stmt_bind_param($album_artist_stmt, "iii", $album_id, $artist_id, $is_primary);
                                        
                                        if (!mysqli_stmt_execute($album_artist_stmt)) {
                                            $error .= " Failed to link album to artist: " . mysqli_stmt_error($album_artist_stmt);
                                        }
                                        mysqli_stmt_close($album_artist_stmt);
                                        
                                        // Also link additional artists to album if applicable
                                        if (isset($_POST['additional_artists']) && is_array($_POST['additional_artists'])) {
                                            foreach ($_POST['additional_artists'] as $add_artist_id) {
                                                if ($add_artist_id != $artist_id && !empty($add_artist_id)) {
                                                    $add_album_artist_sql = "INSERT INTO album_artists (album_id, artist_id, is_primary) VALUES (?, ?, ?)";
                                                    $add_album_artist_stmt = mysqli_prepare($conn, $add_album_artist_sql);
                                                    
                                                    if ($add_album_artist_stmt) {
                                                        $is_secondary = 0; // This is a featured artist
                                                        mysqli_stmt_bind_param($add_album_artist_stmt, "iii", $album_id, $add_artist_id, $is_secondary);
                                                        mysqli_stmt_execute($add_album_artist_stmt);
                                                        mysqli_stmt_close($add_album_artist_stmt);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            } else {
                                $error = "Error creating new album: " . mysqli_error($conn);
                            }
                        }
                    }
                    // album_option 'none' means no album, so $album_id stays null

                    // If no errors, insert song
                    if (empty($error)) {
                        // Insert song into database
                        $release_date = isset($_POST['release_date']) ? mysqli_real_escape_string($conn, $_POST['release_date']) : NULL;

                        // Prepare statement to prevent SQL injection
                        $sql = "INSERT INTO songs (title, duration, file_path, cover_art, album_id, release_date, uploaded_by, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                        // Use prepared statements instead
                        $stmt = mysqli_prepare($conn, $sql);

                        // Initialize variables properly to ensure they can be passed by reference
                        // NULL in PHP can't be directly passed by reference
                        $cover_path_param = $coverPath;
                        $album_id_param = $album_id;
                        $release_date_param = $release_date;

                        // Check for NULL values and convert them to empty strings for binding
                        // This is important because NULL can't be passed by reference
                        if ($cover_path_param === NULL) $cover_path_param = '';
                        if ($album_id_param === NULL) $album_id_param = 0;  // Use 0 instead of NULL for integers
                        if ($release_date_param === NULL) $release_date_param = '';

                        // Bind parameters - now all are properly initialized variables
                        mysqli_stmt_bind_param($stmt, "sissssi",  // Add one more 'i' for the user_id integer
                            $title, 
                            $duration, 
                            $songTargetFile, 
                            $cover_path_param, 
                            $album_id_param, 
                            $release_date_param,
                            $_SESSION['user_id']  // Add the current user's ID
                        );

                        // After execution, put the NULL values back in MySQL with a direct SQL UPDATE if needed
                        if (mysqli_stmt_execute($stmt)) {
                            $song_id = mysqli_insert_id($conn);
                            
                            // If album_id was null, update it to NULL in the database
                            if ($album_id === NULL) {
                                mysqli_query($conn, "UPDATE songs SET album_id = NULL WHERE song_id = $song_id");
                            }
                            
                            // Link song to artist
                            if ($artist_id) {
                                // Insert primary artist relationship
                                $link_sql = "INSERT INTO song_artists (song_id, artist_id, is_primary) VALUES (?, ?, ?)";
                                $link_stmt = mysqli_prepare($conn, $link_sql);
                                
                                if (!$link_stmt) {
                                    $error = "Failed to prepare song-artist link: " . mysqli_error($conn);
                                } else {
                                    $is_primary = 1; // Set primary artist flag
                                    mysqli_stmt_bind_param($link_stmt, "iii", $song_id, $artist_id, $is_primary);
                                    
                                    if (!mysqli_stmt_execute($link_stmt)) {
                                        $error = "Failed to link song to artist: " . mysqli_stmt_error($link_stmt);
                                    } else {
                                        // Success! Continue with additional artists
                                        
                                        // Process additional artists if any were selected
                                        if (isset($_POST['additional_artists']) && !empty($_POST['additional_artists']) && is_array($_POST['additional_artists'])) {
                                            foreach ($_POST['additional_artists'] as $add_artist_id) {
                                                // Skip if it's the same as primary artist or empty
                                                if ($add_artist_id != $artist_id && !empty($add_artist_id)) {
                                                    $add_stmt = mysqli_prepare($conn, $link_sql); // Reuse the same SQL
                                                    
                                                    if ($add_stmt) {
                                                        $not_primary = 0; // Not a primary artist
                                                        mysqli_stmt_bind_param($add_stmt, "iii", $song_id, $add_artist_id, $not_primary);
                                                        
                                                        if (!mysqli_stmt_execute($add_stmt)) {
                                                            // Log error but continue with other artists
                                                            $error .= " Warning: Failed to link additional artist: " . mysqli_stmt_error($add_stmt);
                                                        }
                                                        mysqli_stmt_close($add_stmt);
                                                    }
                                                }
                                            }
                                        }
                                        
                                        // Check song_artists table to verify insertion
                                        $check_query = "SELECT COUNT(*) as count FROM song_artists WHERE song_id = $song_id";
                                        $check_result = mysqli_query($conn, $check_query);
                                        $check_data = mysqli_fetch_assoc($check_result);
                                        
                                        if ($check_data['count'] == 0) {
                                            $error .= " Warning: No records found in song_artists table after insertion.";
                                        } else {
                                            $message = "Song uploaded successfully! Detected duration: " . formatTime($duration);
                                            $message .= " Added " . $check_data['count'] . " artist relationship(s).";
                                        }
                                    }
                                    mysqli_stmt_close($link_stmt);
                                }
                            } else {
                                $error = "No artist selected for the song. Song needs at least one artist.";
                            }

                        } else {
                            $error = "Error: " . mysqli_error($conn);
                        }
                        
                        // Close the prepared statement
                        mysqli_stmt_close($stmt);
                    }
                }
            } 
        } 
    } 
}

// Get existing artists
$artists = [];
$result = mysqli_query($conn, "SELECT artist_id, name FROM artists ORDER BY name");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $artists[] = $row;
    }
}

// Get existing albums
$albums = [];
$result = mysqli_query($conn, "SELECT album_id, title FROM albums ORDER BY title");
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $albums[] = $row;
    }
}

// Format time helper function
function formatTime($seconds)
{
    return sprintf("%02d:%02d", floor($seconds / 60), $seconds % 60);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Song - MusicStream</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="home.css">
    <style>
        /* Ensure text inputs allow all characters including spaces */
        input[type="text"],
        textarea {
            white-space: pre-wrap !important;
            word-spacing: normal !important;
        }

        /* Remove any potential input restrictions */
        input[type="text"]:focus,
        textarea:focus {
            outline: 2px solid #1db954 !important;
        }

        /* Additional styles to fix the mobile overlap */
        @media (max-width: 768px) {
            .content-area {
                padding-top: 3.5rem !important;
                /* Ensure enough space for the hamburger menu */
            }
        }
    </style>
</head>

<body class="font-sans bg-gray-900 text-white m-0 p-0 has-player">
    <!-- Mobile menu button (only visible on small screens) -->
    <div class="md:hidden fixed top-4 left-4 z-50">
        <button id="mobile-menu-button" class="text-white text-2xl focus:outline-none">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <!-- Mobile menu overlay -->
    <div id="mobile-overlay" class="mobile-menu-overlay"></div>

    <!-- Switch to a proper grid layout -->
    <div class="content-grid">
        <!-- Sidebar Navigation - same as home.php -->
        <div id="sidebar" class="sidebar-mobile md:static bg-black sidebar-width py-6 overflow-y-auto">
            <div class="px-6 mb-6">
                <h1 class="text-green-500 text-2xl">MusicStream</h1>
            </div>

            <ul class="list-none">
                <li class="py-2 px-6">
                    <a href="home.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-home mr-4 text-xl"></i> Home
                    </a>
                </li>
                <li class="py-2 px-6">
                    <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                        <i class="fas fa-search mr-4 text-xl"></i> Search
                    </a>
                </li>

                <?php if ($logged_in): ?>
                    <li class="py-2 px-6">
                        <a href="library.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-book mr-4 text-xl"></i> Your Library
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="addPlaylist.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-plus-square mr-4 text-xl"></i> Create Playlist
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="liked_songs.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-heart mr-4 text-xl"></i> Liked Songs
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="upload_song.php" class="text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-upload mr-4 text-xl"></i> Upload Song
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="add_artist.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user-plus mr-4 text-xl"></i> Add Artist
                        </a>
                    </li>
                    <li class="mt-5 pt-5 border-t border-gray-700 py-2 px-6">
                        <a href="profile.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <div class="w-8 h-8 rounded-full overflow-hidden mr-4 flex-shrink-0">
                                <img src="<?= $profile_picture ?>" alt="Profile" class="w-full h-full object-cover">
                            </div>
                            <?php echo htmlspecialchars($_SESSION['username']); ?>
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="logout.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-sign-out-alt mr-4 text-xl"></i> Logout
                        </a>
                    </li>
                <?php else: ?>
                    <li class="py-2 px-6">
                        <a href="login.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-sign-in-alt mr-4 text-xl"></i> Login
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="register.php" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user-plus mr-4 text-xl"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Main Content Area - Using Tailwind classes -->
        <div class="main-bg content-area p-4 pt-14 md:p-6 overflow-y-auto">
            <div class="mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl mb-2">Upload New Song</h2>
                <p class="text-gray-400 text-sm">Duration will be automatically detected from the uploaded file</p>
            </div>

            <?php if (!empty($message)): ?>
                <div class="p-4 mb-5 bg-opacity-10 bg-green-500 text-green-500 rounded">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="p-4 mb-5 bg-opacity-10 bg-red-500 text-red-400 rounded">
                    <?= $error ?>
                </div>
            <?php endif; ?>

            <div class="bg-black bg-opacity-20 rounded-lg p-6 max-w-4xl">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-5">
                        <label for="title" class="block mb-2 font-semibold text-gray-400">Song Title *</label>
                        <input type="text" id="title" name="title" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600" required>
                    </div>

                    <!-- Artist Section -->
                    <div class="mb-5">
                        <label class="block mb-2 font-semibold text-gray-400">Artist *</label>
                        <div class="flex mb-4">
                            <div class="mr-6 flex items-center">
                                <input type="radio" id="existing_artist" name="artist_option" value="existing" checked class="mr-2">
                                <label for="existing_artist" class="text-white">Select Existing Artist</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="new_artist" name="artist_option" value="new" class="mr-2">
                                <label for="new_artist" class="text-white">Add New Artist</label>
                            </div>
                        </div>

                        <div id="existing_artist_section" class="bg-black bg-opacity-30 p-4 rounded mb-4">
                            <div class="mb-4">
                                <label for="artist_id" class="block mb-2 font-semibold text-gray-400">Select Primary Artist</label>
                                <select id="artist_id" name="artist_id" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600" required>
                                    <option value="">-- Select Artist --</option>
                                    <?php foreach ($artists as $artist): ?>
                                        <option value="<?= $artist['artist_id'] ?>"><?= htmlspecialchars($artist['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="additional_artists" class="block mb-2 font-semibold text-gray-400">Additional Artists (Optional)</label>
                                <select id="additional_artists" name="additional_artists[]" multiple class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600 h-40">
                                    <?php foreach ($artists as $artist): ?>
                                        <option value="<?= $artist['artist_id'] ?>"><?= htmlspecialchars($artist['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="text-xs text-gray-400 mt-1">Hold Ctrl (Windows) or Command (Mac) to select multiple artists.</p>
                            </div>
                        </div>

                        <div id="new_artist_section" class="bg-black bg-opacity-30 p-4 rounded mb-4" style="display: none;">
                            <div class="mb-4">
                                <label for="new_artist_name" class="block mb-2 font-semibold text-gray-400">Artist Name *</label>
                                <input type="text" id="new_artist_name" name="new_artist_name" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
                            </div>

                            <div class="mb-4">
                                <label for="new_artist_bio" class="block mb-2 font-semibold text-gray-400">Biography</label>
                                <textarea id="new_artist_bio" name="new_artist_bio" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600 h-24 resize-y"></textarea>
                            </div>

                            <div class="mb-4">
                                <label for="new_artist_image" class="block mb-2 font-semibold text-gray-400">Artist Image</label>
                                <div class="relative">
                                    <input type="file" id="new_artist_image" name="new_artist_image" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                                    <div class="flex items-center">
                                        <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                            Choose File
                                        </button>
                                        <div id="new_artist_image_name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                            No file selected
                                        </div>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Recommended size: 300x300 pixels</p>
                            </div>
                        </div>
                    </div>

                    <!-- Album Section -->
                    <div class="mb-5">
                        <label class="block mb-2 font-semibold text-gray-400">Album</label>
                        <div class="flex mb-4 flex-wrap">
                            <div class="mr-6 flex items-center">
                                <input type="radio" id="no_album" name="album_option" value="none" checked class="mr-2">
                                <label for="no_album" class="text-white">No Album</label>
                            </div>
                            <div class="mr-6 flex items-center">
                                <input type="radio" id="existing_album" name="album_option" value="existing" class="mr-2">
                                <label for="existing_album" class="text-white">Select Existing Album</label>
                            </div>
                            <div class="flex items-center">
                                <input type="radio" id="new_album" name="album_option" value="new" class="mr-2">
                                <label for="new_album" class="text-white">Add New Album</label>
                            </div>
                        </div>

                        <div id="existing_album_section" class="bg-black bg-opacity-30 p-4 rounded mb-4" style="display: none;">
                            <div class="mb-4">
                                <label for="album_id" class="block mb-2 font-semibold text-gray-400">Select Album</label>
                                <select id="album_id" name="album_id" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
                                    <option value="">-- Select Album --</option>
                                    <?php foreach ($albums as $album): ?>
                                        <option value="<?= $album['album_id'] ?>"><?= htmlspecialchars($album['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div id="new_album_section" class="bg-black bg-opacity-30 p-4 rounded mb-4" style="display: none;">
                            <div class="mb-4">
                                <label for="new_album_title" class="block mb-2 font-semibold text-gray-400">Album Title *</label>
                                <input type="text" id="new_album_title" name="new_album_title" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
                            </div>

                            <div class="mb-4">
                                <label for="new_album_release_date" class="block mb-2 font-semibold text-gray-400">Release Date</label>
                                <input type="date" id="new_album_release_date" name="new_album_release_date" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
                            </div>

                            <div class="mb-4">
                                <label for="new_album_cover" class="block mb-2 font-semibold text-gray-400">Album Cover</label>
                                <p class="text-xs text-gray-400 mb-1">If not provided, song cover art will be used for album.</p>
                                <div class="relative">
                                    <input type="file" id="new_album_cover" name="new_album_cover" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                                    <div class="flex items-center">
                                        <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                            Choose File
                                        </button>
                                        <div id="new_album_cover_name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                            No file selected
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-5">
                        <label for="release_date" class="block mb-2 font-semibold text-gray-400">Song Release Date (Optional)</label>
                        <input type="date" id="release_date" name="release_date" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
                    </div>

                    <!-- Replace your existing file inputs with these styled versions -->

                    <!-- Song File input -->
                    <div class="mb-5">
                        <label for="song_file" class="block mb-2 font-semibold text-gray-400">Song File *</label>
                        <div class="relative">
                            <input type="file" id="song_file" name="song_file" accept=".mp3,.wav,.ogg,.flac" required class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                            <div class="flex items-center">
                                <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                    Choose File
                                </button>
                                <div id="song_file_name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                    No file selected
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Allowed formats: MP3, WAV, OGG, FLAC. Song duration will be detected automatically.</p>
                        <div id="duration_display" class="text-green-400 text-sm mt-2" style="display: none;">
        Detected duration: <span id="detected_duration">0:00</span>
        <input type="hidden" name="detected_duration" id="detected_duration_input" value="0">
    </div>
                    </div>

                    <!-- Cover Art input -->
                    <div class="mb-5">
                        <label for="cover_art" class="block mb-2 font-semibold text-gray-400">Cover Art (Optional)</label>
                        <div class="relative">
                            <input type="file" id="cover_art" name="cover_art" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                            <div class="flex items-center">
                                <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                                    Choose File
                                </button>
                                <div id="cover_art_name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                                    No file selected
                                </div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Recommended size: 300x300 pixels</p>
                    </div>

                    <button type="submit" class="py-3 px-6 bg-green-500 text-white border-none rounded-full text-base font-bold cursor-pointer hover:bg-green-400 transition-colors">Upload Song</button>
                </form>
            </div>

            <!-- My Uploaded Songs Section -->
            <div class="mt-10 bg-black bg-opacity-20 rounded-lg p-6">
    <h2 class="text-xl font-bold mb-4">My Uploaded Songs</h2>
    
    <?php
    // Get songs uploaded by current user
    $my_songs_query = "SELECT 
                        s.song_id,
                        s.title,
                        s.file_path,
                        s.cover_art,
                        s.duration,
                        GROUP_CONCAT(a.name SEPARATOR ', ') as artists,
                        al.title as album_title
                    FROM songs s
                    LEFT JOIN song_artists sa ON s.song_id = sa.song_id
                    LEFT JOIN artists a ON sa.artist_id = a.artist_id
                    LEFT JOIN albums al ON s.album_id = al.album_id
                    WHERE s.uploaded_by = " . $_SESSION['user_id'] . "
                    GROUP BY s.song_id
                    ORDER BY s.created_at DESC";
    
    $my_songs_result = mysqli_query($conn, $my_songs_query);
    $my_songs = [];
    
    if ($my_songs_result && mysqli_num_rows($my_songs_result) > 0) {
        while ($row = mysqli_fetch_assoc($my_songs_result)) {
            $my_songs[] = $row;
        }
    }
    ?>
    
    <?php if (count($my_songs) > 0): ?>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">#</th>
                        <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Title</th>
                        <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Artists</th>
                        <th class="text-left py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Album</th>
                        <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Duration</th>
                        <th class="text-right py-3 px-2 border-b border-gray-700 text-gray-400 font-normal text-sm">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($my_songs as $index => $song): ?>
                        <tr class="song-row hover:bg-white hover:bg-opacity-10 border-b border-gray-700"
                           data-song-id="<?= $song['song_id'] ?>"
                           data-file="<?= htmlspecialchars($song['file_path']) ?>"
                           data-album-cover="<?= !empty($song['cover_art']) ? htmlspecialchars($song['cover_art']) : 'uploads/covers/default_cover.jpg' ?>">
                            <td class="py-3 px-2 w-12">
                                <div class="flex items-center">
                                    <button class="play-button bg-transparent border-0 text-white cursor-pointer text-sm mr-3 w-4 flex items-center justify-center">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    <span class="text-gray-400"><?= $index + 1 ?></span>
                                </div>
                            </td>
                            <td class="py-3 px-2">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 mr-4 flex-shrink-0">
                                        <img src="<?= !empty($song['cover_art']) ? $song['cover_art'] : 'uploads/covers/default_cover.jpg' ?>" 
                                            alt="Cover" class="w-full h-full object-cover">
                                    </div>
                                    <div class="song-title-artist">
                                        <div class="text-white font-medium song-title"><?= htmlspecialchars($song['title']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-2 song-artist"><?= htmlspecialchars($song['artists']) ?></td>
                            <td class="py-3 px-2 text-gray-400"><?= !empty($song['album_title']) ? htmlspecialchars($song['album_title']) : '-' ?></td>
                            <td class="py-3 px-2 text-right text-gray-400 text-sm"><?= formatTime($song['duration']) ?></td>
                            <td class="py-3 px-2 text-right">
                                <button class="edit-song-btn text-blue-400 hover:text-blue-300 transition-colors mr-3" data-song-id="<?= $song['song_id'] ?>" data-song-title="<?= htmlspecialchars($song['title']) ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="delete-song-btn text-red-400 hover:text-red-300 transition-colors" data-song-id="<?= $song['song_id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="text-center py-8 text-gray-400">
            You haven't uploaded any songs yet.
        </div>
    <?php endif; ?>
</div>

<!-- Delete Song Modal -->
<div id="delete-song-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 p-8 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Delete Song</h3>
        <p class="text-gray-300 mb-6">Are you sure you want to delete this song? This will remove it from all playlists and it cannot be recovered.</p>
        
        <form method="POST" id="delete-song-form">
            <input type="hidden" name="delete_song_id" id="delete-song-id" value="">
            <div class="flex justify-end space-x-4">
                <button type="button" id="cancel-delete-song" class="py-2 px-4 bg-gray-600 hover:bg-gray-500 text-white rounded transition-colors">
                    Cancel
                </button>
                <button type="submit" name="delete_song" value="1" class="py-2 px-4 bg-red-600 hover:bg-red-500 text-white rounded transition-colors">
                    Delete Song
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Song Modal -->
<div id="edit-song-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 p-8 rounded-lg max-w-md w-full">
        <h3 class="text-xl font-bold mb-4">Edit Song</h3>
        <p class="text-gray-300 mb-6">Update song details</p>
        
        <form method="POST" enctype="multipart/form-data" id="edit-song-form">
            <input type="hidden" name="edit_song_id" id="edit-song-id" value="">
            
            <div class="mb-5">
                <label for="edit_song_title" class="block mb-2 font-semibold text-gray-400">Song Title</label>
                <input type="text" id="edit_song_title" name="edit_song_title" class="w-full p-3 bg-gray-700 border-none rounded text-white text-base focus:outline-none focus:bg-gray-600">
            </div>
            
            <div class="mb-5">
                <label class="block mb-2 font-semibold text-gray-400">Current Cover</label>
                <div class="w-full flex items-center justify-center mb-4">
                    <div class="w-40 h-40 overflow-hidden">
                        <img id="edit-cover-preview" src="uploads/covers/default_cover.jpg" alt="Cover" class="w-full h-full object-cover">
                    </div>
                </div>
                
                <label for="edit_cover_art" class="block mb-2 font-semibold text-gray-400">Change Cover Image</label>
                <div class="relative">
                    <input type="file" id="edit_cover_art" name="edit_cover_art" accept="image/*" class="absolute inset-0 w-full h-full opacity-0 z-10 cursor-pointer">
                    <div class="flex items-center">
                        <button type="button" class="py-2 px-4 bg-gray-700 hover:bg-gray-600 text-white rounded-l-md border-0 font-medium focus:outline-none transition-colors">
                            Choose File
                        </button>
                        <div id="edit_cover_art_name" class="py-2 px-4 bg-gray-800 rounded-r-md flex-grow text-gray-400 truncate">
                            No file selected
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-1">Recommended size: 300x300 pixels</p>
            </div>
            
            <div class="flex justify-end space-x-4">
                <button type="button" id="cancel-edit-song" class="py-2 px-4 bg-gray-600 hover:bg-gray-500 text-white rounded transition-colors">
                    Cancel
                </button>
                <button type="submit" name="edit_song" value="1" class="py-2 px-4 bg-green-600 hover:bg-green-500 text-white rounded transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>
        </div>

        <!-- Player Bar - Fixed for proper centering -->
        <div class="md:col-span-2 bg-gray-800 border-t border-gray-700 p-4 flex flex-col md:flex-row items-center space-y-4 md:space-y-0">
            <!-- Left section - Song info (smaller width) -->
            <div class="flex items-center w-full md:w-1/4">
                <div class="w-12 h-12 md:w-14 md:h-14 mr-3 md:mr-4 flex-shrink-0">
                    <img id="current-cover" src="uploads/covers/default_cover.jpg" alt="Now playing" class="w-full h-full object-cover">
                </div>
                <div class="flex flex-col truncate">
                    <div id="current-title" class="text-white text-xs md:text-sm font-medium mb-1 truncate">No song selected</div>
                    <div id="current-artist" class="text-gray-400 text-xs truncate">-</div>
                </div>
            </div>

            <!-- Center section - Player controls (fixed width and centered) -->
            <div class="flex flex-col items-center w-full md:w-1/2">
                <!-- Control buttons with increased spacing -->
                <div class="flex items-center justify-center w-full mb-2">
                    <button class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
                        <i class="fas fa-random"></i>
                    </button>
                    <button id="prev-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-lg mx-4">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    <button id="play-pause" class="w-8 h-8 md:w-10 md:h-10 rounded-full bg-white text-black flex items-center justify-center cursor-pointer mx-5">
                        <i class="fas fa-play"></i>
                    </button>
                    <button id="next-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-lg mx-4">
                        <i class="fas fa-step-forward"></i>
                    </button>
                    <button id="loop-button" class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
                        <i class="fas fa-repeat"></i>
                    </button>
                </div>

                <!-- Progress bar with wider margins -->
                <div class="w-full flex items-center px-4 md:px-8 lg:px-12">
                    <div id="current-time" class="text-xs text-gray-400 min-w-[40px] text-center">0:00</div>
                    <div id="progress-bar" class="progress-bar flex-1 h-2 bg-gray-600 rounded mx-3 cursor-pointer relative">
                        <div class="progress h-full w-0 bg-gray-400 rounded relative" id="progress">
                            <div class="progress-handle"></div>
                        </div>
                    </div>
                    <div id="total-time" class="text-xs text-gray-400 min-w-[40px] text-center">0:00</div>
                </div>
            </div>

            <!-- Right section - Volume control -->
            <div class="items-center justify-end hidden md:flex md:w-1/4">
                <i class="fas fa-volume-up text-gray-400 mr-3 text-base"></i>
                <div id="volume-bar" class="volume-bar w-24 h-2 bg-gray-600 rounded cursor-pointer relative">
                    <div class="volume-level h-full w-1/2 bg-gray-400 rounded" id="volume-level"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Element -->
    <audio id="audio-player"></audio>

    <!-- Include the external player script -->
    <script src="player.js"></script>
    <script src="playerState.js"></script>

    <!-- Keep JavaScript toggle for form sections -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any conflicting event listeners and ensure spaces work
            const textInputs = document.querySelectorAll('input[type="text"], textarea');
            textInputs.forEach(input => {
                // Remove any restrictive attributes
                input.removeAttribute('pattern');
                input.removeAttribute('maxlength');
                
                // Ensure no conflicting event listeners
                input.addEventListener('keydown', function(e) {
                    // Explicitly allow space (keyCode 32)
                    if (e.keyCode === 32 || e.key === ' ') {
                        e.stopPropagation();
                        return true;
                    }
                });
                
                // Prevent any trimming on input
                input.addEventListener('input', function(e) {
                    // Don't modify the value - let spaces through
                    console.log('Input value:', this.value);
                });
            });
            
            // Your existing form toggle code remains the same...
            const existingArtistRadio = document.getElementById('existing_artist');
            const newArtistRadio = document.getElementById('new_artist');
            const existingArtistSection = document.getElementById('existing_artist_section');
            const newArtistSection = document.getElementById('new_artist_section');

            existingArtistRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingArtistSection.style.display = 'block';
                    newArtistSection.style.display = 'none';
                    document.getElementById('artist_id').required = true;
                    document.getElementById('new_artist_name').required = false;
                }
            });

            newArtistRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingArtistSection.style.display = 'none';
                    newArtistSection.style.display = 'block';
                    document.getElementById('artist_id').required = false;
                    document.getElementById('new_artist_name').required = true;
                }
            });

            // Album toggle code...
            const noAlbumRadio = document.getElementById('no_album');
            const existingAlbumRadio = document.getElementById('existing_album');
            const newAlbumRadio = document.getElementById('new_album');
            const existingAlbumSection = document.getElementById('existing_album_section');
            const newAlbumSection = document.getElementById('new_album_section');

            noAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'none';
                    newAlbumSection.style.display = 'none';
                    document.getElementById('album_id').required = false;
                    document.getElementById('new_album_title').required = false;
                }
            });

            existingAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'block';
                    newAlbumSection.style.display = 'none';
                    document.getElementById('album_id').required = true;
                    document.getElementById('new_album_title').required = false;
                }
            });

            newAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'none';
                    newAlbumSection.style.display = 'block';
                    document.getElementById('album_id').required = false;
                    document.getElementById('new_album_title').required = true;
                }
            });

            // File input handlers remain the same...
            document.getElementById('song_file').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file selected';
                document.getElementById('song_file_name').textContent = fileName;
            });

            document.getElementById('cover_art').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file selected';
                document.getElementById('cover_art_name').textContent = fileName;
            });

            document.getElementById('new_artist_image').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file selected';
                document.getElementById('new_artist_image_name').textContent = fileName;
            });

            document.getElementById('new_album_cover').addEventListener('change', function() {
                const fileName = this.files[0] ? this.files[0].name : 'No file selected';
                document.getElementById('new_album_cover_name').textContent = fileName;
            });
        });
    </script>

    <!-- Mobile menu script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.getElementById('mobile-menu-button');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobile-overlay');

            menuButton.addEventListener('click', function() {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            });

            overlay.addEventListener('click', function() {
                sidebar.classList.remove('open');
                overlay.classList.remove('open');
            });
        });
    </script>

    <!-- Update song duration in database if it's different from what we're playing -->
    <script>
        audioPlayer.addEventListener('loadedmetadata', function() {
    const currentSongId = songs[currentSongIndex].id;
    const actualDuration = Math.round(audioPlayer.duration);
    
    // Get displayed duration from the table
    let displayedDuration = 0;
    const songRow = document.querySelector(`tr[data-song-id="${currentSongId}"]`);
    if (songRow) {
        const durationCell = songRow.querySelector('td:last-child');
        const durationText = durationCell.textContent;
        const parts = durationText.split(':');
        displayedDuration = (parseInt(parts[0]) * 60) + parseInt(parts[1]);
    }
    
    // If more than 3 seconds difference, update the database
    if (Math.abs(actualDuration - displayedDuration) > 3) {
        // Update the duration via AJAX
        fetch('update_duration.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `song_id=${currentSongId}&duration=${actualDuration}`
        })
        .then(response => response.json())
        .then (data => {
            if (data.success) {
                // Update the displayed duration in the table
                if (songRow) {
                    const durationCell = songRow.querySelector('td:last-child');
                    durationCell.textContent = formatTime(actualDuration);
                }
            }
        })
        .catch(error => console.error('Error updating duration:', error));
    }
});
    </script>

    <script>
// Duration detection using browser's Audio API
document.addEventListener('DOMContentLoaded', function() {
    const songFileInput = document.getElementById('song_file');
    const durationDisplay = document.getElementById('duration_display');
    const detectedDurationSpan = document.getElementById('detected_duration');
    const detectedDurationInput = document.getElementById('detected_duration_input');
    
    // Function to format time in MM:SS
    function formatTime(seconds) {
        seconds = Math.floor(seconds);
        const minutes = Math.floor(seconds / 60);
        seconds = seconds % 60;
        return minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
    }
    
    songFileInput.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            
            // Create a blob URL for the file
            const blobURL = URL.createObjectURL(file);
            
            // Create an audio element to detect duration
            const audio = new Audio();
            
            // Show loading indicator
            detectedDurationSpan.textContent = "Detecting...";
            durationDisplay.style.display = "block";
            
            // When metadata is loaded, we can access the duration
            audio.addEventListener('loadedmetadata', function() {
                const duration = Math.round(audio.duration);
                detectedDurationSpan.textContent = formatTime(duration);
                detectedDurationInput.value = duration;
                
                // Revoke the blob URL to free memory
                URL.revokeObjectURL(blobURL);
            });
            
            // Handle errors
            audio.addEventListener('error', function() {
                detectedDurationSpan.textContent = "Could not detect duration";
                detectedDurationInput.value = 0;
                URL.revokeObjectURL(blobURL);
            });
            
            // Set the audio source to the blob URL
            audio.src = blobURL;
        }
    });
});
</script>

<script>
// Delete Song functionality
const deleteSongBtns = document.querySelectorAll('.delete-song-btn');
const deleteSongModal = document.getElementById('delete-song-modal');
const deleteSongIdInput = document.getElementById('delete-song-id');
const cancelDeleteSongBtn = document.getElementById('cancel-delete-song');

deleteSongBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const songId = this.getAttribute('data-song-id');
        deleteSongIdInput.value = songId;
        deleteSongModal.classList.remove('hidden');
    });
});

if (cancelDeleteSongBtn) {
    cancelDeleteSongBtn.addEventListener('click', function() {
        deleteSongModal.classList.add('hidden');
    });
}

// Close when clicking outside modal
if (deleteSongModal) {
    deleteSongModal.addEventListener('click', function(e) {
        if (e.target === deleteSongModal) {
            deleteSongModal.classList.add('hidden');
        }
    });
}
</script>

<script>
// Edit Song functionality
const editSongBtns = document.querySelectorAll('.edit-song-btn');
const editSongModal = document.getElementById('edit-song-modal');
const editSongIdInput = document.getElementById('edit-song-id');
const editSongTitleInput = document.getElementById('edit_song_title');
const editCoverPreview = document.getElementById('edit-cover-preview');
const cancelEditSongBtn = document.getElementById('cancel-edit-song');
const editCoverInput = document.getElementById('edit_cover_art');
const editCoverNameDisplay = document.getElementById('edit_cover_art_name');

editSongBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const songId = this.getAttribute('data-song-id');
        const songTitle = this.getAttribute('data-song-title');
        const songRow = document.querySelector(`tr[data-song-id="${songId}"]`);
        const coverUrl = songRow.getAttribute('data-album-cover');
        
        // Set form values
        editSongIdInput.value = songId;
        editSongTitleInput.value = songTitle;
        editCoverPreview.src = coverUrl;
        
        // Show modal
        editSongModal.classList.remove('hidden');
    });
});

if (cancelEditSongBtn) {
    cancelEditSongBtn.addEventListener('click', function() {
        editSongModal.classList.add('hidden');
    });
}

// Close when clicking outside modal
if (editSongModal) {
    editSongModal.addEventListener('click', function(e) {
        if (e.target === editSongModal) {
            editSongModal.classList.add('hidden');
        }
    });
}

// Handle file selection for edit cover
if (editCoverInput) {
    editCoverInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            // Update file name display
            editCoverNameDisplay.textContent = this.files[0].name;
            
            // Show preview of new image
            const reader = new FileReader();
            reader.onload = function(e) {
                editCoverPreview.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            editCoverNameDisplay.textContent = 'No file selected';
        }
    });
}
</script>
</body>

</html>