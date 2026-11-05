<?php
require_once __DIR__ . '/config.php';
$teacher = require_teacher();
$pdo = get_db();

$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;

// Fetch session info
$stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE id = ? AND teacher_id = ?");
$stmt->execute([$sessionId, $teacher['id']]);
$session = $stmt->fetch();

if (!$session) {
    die("Session not found or access denied.");
}

// Fetch all present students for this session
$stmt = $pdo->prepare(
    "SELECT a.marked_at, a.ip_address, a.method, s.roll_no as student_roll_no, s.name as student_name
     FROM attendance a
     JOIN students s ON a.student_roll_no = s.roll_no
     WHERE a.session_id = ?
     ORDER BY a.marked_at DESC"
);
$stmt->execute([$sessionId]);
$attendance = $stmt->fetchAll();

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="attendance_' . preg_replace('/[^a-zA-Z0-9]/', '_', $session['subject']) . '_session_' . $session['id'] . '.csv"');
    
    $output = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Roll Number', 'Student Name', 'Time Marked', 'IP Address', 'Verification Method']);
    
    foreach ($attendance as $record) {
        fputcsv($output, [
            $record['student_roll_no'],
            $record['student_name'],
            $record['marked_at'],
            $record['ip_address'],
            ucfirst($record['method'])
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Log - <?= htmlspecialchars($session['subject']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
<div class="container">
    <div class="header">
        <a href="teacher_dashboard.php" class="logout-btn" style="background: rgba(255,255,255,0.05); color: #fff; border-color: var(--border-color);">← Back to Dashboard</a>
        <div style="display: flex; gap: 10px;">
            <a href="?session=<?= $session['id'] ?>&export=csv" class="btn" style="width: auto; font-size: 0.9rem; padding: 8px 16px;">Export CSV</a>
        </div>
    </div>

    <div class="card wide" style="margin: 0 0 24px 0;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <span class="badge <?= $session['is_active'] ? 'badge-success' : 'badge-danger' ?>" style="margin-bottom: 8px;">
                    <?= $session['is_active'] ? 'Active Session' : 'Closed Session' ?>
                </span>
                <h1 class="left-align" style="margin-bottom: 6px;"><?= htmlspecialchars($session['subject']) ?></h1>
                <p style="margin: 0; font-size: 0.9rem;">
                    Created on: <?= (new DateTime($session['created_at']))->format('M d, Y h:i A') ?>
                </p>
            </div>
            
            <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--border-color); border-radius: 12px; padding: 16px; display: grid; grid-template-columns: auto auto; gap: 8px 16px; font-size: 0.9rem;">
                <span style="color: var(--text-secondary);">Class Code:</span>
                <strong style="color: #fff;"><?= $session['rotating_code'] ? htmlspecialchars($session['rotating_code']) : 'None' ?></strong>
                
                <span style="color: var(--text-secondary);">IP Subnet:</span>
                <strong style="color: #fff;"><?= htmlspecialchars($session['allowed_ip_cidr']) ?></strong>
                
                <span style="color: var(--text-secondary);">Start Time:</span>
                <strong style="color: #fff;"><?= (new DateTime($session['starts_at']))->format('h:i A') ?></strong>
                
                <span style="color: var(--text-secondary);">End Time:</span>
                <strong style="color: #fff;"><?= (new DateTime($session['ends_at']))->format('h:i A') ?></strong>
            </div>
        </div>
    </div>

    <div class="card wide" style="margin: 0;">
        <h2 style="display: flex; justify-content: space-between; align-items: center;">
            <span>Present Students</span>
            <span class="badge badge-info" style="font-size: 0.9rem; padding: 6px 12px;"><?= count($attendance) ?> Marked Present</span>
        </h2>
        
        <?php if (empty($attendance)): ?>
            <p style="text-align: center; padding: 40px 0; color: var(--text-secondary);">No student has checked into this class yet.</p>
        <?php else: ?>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Time Marked</th>
                            <th>IP Address</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attendance as $record): ?>
                            <tr>
                                <td style="font-weight: 600; color: #fff;"><?= htmlspecialchars($record['student_roll_no']) ?></td>
                                <td><?= htmlspecialchars($record['student_name']) ?></td>
                                <td><?= (new DateTime($record['marked_at']))->format('h:i:s A') ?></td>
                                <td><?= htmlspecialchars($record['ip_address']) ?></td>
                                <td>
                                    <?php if ($record['method'] === 'self'): ?>
                                        <span class="badge badge-info">Self Check-In</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">QR Scanned</span>
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
</body>
</html>
