<?php
require_once '../includes/config.php';

// Check if already logged in
if (isLoggedIn() && getUserType() === 'client') {
    redirect('/client/dashboard.php');
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    
    try {
        $stmt = $conn->prepare("SELECT client_id, email, password_hash FROM client WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['client_id'];
            $_SESSION['user_type'] = 'client';
            redirect('/client/dashboard.php');
        } else {
            $error = 'Invalid email or password';
        }
    } catch(PDOException $e) {
        $error = 'Login failed. Please try again.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <link rel="stylesheet" href="/tbcph/assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h2>Client Login</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form id="loginForm" method="POST" action="" onsubmit="return validateForm('loginForm')">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn primary">Login</button>
            </form>
            
            <div class="auth-links">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="/tbcph/public/index.php">Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script src="/tbcph/assets/js/main.js"></script>
</body>
</html>  
