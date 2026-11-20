<?php
// book-facility.php - Facility reservation page for residents

require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

requireRole('resident');

$user = currentUser();
$blacklisted = getActiveBlacklist($pdo, $user['res_id'] ? (int)$user['res_id'] : null);
$pageTitle = 'Book a Facility';
$error = '';

// Terms & Conditions gate: residents must agree before using the booking form.
// Agreement is remembered for the current login session.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'accept_terms') {
    if (!empty($_POST['agree_terms'])) {
        $_SESSION['facility_terms_agreed'] = true;
    } else {
        $error = 'You must tick the checkbox to agree to the Terms & Conditions.';
    }
    if (!empty($_SESSION['facility_terms_agreed'])) {
        header('Location: book-facility.php');
        exit;
    }
}
$termsAgreed = !empty($_SESSION['facility_terms_agreed']);

// Load all facilities (unavailable ones are shown greyed out and cannot be selected)
$stmt = $pdo->query("SELECT * FROM facilities ORDER BY name ASC");
$facilities = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'accept_terms' && !$blacklisted) {
    if (!$termsAgreed) {
        $error = 'Please agree to the Terms & Conditions before booking a facility.';
    }
    $facility_id = (int)($_POST['facility_id'] ?? 0);
    $date_raw    = trim($_POST['date'] ?? '');
    $start_hour  = isset($_POST['start_hour']) && $_POST['start_hour'] !== '' ? (int)$_POST['start_hour'] : null;
    $end_hour    = isset($_POST['end_hour']) && $_POST['end_hour'] !== '' ? (int)$_POST['end_hour'] : null;
    $remarks     = trim($_POST['remarks'] ?? '');

    $booking_date = parseInputDate($date_raw);

    // Server-side validation
    if (!$termsAgreed) {
        // error already set above
    } elseif (!$facility_id) {
        $error = 'Please select a facility.';
    } elseif (!$booking_date) {
        $error = 'Please enter a valid date (dd/mm/yyyy or yyyy-mm-dd).';
    } elseif ($booking_date < date('Y-m-d')) {
        $error = 'Booking date cannot be in the past.';
    } elseif ($start_hour === null || $end_hour === null || $start_hour >= $end_hour) {
        $error = 'Please select at least one valid time slot.';
    } else {
        // Find facility info and hourly rate
        $fStmt = $pdo->prepare("SELECT * FROM facilities WHERE id = :id AND status = 'Available' LIMIT 1");
        $fStmt->execute([':id' => $facility_id]);
        $facility = $fStmt->fetch();

        if (!$facility) {
            $error = 'Selected facility is currently unavailable.';
        } elseif ($start_hour < (int)$facility['open_hour'] || $end_hour > (int)$facility['close_hour']) {
            $error = 'Selected time is outside the facility operating hours (' . formatTimeRange((int)$facility['open_hour'], (int)$facility['close_hour']) . ').';
        } else {
            // Check for slot conflicts in database
            $chkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM bookings 
                WHERE facility_id = :fid 
                  AND booking_date = :bdate 
                  AND status IN ('pending', 'approved')
                  AND NOT (:end_h <= start_hour OR :start_h >= end_hour)
            ");
            $chkStmt->execute([
                ':fid'     => $facility_id,
                ':bdate'   => $booking_date,
                ':start_h' => $start_hour,
                ':end_h'   => $end_hour
            ]);
            $hasConflict = (int)$chkStmt->fetchColumn() > 0;

            if ($hasConflict) {
                $error = 'One or more of the selected time slots are already booked. Please choose other slots.';
            } else {
                // Compute verified total on server side
                $hours = $end_hour - $start_hour;
                $total_amount = $hours * (float)$facility['hourly_rate'];

                // Generate unique booking number
                $booking_no = 'BK-' . strtoupper(bin2hex(random_bytes(2))) . rand(10, 99);

                $insStmt = $pdo->prepare("
                    INSERT INTO bookings 
                      (booking_no, resident_id, facility_id, booking_date, start_hour, end_hour, total_amount, remarks, status) 
                    VALUES 
                      (:bno, :rid, :fid, :bdate, :sh, :eh, :tot, :rem, 'pending')
                ");
                $insStmt->execute([
                    ':bno'   => $booking_no,
                    ':rid'   => $user['res_id'],
                    ':fid'   => $facility_id,
                    ':bdate' => $booking_date,
                    ':sh'    => $start_hour,
                    ':eh'    => $end_hour,
                    ':tot'   => $total_amount,
                    ':rem'   => $remarks ?: null
                ]);

                setFlash('success', "Booking {$booking_no} submitted successfully! Status: Pending approval.");
                header('Location: my-requests-bookings.php');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes_header.php';
?>

<h1>Book a Facility</h1>

<?php if ($blacklisted): ?>
<div class="bl-alert">
  <h3>Facility booking is restricted</h3>
  <p>Your account is currently blacklisted from booking facilities.</p>
  <p><b>Reason:</b> <?= e($blacklisted['reason']) ?></p>
  <p><b>Restriction ends:</b> <?= $blacklisted['banned_until'] ? e(formatDate($blacklisted['banned_until'])) : 'Until lifted by management' ?></p>
  <p>Please contact the management office if you believe this is a mistake.</p>
</div>
<?php elseif (!$termsAgreed): ?>
<div class="card" style="text-align:center;padding:40px 20px;">
  <p class="note" style="margin:0;">Please review and agree to the Terms &amp; Conditions to continue booking a facility.</p>
</div>

<div class="modal-backdrop" id="termsModal" role="dialog" aria-modal="true" aria-labelledby="termsTitle">
  <form class="modal-content" method="POST" action="book-facility.php">
    <input type="hidden" name="action" value="accept_terms">
    <div class="modal-header">
      <h3 id="termsTitle">Facility Booking Terms &amp; Conditions</h3>
    </div>
    <div class="modal-body">
      <p>Before booking a facility, please read our Terms &amp; Conditions and confirm that you agree to them.</p>
    </div>

    <label class="terms-check">
      <input type="checkbox" name="agree_terms" id="agreeTerms" value="1">
      <span>I have read and agree to the
        <a href="terms.php" target="_blank" rel="noopener" class="terms-link" style="color:#1d4ed8;font-weight:700;text-decoration:underline;text-underline-offset:3px;cursor:pointer;">Terms &amp; Conditions</a>.</span>
    </label>
    <p class="msg <?= $error ? 'error' : '' ?>"><?= e($error) ?></p>

    <div class="modal-footer">
      <a class="btn" href="resident-home.php">Decline</a>
      <button class="btn btn-dark" id="agreeBtn" type="submit" disabled>Agree &amp; Continue</button>
    </div>
  </form>
</div>
<script>
  (function () {
    var cb = document.getElementById('agreeTerms');
    var btn = document.getElementById('agreeBtn');
    cb.addEventListener('change', function () { btn.disabled = !cb.checked; });
    btn.disabled = !cb.checked;
  })();
</script>
<?php else: ?>
<div class="book">
  <form class="card main" id="bookingForm" method="POST" action="book-facility.php" novalidate>
    <div class="grid2">
      <div class="field">
        <label for="facility">Facility *</label>
        <select class="input" id="facility" name="facility_id" required>
          <option value="">Select Facility...</option>
          <?php foreach ($facilities as $f): ?>
            <?php $unavailable = ($f['status'] !== 'Available'); ?>
            <option value="<?= (int)$f['id'] ?>" data-rate="<?= (float)$f['hourly_rate'] ?>" data-open="<?= (int)$f['open_hour'] ?>" data-close="<?= (int)$f['close_hour'] ?>"
              <?= $unavailable ? 'disabled class="opt-unavailable"' : '' ?>>
              <?= e($f['name']) ?> (RM <?= number_format($f['hourly_rate'], 2) ?>/hr)<?= $unavailable ? ' - Currently unavailable' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label for="date">Date *</label>
        <input class="input" id="date" name="date" type="date" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
      </div>
    </div>

    <p style="margin:12px 0 4px"><b style="font-size:13px">Available time slots</b></p>
    <div class="slots" id="slots"></div>
    <p class="note" style="margin:6px 0">Grey = Booked / Unavailable &bull; Black = Selected</p>

    <!-- Hidden inputs for calculated hours & total -->
    <input type="hidden" name="start_hour" id="start_hour" value="">
    <input type="hidden" name="end_hour" id="end_hour" value="">
    <input type="hidden" name="total_amount" id="total_amount" value="">

    <p class="msg <?= $error ? 'error' : '' ?>" id="msg"><?= e($error) ?></p>

    <div class="field" style="margin-top:14px;">
      <label for="remarks">Remarks (Optional)</label>
      <textarea class="input" id="remarks" name="remarks" placeholder="Any specific equipment or purpose needed..."></textarea>
    </div>

    <button class="btn btn-dark btn-block" id="confirm" type="submit">Confirm &amp; Submit Booking</button>
  </form>

  <div class="card summary">
    <h3 style="margin:0 0 12px">Booking summary</h3>
    <p>Facility: <strong id="sFac">______</strong></p>
    <p>Date: <strong id="sDate">______</strong></p>
    <p>Time: <strong id="sTime">______</strong></p>
    <p style="font-size:15px;margin-top:16px;">
      <b>Total: RM <span id="sTotal">__.__</span></b>
      
    </p>
  </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes_footer.php'; ?>
