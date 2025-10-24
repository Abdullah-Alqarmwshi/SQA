# ClassConnect - Digital Learning Management System

![ClassConnect](https://img.shields.io/badge/ClassConnect-v1.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.2-purple)
![MySQL](https://img.shields.io/badge/MySQL-8.0-orange)
![License](https://img.shields.io/badge/License-MIT-green)

A comprehensive web-based learning management system built with PHP and MySQL, designed to facilitate digital education with role-based access control and modern UI/UX.

##  Overview

ClassConnect is an e-learning platform that streamlines the educational process by providing dedicated interfaces for administrators, teachers, and students. It addresses common challenges in digital education such as inefficient user management, poor content organization, unsystematic assignment handling, and fragmented communication.

##  Features

### Core Functionality
- **Three User Roles**: Admin, Teacher, and Student with role-based access control
- **Authentication System**: Secure login with password hashing (bcrypt)
- **User Management**: 
  - Admin can register teachers
  - Students can self-register
  - Profile editing for all users
  - Account deletion (Teachers & Students)
- **Professional UI/UX**: Modern gradient design with responsive layout
- **Secure Architecture**: Prepared statements, session management, input validation

### Database Structure
- Users management
- Lessons module (ready for implementation)
- Assignments system (ready for implementation)
- Submissions tracking (ready for implementation)
- Announcements system (ready for implementation)

##  Quick Start

### Prerequisites
- XAMPP (Apache + MySQL + PHP)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Modern web browser

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/Abdullah-Alqarmwshi/SQA.git
   cd SQA
   ```

2. **Move to XAMPP directory**
   ```bash
   # Copy files to C:\xampp\htdocs\mywebsite
   ```

3. **Fix MySQL (if needed)**
   - Double-click `QUICK_FIX_MYSQL.bat`
   - Or follow instructions in `INSTALLATION_GUIDE.txt`

4. **Setup Database**
   - Start Apache and MySQL in XAMPP
   - Open: `http://localhost/mywebsite/setup.php`
   - Wait for "Database Setup Complete!"

5. **Login**
   - Go to: `http://localhost/mywebsite/`
   - Username: `admin`
   - Password: `admin`

##  Documentation

Detailed documentation available in:
- `INSTALLATION_GUIDE.txt` - Complete setup and usage guide
- `README.txt` - Quick reference
- `QUICK_FIX_MYSQL.bat` - Automated MySQL repair tool

##  User Roles

### Administrator
- Register and manage teachers
- View system statistics
- Manage announcements
- Access all system features

### Teacher
- Create and manage lessons
- Create and grade assignments
- View student submissions
- Edit profile and manage account

### Student
- Self-registration
- Browse lessons
- Submit assignments
- View grades and feedback
- Edit profile and manage account

##  Project Structure

```
mywebsite/
 config/              # Database and session configuration
 admin/              # Admin panel pages
 teacher/            # Teacher panel pages
 student/            # Student panel pages
 assets/css/         # Professional stylesheet
 uploads/            # File upload directories
 index.php           # Login page
 register.php        # Student registration
 setup.php           # Database installer
 logout.php          # Logout handler
```

##  Security Features

-  Password hashing using PHP `password_hash()`
-  Prepared SQL statements (prevents SQL injection)
-  Session-based authentication
-  Role-based access control
-  Input validation and sanitization
-  Protected admin functionalities

##  Technology Stack

- **Backend**: PHP 8.2
- **Database**: MySQL 8.0 (MariaDB compatible)
- **Frontend**: HTML5, CSS3, JavaScript
- **Server**: Apache (XAMPP)
- **Design**: Modern gradient UI with responsive layout

##  Database Schema

### Tables
1. **users** - User accounts and profiles
2. **lessons** - Lesson content and materials
3. **assignments** - Assignment details
4. **submissions** - Student assignment submissions
5. **announcements** - System-wide announcements

##  Future Enhancements

The system is ready for expansion with:
- Lesson Management Module
- Assignment Management System
- Announcement System
- File Upload Functionality
- Grading and Feedback System
- Real-time Notifications

##  Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

##  License

This project is licensed under the MIT License - see the LICENSE file for details.

##  Authors

- **Abdullah Alqarmwshi** - [GitHub](https://github.com/Abdullah-Alqarmwshi)

##  Contact

For questions or support, please open an issue on GitHub.

##  Acknowledgments

- Built as part of Software Quality Assurance (SQA) coursework
- Designed to address real-world challenges in digital education
- Follows modern web development best practices

---

**ClassConnect** - Empowering Digital Education 
