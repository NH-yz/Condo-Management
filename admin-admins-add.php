<?php
// admin-admins-add.php - Add or Edit Administrator (CRUD: Create & Update)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Admin' : 'Add New Admin';
$error = '';

$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name, u.email, u.phone 
        FROM admins a 
        JOIN users u ON a.user_id = u.id 
        WHERE a.id = :id LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Administrator not found.');
        header('Location: admin-admins.php');
        exit;
    }
}

$fullname = $_POST['fullname'] ?? ($existing['full_name'] ?? '');
$staffid  = $_POST['staffid']  ?? ($existing['staff_id'] ?? '');
$email    = $_POST['email']    ?? ($existing['email'] ?? '');
$phone    = $_POST['phone']    ?? ($existing['phone'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $staffid  = strtoupper(trim($_POST['staffid'] ?? ''));
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $phone    = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    // Validations
    if (empty($fullname) || empty($staffid) || empty($email) || empty($phone)) {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,18}$/', $phone)) {
        $error = 'Please enter a valid phone number (e.g. 03-1234 5678).';
    } elseif (!$isEdit && empty($password)) {
        $error = 'Password is required.';
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif (!empty($password) && $password !== $confirm) {
        $error = 'Passwords do not match.';
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
            // Check duplicate staff ID
            $sQuery = "SELECT id FROM admins WHERE staff_id = :sid";
            $sParams = [':sid' => $staffid];
            if ($isEdit) {
                $sQuery .= " AND id != :aid";
                $sParams[':aid'] = $id;
            }
            $sStmt = $pdo->prepare($sQuery);
            $sStmt->execute($sParams);
            if ($sStmt->fetch()) {
                $error = 'This Staff ID is already assigned to another administrator.';
            } else {
                try {
                    $pdo->beginTransaction();

                    if ($isEdit) {
                        if (!empty($password)) {
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $uUpd = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, phone = :ph, password = :pw WHERE id = :uid");
                            $uUpd->execute([':fn' => $fullname, ':em' => $email, ':ph' => $phone, ':pw' => $hashed, ':uid' => $existing['user_id']]);
                        } else {
                            $uUpd = $pdo->prepare("UPDATE users SET full_name = :fn, email = :em, phone = :ph WHERE id = :uid");
                            $uUpd->execute([':fn' => $fullname, ':em' => $email, ':ph' => $phone, ':uid' => $existing['user_id']]);
                        }

                        $aUpd = $pdo->prepare("UPDATE admins SET staff_id = :sid WHERE id = :id");
                        $aUpd->execute([':sid' => $staffid, ':id' => $id]);

                        $pdo->commit();
                        setFlash('success', "Administrator {$fullname} updated successfully.");
                    } else {
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        $uIns = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role) VALUES (:fn, :em, :pw, :ph, 'admin')");
                        $uIns->execute([':fn' => $fullname, ':em' => $email, ':pw' => $hashed, ':ph' => $phone]);
                        $newUserId = (int)$pdo->lastInsertId();

                        $aIns = $pdo->prepare("INSERT INTO admins (user_id, staff_id) VALUES (:uid, :sid)");
                        $aIns->execute([':uid' => $newUserId, ':sid' => $staffid]);

                        $pdo->commit();
                        setFlash('success', "Administrator {$fullname} (Staff ID: {$staffid}) added successfully.");
                    }

                    header('Location: admin-admins.php');
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
}

require_once __DIR__ . '/includes_header.php';
?>

<h1><?= $isEdit ? 'Edit Administrator Details' : 'Add New Admin' ?></h1>

<form class="card form-card" method="POST" action="admin-admins-add.php<?= $isEdit ? '?id=' . $id : '' ?>" data-validate="admin" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="fullname">Full name *</label>
      <input class="input" id="fullname" name="fullname" value="<?= e($fullname) ?>" required data-label="Full name">
    </div>

    <div class="field">
      <label for="staffid">Staff ID *</label>
      <input class="input" id="staffid" name="staffid" placeholder="e.g. ST-004" value="<?= e($staffid) ?>" required data-label="Staff ID">
    </div>

    <div class="field">
      <label for="email">Email *</label>
      <input class="input" id="email" name="email" type="email" value="<?= e($email) ?>" required data-label="Email">
    </div>

    <div class="field">
      <label for="phone">Phone *</label>
      <input class="input" id="phone" name="phone" placeholder="e.g. 03-1234 5678" value="<?= e($phone) ?>" required data-label="Phone">
    </div>

    <div class="field">
      <label for="password">Password <?= $isEdit ? '(Leave empty to keep current)' : '*' ?></label>
      <input class="input" id="password" name="password" type="password" placeholder="At least 6 characters" <?= $isEdit ? '' : 'required' ?> data-label="Password">
    </div>

    <div class="field">
      <label for="confirm">Confirm password <?= $isEdit ? '' : '*' ?></label>
      <input class="input" id="confirm" name="confirm" type="password" placeholder="Re-enter password" <?= $isEdit ? '' : 'required' ?> data-label="Confirm password">
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="admin-admins.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit"><?= $isEdit ? 'Update Admin' : 'Save Admin' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
