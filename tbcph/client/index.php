<?php
require_once '../includes/config.php';

// Check if user is already logged in
if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'client') {
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
            // Get client from database
            $stmt = $conn->prepare("SELECT client_id, name, email, password FROM client WHERE email = ?");
            $stmt->execute([$email]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client && password_verify($password, $client['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $client['client_id'];
                $_SESSION['user_type'] = 'client';
                $_SESSION['user_name'] = $client['name'];
                $_SESSION['user_email'] = $client['email'];

                // Redirect to dashboard
                header('Location: dashboard.php');
                exit();
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
    <title>Client Login - TBCPH</title>
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
        <div class="auth-container">
            <div class="auth-box">
                <h1>Client Login</h1>
                
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" class="auth-form" novalidate>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($email); ?>"
                            required 
                            autocomplete="email"
                            placeholder="Enter your email"
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="Enter your password"
                            minlength="8"
                        >
                    </div>

                    <button type="submit" class="btn primary">Sign In</button>
                </form>

                <div class="auth-links">
                    <a href="register.php">Don't have an account? Register here</a>
                    <a href="/tbcph/public/index.php">Back to Home</a>
                </div>
            </div>
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
        // Form validation
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            let isValid = true;
            let errorMessage = '';

            if (!email) {
                errorMessage = 'Please enter your email address';
                isValid = false;
            } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errorMessage = 'Please enter a valid email address';
                isValid = false;
            }

            if (!password) {
                errorMessage = 'Please enter your password';
                isValid = false;
            } else if (password.length < 8) {
                errorMessage = 'Password must be at least 8 characters long';
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                const errorDiv = document.querySelector('.error-message') || document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = errorMessage;
                
                if (!document.querySelector('.error-message')) {
                    document.querySelector('.auth-box').insertBefore(errorDiv, document.querySelector('.auth-form'));
                }
            }
        });
    </script>
</body>
</html>  
