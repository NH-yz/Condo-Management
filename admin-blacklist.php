<?php
// admin-blacklist.php - Blacklist Management (CRUD: Read, Lift & Delete)
// Blacklisted residents cannot book facilities while their ban is active.

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - Blacklist';

// Handle Lift / Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $bl_id  = (int)($_POST['blacklist_id'] ?? 0);
    $action = $_POST['action'];

    if ($bl_id > 0 && $action === 'lift') {
        $stmt = $pdo->prepare("UPDATE blacklist SET status = 'Lifted', lifted_at = NOW() WHERE id = :id AND status = 'Active'");
        $stmt->execute([':id' => $bl_id]);
        setFlash('success', 'Ban lifted. The resident can book facilities again.');
    } elseif ($bl_id > 0 && $action === 'delete') {
        $stmt = $pdo->prepare("DELETE FROM blacklist WHERE id = :id");
        $stmt->execute([':id' => $bl_id]);
        setFlash('success', 'Blacklist record deleted.');
    }
    header('Location: admin-blacklist.php');
    exit;
}

// Fetch blacklist records
$stmt = $pdo->query("
    SELECT b.*,
           r.unit_no, r.tower,
           u.full_name, u.email,
           au.full_name AS added_by_name
    FROM blacklist b
    JOIN residents r ON b.resident_id = r.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN users au ON b.added_by = au.id
    ORDER BY (b.status = 'Active') DESC, b.created_at DESC
");
$records = $stmt->fetchAll();

$today = date('Y-m-d');

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Blacklist</h1>
  <a class="btn btn-dark btn-lg" href="admin-blacklist-add.php">+ Add to Blacklist</a>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by resident, unit or reason...">
  <div class="chips">
    <button class="chip on" data-filter="all">All</button>
    <button class="chip" data-filter="banned">Active</button>
    <button class="chip" data-filter="expired">Expired</button>
    <button class="chip" data-filter="lifted">Lifted</button>
  </div>
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>Resident</th>
        <th>Unit</th>
        <th>Reason</th>
        <th>Banned Until</th>
        <th>Added By</th>
        <th>Date Added</th>
        <th>Status</th>
        <th style="width:220px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($records)): ?>
        <tr>
          <td colspan="9" style="text-align:center;padding:20px;color:#777;">No residents are blacklisted.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($records as $b): ?>
          <?php
            // Effective status: an 'Active' record past its end date is shown as Expired
            if ($b['status'] === 'Lifted') {
                $state = 'lifted'; $label = 'Lifted';
            } elseif ($b['banned_until'] && $b['banned_until'] < $today) {
                $state = 'expired'; $label = 'Expired';
            } else {
                $state = 'banned'; $label = 'Active';
            }
            $viewData = [
                'Record ID'    => 'BL' . str_pad((string)$b['id'], 3, '0', STR_PAD_LEFT),
                'Resident'     => $b['full_name'] . ' (' . $b['email'] . ')',
                'Unit'         => $b['unit_no'] . ' (' . $b['tower'] . ')',
                'Reason'       => $b['reason'],
                'Notes'        => $b['notes'] ?: '-',
                'Banned Until' => $b['banned_until'] ? formatDate($b['banned_until']) : 'Indefinite',
                'Added By'     => $b['added_by_name'] ?: '-',
                'Date Added'   => formatDate($b['created_at']),
                'Date Lifted'  => $b['lifted_at'] ? formatDate($b['lifted_at']) : '-',
                'Status'       => $label,
            ];
            $viewData = array_map(fn($v) => e((string)$v), $viewData); // modal renders values as HTML
          ?>
          <tr data-status="<?= e($state) ?>">
            <td><strong>BL<?= str_pad((string)$b['id'], 3, '0', STR_PAD_LEFT) ?></strong></td>
            <td><?= e($b['full_name']) ?></td>
            <td><?= e($b['unit_no']) ?></td>
            <td><?= e($b['reason']) ?></td>
            <td><?= $b['banned_until'] ? e(formatDate($b['banned_until'])) : 'Indefinite' ?></td>
            <td><?= e($b['added_by_name'] ?: '-') ?></td>
            <td><?= e(formatDate($b['created_at'])) ?></td>
            <td><span class="status <?= e($state) ?>"><?= e($label) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json="<?= e(json_encode($viewData, JSON_UNESCAPED_UNICODE)) ?>" data-title="Blacklist Record">View</button>
              <?php if ($b['status'] === 'Active'): ?>
                <a class="btn" href="admin-blacklist-add.php?id=<?= (int)$b['id'] ?>">Edit</a>
                <?php if ($state === 'banned'): ?>
                  <form method="POST" action="admin-blacklist.php" style="display:inline;">
                    <input type="hidden" name="action" value="lift">
                    <input type="hidden" name="blacklist_id" value="<?= (int)$b['id'] ?>">
                    <button type="submit" class="btn" data-confirm="Lift the ban on <?= e($b['full_name']) ?> (Unit <?= e($b['unit_no']) ?>)? They will be able to book facilities again.">Lift</button>
                  </form>
                <?php endif; ?>
              <?php endif; ?>
              <form method="POST" action="admin-blacklist.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="blacklist_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete this blacklist record for <?= e($b['full_name']) ?>? This cannot be undone.">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No blacklist records found matching your filters.</p>
<p class="note">Blacklisted residents cannot book facilities while their ban is active. Lift and Delete show a confirmation dialog first.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
