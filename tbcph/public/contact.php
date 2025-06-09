<?php
require_once '../includes/config.php';

$busker_id = isset($_GET['busker_id']) ? (int)$_GET['busker_id'] : null;
$error = '';
$success = '';
$busker = null;

// Fetch busker details if busker_id is provided
if ($busker_id) {
    try {
        $stmt = $conn->prepare("SELECT band_name FROM busker WHERE busker_id = ? AND status = 'Active'");
        $stmt->execute([$busker_id]);
        $busker = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Error fetching busker: " . $e->getMessage());
        $busker = null;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client') {
        $error = 'Please log in as a client to submit an inquiry.';
    } else {
        try {
            $conn->beginTransaction();

            // Handle custom location
            $location_id = null;
            if ($_POST['location_type'] === 'custom') {
                // Insert custom location
                $stmt = $conn->prepare("
                    INSERT INTO location (address, city, is_custom)
                    VALUES (?, ?, 1)
                ");
                $stmt->execute([
                    $_POST['custom_address'],
                    $_POST['custom_city']
                ]);
                $location_id = $conn->lastInsertId();
            } else {
                $location_id = $_POST['location'];
            }

            // Insert event details
            $stmt = $conn->prepare("
                INSERT INTO event_table (
                    event_name, event_type, event_date, time_slot_id, 
                    location_id, venue_equipment, description
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_POST['event_name'],
                $_POST['event_type'],
                $_POST['event_date'],
                $_POST['time_slot'],
                $_POST['location_type'] === 'existing' ? $_POST['location'] : $location_id,
                $_POST['venue_equipment'],
                $_POST['description']
            ]);
            
            $eventId = $conn->lastInsertId();

            // Handle supporting document upload
            $docId = null;
            if (isset($_FILES['supporting_doc']) && $_FILES['supporting_doc']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/documents/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . '_' . basename($_FILES['supporting_doc']['name']);
                $uploadFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['supporting_doc']['tmp_name'], $uploadFile)) {
                    $stmt = $conn->prepare("INSERT INTO supporting_document (doc_link) VALUES (?)");
                    $stmt->execute(['/tbcph/uploads/documents/' . $fileName]);
                    $docId = $conn->lastInsertId();
                }
            }

            // Insert inquiry
            $stmt = $conn->prepare("
                INSERT INTO inquiry (
                    client_id, event_id, budget, inquiry_status, docs_id
                ) VALUES (?, ?, ?, 'pending', ?)
            ");
            
            $stmt->execute([
                $_SESSION['user_id'],
                $eventId,
                $_POST['budget'],
                $docId
            ]);
            
            $inquiryId = $conn->lastInsertId();

            // Insert genres
            if (!empty($_POST['genres'])) {
                $stmt = $conn->prepare("
                    INSERT INTO inquiry_genre (inquiry_id, genre_id) 
                    VALUES (?, ?)
                ");
                
                foreach ($_POST['genres'] as $genreId) {
                    $stmt->execute([$inquiryId, $genreId]);
                }
            }

            // If specific busker was selected, create hire record
            if ($busker_id) {
                $stmt = $conn->prepare("
                    INSERT INTO hire (inquiry_id, busker_id, payment_status)
                    VALUES (?, ?, 'Pending')
                ");
                $stmt->execute([$inquiryId, $busker_id]);
            }

            $conn->commit();
            $success = 'Your inquiry has been submitted successfully! We will contact you shortly.';
        } catch(PDOException $e) {
            $conn->rollBack();
            error_log("Error submitting inquiry: " . $e->getMessage());
            $error = 'An error occurred while submitting your inquiry. Please try again.';
        }
    }
}

// Fetch available time slots
try {
    $stmt = $conn->query("SELECT * FROM time_slot ORDER BY time");
    $time_slots = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $time_slots = [];
}

// Fetch available locations
try {
    $stmt = $conn->query("SELECT * FROM location WHERE is_custom = 0");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $locations = [];
}

// Fetch available genres
try {
    $stmt = $conn->query("SELECT * FROM genre");
    $genres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $genres = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $busker ? "Book " . htmlspecialchars($busker['band_name']) : "Book a Busker"; ?> - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .location-options {
            margin-bottom: 20px;
        }

        .location-options label {
            margin-right: 20px;
            cursor: pointer;
        }

        .custom-location-fields {
            display: none;
            margin-top: 15px;
            padding: 15px;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .custom-location-fields.active {
            display: block;
        }

        .form-group input[type="text"],
        .form-group input[type="date"],
        .form-group input[type="number"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-group input[type="text"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="number"]:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .location-search {
            margin-bottom: 10px;
        }

        .search-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            margin-bottom: 10px;
        }

        .search-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        #location {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background-color: white;
        }

        #location option {
            padding: 8px;
        }

        #location option:checked {
            background-color: #3498db;
            color: white;
        }

        .no-results {
            padding: 10px;
            color: #666;
            font-style: italic;
            text-align: center;
            display: none;
        }

        .location-search-container {
            position: relative;
            margin-bottom: 20px;
        }

        .search-input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            background-color: white;
        }

        .search-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
        }

        .location-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .location-item {
            padding: 10px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .location-item:hover {
            background-color: #f5f5f5;
        }

        .location-item.selected {
            background-color: #3498db;
            color: white;
        }

        .no-results {
            padding: 12px;
            color: #666;
            font-style: italic;
            text-align: center;
        }

        /* Custom scrollbar for the dropdown */
        .location-dropdown::-webkit-scrollbar {
            width: 8px;
        }

        .location-dropdown::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .location-dropdown::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .location-dropdown::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f8f9fa;
        }

        .form-group small.form-text {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.9em;
        }

        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            min-height: 100px;
        }

        textarea:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
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
                    <li><a href="/tbcph/client/register.php">Register</a></li>
                    <li><a href="/tbcph/client/index.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="contact-container">
            <h1><?php echo $busker ? "Book " . htmlspecialchars($busker['band_name']) : "Book a Busker"; ?></h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'client'): ?>
                <div class="login-prompt">
                    <p>Please log in as a client to submit an inquiry.</p>
                    <a href="/tbcph/client/index.php" class="btn primary">Client Login</a>
                </div>
            <?php else: ?>
                <form method="POST" class="inquiry-form" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="event_name">Event Name</label>
                        <input type="text" id="event_name" name="event_name" required>
                    </div>

                    <div class="form-group">
                        <label for="event_type">Event Type</label>
                        <select id="event_type" name="event_type" required>
                            <option value="">Select Event Type</option>
                            <option value="Wedding">Wedding</option>
                            <option value="Corporate">Corporate</option>
                            <option value="Birthday">Birthday</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="event_date">Event Date</label>
                        <input type="date" id="event_date" name="event_date" required>
                    </div>

                    <div class="form-group">
                        <label for="time_slot">Time Slot</label>
                        <select id="time_slot" name="time_slot" required>
                            <option value="">Select Time Slot</option>
                            <?php foreach ($time_slots as $slot): ?>
                                <option value="<?php echo $slot['time_slot_id']; ?>">
                                    <?php echo date('g:i A', strtotime($slot['time'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location Type</label>
                        <div class="location-options">
                            <label>
                                <input type="radio" name="location_type" value="existing" checked>
                                Known Location
                            </label>
                            <label>
                                <input type="radio" name="location_type" value="custom">
                                Enter New Location
                            </label>
                        </div>

                        <div id="existing-location">
                            <div class="location-search-container">
                                <input type="text" id="locationSearch" placeholder="Search or select a location..." class="search-input" autocomplete="off">
                                <input type="hidden" id="selected_location_id" name="location">
                                <div id="locationDropdown" class="location-dropdown"></div>
                            </div>
                        </div>

                        <div id="custom-location" class="custom-location-fields">
                            <div class="form-group">
                                <label for="custom_address">Address</label>
                                <input type="text" id="custom_address" name="custom_address" placeholder="Enter complete address">
                            </div>
                            <div class="form-group">
                                <label for="custom_city">City</label>
                                <input type="text" id="custom_city" name="custom_city" placeholder="Enter city">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="budget">Budget (PHP)</label>
                        <input type="number" id="budget" name="budget" min="0" required>
                    </div>

                    <div class="form-group">
                        <label>Preferred Genres</label>
                        <div class="genre-checkboxes">
                            <?php foreach ($genres as $genre): ?>
                                <label class="checkbox-label">
                                    <input type="checkbox" name="genres[]" value="<?php echo $genre['genre_id']; ?>">
                                    <?php echo htmlspecialchars($genre['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Additional Details</label>
                        <textarea id="description" name="description" rows="4" placeholder="Enter any additional details about your event..."></textarea>
                    </div>

                    <div class="form-group">
                        <label for="venue_equipment">Venue Equipment</label>
                        <textarea id="venue_equipment" name="venue_equipment" rows="3" 
                                  placeholder="List any equipment available at the venue (e.g., PA system, microphones, stage lighting)"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="supporting_doc">Supporting Documents</label>
                        <input type="file" id="supporting_doc" name="supporting_doc" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <small class="form-text">Upload any relevant documents (contracts, permits, etc.)</small>
                    </div>

                    <button type="submit" class="btn primary">Submit Inquiry</button>
                </form>
            <?php endif; ?>
        </div>
    </main>

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
        document.addEventListener('DOMContentLoaded', function() {
            const locationTypeRadios = document.querySelectorAll('input[name="location_type"]');
            const existingLocation = document.getElementById('existing-location');
            const customLocation = document.getElementById('custom-location');
            const locationSearch = document.getElementById('locationSearch');
            const locationDropdown = document.getElementById('locationDropdown');
            const selectedLocationId = document.getElementById('selected_location_id');
            const customAddress = document.getElementById('custom_address');
            const customCity = document.getElementById('custom_city');

            // Store all locations
            const locations = <?php echo json_encode($locations); ?>;
            let selectedLocation = null;

            function createLocationItem(location) {
                const div = document.createElement('div');
                div.className = 'location-item';
                div.textContent = `${location.address}, ${location.city}`;
                div.dataset.id = location.location_id;
                div.dataset.address = location.address;
                div.dataset.city = location.city;

                div.addEventListener('click', () => {
                    selectLocation(location);
                });

                return div;
            }

            function selectLocation(location) {
                selectedLocation = location;
                locationSearch.value = `${location.address}, ${location.city}`;
                selectedLocationId.value = location.location_id;
                locationDropdown.style.display = 'none';
            }

            function filterLocations(searchTerm) {
                locationDropdown.innerHTML = '';
                const searchLower = searchTerm.toLowerCase();
                let hasResults = false;

                locations.forEach(location => {
                    const address = location.address.toLowerCase();
                    const city = location.city.toLowerCase();

                    if (address.includes(searchLower) || city.includes(searchLower)) {
                        const item = createLocationItem(location);
                        locationDropdown.appendChild(item);
                        hasResults = true;
                    }
                });

                if (!hasResults) {
                    const noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = 'No locations found';
                    locationDropdown.appendChild(noResults);
                }

                locationDropdown.style.display = 'block';
            }

            // Search input event listeners
            locationSearch.addEventListener('input', function() {
                filterLocations(this.value);
            });

            locationSearch.addEventListener('focus', function() {
                if (this.value) {
                    filterLocations(this.value);
                } else {
                    filterLocations('');
                }
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!locationSearch.contains(e.target) && !locationDropdown.contains(e.target)) {
                    locationDropdown.style.display = 'none';
                }
            });

            function updateLocationFields() {
                const selectedType = document.querySelector('input[name="location_type"]:checked').value;
                
                if (selectedType === 'existing') {
                    existingLocation.style.display = 'block';
                    customLocation.classList.remove('active');
                    selectedLocationId.required = true;
                    customAddress.required = false;
                    customCity.required = false;
                    // Clear search when switching to custom
                    locationSearch.value = '';
                    selectedLocationId.value = '';
                    selectedLocation = null;
                    locationDropdown.style.display = 'none';
                } else {
                    existingLocation.style.display = 'none';
                    customLocation.classList.add('active');
                    selectedLocationId.required = false;
                    customAddress.required = true;
                    customCity.required = true;
                }
            }

            locationTypeRadios.forEach(radio => {
                radio.addEventListener('change', updateLocationFields);
            });

            // Initial state
            updateLocationFields();

            // Add file size validation
            document.getElementById('supporting_doc').addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    if (file.size > maxSize) {
                        alert('File size must be less than 5MB');
                        e.target.value = '';
                    }
                }
            });
        });
    </script>
</body>
</html> 
