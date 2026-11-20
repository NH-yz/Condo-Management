<?php
// report-defect.php - Defect and Maintenance Reporting Page for Residents

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$pageTitle = 'Report a Defect';
$error = '';

$category      = $_POST['category'] ?? '';
$location_type = $_POST['location_type'] ?? 'My unit';
$title         = $_POST['title'] ?? '';
$description   = $_POST['description'] ?? '';
$urgency       = $_POST['urgency'] ?? 'Medium';
$preferred_raw = $_POST['preferred_date'] ?? '';

if (uploadLimitExceeded()) {
    $error = 'The upload was too large for the server (limit ' . ini_get('post_max_size') . '). Please choose a smaller photo.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category      = trim($_POST['category'] ?? '');
    $location_type = trim($_POST['location_type'] ?? 'My unit');
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $urgency       = trim($_POST['urgency'] ?? 'Medium');
    $preferred_raw = trim($_POST['preferred_date'] ?? '');

    $preferred_date = parseInputDate($preferred_raw);

    // Server-side validation
    if (empty($category) || empty($location_type) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (mb_strlen($description) > 500) {
        $error = 'Description cannot exceed 500 characters.';
    } elseif ($preferred_date && $preferred_date < date('Y-m-d')) {
        $error = 'Preferred date cannot be in the past.';
    } else {
        // Photo upload handling (optional)
        $photo_path = savePhotoUpload('photo', 'defect', $error);

        if (empty($error)) {
            // Generate unique ticket number
            $ticket_no = 'MT-' . str_pad((string)mt_rand(100, 9999), 4, '0', STR_PAD_LEFT);

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO maintenance_requests 
                      (ticket_no, resident_id, category, location_type, title, description, urgency, preferred_date, photo_path, status) 
                    VALUES 
                      (:tno, :rid, :cat, :loc, :title, :descr, :urg, :pdate, :photo, 'reported')
                ");
                $stmt->execute([
                    ':tno'   => $ticket_no,
                    ':rid'   => $user['res_id'],
                    ':cat'   => $category,
                    ':loc'   => $location_type,
                    ':title' => $title,
                    ':descr' => $description,
                    ':urg'   => $urgency,
                    ':pdate' => $preferred_date,
                    ':photo' => $photo_path
                ]);

                setFlash('success', "Defect report {$ticket_no} submitted successfully. Our team will review it shortly.");
                header('Location: my-requests-maintenance.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to record maintenance request: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1>Report a Defect</h1>

<form class="card form-card" id="reportForm" method="POST" action="report-defect.php" enctype="multipart/form-data" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="category">Category *</label>
      <select class="input" id="category" name="category" required data-label="Category">
        <option value="">Select Category...</option>
        <option value="Plumbing" <?= $category === 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
        <option value="Electrical" <?= $category === 'Electrical' ? 'selected' : '' ?>>Electrical</option>
        <option value="Air-con" <?= $category === 'Air-con' ? 'selected' : '' ?>>Air-con</option>
        <option value="Lift" <?= $category === 'Lift' ? 'selected' : '' ?>>Lift</option>
        <option value="Other" <?= $category === 'Other' ? 'selected' : '' ?>>Other</option>
      </select>
    </div>

    <div class="field">
      <label for="location">Location *</label>
      <select class="input" id="location" name="location_type" required data-label="Location">
        <option value="My unit" <?= $location_type === 'My unit' ? 'selected' : '' ?>>My unit</option>
        <option value="Common area" <?= $location_type === 'Common area' ? 'selected' : '' ?>>Common area</option>
      </select>
    </div>

    <div class="field full">
      <label for="title">Title *</label>
      <input class="input" id="title" name="title" placeholder="e.g. Leaking kitchen pipe" value="<?= e($title) ?>" required data-label="Title">
    </div>

    <div class="field full">
      <label for="desc">Description *</label>
      <textarea class="input" id="desc" name="description" maxlength="500" placeholder="Describe the defect or problem in detail..." required data-label="Description"><?= e($description) ?></textarea>
      <div class="counter" id="counter">0 / 500 characters</div>
    </div>

    <div class="field">
      <label for="urgency">Urgency</label>
      <select class="input" id="urgency" name="urgency">
        <option value="Low" <?= $urgency === 'Low' ? 'selected' : '' ?>>Low</option>
        <option value="Medium" <?= $urgency === 'Medium' ? 'selected' : '' ?>>Medium</option>
        <option value="High" <?= $urgency === 'High' ? 'selected' : '' ?>>High</option>
      </select>
    </div>

    <div class="field">
      <label for="pdate">Preferred date</label>
      <input class="input" id="pdate" name="preferred_date" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($preferred_raw) ?>">
    </div>

    <div class="field full">
      <label for="photo">Photo (optional, max 5 MB)</label>
      <input class="input" id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp">
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="resident-home.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit">Submit Defect Report</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
