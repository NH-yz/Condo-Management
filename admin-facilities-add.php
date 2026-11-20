<?php
// admin-facilities-add.php - Add or Edit Facility (CRUD: Create & Update)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Facility' : 'Add New Facility';
$error = '';

$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM facilities WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Facility not found.');
        header('Location: admin-facilities.php');
        exit;
    }
}

$name        = $_POST['name']        ?? ($existing['name'] ?? '');
$hourly_rate = $_POST['hourly_rate'] ?? ($existing['hourly_rate'] ?? '0.00');
$description = $_POST['description'] ?? ($existing['description'] ?? '');
$open_hour   = $_POST['open_hour']   ?? ($existing['open_hour'] ?? 8);
$close_hour  = $_POST['close_hour']  ?? ($existing['close_hour'] ?? 22);
$status      = $_POST['status']      ?? ($existing['status'] ?? 'Available');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($name);
    $description = trim($description);
    $hourly_rate = trim((string)$hourly_rate);
    $open_hour   = (int)$open_hour;
    $close_hour  = (int)$close_hour;
    $status      = trim($status);

    if ($name === '' || $hourly_rate === '') {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (mb_strlen($name) > 100) {
        $error = 'Facility name must be 100 characters or fewer.';
    } elseif (!is_numeric($hourly_rate) || (float)$hourly_rate < 0 || (float)$hourly_rate > 999999.99) {
        $error = 'Hourly rate must be a number between 0.00 and 999999.99 (use 0 for free facilities).';
    } elseif ($open_hour < 5 || $open_hour > 22 || $close_hour < 5 || $close_hour > 22) {
        $error = 'Please choose valid operating hours.';
    } elseif ($open_hour >= $close_hour) {
        $error = '24/7 bookings are not allowed.';
    } elseif (mb_strlen($description) > 1000) {
        $error = 'Description must be 1000 characters or fewer.';
    } elseif (!in_array($status, ['Available', 'Maintenance'], true)) {
        $error = 'Please select a valid status.';
    } else {
        $rate = round((float)$hourly_rate, 2);

        try {
            // Friendly duplicate-name check (the column is also UNIQUE in the database)
            $dup = $pdo->prepare("SELECT COUNT(*) FROM facilities WHERE name = :n AND id <> :id");
            $dup->execute([':n' => $name, ':id' => $id]);

            if ((int)$dup->fetchColumn() > 0) {
                $error = 'A facility with this name already exists.';
            } else {
                if ($isEdit) {
                    $upd = $pdo->prepare("
                        UPDATE facilities
                        SET name = :n, hourly_rate = :r, description = :d,
                            open_hour = :oh, close_hour = :ch, status = :st
                        WHERE id = :id
                    ");
                    $upd->execute([
                        ':n'  => $name,
                        ':r'  => $rate,
                        ':d'  => $description ?: null,
                        ':oh' => $open_hour,
                        ':ch' => $close_hour,
                        ':st' => $status,
                        ':id' => $id,
                    ]);
                    setFlash('success', "Facility {$name} updated successfully.");

                    // Warn if existing upcoming bookings now fall outside the new operating hours
                    $out = $pdo->prepare("
                        SELECT COUNT(*) FROM bookings
                        WHERE facility_id = :id
                          AND status IN ('pending', 'approved')
                          AND booking_date >= CURDATE()
                          AND (start_hour < :oh OR end_hour > :ch)
                    ");
                    $out->execute([':id' => $id, ':oh' => $open_hour, ':ch' => $close_hour]);
                    $outside = (int)$out->fetchColumn();
                    if ($outside > 0) {
                        setFlash('info', "{$outside} existing upcoming booking(s) fall outside the new operating hours. Please review them in Bookings.");
                    }
                } else {
                    $ins = $pdo->prepare("
                        INSERT INTO facilities (name, hourly_rate, description, open_hour, close_hour, status)
                        VALUES (:n, :r, :d, :oh, :ch, :st)
                    ");
                    $ins->execute([
                        ':n'  => $name,
                        ':r'  => $rate,
                        ':d'  => $description ?: null,
                        ':oh' => $open_hour,
                        ':ch' => $close_hour,
                        ':st' => $status,
                    ]);
                    setFlash('success', "Facility {$name} added successfully.");
                }

                header('Location: admin-facilities.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1><?= $isEdit ? 'Edit Facility Details' : 'Add New Facility' ?></h1>

<form class="card form-card" method="POST" action="admin-facilities-add.php<?= $isEdit ? '?id=' . $id : '' ?>" data-validate="facility" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="name">Facility name *</label>
      <input class="input" id="name" name="name" maxlength="100" placeholder="e.g. Tennis Court" value="<?= e($name) ?>" required data-label="Facility name">
    </div>

    <div class="field">
      <label for="hourly_rate">Hourly rate (RM) *</label>
      <input class="input" id="hourly_rate" name="hourly_rate" type="number" min="0" max="999999.99" step="0.01" placeholder="0.00 for free" value="<?= e((string)$hourly_rate) ?>" required data-label="Hourly rate">
    </div>

    <div class="field">
      <label for="open_hour">Opens at *</label>
      <select class="input" id="open_hour" name="open_hour" required data-label="Opening time">
        <?php for ($h = 5; $h <= 21; $h++): ?>
          <option value="<?= $h ?>" <?= (int)$open_hour === $h ? 'selected' : '' ?>><?= e(formatHour($h)) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="field">
      <label for="close_hour">Closes at *</label>
      <select class="input" id="close_hour" name="close_hour" required data-label="Closing time">
        <?php for ($h = 6; $h <= 22; $h++): ?>
          <option value="<?= $h ?>" <?= (int)$close_hour === $h ? 'selected' : '' ?>><?= e(formatHour($h)) ?></option>
        <?php endfor; ?>
      </select>
    </div>

    <div class="field full">
      <label for="description">Description</label>
      <textarea class="input" id="description" name="description" maxlength="1000" placeholder="What the facility offers, capacity, rules..."><?= e($description) ?></textarea>
    </div>

    <div class="field full">
      <label for="status">Status</label>
      <select class="input" id="status" name="status">
        <option value="Available" <?= $status === 'Available' ? 'selected' : '' ?>>Available (residents can book)</option>
        <option value="Maintenance" <?= $status === 'Maintenance' ? 'selected' : '' ?>>Maintenance (booking disabled)</option>
      </select>
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="admin-facilities.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit"><?= $isEdit ? 'Update Facility' : 'Save Facility' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
