<?php
session_start();
require_once '../includes/config.php'; // Include the database configuration

// Debug session information
error_log("Session contents: " . print_r($_SESSION, true));
error_log("POST contents: " . print_r($_POST, true));

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

// This should be after all POST handling to display latest messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
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
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <a href="../includes/logout.php" class="btn btn-danger logout-btn">Logout</a>
        <h1 class="my-4">Busker Dashboard</h1>
        <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link" id="upcoming-tab" data-toggle="tab" href="#upcoming" role="tab">Upcoming Bookings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="requests-tab" data-toggle="tab" href="#requests" role="tab">Booking Requests</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="past-tab" data-toggle="tab" href="#past" role="tab">Past Events</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" id="profile-tab" data-toggle="tab" href="#profile" role="tab">Profile Management</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="documents-tab" data-toggle="tab" href="#documents" role="tab">Document Management</a>
            </li>
        </ul>
        <div class="tab-content" id="dashboardTabsContent">
            <div class="tab-pane fade" id="upcoming" role="tabpanel">
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
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <h2>Profile Management</h2>
                <?php if ($busker_data): ?>
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success alert-fixed"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-fixed"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" class="profile-form">
                        <input type="hidden" name="update_profile" value="1">

                        <h3>Personal and Contact Information</h3>
                        <div class="form-group">
                            <label for="email">Email (Cannot be changed)</label>
                            <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($busker_data['email']); ?>" disabled>
                        </div>
                        <div class="form-group">
                            <label for="band_name">Band Name (Optional)</label>
                            <input type="text" class="form-control" id="band_name" name="band_name" value="<?php echo htmlspecialchars($busker_data['band_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($busker_data['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="contact_number">Contact Number</label>
                            <input type="text" class="form-control" id="contact_number" name="contact_number" value="<?php echo htmlspecialchars($busker_data['contact_number']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="address">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($busker_data['address']); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="birthday">Birthday</label>
                            <input type="date" class="form-control" id="birthday" name="birthday" value="<?php echo htmlspecialchars($busker_data['birthday']); ?>">
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="has_equipment" name="has_equipment" value="1" <?php echo $busker_data['has_equipment'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="has_equipment">I have my own equipment</label>
                        </div>

                        <h3 class="mt-4">Genres</h3>
                        <div class="form-group">
                            <div class="genre-checkbox-group">
                                <?php foreach ($all_genres as $genre): ?>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" id="genre_<?php echo $genre['genre_id']; ?>" name="genres[]" value="<?php echo $genre['genre_id']; ?>"
                                            <?php echo in_array($genre['genre_id'], $busker_genre_ids) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="genre_<?php echo $genre['genre_id']; ?>"><?php echo htmlspecialchars($genre['name']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <h3 class="mt-4">Equipment (Current)</h3>
                        <div class="equipment-list-section">
                            <?php if (!empty($busker_equipment)): ?>
                                <ul>
                                    <?php foreach ($busker_equipment as $eq): ?>
                                        <li>
                                            <?php echo htmlspecialchars($eq['equipment_name']) . ' (' . htmlspecialchars($eq['quantity']) . ', ' . htmlspecialchars($eq['eq_condition']) . ')'; ?>
                                            <form action="" method="POST" style="display:inline;">
                                                <input type="hidden" name="delete_equipment" value="1">
                                                <input type="hidden" name="equipment_id" value="<?php echo $eq['equipment_id']; ?>">
                                                <button type="submit" class="remove-equipment-btn" onclick="return confirm('Are you sure you want to delete this equipment?');"><i class="fas fa-times"></i></button>
                                            </form>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No equipment listed.</p>
                            <?php endif; ?>
                            
                            <!-- Add Equipment Button -->
                            <button type="button" class="btn btn-primary mt-3" data-toggle="modal" data-target="#addEquipmentModal">
                                <i class="fas fa-plus"></i> Add New Equipment
                            </button>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary mt-4">Update Profile</button>
                    </form>
                <?php else: ?>
                    <p class="alert alert-danger">Could not load busker profile. Please try again.</p>
                <?php endif; ?>
            </div>
            <div class="tab-pane fade" id="documents" role="tabpanel">
                <h2>Document Management</h2>
                <!-- Content for document management will go here -->
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

    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab');
            if (tab) {
                $(`#dashboardTabs a[href="#${tab}"]`).tab('show');
            }
            
            // Fade out alerts after a few seconds
            setTimeout(function() {
                $('.alert-fixed').fadeOut('slow');
            }, 5000);

            // Clear modal form when it's closed
            $('#addEquipmentModal').on('hidden.bs.modal', function () {
                $(this).find('form')[0].reset();
            });
        });
    </script>
</body>
</html> 