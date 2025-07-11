<?php
require_once '../includes/config.php'; // Include the database configuration

// Debug session information
error_log("Session contents: " . print_r($_SESSION, true));
error_log("POST contents: " . print_r($_POST, true));

// Display and clear any session success/error messages
$success_message = '';
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

$error_message = '';
if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (!isset($_SESSION['busker_id'])) {
    error_log("No busker_id in session. Redirecting to index.php");
    header('Location: index.php');
    exit();
}

$busker_id = $_SESSION['busker_id'];
error_log("Current busker_id: " . $busker_id);
$busker_data = null;
$busker_genres = [];
$busker_equipment = [];
$all_genres = [];
$success = '';
$error = '';

// Handle booking request actions (Accept/Reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['confirm_request']) || isset($_POST['reject_request']))) {
    $inquiry_id = (int)$_POST['inquiry_id'];
    $new_status = isset($_POST['confirm_request']) ? 'confirmed' : 'rejected';

    try {
        $conn->beginTransaction();

        // Update inquiry status
        $stmt = $conn->prepare("UPDATE inquiry SET inquiry_status = ? WHERE inquiry_id = ?");
        $stmt->execute([$new_status, $inquiry_id]);

        if ($new_status === 'confirmed') {
            $success = 'Booking request confirmed successfully!';
        } else {
            $success = 'Booking request rejected.';
        }

        $conn->commit();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=requests');
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error handling booking request: " . $e->getMessage());
        $error = 'Error handling request: ' . $e->getMessage();
    }
}

// Handle profile update form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        $conn->beginTransaction();

        // Update busker personal details
        $stmt = $conn->prepare("
            UPDATE busker
            SET band_name = ?, name = ?, contact_number = ?, address = ?, birthday = ?, has_equipment = ?
            WHERE busker_id = ?
        ");
        $stmt->execute([
            $_POST['band_name'],
            $_POST['name'],
            $_POST['contact_number'],
            $_POST['address'],
            $_POST['birthday'],
            isset($_POST['has_equipment']) ? 1 : 0,
            $busker_id
        ]);

        // Update busker genres
        $stmt = $conn->prepare("DELETE FROM busker_genre WHERE busker_id = ?");
        $stmt->execute([$busker_id]);

        if (!empty($_POST['genres'])) {
            $insert_genre_stmt = $conn->prepare("INSERT INTO busker_genre (busker_id, genre_id) VALUES (?, ?)");
            foreach ($_POST['genres'] as $genre_id) {
                $insert_genre_stmt->execute([$busker_id, $genre_id]);
            }
        }

        $conn->commit();
        $success = 'Profile updated successfully!';
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=profile');
        exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        error_log("Error updating busker profile: " . $e->getMessage());
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}

// Handle add equipment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    try {
        // Log the incoming data
        error_log("Attempting to add equipment with data: " . print_r($_POST, true));
        
        // Validate required fields
        if (empty($_POST['equipment_name'])) {
            throw new Exception("Equipment name is required");
        }

        // Prepare the SQL statement
        $stmt = $conn->prepare("
            INSERT INTO busker_equipment (busker_id, equipment_name, quantity, eq_condition)
            VALUES (?, ?, ?, ?)
        ");

        // Log the values being inserted
        $values = [
            $busker_id,
            $_POST['equipment_name'],
            $_POST['quantity'] ?? '',
            $_POST['eq_condition'] ?? ''
        ];
        error_log("Inserting equipment with values: " . print_r($values, true));

        // Execute the statement
        $result = $stmt->execute($values);
        
        if ($result) {
            $_SESSION['success'] = 'Equipment added successfully!';
            error_log("Equipment added successfully for busker_id: " . $busker_id);
        } else {
            throw new Exception("Failed to add equipment");
        }

        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=profile');
        exit();
    } catch (Exception $e) {
        error_log("Error adding equipment: " . $e->getMessage());
        $_SESSION['error'] = 'Error adding equipment: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=profile');
        exit();
    }
}

// Handle delete equipment request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM busker_equipment WHERE equipment_id = ? AND busker_id = ?");
        $stmt->execute([$_POST['equipment_id'], $busker_id]);
        $success = 'Equipment deleted successfully!';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=profile');
        exit();
    } catch (PDOException $e) {
        error_log("Error deleting equipment: " . $e->getMessage());
        $error = 'Error deleting equipment: ' . $e->getMessage();
    }
}

// Fetch busker data
try {
    $stmt = $conn->prepare("SELECT band_name, name, contact_number, address, birthday, has_equipment, email FROM busker WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_data = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch busker's genres
    $stmt = $conn->prepare("SELECT g.genre_id, g.name FROM busker_genre bg JOIN genre g ON bg.genre_id = g.genre_id WHERE bg.busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $busker_genre_ids = array_column($busker_genres, 'genre_id');

    // Fetch busker's equipment
    $stmt = $conn->prepare("SELECT equipment_id, equipment_name, quantity, eq_condition FROM busker_equipment WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all genres for selection
    $stmt = $conn->query("SELECT genre_id, name FROM genre ORDER BY name");
    $all_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching busker profile data: " . $e->getMessage());
    $error = 'Failed to load profile data.';
}

// Get busker's booking requests (inquiry_status = 'approved' by admin)
try {
    $stmt = $conn->prepare("SELECT h.order_id, h.inquiry_id, h.payment_status, h.performance_time,
                           i.budget, i.inquiry_status,
                           e.event_name, e.event_type, e.event_date, e.venue_equipment, e.description,
                           c.name as client_name, c.phone as client_contact, c.email as client_email,
                           ts.start_time, ts.end_time
        FROM hire h
        JOIN inquiry i ON h.inquiry_id = i.inquiry_id
        JOIN event_table e ON i.event_id = e.event_id
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        WHERE h.busker_id = ? AND i.inquiry_status = 'approved'
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$_SESSION['busker_id']]);
    $booking_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Error fetching booking requests: ' . $e->getMessage();
}

// Get busker's upcoming events (inquiry_status = 'accepted' and event_date >= CURDATE())
try {
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
            c.name as client_name,
            c.phone as client_contact,
            c.email as client_email,
            GROUP_CONCAT(DISTINCT sd.docs_id) as doc_ids,
            GROUP_CONCAT(DISTINCT sd.doc_link) as doc_links
        FROM hire h
        JOIN inquiry i ON h.inquiry_id = i.inquiry_id
        JOIN event_table e ON i.event_id = e.event_id
        LEFT JOIN location l ON e.location_id = l.location_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN inquiry_document id ON i.inquiry_id = id.inquiry_id
        LEFT JOIN supporting_document sd ON id.docs_id = sd.docs_id
        WHERE h.busker_id = ? AND (i.inquiry_status = 'accepted' OR i.inquiry_status = 'confirmed') AND e.event_date >= CURDATE()
        GROUP BY h.order_id
        ORDER BY e.event_date ASC
    ");
    $stmt->execute([$_SESSION['busker_id']]);
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching upcoming events: ' . $e->getMessage();
}

// Get busker's past events (inquiry_status = 'accepted' and event_date < CURDATE())
// Also includes events that were completed (e.g., inquiry_status = 'completed')
try {
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
            c.name as client_name,
            c.phone as client_contact,
            c.email as client_email,
            GROUP_CONCAT(DISTINCT sd.docs_id) as doc_ids,
            GROUP_CONCAT(DISTINCT sd.doc_link) as doc_links
        FROM hire h
        JOIN inquiry i ON h.inquiry_id = i.inquiry_id
        JOIN event_table e ON i.event_id = e.event_id
        LEFT JOIN location l ON e.location_id = l.location_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN inquiry_document id ON i.inquiry_id = id.inquiry_id
        LEFT JOIN supporting_document sd ON id.docs_id = sd.docs_id
        WHERE h.busker_id = ? AND (i.inquiry_status = 'accepted' AND e.event_date < CURDATE() OR i.inquiry_status = 'completed')
        GROUP BY h.order_id
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$_SESSION['busker_id']]);
    $past_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching past events: ' . $e->getMessage();
}

// Get busker's hired events for document management (all except rejected/deleted)
// Using a different variable name to avoid conflict with `events` for document management table
try {
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
            c.name as client_name,
            c.phone as client_contact,
            c.email as client_email,
            GROUP_CONCAT(DISTINCT sd.docs_id) as doc_ids,
            GROUP_CONCAT(DISTINCT sd.doc_link) as doc_links
        FROM hire h
        JOIN inquiry i ON h.inquiry_id = i.inquiry_id
        JOIN event_table e ON i.event_id = e.event_id
        LEFT JOIN location l ON e.location_id = l.location_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN inquiry_document id ON i.inquiry_id = id.inquiry_id
        LEFT JOIN supporting_document sd ON id.docs_id = sd.docs_id
        WHERE h.busker_id = ? AND i.inquiry_status NOT IN ('rejected', 'deleted by client')
        GROUP BY h.order_id
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$_SESSION['busker_id']]);
    $document_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching events for document management: ' . $e->getMessage();
}

// Add a function to map inquiry_status to busker status
function getBuskerStatus($status) {
    switch (strtolower($status)) {
        case 'deleted':
        case 'deleted by client':
            return 'Not Visible';
        case 'pending':
            return 'Not Visible';
        case 'approved':
            return 'Pending';
        case 'approve by admin':
            return 'Pending';
        case 'rejected':
        case 'rejected by busker':
            return 'Rejected by Busker';
        case 'rejected by admin':
            return 'Not Visible';
        case 'confirmed':
            return 'Confirmed';
        case 'canceled':
        case 'cancelled':
            return 'Not Visible';
        case 'completed':
            return 'Completed';
        default:
            return ucfirst($status);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <style>
        .tab-content {
            padding: 20px;
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 .25rem .25rem;
        }
        .logout-btn {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
        .container {
            padding-top: 60px;
        }
        .profile-form .form-group {
            margin-bottom: 1rem;
        }
        .profile-form label {
            font-weight: bold;
        }
        .profile-form .form-control,
        .profile-form .form-check-input {
            border-radius: .25rem;
        }
        .profile-form .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
        }
        .profile-form .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .genre-checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid #ced4da;
            padding: 10px;
            border-radius: .25rem;
            max-height: 150px;
            overflow-y: auto;
        }
        .genre-checkbox-group .form-check-label {
            margin-bottom: 0;
        }
        .equipment-list-section ul {
            list-style: none;
            padding: 0;
        }
        .equipment-list-section li {
            background-color: #e9ecef;
            border: 1px solid #dee2e6;
            padding: 8px 15px;
            margin-bottom: 8px;
            border-radius: .25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .equipment-list-section .remove-equipment-btn {
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0 5px;
        }
        .equipment-list-section .remove-equipment-btn:hover {
            color: #c82333;
        }
        .alert-fixed {
            position: fixed;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2000;
            min-width: 300px;
            text-align: center;
        }
        .add-equipment-form {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        /* Styles for new tables */
        .events-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.07);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }

        .events-table th,
        .events-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }

        .events-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
            white-space: nowrap;
            min-width: fit-content;
        }

        .events-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.95em;
            font-weight: 500;
        }

        .status-badge.not-visible { background: #e0e0e0; color: #888; }
        .status-badge.pending { background: #fff3cd; color: #856404; }
        .status-badge.approve-by-admin { background: #b3e5fc; color: #0277bd; }
        .status-badge.rejected-by-busker { background: #f8d7da; color: #721c24; }
        .status-badge.confirmed { background: #d4edda; color: #155724; }
        .status-badge.canceled { background: #f5c6cb; color: #721c24; }
        .status-badge.completed { background: #cce5ff; color: #004085; }
        .status-badge.busker.selected { background: #007bff; color: #fff; } /* Specific for busker selected status */

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.875rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }

        .document-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .document-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .document-item i {
            color: #007bff;
        }

        /* Improved View Modal Styles */
        #viewModal.modal {
            display: none;
            position: fixed;
            z-index: 3000;
            left: 0;
            top: 0;
            width: 100vw;
            height: 100vh;
            overflow: auto;
            background: rgba(0,0,0,0.4);
            align-items: center;
            justify-content: center;
        }
        #viewModal .modal-content {
            background: #fff;
            margin: 40px auto;
            padding: 32px 32px 24px 32px;
            border-radius: 12px;
            max-width: 600px;
            width: 95%;
            box-shadow: 0 8px 32px rgba(0,0,0,0.18);
            position: relative;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        #viewModal .close-button {
            position: absolute;
            top: 18px;
            right: 24px;
            font-size: 2rem;
            color: #888;
            cursor: pointer;
            transition: color 0.2s;
            z-index: 10;
        }
        #viewModal .close-button:hover {
            color: #e74c3c;
        }
        #viewModal h2 {
            margin-top: 0;
            margin-bottom: 18px;
            font-size: 2rem;
            color: #2c3e50;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 8px;
        }
        #viewModal .detail-section {
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        #viewModal .detail-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        #viewModal h3 {
            font-size: 1.15rem;
            color: #2980b9;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #viewModal p {
            margin: 4px 0 8px 0;
            color: #222;
            font-size: 1rem;
        }
        #viewModal .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 0.95em;
            font-weight: 500;
            background: #fff3cd;
            color: #856404;
            margin-left: 6px;
        }
        #viewModal .document-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        #viewModal .document-item a {
            color: #007bff;
            text-decoration: none;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        #viewModal .document-item a:hover {
            text-decoration: underline;
        }
        @media (max-width: 700px) {
            #viewModal .modal-content {
                padding: 18px 6px 12px 6px;
                max-width: 98vw;
            }
            #viewModal h2 {
                font-size: 1.3rem;
            }
            #viewModal h3 {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h1 class="my-4">Busker Dashboard</h1>
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="upcoming-tab" data-toggle="tab" href="#upcoming" role="tab">Upcoming Bookings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="requests-tab" data-toggle="tab" href="#requests" role="tab">Booking Requests</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="past-tab" data-toggle="tab" href="#past" role="tab">Past Events</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab">Document Management</a>
            </li>
        </ul>
        <div class="tab-content" id="dashboardTabsContent">
            <!-- Upcoming Bookings Tab Content -->
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                <h2>Upcoming Bookings</h2>
                <?php if (isset($error_message)): ?><!-- <div class="alert alert-danger"><?php echo $error_message; ?></div> --> <?php endif; ?>

                <div class="table-responsive">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Client</th>
                                <th>Payment Status</th>
                                <th>Inquiry Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($upcoming_events)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No upcoming bookings found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($upcoming_events as $event): ?>
                                    <tr onclick="viewEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" style="cursor:pointer;">
                                        <td><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_type'] ?? ''); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($event['event_date'] ?? '')); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($event['start_time']) && !empty($event['end_time'])) {
                                                $tz = new DateTimeZone('Asia/Manila');
                                                $start = new DateTime($event['start_time'], $tz);
                                                $end = new DateTime($event['end_time'], $tz);
                                                echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['client_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $event['payment_status'])); ?>">
                                                <?php echo ucfirst($event['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', getBuskerStatus($event['inquiry_status']))); ?>">
                                                <?php echo getBuskerStatus($event['inquiry_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Booking Requests Tab Content -->
            <div class="tab-pane fade" id="requests" role="tabpanel">
                <h2>Booking Requests</h2>
                <?php if (isset($error_message)): ?><!-- <div class="alert alert-danger"><?php echo $error_message; ?></div> --> <?php endif; ?>

                <div class="table-responsive">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Client</th>
                                <th>Budget</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($booking_requests)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No approved booking requests found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($booking_requests as $request): ?>
                                    <tr onclick="viewEvent(<?php echo htmlspecialchars(json_encode($request)); ?>)" style="cursor:pointer;">
                                        <td><?php echo htmlspecialchars($request['event_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($request['event_type'] ?? ''); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($request['event_date'] ?? '')); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($request['start_time']) && !empty($request['end_time'])) {
                                                $tz = new DateTimeZone('Asia/Manila');
                                                $start = new DateTime($request['start_time'], $tz);
                                                $end = new DateTime($request['end_time'], $tz);
                                                echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['client_name']); ?></td>
                                        <td>₱<?php echo number_format($request['budget']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', getBuskerStatus($request['inquiry_status']))); ?>">
                                                <?php echo getBuskerStatus($request['inquiry_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <form action="" method="POST" class="d-inline-block me-2">
                                                <input type="hidden" name="inquiry_id" value="<?php echo $request['inquiry_id']; ?>">
                                                <button type="submit" name="confirm_request" class="btn btn-success btn-sm">Confirm</button>
                                            </form>
                                            <form action="" method="POST" class="d-inline-block">
                                                <input type="hidden" name="inquiry_id" value="<?php echo $request['inquiry_id']; ?>">
                                                <button type="submit" name="reject_request" class="btn btn-danger btn-sm">Reject</button>
                                            </form>
                                            <button class="btn btn-info btn-sm mt-1" onclick="viewEvent(<?php echo htmlspecialchars(json_encode($request)); ?>)">View</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Past Events Tab Content -->
            <div class="tab-pane fade" id="past" role="tabpanel">
                <h2>Past Events</h2>
                <?php if (isset($error_message)): ?><!-- <div class="alert alert-danger"><?php echo $error_message; ?></div> --> <?php endif; ?>

                <div class="table-responsive">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Type</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Client</th>
                                <th>Payment Status</th>
                                <th>Inquiry Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($past_events)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No past events found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($past_events as $event): ?>
                                    <tr onclick="viewEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" style="cursor:pointer;">
                                        <td><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_type'] ?? ''); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($event['event_date'] ?? '')); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($event['start_time']) && !empty($event['end_time'])) {
                                                $tz = new DateTimeZone('Asia/Manila');
                                                $start = new DateTime($event['start_time'], $tz);
                                                $end = new DateTime($event['end_time'], $tz);
                                                echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($event['client_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $event['payment_status'])); ?>">
                                                <?php echo ucfirst($event['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', getBuskerStatus($event['inquiry_status']))); ?>">
                                                <?php echo getBuskerStatus($event['inquiry_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tab-pane fade" id="documents" role="tabpanel">
                <h2>Document Management</h2>
                <?php if (isset($error)): ?><!-- <div class="alert alert-danger"><?php echo $error; ?></div> --> <?php endif; ?>

                <div class="table-responsive">
                    <table class="events-table">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Event Type</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Payment Status</th>
                                <th>Documents</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($document_events)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No events found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($document_events as $event): ?>
                                    <tr onclick="viewEvent(<?php echo htmlspecialchars(json_encode($event)); ?>)" style="cursor:pointer;">
                                        <td><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($event['event_type'] ?? ''); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($event['event_date'] ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($event['client_name']); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $event['payment_status'])); ?>">
                                                <?php echo ucfirst($event['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="document-list">
                                                <?php if ($event['doc_links']): ?>
                                                    <?php
                                                    $docLinks = explode(',', $event['doc_links']);
                                                    foreach ($docLinks as $index => $link): ?>
                                                        <div class="document-item">
                                                            <a href="/tbcph/<?php echo htmlspecialchars($link); ?>" target="_blank">
                                                                <i class="fas fa-file-pdf"></i>
                                                                Document <?php echo $index + 1; ?>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p>No documents uploaded</p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            if (!empty($event['start_time']) && !empty($event['end_time'])) {
                                                $tz = new DateTimeZone('Asia/Manila');
                                                $start = new DateTime($event['start_time'], $tz);
                                                $end = new DateTime($event['end_time'], $tz);
                                                echo $start->format('g:i A') . ' - ' . $end->format('g:i A');
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Equipment Modal -->
    <div class="modal fade" id="addEquipmentModal" tabindex="-1" role="dialog" aria-labelledby="addEquipmentModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEquipmentModalLabel">Add New Equipment</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_equipment" value="1">
                        <div class="form-group">
                            <label for="equipment_name">Equipment Name</label>
                            <input type="text" class="form-control" id="equipment_name" name="equipment_name" required>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity</label>
                            <input type="text" class="form-control" id="quantity" name="quantity">
                        </div>
                        <div class="form-group">
                            <label for="eq_condition">Condition</label>
                            <select class="form-control" id="eq_condition" name="eq_condition">
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Fair">Fair</option>
                                <option value="Poor">Poor</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Add Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeViewModal()">&times;</span>
            <h2>Event Details</h2>
            <div id="viewContent"></div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                $(`#dashboardTabs a[href="#${tab}"]`).tab('show');
            } // else do nothing, Upcoming Bookings is default
            
            // Fade out alerts after a few seconds
            setTimeout(function() {
                $('.alert-fixed').fadeOut('slow');
            }, 5000);

            // Clear modal form when it's closed
            $('#addEquipmentModal').on('hidden.bs.modal', function () {
                $(this).find('form')[0].reset();
            });
        });

        function viewEvent(event) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewContent');
            content.innerHTML = `
                <div class="detail-section">
                    <h3><i class="fas fa-calendar-alt"></i> Event Information</h3>
                    <p><strong>Event Name:</strong> ${event.event_name || 'N/A'}</p>
                    <p><strong>Event Type:</strong> ${event.event_type || 'N/A'}</p>
                    <p><strong>Event Date:</strong> ${event.event_date ? new Date(event.event_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) : 'N/A'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-map-marker-alt"></i> Location</h3>
                    <p><strong>Address:</strong> ${event.address || 'Not specified'}</p>
                    <p><strong>City:</strong> ${event.city || 'Not specified'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-user"></i> Client Information</h3>
                    <p><strong>Name:</strong> ${event.client_name || 'N/A'}</p>
                    <p><strong>Contact:</strong> ${event.client_contact || 'Not provided'}</p>
                    <p><strong>Email:</strong> ${event.client_email || 'N/A'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-info-circle"></i> Event Details</h3>
                    <p><strong>Budget:</strong> ₱${event.budget ? Number(event.budget).toLocaleString() : 'N/A'}</p>
                    <p><strong>Payment Status:</strong> <span class="status-badge ${event.payment_status ? event.payment_status.toLowerCase() : ''}">${event.payment_status || 'N/A'}</span></p>
                    <p><strong>Time:</strong> ${(event.start_time && event.end_time) ?
                        (new Date('1970-01-01T' + event.start_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) + ' - ' +
                         new Date('1970-01-01T' + event.end_time).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true })) : 'Not set'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-tools"></i> Venue Equipment</h3>
                    <p>${event.venue_equipment || 'None specified'}</p>
                </div>
                <div class="detail-section">
                    <h3><i class="fas fa-file-alt"></i> Supporting Documents</h3>
                    <div class="document-list">
                        ${event.doc_links ? event.doc_links.split(',').map((link, index) => `
                            <div class="document-item">
                                <a href="/tbcph/${link}" target="_blank">
                                    <i class="fas fa-file-pdf"></i>
                                    Document ${index + 1}
                                </a>
                            </div>
                        `).join('') : '<p>No documents uploaded</p>'}
                    </div>
                </div>
            `;
            modal.style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html> 