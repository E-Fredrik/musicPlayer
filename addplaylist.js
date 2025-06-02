document.addEventListener("DOMContentLoaded", function () {
	// Select all songs functionality
	const selectAllCheckbox = document.getElementById("select-all-songs");
	const songCheckboxes = document.querySelectorAll(".song-checkbox");

	selectAllCheckbox.addEventListener("change", function () {
		songCheckboxes.forEach((checkbox) => {
			checkbox.checked = this.checked;
			updateCheckboxStyle(checkbox);
		});
	});

	// Individual song selection
	songCheckboxes.forEach((checkbox) => {
		checkbox.addEventListener("change", function () {
			updateCheckboxStyle(this);

			// Update "select all" checkbox state
			let allChecked = true;
			songCheckboxes.forEach((cb) => {
				if (!cb.checked) allChecked = false;
			});
			selectAllCheckbox.checked = allChecked;
		});

		// Initial styling
		updateCheckboxStyle(checkbox);
	});

	function updateCheckboxStyle(checkbox) {
		const checkIcon = checkbox.parentNode.querySelector(".song-check-icon");
		if (checkbox.checked) {
			checkIcon.classList.remove("text-transparent");
			checkIcon.classList.add("text-green-500");
		} else {
			checkIcon.classList.add("text-transparent");
			checkIcon.classList.remove("text-green-500");
		}
	}

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

	// Cover image upload preview
	const coverImageInput = document.getElementById("cover_image");
	const coverPreview = document.getElementById("cover-preview");
	const uploadCoverButton = document.getElementById("upload-cover-button");

	uploadCoverButton.addEventListener("click", function () {
		coverImageInput.click();
	});

	coverImageInput.addEventListener("change", function () {
		const file = this.files[0];
		if (file) {
			const reader = new FileReader();
			reader.onload = function (e) {
				coverPreview.src = e.target.result;
			};
			reader.readAsDataURL(file);
		}
	});
});
