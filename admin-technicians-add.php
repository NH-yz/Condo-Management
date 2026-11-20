<?php
// admin-technicians-add.php - Add or Edit Technician (CRUD: Create & Update)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$isEdit = ($id > 0);
$pageTitle = $isEdit ? 'Edit Technician' : 'Add New Technician';
$error = '';

$existing = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM technicians WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        setFlash('error', 'Technician not found.');
        header('Location: admin-technicians.php');
        exit;
    }
}

$fullname  = $_POST['fullname']  ?? ($existing['full_name'] ?? '');
$phone     = $_POST['phone']     ?? ($existing['phone'] ?? '');
$email     = $_POST['email']     ?? ($existing['email'] ?? '');
$specialty = $_POST['specialty'] ?? ($existing['specialty'] ?? 'Plumbing');
$status    = $_POST['status']    ?? ($existing['status'] ?? 'Active');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname  = trim($_POST['fullname'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $specialty = trim($_POST['specialty'] ?? 'General');
    $status    = trim($_POST['status'] ?? 'Active');

    if (empty($fullname) || empty($phone) || empty($specialty)) {
        $error = 'Please fill in all required fields (marked with *).';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[0-9+\-\s]{8,18}$/', $phone)) {
        $error = 'Please enter a valid phone number (e.g. 012-345 6789).';
    } else {
        try {
            if ($isEdit) {
                $upd = $pdo->prepare("
                    UPDATE technicians 
                    SET full_name = :fn, phone = :ph, email = :em, specialty = :sp, status = :st 
                    WHERE id = :id
                ");
                $upd->execute([
                    ':fn' => $fullname,
                    ':ph' => $phone,
                    ':em' => $email ?: null,
                    ':sp' => $specialty,
                    ':st' => $status,
                    ':id' => $id
                ]);
                setFlash('success', "Technician {$fullname} updated successfully.");
            } else {
                $ins = $pdo->prepare("
                    INSERT INTO technicians (full_name, phone, email, specialty, status) 
                    VALUES (:fn, :ph, :em, :sp, :st)
                ");
                $ins->execute([
                    ':fn' => $fullname,
                    ':ph' => $phone,
                    ':em' => $email ?: null,
                    ':sp' => $specialty,
                    ':st' => $status
                ]);
                setFlash('success', "Technician {$fullname} added successfully.");
            }

            header('Location: admin-technicians.php');
            exit;

        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1><?= $isEdit ? 'Edit Technician Details' : 'Add New Technician' ?></h1>

<form class="card form-card" method="POST" action="admin-technicians-add.php<?= $isEdit ? '?id=' . $id : '' ?>" data-validate="technician" novalidate>
  <div class="grid2">
    <div class="field">
      <label for="fullname">Full name *</label>
      <input class="input" id="fullname" name="fullname" value="<?= e($fullname) ?>" required data-label="Full name">
    </div>

    <div class="field">
      <label for="phone">Phone *</label>
      <input class="input" id="phone" name="phone" placeholder="e.g. 012-111 2233" value="<?= e($phone) ?>" required data-label="Phone">
    </div>

    <div class="field">
      <label for="email">Email</label>
      <input class="input" id="email" name="email" type="email" placeholder="example@email.com" value="<?= e($email) ?>">
    </div>

    <div class="field">
      <label for="specialty">Specialty *</label>
      <select class="input" id="specialty" name="specialty" required data-label="Specialty">
        <option value="Plumbing" <?= $specialty === 'Plumbing' ? 'selected' : '' ?>>Plumbing</option>
        <option value="Electrical" <?= $specialty === 'Electrical' ? 'selected' : '' ?>>Electrical</option>
        <option value="Air-con" <?= $specialty === 'Air-con' ? 'selected' : '' ?>>Air-con</option>
        <option value="General" <?= $specialty === 'General' ? 'selected' : '' ?>>General</option>
        <option value="Lift" <?= $specialty === 'Lift' ? 'selected' : '' ?>>Lift</option>
        <option value="Other" <?= $specialty === 'Other' ? 'selected' : '' ?>>Other</option>
      </select>
    </div>

    <div class="field full">
      <label for="status">Status</label>
      <select class="input" id="status" name="status">
        <option value="Active" <?= $status === 'Active' ? 'selected' : '' ?>>Active</option>
        <option value="Inactive" <?= $status === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </div>
  </div>

  <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

  <div class="form-actions">
    <a class="btn btn-lg" href="admin-technicians.php">Cancel</a>
    <button class="btn btn-dark btn-lg" type="submit"><?= $isEdit ? 'Update Technician' : 'Save Technician' ?></button>
  </div>
</form>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
