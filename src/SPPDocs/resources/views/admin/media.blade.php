@extends('layouts.admin')

@section('title', 'Media Library — ' . ($project['title'] ?? $project_id))

@section('content')
<div class="adm-page-header">
    <div>
        <h1 class="adm-page-title">
            🖼️ Media Library
            <span class="adm-title-project-chip">📁 {{ $project['title'] ?? $project_id }}</span>
        </h1>
        <p class="adm-page-desc">Manage images, logos, diagrams, and video assets uploaded for project <strong>{{ $project['title'] ?? $project_id }}</strong> (<code>{{ $project_id }}</code>).</p>
    </div>
    <div class="adm-page-actions">
        <button type="button" class="adm-btn adm-btn-primary" id="toggleUploadBtn">➕ Upload Media</button>
    </div>
</div>

<!-- Inline Feedback Alert -->
<div id="mediaFeedbackAlert" class="adm-alert adm-media-feedback-alert">
    <span id="mediaFeedbackIcon">✅</span>
    <div id="mediaFeedbackText"></div>
</div>

<!-- Upload Media Card -->
<div id="uploadCard" class="adm-card adm-media-upload-card">
    <div class="adm-card-header">
        <h2 class="adm-card-title">📤 Upload New Media</h2>
        <span class="adm-badge adm-badge-active">Drag &amp; Drop</span>
    </div>
    <div class="adm-media-upload-body">
        <form action="@url('admin/media/upload')" method="POST" enctype="multipart/form-data" id="mediaUploadForm">
            <input type="hidden" name="csrf_token" value="{{ $csrf_token }}">
            <input type="hidden" name="project_id" value="{{ $project_id }}">

            <div class="adm-dropzone-box" id="dropzoneBox">
                <div class="adm-dropzone-icon">📁</div>
                <div class="adm-dropzone-title">Click to browse or drop files here</div>
                <div class="adm-dropzone-hint">Supported formats: PNG, JPG, JPEG, SVG, WebP, GIF, MP4, WebM (Max 10MB per file)</div>
                <input type="file" name="files[]" id="fileInput" class="adm-dropzone-file-input" multiple accept="image/*,video/*">
                <div id="selectedFilesText" class="adm-dropzone-selected-files"></div>
            </div>

            <!-- Upload Progress Bar -->
            <div id="uploadProgressContainer" class="adm-upload-progress-container">
                <div class="adm-upload-progress-bar-track">
                    <div id="uploadProgressBar" class="adm-upload-progress-bar"></div>
                </div>
                <div id="uploadProgressStatus" class="adm-upload-progress-status">Uploading 0%...</div>
            </div>

            <div class="adm-media-upload-actions">
                <button type="reset" class="adm-btn adm-btn-secondary" id="cancelUploadBtn">Cancel</button>
                <button type="submit" class="adm-btn adm-btn-primary" id="submitUploadBtn">Upload Files</button>
            </div>
        </form>
    </div>
</div>

<!-- Media Assets Grid Container (External Partial) -->
<div id="mediaGridWrapper">
    @spppartial('partials/media_grid.blade.php', ['images' => $images, 'project_id' => $project_id])
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const csrfToken = "{{ $csrf_token }}";
    const projectId = "{{ $project_id }}";
    const deleteUrl = "{{ \SPP\App::getBaseUrl() }}/admin/media/delete";

    const form = document.getElementById('mediaUploadForm');
    const dropzone = document.getElementById('dropzoneBox');
    const fileInput = document.getElementById('fileInput');
    const selectedText = document.getElementById('selectedFilesText');
    const uploadCard = document.getElementById('uploadCard');
    const toggleBtn = document.getElementById('toggleUploadBtn');
    const cancelBtn = document.getElementById('cancelUploadBtn');
    const submitBtn = document.getElementById('submitUploadBtn');
    const progressContainer = document.getElementById('uploadProgressContainer');
    const progressBar = document.getElementById('uploadProgressBar');
    const progressStatus = document.getElementById('uploadProgressStatus');
    const feedbackAlert = document.getElementById('mediaFeedbackAlert');
    const feedbackIcon = document.getElementById('mediaFeedbackIcon');
    const feedbackText = document.getElementById('mediaFeedbackText');
    const gridWrapper = document.getElementById('mediaGridWrapper');

    let isUploading = false;

    function showFeedback(msg, type) {
        if (!feedbackAlert || !feedbackText || !feedbackIcon) return;
        feedbackText.textContent = msg;
        feedbackAlert.classList.remove('adm-alert-success', 'adm-alert-danger');
        if (type === 'success') {
            feedbackAlert.classList.add('adm-alert-success');
            feedbackIcon.textContent = '✅';
        } else {
            feedbackAlert.classList.add('adm-alert-danger');
            feedbackIcon.textContent = '⚠️';
        }
        feedbackAlert.classList.add('visible');
        feedbackAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function hideFeedback() {
        if (feedbackAlert) feedbackAlert.classList.remove('visible');
    }

    if (dropzone && fileInput) {
        dropzone.addEventListener('click', () => {
            if (!isUploading) fileInput.click();
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (!isUploading) dropzone.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
                dropzone.classList.remove('dragover');
            });
        });

        dropzone.addEventListener('drop', (e) => {
            if (isUploading) return;
            if (e.dataTransfer && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                updateSelectedFilesText();
            }
        });

        fileInput.addEventListener('change', updateSelectedFilesText);
    }

    function updateSelectedFilesText() {
        if (!fileInput || !selectedText) return;
        const count = fileInput.files.length;
        if (count > 0) {
            const names = Array.from(fileInput.files).map(f => f.name).join(', ');
            selectedText.textContent = count + ' file(s) selected: ' + names;
            selectedText.classList.add('visible');
        } else {
            selectedText.textContent = '';
            selectedText.classList.remove('visible');
        }
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            if (isUploading) return;
            if (selectedText) {
                selectedText.textContent = '';
                selectedText.classList.remove('visible');
            }
            hideFeedback();
        });
    }

    if (toggleBtn && uploadCard) {
        toggleBtn.addEventListener('click', () => {
            uploadCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (fileInput && !isUploading) fileInput.click();
        });
    }

    // Single-Page Asynchronous Upload with Real-Time Progress & Double-Click Protection
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Guard against concurrent clicks
            if (isUploading) return;

            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                showFeedback('Please select at least one media file to upload.', 'danger');
                return;
            }

            isUploading = true;
            submitBtn.disabled = true;
            if (cancelBtn) cancelBtn.disabled = true;
            submitBtn.textContent = '⏳ Uploading...';
            hideFeedback();

            if (progressContainer && progressBar && progressStatus) {
                progressContainer.classList.add('active');
                progressBar.style.width = '0%';
                progressStatus.textContent = 'Preparing upload...';
            }

            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken);

            xhr.upload.onprogress = function(evt) {
                if (evt.lengthComputable && progressBar && progressStatus) {
                    const percent = Math.round((evt.loaded / evt.total) * 100);
                    progressBar.style.width = percent + '%';
                    progressStatus.textContent = 'Uploading ' + percent + '%...';
                }
            };

            xhr.onload = function() {
                isUploading = false;
                submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                submitBtn.textContent = 'Upload Files';

                if (progressContainer) {
                    progressContainer.classList.remove('active');
                }

                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        showFeedback(data.message || 'Media uploaded successfully.', 'success');
                        form.reset();
                        if (selectedText) {
                            selectedText.textContent = '';
                            selectedText.classList.remove('visible');
                        }
                        if (data.grid_html && gridWrapper) {
                            gridWrapper.innerHTML = data.grid_html;
                        }
                    } else {
                        const err = (data.errors && data.errors.length) ? data.errors.join(' ') : (data.error || 'Upload failed.');
                        showFeedback(err, 'danger');
                    }
                } catch (err) {
                    showFeedback('Server returned an unexpected response.', 'danger');
                }
            };

            xhr.onerror = function() {
                isUploading = false;
                submitBtn.disabled = false;
                if (cancelBtn) cancelBtn.disabled = false;
                submitBtn.textContent = 'Upload Files';
                if (progressContainer) {
                    progressContainer.classList.remove('active');
                }
                showFeedback('Network error occurred during upload. Please check connection.', 'danger');
            };

            xhr.send(formData);
        });
    }

    // Event Delegation: Copy URL & Delete
    document.addEventListener('click', function(e) {
        const copyBtn = e.target.closest('[data-copy-url]');
        if (copyBtn) {
            const url = copyBtn.getAttribute('data-copy-url');
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    const originalText = copyBtn.innerHTML;
                    copyBtn.textContent = '✅ Copied!';
                    setTimeout(() => {
                        copyBtn.innerHTML = originalText;
                    }, 2000);
                }).catch(() => {
                    prompt('Copy this media URL:', url);
                });
            } else {
                prompt('Copy this media URL:', url);
            }
            return;
        }

        const deleteBtn = e.target.closest('[data-delete-filename]');
        if (deleteBtn) {
            const filename = deleteBtn.getAttribute('data-delete-filename');
            const mediaId = deleteBtn.getAttribute('data-media-id');

            if (!confirm('Are you sure you want to delete "' + filename + '"? This file may be linked in documentation pages.')) {
                return;
            }

            deleteBtn.disabled = true;
            const originalText = deleteBtn.innerHTML;
            deleteBtn.textContent = '⏳';

            const formData = new FormData();
            formData.append('csrf_token', csrfToken);
            formData.append('project', projectId);
            formData.append('filename', filename);

            fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const card = document.getElementById('media-' + mediaId);
                    if (card) {
                        card.remove();
                    }
                    // Update counter badge if available
                    const badge = document.getElementById('mediaCountBadge');
                    if (badge && typeof data.remaining !== 'undefined') {
                        badge.textContent = data.remaining + ' asset' + (data.remaining === 1 ? '' : 's');
                        if (data.remaining === 0) {
                            const grid = document.getElementById('mediaCardsGrid');
                            if (grid) {
                                const emptyDiv = document.createElement('div');
                                emptyDiv.className = 'adm-media-empty-card';
                                emptyDiv.id = 'emptyMediaPlaceholder';
                                
                                const iconDiv = document.createElement('div');
                                iconDiv.className = 'adm-media-empty-icon';
                                iconDiv.textContent = '📂';
                                emptyDiv.appendChild(iconDiv);

                                const titleDiv = document.createElement('div');
                                titleDiv.className = 'adm-media-empty-title';
                                titleDiv.textContent = 'No Media Found';
                                emptyDiv.appendChild(titleDiv);

                                const descDiv = document.createElement('div');
                                descDiv.className = 'adm-media-empty-desc';
                                descDiv.textContent = 'No media files have been uploaded for this project yet. Use the upload area above to get started.';
                                emptyDiv.appendChild(descDiv);

                                grid.appendChild(emptyDiv);
                            }
                        }
                    }
                    showFeedback('"' + filename + '" was deleted successfully.', 'success');
                } else {
                    showFeedback('Error deleting media: ' + (data.error || 'Unknown error'), 'danger');
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
            })
            .catch(err => {
                showFeedback('Request failed: ' + err.message, 'danger');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalText;
            });
        }
    });
});
</script>
@endsection

