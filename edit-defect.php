<?php
// edit-defect.php - Resident Edit Defect Report Page

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$pageTitle = 'Edit Defect Report';
$id = (int)($_GET['id'] ?? 0);
$error = '';

if ($id <= 0) {
    header('Location: my-requests-maintenance.php');
    exit;
}

// Fetch existing ticket
$stmt = $pdo->prepare("SELECT * FROM maintenance_requests WHERE id = :id AND resident_id = :rid LIMIT 1");
$stmt->execute([':id' => $id, ':rid' => $user['res_id']]);
$ticket = $stmt->fetch();

if (!$ticket) {
    setFlash('error', 'Request not found.');
    header('Location: my-requests-maintenance.php');
    exit;
}

if ($ticket['status'] !== 'reported') {
    setFlash('error', 'Only unserviced requests with "Reported" status can be edited.');
    header('Location: my-requests-maintenance.php');
    exit;
}

$category      = $_POST['category'] ?? $ticket['category'];
$location_type = $_POST['location_type'] ?? $ticket['location_type'];
$title         = $_POST['title'] ?? $ticket['title'];
$description   = $_POST['description'] ?? $ticket['description'];
$urgency       = $_POST['urgency'] ?? $ticket['urgency'];
$preferred_raw = $_POST['preferred_date'] ?? ($ticket['preferred_date'] ?: '');

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

    if (empty($category) || empty($location_type) || empty($title) || empty($description)) {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (mb_strlen($description) > 500) {
        $error = 'Description cannot exceed 500 characters.';
    } elseif ($preferred_date && $preferred_date < date('Y-m-d')) {
        $error = 'Preferred date cannot be in the past.';
    } else {
        $photo_path = $ticket['photo_path'];

        // Optional photo replacement (keeps the old photo if nothing new is chosen)
        $newPhoto = savePhotoUpload('photo', 'defect', $error);
        if ($newPhoto !== null) {
            $photo_path = $newPhoto;
        }

        if (empty($error)) {
            try {
                $upd = $pdo->prepare("
                    UPDATE maintenance_requests 
                    SET category = :cat, location_type = :loc, title = :title, 
                        description = :descr, urgency = :urg, preferred_date = :pdate, photo_path = :photo
                    WHERE id = :id AND resident_id = :rid
                ");
                $upd->execute([
                    ':cat'   => $category,
                    ':loc'   => $location_type,
                    ':title' => $title,
                    ':descr' => $description,
                    ':urg'   => $urgency,
                    ':pdate' => $preferred_date,
                    ':photo' => $photo_path,
                    ':id'    => $id,
                    ':rid'   => $user['res_id']
                ]);

                setFlash('success', "Ticket {$ticket['ticket_no']} updated successfully.");
                header('Location: my-requests-maintenance.php');
                exit;
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1>Edit Defect Report &mdash; <?= e($ticket['ticket_no']) ?></h1>

<form class="card form-card" id="reportForm" method="POST" action="edit-defect.php?id=<?= $id ?>" enctype="multipart/form-data" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="category">Category *</label>
      <select class="input" id="category" name="category" required data-label="Category">
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
      <input class="input" id="title" name="title" value="<?= e($title) ?>" required data-label="Title">
    </div>

    <div class="field full">
      <label for="desc">Description *</label>
      <textarea class="input" id="desc" name="description" maxlength="500" required data-label="Description"><?= e($description) ?></textarea>
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
      <label for="photo">Change Photo (optional, max 5 MB)</label>
      <input class="input" id="photo" name="photo" type="file" accept="image/png,image/jpeg,image/webp">
      <?php if ($ticket['photo_path']): ?>
        <p style="font-size:12px;margin:6px 0 0;color:#666;">Current photo: <a href="<?= e($ticket['photo_path']) ?>" target="_blank">View attached photo</a></p>
      <?php endif; ?>
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="my-requests-maintenance.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit">Save Changes</button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
