-- BookFlow Appointment System Database Schema

CREATE DATABASE IF NOT EXISTS bookflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookflow;

-- Users (Admin)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','staff') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Services
CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    duration INT DEFAULT 60 COMMENT 'Duration in minutes',
    price DECIMAL(10,2) DEFAULT 0.00,
    color VARCHAR(7) DEFAULT '#6366f1',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Service Closed Dates
CREATE TABLE IF NOT EXISTS service_closed_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    closed_date DATE NOT NULL,
    reason VARCHAR(255),
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Time Slots
CREATE TABLE IF NOT EXISTS time_slots (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT,
    slot_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    max_bookings INT DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

-- Blocked/Off Dates (shop closed)
CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocked_date DATE NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Form Fields
CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(150) NOT NULL,
    field_type ENUM('text','email','tel','textarea','select','checkbox','radio','date','number') DEFAULT 'text',
    field_options TEXT COMMENT 'JSON for select/radio/checkbox options',
    is_required TINYINT(1) DEFAULT 0,
    placeholder VARCHAR(255),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1
);

-- Appointments
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) UNIQUE NOT NULL,
    service_id INT,
    time_slot_id INT,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(150) NOT NULL,
    customer_phone VARCHAR(30),
    notes TEXT,
    form_data JSON COMMENT 'Dynamic form fields data',
    status ENUM('pending','confirmed','cancelled','completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL,
    FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE SET NULL
);

-- Form Templates
CREATE TABLE IF NOT EXISTS form_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    template_data JSON COMMENT 'Complete form configuration',
    is_default TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Form Style Settings
CREATE TABLE IF NOT EXISTS form_styles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    style_name VARCHAR(100) DEFAULT 'default',
    primary_color VARCHAR(7) DEFAULT '#6366f1',
    button_color VARCHAR(7) DEFAULT '#6366f1',
    button_text_color VARCHAR(7) DEFAULT '#ffffff',
    button_border_radius VARCHAR(20) DEFAULT '8px',
    field_border_radius VARCHAR(20) DEFAULT '6px',
    field_padding VARCHAR(20) DEFAULT '12px 16px',
    font_family VARCHAR(100) DEFAULT 'Inter',
    background_color VARCHAR(7) DEFAULT '#ffffff',
    text_color VARCHAR(7) DEFAULT '#1f2937',
    is_active TINYINT(1) DEFAULT 1
);

-- Email Logs
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT,
    to_email VARCHAR(150),
    subject VARCHAR(255),
    status ENUM('sent','failed') DEFAULT 'sent',
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
);

-- Insert default settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'BookFlow'),
('site_logo', ''),
('dashboard_primary_color', '#6366f1'),
('dashboard_secondary_color', '#8b5cf6'),
('dashboard_sidebar_color', '#0f172a'),
('mailgun_api_key', ''),
('mailgun_domain', ''),
('from_email', 'noreply@yourdomain.com'),
('from_name', 'BookFlow'),
('to_email', 'admin@yourdomain.com'),
('redirect_url', ''),
('timezone', 'UTC'),
('active_template_id', '1'),
('active_style_id', '1');

-- Insert default admin user (password: admin123)
INSERT IGNORE INTO users (name, email, password, role) VALUES
('Administrator', 'admin@bookflow.com', '$2y$12$rS.e3SJKjdt2tI4rSHktReyCZ/o5m1A3yYoHM38olkHJf1LZAx39q', 'admin');

-- Insert default services
INSERT IGNORE INTO services (name, description, duration, price, color) VALUES
('General Consultation', 'Standard 60-minute consultation session', 60, 50.00, '#6366f1'),
('Follow-up Visit', 'Quick 30-minute follow-up appointment', 30, 25.00, '#8b5cf6'),
('Extended Session', 'Comprehensive 90-minute extended session', 90, 90.00, '#06b6d4');

-- Insert default form fields
INSERT IGNORE INTO form_fields (field_name, field_label, field_type, is_required, placeholder, sort_order) VALUES
('customer_name', 'Full Name', 'text', 1, 'Enter your full name', 1),
('customer_email', 'Email Address', 'email', 1, 'Enter your email', 2),
('customer_phone', 'Phone Number', 'tel', 0, 'Enter your phone number', 3),
('notes', 'Additional Notes', 'textarea', 0, 'Any special requests or notes...', 4);

-- Insert default form style
INSERT IGNORE INTO form_styles (id, style_name) VALUES (1, 'Default Style');

-- Insert default form templates
INSERT IGNORE INTO form_templates (id, name, description, is_default) VALUES
(1, 'Modern Minimal', 'Clean and minimal booking form with subtle shadows', 1),
(2, 'Bold Vibrant', 'Eye-catching form with bold colors and strong typography', 0),
(3, 'Corporate Professional', 'Professional form suitable for business appointments', 0);
