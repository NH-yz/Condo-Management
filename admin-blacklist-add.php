<?php
// admin-blacklist-add.php - Add or Edit a Blacklist record (CRUD: Create & Update)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$cur_user = currentUser();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Blacklist Record' : 'Add to Blacklist';
$error = '';

$reasons = ['Unpaid fees', 'Facility damage', 'No-show / late cancellation', 'Noise / misconduct', 'Other'];

$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT b.*, r.unit_no, r.tower, u.full_name
        FROM blacklist b
        JOIN residents r ON b.resident_id = r.id
        JOIN users u ON r.user_id = u.id
        WHERE b.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Blacklist record not found.');
        header('Location: admin-blacklist.php');
        exit;
    }
    if ($existing['status'] === 'Lifted') {
        setFlash('error', 'This ban has already been lifted and can no longer be edited.');
        header('Location: admin-blacklist.php');
        exit;
    }
}

// Residents without a currently active ban (plus the record's own resident when editing)
$rStmt = $pdo->prepare("
    SELECT r.id, r.unit_no, r.tower, u.full_name
    FROM residents r
    JOIN users u ON r.user_id = u.id
    WHERE r.id NOT IN (
        SELECT resident_id FROM blacklist
        WHERE status = 'Active' AND (banned_until IS NULL OR banned_until >= CURDATE())
    )
    OR r.id = :cur
    ORDER BY r.tower ASC, r.unit_no ASC
");
$rStmt->execute([':cur' => $existing['resident_id'] ?? 0]);
$residents = $rStmt->fetchAll();

$resident_id  = (int)($_POST['resident_id'] ?? ($existing['resident_id'] ?? 0));
$reason       = $_POST['reason']       ?? ($existing['reason'] ?? '');
$notes        = $_POST['notes']        ?? ($existing['notes'] ?? '');
$banned_until = $_POST['banned_until'] ?? ($existing['banned_until'] ?? '');
$duration     = $_POST['duration']     ?? (($existing && !$existing['banned_until']) || !$isEdit ? 'indefinite' : 'until');
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $duration = $existing['banned_until'] ? 'until' : 'indefinite';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes    = trim($notes);
    $until    = null;

    if ($duration === 'until') {
        $until = parseInputDate(trim($banned_until));
    }

    if (!$resident_id) {
        $error = 'Please select a resident.';
    } elseif (!in_array($reason, $reasons, true)) {
        $error = 'Please select a reason.';
    } elseif ($duration === 'until' && !$until) {
        $error = 'Please enter a valid end date for the ban.';
    } elseif ($duration === 'until' && $until < date('Y-m-d')) {
        $error = 'The ban end date cannot be in the past.';
    } elseif (strlen($notes) > 500) {
        $error = 'Notes must be 500 characters or fewer.';
    } else {
        // Make sure the resident exists
        $chk = $pdo->prepare("SELECT id FROM residents WHERE id = :id");
        $chk->execute([':id' => $resident_id]);
        if (!$chk->fetch()) {
            $error = 'Selected resident was not found.';
        } else {
            try {
                if ($isEdit) {
                    $upd = $pdo->prepare("UPDATE blacklist SET reason = :rs, notes = :nt, banned_until = :bu WHERE id = :id");
                    $upd->execute([':rs' => $reason, ':nt' => $notes ?: null, ':bu' => $until, ':id' => $id]);
                    setFlash('success', 'Blacklist record updated successfully.');
                } else {
                    // Prevent duplicate active bans for the same resident
                    if (getActiveBlacklist($pdo, $resident_id)) {
                        $error = 'This resident is already blacklisted.';
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO blacklist (resident_id, reason, notes, banned_until, status, added_by)
                            VALUES (:rid, :rs, :nt, :bu, 'Active', :ab)
                        ");
                        $ins->execute([
                            ':rid' => $resident_id,
                            ':rs'  => $reason,
                            ':nt'  => $notes ?: null,
                            ':bu'  => $until,
                            ':ab'  => $cur_user['id'],
                        ]);
                        setFlash('success', 'Resident added to the blacklist.');
                    }
                }
                if (!$error) {
                    header('Location: admin-blacklist.php');
                    exit;
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1><?= $isEdit ? 'Edit Blacklist Record' : 'Add to Blacklist' ?></h1>

<form class="card form-card" method="POST" action="admin-blacklist-add.php<?= $isEdit ? '?id=' . $id : '' ?>" data-validate="blacklist" novalidate>
  <div class="grid2">
    <div class="field" style="grid-column:1 / -1;">
      <label for="resident_id">Resident *</label>
      <?php if ($isEdit): ?>
        <input class="input" value="<?= e($existing['full_name']) ?> — <?= e($existing['unit_no']) ?> (<?= e($existing['tower']) ?>)" disabled>
        <input type="hidden" name="resident_id" value="<?= (int)$resident_id ?>">
      <?php else: ?>
        <select class="input" id="resident_id" name="resident_id" required data-label="Resident">
          <option value="">Select resident...</option>
          <?php foreach ($residents as $r): ?>
            <option value="<?= (int)$r['id'] ?>" <?= $resident_id === (int)$r['id'] ? 'selected' : '' ?>>
              <?= e($r['full_name']) ?> — <?= e($r['unit_no']) ?> (<?= e($r['tower']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>

    <div class="field">
      <label for="reason">Reason *</label>
      <select class="input" id="reason" name="reason" required data-label="Reason">
        <option value="">Select reason...</option>
        <?php foreach ($reasons as $rs): ?>
          <option value="<?= e($rs) ?>" <?= $reason === $rs ? 'selected' : '' ?>><?= e($rs) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="duration">Ban length *</label>
      <select class="input" id="duration" name="duration">
        <option value="indefinite" <?= $duration === 'indefinite' ? 'selected' : '' ?>>Indefinite (until lifted)</option>
        <option value="until" <?= $duration === 'until' ? 'selected' : '' ?>>Until a set date</option>
      </select>
    </div>

    <div class="field" id="untilWrap" <?= $duration === 'until' ? '' : 'hidden' ?>>
      <label for="banned_until">Banned until *</label>
      <input class="input" id="banned_until" name="banned_until" type="date" min="<?= date('Y-m-d') ?>" value="<?= e($banned_until) ?>">
    </div>

    <div class="field" style="grid-column:1 / -1;">
      <label for="notes">Notes (Optional)</label>
      <textarea class="input" id="notes" name="notes" maxlength="500" rows="4" placeholder="Describe what happened, e.g. incident details or follow-up needed..."><?= e($notes) ?></textarea>
    </div>
  </div>

  <p class="note" style="margin:4px 0 0;">While active, this resident will not be able to submit new facility bookings.</p>
  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="admin-blacklist.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit"><?= $isEdit ? 'Update Record' : 'Add to Blacklist' ?></button>
  </div>
</form>

<script>
  (function () {
    var dur = document.getElementById('duration');
    var wrap = document.getElementById('untilWrap');
    var until = document.getElementById('banned_until');
    var form = dur.form;
    function sync() { wrap.hidden = dur.value !== 'until'; }
    dur.addEventListener('change', sync);
    sync();
    // Client-side check for the end date (the shared validator handles the other required fields)
    form.addEventListener('submit', function (e) {
      if (dur.value === 'until' && !until.value) {
        e.preventDefault();
        var m = document.getElementById('msg');
        m.textContent = 'Please enter an end date for the ban.';
        m.className = 'msg error';
        until.focus();
      }
    });
  })();
</script>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
