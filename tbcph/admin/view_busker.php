<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';
$busker = null;
$equipment = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['busker_id'])) {
        $busker_id = $_POST['busker_id'];
        $action = $_POST['action'];
        
        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE busker SET status = 'active' WHERE busker_id = ?");
                $stmt->execute([$busker_id]);
                $success = "Busker registration has been approved.";
            } elseif ($action === 'reject') {
                $stmt = $conn->prepare("UPDATE busker SET status = 'rejected' WHERE busker_id = ?");
                $stmt->execute([$busker_id]);
                $success = "Busker registration has been rejected.";
            }
            
            // Redirect to prevent form resubmission
            header("Location: pending_buskers.php");
            exit();
        } catch (PDOException $e) {
            error_log("Busker status update error: " . $e->getMessage());
            $error = "An error occurred while updating busker status.";
        }
    }
}

try {
    if (!isset($_GET['id'])) {
        $error = "No busker ID provided.";
    } else {
        $busker_id = $_GET['id'];

        // Get busker details with genres
        $stmt = $conn->prepare("
            SELECT b.*, 
                   GROUP_CONCAT(g.name) as genres,
                   DATE_FORMAT(b.registration_date, '%M %d, %Y') as formatted_date
            FROM busker b
            LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
            LEFT JOIN genre g ON bg.genre_id = g.genre_id
            WHERE b.busker_id = ?
            GROUP BY b.busker_id
        ");
        $stmt->execute([$busker_id]);
        $busker = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($busker) {
            // Get busker equipment
            $stmt = $conn->prepare("
                SELECT * FROM busker_equipment 
                WHERE busker_id = ?
            ");
            $stmt->execute([$busker_id]);
            $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $error = "Busker not found.";
        }
    }
} catch (PDOException $e) {
    error_log("View busker error: " . $e->getMessage());
    $error = "An error occurred while fetching busker details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Busker - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .view-busker-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            font-size: 2em;
            color: #2c3e50;
            margin: 0;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background: #e9ecef;
            color: #2c3e50;
        }

        .busker-details {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 15px;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .info-value {
            color: #666;
        }

        .genres-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .genre-tag {
            background: #e9ecef;
            color: #2c3e50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .equipment-table th,
        .equipment-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .equipment-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }

        @media (max-width: 768px) {
            .view-busker-container {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
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
        <div class="view-busker-container">
            <div class="page-header">
                <h1 class="page-title">Busker Details</h1>
                <a href="pending_buskers.php" class="back-button">
                    ← Back to Pending Buskers
                </a>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($busker): ?>
                <div class="busker-details">
                    <div class="section">
                        <h2 class="section-title">Personal Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['name']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Contact Number</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['contact_number']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['address']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Birthday</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['birthday']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Band Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['band_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Registration Date</div>
                                <div class="info-value"><?php echo htmlspecialchars($busker['formatted_date']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h2 class="section-title">Musical Genres</h2>
                        <?php if ($busker['genres']): ?>
                            <div class="genres-list">
                                <?php foreach (explode(',', $busker['genres']) as $genre): ?>
                                    <span class="genre-tag"><?php echo htmlspecialchars($genre); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p>No genres specified.</p>
                        <?php endif; ?>
                    </div>

                    <div class="section">
                        <h2 class="section-title">Equipment</h2>
                        <?php if ($busker['has_equipment'] && !empty($equipment)): ?>
                            <table class="equipment-table">
                                <thead>
                                    <tr>
                                        <th>Equipment Name</th>
                                        <th>Quantity</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipment as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                            <td><?php echo htmlspecialchars($item['eq_condition']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p>No equipment listed.</p>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="view_busker.php?id=<?php echo $busker['busker_id']; ?>" class="action-buttons">
                        <input type="hidden" name="busker_id" value="<?php echo $busker['busker_id']; ?>">
                        <button type="submit" name="action" value="approve" class="btn btn-approve">
                            Approve Registration
                        </button>
                        <button type="submit" name="action" value="reject" class="btn btn-reject">
                            Reject Registration
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 