
<?php
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

$db = Database::getInstance();

$serviceId = (int)($_GET['service_id'] ?? 0);
$date      = sanitize($_GET['date'] ?? '');

if (!$serviceId || !$date) {
    jsonResponse(['error' => 'Missing service_id or date'], 400);
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(['error' => 'Invalid date format'], 400);
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj) {
    jsonResponse(['error' => 'Invalid date'], 400);
}

// Check if date is a globally blocked date
$blocked = $db->fetchOne(
    "SELECT id FROM blocked_dates WHERE blocked_date = ?",
    [$date]
);
if ($blocked) {
    jsonResponse(['slots' => [], 'blocked' => true, 'message' => 'This date is unavailable']);
}

// Check if date is a service-specific closed date
$serviceClosed = $db->fetchOne(
    "SELECT id FROM service_closed_dates WHERE service_id = ? AND closed_date = ?",
    [$serviceId, $date]
);
if ($serviceClosed) {
    jsonResponse(['slots' => [], 'blocked' => true, 'message' => 'Service unavailable on this date']);
}

// Get service details (to check if active)
$service = $db->fetchOne(
    "SELECT * FROM services WHERE id = ? AND is_active = 1",
    [$serviceId]
);
if (!$service) {
    jsonResponse(['slots' => [], 'blocked' => true, 'message' => 'Service not found or inactive']);
}

// Get all active time slots for this date
$slots = $db->fetchAll(
    "SELECT ts.*, 
        (SELECT COUNT(*) FROM appointments a 
         WHERE a.time_slot_id = ts.id 
         AND a.appointment_date = ? 
         AND a.status NOT IN ('cancelled')) as booked_count
     FROM time_slots ts
     WHERE ts.slot_date = ? AND ts.is_active = 1
     ORDER BY ts.start_time ASC",
    [$date, $date]
);

$result = [];
foreach ($slots as $slot) {
    $available = $slot['booked_count'] < $slot['max_bookings'];
    $result[] = [
        'id'           => $slot['id'],
        'start_time'   => $slot['start_time'],
        'end_time'     => $slot['end_time'],
        'label'        => date('g:i A', strtotime($slot['start_time'])) . ' – ' . date('g:i A', strtotime($slot['end_time'])),
        'available'    => $available,
        'max_bookings' => $slot['max_bookings'],
        'booked'       => (int)$slot['booked_count'],
        'spots_left'   => max(0, $slot['max_bookings'] - $slot['booked_count']),
    ];
}

jsonResponse([
    'slots'   => $result,
    'date'    => $date,
    'service' => [
        'id'       => $service['id'],
        'name'     => $service['name'],
        'duration' => $service['duration'],
        'price'    => $service['price'],
    ],
]);
