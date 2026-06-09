# InstCRM — Smart Management for Institutes & Coaching Centers

InstCRM is a comprehensive, multi-tenant web application designed to help coaching centers, schools, and educational institutes manage their day-to-day operations seamlessly. Built with core PHP and MySQL, it offers a blazing-fast, modern interface with zero framework bloat.

![InstCRM Dashboard](https://instcrm.in/assets/images/og-image.jpg)

## Features

- **Multi-Tenant Architecture**: Single codebase powers multiple independent institutions safely.
- **Enquiry Management**: Track leads, assign counsellors, schedule follow-ups, and convert enquiries to admissions.
- **Student & Admissions**: Manage student profiles, automated ID cards (with QR codes), and batch enrollments.
- **Attendance Tracking**: Lightning-fast daily attendance marking and live reporting.
- **Fee Management**: Create structured fee plans, accept partial/full payments, and generate beautiful, customizable receipts with authorized signatures.
- **Expense & Accounts**: Track daily expenses and monitor the financial health of the institute.
- **Staff Roles (RBAC)**: Fine-grained access control for Admins, Counsellors, Teachers, and Cashiers.
- **Public Profiles**: Public-facing pages for easy student self-registration and lead generation.
- **Subscription Billing Engine**: Built-in 3-day free trial engine with monthly/yearly upgrade locks for SaaS operators.

## Tech Stack
- **Backend**: Vanilla PHP 8.x
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3 (Custom Variables/Design System), Vanilla JavaScript
- **Icons**: Bootstrap Icons

## Installation

Installing InstCRM takes less than a minute.

1. **Clone the repository**
   ```bash
   git clone https://github.com/krsaurabhmca/instCRM.git
   cd instCRM
   ```

2. **Serve the Application**
   Host the directory using Apache/Nginx or local tools like Laragon, XAMPP, or the PHP built-in server:
   ```bash
   php -S localhost:8000
   ```

3. **Run the Auto-Installer**
   - Open your browser and navigate to the application URL (e.g., `http://localhost/instcrm`).
   - The application will automatically detect that it is not configured and redirect you to the visual installer.
   - Enter your Database Credentials. The installer will automatically create the database and import `instcrm.sql`.
   - Start registering your first institution!

## Directory Structure
- `/assets` - CSS, JS, and Images
- `/auth` - Login, Logout, Registration flows
- `/config` - Database and Core App configurations (`db.php` is generated on install)
- `/includes` - Reusable headers, footers, and sidebars
- `/uploads` - Institute logos, signatures, and user-generated content

## License
All rights reserved.
