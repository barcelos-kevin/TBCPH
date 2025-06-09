<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

// Get statistics
try {
    // Total buskers
    $stmt = $conn->query("SELECT COUNT(*) FROM busker");
    $total_buskers = $stmt->fetchColumn();

    // Active buskers
    $stmt = $conn->query("SELECT COUNT(*) FROM busker WHERE status = 'active'");
    $active_buskers = $stmt->fetchColumn();

    // Total clients
    $stmt = $conn->query("SELECT COUNT(*) FROM client");
    $total_clients = $stmt->fetchColumn();

    // Total inquiries
    $stmt = $conn->query("SELECT COUNT(*) FROM inquiry");
    $total_inquiries = $stmt->fetchColumn();

    // Recent inquiries
    $stmt = $conn->query("
        SELECT i.inquiry_id, i.budget, i.inquiry_status, c.name as client_name, e.event_name, e.event_date
        FROM inquiry i
        JOIN client c ON i.client_id = c.client_id
        LEFT JOIN event_table e ON i.event_id = e.event_id
        ORDER BY i.inquiry_id DESC
        LIMIT 5
    ");
    $recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending busker registrations
    $stmt = $conn->query("
        SELECT b.*, 
               DATE_FORMAT(b.registration_date, '%M %d, %Y') as formatted_date
        FROM busker b 
        WHERE b.status = 'pending' 
        ORDER BY b.registration_date DESC
    ");
    $pending_buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .dashboard-container {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .dashboard-title {
            font-size: 2em;
            color: #2c3e50;
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .header-actions .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1em;
            transition: all 0.3s ease;
            background: #2c3e50;
            color: white;
            border: 2px solid #2c3e50;
        }

        .header-actions .btn i {
            font-size: 1.2em;
        }

        .header-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background: #34495e;
            border-color: #34495e;
        }

        .header-actions .btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .stat-card .number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
        }

        .dashboard-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 1.5em;
            color: #2c3e50;
        }

        .view-all {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9em;
        }

        .view-all:hover {
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: 500;
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

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            margin-right: 8px;
        }

        .approve-btn {
            background: #28a745;
            color: white;
        }

        .reject-btn {
            background: #dc3545;
            color: white;
        }

        .view-btn {
            background: #17a2b8;
            color: white;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow-x: auto;
            margin-top: 20px;
        }

        .dashboard-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .dashboard-table th,
        .dashboard-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .dashboard-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .dashboard-table tr:hover {
            background: #f8f9fa;
        }

        .text-center {
            text-align: center;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
        }

        .section-header .btn {
            padding: 8px 16px;
            font-size: 0.9em;
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
                    <li><a href="/tbcph/includes/logout.php?type=admin">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Admin Dashboard</h1>
                <div class="header-actions">
                    <a href="pending_buskers.php" class="btn btn-primary">
                        <i class="fas fa-user-clock"></i> Pending Buskers
                    </a>
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i> My Profile
                    </a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Buskers</h3>
                    <p><?php echo $total_buskers; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Active Buskers</h3>
                    <p><?php echo $active_buskers; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Clients</h3>
                    <p><?php echo $total_clients; ?></p>
                </div>
                <div class="stat-card">
                    <h3>Total Inquiries</h3>
                    <p><?php echo $total_inquiries; ?></p>
                </div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>Pending Busker Registrations</h2>
                    <a href="pending_buskers.php" class="btn btn-primary">View All</a>
                </div>
                <div class="table-container">
                    <table class="dashboard-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Contact</th>
                                <th>Band Name</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_buskers)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No pending registrations</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_buskers as $busker): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($busker['name']); ?></td>
                                        <td><?php echo htmlspecialchars($busker['email']); ?></td>
                                        <td><?php echo htmlspecialchars($busker['contact_number']); ?></td>
                                        <td><?php echo htmlspecialchars($busker['band_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo $busker['formatted_date'] ?? 'N/A'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Inquiries</h2>
                    <a href="manage_inquiries.php" class="view-all">View All</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Event</th>
                            <th>Date</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_inquiries as $inquiry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($inquiry['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['event_name']); ?></td>
                            <td><?php echo htmlspecialchars($inquiry['event_date']); ?></td>
                            <td>₱<?php echo number_format($inquiry['budget'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($inquiry['inquiry_status']); ?>">
                                    <?php echo htmlspecialchars($inquiry['inquiry_status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view_inquiry.php?id=<?php echo $inquiry['inquiry_id']; ?>" class="action-btn view-btn">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>  
