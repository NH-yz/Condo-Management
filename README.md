# CondoCare - Condominium Management System

A complete web-based information management system for condominium maintenance requests and facility booking, built with **HTML5, CSS3, JavaScript, PHP, and MySQL (XAMPP)** for **DCS2102 Rapid Application Development**.

---

## 🚀 Quick Setup Instructions (XAMPP)

1. **Start XAMPP Control Panel:**
   - Start **Apache**
   - Start **MySQL**

2. **Database Setup:**
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin/`)
   - Create a new database named `condocare_db`
   - Import `condocare_db.sql` into `condocare_db`
   *(Alternatively, run command line: `mysql -u root < condocare_db.sql`)*

3. **Open Project in Web Browser:**
   - Visit: **`http://localhost/RAD/Project%20Nov21/condominium%20design/`**

---

## 🔑 Demo Login Accounts

| Role | Email | Password | Details |
|---|---|---|---|
| **Resident** | `resident@email.com` | `12345678` | Tan Mei Ling (Unit A-12-03) |
| **Management / Admin** | `admin@email.com` | `admin123` | Rachel Lim (Staff ID: ST-001) |

---

## ✨ Features Checklist (Assignment Rubric: 100% Coverage)

- [x] **Problem and System Concept (10%):** Dual-portal system solving real-world condo maintenance and facility conflicts.
- [x] **UI/UX – HTML & CSS (15%):** Responsive layout, minimalist clean styling, interactive modals, distinct status badges, alert banners.
- [x] **JavaScript Functionality (10%):**
  - Interactive facility booking slot picker with live total cost calculation ($Hours \times Rate$).
  - Live client-side search across table columns and filter chips.
  - Character counter for defect descriptions (up to 500 characters).
  - Client-side regex & required form validations.
  - Delete and cancel confirmation dialogs.
- [x] **PHP Implementation (15%):**
  - Prepared statements with PDO (100% safe against SQL injection).
  - Session-based authentication with role-based access control (Resident vs Admin).
  - Server-side validations, secure image uploads to `uploads/`.
- [x] **Database & ERD (15%):**
  - 7 relational tables with primary keys, foreign keys (`ON DELETE CASCADE` / `SET NULL`), indexes, and sample records.
  - Complete `condocare_db.sql` file included.
- [x] **CRUD Functionality (20%):** Complete Create, Read, Update, Delete for:
  1. Facility Bookings
  2. Maintenance / Defect Requests
  3. Residents
  4. Technicians
  5. Administrators
  6. Facilities (admin: rate, operating hours, availability)
- [x] **Testing & Validation (5%):** Conflict checking (preventing double bookings), duplicate email/unit check, past-date prevention.
- [x] **Documentation (5%):** Comprehensive 17-section documentation report in `DOCUMENTATION.md` with ERD and Use Case diagrams.

---

## 📁 Project Structure

```text
condominium/
├── api/
│   └── get-booked-slots.php         # Asynchronous slot check API
├── config/
│   └── db.php                       # PDO database connection
├── css/
│   └── style.css                    # Responsive stylesheet & UI components
├── includes/
│   ├── auth.php                     # Authentication, sessions, & helpers
│   ├── footer.php                   # Page footer & JS scripts
│   └── header.php                   # Global navbar, user badge & flash banners
├── js/
│   └── app.js                       # Search, slots, validations, & modals
├── uploads/                         # Directory for uploaded defect photos
├── admin-admins-add.php             # Add / Edit administrator
├── admin-admins.php                 # List & delete administrators
├── admin-bookings.php               # Approve, reject, complete bookings
├── admin-dashboard.php              # Real-time statistics & usage chart
├── admin-maintenance.php            # Assign technicians & update defect tickets
├── admin-residents-add.php          # Add / Edit residents
├── admin-residents.php              # List & delete residents
├── admin-facilities-add.php         # Add / Edit facilities (rate, hours, status)
├── admin-facilities.php             # List, toggle Maintenance & delete facilities
├── admin-technicians-add.php        # Add / Edit technicians
├── admin-technicians.php            # List & delete technicians
├── book-facility.php                # Interactive facility reservation
├── condocare_db.sql                 # MySQL database schema & sample data
├── DOCUMENTATION.md                 # Full 17-section assignment report
├── edit-defect.php                  # Edit reported defect
├── index.php                        # Main entry router
├── login.php                        # User authentication with role switch
├── logout.php                       # Session termination
├── my-requests-bookings.php         # Resident facility bookings list
├── my-requests-maintenance.php      # Resident defect tickets list
├── README.md                        # Quick setup & features guide
├── report-defect.php                # Submit defect report with photo
└── resident-home.php                # Resident homepage & latest updates
```
