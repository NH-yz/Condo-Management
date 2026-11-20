<?php
// my-requests-bookings.php - Resident Bookings List

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$pageTitle = 'My Requests - Bookings';

// Handle resident booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $chk = $pdo->prepare("SELECT * FROM bookings WHERE id = :id AND resident_id = :rid LIMIT 1");
        $chk->execute([':id' => $id, ':rid' => $user['res_id']]);
        $bk = $chk->fetch();

        if ($bk) {
            $today = date('Y-m-d');
            if ($bk['booking_date'] <= $today) {
                setFlash('error', "Cancellations are only allowed before the booking date.");
            } elseif (!in_array($bk['status'], ['pending', 'approved'])) {
                setFlash('error', "This booking cannot be cancelled because it is already {$bk['status']}.");
            } else {
                $upd = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id");
                $upd->execute([':id' => $id]);
                setFlash('success', "Booking {$bk['booking_no']} has been cancelled.");
            }
        } else {
            setFlash('error', "Booking not found.");
        }
    }
    header('Location: my-requests-bookings.php');
    exit;
}

// Fetch bookings for current resident
try {
    $stmt = $pdo->prepare("
        SELECT b.*, f.name AS facility_name, f.hourly_rate 
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        WHERE b.resident_id = :rid 
        ORDER BY b.booking_date DESC, b.start_hour DESC
    ");
    $stmt->execute([':rid' => $user['res_id']]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    $bookings = [];
}

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>My Requests</h1>
  <div class="toggle">
    <a href="my-requests-maintenance.php">Maintenance</a>
    <a class="on" href="my-requests-bookings.php">Bookings</a>
  </div>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by facility or ID...">
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
        <th>Facility</th>
        <th>Date</th>
        <th>Time</th>
        <th>Total (RM)</th>
        <th>Status</th>
        <th style="width:140px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($bookings)): ?>
        <tr>
          <td colspan="7" style="text-align:center;padding:20px;color:#777;">No facility bookings found.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($bookings as $b): ?>
          <?php
            $timeStr = formatTimeRange($b['start_hour'], $b['end_hour']);
            $today = date('Y-m-d');
            $canCancel = ($b['booking_date'] > $today) && in_array($b['status'], ['pending', 'approved']);
            $viewData = [
              'Booking ID'    => $b['booking_no'],
              'Facility'      => $b['facility_name'],
              'Date'          => formatDate($b['booking_date']),
              'Time'          => $timeStr,
              'Hourly Rate'   => 'RM ' . number_format($b['hourly_rate'], 2),
              'Total Amount'  => 'RM ' . number_format($b['total_amount'], 2),
              'Status'        => ucfirst($b['status']),
              'Remarks'       => $b['remarks'] ?? 'None',
              'Created At'    => formatDate($b['created_at'])
            ];
          ?>
          <tr data-status="<?= e($b['status']) ?>" data-date="<?= e(formatDate($b['booking_date'])) ?>">
            <td><strong><?= e($b['booking_no']) ?></strong></td>
            <td><?= e($b['facility_name']) ?></td>
            <td><?= e(formatDate($b['booking_date'])) ?></td>
            <td><?= e($timeStr) ?></td>
            <td><?= number_format($b['total_amount'], 2) ?></td>
            <td><span class="status <?= strtolower(e($b['status'])) ?>"><?= e(ucfirst($b['status'])) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json='<?= htmlspecialchars(json_encode($viewData), ENT_QUOTES, 'UTF-8') ?>' data-title="Booking Details: <?= e($b['booking_no']) ?>">View</button>
              
              <?php if ($canCancel): ?>
                <form method="POST" action="my-requests-bookings.php" style="display:inline;">
                  <input type="hidden" name="action" value="cancel">
                  <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                  <button type="submit" class="btn" data-confirm="Are you sure you want to cancel this booking?">Cancel</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No bookings match your search or filter.</p>
<p class="note">Cancellation is only allowed prior to the reservation date &bull; Cancel shows a confirmation dialog.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
