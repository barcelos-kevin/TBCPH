<?php
require_once '../includes/config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_email'])) {
    header('Location: index.php');
    exit();
}

$success = $error = '';
$email = $_SESSION['admin_email'];

try {
    // Get admin details
    $stmt = $conn->prepare("SELECT * FROM admin WHERE email = ?");
    $stmt->execute([$email]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        header('Location: index.php');
        exit();
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_email = trim($_POST['email']);
        
        // Validate email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid email address.";
        } else {
            // Check if email is already taken by another admin
            $stmt = $conn->prepare("SELECT COUNT(*) FROM admin WHERE email = ? AND email != ?");
            $stmt->execute([$new_email, $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "This email is already registered.";
            } else {
                // Update admin email
                $stmt = $conn->prepare("UPDATE admin SET email = ? WHERE email = ?");
                if ($stmt->execute([$new_email, $email])) {
                    $_SESSION['admin_email'] = $new_email;
                    $success = "Profile updated successfully.";
                    $email = $new_email;
                } else {
                    $error = "Failed to update profile.";
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("Edit profile error: " . $e->getMessage());
    $error = "An error occurred while updating the profile.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - TBCPH</title>
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .edit-profile-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }

        .edit-profile-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1em;
        }

        .form-input:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #2c3e50;
            color: white;
        }

        .btn-secondary:hover {
            background: #1a252f;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .back-button:hover {
            color: #3498db;
        }

        @media (max-width: 768px) {
            .edit-profile-container {
                padding: 15px;
            }

            .edit-profile-form {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
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
                <?php if(isset($_SESSION['admin_email'])): ?>
                    <li><a href="/tbcph/admin/dashboard.php">Admin Dashboard</a></li>
                    <li><a href="/tbcph/admin/profile.php">My Profile</a></li>
                    <li><a href="/tbcph/includes/logout.php?type=admin">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <div class="edit-profile-container">
            <a href="profile.php" class="back-button">
                ← Back to Profile
            </a>

            <div class="edit-profile-form">
                <h1 class="form-title">Edit Profile</h1>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="profile.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html> 