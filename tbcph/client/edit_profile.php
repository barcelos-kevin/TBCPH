<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ' . SITE_URL . '/client/index.php');
    exit();
}

$client_id = $_SESSION['client_id'];
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update personal information
        if (isset($_POST['update_info'])) {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);

            // Validate email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format.");
            }

            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT client_id FROM client WHERE email = ? AND client_id != ?");
            $stmt->execute([$email, $client_id]);
            if ($stmt->rowCount() > 0) {
                throw new Exception("Email is already taken by another user.");
            }

            // Update client information
            $stmt = $conn->prepare("
                UPDATE client 
                SET name = ?, email = ?, phone = ?, address = ?
                WHERE client_id = ?
            ");
            $stmt->execute([$name, $email, $phone, $address, $client_id]);

            $_SESSION['success'] = "Profile information updated successfully.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }

        // Change password
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate passwords
            if (strlen($new_password) < 8) {
                throw new Exception("New password must be at least 8 characters long.");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match.");
            }

            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM client WHERE client_id = ?");
            $stmt->execute([$client_id]);
            $client = $stmt->fetch();

            if (!password_verify($current_password, $client['password'])) {
                throw new Exception("Current password is incorrect.");
            }

            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE client SET password = ? WHERE client_id = ?");
            $stmt->execute([$hashed_password, $client_id]);

            $_SESSION['success'] = "Password changed successfully.";
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit();
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Get client information
try {
    $stmt = $conn->prepare("SELECT * FROM client WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
} catch (PDOException $e) {
    $_SESSION['error'] = "Failed to fetch client information.";
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$page_title = "Edit Profile - " . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<main>
    <div class="container">
        <div class="back-button-container">
            <a href="profile.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>
        </div>
        <div class="edit-profile-container">
            <div class="form-header">
                <h1>Edit Profile</h1>
                <p>Update your personal information and password</p>
            </div>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Personal Information Form -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h2>Personal Information</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="edit-form">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-phone"></i>
                                        </span>
                                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="address">Address</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-map-marker-alt"></i>
                                        </span>
                                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="update_info" class="btn btn-primary">
                                        <i class="fas fa-save"></i>
                                        Update Information
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Change Password Form -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h2>Change Password</h2>
                        </div>
                        <div class="card-body">
                            <form method="POST" class="edit-form">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" id="current_password" name="current_password" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-key"></i>
                                        </span>
                                        <input type="password" id="new_password" name="new_password" required>
                                    </div>
                                    <small class="form-text">Password must be at least 8 characters long</small>
                                </div>

                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <div class="input-group">
                                        <span class="input-icon">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                        <input type="password" id="confirm_password" name="confirm_password" required>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="change_password" class="btn btn-primary">
                                        <i class="fas fa-key"></i>
                                        Change Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
/* Base Styles */
.edit-profile-page {
    background-color: #f8f9fa;
    min-height: 100vh;
    padding: 2rem 0;
}

/* Container */
.edit-profile-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Form Header */
.form-header {
    text-align: center;
    margin-bottom: 2rem;
}

.form-header h1 {
    color: #1a237e;
    font-size: 2rem;
    margin-bottom: 0.5rem;
}

.form-header p {
    color: #666;
    font-size: 1.1rem;
}

/* Cards */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    height: 100%;
}

.card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #eee;
    padding: 1.5rem;
    border-radius: 12px 12px 0 0;
}

.card-header h2 {
    color: #1a237e;
    font-size: 1.5rem;
    margin: 0;
}

.card-body {
    padding: 1.5rem;
}

/* Alerts */
.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
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

.alert i {
    font-size: 1.25rem;
}

/* Form Groups */
.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    color: #1a237e;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    color: #1a237e;
    display: flex;
    align-items: center;
}

.input-group input,
.input-group textarea {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s;
}

.input-group textarea {
    resize: vertical;
    min-height: 100px;
}

.input-group input:focus,
.input-group textarea:focus {
    outline: none;
    border-color: #1a237e;
    box-shadow: 0 0 0 2px rgba(26, 35, 126, 0.1);
}

.form-text {
    display: block;
    margin-top: 0.5rem;
    color: #666;
    font-size: 0.875rem;
}

/* Form Actions */
.form-actions {
    margin-top: 2rem;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    width: 100%;
    justify-content: center;
}

.btn-primary {
    background: #1a237e;
    color: white;
}

.btn-primary:hover {
    background: #0d47a1;
}

/* Responsive Design */
@media (max-width: 768px) {
    .edit-profile-page {
        padding: 1rem;
    }

    .card-header h2 {
        font-size: 1.25rem;
    }

    .form-header h1 {
        font-size: 1.75rem;
    }
}

.back-button-container {
    margin: 20px 0;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.back-button-container .btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background-color: #2c3e50;
    color: white;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1em;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.back-button-container .btn:hover {
    background-color: #34495e;
    transform: translateX(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.back-button-container .btn i {
    font-size: 1.2em;
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?> 
</html> 
</html> 