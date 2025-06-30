<?php
require_once __DIR__ . '/../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include __DIR__ . '/../includes/header.php';

// Fetch all active buskers with their genres and equipment
$query = "SELECT b.*, 
          GROUP_CONCAT(DISTINCT g.name) as genres,
          GROUP_CONCAT(DISTINCT be.equipment_name) as equipment
          FROM busker b
          LEFT JOIN busker_genre bg ON b.busker_id = bg.busker_id
          LEFT JOIN genre g ON bg.genre_id = g.genre_id
          LEFT JOIN busker_equipment be ON b.busker_id = be.busker_id
          WHERE b.status = 'active'
          GROUP BY b.busker_id";

$stmt = $conn->query($query);
$buskers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all genres for filter
$genres_query = "SELECT DISTINCT name FROM genre ORDER BY name";
$genres_stmt = $conn->query($genres_query);
$genres = $genres_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Buskers - The Busking Community PH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2ecc71;
            --dark-color: #2c3e50;
            --light-color: #ecf0f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header Styles */
        header {
            background-color: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.4)),
                        url('/tbcph/assets/images/backgrounds/OldMan_Busker.png') center center/cover no-repeat;
            color: white;
            text-align: center;
            min-height: 60vh;
            width: 100vw;
            margin: 0;
            padding: 0;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .hero .container {
            max-width: 700px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Search and Filter Section */
        .search-filter {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .search-container {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .search-input {
            flex: 1;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        .filter-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
            font-weight: 500;
        }

        .filter-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        /* Buskers Grid */
        .buskers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .busker-card {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .busker-card:hover {
            transform: translateY(-5px);
        }

        .busker-image {
            width: 100%;
            height: 200px;
            background-color: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        .busker-info {
            padding: 1.5rem;
        }

        .busker-name {
            font-size: 1.25rem;
            font-weight: bold;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .busker-band {
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .busker-details {
            margin-bottom: 1rem;
        }

        .busker-details p {
            margin-bottom: 0.5rem;
            color: #666;
        }

        .busker-genres {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .genre-tag {
            background: var(--light-color);
            color: var(--dark-color);
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .busker-equipment {
            margin-bottom: 1rem;
        }

        .equipment-list {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .equipment-item {
            background: #f8f9fa;
            padding: 0.3rem 0.8rem;
            border-radius: 4px;
            font-size: 0.9rem;
            color: #666;
        }

        .book-button {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s;
            width: 100%;
            text-align: center;
        }

        .book-button:hover {
            background: #2980b9;
        }

        /* Footer */
        footer {
            background: var(--dark-color);
            color: white;
            padding: 3rem 0;
            margin-top: 3rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .footer-section h3 {
            margin-bottom: 1rem;
        }

        .footer-section p {
            margin-bottom: 0.5rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .social-links a {
            color: white;
            font-size: 1.5rem;
            transition: color 0.3s;
        }

        .social-links a:hover {
            color: var(--primary-color);
        }

        .copyright {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-direction: column;
                align-items: center;
                gap: 1rem;
            }

            .search-container {
                flex-direction: column;
            }

            .buskers-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <section class="hero">
        <div class="container">
            <h1>Discover Our Talented Buskers</h1>
            <p>Find the perfect performer for your event from our diverse community of talented artists</p>
        </div>
    </section>

    <div class="container">
        <section class="search-filter">
            <div class="search-container">
                <input type="text" id="searchInput" class="search-input" placeholder="Search buskers by name, band, or genre...">
            </div>
            <div class="filter-container">
                <div class="filter-group">
                    <label for="genreFilter">Filter by Genre</label>
                    <select id="genreFilter">
                        <option value="">All Genres</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?php echo htmlspecialchars($genre['name']); ?>">
                                <?php echo htmlspecialchars($genre['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="equipmentFilter">Has Equipment</label>
                    <select id="equipmentFilter">
                        <option value="">All</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
            </div>
        </section>

        <section class="buskers-grid">
            <?php foreach ($buskers as $busker): ?>
                <div class="busker-card" 
                     data-name="<?php echo htmlspecialchars($busker['name']); ?>"
                     data-band="<?php echo htmlspecialchars($busker['band_name']); ?>"
                     data-genres="<?php echo htmlspecialchars($busker['genres']); ?>"
                     data-equipment="<?php echo $busker['has_equipment'] ? '1' : '0'; ?>">
                    <div class="busker-image">
                        <i class="fas fa-music fa-3x"></i>
                    </div>
                    <div class="busker-info">
                        <h3 class="busker-name"><?php echo htmlspecialchars($busker['name']); ?></h3>
                        <?php if ($busker['band_name']): ?>
                            <p class="busker-band"><?php echo htmlspecialchars($busker['band_name']); ?></p>
                        <?php endif; ?>
                        
                        <div class="busker-details">
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($busker['email']); ?></p>
                            <?php if ($busker['contact_number']): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($busker['contact_number']); ?></p>
                            <?php endif; ?>
                        </div>

                        <?php if ($busker['genres']): ?>
                            <div class="busker-genres">
                                <?php foreach (explode(',', $busker['genres']) as $genre): ?>
                                    <span class="genre-tag"><?php echo htmlspecialchars(trim($genre)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($busker['equipment']): ?>
                            <div class="busker-equipment">
                                <h4>Equipment:</h4>
                                <ul class="equipment-list">
                                    <?php foreach (explode(',', $busker['equipment']) as $equipment): ?>
                                        <li class="equipment-item"><?php echo htmlspecialchars(trim($equipment)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['client_id'])): ?>
                            <a href="contact.php?busker_id=<?php echo $busker['busker_id']; ?>" class="book-button">Book This Busker</a>
                        <?php else: ?>
                            <a href="../client/index.php" class="book-button">Login to Book</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </section>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p>Email: info@tbcph.com</p>
                    <p>Phone: +63 123 456 7890</p>
                    <p>Address: Manila, Philippines</p>
                </div>
                <div class="footer-section">
                    <h3>Follow Us</h3>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <p><a href="about.php">About Us</a></p>
                    <p><a href="contact.php">Contact</a></p>
                    <p><a href="buskers.php">Our Buskers</a></p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> The Busking Community PH. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const genreFilter = document.getElementById('genreFilter');
            const equipmentFilter = document.getElementById('equipmentFilter');
            const buskerCards = document.querySelectorAll('.busker-card');

            function filterBuskers() {
                const searchTerm = searchInput.value.toLowerCase();
                const selectedGenre = genreFilter.value.toLowerCase();
                const hasEquipment = equipmentFilter.value;

                buskerCards.forEach(card => {
                    const name = card.dataset.name.toLowerCase();
                    const band = card.dataset.band.toLowerCase();
                    const genres = card.dataset.genres.toLowerCase();
                    const equipment = card.dataset.equipment;

                    const matchesSearch = name.includes(searchTerm) || 
                                        band.includes(searchTerm) || 
                                        genres.includes(searchTerm);
                    
                    const matchesGenre = !selectedGenre || genres.includes(selectedGenre);
                    const matchesEquipment = !hasEquipment || equipment === hasEquipment;

                    card.style.display = matchesSearch && matchesGenre && matchesEquipment ? 'block' : 'none';
                });
            }

            searchInput.addEventListener('input', filterBuskers);
            genreFilter.addEventListener('change', filterBuskers);
            equipmentFilter.addEventListener('change', filterBuskers);
        });
    </script>
</body>
</html>  
