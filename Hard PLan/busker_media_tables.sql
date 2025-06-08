-- Additional Tables for Busker Media Management
-- Run this after the main schema is created

USE busker_management;

-- Busker Media Samples Table
CREATE TABLE busker_media (
    media_id INT PRIMARY KEY AUTO_INCREMENT,
    busker_id INT NOT NULL,
    media_type ENUM('image', 'video', 'audio') NOT NULL,
    media_url VARCHAR(500) NOT NULL,
    title VARCHAR(255),
    description TEXT,
    is_featured BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (busker_id) REFERENCES busker(busker_id) ON DELETE CASCADE
);

-- Busker Social Media Links Table
CREATE TABLE busker_social_media (
    social_id INT PRIMARY KEY AUTO_INCREMENT,
    busker_id INT NOT NULL,
    platform ENUM('YouTube', 'Instagram', 'Facebook', 'TikTok', 'Spotify', 'SoundCloud', 'Other') NOT NULL,
    profile_url VARCHAR(500) NOT NULL,
    FOREIGN KEY (busker_id) REFERENCES busker(busker_id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_busker_media_type ON busker_media(media_type);
CREATE INDEX idx_busker_media_featured ON busker_media(is_featured);
CREATE INDEX idx_busker_social_platform ON busker_social_media(platform);

-- Add sample data for testing (optional)
INSERT INTO busker_media (busker_id, media_type, media_url, title, description, is_featured) VALUES
(1, 'image', 'https://example.com/sample1.jpg', 'Live Performance', 'Street performance at Rizal Park', TRUE),
(1, 'video', 'https://youtube.com/watch?v=sample1', 'Acoustic Session', 'Live acoustic performance', FALSE),
(1, 'audio', 'https://soundcloud.com/sample1', 'Original Song', 'My first original composition', TRUE);

INSERT INTO busker_social_media (busker_id, platform, profile_url) VALUES
(1, 'YouTube', 'https://youtube.com/@busker1'),
(1, 'Instagram', 'https://instagram.com/busker1'),
(1, 'SoundCloud', 'https://soundcloud.com/busker1');

-- Add triggers for media management
DELIMITER //

-- Trigger to update busker's last_updated timestamp when media is added
CREATE TRIGGER after_media_insert
AFTER INSERT ON busker_media
FOR EACH ROW
BEGIN
    UPDATE busker 
    SET updated_at = CURRENT_TIMESTAMP 
    WHERE busker_id = NEW.busker_id;
END//

-- Trigger to update busker's last_updated timestamp when social media is added
CREATE TRIGGER after_social_media_insert
AFTER INSERT ON busker_social_media
FOR EACH ROW
BEGIN
    UPDATE busker 
    SET updated_at = CURRENT_TIMESTAMP 
    WHERE busker_id = NEW.busker_id;
END//

DELIMITER ;

-- Add views for easier media access
CREATE VIEW busker_media_overview AS
SELECT 
    b.busker_id,
    b.band_name,
    COUNT(CASE WHEN m.media_type = 'image' THEN 1 END) as total_images,
    COUNT(CASE WHEN m.media_type = 'video' THEN 1 END) as total_videos,
    COUNT(CASE WHEN m.media_type = 'audio' THEN 1 END) as total_audio,
    COUNT(CASE WHEN m.is_featured = TRUE THEN 1 END) as featured_media
FROM busker b
LEFT JOIN busker_media m ON b.busker_id = m.busker_id
GROUP BY b.busker_id, b.band_name;

-- Add stored procedures for common operations
DELIMITER //

-- Procedure to get all media for a busker
CREATE PROCEDURE GetBuskerMedia(IN p_busker_id INT)
BEGIN
    SELECT * FROM busker_media 
    WHERE busker_id = p_busker_id 
    ORDER BY is_featured DESC, created_at DESC;
END//

-- Procedure to get all social media links for a busker
CREATE PROCEDURE GetBuskerSocialMedia(IN p_busker_id INT)
BEGIN
    SELECT * FROM busker_social_media 
    WHERE busker_id = p_busker_id;
END//

-- Procedure to get featured media for a busker
CREATE PROCEDURE GetFeaturedMedia(IN p_busker_id INT)
BEGIN
    SELECT * FROM busker_media 
    WHERE busker_id = p_busker_id AND is_featured = TRUE 
    ORDER BY created_at DESC;
END//

DELIMITER ; 