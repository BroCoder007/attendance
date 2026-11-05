<?php
require_once __DIR__ . '/config.php';

if (empty($_SESSION['student_roll_no'])) {
    header('Location: student_login.php');
    exit;
}

$rollNo = $_SESSION['student_roll_no'];
$studentName = $_SESSION['student_name'];
$error = '';
$success = '';

$pdo = get_db();
$clientIp = get_client_ip();

// Process check-in POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Session expired, please try again.';
    } else {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $classCode = trim($_POST['class_code'] ?? '');
        $deviceUuid = trim($_POST['device_uuid'] ?? '');
        
        // Fetch session
        $stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE id = ? AND is_active = 1");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch();
        
        if (!$session) {
            $error = 'This session is no longer active.';
        } elseif ($deviceUuid === '') {
            $error = 'Device identification failed. Please ensure cookies and JavaScript are enabled.';
        } else {
            $now = new DateTime();
            $starts = new DateTime($session['starts_at']);
            $ends   = new DateTime($session['ends_at']);
            $ipOk = ip_in_cidr($clientIp, $session['allowed_ip_cidr']);
            $timeOk = ($now >= $starts && $now <= $ends);
            
            if (!$ipOk) {
                $error = 'You must be connected to the classroom Wi-Fi network.';
            } elseif (!$timeOk) {
                $error = 'The attendance window for this session has closed.';
            } elseif ($session['rotating_code'] !== null && $classCode !== $session['rotating_code']) {
                log_attempt($pdo, $clientIp, $rollNo, false);
                $error = 'Incorrect Class Code.';
            } elseif (too_many_attempts($pdo, $clientIp, $rollNo)) {
                $error = 'Too many attempts. Please try again in 10 minutes.';
            } else {
                // 1. Session-level check: Has this device UUID already marked attendance for another student in this session?
                $stmt = $pdo->prepare("SELECT student_roll_no FROM device_tokens WHERE token = ? AND session_id = ?");
                $stmt->execute([$deviceUuid, $sessionId]);
                $tokenRecord = $stmt->fetch();
                
                if ($tokenRecord && $tokenRecord['student_roll_no'] !== $rollNo) {
                    $error = 'This device has already been used to mark attendance for another student in this session.';
                } else {
                    // 2. Permanent device check: Does this student already have a permanently bound device UUID?
                    $stmt = $pdo->prepare("SELECT device_uuid FROM students WHERE roll_no = ?");
                    $stmt->execute([$rollNo]);
                    $studentRow = $stmt->fetch();
                    $registeredDevice = $studentRow ? $studentRow['device_uuid'] : null;
                    
                    if ($registeredDevice !== null && $registeredDevice !== $deviceUuid) {
                        $error = 'This account is locked to a different device. Please contact your instructor to reset it.';
                    } else {
                        // Check if this device is registered to another student (permanent registration check)
                        $stmt = $pdo->prepare("SELECT roll_no, name FROM students WHERE device_uuid = ? AND roll_no != ?");
                        $stmt->execute([$deviceUuid, $rollNo]);
                        $otherStudent = $stmt->fetch();
                        
                        if ($otherStudent) {
                            $error = 'This device is already permanently registered to another student (' . htmlspecialchars($otherStudent['name']) . ').';
                        } else {
                            // Check if already checked in
                            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_roll_no = ?");
                            $stmt->execute([$sessionId, $rollNo]);
                            
                            if ($stmt->fetch()) {
                                $success = 'You are already marked present!';
                            } else {
                                try {
                                    log_attempt($pdo, $clientIp, $rollNo, true);
                                    
                                    // Lock device to student permanently if not already set
                                    if ($registeredDevice === null) {
                                        $stmt = $pdo->prepare("UPDATE students SET device_uuid = ? WHERE roll_no = ?");
                                        $stmt->execute([$deviceUuid, $rollNo]);
                                    }
                                    
                                    // Device token session registration (resume support / session token)
                                    $stmt = $pdo->prepare(
                                        "INSERT INTO device_tokens (token, session_id, student_roll_no, ip_address, status)
                                         VALUES (?, ?, ?, ?, 'completed')
                                         ON DUPLICATE KEY UPDATE token = VALUES(token), ip_address = VALUES(ip_address), status = 'completed'"
                                    );
                                    $stmt->execute([$deviceUuid, $sessionId, $rollNo, $clientIp]);
                                    
                                    // Mark attendance
                                    $stmt = $pdo->prepare(
                                        "INSERT INTO attendance (session_id, student_roll_no, ip_address, method)
                                         VALUES (?, ?, ?, 'self')"
                                    );
                                    $stmt->execute([$sessionId, $rollNo, $clientIp]);
                                    
                                    $success = '✅ Attendance marked successfully!';
                                } catch (PDOException $e) {
                                    // Already marked (race condition)
                                    $success = '✅ Attendance marked successfully!';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

// Fetch all active sessions
$stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE is_active = 1 ORDER BY created_at DESC");
$stmt->execute();
$activeSessions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
<div class="container">
    <div class="header">
        <div class="user-info">
            <span style="font-size: 1.8rem;">🎓</span>
            <div>
                <h2 style="margin: 0; line-height: 1.2; font-size: 1.2rem; color: #fff;"><?= htmlspecialchars($studentName) ?></h2>
                <span style="font-size: 0.85rem; color: var(--text-secondary);"><?= htmlspecialchars($rollNo) ?></span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="card wide" style="margin-top: 0;">
        <h2 style="margin-bottom: 8px;">Active Classes</h2>
        <p style="margin-bottom: 24px;">Your current active class attendance sessions. Make sure you are connected to the classroom Wi-Fi before checking in.</p>
        
        <?php if (empty($activeSessions)): ?>
            <div style="text-align: center; padding: 40px 0; color: var(--text-secondary);">
                <div style="font-size: 2.5rem; margin-bottom: 12px;">📭</div>
                No active attendance sessions right now. Please wait for your instructor to open one.
            </div>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Time Window</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeSessions as $session): 
                            $starts = new DateTime($session['starts_at']);
                            $ends   = new DateTime($session['ends_at']);
                            $now    = new DateTime();
                            
                            $ipOk = ip_in_cidr($clientIp, $session['allowed_ip_cidr']);
                            $timeOk = ($now >= $starts && $now <= $ends);
                            
                            // Check if student already checked in
                            $chkStmt = $pdo->prepare("SELECT marked_at FROM attendance WHERE session_id = ? AND student_roll_no = ?");
                            $chkStmt->execute([$session['id'], $rollNo]);
                            $attendanceRecord = $chkStmt->fetch();
                        ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($session['subject']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 2px;">
                                        Allowed IP: <?= htmlspecialchars($session['allowed_ip_cidr']) ?> (Your IP: <?= htmlspecialchars($clientIp) ?>)
                                    </div>
                                </td>
                                <td>
                                    <?= $starts->format('h:i A') ?> - <?= $ends->format('h:i A') ?>
                                </td>
                                <td>
                                    <?php if ($attendanceRecord): ?>
                                        <span class="badge badge-success">Present (<?= (new DateTime($attendanceRecord['marked_at']))->format('h:i A') ?>)</span>
                                    <?php elseif (!$timeOk): ?>
                                        <span class="badge badge-danger">Expired</span>
                                    <?php elseif (!$ipOk): ?>
                                        <span class="badge badge-danger" title="Please connect to classroom Wi-Fi">Wi-Fi Mismatch</span>
                                    <?php else: ?>
                                        <form method="POST" style="display: flex; gap: 8px; margin: 0; align-items: center;" autocomplete="off">
                                            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="checkin">
                                            <input type="hidden" name="session_id" value="<?= $session['id'] ?>">
                                            <input type="hidden" name="device_uuid" class="device-uuid-field" value="">
                                            
                                            <?php if ($session['rotating_code'] !== null): ?>
                                                <input type="text" name="class_code" placeholder="Code" style="width: 80px; margin: 0; padding: 8px 12px; font-size: 0.85rem;" required autocomplete="off">
                                            <?php endif; ?>
                                            
                                            <button type="submit" class="btn" style="width: auto; padding: 8px 16px; font-size: 0.85rem;">Check In</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let deviceUuid = localStorage.getItem('attendance_device_uuid');
    
    // Check if the cookie is set
    if (!deviceUuid) {
        deviceUuid = getCookie('attendance_device_uuid');
    }
    
    // If not found in either, generate a new one
    if (!deviceUuid) {
        deviceUuid = 'device_' + generateUUID();
    }
    
    // Ensure both cookie and localStorage are synchronized
    localStorage.setItem('attendance_device_uuid', deviceUuid);
    setCookie('attendance_device_uuid', deviceUuid, 365); // Save for 365 days
    
    // Inject the device UUID into all check-in forms
    const fields = document.querySelectorAll('.device-uuid-field');
    fields.forEach(field => {
        field.value = deviceUuid;
    });
});

function generateUUID() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
        const r = Math.random() * 16 | 0;
        const v = c === 'x' ? r : (r & 0x3 | 0x8);
        return v.toString(16);
    });
}

function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    return null;
}

function setCookie(name, value, days) {
    const date = new Date();
    date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
    const expires = "expires=" + date.toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)};${expires};path=/;SameSite=Strict`;
}
</script>
</body>
</html>
