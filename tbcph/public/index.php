<?php
require_once '../includes/header.php';
?>

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
        <!-- Busker cards will be dynamically loaded here -->
        <div class="busker-card">
            <div class="busker-image">
                <img src="/tbcph/assets/images/placeholder.jpg" alt="Busker Name">
            </div>
            <div class="busker-info">
                <h3>Busker Name</h3>
                <p>Genre: Acoustic</p>
                <a href="buskers.php" class="btn">View Profile</a>
            </div>
        </div>
        <!-- More busker cards -->
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

<?php
require_once '../includes/footer.php';
?>  
