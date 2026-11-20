<?php
// resident-home.php - Resident Portal Home Page

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$pageTitle = 'Resident Home';

// Fetch latest updates (combined maintenance requests and bookings for this resident)
try {
    // Recent maintenance requests
    $stmtM = $pdo->prepare("
        SELECT 'Maintenance' AS item_type, title, preferred_date AS event_date, status, created_at 
        FROM maintenance_requests 
        WHERE resident_id = :rid 
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmtM->execute([':rid' => $user['res_id']]);
    $maintenanceList = $stmtM->fetchAll();

    // Recent bookings
    $stmtB = $pdo->prepare("
        SELECT 'Booking' AS item_type, f.name AS title, b.booking_date AS event_date, b.status, b.created_at 
        FROM bookings b
        JOIN facilities f ON b.facility_id = f.id
        WHERE b.resident_id = :rid 
        ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmtB->execute([':rid' => $user['res_id']]);
    $bookingsList = $stmtB->fetchAll();

    // Merge and sort by date/created_at
    $allUpdates = array_merge($maintenanceList, $bookingsList);
    usort($allUpdates, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $allUpdates = array_slice($allUpdates, 0, 6);

} catch (PDOException $e) {
    $allUpdates = [];
}

require_once __DIR__ . '/includes_header.php';
?>

<h1>Welcome, <?= e($user['name']) ?> &mdash; Unit <?= e($user['unit']) ?> (<?= e($user['tower']) ?>)</h1>

<div class="boxes">
  <div class="box">
    <h2>Report a Defect</h2>
    <p style="color:#666;font-size:13px;margin-bottom:18px;">Submit maintenance requests for repairs in your unit or common areas.</p>
    <a class="btn btn-dark btn-lg" href="report-defect.php">Report Defect</a>
  </div>
  <div class="box">
    <h2>Book a Facility</h2>
    <p style="color:#666;font-size:13px;margin-bottom:18px;">Reserve Function Hall, Badminton Court, BBQ Pit and other amenities.</p>
    <a class="btn btn-dark btn-lg" href="book-facility.php">Book Facility</a>
  </div>
  <div class="box">
    <h2>My Requests</h2>
    <p style="color:#666;font-size:13px;margin-bottom:18px;">Track the progress of your defect reports and manage facility bookings.</p>
    <a class="btn btn-dark btn-lg" href="my-requests-maintenance.php">View Requests</a>
  </div>
</div>

<p class="sub">Latest updates</p>
<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th style="width:18%;">Type</th>
        <th>Title / Facility</th>
        <th style="width:20%;">Date</th>
        <th style="width:18%;">Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($allUpdates)): ?>
        <tr>
          <td colspan="4" style="text-align:center;padding:20px;color:#777;">No recent requests found. Use the buttons above to get started!</td>
        </tr>
      <?php else: ?>
        <?php foreach ($allUpdates as $item): ?>
          <tr>
            <td><strong><?= e($item['item_type']) ?></strong></td>
            <td><?= e($item['title']) ?></td>
            <td><?= e(formatDate($item['event_date'])) ?></td>
            <td><span class="status <?= strtolower(e($item['status'])) ?>"><?= e(ucfirst($item['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
