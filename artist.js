document.addEventListener("DOMContentLoaded", function () {
	// Mobile menu
	const menuButton = document.getElementById("mobile-menu-button");
	const sidebar = document.getElementById("sidebar");
	const overlay = document.getElementById("mobile-overlay");

	menuButton.addEventListener("click", function () {
		sidebar.classList.toggle("open");
		overlay.classList.toggle("open");
	});

	overlay.addEventListener("click", function () {
		sidebar.classList.remove("open");
		overlay.classList.remove("open");
	});

	// Play all button
	const playAllBtn = document.getElementById("play-all");
	if (playAllBtn) {
		playAllBtn.addEventListener("click", function () {
			const firstSongRow = document.querySelector(".song-row");
			if (firstSongRow) {
				firstSongRow.click();
			}
		});
	}

	// Like/Unlike functionality
	const likeButtons = document.querySelectorAll(".like-button");
	likeButtons.forEach((button) => {
		button.addEventListener("click", function (e) {
			e.stopPropagation(); // Prevent row click
			const songId = this.dataset.songId;
			const heartIcon = this.querySelector("i");
			const isLiked = heartIcon.classList.contains("fas");

			// Determine action based on current state
			const action = isLiked ? "unlike" : "like";

			// AJAX request to like/unlike the song
			fetch("like_song.php", {
				method: "POST",
				headers: {
					"Content-Type": "application/x-www-form-urlencoded",
				},
				body: "song_id=" + songId + "&action=" + action,
			})
				.then((response) => response.json())
				.then((data) => {
					if (data.success) {
						// Update heart icon
						if (action === "like") {
							heartIcon.classList.replace("far", "fas");
							heartIcon.classList.add("text-green-500");
						} else {
							heartIcon.classList.replace("fas", "far");
							heartIcon.classList.remove("text-green-500");
						}
					}
				})
				.catch((error) => console.error("Error:", error));
		});
	});
});

