<?php
// admin-maintenance.php - Management Maintenance Tickets Management

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - All Requests (Maintenance)';

// Handle Admin Actions: assign, update_status, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);

    if ($ticket_id > 0) {
        if ($action === 'assign') {
            $technician_id = !empty($_POST['technician_id']) ? (int)$_POST['technician_id'] : null;
            $stmt = $pdo->prepare("
                UPDATE maintenance_requests 
                SET technician_id = :tid, 
                    status = CASE WHEN status = 'reported' THEN 'review' ELSE status END 
                WHERE id = :id
            ");
            $stmt->execute([':tid' => $technician_id, ':id' => $ticket_id]);
            setFlash('success', 'Technician assignment updated.');

        } elseif ($action === 'update_status') {
            $newStatus = trim($_POST['status'] ?? '');
            $adminNotes = trim($_POST['admin_notes'] ?? '');
            $validStatuses = ['reported', 'review', 'progress', 'resolved', 'cancelled'];

            if (in_array($newStatus, $validStatuses)) {
                $stmt = $pdo->prepare("
                    UPDATE maintenance_requests 
                    SET status = :st, admin_notes = :notes 
                    WHERE id = :id
                ");
                $stmt->execute([
                    ':st'    => $newStatus,
                    ':notes' => $adminNotes ?: null,
                    ':id'    => $ticket_id
                ]);
                setFlash('success', 'Ticket status and notes updated.');
            } else {
                setFlash('error', 'Invalid status selected.');
            }

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM maintenance_requests WHERE id = :id");
            $stmt->execute([':id' => $ticket_id]);
            setFlash('success', 'Maintenance record deleted.');
        }
    }
    header('Location: admin-maintenance.php');
    exit;
}

// Fetch all maintenance tickets
$stmt = $pdo->query("
    SELECT mr.*, r.unit_no, r.tower, u.full_name AS resident_name, u.email AS resident_email, u.phone AS resident_phone,
           t.full_name AS technician_name, t.phone AS technician_phone
    FROM maintenance_requests mr
    JOIN residents r ON mr.resident_id = r.id
    JOIN users u ON r.user_id = u.id
    LEFT JOIN technicians t ON mr.technician_id = t.id
    ORDER BY mr.created_at DESC
");
$tickets = $stmt->fetchAll();

// Fetch active technicians for dropdown
$tStmt = $pdo->query("SELECT id, full_name, specialty FROM technicians ORDER BY full_name ASC");
$allTechs = $tStmt->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>All Requests</h1>
  <div class="toggle">
    <a class="on" href="admin-maintenance.php">Maintenance</a>
    <a href="admin-bookings.php">Bookings</a>
  </div>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by ID, resident, unit or title...">
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
        <th>ID</th>
        <th>Resident</th>
        <th>Unit</th>
        <th>Title</th>
        <th>Category</th>
        <th>Date</th>
        <th>Technician</th>
        <th>Status</th>
        <th style="width:210px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($tickets)): ?>
        <tr>
          <td colspan="9" style="text-align:center;padding:20px;color:#777;">No maintenance requests found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($tickets as $t): ?>
          <?php
            $viewData = [
              'Ticket No'       => $t['ticket_no'],
              'Resident'        => $t['resident_name'] . ' (' . $t['resident_email'] . ', ' . $t['resident_phone'] . ')',
              'Unit'            => $t['unit_no'] . ' (' . $t['tower'] . ')',
              'Title'           => $t['title'],
              'Category'        => $t['category'],
              'Location'        => $t['location_type'],
              'Urgency'         => $t['urgency'],
              'Description'     => $t['description'],
              'Preferred Date'  => formatDate($t['preferred_date']),
              'Technician'      => $t['technician_name'] ? ($t['technician_name'] . ' - ' . $t['technician_phone']) : 'None',
              'Status'          => ucfirst($t['status']),
              'Admin Notes'     => $t['admin_notes'] ?? 'None',
              'Date Reported'   => formatDate($t['created_at']),
              'Photo'           => $t['photo_path'] ? '<a href="' . e($t['photo_path']) . '" target="_blank"><img src="' . e($t['photo_path']) . '" style="max-width:180px;max-height:120px;display:block;margin-top:6px;border:1px solid #ccc;border-radius:4px;"></a>' : 'No photo'
            ];
          ?>
          <tr data-status="<?= e($t['status']) ?>" data-date="<?= e(formatDate($t['created_at'])) ?>">
            <td><strong><?= e($t['ticket_no']) ?></strong></td>
            <td><?= e($t['resident_name']) ?></td>
            <td><?= e($t['unit_no']) ?></td>
            <td><?= e($t['title']) ?></td>
            <td><?= e($t['category']) ?></td>
            <td><?= e(formatDate($t['created_at'])) ?></td>
            <td class="tech"><?= e($t['technician_name'] ?? 'Not assigned') ?></td>
            <td><span class="status <?= strtolower(e($t['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', $t['status']))) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json='<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES, 'UTF-8') ?>' data-title="Ticket Details: <?= e($t['ticket_no']) ?>">View</button>
              
              <button type="button" class="btn" onclick="openAssignModal(<?= (int)$t['id'] ?>, '<?= e($t['ticket_no']) ?>', <?= (int)($t['technician_id'] ?? 0) ?>)">Assign</button>
              
              <button type="button" class="btn" onclick="openUpdateModal(<?= (int)$t['id'] ?>, '<?= e($t['ticket_no']) ?>', '<?= e($t['status']) ?>', '<?= e(addslashes($t['admin_notes'] ?? '')) ?>')">Update</button>

              <form method="POST" action="admin-maintenance.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Are you sure you want to permanently delete ticket <?= e($t['ticket_no']) ?>?">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No requests match your search or filter.</p>
<p class="note">Admin can review all tickets, assign technicians, update repair progress, and delete records.</p>

<script>
function openAssignModal(id, ticketNo, currentTechId) {
  let techOptions = '<option value="">None (Unassigned)</option>';
  <?php foreach ($allTechs as $tc): ?>
    techOptions += `<option value="<?= (int)$tc['id'] ?>" ${currentTechId === <?= (int)$tc['id'] ?> ? 'selected' : ''}><?= e($tc['full_name']) ?> (<?= e($tc['specialty']) ?>)</option>`;
  <?php endforeach; ?>

  const html = `
    <form method="POST" action="admin-maintenance.php">
      <input type="hidden" name="action" value="assign">
      <input type="hidden" name="ticket_id" value="${id}">
      <p>Assign technician for <strong>${ticketNo}</strong>:</p>
      <div class="field" style="margin:12px 0;">
        <label>Technician</label>
        <select class="input" name="technician_id">
          ${techOptions}
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
        <button type="button" class="btn" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-dark">Save Assignment</button>
      </div>
    </form>
  `;
  openModal('Assign Technician', html);
}

function openUpdateModal(id, ticketNo, currentStatus, currentNotes) {
  const statuses = ['reported', 'review', 'progress', 'resolved', 'cancelled'];
  let statusOptions = '';
  statuses.forEach(s => {
    const label = s.charAt(0).toUpperCase() + s.slice(1);
    statusOptions += `<option value="${s}" ${currentStatus === s ? 'selected' : ''}>${label}</option>`;
  });

  const html = `
    <form method="POST" action="admin-maintenance.php">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="ticket_id" value="${id}">
      <p>Update Status &amp; Notes for <strong>${ticketNo}</strong>:</p>
      <div class="field">
        <label>Status</label>
        <select class="input" name="status">
          ${statusOptions}
        </select>
      </div>
      <div class="field" style="margin-top:10px;">
        <label>Admin / Inspection Notes</label>
        <textarea class="input" name="admin_notes" placeholder="Notes on parts replaced, technician remarks...">${currentNotes}</textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
        <button type="button" class="btn" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-dark">Save Status</button>
      </div>
    </form>
  `;
  openModal('Update Ticket Status', html);
}
</script>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
