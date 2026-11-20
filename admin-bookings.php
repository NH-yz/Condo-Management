<?php
// admin-bookings.php - Management Bookings Approval and Administration

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - All Requests (Bookings)';

// Handle Admin Actions: approve, reject, used, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if ($booking_id > 0) {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'approved' WHERE id = :id AND status = 'pending'");
            $stmt->execute([':id' => $booking_id]);
            setFlash('success', 'Booking approved.');

        } elseif ($action === 'reject') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'rejected' WHERE id = :id AND status = 'pending'");
            $stmt->execute([':id' => $booking_id]);
            setFlash('info', 'Booking marked as rejected.');

        } elseif ($action === 'used') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = :id AND status = 'approved'");
            $stmt->execute([':id' => $booking_id]);
            setFlash('success', 'Booking marked as completed (used).');

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = :id");
            $stmt->execute([':id' => $booking_id]);
            setFlash('success', 'Booking record deleted.');
        }
    }
    header('Location: admin-bookings.php');
    exit;
}

// Fetch all bookings
$stmt = $pdo->query("
    SELECT b.*, f.name AS facility_name, f.hourly_rate,
           r.unit_no, r.tower, u.full_name AS resident_name, u.email AS resident_email, u.phone AS resident_phone
    FROM bookings b
    JOIN facilities f ON b.facility_id = f.id
    JOIN residents r ON b.resident_id = r.id
    JOIN users u ON r.user_id = u.id
    ORDER BY b.booking_date DESC, b.start_hour DESC
");
$bookings = $stmt->fetchAll();

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>All Requests</h1>
  <div class="toggle">
    <a href="admin-maintenance.php">Maintenance</a>
    <a class="on" href="admin-bookings.php">Bookings</a>
  </div>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by resident, unit or facility...">
  <div class="chips">
    <button class="chip on" data-filter="all">All</button>
    <button class="chip" data-filter="pending">Pending</button>
    <button class="chip" data-filter="approved">Approved</button>
    <button class="chip" data-filter="cancelled">Cancelled</button>
    <button class="chip" data-filter="completed">Completed</button>
  </div>
  <input class="input" id="dateFilter" placeholder="Date: dd/mm/yyyy" style="width:160px;">
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>Booking ID</th>
        <th>Resident</th>
        <th>Unit</th>
        <th>Facility</th>
        <th>Date</th>
        <th>Time</th>
        <th>Total (RM)</th>
        <th>Status</th>
        <th style="width:230px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($bookings)): ?>
        <tr>
          <td colspan="9" style="text-align:center;padding:20px;color:#777;">No facility bookings found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($bookings as $b): ?>
          <?php
            $timeStr = formatTimeRange($b['start_hour'], $b['end_hour']);
            $viewData = [
              'Booking No'    => $b['booking_no'],
              'Resident'      => $b['resident_name'] . ' (' . $b['resident_email'] . ', ' . $b['resident_phone'] . ')',
              'Unit'          => $b['unit_no'] . ' (' . $b['tower'] . ')',
              'Facility'      => $b['facility_name'],
              'Date'          => formatDate($b['booking_date']),
              'Time'          => $timeStr,
              'Hourly Rate'   => 'RM ' . number_format($b['hourly_rate'], 2),
              'Total'         => 'RM ' . number_format($b['total_amount'], 2),
              'Status'        => ucfirst($b['status']),
              'Remarks'       => $b['remarks'] ?? 'None',
              'Created At'    => formatDate($b['created_at'])
            ];
          ?>
          <tr data-status="<?= e($b['status']) ?>" data-date="<?= e(formatDate($b['booking_date'])) ?>">
            <td><strong><?= e($b['booking_no']) ?></strong></td>
            <td><?= e($b['resident_name']) ?></td>
            <td><?= e($b['unit_no']) ?></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= e(formatDate($b['booking_date'])) ?></td>
            <td><?= e($timeStr) ?></td>
            <td><?= number_format($b['total_amount'], 2) ?></td>
            <td><span class="status <?= strtolower(e($b['status'])) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json='<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES, 'UTF-8') ?>' data-title="Booking: <?= e($b['booking_no']) ?>">View</button>

              <?php if ($b['status'] === 'pending'): ?>
                <form method="POST" action="admin-bookings.php" style="display:inline;">
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-success">Approve</button>
                </form>

                <form method="POST" action="admin-bookings.php" style="display:inline;">
                  <input type="hidden" name="action" value="reject">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-danger" data-confirm="Reject this booking?">Reject</button>
                </form>
              <?php endif; ?>

              <?php if ($b['status'] === 'approved'): ?>
                <form method="POST" action="admin-bookings.php" style="display:inline;">
                  <input type="hidden" name="action" value="used">
                  <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn btn-dark" data-confirm="Mark this booking as Completed (Facility was used)?">Used</button>
                </form>
              <?php endif; ?>

              <form method="POST" action="admin-bookings.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete booking <?= e($b['booking_no']) ?>?">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No bookings match your search or filter.</p>
<p class="note">Admin approves / rejects pending bookings, marks used bookings as completed, or deletes records.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
