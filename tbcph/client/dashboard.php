<?php
require_once '../includes/config.php';

// Check if user is logged in as client
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
    header('Location: index.php');
    exit();
}

// Handle inquiry deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry'])) {
    try {
        $stmt = $conn->prepare("
            UPDATE inquiry 
            SET inquiry_status = 'deleted by client'
            WHERE inquiry_id = ? AND client_id = ?
        ");
        $stmt->execute([
            $_POST['inquiry_id'],
            $_SESSION['user_id']
        ]);
        $_SESSION['success'] = 'Inquiry has been deleted successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting inquiry: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle inquiry update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inquiry'])) {
    try {
        $conn->beginTransaction();
        
        // Update event details
        $stmt = $conn->prepare("
            UPDATE event_table e
            JOIN inquiry i ON e.event_id = i.event_id
            SET e.event_name = ?, 
                e.event_type = ?, 
                e.event_date = ?, 
                e.time_slot_id = COALESCE(?, e.time_slot_id), 
                e.venue_equipment = ?, 
                e.description = ?
            WHERE i.inquiry_id = ? AND i.client_id = ?
        ");
        $stmt->execute([
            $_POST['event_name'],
            $_POST['event_type'],
            $_POST['event_date'],
            !empty($_POST['time_slot_id']) ? $_POST['time_slot_id'] : null,
            $_POST['venue_equipment'],
            $_POST['description'],
            $_POST['inquiry_id'],
            $_SESSION['user_id']
        ]);

        // Update inquiry budget
        $stmt = $conn->prepare("
            UPDATE inquiry 
            SET budget = ?
            WHERE inquiry_id = ? AND client_id = ?
        ");
        $stmt->execute([
            $_POST['budget'],
            $_POST['inquiry_id'],
            $_SESSION['user_id']
        ]);

        // Update supporting document if a new one is uploaded
        if (!empty($_FILES['supporting_doc']['name'])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_name = time() . '_' . $_FILES['supporting_doc']['name'];
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $file_path)) {
                // Delete old document if exists
                $stmt = $conn->prepare("SELECT docs_id FROM inquiry WHERE inquiry_id = ?");
                $stmt->execute([$_POST['inquiry_id']]);
                $old_doc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($old_doc && $old_doc['docs_id']) {
                    $stmt = $conn->prepare("DELETE FROM supporting_document WHERE docs_id = ?");
                    $stmt->execute([$old_doc['docs_id']]);
                }

                // Insert new document
                $stmt = $conn->prepare("INSERT INTO supporting_document (doc_link) VALUES (?)");
                $stmt->execute(['uploads/' . $file_name]);
                $new_doc_id = $conn->lastInsertId();

                // Update inquiry with new document ID
                $stmt = $conn->prepare("UPDATE inquiry SET docs_id = ? WHERE inquiry_id = ?");
                $stmt->execute([$new_doc_id, $_POST['inquiry_id']]);
            }
        }

        // Delete old genres
        $stmt = $conn->prepare("DELETE FROM inquiry_genre WHERE inquiry_id = ?");
        $stmt->execute([$_POST['inquiry_id']]);

        // Insert new genres
        if (!empty($_POST['genres'])) {
            $stmt = $conn->prepare("INSERT INTO inquiry_genre (inquiry_id, genre_id) VALUES (?, ?)");
            foreach ($_POST['genres'] as $genre_id) {
                $stmt->execute([$_POST['inquiry_id'], $genre_id]);
            }
        }

        $conn->commit();
        $_SESSION['success'] = 'Inquiry updated successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = 'Error updating inquiry: ' . $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get client's inquiries with more details
try {
    $stmt = $conn->prepare("
        SELECT 
            i.inquiry_id,
            i.budget,
            i.inquiry_status,
            e.event_id,
            e.event_name,
            e.event_date,
            e.event_type,
            e.venue_equipment,
            l.address,
            l.city,
            ts.time as time_slot,
            e.description,
            GROUP_CONCAT(g.name) as genres,
            sd.doc_link as document_link
        FROM inquiry i
        JOIN event_table e ON i.event_id = e.event_id
        LEFT JOIN location l ON e.location_id = l.location_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        LEFT JOIN inquiry_genre ig ON i.inquiry_id = ig.inquiry_id
        LEFT JOIN genre g ON ig.genre_id = g.genre_id
        LEFT JOIN supporting_document sd ON i.docs_id = sd.docs_id
        WHERE i.client_id = ? AND i.inquiry_status != 'deleted by client'
        GROUP BY i.inquiry_id
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching inquiries: ' . $e->getMessage();
}

// Display success/error messages
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
    <title>Client Dashboard - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .inquiry-card {
            position: relative;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .edit-button {
            display: none; /* Hide edit button by default */
            background: #3498db;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .edit-button:hover {
            background: #2980b9;
        }

        .edit-button.visible {
            display: inline-block; /* Show edit button when visible class is added */
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .close-button {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .submit-button {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }

        .submit-button:hover {
            background: #2980b9;
        }

        .success-message {
            background: #2ecc71;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .error-message {
            background: #e74c3c;
            color: white;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .view-details-button {
            background: #2ecc71;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            margin-left: 10px;
        }

        .view-details-button:hover {
            background: #27ae60;
        }

        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .details-content {
            position: relative;
            background: white;
            width: 90%;
            max-width: 800px;
            margin: 50px auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .detail-section p {
            margin: 5px 0;
            color: #666;
        }

        .detail-section strong {
            color: #2c3e50;
        }

        .genre-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-top: 5px;
        }

        .genre-tag {
            background: #f0f0f0;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            color: #666;
        }

        .equipment-list {
            list-style: none;
            padding: 0;
            margin: 5px 0;
        }

        .equipment-item {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 4px;
            margin-bottom: 5px;
            font-size: 0.9em;
            color: #666;
        }

        .document-link {
            display: inline-block;
            padding: 5px 10px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 5px;
        }

        .document-link:hover {
            background: #2980b9;
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #f1c40f;
            color: #fff;
        }

        .status-badge.approved {
            background: #2ecc71;
            color: #fff;
        }

        .status-badge.rejected {
            background: #e74c3c;
            color: #fff;
        }

        .status-badge.completed {
            background: #3498db;
            color: #fff;
        }

        .status-badge.deleted-by-client {
            background: #95a5a6;
            color: #fff;
        }

        .action-buttons {
            margin-top: 20px;
            text-align: right;
        }

        .action-buttons button {
            margin-left: 10px;
        }

        .genre-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
            max-height: 150px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            padding: 5px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .checkbox-label:hover {
            background-color: #f8f9fa;
        }

        .checkbox-label input[type="checkbox"] {
            margin: 0;
        }

        .edit-form {
            max-height: calc(80vh - 100px);
            overflow-y: auto;
            padding-right: 10px;
        }

        .edit-form::-webkit-scrollbar {
            width: 8px;
        }

        .edit-form::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .edit-form::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .edit-form::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .edit-form .form-group {
            margin-bottom: 20px;
        }

        .edit-form label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
        }

        .edit-form input[type="text"],
        .edit-form input[type="date"],
        .edit-form input[type="number"],
        .edit-form select,
        .edit-form textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }

        .edit-form input[type="text"]:focus,
        .edit-form input[type="date"]:focus,
        .edit-form input[type="number"]:focus,
        .edit-form select:focus,
        .edit-form textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .edit-form .submit-container {
            position: sticky;
            bottom: 0;
            background: white;
            padding: 15px 0;
            margin-top: 20px;
            border-top: 1px solid #eee;
            text-align: right;
        }

        .edit-form .submit-container button {
            min-width: 120px;
        }

        .delete-button {
            background: #e74c3c;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background 0.3s;
            margin-left: 10px;
        }

        .delete-button:hover {
            background: #c0392b;
        }

        .delete-confirmation {
            max-width: 400px;
            text-align: center;
        }

        .delete-confirmation h2 {
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .delete-confirmation p {
            margin-bottom: 20px;
            color: #666;
        }

        .button-group {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        .btn.danger {
            background: #e74c3c;
            color: white;
        }

        .btn.danger:hover {
            background: #c0392b;
        }

        .btn.secondary {
            background: #95a5a6;
            color: white;
        }

        .btn.secondary:hover {
            background: #7f8c8d;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        #details_documents {
            margin-top: 10px;
        }

        #details_documents a {
            color: #3498db;
            text-decoration: none;
            display: inline-block;
            margin-right: 15px;
        }

        #details_documents a:hover {
            text-decoration: underline;
        }

        .genre-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
        }

        .checkbox-label {
            display: block;
            margin-bottom: 8px;
            cursor: pointer;
        }

        .checkbox-label:hover {
            background: #f7f9fc;
        }

        input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 5px;
        }

        small {
            color: #666;
            font-size: 0.9em;
            display: block;
            margin-top: 5px;
        }

        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
        }

        select:focus {
            border-color: #3498db;
            outline: none;
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
                <?php if(isset($_SESSION['user_type'])): ?>
                    <?php if($_SESSION['user_type'] == 'admin'): ?>
                        <li><a href="/tbcph/admin/dashboard.php">Admin Dashboard</a></li>
                    <?php elseif($_SESSION['user_type'] == 'busker'): ?>
                        <li><a href="/tbcph/busker/profile.php">My Profile</a></li>
                    <?php elseif($_SESSION['user_type'] == 'client'): ?>
                        <li><a href="/tbcph/client/dashboard.php">My Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="/tbcph/includes/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/tbcph/client/index.php">Client Login</a></li>
                    <li><a href="/tbcph/busker/index.php">Busker Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="dashboard-container">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h1>
            
            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="dashboard-actions">
                <a href="/tbcph/public/contact.php" class="btn primary">Book a Busker</a>
                <a href="profile.php" class="btn secondary">Edit Profile</a>
            </div>

            <section class="inquiries-section">
                <h2>Your Inquiries</h2>
                <?php if (empty($inquiries)): ?>
                    <p>You haven't made any inquiries yet.</p>
                <?php else: ?>
                    <div class="inquiries-grid">
                        <?php foreach ($inquiries as $inquiry): ?>
                            <div class="inquiry-card">
                                <div class="card-actions">
                                    <button class="view-details-button" onclick="openDetailsModal(<?php echo htmlspecialchars(json_encode($inquiry)); ?>)">
                                        View Details
                                    </button>
                                </div>
                                <h3><?php echo htmlspecialchars($inquiry['event_name']); ?></h3>
                                <p><strong>Event Type:</strong> <?php echo htmlspecialchars($inquiry['event_type']); ?></p>
                                <p><strong>Date:</strong> <?php echo date('F j, Y', strtotime($inquiry['event_date'])); ?></p>
                                <p><strong>Budget:</strong> ₱<?php echo number_format($inquiry['budget']); ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="status-badge <?php echo strtolower($inquiry['inquiry_status']); ?>">
                                        <?php echo ucfirst($inquiry['inquiry_status']); ?>
                                    </span>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <!-- Details Modal -->
    <div id="detailsModal" class="details-modal">
        <div class="details-content">
            <span class="close-button" onclick="closeDetailsModal()">&times;</span>
            <h2>Inquiry Details</h2>
            <div class="details-grid">
                <div class="detail-section">
                    <h3>Event Information</h3>
                    <p><strong>Event Name:</strong> <span id="details_event_name"></span></p>
                    <p><strong>Event Type:</strong> <span id="details_event_type"></span></p>
                    <p><strong>Event Date:</strong> <span id="details_event_date"></span></p>
                    <p><strong>Time Slot:</strong> <span id="details_time_slot"></span></p>
                </div>
                <div class="detail-section">
                    <h3>Location</h3>
                    <p><strong>Address:</strong> <span id="details_address"></span></p>
                    <p><strong>City:</strong> <span id="details_city"></span></p>
                </div>
                <div class="detail-section">
                    <h3>Budget and Status</h3>
                    <p><strong>Budget:</strong> ₱<span id="details_budget"></span></p>
                    <p><strong>Status:</strong> <span id="details_status" class="status-badge"></span></p>
                </div>
                <div class="detail-section">
                    <h3>Preferred Genres</h3>
                    <p id="details_genres"></p>
                </div>
                <div class="detail-section">
                    <h3>Venue Equipment</h3>
                    <p id="details_venue_equipment"></p>
                </div>
                <div class="detail-section">
                    <h3>Supporting Documents</h3>
                    <div id="details_documents"></div>
                </div>
            </div>
            <div class="action-buttons">
                <button class="edit-button" onclick="openEditModal()">Edit Details</button>
                <button class="delete-button" onclick="confirmDelete()">Delete Inquiry</button>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditModal()">&times;</span>
            <h2>Edit Inquiry Details</h2>
            <form method="POST" enctype="multipart/form-data" class="edit-form">
                <input type="hidden" name="inquiry_id" id="edit_inquiry_id">
                <input type="hidden" name="event_id" id="edit_event_id">
                <input type="hidden" name="update_inquiry" value="1">
                
                <div class="form-group">
                    <label for="edit_event_name">Event Name</label>
                    <input type="text" id="edit_event_name" name="event_name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_event_type">Event Type</label>
                    <input type="text" id="edit_event_type" name="event_type" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_event_date">Event Date</label>
                    <input type="date" id="edit_event_date" name="event_date" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_time_slot">Time Slot (Optional)</label>
                    <select id="edit_time_slot" name="time_slot_id">
                        <option value="">Keep Current Time Slot</option>
                        <?php
                        $stmt = $conn->query("SELECT time_slot_id, time FROM time_slot ORDER BY time");
                        while ($slot = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$slot['time_slot_id']}'>{$slot['time']}</option>";
                        }
                        ?>
                    </select>
                    <small>Leave as is to keep the current time slot</small>
                </div>
                
                <div class="form-group">
                    <label for="edit_budget">Budget (₱)</label>
                    <input type="number" id="edit_budget" name="budget" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_venue_equipment">Venue Equipment</label>
                    <textarea id="edit_venue_equipment" name="venue_equipment" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="edit_supporting_doc">Supporting Document</label>
                    <input type="file" id="edit_supporting_doc" name="supporting_doc" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                    <small>Leave empty to keep existing document</small>
                </div>
                
                <div class="form-group">
                    <label>Preferred Genres</label>
                    <div class="genre-checkboxes">
                        <?php
                        $stmt = $conn->query("SELECT genre_id, name FROM genre ORDER BY name");
                        while ($genre = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<label class='checkbox-label'>
                                    <input type='checkbox' name='genres[]' value='{$genre['genre_id']}'>
                                    {$genre['name']}
                                  </label>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn primary">Update Details</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add delete confirmation modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content delete-confirmation">
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this inquiry? This action cannot be undone.</p>
            <form method="POST" class="delete-form">
                <input type="hidden" name="inquiry_id" id="delete_inquiry_id">
                <input type="hidden" name="delete_inquiry" value="1">
                <div class="button-group">
                    <button type="button" class="btn secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Contact Us</h3>
                <p>Email: info@tbcph.com</p>
                <p>Phone: (123) 456-7890</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="/tbcph/public/about.php">About Us</a></li>
                    <li><a href="/tbcph/public/buskers.php">Our Buskers</a></li>
                    <li><a href="/tbcph/public/contact.php">Contact</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Follow Us</h3>
                <div class="social-links">
                    <a href="#">Facebook</a>
                    <a href="#">Instagram</a>
                    <a href="#">Twitter</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> The Busking Community PH. All rights reserved.</p>
        </div>
    </footer>

    <script>
        let currentInquiry = null;

        function openDetailsModal(inquiry) {
            currentInquiry = inquiry;
            const modal = document.getElementById('detailsModal');
            
            // Populate basic details
            document.getElementById('details_event_name').textContent = inquiry.event_name;
            document.getElementById('details_event_type').textContent = inquiry.event_type;
            document.getElementById('details_event_date').textContent = inquiry.event_date;
            document.getElementById('details_time_slot').textContent = inquiry.time_slot;
            document.getElementById('details_address').textContent = inquiry.address;
            document.getElementById('details_city').textContent = inquiry.city;
            document.getElementById('details_budget').textContent = inquiry.budget;
            document.getElementById('details_venue_equipment').textContent = inquiry.venue_equipment || 'None specified';
            
            // Set status with appropriate class
            const statusElement = document.getElementById('details_status');
            statusElement.textContent = inquiry.inquiry_status;
            statusElement.className = 'status-badge ' + inquiry.inquiry_status.toLowerCase().replace(/\s+/g, '-');
            
            // Display genres
            document.getElementById('details_genres').textContent = inquiry.genres || 'None selected';
            
            // Display supporting document
            const documentsDiv = document.getElementById('details_documents');
            if (inquiry.document_link) {
                documentsDiv.innerHTML = `<a href="../${inquiry.document_link}" target="_blank">View Document</a>`;
            } else {
                documentsDiv.innerHTML = 'No document uploaded';
            }
            
            // Show/hide edit and delete buttons based on status
            const editButton = document.querySelector('.edit-button');
            const deleteButton = document.querySelector('.delete-button');
            if (inquiry.inquiry_status.toLowerCase() === 'pending') {
                editButton.classList.add('visible');
                deleteButton.classList.add('visible');
            } else {
                editButton.classList.remove('visible');
                deleteButton.classList.remove('visible');
            }
            
            modal.style.display = 'block';
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            modal.style.display = 'none';
            currentInquiry = null;
        }

        function openEditModal() {
            if (!currentInquiry) return;
            
            const modal = document.getElementById('editModal');
            
            // Populate form fields
            document.getElementById('edit_inquiry_id').value = currentInquiry.inquiry_id;
            document.getElementById('edit_event_id').value = currentInquiry.event_id;
            document.getElementById('edit_event_name').value = currentInquiry.event_name;
            document.getElementById('edit_event_type').value = currentInquiry.event_type;
            document.getElementById('edit_event_date').value = currentInquiry.event_date;
            document.getElementById('edit_time_slot').value = currentInquiry.time_slot_id || '';
            document.getElementById('edit_budget').value = currentInquiry.budget;
            document.getElementById('edit_venue_equipment').value = currentInquiry.venue_equipment || '';
            document.getElementById('edit_description').value = currentInquiry.description || '';
            
            // Set selected genres
            const genres = currentInquiry.genres ? currentInquiry.genres.split(',') : [];
            document.querySelectorAll('input[name="genres[]"]').forEach(checkbox => {
                checkbox.checked = genres.includes(checkbox.nextSibling.textContent.trim());
            });
            
            modal.style.display = 'block';
            closeDetailsModal();
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.style.display = 'none';
        }

        function confirmDelete() {
            if (!currentInquiry) return;
            
            const modal = document.getElementById('deleteModal');
            document.getElementById('delete_inquiry_id').value = currentInquiry.inquiry_id;
            modal.style.display = 'block';
            closeDetailsModal();
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const detailsModal = document.getElementById('detailsModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == detailsModal) {
                closeDetailsModal();
            }
            if (event.target == editModal) {
                closeEditModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>  
