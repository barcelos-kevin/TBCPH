<?php
session_start();
require_once __DIR__ . '/includes/config.php';

// Set this manually for testing if not logged in
// $_SESSION['busker_id'] = 1; // <-- Set your test busker_id here

$stmt = $conn->prepare("
    SELECT 
        h.order_id,
        h.inquiry_id,
        h.payment_status,
        h.performance_time,
        i.budget,
        i.inquiry_status,
        e.event_name,
        e.event_type,
        e.event_date,
        e.venue_equipment,
        e.description,
        l.address,
        l.city,
        ts.start_time,
        ts.end_time,
        c.name AS client_name,
        c.phone AS client_contact,
        c.email AS client_email
    FROM hire h
    JOIN inquiry i ON h.inquiry_id = i.inquiry_id
    JOIN event_table e ON i.event_id = e.event_id
    LEFT JOIN location l ON e.location_id = l.location_id
    LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
    JOIN client c ON i.client_id = c.client_id
    WHERE h.busker_id = ? 
      AND (i.inquiry_status = 'accepted' OR i.inquiry_status = 'confirmed')
      AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC
");
$stmt->execute([$_SESSION['busker_id']]);
$upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

function displayEventsTable($events) {
    if (empty($events)) {
        echo '<p>No upcoming bookings found.</p>';
        return;
    }
    echo '<table border="1" cellpadding="5" style="border-collapse:collapse;">';
    echo '<tr>';
    foreach (array_keys($events[0]) as $col) {
        echo '<th>' . htmlspecialchars($col) . '</th>';
    }
    echo '</tr>';
    foreach ($events as $event) {
        echo '<tr>';
        foreach ($event as $val) {
            echo '<td>' . htmlspecialchars($val ?? '') . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Upcoming Bookings</title>
</head>
<body>
    <h1>Upcoming Bookings (Test)</h1>
    <?php displayEventsTable($upcoming_events); ?>
</body>
</html>