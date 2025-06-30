<?php
require_once __DIR__ . '/../includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['client_id'])) {
    header('Location: ' . SITE_URL . '/client/index.php');
    exit();
}

// Get client information
$stmt = $conn->prepare("
    SELECT c.*, 
           COUNT(DISTINCT i.inquiry_id) as total_inquiries,
           COUNT(DISTINCT CASE WHEN i.inquiry_status = 'completed' THEN i.inquiry_id END) as completed_inquiries,
           COUNT(DISTINCT CASE WHEN i.inquiry_status = 'pending' THEN i.inquiry_id END) as pending_inquiries
    FROM client c
    LEFT JOIN inquiry i ON c.client_id = i.client_id
    WHERE c.client_id = ?
    GROUP BY c.client_id
");
$stmt->execute([$_SESSION['client_id']]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

// Get recent inquiries
$stmt = $conn->prepare("
    SELECT i.*, e.event_name, e.event_date, e.event_type,
           GROUP_CONCAT(DISTINCT g.name) as genres,
           GROUP_CONCAT(DISTINCT sd.doc_link) as documents
    FROM inquiry i
    JOIN event_table e ON i.event_id = e.event_id
    LEFT JOIN inquiry_genre ig ON i.inquiry_id = ig.inquiry_id
    LEFT JOIN genre g ON ig.genre_id = g.genre_id
    LEFT JOIN inquiry_document id ON i.inquiry_id = id.inquiry_id
    LEFT JOIN supporting_document sd ON id.docs_id = sd.docs_id
    WHERE i.client_id = ? AND i.inquiry_status != 'deleted by client'
    GROUP BY i.inquiry_id
    ORDER BY e.event_date DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['client_id']]);
$recent_inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Client Profile - " . SITE_NAME;
include __DIR__ . '/../includes/header.php';
?>

<div class="profile-page">
    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($client['name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h1><?php echo htmlspecialchars($client['name']); ?></h1>
                        <p class="join-date">
                            <i class="fas fa-calendar"></i>
                            Member since <?php echo date('F Y', strtotime($client['created_at'] ?? 'now')); ?>
                        </p>
                    </div>
                </div>
                <div class="user-actions">
                    <a href="edit_profile.php" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Edit Profile
                    </a>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="back-button-container">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <div class="profile-content">
            <!-- Main Content -->
            <div class="main-content">
                <!-- Stats Overview -->
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $client['total_inquiries']; ?></h3>
                            <p>Total Inquiries</p>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $client['completed_inquiries']; ?></h3>
                            <p>Completed Events</p>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $client['pending_inquiries']; ?></h3>
                            <p>Pending Inquiries</p>
                        </div>
                    </div>
                </div>

                <!-- Recent Inquiries -->
                <div class="recent-inquiries">
                    <div class="section-header">
                        <h2>Recent Inquiries</h2>
                        <a href="dashboard.php" class="btn btn-outline">View All</a>
                    </div>

                    <?php if (empty($recent_inquiries)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <h3>No Inquiries Yet</h3>
                            <p>Start your journey by making your first inquiry</p>
                            <a href="contact.php" class="btn btn-primary">Make Your First Inquiry</a>
                        </div>
                    <?php else: ?>
                        <div class="inquiry-list">
                            <?php foreach ($recent_inquiries as $inquiry): ?>
                                <div class="inquiry-card">
                                    <div class="inquiry-header">
                                        <h3><?php echo htmlspecialchars($inquiry['event_name']); ?></h3>
                                        <span class="status-badge status-<?php echo strtolower($inquiry['inquiry_status']); ?>">
                                            <?php echo ucfirst($inquiry['inquiry_status']); ?>
                                        </span>
                                    </div>
                                    <div class="inquiry-body">
                                        <div class="inquiry-details">
                                            <div class="detail-item">
                                                <i class="fas fa-calendar"></i>
                                                <span><?php echo date('F j, Y', strtotime($inquiry['event_date'])); ?></span>
                                            </div>
                                            <div class="detail-item">
                                                <i class="fas fa-tag"></i>
                                                <span><?php echo htmlspecialchars($inquiry['event_type']); ?></span>
                                            </div>
                                            <?php if ($inquiry['genres']): ?>
                                                <div class="detail-item">
                                                    <i class="fas fa-music"></i>
                                                    <span><?php echo htmlspecialchars($inquiry['genres']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="inquiry-footer">
                                            <div class="budget">
                                                <span class="budget-label">Budget:</span>
                                                <span class="budget-amount">₱<?php echo number_format($inquiry['budget']); ?></span>
                                            </div>
                                            <a href="dashboard.php?inquiry_id=<?php echo $inquiry['inquiry_id']; ?>" class="btn btn-outline">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="sidebar">
                <div class="contact-card">
                    <h2>Contact Information</h2>
                    <div class="contact-list">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-info">
                                <label>Email Address</label>
                                <p><?php echo htmlspecialchars($client['email']); ?></p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="contact-info">
                                <label>Phone Number</label>
                                <p><?php echo htmlspecialchars($client['phone'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-info">
                                <label>Address</label>
                                <p><?php echo htmlspecialchars($client['address'] ?? 'Not provided'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Base Styles */
.profile-page {
    background-color: #f8f9fa;
    min-height: 100vh;
}

/* Hero Section */
.hero {
    background: linear-gradient(135deg, #1a237e, #0d47a1);
    color: white;
    padding: 3rem 0;
    margin-bottom: 2rem;
}

.hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.user-avatar {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.user-details h1 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 600;
}

.join-date {
    margin: 0.5rem 0 0;
    opacity: 0.8;
    font-size: 0.9rem;
}

.join-date i {
    margin-right: 0.5rem;
}

/* Profile Content */
.profile-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 2rem;
    margin-bottom: 2rem;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-box {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: transform 0.2s;
}

.stat-box:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    background: #f8f9fa;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: #1a237e;
}

.stat-info h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    color: #1a237e;
}

.stat-info p {
    margin: 0.25rem 0 0;
    color: #666;
    font-size: 0.9rem;
}

/* Recent Inquiries */
.recent-inquiries {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.section-header h2 {
    margin: 0;
    font-size: 1.25rem;
    color: #1a237e;
}

/* Inquiry Cards */
.inquiry-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.inquiry-card {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
}

.inquiry-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.inquiry-header {
    padding: 1rem;
    background: #f8f9fa;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.inquiry-header h3 {
    margin: 0;
    font-size: 1.1rem;
    color: #1a237e;
}

.status-badge {
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-pending { background: #fff3cd; color: #856404; }
.status-approved { background: #d4edda; color: #155724; }
.status-completed { background: #cce5ff; color: #004085; }
.status-rejected { background: #f8d7da; color: #721c24; }
.status-deleted { background: #e2e3e5; color: #383d41; }

.inquiry-body {
    padding: 1rem;
}

.inquiry-details {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1rem;
}

.detail-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: #666;
    font-size: 0.9rem;
}

.detail-item i {
    color: #1a237e;
}

.inquiry-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 1rem;
    border-top: 1px solid #eee;
}

.budget {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.budget-label {
    color: #666;
    font-size: 0.9rem;
}

.budget-amount {
    font-weight: 600;
    color: #1a237e;
}

/* Sidebar */
.sidebar {
    position: sticky;
    top: 2rem;
}

.contact-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.contact-card h2 {
    margin: 0 0 1.5rem;
    font-size: 1.25rem;
    color: #1a237e;
}

.contact-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.contact-item {
    display: flex;
    gap: 1rem;
}

.contact-icon {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #1a237e;
}

.contact-info label {
    display: block;
    font-size: 0.8rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.contact-info p {
    margin: 0;
    color: #1a237e;
    font-weight: 500;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}

.empty-icon {
    font-size: 3rem;
    color: #dee2e6;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #1a237e;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
    margin-bottom: 1.5rem;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: #1a237e;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #0d47a1;
}

.btn-outline {
    background: transparent;
    color: #1a237e;
    border: 1px solid #1a237e;
}

.btn-outline:hover {
    background: #1a237e;
    color: white;
}

/* Responsive Design */
@media (max-width: 992px) {
    .profile-content {
        grid-template-columns: 1fr;
    }

    .sidebar {
        position: static;
    }
}

@media (max-width: 768px) {
    .hero-content {
        flex-direction: column;
        text-align: center;
    }

    .user-info {
        flex-direction: column;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .inquiry-header {
        flex-direction: column;
        gap: 0.5rem;
        text-align: center;
    }

    .inquiry-footer {
        flex-direction: column;
        gap: 1rem;
        align-items: flex-start;
    }
}

.back-button-container {
    margin: 20px 0;
    padding: 10px;
    background-color: #f8f9fa;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.back-button-container .btn {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 12px 24px;
    background-color: #2c3e50;
    color: white;
    border: none;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 1.1em;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.back-button-container .btn:hover {
    background-color: #34495e;
    transform: translateX(-3px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.back-button-container .btn i {
    font-size: 1.2em;
}
</style>
<link rel="icon" href="/tbcph/assets/images/logo.jpg">

<?php include __DIR__ . '/../includes/footer.php'; ?> 