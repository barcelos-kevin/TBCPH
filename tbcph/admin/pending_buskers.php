<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

$success = $error = '';

try {
    // Handle busker status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['busker_id'], $_POST['action'])) {
        $busker_id = $_POST['busker_id'];
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'active' : 'rejected';

        $stmt = $conn->prepare("UPDATE busker SET status = ? WHERE busker_id = ?");
        if ($stmt->execute([$new_status, $busker_id])) {
            $success = "Busker status updated successfully.";
        } else {
            $error = "Failed to update busker status.";
        }
    }

    // Get pending buskers
    $stmt = $conn->prepare("
        SELECT b.*, GROUP_CONCAT(g.name) as genres
        FROM busker b
        LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
        LEFT JOIN genre g ON bg.genre_id = g.genre_id
        WHERE b.status = 'pending'
        GROUP BY b.busker_id
        ORDER BY b.busker_id DESC
    ");
    $stmt->execute();
    $pending_buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Pending buskers error: " . $e->getMessage());
    $error = "An error occurred while fetching pending buskers.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Busker Registrations - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .pending-buskers-container {
            max-width: 1200px;
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

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
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

        .busker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .busker-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s ease;
        }

        .busker-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .busker-name {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .busker-band {
            color: #666;
            font-size: 1.1em;
            margin-bottom: 15px;
        }

        .busker-info {
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            color: #666;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 100px;
        }

        .busker-genres {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .genre-tag {
            background: #e9ecef;
            color: #2c3e50;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            text-decoration: none;
            font-size: 0.9em;
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

        .no-buskers {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            color: #666;
        }

        @media (max-width: 768px) {
            .pending-buskers-container {
                padding: 15px;
            }

            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .busker-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
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
        <div class="pending-buskers-container">
            <div class="page-header">
                <h1 class="page-title">Pending Busker Registrations</h1>
                <a href="dashboard.php" class="back-button">
                    ← Back to Dashboard
                </a>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if (empty($pending_buskers)): ?>
                <div class="no-buskers">
                    <h2>No Pending Registrations</h2>
                    <p>There are currently no busker registrations awaiting approval.</p>
                </div>
            <?php else: ?>
                <div class="busker-grid">
                    <?php foreach ($pending_buskers as $busker): ?>
                        <div class="busker-card">
                            <h2 class="busker-name"><?php echo htmlspecialchars($busker['name']); ?></h2>
                            <?php if ($busker['band_name']): ?>
                                <div class="busker-band"><?php echo htmlspecialchars($busker['band_name']); ?></div>
                            <?php endif; ?>

                            <div class="busker-info">
                                <div class="info-item">
                                    <span class="info-label">Email:</span>
                                    <span><?php echo htmlspecialchars($busker['email']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone:</span>
                                    <span><?php echo htmlspecialchars($busker['contact_number']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Address:</span>
                                    <span><?php echo htmlspecialchars($busker['address']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Birthday:</span>
                                    <span><?php echo htmlspecialchars($busker['birthday']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Equipment:</span>
                                    <span><?php echo $busker['has_equipment'] ? 'Yes' : 'No'; ?></span>
                                </div>
                            </div>

                            <?php if ($busker['genres']): ?>
                                <div class="busker-genres">
                                    <?php foreach (explode(',', $busker['genres']) as $genre): ?>
                                        <span class="genre-tag"><?php echo htmlspecialchars($genre); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" class="action-buttons">
                                <input type="hidden" name="busker_id" value="<?php echo $busker['busker_id']; ?>">
                                <button type="submit" name="action" value="approve" class="btn btn-approve">
                                    Approve
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-reject">
                                    Reject
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 