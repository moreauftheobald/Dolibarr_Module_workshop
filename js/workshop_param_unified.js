/* Copyright (C) 2024 T-SERVICES <contact@theobald-groupe.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * AJAX upload handler for 'file' type fields rendered by
 * workshopBuildParamFormQuestion() (param/workshop_param_unified.php).
 * One delegated handler covers every such field on the page — all
 * per-field data (id, target url, temp dir, token) comes from the
 * button's data-* attributes, nothing is injected into inline script.
 */
$(document).on('click', '.ws-upload-btn', function (e) {
	e.preventDefault();

	var fieldName = $(this).data('field');
	var fileInput = document.getElementById('ws_file_' + fieldName);
	if (!fileInput.files || !fileInput.files[0]) {
		return;
	}

	var button = this;
	var formData = new FormData();
	formData.append('file', fileInput.files[0]);
	formData.append('upload_dir', $(this).data('uploaddir'));
	formData.append('token', $(this).data('token'));

	$.ajax({
		url: $(this).data('url'),
		cache: false,
		contentType: false,
		processData: false,
		data: formData,
		type: 'POST',
		success: function (data) {
			fileInput.disabled = true;
			button.disabled = true;
			document.getElementById('ws_file_uploaded_' + fieldName).value = data;
		},
		error: function () {
			alert(window.workshopUploadErrorMsg || 'Upload error');
		}
	});
});
