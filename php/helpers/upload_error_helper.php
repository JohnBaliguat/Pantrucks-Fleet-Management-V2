<?php
// Helpers for surfacing friendly upload errors to the driver app.
// Most importantly, distinguish "file too large for server limits" from
// "no file attached" — the default `$_FILES['x']['error'] !== UPLOAD_ERR_OK`
// check returns the misleading "photo required" message when a mobile
// camera photo exceeds upload_max_filesize / post_max_size.

if (!function_exists('upload_friendly_error')) {
    /**
     * Map a PHP UPLOAD_ERR_* code to a human-readable message.
     */
    function upload_friendly_error(int $code, string $missingMessage = 'Photo is required.'): string
    {
        switch ($code) {
            case UPLOAD_ERR_OK:
                return '';
            case UPLOAD_ERR_INI_SIZE:
                return 'Photo is larger than the server upload limit ('
                    . ini_get('upload_max_filesize') . '). Please use a smaller image.';
            case UPLOAD_ERR_FORM_SIZE:
                return 'Photo is larger than the form upload limit. Please use a smaller image.';
            case UPLOAD_ERR_PARTIAL:
                return 'Photo upload was interrupted. Please try again on a stable connection.';
            case UPLOAD_ERR_NO_FILE:
                return $missingMessage;
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Server is missing a temporary upload folder. Contact admin.';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Server could not write the upload to disk. Contact admin.';
            case UPLOAD_ERR_EXTENSION:
                return 'Upload was blocked by a PHP extension. Contact admin.';
            default:
                return 'Photo upload failed (code ' . $code . ').';
        }
    }

    function upload_error_http_status(int $code): int
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 413;
        }
        if ($code === UPLOAD_ERR_NO_TMP_DIR || $code === UPLOAD_ERR_CANT_WRITE || $code === UPLOAD_ERR_EXTENSION) {
            return 500;
        }
        return 400;
    }

    /**
     * If post_max_size is exceeded the whole $_POST/$_FILES is empty.
     * Detect this by comparing CONTENT_LENGTH to the configured limit
     * so we can return a meaningful 413 instead of "field is required".
     */
    function upload_post_overflow(): ?string
    {
        $contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if ($contentLen <= 0) return null;
        $postMax = trim((string)ini_get('post_max_size'));
        if ($postMax === '') return null;
        $bytes = (int)$postMax;
        $unit  = strtolower(substr($postMax, -1));
        if ($unit === 'g') { $bytes *= 1024 * 1024 * 1024; }
        elseif ($unit === 'm') { $bytes *= 1024 * 1024; }
        elseif ($unit === 'k') { $bytes *= 1024; }
        if ($bytes > 0 && $contentLen > $bytes) {
            return 'Upload is too large. Please use a smaller image (server limit: ' . $postMax . ').';
        }
        return null;
    }
}
