<?php
require_once '../includes/config.php';
include __DIR__ . '/../includes/header.php';

// Fetch featured buskers
try {
    $stmt = $conn->query("
        SELECT 
            b.*,
            GROUP_CONCAT(DISTINCT g.name SEPARATOR ', ') as genres
        FROM busker b
        LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
        LEFT JOIN genre g ON bg.genre_id = g.genre_id
        WHERE b.status = 'Active'
        GROUP BY b.busker_id, b.band_name, b.name, b.contact_number, 
                 b.address, b.birthday, b.has_equipment, b.status, 
                 b.password, b.email
        LIMIT 6
    ");
    $featured_buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $featured_buskers = [];
    // Log error but don't display to user
    error_log("Error fetching buskers: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TBCPH - The Busking Community PH</title>
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .hero {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                        url('/tbcph/assets/images/backgrounds/Busker_girl.jpg') center center/cover no-repeat;
            color: white;
            text-align: center;
            min-height: 100vh;
            width: 100vw;
            margin: 0;
            padding: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .hero-content {
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .cta {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)),
                        url('/tbcph/assets/images/backgrounds/Busker_boy.png') center center/cover no-repeat;
            color: #fff;
            text-align: center;
            padding: 80px 20px;
            border-radius: 0;
            margin-top: 0;
            position: relative;
            min-height: 60vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .cta h2, .cta p, .cta a {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <main>
        <div class="hero">
            <div class="hero-content">
                <h1>Welcome to The Busking Community PH</h1>
                <p>Connecting talented street performers with amazing opportunities</p>
                <div class="hero-buttons">
                    <a href="contact.php" class="btn primary">Book a Busker</a>
                    <a href="about.php" class="btn secondary">Learn More</a>
                </div>
            </div>
        </div>

        <section class="featured-buskers">
            <h2>Featured Buskers</h2>
            <div class="busker-grid">
                <?php if (!empty($featured_buskers)): ?>
                    <?php foreach ($featured_buskers as $busker): ?>
                        <div class="busker-card" data-genre="<?php echo htmlspecialchars($busker['genres'] ?? 'Not specified'); ?>">
                            <div class="busker-image">
                                <img src="<?php echo !empty($busker['profile_picture']) ? htmlspecialchars($busker['profile_picture']) : '/tbcph/assets/images/placeholder.jpg'; ?>" 
                                     alt="<?php echo htmlspecialchars($busker['band_name'] ?? 'Busker'); ?>">
                            </div>
                            <div class="busker-info">
                                <h3><?php echo htmlspecialchars($busker['band_name'] ?? 'Unknown Artist'); ?></h3>
                                <p>Genre: <?php echo htmlspecialchars($busker['genres'] ?? 'Not specified'); ?></p>
                                <a href="buskers.php?id=<?php echo $busker['busker_id']; ?>" class="btn">View Profile</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-buskers">
                        <p>No featured buskers available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="how-it-works">
            <h2>How It Works</h2>
            <div class="steps">
                <div class="step">
                    <div class="step-icon">1</div>
                    <h3>Browse Buskers</h3>
                    <p>Explore our talented community of street performers</p>
                </div>
                <div class="step">
                    <div class="step-icon">2</div>
                    <h3>Make an Inquiry</h3>
                    <p>Submit your event details and requirements</p>
                </div>
                <div class="step">
                    <div class="step-icon">3</div>
                    <h3>Book & Enjoy</h3>
                    <p>Confirm your booking and enjoy the performance</p>
                </div>
            </div>
        </section>

        <section class="cta">
            <h2>Ready to Book a Busker?</h2>
            <p>Join our community and discover amazing talent for your next event</p>
            <a href="contact.php" class="btn primary">Get Started</a>
        </section>
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

    <script src="/tbcph/assets/js/main.js"></script>
</body>
</html>  
