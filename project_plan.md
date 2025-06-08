# TBCPH Website Project - Simplified Plan

## Project Overview
A website for The Busking Community PH that connects clients with buskers through a simple inquiry system.

## Technology Stack
- HTML5
- CSS3
- JavaScript
- PHP
- MySQL

## Folder Structure
```
tbcph/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   └── admin.css
│   ├── js/
│   │   ├── main.js
│   │   └── admin.js
│   └── images/
│       ├── logo/
│       └── buskers/
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
│   └── inquiries.php
├── public/
│   ├── index.php
│   ├── about.php
│   ├── buskers.php
│   └── contact.php
└── vendor/
    └── (third-party libraries)
```

## Pages Structure

### 1. Public Pages
- **Landing Page (index.php)**
  - About TBCPH
  - Featured Buskers
  - Contact Form
  - Inquiry Form

- **About Page (about.php)**
  - TBCPH Mission
  - Vision
  - History

- **Busker Profiles (buskers.php)**
  - List of Buskers
  - Individual Busker Profiles
  - Social Media Links (YouTube, Spotify, TikTok)

- **Contact/Inquiry Form (contact.php)**
  - Contact Information
  - Inquiry Form
  - Email Notification System

### 2. Admin Section
- **Admin Login (admin/index.php)**
  - Secure Login Form
  - Password Reset

- **Admin Dashboard (admin/dashboard.php)**
  - Overview Statistics
  - Recent Inquiries
  - Quick Actions

- **User Management (admin/users.php)**
  - View All Users
  - Edit User Roles
  - Manage Permissions

- **Inquiry Management (admin/inquiries.php)**
  - View All Inquiries
  - Update Status
  - Send Email Notifications

## Database Structure (Simplified)

### Core Tables
1. **users**
   - user_id
   - email
   - password
   - role (admin/client)
   - created_at

2. **buskers**
   - busker_id
   - name
   - contact
   - social_links (JSON)
   - status

3. **inquiries**
   - inquiry_id
   - client_email
   - event_details
   - status
   - created_at

## Implementation Steps

### Phase 1: Basic Setup
1. Set up local development environment
2. Create database and tables
3. Implement basic file structure
4. Create header and footer templates

### Phase 2: Public Pages
1. Create landing page
2. Implement about page
3. Create busker profiles
4. Build contact/inquiry form

### Phase 3: Admin System
1. Create admin login
2. Build dashboard
3. Implement user management
4. Create inquiry management system

### Phase 4: Email System
1. Set up email templates
2. Implement inquiry notifications
3. Create status update emails

## Security Considerations
1. Password hashing
2. SQL injection prevention
3. XSS protection
4. CSRF protection
5. Secure file uploads

## Next Steps
1. Set up local development environment
2. Create database
3. Start with landing page
4. Implement basic styling

## Notes
- Keep the design simple and clean
- Focus on mobile responsiveness
- Ensure fast loading times
- Maintain security best practices
- Regular backups of database 