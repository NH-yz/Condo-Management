<?php
// admin-facilities.php - Facility Management (CRUD: Read, Toggle availability & Delete)
// Facilities are the bookable amenities residents see on book-facility.php.

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('admin');

$pageTitle = 'Admin - Facilities';

// Handle Toggle availability / Delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $fac_id = (int)($_POST['facility_id'] ?? 0);
    $action = $_POST['action'];

    if ($fac_id > 0) {
        $fStmt = $pdo->prepare("SELECT * FROM facilities WHERE id = :id LIMIT 1");
        $fStmt->execute([':id' => $fac_id]);
        $fac = $fStmt->fetch();

        if (!$fac) {
            setFlash('error', 'Facility not found.');
        } elseif ($action === 'toggle') {
            $newStatus = ($fac['status'] === 'Available') ? 'Maintenance' : 'Available';
            $upd = $pdo->prepare("UPDATE facilities SET status = :st WHERE id = :id");
            $upd->execute([':st' => $newStatus, ':id' => $fac_id]);

            if ($newStatus === 'Maintenance') {
                setFlash('success', "{$fac['name']} is now under maintenance and cannot be booked by residents.");
            } else {
                setFlash('success', "{$fac['name']} is now available for booking again.");
            }
        } elseif ($action === 'delete') {
            // bookings.facility_id uses ON DELETE RESTRICT, so check first for a friendly message
            $cStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE facility_id = :id");
            $cStmt->execute([':id' => $fac_id]);
            $bookingCount = (int)$cStmt->fetchColumn();

            if ($bookingCount > 0) {
                setFlash('error', "Cannot delete {$fac['name']}: it has {$bookingCount} booking record(s). Set it to Maintenance instead to stop new bookings.");
            } else {
                $del = $pdo->prepare("DELETE FROM facilities WHERE id = :id");
                $del->execute([':id' => $fac_id]);
                setFlash('success', "Facility {$fac['name']} deleted.");
            }
        }
    }
    header('Location: admin-facilities.php');
    exit;
}

// Fetch facilities with booking counts
$stmt = $pdo->query("
    SELECT f.*,
           COUNT(CASE WHEN b.status IN ('pending', 'approved') AND b.booking_date >= CURDATE() THEN 1 END) AS upcoming_bookings,
           COUNT(b.id) AS total_bookings
    FROM facilities f
    LEFT JOIN bookings b ON b.facility_id = f.id
    GROUP BY f.id
    ORDER BY f.name ASC
");
$facilities = $stmt->fetchAll();

// Summary figures
$totalFac       = count($facilities);
$availableFac   = count(array_filter($facilities, fn($f) => $f['status'] === 'Available'));
$maintenanceFac = $totalFac - $availableFac;
$upcomingAll    = array_sum(array_map(fn($f) => (int)$f['upcoming_bookings'], $facilities));

require_once __DIR__ . '/includes_header.php';
?>

<div class="page-head">
  <h1>Facilities</h1>
  <a class="btn btn-dark btn-lg" href="admin-facilities-add.php">+ Add New Facility</a>
</div>

<div class="stats">
  <div class="stat">
    <span>Total Facilities</span>
    <b><?= $totalFac ?></b>
  </div>
  <div class="stat">
    <span>Available</span>
    <b><?= $availableFac ?></b>
  </div>
  <div class="stat">
    <span>Under Maintenance</span>
    <b><?= $maintenanceFac ?></b>
  </div>
  <div class="stat">
    <span>Upcoming Bookings</span>
    <b><?= $upcomingAll ?></b>
  </div>
</div>

<div class="toolbar">
  <input class="input search" id="search" placeholder="🔍 Search by facility name or description...">
  <div class="chips">
    <button class="chip on" data-filter="all">All</button>
    <button class="chip" data-filter="available">Available</button>
    <button class="chip" data-filter="maintenance">Maintenance</button>
  </div>
</div>

<div class="table-wrap">
  <table id="tbl">
    <thead>
      <tr>
        <th>ID</th>
        <th>Facility</th>
        <th>Description</th>
        <th>Rate (per hour)</th>
        <th>Operating Hours</th>
        <th>Upcoming Bookings</th>
        <th>Status</th>
        <th style="width:260px;">Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($facilities)): ?>
        <tr>
          <td colspan="8" style="text-align:center;padding:20px;color:#777;">No facilities registered yet.</td>
        </tr>
      <?php else: ?>
        <?php foreach ($facilities as $f): ?>
          <?php
            $isAvailable = ($f['status'] === 'Available');
            $state       = $isAvailable ? 'available' : 'maintenance';
            $rateLabel   = ((float)$f['hourly_rate'] > 0) ? 'RM ' . number_format((float)$f['hourly_rate'], 2) : 'Free';
            $hoursLabel  = formatTimeRange((int)$f['open_hour'], (int)$f['close_hour']);
            $code        = 'F' . str_pad((string)$f['id'], 3, '0', STR_PAD_LEFT);

            $viewData = [
                'Facility ID'       => $code,
                'Name'              => $f['name'],
                'Description'       => $f['description'] ?: '-',
                'Rate'              => $rateLabel . ((float)$f['hourly_rate'] > 0 ? ' / hour' : ''),
                'Operating Hours'   => $hoursLabel,
                'Status'            => $f['status'],
                'Upcoming Bookings' => (string)(int)$f['upcoming_bookings'],
                'Total Bookings'    => (string)(int)$f['total_bookings'],
            ];
            $viewData = array_map(fn($v) => e((string)$v), $viewData); // modal renders values as HTML
          ?>
          <tr data-status="<?= e($state) ?>">
            <td><strong><?= e($code) ?></strong></td>
            <td><strong><?= e($f['name']) ?></strong></td>
            <td class="fac-desc" title="<?= e($f['description'] ?? '') ?>"><?= e($f['description'] ?: '-') ?></td>
            <td><?= e($rateLabel) ?></td>
            <td><?= e($hoursLabel) ?></td>
            <td><strong style="color:<?= $f['upcoming_bookings'] > 0 ? '#b45309' : '#155724' ?>;"><?= (int)$f['upcoming_bookings'] ?></strong></td>
            <td><span class="status <?= e($state) ?>"><?= e($f['status']) ?></span></td>
            <td class="act">
              <button type="button" class="btn" data-view-json="<?= e(json_encode($viewData, JSON_UNESCAPED_UNICODE)) ?>" data-title="Facility Details">View</button>
              <a class="btn" href="admin-facilities-add.php?id=<?= (int)$f['id'] ?>">Edit</a>

              <form method="POST" action="admin-facilities.php" style="display:inline;">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="facility_id" value="<?= (int)$f['id'] ?>">
                <?php if ($isAvailable): ?>
                  <button type="submit" class="btn" data-confirm="Set <?= e($f['name']) ?> to Maintenance? Residents will not be able to make new bookings.<?= $f['upcoming_bookings'] > 0 ? ' Note: ' . (int)$f['upcoming_bookings'] . ' existing upcoming booking(s) are NOT cancelled automatically.' : '' ?>">Maintenance</button>
                <?php else: ?>
                  <button type="submit" class="btn btn-dark" data-confirm="Make <?= e($f['name']) ?> available for booking again?">Reopen</button>
                <?php endif; ?>
              </form>

              <form method="POST" action="admin-facilities.php" style="display:inline;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="facility_id" value="<?= (int)$f['id'] ?>">
                <button type="submit" class="btn btn-danger" data-confirm="Delete facility <?= e($f['name']) ?>? This cannot be undone.">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="note" id="noData" hidden>No facilities found matching your filters.</p>
<p class="note">Facilities set to Maintenance are greyed out on the resident booking page. A facility with booking history cannot be deleted &ndash; set it to Maintenance instead.</p>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
