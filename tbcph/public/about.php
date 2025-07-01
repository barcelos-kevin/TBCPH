<?php
require_once '../includes/config.php';
include __DIR__ . '/../includes/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - TBCPH</title>
    <link rel="icon" href="/tbcph/assets/images/logo.jpg">
    <link rel="stylesheet" href="/tbcph/assets/css/style.css">
    <style>
        .about-hero {
            background: linear-gradient(rgba(96, 96, 96, 0.7), rgba(0, 0, 0, 0.7)), url('/tbcph/assets/images/busking.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 20px;
            text-align: center;
        }

        .about-hero-content {
            max-width: 800px;
            margin: 0 auto;
        }

        .about-hero h1 {
            font-size: 3em;
            margin-bottom: 20px;
        }

        .about-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 60px 20px;
        }

        .about-section {
            margin-bottom: 60px;
        }

        .about-section h2 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2.2em;
        }

        .services-grid, .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }

        .service-card, .value-card {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .service-card:hover, .value-card:hover {
            transform: translateY(-5px);
        }

        .service-card h3, .value-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.5em;
        }

        .about-section ul {
            list-style-type: none;
            padding: 0;
            max-width: 800px;
            margin: 0 auto;
        }

        .about-section ul li {
            margin-bottom: 15px;
            padding-left: 30px;
            position: relative;
        }

        .about-section ul li:before {
            content: "•";
            color: #2c3e50;
            font-size: 1.5em;
            position: absolute;
            left: 0;
            top: -5px;
        }

        .cta {
            background: #f8f9fa;
            padding: 80px 20px;
            text-align: center;
        }

        .cta h2 {
            font-size: 2.5em;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .btn.primary {
            background: #2c3e50;
            color: white;
        }

        .btn.secondary {
            background: #3498db;
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        @media (max-width: 768px) {
            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .about-hero h1 {
                font-size: 2em;
            }

            .services-grid, .values-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main>
        <section class="about-hero">
            <div class="about-hero-content">
                <h1>About The Busking Community PH</h1>
                <p>Empowering street performers and connecting them with opportunities</p>
            </div>
        </section>

        <section class="about-content">
            <div class="about-section">
                <h2>Our Story</h2>
                <p>The Busking Community PH (TBCPH) was founded with a simple yet powerful vision: to create a platform where talented street performers can showcase their art and connect with opportunities that value their craft. We believe that street performance is not just entertainment—it's a vital part of our cultural landscape that deserves recognition and support.</p>
            </div>

            <div class="about-section">
                <h2>Our Mission</h2>
                <p>We are dedicated to:</p>
                <ul>
                    <li>Providing a platform for street performers to showcase their talents</li>
                    <li>Creating meaningful connections between performers and event organizers</li>
                    <li>Promoting the value of street performance as a legitimate art form</li>
                    <li>Supporting the growth and development of the busking community</li>
                </ul>
            </div>

            <div class="about-section">
                <h2>What We Do</h2>
                <div class="services-grid">
                    <div class="service-card">
                        <h3>For Buskers</h3>
                        <p>We provide a professional platform to showcase your talent, connect with potential clients, and manage your bookings efficiently.</p>
                    </div>
                    <div class="service-card">
                        <h3>For Clients</h3>
                        <p>We help you find the perfect performers for your events, ensuring quality entertainment that matches your vision.</p>
                    </div>
                    <div class="service-card">
                        <h3>For the Community</h3>
                        <p>We work to promote and preserve the rich tradition of street performance in the Philippines.</p>
                    </div>
                </div>
            </div>

            <div class="about-section">
                <h2>Our Values</h2>
                <div class="values-grid">
                    <div class="value-card">
                        <h3>Community</h3>
                        <p>Building a supportive network of performers, clients, and enthusiasts</p>
                    </div>
                    <div class="value-card">
                        <h3>Excellence</h3>
                        <p>Promoting high-quality performances and professional standards</p>
                    </div>
                    <div class="value-card">
                        <h3>Innovation</h3>
                        <p>Embracing new ways to support and grow the busking community</p>
                    </div>
                    <div class="value-card">
                        <h3>Inclusivity</h3>
                        <p>Welcoming performers of all backgrounds and styles</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="cta">
            <h2>Join Our Community</h2>
            <p>Whether you're a performer looking for opportunities or a client seeking talent, we're here to help you connect.</p>
            <div class="cta-buttons">
                <a href="/tbcph/client/register.php" class="btn primary">Register Now</a>
                <a href="/tbcph/client/index.php" class="btn secondary">Login</a>
            </div>
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
