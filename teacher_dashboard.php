<?php
require_once __DIR__ . '/config.php';
$teacher = require_teacher();
$pdo = get_db();

$error = '';
$success = '';

// Handle creating a new session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_session') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Form expired, please try again.';
    } else {
        $subject   = trim($_POST['subject'] ?? '');
        $cidr      = trim($_POST['cidr'] ?? '192.168.1.0/24');
        $starts    = $_POST['starts_at'] ?? '';
        $ends      = $_POST['ends_at'] ?? '';
        $classCode = trim($_POST['class_code'] ?? '');
        
        if ($classCode === '') $classCode = null;
        
        if ($subject === '' || $starts === '' || $ends === '') {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO class_sessions (teacher_id, subject, allowed_ip_cidr, rotating_code, starts_at, ends_at, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt->execute([$teacher['id'], $subject, $cidr, $classCode, $starts, $ends]);
                $success = 'Attendance session created successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to create session: ' . $e->getMessage();
            }
        }
    }
}

// Handle closing a session manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'close_session') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Form expired, please try again.';
    } else {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE class_sessions SET is_active = 0 WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$sessionId, $teacher['id']]);
            $success = 'Session closed successfully!';
        } catch (PDOException $e) {
            $error = 'Failed to close session: ' . $e->getMessage();
        }
    }
}

// Handle resetting a student's device lock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_device') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Form expired, please try again.';
    } else {
        $rollNo = strtoupper(trim($_POST['roll_no'] ?? ''));
        if ($rollNo === '') {
            $error = 'Please enter a student roll number.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT name FROM students WHERE roll_no = ?");
                $stmt->execute([$rollNo]);
                $student = $stmt->fetch();
                
                if (!$student) {
                    $error = 'Student with Roll Number ' . htmlspecialchars($rollNo) . ' not found.';
                } else {
                    $stmt = $pdo->prepare("UPDATE students SET device_uuid = NULL WHERE roll_no = ?");
                    $stmt->execute([$rollNo]);
                    $success = 'Successfully reset device lock for ' . htmlspecialchars($student['name']) . ' (' . htmlspecialchars($rollNo) . ').';
                }
            } catch (PDOException $e) {
                $error = 'Failed to reset device lock: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all sessions created by this teacher
$stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$teacher['id']]);
$allSessions = $stmt->fetchAll();

$activeSessions = [];
$pastSessions = [];

foreach ($allSessions as $sess) {
    if ($sess['is_active']) {
        $activeSessions[] = $sess;
    } else {
        $pastSessions[] = $sess;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
<div class="container">
    <div class="header">
        <div class="user-info">
            <span style="font-size: 1.8rem;">🏫</span>
            <div>
                <h2 style="margin: 0; line-height: 1.2; font-size: 1.2rem; color: #fff;"><?= htmlspecialchars($teacher['name']) ?></h2>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">Instructor (<?= htmlspecialchars($teacher['email']) ?>)</span>
            </div>
        </div>
        <a href="logout.php" class="logout-btn">Log Out</a>
    </div>

    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="grid-2">
        <!-- Sessions Column -->
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <!-- Active Sessions Card -->
            <div class="card wide" style="margin: 0;">
                <h2>Active Attendance Windows</h2>
                
                <?php if (empty($activeSessions)): ?>
                    <p style="text-align: center; padding: 20px 0;">No active sessions. Start one on the right!</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Class Code</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($activeSessions as $sess): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($sess['subject']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px;">
                                                IP: <?= htmlspecialchars($sess['allowed_ip_cidr']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code style="background: rgba(255,255,255,0.08); padding: 4px 8px; border-radius: 4px;">
                                                <?= $sess['rotating_code'] ? htmlspecialchars($sess['rotating_code']) : 'None Required' ?>
                                            </code>
                                        </td>
                                        <td>
                                            <div class="session-actions">
                                                <a href="scan.php?session=<?= $sess['id'] ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 12px;">Scan QR</a>
                                                <a href="view_attendance.php?session=<?= $sess['id'] ?>" class="btn" style="font-size: 0.8rem; padding: 6px 12px;">Live View</a>
                                                
                                                <form method="POST" style="display: inline; margin: 0;">
                                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="close_session">
                                                    <input type="hidden" name="session_id" value="<?= $sess['id'] ?>">
                                                    <button type="submit" class="btn btn-danger" style="font-size: 0.8rem; padding: 6px 12px; width: auto;">Close</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Sessions Card -->
            <div class="card wide" style="margin: 0;">
                <h2>History / Closed Sessions</h2>
                
                <?php if (empty($pastSessions)): ?>
                    <p style="text-align: center; padding: 20px 0;">No completed sessions found.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Report</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pastSessions as $sess): 
                                    $date = new DateTime($sess['starts_at']);
                                ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: #fff;"><?= htmlspecialchars($sess['subject']) ?></div>
                                        </td>
                                        <td>
                                            <?= $date->format('M d, Y h:i A') ?>
                                        </td>
                                        <td>
                                            <a href="view_attendance.php?session=<?= $sess['id'] ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 6px 12px; width: auto;">View Log</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Create & Management Column -->
        <div style="display: flex; flex-direction: column; gap: 24px; width: 100%;">
            <!-- Create Session Card -->
            <div class="card" style="margin: 0; width: 100%;">
                <h2>Create New Window</h2>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="create_session">
                    
                    <label>Subject Name
                        <input type="text" name="subject" placeholder="e.g. Computer Networks" required>
                    </label>
                    
                    <label>Allowed Subnet (CIDR)
                        <input type="text" name="cidr" value="192.168.1.0/24" required>
                    </label>
                    
                    <label>Class Code (Optional)
                        <input type="text" name="class_code" placeholder="e.g. 5928 (helps prevent proxies)" maxlength="10">
                    </label>
                    
                    <label>Starts At
                        <input type="datetime-local" name="starts_at" value="<?= (new DateTime())->format('Y-m-d\TH:i') ?>" required>
                    </label>
                    
                    <label>Ends At
                        <input type="datetime-local" name="ends_at" value="<?= (new DateTime('+1 hour'))->format('Y-m-d\TH:i') ?>" required>
                    </label>
                    
                    <button type="submit">Launch Attendance Window</button>
                </form>
            </div>

            <!-- Reset Device Card -->
            <div class="card" style="margin: 0; width: 100%;">
                <h2>Reset Student Device Lock</h2>
                <p style="margin-bottom: 16px; font-size: 0.9rem; color: var(--text-secondary);">
                    Use this to clear a student's bound device (e.g. if they got a new phone or logged into the wrong device).
                </p>
                <form method="POST" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                    <input type="hidden" name="action" value="reset_device">
                    
                    <label>Student Roll Number
                        <input type="text" name="roll_no" placeholder="e.g. 22BCE1234" required>
                    </label>
                    
                    <button type="submit" class="btn btn-danger" style="margin-top: 8px;">Reset Device Lock</button>
                </form>
            </div>
        </div>
    </div>
</div>
</body>
</html>
