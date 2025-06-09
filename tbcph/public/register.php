<?php
require_once __DIR__ . '/../includes/config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate input
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All required fields must be filled out.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT client_id FROM client WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email is already registered.";
            } else {
                // Insert new client
                $stmt = $conn->prepare("
                    INSERT INTO client (name, email, phone, address, password) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$name, $email, $phone, $address, $hashed_password]);

                $success = "Registration successful! You can now login.";
            }
        } catch (PDOException $e) {
            $error = "Registration failed. Please try again.";
        }
    }
}

$page_title = "Register - " . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="register-page">
    <div class="container">
        <div class="register-form-container">
            <div class="form-header">
                <h1>Create an Account</h1>
                <p>Join us to start booking buskers for your events</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="register-form">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </span>
                        <input type="email" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-phone"></i>
                        </span>
                        <input type="tel" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </span>
                        <textarea id="address" name="address" rows="3"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <small class="form-text">Password must be at least 8 characters long</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-icon">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Register
                    </button>
                </div>

                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Base Styles */
.register-page {
    background-color: #f8f9fa;
    min-height: 100vh;
    padding: 2rem 0;
}

/* Form Container */
.register-form-container {
    max-width: 600px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
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

/* Form Footer */
.form-footer {
    text-align: center;
    margin-top: 1.5rem;
    color: #666;
}

.form-footer a {
    color: #1a237e;
    text-decoration: none;
    font-weight: 500;
}

.form-footer a:hover {
    text-decoration: underline;
}

/* Responsive Design */
@media (max-width: 768px) {
    .register-page {
        padding: 1rem;
    }

    .register-form-container {
        padding: 1.5rem;
    }

    .form-header h1 {
        font-size: 1.75rem;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?> 