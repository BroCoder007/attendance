<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

$pdo = get_db();
$input = json_decode(file_get_contents('php://input'), true);

$sessionId = (int)($input['session_id'] ?? 0);
$barcode   = trim($input['barcode'] ?? '');
$csrf      = $input['csrf'] ?? '';

function respond(bool $success, string $message): void {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!csrf_verify($csrf)) {
    respond(false, 'Invalid session, please reload the scanner page.');
}
if (!$sessionId || !$barcode) {
    respond(false, 'Missing data.');
}

$stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE id = ? AND is_active = 1");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { respond(false, 'Session not found or closed.'); }

$now = new DateTime();
if ($now < new DateTime($session['starts_at']) || $now > new DateTime($session['ends_at'])) {
    respond(false, 'Attendance window is closed.');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE roll_no = ?");
$stmt->execute([$barcode]);
$student = $stmt->fetch();
if (!$student) {
    respond(false, 'Unrecognized ID card.');
}

// Already marked?
$stmt = $pdo->prepare("SELECT id FROM attendance WHERE session_id = ? AND student_roll_no = ?");
$stmt->execute([$sessionId, $student['roll_no']]);
if ($stmt->fetch()) {
    respond(false, $student['name'] . ' (' . $student['roll_no'] . ') already marked present.');
}

try {
    $stmt = $pdo->prepare(
        "INSERT INTO attendance (session_id, student_roll_no, ip_address, method) VALUES (?, ?, ?, 'qr')"
    );
    $stmt->execute([$sessionId, $student['roll_no'], get_client_ip()]);
    respond(true, '✅ ' . $student['name'] . ' (' . $student['roll_no'] . ') marked present.');
} catch (PDOException $e) {
    // duplicate key race
    respond(false, $student['name'] . ' already marked present.');
}
