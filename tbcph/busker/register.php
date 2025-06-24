<?php
require_once '../includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['busker_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'birthday' => '',
    'has_equipment' => false,
    'band_name' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $formData = [
        'name' => htmlspecialchars(trim($_POST['name']), ENT_QUOTES, 'UTF-8'),
        'email' => filter_var($_POST['email'], FILTER_VALIDATE_EMAIL),
        'phone' => htmlspecialchars(trim($_POST['phone']), ENT_QUOTES, 'UTF-8'),
        'address' => htmlspecialchars(trim($_POST['address']), ENT_QUOTES, 'UTF-8'),
        'birthday' => htmlspecialchars(trim($_POST['birthday']), ENT_QUOTES, 'UTF-8'),
        'has_equipment' => isset($_POST['has_equipment']),
        'band_name' => htmlspecialchars(trim($_POST['band_name']), ENT_QUOTES, 'UTF-8')
    ];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate form data
    if (empty($formData['name']) || empty($formData['email']) || empty($password) || empty($confirm_password) || empty($formData['birthday'])) {
        $error = 'Please fill in all required fields';
    } elseif (!$formData['email']) {
        $error = 'Please enter a valid email address';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM busker WHERE email = ?");
            $stmt->execute([$formData['email']]);
        if ($stmt->fetchColumn() > 0) {
                $error = 'Email already registered';
            } else {
                // Insert new busker
        $stmt = $conn->prepare("
                    INSERT INTO busker (name, email, contact_number, address, birthday, has_equipment, status, password, band_name)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?)
        ");
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt->execute([
                    $formData['name'],
                    $formData['email'],
                    $formData['phone'],
                    $formData['address'],
                    $formData['birthday'],
                    $formData['has_equipment'],
                    $hashed_password,
                    $formData['band_name']
        ]);
        
                $success = 'Registration successful! Please wait for admin approval.';
                $formData = [
                    'name' => '',
                    'email' => '',
                    'phone' => '',
                    'address' => '',
                    'birthday' => '',
                    'has_equipment' => false,
                    'band_name' => ''
                ];
                }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            error_log("Registration error: " . $e->getMessage());
    }
}
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Registration - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .auth-container {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .auth-box {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }

        .auth-box h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 30px;
            font-size: 2em;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            color: #2c3e50;
            font-weight: 500;
            font-size: 0.9em;
        }

        .form-group input,
        .form-group textarea {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #3498db;
            outline: none;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
        }

        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
        }

        .success-message {
            background: #dcfce7;
            color: #16a34a;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.9em;
            text-align: center;
        }

        .btn.primary {
            background: #2c3e50;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn.primary:hover {
            background: #1a252f;
            transform: translateY(-2px);
        }

        .auth-links {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            text-align: center;
        }

        .auth-links a {
            color: #3498db;
            text-decoration: none;
            font-size: 0.9em;
            transition: color 0.3s ease;
        }

        .auth-links a:hover {
            color: #2980b9;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .auth-box {
                padding: 30px 20px;
            }
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
                <?php if(isset($_SESSION['busker_id'])): ?>
                    <li><a href="/tbcph/busker/dashboard.php">My Dashboard</a></li>
                        <li><a href="/tbcph/busker/profile.php">My Profile</a></li>
                    <li><a href="/tbcph/includes/logout.php?type=busker">Logout</a></li>
                <?php elseif(isset($_SESSION['client_id'])): ?>
                        <li><a href="/tbcph/client/dashboard.php">My Dashboard</a></li>
                    <li><a href="/tbcph/client/profile.php">My Profile</a></li>
                    <li><a href="/tbcph/includes/logout.php?type=client">Logout</a></li>
                <?php elseif(isset($_SESSION['admin_email'])): ?>
                    <li><a href="/tbcph/admin/dashboard.php">Admin Dashboard</a></li>
                    <li><a href="/tbcph/includes/logout.php?type=admin">Logout</a></li>
                <?php else: ?>
                    <li><a href="/tbcph/busker/register.php">Register as Busker</a></li>
                    <li><a href="/tbcph/busker/index.php">Busker Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="auth-container">
            <div class="auth-box">
                <h1>Busker Registration</h1>
            
            <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-row">
                <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($formData['name']); ?>" required>
                </div>

                <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                        </div>
                </div>

                    <div class="form-row">
                <div class="form-group">
                            <label for="phone">Contact Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                </div>

                <div class="form-group">
                            <label for="birthday">Birthday</label>
                            <input type="date" id="birthday" name="birthday" value="<?php echo htmlspecialchars($formData['birthday']); ?>" required>
                        </div>
                </div>

                <div class="form-group">
                        <label for="band_name">Band Name (if applicable)</label>
                        <input type="text" id="band_name" name="band_name" value="<?php echo htmlspecialchars($formData['band_name']); ?>">
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                        <textarea id="address" name="address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                </div>

                <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="has_equipment" name="has_equipment" <?php echo $formData['has_equipment'] ? 'checked' : ''; ?>>
                            <label for="has_equipment">I have my own equipment</label>
                </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                            <label for="confirm_password">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                </div>

                <button type="submit" class="btn primary">Register</button>
            </form>

                <div class="auth-links">
                    <a href="index.php">Already have an account? Login here</a>
                    <a href="/tbcph/public/index.php">Back to Home</a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>  
