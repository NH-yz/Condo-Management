<?php
// api/get-booked-slots.php - Dynamic booking slot availability endpoint

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/config_db.php';
require_once __DIR__ . '/includes_auth.php';

$facility_id = isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : 0;
$date_raw    = isset($_GET['date']) ? trim($_GET['date']) : '';

if (!$facility_id || !$date_raw) {
    echo json_encode(['booked_hours' => []]);
    exit;
}

$date_sql = parseInputDate($date_raw);
if (!$date_sql) {
    echo json_encode(['booked_hours' => []]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT start_hour, end_hour 
        FROM bookings 
        WHERE facility_id = :fid 
          AND booking_date = :bdate 
          AND status IN ('pending', 'approved')
    ");
    $stmt->execute([
        ':fid'   => $facility_id,
        ':bdate' => $date_sql,
    ]);
    $rows = $stmt->fetchAll();

    $booked_hours = [];
    foreach ($rows as $r) {
        for ($h = (int)$r['start_hour']; $h < (int)$r['end_hour']; $h++) {
            $booked_hours[] = $h;
        }
    }

    echo json_encode(['booked_hours' => array_values(array_unique($booked_hours))]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error', 'booked_hours' => []]);
}
