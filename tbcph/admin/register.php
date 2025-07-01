<?php
require_once '../includes/config.php';

// If already logged in as admin, redirect to dashboard
if (isset($_SESSION['admin_email'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$formData = [
    'email' => '',
    'password' => '',
    'confirm_password' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['email'] = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $formData['password'] = $_POST['password'];
    $formData['confirm_password'] = $_POST['confirm_password'];

    if (!$formData['email'] || empty($formData['password']) || empty($formData['confirm_password'])) {
        $error = 'Please fill in all required fields.';
    } elseif (strlen($formData['password']) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($formData['password'] !== $formData['confirm_password']) {
        $error = 'Passwords do not match.';
    } else {
        try {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ?");
            $stmt->execute([$formData['email']]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Email already registered.';
            } else {
                $hashed_password = password_hash($formData['password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO admin (email, password, account_level, status) VALUES (?, ?, 'admin', 'pending')");
                $stmt->execute([
                    $formData['email'],
                    $hashed_password
                ]);
                $success = 'Registration submitted! Awaiting super admin approval.';
                $formData = [
                    'email' => '',
                    'password' => '',
                    'confirm_password' => ''
                ];
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            error_log("Admin registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - TBCPH</title>
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
            max-width: 400px;
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
        .form-group input {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            border-color: #3498db;
            outline: none;
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
                <li><a href="/tbcph/admin/index.php">Admin Login</a></li>
            </ul>
        </nav>
    </header>
    <main>
        <div class="auth-container">
            <div class="auth-box">
                <h1>Admin Registration</h1>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($formData['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
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