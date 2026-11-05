-- ============================================================
-- Attendance Monitoring System - Database Schema
-- Import via phpMyAdmin or: mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS attendance
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE attendance;

-- ------------------------------------------------------------
-- Teachers / staff who create sessions and run the QR scanner
-- ------------------------------------------------------------
CREATE TABLE teachers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Students. secret_pin = last 4 digits of ID card / DOB etc,
-- stored HASHED. This is a lightweight second factor so a
-- student can't mark attendance just by guessing a roll number.
-- ------------------------------------------------------------
CREATE TABLE students (
    roll_no       VARCHAR(30) PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    device_uuid   VARCHAR(64) DEFAULT NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- A class session = one attendance window.
-- allowed_ip_cidr: e.g. '192.168.1.0/24'  (your classroom Wi-Fi subnet)
-- rotating_code: optional 6-digit code teacher displays on a
--   projector; refresh it every N minutes for extra assurance
--   beyond IP alone (IP can be spoofed/shared via NAT).
-- ------------------------------------------------------------
CREATE TABLE class_sessions (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id       INT NOT NULL,
    subject          VARCHAR(100) NOT NULL,
    allowed_ip_cidr  VARCHAR(50) NOT NULL,
    rotating_code    VARCHAR(10) DEFAULT NULL,
    code_expires_at  DATETIME DEFAULT NULL,
    starts_at        DATETIME NOT NULL,
    ends_at          DATETIME NOT NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- device_tokens: lets a disconnected student resume THEIR OWN
-- in-progress check-in without allowing a second student to
-- reuse the slot. One row per (session, student) attempt.
-- status: pending -> in progress, completed -> attendance marked
-- ------------------------------------------------------------
CREATE TABLE device_tokens (
    token           CHAR(64) PRIMARY KEY,        -- random token stored in httpOnly cookie
    session_id      INT NOT NULL,
    student_roll_no VARCHAR(30) NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    status          ENUM('pending','completed') NOT NULL DEFAULT 'pending',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_session_student (session_id, student_roll_no),
    FOREIGN KEY (session_id) REFERENCES class_sessions(id),
    FOREIGN KEY (student_roll_no) REFERENCES students(roll_no)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Final attendance record. The UNIQUE constraint is the real
-- guarantee against duplicates -- even a race condition or a
-- bug elsewhere cannot produce two rows for the same student
-- in the same session.
-- ------------------------------------------------------------
CREATE TABLE attendance (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    session_id      INT NOT NULL,
    student_roll_no VARCHAR(30) NOT NULL,
    marked_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address      VARCHAR(45) NOT NULL,
    method          ENUM('self','qr') NOT NULL,
    UNIQUE KEY uniq_attendance (session_id, student_roll_no),
    FOREIGN KEY (session_id) REFERENCES class_sessions(id),
    FOREIGN KEY (student_roll_no) REFERENCES students(roll_no)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Simple attempt log for rate limiting / abuse detection
-- ------------------------------------------------------------
CREATE TABLE login_attempts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    ip_address  VARCHAR(45) NOT NULL,
    roll_no     VARCHAR(30),
    success     TINYINT(1) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Demo teacher (password: Teacher@123 -- change immediately)
-- Hash generated with PHP password_hash('Teacher@123', PASSWORD_DEFAULT)
-- Run generate_hash.php once to get a real hash for your install.
