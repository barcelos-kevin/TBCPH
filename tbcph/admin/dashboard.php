<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

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

    // Pending inquiries
    $stmt = $conn->query("SELECT COUNT(*) FROM inquiry WHERE inquiry_status = 'pending'");
    $pending_inquiries = $stmt->fetchColumn();

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

    // Fetch current admin details
    $admin = null;
    if (isset($_SESSION['admin_email'])) {
        $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
        $stmt->execute([$_SESSION['admin_email']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch pending admin registrations (for super admin)
    $pending_admins = [];
    if ($admin && $admin['account_level'] === 'super_admin') {
        $stmt = $conn->query("SELECT email, account_level, status FROM admin WHERE status = 'pending'");
        $pending_admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "An error occurred while loading the dashboard.";
}

// Add a function to map inquiry_status to admin status
function getAdminStatus($status) {
    switch (strtolower($status)) {
        case 'deleted':
        case 'deleted by client':
            return 'Deleted';
        case 'pending':
            return 'Pending';
        case 'approved':
        case 'approve by admin':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        case 'rejected by admin':
            return 'Rejected by Admin';
        case 'rejected by busker':
            return 'Rejected';
        case 'confirmed':
            return 'Confirmed';
        case 'canceled':
            return 'Canceled';
        case 'completed':
            return 'Completed';
        default:
            return ucfirst($status);
    }
}

// Handle approve/reject admin POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin && $admin['account_level'] === 'super_admin') {
    if (isset($_POST['approve_admin']) && !empty($_POST['admin_email'])) {
        $stmt = $conn->prepare("UPDATE admin SET status = 'approved' WHERE email = ?");
        $stmt->execute([$_POST['admin_email']]);
        header('Location: dashboard.php');
        exit();
    } elseif (isset($_POST['reject_admin']) && !empty($_POST['admin_email'])) {
        $stmt = $conn->prepare("UPDATE admin SET status = 'rejected' WHERE email = ?");
        $stmt->execute([$_POST['admin_email']]);
        header('Location: dashboard.php');
        exit();
    }
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

        .header-actions .btn-primary {
            background: #007bff;
            border-color: #007bff;
            font-size: 1.2em;
            padding: 14px 28px;
            box-shadow: 0 4px 6px rgba(0, 123, 255, 0.2);
        }

        .header-actions .btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(0, 123, 255, 0.3);
        }

        .header-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background: #34495e;
            border-color: #34495e;
        }

        .badge {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 8px;
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

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
                justify-content: center;
                margin-bottom: 10px;
            }

            table {
                display: block;
                overflow-x: auto;
            }
        }

        .stat-card.highlight {
            background: #fff3cd;
            border: 2px solid #ffeeba;
            position: relative;
        }

        .stat-card.highlight h3 {
            color: #856404;
        }

        .stat-card.highlight .number {
            color: #856404;
            font-size: 2.2em;
        }

        .view-link {
            display: inline-block;
            margin-top: 10px;
            color: #856404;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9em;
        }

        .view-link:hover {
            text-decoration: underline;
        }

        .status-badge.status-deleted { background: #e0e0e0; color: #888; }
        .status-badge.status-pending { background: #fff3cd; color: #856404; }
        .status-badge.status-approved { background: #d4edda; color: #155724; font-weight: 600; }
        .status-badge.status-rejected { background: #f8d7da; color: #721c24; }
        .status-badge.status-rejected-by-admin { background: #f5c6cb; color: #721c24; }
        .status-badge.status-confirmed { background: #d4edda; color: #155724; }
        .status-badge.status-canceled { background: #f5c6cb; color: #721c24; }
        .status-badge.status-completed { background: #cce5ff; color: #004085; }
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
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">Admin Dashboard</h1>
                <div>
                    <span style="font-size:1em;color:#888;">Account Level: <strong><?php echo htmlspecialchars($admin['account_level']); ?></strong></span>
                </div>
                <div class="header-actions">
                    <a href="manage_inquiries.php" class="btn btn-primary">
                        <i class="fas fa-tasks"></i>
                        Manage Inquiries
                        <?php if ($pending_inquiries > 0): ?>
                            <span class="badge"><?php echo $pending_inquiries; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="pending_buskers.php" class="btn">Pending Buskers</a>
                    <a href="profile.php" class="btn">My Profile</a>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total Buskers</h3>
                    <div class="number"><?php echo $total_buskers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Active Buskers</h3>
                    <div class="number"><?php echo $active_buskers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Clients</h3>
                    <div class="number"><?php echo $total_clients; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Inquiries</h3>
                    <div class="number"><?php echo $total_inquiries; ?></div>
                </div>
                <div class="stat-card highlight">
                    <h3>Pending Inquiries</h3>
                    <div class="number"><?php echo $pending_inquiries; ?></div>
                    <?php if ($pending_inquiries > 0): ?>
                        <a href="manage_inquiries.php?status=pending" class="view-link">View Pending</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Pending Busker Registrations</h2>
                    <a href="pending_buskers.php" class="view-all">View All</a>
                </div>
                <table>
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
                                    <td><?php echo $busker['formatted_date']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Recent Inquiries</h2>
                    <a href="manage_inquiries.php" class="view-all">Manage Inquiries</a>
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
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', getAdminStatus($inquiry['inquiry_status']))); ?>">
                                        <?php echo getAdminStatus($inquiry['inquiry_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="manage_inquiries.php?id=<?php echo $inquiry['inquiry_id']; ?>" class="action-btn view-btn">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($admin && $admin['account_level'] === 'super_admin'): ?>
            <div class="dashboard-section">
                <div class="section-header">
                    <h2 class="section-title">Pending Admin Registrations</h2>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Account Level</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_admins)): ?>
                            <tr><td colspan="4" class="text-center">No pending admin registrations</td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_admins as $pending): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pending['email']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['account_level']); ?></td>
                                    <td><?php echo htmlspecialchars($pending['status']); ?></td>
                                    <td>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($pending['email']); ?>">
                                            <button type="submit" name="approve_admin" class="btn btn-primary" onclick="return confirm('Approve this admin?');">Approve</button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="admin_email" value="<?php echo htmlspecialchars($pending['email']); ?>">
                                            <button type="submit" name="reject_admin" class="btn btn-secondary" onclick="return confirm('Reject this admin?');">Reject</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>  
