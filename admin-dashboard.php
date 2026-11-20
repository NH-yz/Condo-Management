<?php
// admin-dashboard.php - Management Overview and Analytics Dashboard

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin Dashboard';

// Handle quick technician assignment from dashboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_assign') {
    $ticket_id = (int)($_POST['ticket_id'] ?? 0);
    $technician_id = (int)($_POST['technician_id'] ?? 0);

    if ($ticket_id > 0 && $technician_id > 0) {
        $upd = $pdo->prepare("
            UPDATE maintenance_requests 
            SET technician_id = :tid, 
                status = CASE WHEN status = 'reported' THEN 'review' ELSE status END 
            WHERE id = :id
        ");
        $upd->execute([':tid' => $technician_id, ':id' => $ticket_id]);
        setFlash('success', 'Technician assigned successfully.');
    }
    header('Location: admin-dashboard.php');
    exit;
}

// 1. Dashboard statistics
$todayDate = date('Y-m-d');
$currentMonth = date('m');
$currentYear = date('Y');

// Open tickets: status in ('reported', 'review')
$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status IN ('reported', 'review')");
$openTickets = (int)$stmt->fetchColumn();

// In progress tickets
$stmt = $pdo->query("SELECT COUNT(*) FROM maintenance_requests WHERE status = 'progress'");
$inProgressTickets = (int)$stmt->fetchColumn();

// Resolved this month
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM maintenance_requests 
    WHERE status = 'resolved' 
      AND MONTH(updated_at) = :m 
      AND YEAR(updated_at) = :y
");
$stmt->execute([':m' => $currentMonth, ':y' => $currentYear]);
$resolvedMonth = (int)$stmt->fetchColumn();

// Bookings today
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE booking_date = :t AND status IN ('pending', 'approved')");
$stmt->execute([':t' => $todayDate]);
$bookingsToday = (int)$stmt->fetchColumn();

// 2. Unassigned tickets needing a technician
$stmt = $pdo->query("
    SELECT mr.*, r.unit_no, u.full_name AS resident_name 
    FROM maintenance_requests mr
    JOIN residents r ON mr.resident_id = r.id
    JOIN users u ON r.user_id = u.id
    WHERE mr.technician_id IS NULL AND mr.status IN ('reported', 'review')
    ORDER BY mr.created_at ASC
    LIMIT 6
");
$unassignedTickets = $stmt->fetchAll();

// 3. Technicians list for quick assignment
$tStmt = $pdo->query("SELECT id, full_name, specialty FROM technicians WHERE status = 'Active' ORDER BY full_name ASC");
$activeTechs = $tStmt->fetchAll();

// 4. Booking hourly distribution data for bar chart
$hourBuckets = [
    '8-10AM'  => ['start' => 8,  'end' => 10, 'count' => 0],
    '10-12PM' => ['start' => 10, 'end' => 12, 'count' => 0],
    '12-2PM'  => ['start' => 12, 'end' => 14, 'count' => 0],
    '2-4PM'   => ['start' => 14, 'end' => 16, 'count' => 0],
    '4-6PM'   => ['start' => 16, 'end' => 18, 'count' => 0],
    '6-8PM'   => ['start' => 18, 'end' => 20, 'count' => 0],
    '8-10PM'  => ['start' => 20, 'end' => 22, 'count' => 0],
];

$bStmt = $pdo->query("SELECT start_hour, end_hour FROM bookings WHERE status IN ('approved', 'completed')");
$allBkHours = $bStmt->fetchAll();
foreach ($allBkHours as $bk) {
    foreach ($hourBuckets as $label => &$bkt) {
        if (!($bk['end_hour'] <= $bkt['start'] || $bk['start_hour'] >= $bkt['end'])) {
            $bkt['count']++;
        }
    }
}
unset($bkt);

$maxCount = 1;
foreach ($hourBuckets as $bkt) {
    if ($bkt['count'] > $maxCount) $maxCount = $bkt['count'];
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Management Dashboard</h1>
  <span style="font-size:13px;color:#666;">Today: <strong><?= date('l, d F Y') ?></strong></span>
</div>

<div class="stats">
  <div class="stat">
    <span>Open Tickets</span>
    <b><?= $openTickets ?></b>
  </div>
  <div class="stat">
    <span>In Progress</span>
    <b><?= $inProgressTickets ?></b>
  </div>
  <div class="stat">
    <span>Resolved This Month</span>
    <b><?= $resolvedMonth ?></b>
  </div>
  <div class="stat">
    <span>Bookings Today</span>
    <b><?= $bookingsToday ?></b>
  </div>
</div>

<div class="dash">
  <div class="chart">
    <p class="sub">Peak Facility Usage Distribution (By Time Slots)</p>
    <div class="bars">
      <?php foreach ($hourBuckets as $label => $data): ?>
        <?php 
          $pct = max(10, round(($data['count'] / $maxCount) * 90)); 
        ?>
        <span style="--h:<?= $pct ?>%" title="<?= e($label) ?>: <?= $data['count'] ?> bookings"></span>
      <?php endforeach; ?>
    </div>
    <div class="bar-labels">
      <?php foreach ($hourBuckets as $label => $data): ?>
        <span><?= e($label) ?><br><small>(<?= $data['count'] ?>)</small></span>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
      <p class="sub" style="margin:0;">New Tickets (Need Technician)</p>
      <a class="btn" href="admin-maintenance.php">View All</a>
    </div>

    <table id="tbl">
      <thead>
        <tr>
          <th>Ticket</th>
          <th>Unit</th>
          <th>Title</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($unassignedTickets)): ?>
          <tr>
            <td colspan="5" style="text-align:center;padding:16px;color:#777;">No pending unassigned tickets.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($unassignedTickets as $t): ?>
            <tr>
              <td><strong><?= e($t['ticket_no']) ?></strong></td>
              <td><?= e($t['unit_no']) ?></td>
              <td><?= e($t['title']) ?></td>
              <td><span class="status <?= strtolower(e($t['status'])) ?>"><?= e(ucfirst($t['status'])) ?></span></td>
              <td class="act">
                <button type="button" class="btn btn-dark" onclick="openAssignModal(<?= (int)$t['id'] ?>, '<?= e($t['ticket_no']) ?>')">Assign</button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function openAssignModal(ticketId, ticketNo) {
  let techOptions = '';
  <?php foreach ($activeTechs as $tc): ?>
    techOptions += '<option value="<?= (int)$tc['id'] ?>"><?= e($tc['full_name']) ?> (<?= e($tc['specialty']) ?>)</option>';
  <?php endforeach; ?>

  const html = `
    <form method="POST" action="admin-dashboard.php" style="margin:0;">
      <input type="hidden" name="action" value="quick_assign">
      <input type="hidden" name="ticket_id" value="${ticketId}">
      <p>Assign technician for Ticket <strong>${ticketNo}</strong>:</p>
      <div class="field" style="margin:12px 0;">
        <label>Select Technician</label>
        <select class="input" name="technician_id" required>
          <option value="">Choose technician...</option>
          ${techOptions}
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px;">
        <button type="button" class="btn" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-dark">Confirm Assignment</button>
      </div>
    </form>
  `;
  openModal('Assign Technician', html);
}
</script>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
