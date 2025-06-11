<?php
require_once '../includes/config.php';

// Check if user is logged in as client
if (!isset($_SESSION['client_id'])) {
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
            $_SESSION['client_id']
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
            $_SESSION['client_id']
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
            $_SESSION['client_id']
        ]);

        // Handle multiple supporting documents
        if (!empty($_FILES['supporting_docs']['name'][0])) {
            $upload_dir = __DIR__ . '/../uploads/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            foreach ($_FILES['supporting_docs']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['supporting_docs']['error'][$key] === UPLOAD_ERR_OK) {
                    $file_name = time() . '_' . $_FILES['supporting_docs']['name'][$key];
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($tmp_name, $file_path)) {
                        $stmt = $conn->prepare("INSERT INTO supporting_document (doc_link) VALUES (?)");
                        $stmt->execute(['uploads/' . $file_name]);
                        $new_doc_id = $conn->lastInsertId();

                        $stmt = $conn->prepare("INSERT INTO inquiry_document (inquiry_id, docs_id) VALUES (?, ?)");
                        $stmt->execute([$_POST['inquiry_id'], $new_doc_id]);
                    }
                }
            }
        }

        // Delete selected documents if any
        if (!empty($_POST['delete_docs'])) {
            foreach ($_POST['delete_docs'] as $doc_id) {
                $stmt = $conn->prepare("SELECT doc_link FROM supporting_document WHERE docs_id = ?");
                $stmt->execute([$doc_id]);
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($doc) {
                    $file_path = __DIR__ . '/../' . $doc['doc_link'];
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }

                    $stmt = $conn->prepare("DELETE FROM inquiry_document WHERE docs_id = ?");
                    $stmt->execute([$doc_id]);
                    $stmt = $conn->prepare("DELETE FROM supporting_document WHERE docs_id = ?");
                    $stmt->execute([$doc_id]);
                }
            }
        }

        // Update genres
        $stmt = $conn->prepare("DELETE FROM inquiry_genre WHERE inquiry_id = ?");
        $stmt->execute([$_POST['inquiry_id']]);

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
            GROUP_CONCAT(DISTINCT sd.docs_id) as doc_ids,
            GROUP_CONCAT(DISTINCT sd.doc_link) as doc_links
        FROM inquiry i
        JOIN event_table e ON i.event_id = e.event_id
        LEFT JOIN location l ON e.location_id = l.location_id
        LEFT JOIN time_slot ts ON e.time_slot_id = ts.time_slot_id
        LEFT JOIN inquiry_genre ig ON i.inquiry_id = ig.inquiry_id
        LEFT JOIN genre g ON ig.genre_id = g.genre_id
        LEFT JOIN inquiry_document id ON i.inquiry_id = id.inquiry_id
        LEFT JOIN supporting_document sd ON id.docs_id = sd.docs_id
        WHERE i.client_id = ? AND i.inquiry_status != 'deleted by client'
        GROUP BY i.inquiry_id
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$_SESSION['client_id']]);
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
        .dashboard-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.07);
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 24px;
        }

        .header-actions {
            display: flex;
            gap: 24px;
        }

        .btn-blue {
            background: #3498db;
            color: #fff;
            border: none;
            font-size: 1.2em;
            padding: 18px 36px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(52,152,219,0.08);
            transition: background 0.2s;
        }

        .btn-blue:hover, .btn-blue:focus {
            background: #217dbb;
            color: #fff;
        }

        .btn-green {
            background: #2ecc71;
            color: #fff;
            border: none;
            font-size: 1.2em;
            padding: 18px 36px;
            border-radius: 8px;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(46,204,113,0.08);
            transition: background 0.2s;
        }

        .btn-green:hover, .btn-green:focus {
            background: #219150;
            color: #fff;
        }

        .inquiries-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.07);
            border-radius: 8px;
            overflow: hidden;
        }

        .inquiries-table th,
        .inquiries-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
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
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.95em;
            font-weight: 500;
        }

        .status-badge.pending { background: #f1c40f; color: #fff; }
        .status-badge.approved { background: #2ecc71; color: #fff; }
        .status-badge.rejected { background: #e74c3c; color: #fff; }
        .status-badge.completed { background: #3498db; color: #fff; }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            background: #3498db;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            padding: 8px 24px;
            transition: background 0.2s;
        }

        .btn-view:hover, .btn-view:focus {
            background: #217dbb;
            color: #fff;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
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
        }

        .document-list {
            margin-top: 10px;
        }

        .document-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .document-item a {
            flex-grow: 1;
            color: #3498db;
            text-decoration: none;
        }

        .document-item a:hover {
            text-decoration: underline;
        }

        .delete-confirmation {
            max-width: 400px;
            text-align: center;
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .modal-action-btns {
            display: flex;
            gap: 16px;
            margin-top: 24px;
        }

        .btn-edit {
            background: #f39c12;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            padding: 10px 24px;
            font-size: 1em;
            transition: background 0.2s;
        }

        .btn-edit:hover, .btn-edit:focus {
            background: #c87f0a;
            color: #fff;
        }

        .btn-delete {
            background: #e74c3c;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            padding: 10px 24px;
            font-size: 1em;
            transition: background 0.2s;
        }

        .btn-delete:hover, .btn-delete:focus {
            background: #b71c1c;
            color: #fff;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <main>
        <div class="dashboard-container">
            <div class="dashboard-header">
                <div class="header-content">
                    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['client_name']); ?>!</h1>
                    <div class="header-actions">
                        <a href="/tbcph/public/contact.php" class="btn-blue">Book a Busker</a>
                        <a href="profile.php" class="btn-green">View Profile</a>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="inquiries-table">
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Event Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inquiries)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No inquiries found.</td>
                            </tr>
                <?php else: ?>
                        <?php foreach ($inquiries as $inquiry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($inquiry['event_name']); ?></td>
                                    <td><?php echo htmlspecialchars($inquiry['event_type']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($inquiry['event_date'])); ?></td>
                                    <td><?php echo $inquiry['time_slot'] ? date('g:i A', strtotime($inquiry['time_slot'])) : 'Not set'; ?></td>
                                    <td>₱<?php echo number_format($inquiry['budget']); ?></td>
                                    <td>
                                    <span class="status-badge <?php echo strtolower($inquiry['inquiry_status']); ?>">
                                        <?php echo ucfirst($inquiry['inquiry_status']); ?>
                                    </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-view" onclick="viewInquiry(<?php echo htmlspecialchars(json_encode($inquiry)); ?>)">View</button>
                            </div>
                                    </td>
                                </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                    </div>
        </div>
    </main>

    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeViewModal()">&times;</span>
            <h2>Inquiry Details</h2>
            <div id="viewContent"></div>
            <div id="modalActionBtns" class="modal-action-btns"></div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-button" onclick="closeEditModal()">&times;</span>
            <h2>Edit Inquiry</h2>
            <form id="editForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="update_inquiry" value="1">
                <input type="hidden" name="inquiry_id" id="edit_inquiry_id">
                <input type="hidden" name="event_id" id="edit_event_id">
                
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
                    <label for="edit_time_slot">Time Slot</label>
                    <select id="edit_time_slot" name="time_slot_id">
                        <option value="">Select Time Slot</option>
                        <?php
                        $stmt = $conn->query("SELECT time_slot_id, time FROM time_slot ORDER BY time");
                        while ($slot = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo "<option value='{$slot['time_slot_id']}'>{$slot['time']}</option>";
                        }
                        ?>
                    </select>
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

                <div class="form-group">
                    <label for="edit_supporting_docs">Supporting Documents</label>
                    <input type="file" id="edit_supporting_docs" name="supporting_docs[]" multiple>
                    <small>You can select multiple files. Leave empty to keep existing documents.</small>
                    
                    <div id="current_documents" class="document-list"></div>
                </div>

                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Inquiry</button>
            </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content delete-confirmation">
            <h2>Confirm Deletion</h2>
            <p>Are you sure you want to delete this inquiry? This action cannot be undone.</p>
            <form method="POST">
                <input type="hidden" name="delete_inquiry" value="1">
                <input type="hidden" name="inquiry_id" id="delete_inquiry_id">
                <div class="button-group">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script>
        function viewInquiry(inquiry) {
            const modal = document.getElementById('viewModal');
            const content = document.getElementById('viewContent');
            const actionBtns = document.getElementById('modalActionBtns');
            content.innerHTML = `
                <div class="detail-section">
                    <h3>Event Information</h3>
                    <p><strong>Event Name:</strong> ${inquiry.event_name}</p>
                    <p><strong>Event Type:</strong> ${inquiry.event_type}</p>
                    <p><strong>Event Date:</strong> ${inquiry.event_date}</p>
                    <p><strong>Time Slot:</strong> ${inquiry.time_slot || 'Not set'}</p>
                </div>
                <div class="detail-section">
                    <h3>Location</h3>
                    <p><strong>Address:</strong> ${inquiry.address}</p>
                    <p><strong>City:</strong> ${inquiry.city}</p>
                </div>
                <div class="detail-section">
                    <h3>Budget and Status</h3>
                    <p><strong>Budget:</strong> ₱${inquiry.budget}</p>
                    <p><strong>Status:</strong> <span class="status-badge ${inquiry.inquiry_status.toLowerCase()}">${inquiry.inquiry_status}</span></p>
                </div>
                <div class="detail-section">
                    <h3>Preferred Genres</h3>
                    <p>${inquiry.genres || 'None selected'}</p>
                </div>
                <div class="detail-section">
                    <h3>Venue Equipment</h3>
                    <p>${inquiry.venue_equipment || 'None specified'}</p>
                </div>
                <div class="detail-section">
                    <h3>Supporting Documents</h3>
                    <div class="document-list">
                        ${inquiry.doc_links ? inquiry.doc_links.split(',').map((link, index) => `
                            <div class="document-item">
                                <a href="../${link}" target="_blank">Document ${index + 1}</a>
                            </div>
                        `).join('') : 'No documents uploaded'}
                    </div>
                </div>
            `;
            // Show Edit/Delete if pending
            if (inquiry.inquiry_status.toLowerCase() === 'pending') {
                actionBtns.innerHTML = `
                    <button class="btn-edit" onclick='editInquiry(${JSON.stringify(inquiry)})'>Edit</button>
                    <button class="btn-delete" onclick='deleteInquiry(${inquiry.inquiry_id})'>Delete Inquiry</button>
                `;
            } else {
                actionBtns.innerHTML = '';
            }
            modal.style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function editInquiry(inquiry) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('editForm');
            
            // Populate form fields
            document.getElementById('edit_inquiry_id').value = inquiry.inquiry_id;
            document.getElementById('edit_event_id').value = inquiry.event_id;
            document.getElementById('edit_event_name').value = inquiry.event_name;
            document.getElementById('edit_event_type').value = inquiry.event_type;
            document.getElementById('edit_event_date').value = inquiry.event_date;
            document.getElementById('edit_time_slot').value = inquiry.time_slot_id || '';
            document.getElementById('edit_budget').value = inquiry.budget;
            document.getElementById('edit_venue_equipment').value = inquiry.venue_equipment || '';
            document.getElementById('edit_description').value = inquiry.description || '';
            
            // Set selected genres
            const genres = inquiry.genres ? inquiry.genres.split(',') : [];
            document.querySelectorAll('input[name="genres[]"]').forEach(checkbox => {
                checkbox.checked = genres.includes(checkbox.nextSibling.textContent.trim());
            });
            
            // Display current documents
            const currentDocsDiv = document.getElementById('current_documents');
            currentDocsDiv.innerHTML = '';
            
            if (inquiry.doc_links) {
                const docLinks = inquiry.doc_links.split(',');
                const docIds = inquiry.doc_ids.split(',');
                
                docLinks.forEach((link, index) => {
                    const docItem = document.createElement('div');
                    docItem.className = 'document-item';
                    docItem.innerHTML = `
                        <a href="../${link}" target="_blank">Document ${index + 1}</a>
                        <label class="delete-doc">
                            <input type="checkbox" name="delete_docs[]" value="${docIds[index]}">
                            Delete
                        </label>
                    `;
                    currentDocsDiv.appendChild(docItem);
                });
            } else {
                currentDocsDiv.innerHTML = 'No documents uploaded';
            }
            
            modal.style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function deleteInquiry(inquiryId) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('delete_inquiry_id').value = inquiryId;
            modal.style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == viewModal) {
                closeViewModal();
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
