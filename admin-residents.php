<?php
// admin-residents.php - Resident Account Management (CRUD: Read & Delete)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - Residents';

// Handle Delete Resident Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $resident_id = (int)($_POST['resident_id'] ?? 0);
    if ($resident_id > 0) {
        // Find associated user_id
        $stmt = $pdo->prepare("SELECT user_id FROM residents WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $resident_id]);
        $uid = $stmt->fetchColumn();

        if ($uid) {
            // Deleting user cascades to residents, bookings, and requests
            $del = $pdo->prepare("DELETE FROM users WHERE id = :uid");
            $del->execute([':uid' => $uid]);
            setFlash('success', 'Resident and associated profile deleted successfully.');
        } else {
            setFlash('error', 'Resident not found.');
        }
    }
    header('Location: admin-residents.php');
    exit;
}

// Fetch residents
$stmt = $pdo->query("
    SELECT r.id AS resident_id, r.unit_no, r.tower, r.move_in_date,
           u.id AS user_id, u.full_name, u.email, u.phone, u.created_at
    FROM residents r
    JOIN users u ON r.user_id = u.id
    ORDER BY r.tower ASC, r.unit_no ASC
");
$residents = $stmt->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Residents</h1>
  <a class="btn btn-dark btn-lg" href="admin-residents-add.php">+ Add New Resident</a>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by name, unit or email...">
  <select class="input" id="tower">
    <option value="">Filter: All Towers</option>
    <option value="Tower A">Tower A</option>
    <option value="Tower B">Tower B</option>
  </select>
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Unit</th>
        <th>Tower</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Move-in Date</th>
        <th style="width:140px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($residents)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:20px;color:#777;">No residents registered yet.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($residents as $r): ?>
          <tr data-tower="<?= e($r['tower']) ?>">
            <td><strong>R<?= str_pad((string)$r['resident_id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
            <td><?= e($r['full_name']) ?></td>
            <td><?= e($r['unit_no']) ?></td>
            <td><?= e($r['tower']) ?></td>
            <td><?= e($r['email']) ?></td>
            <td><?= e($r['phone']) ?></td>
            <td><?= e(formatDate($r['move_in_date'])) ?></td>
            <td class="act">
              <a class="btn" href="admin-residents-add.php?id=<?= (int)$r['resident_id'] ?>">Edit</a>

              <form method="POST" action="admin-residents.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="resident_id" value="<?= (int)$r['resident_id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to delete resident <?= e($r['full_name']) ?> (Unit <?= e($r['unit_no']) ?>)? This cannot be undone.">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No residents found matching your search.</p>
<p class="note">Delete button shows a confirmation dialog before removing resident records.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
