<?php
// admin-technicians.php - Technician Management (CRUD: Read & Delete)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - Technicians';

// Handle Delete Technician Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $tech_id = (int)($_POST['technician_id'] ?? 0);
    if ($tech_id > 0) {
        $stmt = $pdo->prepare("DELETE FROM technicians WHERE id = :id");
        $stmt->execute([':id' => $tech_id]);
        setFlash('success', 'Technician record deleted.');
    }
    header('Location: admin-technicians.php');
    exit;
}

// Fetch technicians with active open tickets count
$stmt = $pdo->query("
    SELECT t.*, 
           COUNT(CASE WHEN mr.status IN ('review', 'progress') THEN 1 END) AS open_tickets
    FROM technicians t
    LEFT JOIN maintenance_requests mr ON mr.technician_id = t.id
    GROUP BY t.id
    ORDER BY t.full_name ASC
");
$technicians = $stmt->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Technicians</h1>
  <a class="btn btn-dark btn-lg" href="admin-technicians-add.php">+ Add New Technician</a>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by name or specialty...">
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Specialty</th>
        <th>Phone</th>
        <th>Email</th>
        <th>Open Tickets</th>
        <th>Status</th>
        <th style="width:140px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($technicians)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:20px;color:#777;">No technicians registered yet.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($technicians as $t): ?>
          <tr>
            <td><strong>T<?= str_pad((string)$t['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
            <td><?= e($t['full_name']) ?></td>
            <td><?= e($t['specialty']) ?></td>
            <td><?= e($t['phone']) ?></td>
            <td><?= e($t['email'] ?? '-') ?></td>
            <td><strong style="color:<?= $t['open_tickets'] > 0 ? '#b00020' : '#155724' ?>;"><?= (int)$t['open_tickets'] ?></strong></td>
            <td><span class="status <?= $t['status'] === 'Active' ? 'resolved' : 'cancelled' ?>"><?= e($t['status']) ?></span></td>
            <td class="act">
              <a class="btn" href="admin-technicians-add.php?id=<?= (int)$t['id'] ?>">Edit</a>

              <form method="POST" action="admin-technicians.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="technician_id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to delete technician <?= e($t['full_name']) ?>?">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No technicians found matching your search.</p>
<p class="note">Delete button shows a confirmation dialog before removing the technician record.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
