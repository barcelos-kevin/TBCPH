<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TBCPH - The Busking Community PH</title>
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
</body>
</html>  
