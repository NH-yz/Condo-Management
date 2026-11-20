<?php
// admin-residents-add.php - Add or Edit Resident (CRUD: Create & Update)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Resident' : 'Add New Resident';
$error = '';

$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name, u.email, u.phone 
        FROM residents r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Resident not found.');
        header('Location: admin-residents.php');
        exit;
    }
}

$fullname = $_POST['fullname'] ?? ($existing['full_name'] ?? '');
$unit     = $_POST['unit']     ?? ($existing['unit_no'] ?? '');
$email    = $_POST['email']    ?? ($existing['email'] ?? '');
$phone    = $_POST['phone']    ?? ($existing['phone'] ?? '');
$tower    = $_POST['tower']    ?? ($existing['tower'] ?? 'Tower A');
$movein   = $_POST['movein']   ?? ($existing['move_in_date'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $unit     = strtoupper(trim($_POST['unit'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $tower    = trim($_POST['tower'] ?? 'Tower A');
    $movein   = trim($_POST['movein'] ?? '');
    $password = $_POST['password'] ?? '';

    $movein_sql = parseInputDate($movein);

    // Form Validations
    if (empty($fullname) || empty($unit) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[A-Za-z]-\d{1,2}-\d{2}$/', $unit)) {
        $error = 'Unit format must look like A-12-03 or B-03-07.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,18}$/', $phone)) {
        $error = 'Please enter a valid phone number (e.g. 012-345 6789).';
    } elseif (!$isEdit && (strlen($password) < 6)) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($isEdit && !empty($password) && (strlen($password) < 6)) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        // Check duplicate email
        $chkQuery = "SELECT id FROM users WHERE email = :email";
        $chkParams = [':email' => $email];
        if ($isEdit) {
            $chkQuery .= " AND id != :uid";
            $chkParams[':uid'] = $existing['user_id'];
        }
        $chkStmt = $pdo->prepare($chkQuery);
        $chkStmt->execute($chkParams);
        if ($chkStmt->fetch()) {
            $error = 'This email address is already registered.';
        } else {
            try {
                $pdo->beginTransaction();

                if ($isEdit) {
                    // Update user
                    if (!empty($password)) {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $uUpd = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, phone = :ph, password = :pw WHERE id = :uid");
                        $uUpd->execute([':fn' => $fullname, ':em' => $email, ':ph' => $phone, ':pw' => $hashed, ':uid' => $existing['user_id']]);
                    } else {
                        $uUpd = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, phone = :ph WHERE id = :uid");
                        $uUpd->execute([':fn' => $fullname, ':em' => $email, ':ph' => $phone, ':uid' => $existing['user_id']]);
                    }

                    // Update resident
                    $rUpd = $pdo->prepare("UPDATE residents SET unit_no = :unit, tower = :tow, move_in_date = :mv WHERE id = :id");
                    $rUpd->execute([':unit' => $unit, ':tow' => $tower, ':mv' => $movein_sql, ':id' => $id]);

                    $pdo->commit();
                    setFlash('success', "Resident {$fullname} updated successfully.");
                } else {
                    // Insert new user
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $uIns = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (:fn, :em, :pw, :ph, 'resident')");
                    $uIns->execute([':fn' => $fullname, ':em' => $email, ':pw' => $hashed, ':ph' => $phone]);
                    $newUserId = (int)$pdo->lastInsertId();

                    // Insert resident
                    $rIns = $pdo->prepare("INSERT INTO residents (user_id, unit_no, tower, move_in_date) VALUES (:uid, :unit, :tow, :mv)");
                    $rIns->execute([':uid' => $newUserId, ':unit' => $unit, ':tow' => $tower, ':mv' => $movein_sql]);

                    $pdo->commit();
                    setFlash('success', "Resident {$fullname} (Unit {$unit}) registered successfully.");
                }

                header('Location: admin-residents.php');
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1><?= $isEdit ? 'Edit Resident Details' : 'Add New Resident' ?></h1>

<form class="card form-card" method="POST" action="admin-residents-add.php<?= $isEdit ? '?id=' . $id : '' ?>" data-validate="resident" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="fullname">Full name *</label>
      <input class="input" id="fullname" name="fullname" value="<?= e($fullname) ?>" required data-label="Full name">
    </div>

    <div class="field">
      <label for="unit">Unit no. *</label>
      <input class="input" id="unit" name="unit" placeholder="e.g. A-12-03" value="<?= e($unit) ?>" required data-label="Unit no.">
    </div>

    <div class="field">
      <label for="email">Email *</label>
      <input class="input" id="email" name="email" type="email" value="<?= e($email) ?>" required data-label="Email">
    </div>

    <div class="field">
      <label for="phone">Phone *</label>
      <input class="input" id="phone" name="phone" placeholder="e.g. 012-345 6789" value="<?= e($phone) ?>" required data-label="Phone">
    </div>

    <div class="field">
      <label for="tower">Tower</label>
      <select class="input" id="tower" name="tower">
        <option value="Tower A" <?= $tower === 'Tower A' ? 'selected' : '' ?>>Tower A</option>
        <option value="Tower B" <?= $tower === 'Tower B' ? 'selected' : '' ?>>Tower B</option>
      </select>
    </div>

    <div class="field">
      <label for="movein">Move-in date</label>
      <input class="input" id="movein" name="movein" type="date" value="<?= e($movein) ?>">
    </div>

    <div class="field full">
      <label for="password">Password <?= $isEdit ? '(Leave empty to keep current password)' : '*' ?></label>
      <input class="input" id="password" name="password" type="password" placeholder="<?= $isEdit ? '••••••••' : 'At least 6 characters' ?>" <?= $isEdit ? '' : 'required' ?> data-label="Password">
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="admin-residents.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit"><?= $isEdit ? 'Update Resident' : 'Save Resident' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
