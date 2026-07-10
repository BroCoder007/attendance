# Attendance Monitoring App (WAMP: Apache + MySQL + PHP)

## 1. Setup

### Prerequisites
- WAMP Server (WampServer with Apache, MySQL/MariaDB, and PHP) installed on Windows.
- Basic knowledge of using phpMyAdmin or MySQL Command Line.

### Step-by-Step Installation

1. **Deploy the Codebase**
   - Copy the `attendance/` project folder into your WAMP web root directory, typically located at `C:\wamp64\www\attendance`.
   - Ensure the web server has read access to these files.

2. **Database Initialization**
   - Open phpMyAdmin (usually available at `http://localhost/phpmyadmin`) or your preferred MySQL CLI.
   - Create a new database for the application (e.g., `attendance_db`).
   - Import the provided schema file located at `sql/schema.sql` into the newly created database.

3. **Configure Database Credentials**
   - Open the `config/config.php` file in a text editor.
   - Update the database connection constants (like `DB_USER` and `DB_PASS`) to match your MySQL environment. 
   - *Note: If using WampServer's default settings, the user is often `root` with no password, but setting a secure password is highly recommended.*

4. **Prepare the Logs Directory**
   - Create a new folder named `logs/` at the root of the project (`C:\wamp64\www\attendance\logs`).
   - Ensure this folder is writable by the web server so PHP can write error logs appropriately.

5. **Secure the Seeding Utility**
   - **CRITICAL:** The `tools/generate_hash.php` script is no longer needed for students (since we removed PINs), but you can still use it to generate secure `password_hash` strings for your **teachers** if you plan to build a teacher login page. Delete or restrict access to it before deploying.

6. **Start Using the Application**
   - Navigate your browser to `http://localhost/attendance/index.php`. This acts as the centralized landing portal.
   - **Students:** Click the **Student Portal** button, sign in/register with their Roll Number, and check into any active classes by inputting the Class Code.
   - **Teachers/Staff:** Click the **Staff Portal** button and log in to the Instructor Dashboard. From there, you can launch attendance sessions, close sessions, view live attendance logs, export attendance to CSV, or launch the QR barcode scanner.

## 2. How the anti-duplicate / "resume if disconnected" logic works

- Every attendance row has `UNIQUE(session_id, student_roll_no)` in MySQL — this
  is the actual guarantee. Even if two requests race each other, the
  database itself rejects the second insert.
- A `device_tokens` table (also unique per session+student) issues an
  httpOnly cookie the first time a student starts checking in. If their
  connection drops before the final insert completes, reloading the page
  re-checks the `attendance` table first — if it's not there yet, they can
  simply try again. If it *is* there, they see "already marked" and can't
  resubmit.
- Because the uniqueness is keyed on `student_roll_no` (verified via roll
  number), it doesn't matter which device/browser/tab they use to
  resume.

## 3. IP restriction & Class Code validation

`ip_in_cidr()` checks the request's IP against the CIDR you set per
session (e.g. your classroom Wi-Fi's DHCP subnet, `192.168.1.0/24`).

**Be aware:** IP-based restriction alone can be bypassed by anyone on the
same NAT/VPN, and is trivially spoofable if a student is technical enough
and connects through a proxy inside that subnet. For a stronger setup,
we use the `rotating_code` column in the database (Class Code). Display
a code on the classroom projector and require students to type it in
alongside their Roll Number. This closes the "gave someone outside the
room my Wi-Fi password" gap that plain IP checking doesn't cover.

## 4. Security measures already included

- PDO prepared statements everywhere (no SQL injection surface)
- Passwords/PINs stored with `password_hash()` / `password_verify()` (bcrypt)
- CSRF tokens on both forms and the QR-scan API
- httpOnly, SameSite=Strict session & device cookies (not readable by JS,
  not sent cross-site)
- Per-IP + per-roll-number rate limiting on failed login attempts
- Students never see a list of others' attendance — the self-checkin page
  only ever shows their own success/failure state
- `display_errors` off; errors go to a log file instead of leaking to users

## 5. Before going to production

- Set `'secure' => true` in the session cookie config once you're serving
  over HTTPS (recommended — get a free cert via Let's Encrypt or a
  self-signed cert for LAN-only use).
- Add real teacher authentication (login form + session check) in front of
  `staff/create_session.php` and `staff/scan.php` — right now they're open
  to keep the example focused.
- Change the MySQL root password / create a dedicated low-privilege DB user
  for this app instead of using `root`.
- Remove `tools/generate_hash.php` or move it outside the web root.
