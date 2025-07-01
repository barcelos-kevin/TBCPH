<?php
session_start();
require_once '../includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['busker_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$email) {
        $error = 'Please enter a valid email address';
    } else {
        try {
            // Get busker from database
            $stmt = $conn->prepare("SELECT busker_id, name, email, password, status FROM busker WHERE email = ?");
            $stmt->execute([$email]);
            $busker = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($busker && password_verify($password, $busker['password'])) {
                if ($busker['status'] === 'active') {
                    // Set session variables
                    $_SESSION['busker_id'] = $busker['busker_id'];
                    $_SESSION['busker_name'] = $busker['name'];
                    $_SESSION['busker_email'] = $busker['email'];
                    $_SESSION['user_type'] = 'busker';

                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $error = 'Your account is not active. Please contact the administrator.';
                }
            } else {
                $error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $error = 'An error occurred. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Busker Login - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
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
                <img
                    src="/tbcph/assets/images/logo.jpg"
                    class="logo-img" />
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
                <h1>Busker Login</h1>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>

                    <button type="submit" class="btn primary">Login</button>
                </form>

                <div class="auth-links">
                    <a href="register.php">Don't have an account? Register here</a>
                    <a href="/tbcph/public/index.php">Back to Home</a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>  
