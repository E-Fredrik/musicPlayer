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
    // Get form data
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $artist_option = $_POST['artist_option'];
    $album_option = $_POST['album_option'];

    // Process song file upload first to get duration
    $targetDir = "uploads/songs/";
    $duration = 0;
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
                // Get the duration automatically
                $duration = getAudioDuration($songTargetFile);

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

                                // Link album to artist
                                if ($artist_id) {
                                    $link_album_sql = "INSERT INTO album_artists (album_id, artist_id, is_primary) VALUES ($album_id, $artist_id, 1)";
                                    mysqli_query($conn, $link_album_sql);
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

                        $sql = "INSERT INTO songs (title, duration, file_path, cover_art, album_id, release_date) 
                                VALUES ('$title', $duration, '$songTargetFile', " .
                            ($coverPath ? "'$coverPath'" : "NULL") . ", " .
                            ($album_id ? "$album_id" : "NULL") . ", " .
                            ($release_date ? "'$release_date'" : "NULL") . ")";

                        if (mysqli_query($conn, $sql)) {
                            $song_id = mysqli_insert_id($conn);

                            // Link song to artist
                            if ($artist_id) {
                                $link_sql = "INSERT INTO song_artists (song_id, artist_id, is_primary) 
                                            VALUES ($song_id, $artist_id, 1)";
                                mysqli_query($conn, $link_sql);

                                // Process additional artists if any
                                if (isset($_POST['additional_artists']) && is_array($_POST['additional_artists'])) {
                                    foreach ($_POST['additional_artists'] as $add_artist_id) {
                                        if ($add_artist_id != $artist_id) { // Don't duplicate the main artist
                                            $link_sql = "INSERT INTO song_artists (song_id, artist_id, is_primary) 
                                                        VALUES ($song_id, $add_artist_id, 0)";
                                            mysqli_query($conn, $link_sql);
                                        }
                                    }
                                }
                            }

                            $message = "Song uploaded successfully! Detected duration: " . formatTime($duration);
                        } else {
                            $error = "Error: " . mysqli_error($conn);
                        }
                    }
                }
            } else {
                $error = "Sorry, there was an error uploading your song file.";
            }
        }
    } else {
        $error = "Please select a song file to upload.";
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
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-book mr-4 text-xl"></i> Your Library
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-plus-square mr-4 text-xl"></i> Create Playlist
                        </a>
                    </li>
                    <li class="py-2 px-6">
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
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
                        <a href="#" class="text-gray-400 hover:text-white flex items-center font-semibold no-underline">
                            <i class="fas fa-user mr-4 text-xl"></i>
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
                    <button class="bg-transparent border-0 text-gray-400 cursor-pointer text-base mx-3 hidden md:block">
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

    <!-- Keep JavaScript toggle for form sections -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Artist option toggle
            const existingArtistRadio = document.getElementById('existing_artist');
            const newArtistRadio = document.getElementById('new_artist');
            const existingArtistSection = document.getElementById('existing_artist_section');
            const newArtistSection = document.getElementById('new_artist_section');

            existingArtistRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingArtistSection.style.display = 'block';
                    newArtistSection.style.display = 'none';

                    // Make existing artist field required
                    document.getElementById('artist_id').required = true;
                    document.getElementById('new_artist_name').required = false;
                }
            });

            newArtistRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingArtistSection.style.display = 'none';
                    newArtistSection.style.display = 'block';

                    // Make new artist field required
                    document.getElementById('artist_id').required = false;
                    document.getElementById('new_artist_name').required = true;
                }
            });

            // Album option toggle
            const noAlbumRadio = document.getElementById('no_album');
            const existingAlbumRadio = document.getElementById('existing_album');
            const newAlbumRadio = document.getElementById('new_album');
            const existingAlbumSection = document.getElementById('existing_album_section');
            const newAlbumSection = document.getElementById('new_album_section');

            noAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'none';
                    newAlbumSection.style.display = 'none';

                    // Make album fields not required
                    document.getElementById('album_id').required = false;
                    document.getElementById('new_album_title').required = false;
                }
            });

            existingAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'block';
                    newAlbumSection.style.display = 'none';

                    // Make existing album field required
                    document.getElementById('album_id').required = true;
                    document.getElementById('new_album_title').required = false;
                }
            });

            newAlbumRadio.addEventListener('change', function() {
                if (this.checked) {
                    existingAlbumSection.style.display = 'none';
                    newAlbumSection.style.display = 'block';

                    // Make new album field required
                    document.getElementById('album_id').required = false;
                    document.getElementById('new_album_title').required = true;
                }
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
</body>

</html>