<?php
require_once '../includes/config.php';

// Check if inquiry data exists in session
if (!isset($_SESSION['temp_inquiry_data'])) {
    header('Location: contact.php');
    exit();
}

$temp_inquiry_data = $_SESSION['temp_inquiry_data'];
$error = '';
$success = '';

// Handle busker selection and final inquiry submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_busker'])) {
    if (!isset($_SESSION['client_id'])) {
        $error = 'Please log in as a client to finalize your inquiry.';
    } else {
        $selected_busker_id = $_POST['busker_id'];
        $client_id = $_SESSION['client_id'];

        try {
            $conn->beginTransaction();

            // 1. Handle Location (insert custom or use existing)
            $location_id = null;
            if ($temp_inquiry_data['location_type'] === 'custom') {
                $stmt = $conn->prepare("
                    INSERT INTO location (address, city, region)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([
                    $temp_inquiry_data['custom_address'],
                    $temp_inquiry_data['custom_city'],
                    $temp_inquiry_data['custom_region']
                ]);
                $location_id = $conn->lastInsertId();
            } else {
                // Cast to int to ensure it's a valid integer ID
                $location_id = (int)$temp_inquiry_data['location_id'];
                // Add a check to ensure it's not 0 if it's supposed to be an existing location
                if ($location_id === 0) {
                     throw new Exception("Invalid existing location selected. Location ID was 0.");
                }
            }

            // --- TEMPORARY DEBUGGING: Log location_id before event_table insert ---
            error_log("Debugging location_id before event_table insert: " . $location_id);
            // ----------------------------------------------------------------------

            // 2. Insert Event Details
            $stmt = $conn->prepare("
                INSERT INTO event_table (
                    event_name, event_type, event_date, time_slot_id, 
                    location_id, venue_equipment, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $temp_inquiry_data['event_name'],
                $temp_inquiry_data['event_type'],
                $temp_inquiry_data['event_date'],
                $temp_inquiry_data['time_slot_id'],
                $location_id,
                $temp_inquiry_data['venue_equipment'],
                $temp_inquiry_data['description']
            ]);
            $eventId = $conn->lastInsertId();

            // 3. Insert Inquiry
            $stmt = $conn->prepare("
                INSERT INTO inquiry (
                    client_id, event_id, budget, inquiry_status
                ) VALUES (?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $client_id,
                $eventId,
                $temp_inquiry_data['budget']
            ]);
            $inquiryId = $conn->lastInsertId();

            // 4. Handle Supporting Documents
            if (!empty($temp_inquiry_data['files'])) {
                foreach ($temp_inquiry_data['files'] as $doc_link) {
                    // Insert document
                    $stmt = $conn->prepare("INSERT INTO supporting_document (doc_link) VALUES (?)");
                    $stmt->execute([$doc_link]);
                    $doc_id = $conn->lastInsertId();

                    // Link document to inquiry
                    $stmt = $conn->prepare("INSERT INTO inquiry_document (inquiry_id, docs_id) VALUES (?, ?)");
                    $stmt->execute([$inquiryId, $doc_id]);
                }
            }

            // 5. Insert Genres
            if (!empty($temp_inquiry_data['genres'])) {
                $stmt = $conn->prepare("
                    INSERT INTO inquiry_genre (inquiry_id, genre_id)
                    VALUES (?, ?)
                ");
                foreach ($temp_inquiry_data['genres'] as $genreId) {
                    $stmt->execute([$inquiryId, $genreId]);
                }
            }

            // 6. Insert Hire Record (linking inquiry to selected busker)
            $stmt = $conn->prepare("
                INSERT INTO hire (inquiry_id, busker_id, payment_status)
                VALUES (?, ?, 'Pending')
            ");
            $stmt->execute([$inquiryId, $selected_busker_id]);

            $conn->commit();
            unset($_SESSION['temp_inquiry_data']); // Clear session data
            $_SESSION['success'] = 'Your inquiry has been submitted successfully! We will contact you shortly.';
            header('Location: ../client/dashboard.php');
            exit();

        } catch(PDOException $e) {
            $conn->rollBack();
            error_log("Error finalizing inquiry (PDO): " . $e->getMessage());
            $error = 'An error occurred while finalizing your inquiry (PDO). Please try again. Error: ' . $e->getMessage();
        } catch(Exception $e) {
            $conn->rollBack(); // Ensure rollback if a non-PDO exception occurs after transaction starts
            error_log("Error finalizing inquiry (General): " . $e->getMessage());
            $error = 'An error occurred while finalizing your inquiry (General). Please try again. Error: ' . $e->getMessage();
        }
    }
}

// Fetch active buskers with their genres and equipment
try {
    $filter_genre_id = isset($_GET['genre_id']) ? (int)$_GET['genre_id'] : null;
    $buskers_query = "
        SELECT 
            b.busker_id, 
            b.band_name, 
            b.name, 
            GROUP_CONCAT(DISTINCT g.name) AS genres, 
            GROUP_CONCAT(DISTINCT be.equipment_name) AS equipment
        FROM busker b
        LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
        LEFT JOIN genre g ON bg.genre_id = g.genre_id
        LEFT JOIN busker_equipment be ON b.busker_id = be.busker_id
        WHERE b.status = 'active'
    ";
    
    $params = [];

    if ($filter_genre_id) {
        $buskers_query .= " AND bg.genre_id = ?";
        $params[] = $filter_genre_id;
    }

    $buskers_query .= " GROUP BY b.busker_id ORDER BY b.band_name";

    $stmt = $conn->prepare($buskers_query);
    $stmt->execute($params);
    $buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Error fetching buskers: " . $e->getMessage());
    $buskers = [];
}

// Fetch all genres for filter dropdown
try {
    $stmt = $conn->query("SELECT genre_id, name FROM genre ORDER BY name");
    $all_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $all_genres = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Busker - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .filter-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .filter-section label {
            font-weight: bold;
            color: #555;
        }
        .filter-section select {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
            flex-grow: 1;
            max-width: 300px;
        }
        .busker-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        .busker-card {
            background: #fbfbfb;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .busker-card h3 {
            color: #3498db;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.4em;
        }
        .busker-card p {
            margin-bottom: 5px;
            color: #666;
        }
        .busker-card strong {
            color: #333;
        }
        .genre-tags {
            margin-top: 10px;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .genre-tag {
            background: #e9e9e9;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.85em;
            color: #444;
        }
        .equipment-list {
            margin-top: 10px;
            list-style: none;
            padding: 0;
        }
        .equipment-list li {
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: 0.9em;
            color: #555;
        }
        .select-button-form {
            margin-top: 20px;
            text-align: right;
        }
        .btn-select {
            background: #2ecc71;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background 0.3s;
        }
        .btn-select:hover {
            background: #27ae60;
        }
        .no-buskers {
            text-align: center;
            padding: 50px;
            color: #777;
            font-size: 1.2em;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="container">
            <h1>Select a Busker for Your Inquiry</h1>

            <?php /* Removed success/error alert boxes as requested */ ?>
            <?php /*
            if (isset($success)):
            ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php
            endif;
            ?>

            <?php
            if (isset($error)):
            ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php
            endif;
            */ ?>

            <div class="filter-section">
                <label for="genreFilter">Filter by Genre:</label>
                <select id="genreFilter" onchange="window.location.href='select_busker.php?genre_id=' + this.value">
                    <option value="">All Genres</option>
                    <?php foreach ($all_genres as $genre): ?>
                        <option value="<?php echo htmlspecialchars($genre['genre_id']); ?>"
                            <?php echo ($filter_genre_id == $genre['genre_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($genre['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="busker-list">
                <?php if (empty($buskers)): ?>
                    <p class="no-buskers">No buskers found matching your criteria.</p>
                <?php else: ?>
                    <?php foreach ($buskers as $busker): ?>
                        <div class="busker-card">
                            <div>
                                <h3><?php echo htmlspecialchars($busker['band_name'] ?: $busker['name']); ?></h3>
                                <p><strong>Individual Name:</strong> <?php echo htmlspecialchars($busker['name']); ?></p>
                                <p><strong>Genres:</strong></p>
                                <div class="genre-tags">
                                    <?php
                                    $busker_genres = $busker['genres'] ? explode(',', $busker['genres']) : [];
                                    foreach ($busker_genres as $g) {
                                        echo "<span class=\"genre-tag\">" . htmlspecialchars(trim($g)) . "</span>";
                                    }
                                    if (empty($busker_genres)) {
                                        echo "<span class=\"genre-tag\">No genres listed</span>";
                                    }
                                    ?>
                                </div>
                                <p><strong>Equipment:</strong></p>
                                <ul class="equipment-list">
                                    <?php
                                    $busker_equipment = $busker['equipment'] ? explode(',', $busker['equipment']) : [];
                                    foreach ($busker_equipment as $eq) {
                                        echo "<li>" . htmlspecialchars(trim($eq)) . "</li>";
                                    }
                                    if (empty($busker_equipment)) {
                                        echo "<li>No equipment listed</li>";
                                    }
                                    ?>
                                </ul>
                            </div>
                            <div class="select-button-form">
                                <form method="POST" action="select_busker.php">
                                    <input type="hidden" name="busker_id" value="<?php echo htmlspecialchars($busker['busker_id']); ?>">
                                    <button type="submit" name="select_busker" class="btn-select">Select Busker</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include '../includes/footer.php'; ?>
</body>
</html> 