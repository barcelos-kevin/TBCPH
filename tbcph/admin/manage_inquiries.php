<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id']) && isset($_POST['status'])) {
    try {
        $stmt = $conn->prepare("UPDATE inquiry SET inquiry_status = ? WHERE inquiry_id = ?");
        $stmt->execute([$_POST['status'], $_POST['inquiry_id']]);
        
        // Set success message in session
        $_SESSION['success_message'] = "Inquiry status updated successfully.";
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit();
    } catch (PDOException $e) {
        error_log("Status update error: " . $e->getMessage());
        $error = "An error occurred while updating the status.";
    }
}

// Get success message from session and clear it
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

try {
    // Get count of pending inquiries
    $stmt = $conn->query("SELECT COUNT(*) FROM inquiry WHERE inquiry_status = 'pending'");
    $pending_count = $stmt->fetchColumn();

    // Build the query based on filters
    $query = "
        SELECT i.*, 
               c.name as client_name, 
               c.email as client_email,
               c.phone as client_phone,
               e.event_name,
               e.event_date,
               e.event_type,
               e.venue_equipment,
               e.description as event_description,
               DATE_FORMAT(i.inquiry_date, '%M %d, %Y %h:%i %p') as formatted_date
        FROM inquiry i
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN event_table e ON i.event_id = e.event_id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status_filter !== 'all') {
        if ($status_filter === 'deleted') {
            $query .= " AND i.inquiry_status = 'deleted by client'";
        } else {
            $query .= " AND i.inquiry_status = ?";
            $params[] = $status_filter;
        }
    }
    
    if ($search) {
        $query .= " AND (c.name LIKE ? OR e.event_name LIKE ? OR i.inquiry_id LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    $query .= " ORDER BY i.inquiry_id DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Inquiry fetch error: " . $e->getMessage());
    $error = "An error occurred while fetching inquiries.";
}

if (!isset($inquiries) || !is_array($inquiries)) $inquiries = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inquiries - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .manage-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .page-title {
            font-size: 2em;
            color: #2c3e50;
            margin: 0;
        }

        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .filter-group {
            flex: 1;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }

        .inquiries-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .inquiries-table th,
        .inquiries-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .inquiries-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .inquiries-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-completed {
            background: #cce5ff;
            color: #004085;
        }

        .status-deleted-by-client {
            background: #e2e3e5;
            color: #383d41;
        }

        .action-buttons.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-primary {
            background: #007bff;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .details-row {
            background: #f8f9fa;
            display: none;
        }

        .details-row.active {
            display: table-row;
        }

        .details-content {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .info-group {
            margin-bottom: 15px;
        }

        .info-label {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .toggle-details {
            background: none;
            border: none;
            color: #007bff;
            cursor: pointer;
            padding: 8px;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .toggle-details:hover {
            color: #0056b3;
            transform: scale(1.1);
        }

        .toggle-details i {
            transition: transform 0.3s ease;
        }

        .toggle-details.active i {
            transform: rotate(180deg);
        }

        .toggle-details .btn-text {
            font-size: 0.9em;
            font-weight: 500;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .pending-badge {
            background: #fff3cd;
            color: #856404;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-badge i {
            color: #856404;
        }

        @media (max-width: 768px) {
            .filters {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .inquiries-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="/tbcph/public/index.php">TBCPH</a>
            </div>
            <ul class="nav-links">
                <li><a href="/tbcph/public/index.php">Home</a></li>
                <li><a href="/tbcph/public/about.php">About</a></li>
                <li><a href="/tbcph/public/buskers.php">Buskers</a></li>
                <li><a href="/tbcph/public/contact.php">Contact</a></li>
                <?php if(isset($_SESSION['admin_email'])): ?>
                    <li><a href="/tbcph/admin/dashboard.php">Admin Dashboard</a></li>
                    <li><a href="/tbcph/admin/profile.php">My Profile</a></li>
                    <li><a href="/tbcph/includes/logout.php?type=admin">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="manage-container">
            <div class="page-header">
                <div class="header-content">
                    <h1 class="page-title">Manage Inquiries</h1>
                    <div class="pending-badge">
                        <i class="fas fa-clock"></i>
                        <?php echo $pending_count; ?> Pending Inquiries
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="GET" class="filters">
                <div class="filter-group">
                    <label for="status">Filter by Status</label>
                    <select id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="deleted" <?php echo $status_filter === 'deleted' ? 'selected' : ''; ?>>Deleted by Client</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search by client name, event, or ID" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="filter-group" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                </div>
            </form>

            <table class="inquiries-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Event</th>
                        <th>Date</th>
                        <th>Inquiry Created</th>
                        <th>Budget</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inquiries as $inquiry): ?>
                        <tr>
                            <td><?php echo $inquiry['inquiry_id']; ?></td>
                            <td><?php echo htmlspecialchars($inquiry['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['event_name']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['event_date']); ?></td>
                            <td><?php echo !empty($inquiry['formatted_date']) ? htmlspecialchars($inquiry['formatted_date']) : 'Not set'; ?></td>
                            <td>₱<?php echo number_format($inquiry['budget'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $inquiry['inquiry_status'])); ?>">
                                    <?php echo ucwords($inquiry['inquiry_status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="toggle-details" onclick="toggleDetails(<?php echo $inquiry['inquiry_id']; ?>)" title="View Details">
                                    <i class="fas fa-chevron-circle-down"></i>
                                    <span class="btn-text">Details</span>
                                </button>
                            </td>
                        </tr>
                        <tr class="details-row" id="details-<?php echo $inquiry['inquiry_id']; ?>">
                            <td colspan="7">
                                <div class="details-content">
                                    <div class="info-grid">
                                        <div class="info-group">
                                            <div class="info-label">Client Information</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($inquiry['client_name']); ?><br>
                                                Email: <?php echo htmlspecialchars($inquiry['client_email']); ?><br>
                                                Phone: <?php echo htmlspecialchars($inquiry['client_phone']); ?>
                                            </div>
                                        </div>
                                        <div class="info-group">
                                            <div class="info-label">Event Details</div>
                                            <div class="info-value">
                                                Event: <?php echo htmlspecialchars($inquiry['event_name']); ?><br>
                                                Type: <?php echo htmlspecialchars($inquiry['event_type']); ?><br>
                                                Date: <?php echo htmlspecialchars($inquiry['event_date']); ?><br>
                                                Inquiry Created: <?php echo !empty($inquiry['formatted_date']) ? htmlspecialchars($inquiry['formatted_date']) : 'Not set'; ?>
                                            </div>
                                        </div>
                                        <?php if ($inquiry['event_description']): ?>
                                            <div class="info-group">
                                                <div class="info-label">Event Description</div>
                                                <div class="info-value"><?php echo nl2br(htmlspecialchars($inquiry['event_description'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($inquiry['venue_equipment']): ?>
                                            <div class="info-group">
                                                <div class="info-label">Venue Equipment</div>
                                                <div class="info-value"><?php echo nl2br(htmlspecialchars($inquiry['venue_equipment'])); ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="action-buttons" style="margin-top: 20px;" <?php echo $inquiry['inquiry_status'] === 'deleted by client' ? 'class="disabled"' : ''; ?>>
                                        <?php if ($inquiry['inquiry_status'] !== 'deleted by client'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="inquiry_id" value="<?php echo $inquiry['inquiry_id']; ?>">
                                                <button type="submit" name="status" value="approved" class="btn btn-success">Approve</button>
                                                <button type="submit" name="status" value="rejected" class="btn btn-danger">Reject</button>
                                                <button type="submit" name="status" value="completed" class="btn btn-primary">Mark as Completed</button>
                                            </form>
                                            <a href="tel:<?php echo htmlspecialchars($inquiry['client_phone']); ?>" class="btn btn-secondary">Call Client</a>
                                        <?php else: ?>
                                            <div class="info-value" style="color: #6c757d;">
                                                <i class="fas fa-info-circle"></i> This inquiry was deleted by the client and cannot be modified.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <script>
        function toggleDetails(inquiryId) {
            const detailsRow = document.getElementById(`details-${inquiryId}`);
            const button = detailsRow.previousElementSibling.querySelector('.toggle-details');
            const icon = button.querySelector('i');
            
            if (detailsRow.classList.contains('active')) {
                detailsRow.classList.remove('active');
                button.classList.remove('active');
                icon.classList.remove('fa-chevron-circle-up');
                icon.classList.add('fa-chevron-circle-down');
                button.querySelector('.btn-text').textContent = 'Details';
            } else {
                detailsRow.classList.add('active');
                button.classList.add('active');
                icon.classList.remove('fa-chevron-circle-down');
                icon.classList.add('fa-chevron-circle-up');
                button.querySelector('.btn-text').textContent = 'Hide';
            }
        }
    </script>
</body>
</html> 