<?php
require_once '../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) {
            throw new Exception('Invalid email address');
        }

        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM busker WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Email already registered');
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert busker
        $stmt = $conn->prepare("
            INSERT INTO busker (
                band_name, name, contact_number, address, birthday,
                has_equipment, status, password, email
            ) VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
        ");
        
        $stmt->execute([
            $_POST['band_name'],
            $_POST['name'],
            $_POST['contact_number'],
            $_POST['address'],
            $_POST['birthday'],
            isset($_POST['has_equipment']) ? 1 : 0,
            password_hash($_POST['password'], PASSWORD_DEFAULT),
            $email
        ]);
        
        $busker_id = $conn->lastInsertId();

        // Insert genres
        if (!empty($_POST['genres'])) {
            $stmt = $conn->prepare("
                INSERT INTO busker_genre (busker_id, genre_id)
                VALUES (?, ?)
            ");
            foreach ($_POST['genres'] as $genre_id) {
                $stmt->execute([$busker_id, $genre_id]);
            }
        }

        // Insert equipment if provided
        if (isset($_POST['has_equipment']) && !empty($_POST['equipment'])) {
            $stmt = $conn->prepare("
                INSERT INTO busker_equipment (busker_id, equipment_name, quantity, eq_condition)
                VALUES (?, ?, ?, ?)
            ");
            foreach ($_POST['equipment'] as $equipment) {
                if (!empty($equipment['name'])) {
                    $stmt->execute([
                        $busker_id,
                        $equipment['name'],
                        $equipment['quantity'] ?? '1',
                        $equipment['condition'] ?? 'Good'
                    ]);
                }
            }
        }

        $conn->commit();
        $success = 'Registration successful! Please wait for admin approval.';
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
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
    <title>Register as Busker - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
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
        <div class="register-container">
            <h1>Register as Busker</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" class="register-form">
                <div class="form-group">
                    <label for="band_name">Band/Artist Name</label>
                    <input type="text" id="band_name" name="band_name" required>
                </div>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="contact_number">Contact Number</label>
                    <input type="tel" id="contact_number" name="contact_number" required>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" required></textarea>
                </div>

                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" id="birthday" name="birthday" required>
                </div>

                <div class="form-group">
                    <label>Genres</label>
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
                    <label class="checkbox-label">
                        <input type="checkbox" id="has_equipment" name="has_equipment">
                        I have my own equipment
                    </label>
                </div>

                <div id="equipment-section" style="display: none;">
                    <h3>Equipment Details</h3>
                    <div id="equipment-list">
                        <div class="equipment-item">
                            <div class="form-group">
                                <label>Equipment Name</label>
                                <input type="text" name="equipment[0][name]">
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="number" name="equipment[0][quantity]" value="1" min="1">
                            </div>
                            <div class="form-group">
                                <label>Condition</label>
                                <select name="equipment[0][condition]">
                                    <option value="Excellent">Excellent</option>
                                    <option value="Good">Good</option>
                                    <option value="Fair">Fair</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="button" id="add-equipment" class="btn secondary">Add More Equipment</button>
                </div>

                <button type="submit" class="btn primary">Register</button>
            </form>
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
        // Show/hide equipment section
        document.getElementById('has_equipment').addEventListener('change', function() {
            document.getElementById('equipment-section').style.display = 
                this.checked ? 'block' : 'none';
        });

        // Add equipment fields
        let equipmentCount = 1;
        document.getElementById('add-equipment').addEventListener('click', function() {
            const equipmentList = document.getElementById('equipment-list');
            const newEquipment = document.createElement('div');
            newEquipment.className = 'equipment-item';
            newEquipment.innerHTML = `
                <div class="form-group">
                    <label>Equipment Name</label>
                    <input type="text" name="equipment[${equipmentCount}][name]">
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="equipment[${equipmentCount}][quantity]" value="1" min="1">
                </div>
                <div class="form-group">
                    <label>Condition</label>
                    <select name="equipment[${equipmentCount}][condition]">
                        <option value="Excellent">Excellent</option>
                        <option value="Good">Good</option>
                        <option value="Fair">Fair</option>
                    </select>
                </div>
                <button type="button" class="btn secondary remove-equipment">Remove</button>
            `;
            equipmentList.appendChild(newEquipment);
            equipmentCount++;

            // Add remove functionality
            newEquipment.querySelector('.remove-equipment').addEventListener('click', function() {
                equipmentList.removeChild(newEquipment);
            });
        });
    </script>
</body>
</html>  
