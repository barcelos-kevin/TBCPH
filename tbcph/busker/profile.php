<?php
require_once '../includes/config.php';

// Redirect if not logged in as busker
if (!isset($_SESSION['busker_id'])) {
    header('Location: index.php');
    exit();
}

$busker_id = $_SESSION['busker_id'];
$success = '';
$error = '';

// Handle profile update
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
        // Update genres
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
    } catch (PDOException $e) {
        $conn->rollBack();
        $error = 'Error updating profile: ' . $e->getMessage();
    }
}
// Handle add equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_equipment'])) {
    try {
        if (empty($_POST['equipment_name'])) throw new Exception("Equipment name is required");
        $stmt = $conn->prepare("
            INSERT INTO busker_equipment (busker_id, equipment_name, quantity, eq_condition)
            VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $busker_id,
            $_POST['equipment_name'],
            $_POST['quantity'] ?? '',
            $_POST['eq_condition'] ?? ''
        ]);
        $success = 'Equipment added successfully!';
    } catch (Exception $e) {
        $error = 'Error adding equipment: ' . $e->getMessage();
    }
}
// Handle delete equipment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_equipment'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM busker_equipment WHERE equipment_id = ? AND busker_id = ?");
        $stmt->execute([$_POST['equipment_id'], $busker_id]);
        $success = 'Equipment deleted successfully!';
    } catch (PDOException $e) {
        $error = 'Error deleting equipment: ' . $e->getMessage();
    }
}
// Fetch busker data
try {
    $stmt = $conn->prepare("SELECT band_name, name, contact_number, address, birthday, has_equipment, email FROM busker WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_data = $stmt->fetch(PDO::FETCH_ASSOC);
    // Genres
    $stmt = $conn->prepare("SELECT g.genre_id, g.name FROM busker_genre bg JOIN genre g ON bg.genre_id = g.genre_id WHERE bg.busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $busker_genre_ids = array_column($busker_genres, 'genre_id');
    // Equipment
    $stmt = $conn->prepare("SELECT equipment_id, equipment_name, quantity, eq_condition FROM busker_equipment WHERE busker_id = ?");
    $stmt->execute([$busker_id]);
    $busker_equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // All genres
    $stmt = $conn->query("SELECT genre_id, name FROM genre ORDER BY name");
    $all_genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Failed to load profile data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        .profile-container { max-width: 700px; margin: 40px auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.07); padding: 32px; }
        .profile-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .profile-title { font-size: 2em; color: #2c3e50; }
        .badge { display: inline-block; background: #3498db; color: #fff; border-radius: 12px; padding: 4px 12px; font-size: 0.95em; margin-bottom: 4px; white-space: nowrap; margin-right: 6px; }
        .genre-badges { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 5px; }
        .equipment-list-section ul { list-style: none; padding: 0; }
        .equipment-list-section li { background-color: #e9ecef; border: 1px solid #dee2e6; padding: 8px 15px; margin-bottom: 8px; border-radius: .25rem; display: flex; justify-content: space-between; align-items: center; }
        .equipment-list-section .remove-equipment-btn { background: none; border: none; color: #dc3545; cursor: pointer; font-size: 1.2rem; padding: 0 5px; }
        .equipment-list-section .remove-equipment-btn:hover { color: #c82333; }
        .modal-header { border-bottom: 1px solid #eee; }
        .modal-footer { border-top: 1px solid #eee; }
    </style>
</head>
<body>
<?php include '../includes/header.php'; ?>
<div class="profile-container">
    <div class="profile-header">
        <h1 class="profile-title">My Profile</h1>
        <button class="btn btn-primary" data-toggle="modal" data-target="#editProfileModal"><i class="fas fa-edit"></i> Edit Profile</button>
    </div>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success"> <?php echo $success; ?> </div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"> <?php echo $error; ?> </div>
    <?php endif; ?>
    <?php if ($busker_data): ?>
        <div class="mb-3"><strong>Email:</strong> <?php echo htmlspecialchars($busker_data['email']); ?></div>
        <div class="mb-3"><strong>Band Name:</strong> <?php echo htmlspecialchars($busker_data['band_name']); ?></div>
        <div class="mb-3"><strong>Full Name:</strong> <?php echo htmlspecialchars($busker_data['name']); ?></div>
        <div class="mb-3"><strong>Contact Number:</strong> <?php echo htmlspecialchars($busker_data['contact_number']); ?></div>
        <div class="mb-3"><strong>Address:</strong> <?php echo htmlspecialchars($busker_data['address']); ?></div>
        <div class="mb-3"><strong>Birthday:</strong> <?php echo htmlspecialchars($busker_data['birthday']); ?></div>
        <div class="mb-3"><strong>Has Equipment:</strong> <?php echo $busker_data['has_equipment'] ? 'Yes' : 'No'; ?></div>
        <div class="mb-3"><strong>Genres:</strong>
            <div class="genre-badges">
                <?php foreach ($busker_genres as $g): ?>
                    <span class="badge"><?php echo htmlspecialchars($g['name']); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mb-3"><strong>Equipment:</strong>
            <div class="equipment-list-section">
                <?php if (!empty($busker_equipment)): ?>
                    <ul>
                        <?php foreach ($busker_equipment as $eq): ?>
                            <li>
                                <?php echo htmlspecialchars($eq['equipment_name'] ?? '') . ' (' . htmlspecialchars($eq['quantity'] ?? '') . ', ' . htmlspecialchars($eq['eq_condition'] ?? '') . ')'; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>No equipment listed.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <p class="alert alert-danger">Could not load busker profile. Please try again.</p>
    <?php endif; ?>
</div>
<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="update_profile" value="1">
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
                    <div class="form-group">
                        <label>Genres</label>
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
                    <hr>
                    <h5>Equipment Management</h5>
                    <div class="equipment-list-section">
                        <?php if (!empty($busker_equipment)): ?>
                            <ul>
                                <?php foreach ($busker_equipment as $eq): ?>
                                    <li>
                                        <?php echo htmlspecialchars($eq['equipment_name'] ?? '') . ' (' . htmlspecialchars($eq['quantity'] ?? '') . ', ' . htmlspecialchars($eq['eq_condition'] ?? '') . ')'; ?>
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
                        <form action="" method="POST" class="mt-3">
                            <input type="hidden" name="add_equipment" value="1">
                            <div class="form-row">
                                <div class="col">
                                    <input type="text" class="form-control" name="equipment_name" placeholder="Equipment Name" required>
                                </div>
                                <div class="col">
                                    <input type="text" class="form-control" name="quantity" placeholder="Quantity">
                                </div>
                                <div class="col">
                                    <select class="form-control" name="eq_condition">
                                        <option value="Excellent">Excellent</option>
                                        <option value="Good">Good</option>
                                        <option value="Fair">Fair</option>
                                        <option value="Poor">Poor</option>
                                    </select>
                                </div>
                                <div class="col-auto">
                                    <button type="submit" class="btn btn-success">Add</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>  
