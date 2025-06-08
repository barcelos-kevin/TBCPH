# TBCPH Website Project Structure

## Folder Structure
```
tbcph/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── admin.css
│   │   ├── client.css
│   │   └── busker.css
│   ├── js/
│   │   ├── main.js
│   │   ├── admin.js
│   │   ├── client.js
│   │   └── busker.js
│   ├── images/
│   │   ├── logo/
│   │   ├── buskers/
│   │   └── events/
│   └── uploads/
│       ├── busker_profiles/
│       └── event_documents/
├── includes/
│   ├── config.php
│   ├── db.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── index.php
│   ├── dashboard.php
│   ├── users.php
│   ├── inquiries.php
│   ├── buskers.php
│   └── clients.php
├── client/
│   ├── index.php
│   ├── dashboard.php
│   ├── profile.php
│   ├── inquiries.php
│   └── bookings.php
├── busker/
│   ├── index.php
│   ├── dashboard.php
│   ├── profile.php
│   ├── availability.php
│   └── equipment.php
├── public/
│   ├── index.php
│   ├── about.php
│   ├── buskers.php
│   ├── contact.php
│   └── inquiry-form.php
└── vendor/
    └── (third-party libraries)
```

## Database Evaluation

The current database schema is well-structured and covers the essential requirements for the IMS. Here's an analysis:

### Strengths:
1. Comprehensive user management (clients, buskers, admin)
2. Detailed event and inquiry tracking
3. Support for multiple genres and equipment
4. Review system for feedback
5. Payment tracking
6. Location and time slot management

### Suggested Improvements:
1. Add user authentication table for login management
2. Include notification system for status updates
3. Add chat/messaging system between clients and buskers
4. Include busker availability calendar
5. Add event categories and tags
6. Include payment history table

### Required New Tables:
```sql
-- User Authentication
CREATE TABLE user_auth (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'client', 'busker') NOT NULL,
    last_login TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth(user_id)
);

-- Busker Availability
CREATE TABLE busker_availability (
    availability_id INT PRIMARY KEY AUTO_INCREMENT,
    busker_id INT NOT NULL,
    date DATE NOT NULL,
    time_slot_id INT NOT NULL,
    is_available BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (busker_id) REFERENCES busker(busker_id),
    FOREIGN KEY (time_slot_id) REFERENCES time_slot(time_slot_id)
);

-- Messages
CREATE TABLE messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES user_auth(user_id),
    FOREIGN KEY (receiver_id) REFERENCES user_auth(user_id)
);

-- Busker Media Samples
CREATE TABLE busker_media (
    media_id INT PRIMARY KEY AUTO_INCREMENT,
    busker_id INT NOT NULL,
    media_type ENUM('image', 'video', 'audio') NOT NULL,
    media_url VARCHAR(500) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (busker_id) REFERENCES busker(busker_id)
);

-- Busker Social Media Links
CREATE TABLE busker_social_media (
    social_id INT PRIMARY KEY AUTO_INCREMENT,
    busker_id INT NOT NULL,
    platform ENUM('YouTube', 'Instagram', 'Facebook', 'TikTok', 'Spotify', 'SoundCloud', 'Other') NOT NULL,
    profile_url VARCHAR(500) NOT NULL,
    FOREIGN KEY (busker_id) REFERENCES busker(busker_id)
);
```

## Busker Profile Features

### Media Management
1. Image Gallery
   - Performance photos
   - Profile pictures
   - Event highlights
   - Equipment showcase

2. Video Samples
   - Performance videos
   - Live session recordings
   - Event highlights
   - YouTube/TikTok embeds

3. Audio Samples
   - Music tracks
   - Performance recordings
   - SoundCloud embeds

### Social Media Integration
1. Social Media Links
   - YouTube channel
   - Instagram profile
   - Facebook page
   - TikTok account
   - Spotify artist page
   - SoundCloud profile

### Profile Customization
1. Basic Information
   - Band/Artist name
   - Contact details
   - Location
   - Bio/Description

2. Performance Details
   - Genres
   - Equipment list
   - Performance history
   - Availability calendar

3. Portfolio Management
   - Featured media
   - Performance highlights
   - Event gallery
   - Client testimonials

## Implementation Details

### Busker Profile Page Structure
```
busker/
├── profile/
│   ├── edit.php
│   ├── media/
│   │   ├── upload.php
│   │   ├── manage.php
│   │   └── delete.php
│   ├── social/
│   │   ├── add.php
│   │   └── manage.php
│   └── portfolio/
│       ├── showcase.php
│       └── featured.php
```

### Media Upload Features
1. Supported Formats
   - Images: JPG, PNG, WebP
   - Videos: MP4, WebM
   - Audio: MP3, WAV

2. Upload Limits
   - Image: Max 5MB per file
   - Video: Max 100MB per file
   - Audio: Max 20MB per file

3. Storage Management
   - Automatic image optimization
   - Video compression
   - Cloud storage integration

### Security Measures
1. File Validation
   - MIME type checking
   - File size verification
   - Malware scanning

2. Access Control
   - Owner-only editing
   - Public/Private media options
   - Watermarking options

## Implementation Priorities

1. Core Features:
   - User authentication system
   - Basic profile management
   - Inquiry form and management
   - Busker showcase

2. Secondary Features:
   - Messaging system
   - Notification system
   - Availability calendar
   - Payment integration

3. Advanced Features:
   - Chat bot integration
   - Advanced search and filtering
   - Analytics dashboard
   - Mobile responsiveness

## Security Considerations

1. Implement proper password hashing
2. Use prepared statements for all database queries
3. Implement CSRF protection
4. Set up proper file upload security
5. Implement session management
6. Use HTTPS for all communications
7. Regular security audits

## Next Steps

1. Set up local development environment
2. Create database and implement schema
3. Develop core authentication system
4. Create basic UI templates
5. Implement inquiry management system
6. Develop user dashboards
7. Add advanced features incrementally 