<?php
session_start();
if (!isset($_SESSION['busker_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Dashboard</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .tab-content {
            padding: 20px;
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <a href="../includes/logout.php" class="btn btn-danger logout-btn">Logout</a>
        <h1>Busker Dashboard</h1>
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
                <a class="nav-link" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Profile Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab">Document Management</a>
            </li>
        </ul>
        <div class="tab-content" id="dashboardTabsContent">
            <div class="tab-pane fade show active" id="upcoming" role="tabpanel">
                <h2>Upcoming Bookings</h2>
                <!-- Content for upcoming bookings will go here -->
            </div>
            <div class="tab-pane fade" id="requests" role="tabpanel">
                <h2>Booking Requests</h2>
                <!-- Content for booking requests will go here -->
            </div>
            <div class="tab-pane fade" id="past" role="tabpanel">
                <h2>Past Events</h2>
                <!-- Content for past events will go here -->
            </div>
            <div class="tab-pane fade" id="profile" role="tabpanel">
                <h2>Profile Management</h2>
                <!-- Content for profile management will go here -->
            </div>
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <h2>Document Management</h2>
                <!-- Content for document management will go here -->
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html> 