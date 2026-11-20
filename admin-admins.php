<?php
// admin-admins.php - Administrator Accounts Management (CRUD: Read & Delete)

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$cur_user = currentUser();
$pageTitle = 'Admin - Admins';

// Handle Delete Admin Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    if ($admin_id > 0) {
        $stmt = $pdo->prepare("SELECT user_id FROM admins WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $admin_id]);
        $uid = (int)$stmt->fetchColumn();

        if ($uid) {
            // Prevent self-deletion
            if ($uid === (int)$cur_user['id']) {
                setFlash('error', 'You cannot delete your own active administrator account.');
            } else {
                $del = $pdo->prepare("DELETE FROM users WHERE id = :uid");
                $del->execute([':uid' => $uid]);
                setFlash('success', 'Administrator account removed successfully.');
            }
        } else {
            setFlash('error', 'Admin not found.');
        }
    }
    header('Location: admin-admins.php');
    exit;
}

// Fetch admins
$stmt = $pdo->query("
    SELECT a.id AS admin_id, a.staff_id,
           u.id AS user_id, u.full_name, u.email, u.phone, u.created_at
    FROM admins a
    JOIN users u ON a.user_id = u.id
    ORDER BY a.staff_id ASC
");
$admins = $stmt->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Admins</h1>
  <a class="btn btn-dark btn-lg" href="admin-admins-add.php">+ Add New Admin</a>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by name, staff ID or email...">
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Staff ID</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Created</th>
        <th style="width:140px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($admins)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:20px;color:#777;">No administrators found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($admins as $a): ?>
          <tr>
            <td><strong>A<?= str_pad((string)$a['admin_id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
            <td>
              <?= e($a['full_name']) ?>
              <?php if ((int)$a['user_id'] === (int)$cur_user['id']): ?>
                <span style="font-size:11px;color:#137333;font-weight:700;">(You)</span>
              <?php endif; ?>
            </td>
            <td><?= e($a['staff_id']) ?></td>
            <td><?= e($a['email']) ?></td>
            <td><?= e($a['phone']) ?></td>
            <td><?= e(formatDate($a['created_at'])) ?></td>
            <td class="act">
              <a class="btn" href="admin-admins-add.php?id=<?= (int)$a['admin_id'] ?>">Edit</a>

              <?php if ((int)$a['user_id'] !== (int)$cur_user['id']): ?>
                <form method="POST" action="admin-admins.php" style="display:inline;">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="admin_id" value="<?= (int)$a['admin_id'] ?>">
                  <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to delete administrator <?= e($a['full_name']) ?> (Staff ID: <?= e($a['staff_id']) ?>)?">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No admins found matching your search.</p>
<p class="note">Delete button shows a confirmation dialog before removing administrator records.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
