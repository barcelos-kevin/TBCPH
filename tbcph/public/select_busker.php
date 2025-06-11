<?php
require_once '../includes/config.php';

// Check if user is logged in and is a client
if (!isset($_SESSION['client_id'])) {
    redirect('/tbcph/client/index.php');
}

// Check if inquiry_id is provided
if (!isset($_GET['inquiry_id'])) {
    redirect('/tbcph/client/dashboard.php');
}

$inquiry_id = (int)$_GET['inquiry_id'];

// Verify that this inquiry belongs to the logged-in client
$stmt = $conn->prepare("SELECT i.*, e.event_name, e.event_date 
                       FROM inquiry i 
                       JOIN event_table e ON i.event_id = e.event_id 
                       WHERE i.inquiry_id = ? AND i.client_id = ?");
$stmt->execute([$inquiry_id, $_SESSION['client_id']]);
$inquiry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$inquiry) {
    redirect('/tbcph/client/dashboard.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['skip']) || !isset($_POST['busker_id'])) {
        // Update inquiry status to indicate no busker selected yet
        $stmt = $conn->prepare("UPDATE inquiry SET inquiry_status = 'pending' WHERE inquiry_id = ?");
        $stmt->execute([$inquiry_id]);
        redirectWithMessage('/tbcph/client/dashboard.php', 'You can select a busker later.', 'info');
    } else {
        $busker_id = (int)$_POST['busker_id'];
        
        // Verify busker exists and is active
        $stmt = $conn->prepare("SELECT * FROM busker WHERE busker_id = ? AND status = 'active'");
        $stmt->execute([$busker_id]);
        $busker = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$busker) {
            $error = 'Invalid busker selection.';
        } else {
            try {
                $conn->beginTransaction();
                
                // Create hire record
                $stmt = $conn->prepare("INSERT INTO hire (inquiry_id, busker_id, payment_status) VALUES (?, ?, 'pending')");
                $stmt->execute([$inquiry_id, $busker_id]);
                
                // Update inquiry status
                $stmt = $conn->prepare("UPDATE inquiry SET inquiry_status = 'busker selected' WHERE inquiry_id = ?");
                $stmt->execute([$inquiry_id]);
                
                $conn->commit();
                redirectWithMessage('/tbcph/client/dashboard.php', 'Busker selected successfully!', 'success');
            } catch(Exception $e) {
                $conn->rollBack();
                error_log("Error selecting busker: " . $e->getMessage());
                $error = 'An error occurred while selecting the busker. Please try again.';
            }
        }
    }
}

// Get all genres for filter
$stmt = $conn->prepare("SELECT * FROM genre ORDER BY name");
$stmt->execute();
$genres = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all equipment for filter
$stmt = $conn->prepare("SELECT DISTINCT equipment_name FROM busker_equipment ORDER BY equipment_name");
$stmt->execute();
$equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all active buskers with their equipment and genres
$stmt = $conn->prepare("
    SELECT 
        b.*,
        GROUP_CONCAT(DISTINCT g.name) as genres,
        GROUP_CONCAT(DISTINCT be.equipment_name) as equipment
    FROM busker b
    LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
    LEFT JOIN genre g ON bg.genre_id = g.genre_id
    LEFT JOIN busker_equipment be ON b.busker_id = be.busker_id
    WHERE b.status = 'active'
    GROUP BY b.busker_id
");
$stmt->execute();
$buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Busker - TBCPH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .busker-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .busker-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .busker-card.selected {
            border: 2px solid #0d6efd;
            background-color: #f8f9fa;
        }
        .genre-badge {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .equipment-list {
            list-style: none;
            padding-left: 0;
        }
        .equipment-list li {
            margin-bottom: 5px;
        }
        .busker-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        .filter-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-section h4 {
            margin-bottom: 15px;
        }
        .filter-checkbox {
            margin-right: 10px;
        }
        .filter-label {
            margin-right: 15px;
            margin-bottom: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h2>Select a Busker for Your Event</h2>
                <p class="text-muted">Event: <?php echo htmlspecialchars($inquiry['event_name']); ?></p>
                <p class="text-muted">Date: <?php echo date('F j, Y', strtotime($inquiry['event_date'])); ?></p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-section">
            <h4>Filter Buskers</h4>
            <form id="filterForm" class="mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <h5>Genres</h5>
                        <?php foreach ($genres as $genre): ?>
                            <label class="filter-label">
                                <input type="checkbox" class="filter-checkbox genre-filter" 
                                       value="<?php echo htmlspecialchars($genre['name']); ?>">
                                <?php echo htmlspecialchars($genre['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="col-md-6">
                        <h5>Equipment</h5>
                        <?php foreach ($equipment as $eq): ?>
                            <label class="filter-label">
                                <input type="checkbox" class="filter-checkbox equipment-filter" 
                                       value="<?php echo htmlspecialchars($eq['equipment_name']); ?>">
                                <?php echo htmlspecialchars($eq['equipment_name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>

        <form method="POST" id="selectBuskerForm">
            <div class="row">
                <?php foreach ($buskers as $busker): ?>
                    <div class="col-md-6 col-lg-4 mb-4 busker-item" 
                         data-genres="<?php echo htmlspecialchars($busker['genres']); ?>"
                         data-equipment="<?php echo htmlspecialchars($busker['equipment']); ?>">
                        <div class="card busker-card h-100">
                            <img src="../assets/images/placeholder.jpg" alt="<?php echo htmlspecialchars($busker['band_name'] ?: $busker['name']); ?>" class="busker-image">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($busker['band_name'] ?: $busker['name']); ?></h5>
                                <p class="card-text">
                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($busker['contact_number']); ?><br>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($busker['email']); ?>
                                </p>
                                
                                <?php if ($busker['genres']): ?>
                                    <div class="mb-3">
                                        <strong>Genres:</strong><br>
                                        <?php foreach (explode(',', $busker['genres']) as $genre): ?>
                                            <span class="badge bg-primary genre-badge"><?php echo htmlspecialchars($genre); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($busker['equipment']): ?>
                                    <div class="mb-3">
                                        <strong>Equipment:</strong>
                                        <ul class="equipment-list">
                                            <?php foreach (explode(',', $busker['equipment']) as $equipment): ?>
                                                <li><i class="fas fa-music"></i> <?php echo htmlspecialchars($equipment); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="busker_id" 
                                           value="<?php echo $busker['busker_id']; ?>">
                                    <label class="form-check-label">Select this busker</label>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="row mt-4">
                <div class="col">
                    <button type="submit" class="btn btn-primary">Select Busker</button>
                    <button type="submit" name="skip" value="1" class="btn btn-secondary">Skip for Now</button>
                    <a href="/tbcph/client/dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
                </div>
            </div>
        </form>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add click handler for busker cards
        document.querySelectorAll('.busker-card').forEach(card => {
            card.addEventListener('click', function() {
                // Remove selected class from all cards
                document.querySelectorAll('.busker-card').forEach(c => c.classList.remove('selected'));
                // Add selected class to clicked card
                this.classList.add('selected');
                // Check the radio button
                this.querySelector('input[type="radio"]').checked = true;
            });
        });

        // Filter functionality
        document.querySelectorAll('.filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', filterBuskers);
        });

        function filterBuskers() {
            const selectedGenres = Array.from(document.querySelectorAll('.genre-filter:checked')).map(cb => cb.value);
            const selectedEquipment = Array.from(document.querySelectorAll('.equipment-filter:checked')).map(cb => cb.value);

            document.querySelectorAll('.busker-item').forEach(item => {
                const genres = item.dataset.genres.split(',');
                const equipment = item.dataset.equipment.split(',');

                const matchesGenres = selectedGenres.length === 0 || selectedGenres.some(genre => genres.includes(genre));
                const matchesEquipment = selectedEquipment.length === 0 || selectedEquipment.some(eq => equipment.includes(eq));

                item.style.display = matchesGenres && matchesEquipment ? '' : 'none';
            });
        }
    </script>
</body>
</html> 