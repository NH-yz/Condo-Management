<?php
// includes/auth.php - Authentication, session management, and helper functions

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if a user is currently logged in
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require user to be logged in; otherwise redirect to login.php
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        header('Location: login.php');
        exit;
    }
}

/**
 * Require a specific role ('admin' or 'resident')
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        setFlash('error', 'Unauthorized access.');
        if ($_SESSION['user_role'] === 'admin') {
            header('Location: admin-dashboard.php');
        } else {
            header('Location: resident-home.php');
        }
        exit;
    }
}

/**
 * Retrieve current logged-in user array from session
 */
function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id'        => $_SESSION['user_id'],
        'email'     => $_SESSION['user_email'] ?? '',
        'name'      => $_SESSION['user_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'unit'      => $_SESSION['resident_unit'] ?? '',
        'tower'     => $_SESSION['resident_tower'] ?? '',
        'res_id'    => $_SESSION['resident_id'] ?? null,
        'admin_id'  => $_SESSION['admin_id'] ?? null,
        'staff_id'  => $_SESSION['admin_staff_id'] ?? '',
    ];
}

/**
 * Set a session flash message
 */
function setFlash(string $type, string $message): void {
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * Render and clear all session flash messages
 */
function renderFlashes(): string {
    if (empty($_SESSION['flashes'])) return '';
    $html = '';
    foreach ($_SESSION['flashes'] as $f) {
        $type = htmlspecialchars($f['type']);
        $msg  = htmlspecialchars($f['message']);
        $cls  = ($type === 'success') ? 'banner-success' : (($type === 'error') ? 'banner-error' : 'banner-info');
        $html .= "<div class=\"flash-banner {$cls}\">{$msg}</div>";
    }
    unset($_SESSION['flashes']);
    return $html;
}

/**
 * XSS prevention helper
 */
function e(?string $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Convert MySQL YYYY-MM-DD to DD/MM/YYYY
 */
function formatDate(?string $date): string {
    if (!$date) return '-';
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : $date;
}

/**
 * Convert DD/MM/YYYY or YYYY-MM-DD input to MySQL YYYY-MM-DD
 */
function parseInputDate(?string $input): ?string {
    if (!$input) return null;
    $input = trim($input);
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $input, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
        return $input;
    }
    return null;
}

/**
 * Format hour number into standard 12-hour AM/PM string (e.g. 18 -> 6:00 PM)
 */
function formatHour(int $hour): string {
    $am_pm = ($hour < 12) ? 'AM' : 'PM';
    $h = ($hour % 12 === 0) ? 12 : ($hour % 12);
    return sprintf('%d:00 %s', $h, $am_pm);
}

/**
 * Format time range (e.g. start_hour 18, end_hour 22 -> "6:00 PM - 10:00 PM")
 */
function formatTimeRange(int $start, int $end): string {
    return formatHour($start) . ' - ' . formatHour($end);
}

/**
 * Blacklist helpers
 * A resident is "blacklisted" while they have a record with status 'Active'
 * whose banned_until is empty (indefinite) or not yet passed.
 * Returns the blacklist row, or null if the resident may book facilities.
 * Fails open (returns null) if the blacklist table has not been created yet.
 */
function getActiveBlacklist(PDO $pdo, ?int $resident_id): ?array {
    if (!$resident_id) return null;
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM blacklist
            WHERE resident_id = :rid
              AND status = 'Active'
              AND (banned_until IS NULL OR banned_until >= CURDATE())
            ORDER BY created_at DESC LIMIT 1
        ");
        $stmt->execute([':rid' => $resident_id]);
        $row = $stmt->fetch();
        return $row ?: null;
    } catch (PDOException $e) {
        return null;
    }
}


/**
 * Photo upload helpers (used by report-defect.php and edit-defect.php)
 * Stored files live in the "uploads" folder next to this file.
 * NOTE: if this file is ever moved into a sub-folder again, change the folder to dirname(__DIR__) . '/uploads/'.
 */
const UPLOAD_MAX_BYTES = 5 * 1024 * 1024; // 5 MB

/**
 * True when the browser sent a POST body bigger than PHP's post_max_size.
 * PHP then silently empties $_POST and $_FILES, which looks like "all fields empty".
 */
function uploadLimitExceeded(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && empty($_POST) && empty($_FILES)
        && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
}

/**
 * Validate and store an uploaded image.
 * Returns the relative path (e.g. "uploads/defect_1700000000_ab12cd34.jpg"), or null.
 * Sets $error when an upload was attempted but failed; stays '' when no file was chosen.
 */
function savePhotoUpload(string $field, string $prefix, string &$error): ?string {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // nothing chosen - photo is optional
    }

    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The photo is larger than the server limit (upload_max_filesize = ' . ini_get('upload_max_filesize') . ').',
            UPLOAD_ERR_FORM_SIZE  => 'The photo is larger than the form allows.',
            UPLOAD_ERR_PARTIAL    => 'The photo was only partly uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary folder for uploads (check upload_tmp_dir in php.ini).',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the photo to disk (check folder permissions).',
            UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
        ];
        $error = $messages[$file['error']] ?? 'Photo upload failed (error code ' . (int)$file['error'] . ').';
        return null;
    }

    if ($file['size'] > UPLOAD_MAX_BYTES) {
        $error = 'Uploaded photo exceeds maximum allowed size of 5 MB.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
        $error = 'Invalid image format. Allowed formats: JPG, PNG, WEBP.';
        return null;
    }

    // Make sure the file really is an image (not just renamed)
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        $error = 'The selected file is not a valid JPG, PNG or WEBP image.';
        return null;
    }

    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
        $error = 'The "uploads" folder does not exist and could not be created. Please create it next to the PHP files.';
        return null;
    }
    if (!is_writable($uploadDir)) {
        $error = 'The "uploads" folder is not writable. Please check its permissions.';
        return null;
    }

    $newFileName = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newFileName)) {
        $error = 'Failed to save the photo on the server. Please try again.';
        return null;
    }

    return 'uploads/' . $newFileName;
}
