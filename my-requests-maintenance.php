<?php
// my-requests-maintenance.php - Resident Maintenance Requests List

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$pageTitle = 'My Requests - Maintenance';

// Handle resident cancellation request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $chk = $pdo->prepare("SELECT * FROM maintenance_requests WHERE id = :id AND resident_id = :rid LIMIT 1");
        $chk->execute([':id' => $id, ':rid' => $user['res_id']]);
        $req = $chk->fetch();

        if ($req) {
            if (in_array($req['status'], ['reported', 'review'])) {
                $upd = $pdo->prepare("UPDATE maintenance_requests SET status = 'cancelled' WHERE id = :id");
                $upd->execute([':id' => $id]);
                setFlash('success', "Ticket {$req['ticket_no']} has been cancelled.");
            } else {
                setFlash('error', "Cannot cancel this request because work has already begun.");
            }
        } else {
            setFlash('error', "Request not found.");
        }
    }
    header('Location: my-requests-maintenance.php');
    exit;
}

// Fetch maintenance requests for this resident
try {
    $stmt = $pdo->prepare("
        SELECT mr.*, t.full_name AS technician_name, t.phone AS technician_phone 
        FROM maintenance_requests mr
        LEFT JOIN technicians t ON mr.technician_id = t.id
        WHERE mr.resident_id = :rid 
        ORDER BY mr.created_at DESC
    ");
    $stmt->execute([':rid' => $user['res_id']]);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $requests = [];
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>My Requests</h1>
  <div class="toggle">
    <a class="on" href="my-requests-maintenance.php">Maintenance</a>
    <a href="my-requests-bookings.php">Bookings</a>
  </div>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by ID or title...">
  <div class="chips">
    <button class="chip on" data-filter="all">All</button>
    <button class="chip" data-filter="reported">Reported</button>
    <button class="chip" data-filter="review">Under review</button>
    <button class="chip" data-filter="progress">In progress</button>
    <button class="chip" data-filter="resolved">Resolved</button>
    <button class="chip" data-filter="cancelled">Cancelled</button>
  </div>
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>Ticket ID</th>
        <th>Title</th>
        <th>Category</th>
        <th>Date Submitted</th>
        <th>Technician</th>
        <th>Status</th>
        <th style="width:170px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($requests)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:20px;color:#777;">No maintenance requests found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($requests as $r): ?>
          <?php
            $viewData = [
              'Ticket No'       => $r['ticket_no'],
              'Title'           => $r['title'],
              'Category'        => $r['category'],
              'Location'        => $r['location_type'],
              'Urgency'         => $r['urgency'],
              'Description'     => $r['description'],
              'Preferred Date'  => formatDate($r['preferred_date']),
              'Technician'      => $r['technician_name'] ? ($r['technician_name'] . ' (' . $r['technician_phone'] . ')') : 'Not assigned yet',
              'Status'          => ucfirst($r['status']),
              'Admin Notes'     => $r['admin_notes'] ?? 'None',
              'Date Submitted'  => formatDate($r['created_at']),
              'Photo'           => $r['photo_path'] ? '<a href="' . e($r['photo_path']) . '" target="_blank"><img src="' . e($r['photo_path']) . '" style="max-width:180px;max-height:120px;display:block;margin-top:6px;border:1px solid #ccc;border-radius:4px;"></a>' : 'No photo attached'
            ];
          ?>
          <tr data-status="<?= e($r['status']) ?>" data-date="<?= e(formatDate($r['created_at'])) ?>">
            <td><strong><?= e($r['ticket_no']) ?></strong></td>
            <td><?= e($r['title']) ?></td>
            <td><?= e($r['category']) ?></td>
            <td><?= e(formatDate($r['created_at'])) ?></td>
            <td class="tech"><?= e($r['technician_name'] ?? 'Not assigned') ?></td>
            <td><span class="status <?= strtolower(e($r['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $r['status']))) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json='<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES, 'UTF-8') ?>' data-title="Ticket Details: <?= e($r['ticket_no']) ?>">View</button>
              
              <?php if ($r['status'] === 'reported'): ?>
                <a class="btn" href="edit-defect.php?id=<?= (int)$r['id'] ?>">Edit</a>
              <?php endif; ?>

              <?php if (in_array($r['status'], ['reported', 'review'])): ?>
                <form method="POST" action="my-requests-maintenance.php" style="display:inline;">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="btn" data-confirm="Are you sure you want to cancel this maintenance request?">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No requests match your search or filter.</p>
<p class="note">Edit / Cancel is only available before repair work begins &bull; Cancel shows a confirmation dialog.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
