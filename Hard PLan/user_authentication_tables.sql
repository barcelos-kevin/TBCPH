-- User Authentication System Tables
-- Run this after the main schema is created

USE busker_management;

-- User Authentication Table
CREATE TABLE user_auth (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'client', 'busker') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User Profile Table (for additional user information)
CREATE TABLE user_profile (
    profile_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    profile_picture VARCHAR(500),
    bio TEXT,
    FOREIGN KEY (user_id) REFERENCES user_auth(user_id) ON DELETE CASCADE
);

-- Password Reset Table
CREATE TABLE password_reset (
    reset_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth(user_id) ON DELETE CASCADE
);

-- User Session Table
CREATE TABLE user_session (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth(user_id) ON DELETE CASCADE
);

-- User Activity Log
CREATE TABLE user_activity_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'profile_update', 'password_change', 'media_upload', 'inquiry_create') NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_auth(user_id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX idx_user_auth_email ON user_auth(email);
CREATE INDEX idx_user_auth_type ON user_auth(user_type);
CREATE INDEX idx_password_reset_token ON password_reset(token);
CREATE INDEX idx_user_session_token ON user_session(session_token);
CREATE INDEX idx_user_activity_type ON user_activity_log(activity_type);

-- Add triggers for user management
DELIMITER //

-- Trigger to create user profile after user registration
CREATE TRIGGER after_user_auth_insert
AFTER INSERT ON user_auth
FOR EACH ROW
BEGIN
    INSERT INTO user_profile (user_id, first_name, last_name)
    VALUES (NEW.user_id, '', '');
END//

-- Trigger to log user activity
CREATE TRIGGER after_user_activity
AFTER INSERT ON user_activity_log
FOR EACH ROW
BEGIN
    UPDATE user_auth 
    SET last_login = CURRENT_TIMESTAMP 
    WHERE user_id = NEW.user_id AND NEW.activity_type = 'login';
END//

DELIMITER ;

-- Add views for user management
CREATE VIEW user_overview AS
SELECT 
    ua.user_id,
    ua.email,
    ua.user_type,
    ua.is_active,
    ua.last_login,
    up.first_name,
    up.last_name,
    up.phone
FROM user_auth ua
LEFT JOIN user_profile up ON ua.user_id = up.user_id;

-- Add stored procedures for common operations
DELIMITER //

-- Procedure to authenticate user
CREATE PROCEDURE AuthenticateUser(IN p_email VARCHAR(255), IN p_password VARCHAR(255))
BEGIN
    SELECT 
        ua.user_id,
        ua.email,
        ua.user_type,
        ua.is_active,
        up.first_name,
        up.last_name
    FROM user_auth ua
    LEFT JOIN user_profile up ON ua.user_id = up.user_id
    WHERE ua.email = p_email 
    AND ua.password_hash = SHA2(p_password, 256)
    AND ua.is_active = TRUE;
END//

-- Procedure to create new user
CREATE PROCEDURE CreateUser(
    IN p_email VARCHAR(255),
    IN p_password VARCHAR(255),
    IN p_user_type ENUM('admin', 'client', 'busker'),
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100)
)
BEGIN
    DECLARE new_user_id INT;
    
    INSERT INTO user_auth (email, password_hash, user_type)
    VALUES (p_email, SHA2(p_password, 256), p_user_type);
    
    SET new_user_id = LAST_INSERT_ID();
    
    INSERT INTO user_profile (user_id, first_name, last_name)
    VALUES (new_user_id, p_first_name, p_last_name);
    
    SELECT new_user_id as user_id;
END//

-- Procedure to update user profile
CREATE PROCEDURE UpdateUserProfile(
    IN p_user_id INT,
    IN p_first_name VARCHAR(100),
    IN p_last_name VARCHAR(100),
    IN p_phone VARCHAR(20),
    IN p_address TEXT,
    IN p_bio TEXT
)
BEGIN
    UPDATE user_profile
    SET 
        first_name = p_first_name,
        last_name = p_last_name,
        phone = p_phone,
        address = p_address,
        bio = p_bio
    WHERE user_id = p_user_id;
END//

-- Procedure to log user activity
CREATE PROCEDURE LogUserActivity(
    IN p_user_id INT,
    IN p_activity_type ENUM('login', 'logout', 'profile_update', 'password_change', 'media_upload', 'inquiry_create'),
    IN p_description TEXT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    INSERT INTO user_activity_log (user_id, activity_type, description, ip_address)
    VALUES (p_user_id, p_activity_type, p_description, p_ip_address);
END//

DELIMITER ;

-- Insert default admin user (password: admin123)
INSERT INTO user_auth (email, password_hash, user_type)
VALUES ('admin@tbcph.com', SHA2('admin123', 256), 'admin');

-- Insert corresponding admin profile
INSERT INTO user_profile (user_id, first_name, last_name)
VALUES (1, 'Admin', 'User'); 