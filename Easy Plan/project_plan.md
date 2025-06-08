# TBCPH Website Project Plan

## Project Overview
A simple website for The Busking Community PH to connect buskers with clients and manage inquiries.

## Technology Stack
- Frontend: HTML, CSS, JavaScript
- Backend: PHP
- Database: MySQL
- Local Development: XAMPP/WAMP

## Folder Structure
```
tbcph/
├── assets/
│   ├── css/
│   │   ├── style.css
│   │   ├── admin.css
│   │   └── auth.css
│   ├── js/
│   │   ├── main.js
│   │   └── validation.js
│   └── images/
│       ├── logo/
│       └── backgrounds/
├── includes/
│   ├── config.php
│   ├── db.php
│   ├── functions.php
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── index.php (login)
│   ├── dashboard.php
│   ├── buskers.php (manage buskers)
│   └── inquiries.php
├── busker/
│   ├── index.php (login)
│   ├── register.php
│   └── profile.php
├── client/
│   ├── index.php (login)
│   ├── register.php
│   └── dashboard.php
└── public/
    ├── index.php (landing page)
    ├── about.php
    ├── buskers.php (showcase)
    └── contact.php
```

## Page Structure

### 1. Public Pages
- **Landing Page (index.php)**
  - About TBCPH
  - Featured Buskers
  - Quick Inquiry Form
  - Login/Register Links

- **About Page (about.php)**
  - TBCPH Mission
  - Community Information
  - How It Works

- **Busker Showcase (buskers.php)**
  - List of Approved Buskers
  - Filter by Genre
  - Basic Profile Cards

- **Contact Page (contact.php)**
  - Inquiry Form
  - Contact Information

### 2. Client Section
- **Login/Register**
  - Simple Registration Form
  - Login Form

- **Dashboard**
  - Inquiry Status
  - Past Inquiries
  - Profile Management

### 3. Busker Section
- **Login/Register**
  - Registration Form (requires admin approval)
  - Login Form

- **Profile Page**
  - Basic Information
  - Social Media Links (Spotify, YouTube, TikTok)
  - Performance History
  - Availability Status

### 4. Admin Section
- **Login**
  - Secure Admin Login

- **Dashboard**
  - Overview Statistics
  - Recent Inquiries
  - Pending Busker Approvals

- **Busker Management**
  - Approve/Reject Busker Registrations
  - Manage Busker Status
  - View Busker Profiles

## Database Evaluation

The current database structure is suitable for the simplified version. Key tables we'll use:

1. **User Management**
   - `admin` - Admin accounts
   - `client` - Client accounts
   - `busker` - Busker accounts

2. **Core Features**
   - `inquiry` - Client inquiries
   - `genre` - Music genres
   - `busker_genre` - Busker genre associations
   - `hire` - Booking information
   - `review` - Client reviews

## Implementation Priorities

1. **Phase 1: Basic Setup**
   - Set up local development environment
   - Create database
   - Implement basic file structure
   - Create header and footer templates

2. **Phase 2: Public Pages**
   - Landing page
   - About page
   - Busker showcase
   - Contact form

3. **Phase 3: Authentication**
   - Client login/registration
   - Busker login/registration
   - Admin login

4. **Phase 4: User Dashboards**
   - Client dashboard
   - Busker profile
   - Admin dashboard

5. **Phase 5: Core Features**
   - Inquiry system
   - Busker approval system
   - Profile management

## Security Considerations

1. **Authentication**
   - Password hashing
   - Session management
   - Input validation

2. **Data Protection**
   - Prepared statements
   - XSS prevention
   - CSRF protection

## Next Steps

1. Set up local development environment
2. Create database using provided schema
3. Implement basic file structure
4. Create header and footer templates
5. Begin with landing page development

## Notes
- Keep the design simple and clean
- Focus on mobile responsiveness
- Implement basic form validation
- Use existing database structure
- Prioritize essential features first 