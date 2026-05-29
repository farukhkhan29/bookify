 BookFlow — Appointment Booking System
A full-featured, self-hosted appointment booking system built with PHP and MySQL. Includes a beautiful admin dashboard, embeddable booking form, WhatsApp notifications, Mailgun email, Google Fonts, and full branding customization.
Show Image
Show Image
Show Image
Show Image

✨ Features
🖥️ Admin Dashboard

Clean SaaS-style dashboard — dark green sidebar, stat cards, Chart.js booking trends
Fully mobile responsive with hamburger navigation
Animated counters, GSAP page transitions, toast notifications
Today's schedule + recent bookings table

📋 Appointment Management

Filter, search, update all bookings in one place
Detail modal with all customer info, services, and custom fields
Status workflow: Pending → Confirmed → Completed / Cancelled
Multi-service booking — customers select multiple services per booking

💼 Services

Add/edit services with name, description, duration, price, color
3-mode icon picker: choose FontAwesome icons · upload SVG file · paste raw SVG code
Per-service closed date ranges (staff leave, holidays)

🕐 Time Slots

Add individual slots or generate time ranges
Auto-generate a full month of slots (configurable hours, interval, max bookings)
Edit per slot (start/end time, max bookings)
Select all + bulk delete
Per-slot availability progress bar

🚫 Blocked Dates

Interactive calendar with date range selection
Reason field, clear past dates in one click

📝 Form Builder

Custom fields: text, email, phone, textarea, select, checkbox, radio, date, number
3 visual templates + live style editor

🎨 Branding & Settings

8 one-click color presets (Forest Green, Ocean Blue, Purple, Sunset…)
Google Fonts search with live preview
Dashboard colors (primary, secondary, sidebar) + logo upload
Redirect URL after booking

📧 Email (Mailgun)

Customer confirmation + admin notification on every booking
AJAX settings save with toast feedback

💬 WhatsApp Notifications

Auto-message owner on every new booking
Supports Twilio (production) and CallMeBot (free)
Built-in sandbox setup guide + config debug panel

🔗 Embeddable Booking Form

3-step form: Service → Date & Time → Customer Info
Date selection auto-scrolls to slots; slot selection auto-advances to next step
iFrame iframe-break: window.top.location redirect so thank-you page opens in full browser
JS widget (inline / modal / button), React snippet, postMessage API


📁 Project Structure
appointment-system/
├── .htaccess                     ← Clean URLs + security
├── includes/
│   ├── config.example.php        ← Copy → config.php, fill credentials
│   ├── config.php                ← ⚠️  gitignored — never commit
│   ├── auth.php                  ← Sessions, CSRF, bcrypt
│   ├── mailer.php                ← Mailgun REST API
│   ├── whatsapp.php              ← Twilio / CallMeBot
│   └── schema.sql                ← Full MySQL schema + seed data
├── admin/
│   ├── layout/{header,footer}.php
│   ├── index.php                 ← Dashboard
│   ├── appointments.php
│   ├── services.php              ← Services + SVG icon picker
│   ├── slots.php                 ← Time slots + month generator
│   ├── calendar.php              ← Blocked dates
│   ├── form-builder.php
│   ├── settings.php
│   ├── ajax-settings.php         ← AJAX save handler
│   └── embed.php
├── embed/
│   ├── booking-form.php          ← Public booking form
│   └── widget.js                 ← JS widget loader
├── api/
│   └── slots.php                 ← Available slots endpoint
└── assets/
    ├── uploads/                  ← gitignored
    └── img/                      ← gitignored

🚀 Quick Start
Requirements

PHP 8.0+, MySQL 5.7+, Apache with mod_rewrite

1 — Clone
bashgit clone https://github.com/yourusername/bookflow.git
cd bookflow
2 — Database
bashmysql -u root -p -e "CREATE DATABASE bookflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p bookflow < includes/schema.sql
3 — Configure
bashcp includes/config.example.php includes/config.php
Edit includes/config.php:
phpdefine('DB_HOST',  'localhost');
define('DB_NAME',  'bookflow');
define('DB_USER',  'your_user');
define('DB_PASS',  'your_password');
define('BASE_URL', 'https://yourdomain.com/appointment-system');
4 — Permissions
bashchmod 755 assets/uploads assets/img
a2enmod rewrite && systemctl restart apache2
5 — Login
URL:      https://yourdomain.com/appointment-system/admin
Email:    admin@bookflow.com
Password: admin123   ← change this immediately

🔗 Embedding the Form
iFrame (recommended)
html<iframe
  src="https://yourdomain.com/appointment-system/book"
  data-redirect="https://yourdomain.com/thank-you"
  style="width:100%;min-height:680px;border:none;border-radius:12px"
  allowtransparency="true">
</iframe>

data-redirect redirects the full browser window (not just the iframe) to your thank-you page.

JS Widget — Modal
html<button id="book-now">Book Appointment</button>
<script src="https://yourdomain.com/appointment-system/embed/widget.js"
        data-target="book-now"
        data-mode="modal"
        data-primary-color="#22c55e">
</script>
Listen for booking events
javascriptwindow.addEventListener('message', (e) => {
  if (e.data?.type === 'bookflow:booked') {
    console.log('Booking confirmed!', e.data.ref);
  }
});

💬 WhatsApp Setup
CallMeBot (free):

Send I allow callmebot to send me messages to +34 644 52 74 69 on WhatsApp
You receive an API key → paste in Settings → WhatsApp

Twilio (production):

Create Twilio account → Messaging → WhatsApp Sandbox
Send join <your-word> to +1 415 523 8886 from your WhatsApp
Paste Account SID + Auth Token in Settings → WhatsApp


🛠️ Tech Stack
BackendPHP 8.0+ · PDO · no frameworkDatabaseMySQL / MariaDBFrontendTailwind CSS · GSAP 3 · Chart.jsIconsFontAwesome 6FontsGoogle Fonts APIEmailMailgun REST APIWhatsAppTwilio · CallMeBotAuthPHP sessions · bcrypt · CSRF

🔐 Security

config.php is in .gitignore — credentials never committed
All queries use PDO prepared statements
CSRF token on every form
HttpOnly + SameSite=Lax cookies
SVG uploads sanitized (scripts + event handlers stripped)
/includes/ blocked from direct web access


🗺️ Roadmap

 Google Calendar / Outlook sync
 Stripe / PayPal payments
 SMS notifications
 Multi-staff support
 Customer portal
 REST API


📄 License
MIT — free to use, modify, and distribute.
