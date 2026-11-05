<?php
require_once __DIR__ . '/config.php';
$teacher = require_teacher();

$sessionId = isset($_GET['session']) ? (int)$_GET['session'] : 0;
$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM class_sessions WHERE id = ? AND is_active = 1");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
if (!$session) { die('Invalid or inactive session.'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ID Card Scanner - <?= htmlspecialchars($session['subject']) ?></title>
<link rel="stylesheet" href="style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html5-qrcode/2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <a href="teacher_dashboard.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">← Dashboard</a>
        <a href="logout.php" class="logout-btn" style="padding: 6px 12px; font-size: 0.8rem;">Logout</a>
    </div>
    <h1 style="margin-top: 15px; text-align: left;">Scan ID Card - <?= htmlspecialchars($session['subject']) ?></h1>
    <p class="hint">Hold each student's ID card barcode/QR up to the camera. The system marks them present and shows a confirmation.</p>
    <div id="reader" style="width:100%"></div>
    <div id="result" class="result"></div>
    <ul id="log"></ul>
</div>

<script>
const sessionId = <?= json_encode($sessionId) ?>;
const csrfToken = <?= json_encode(csrf_token()) ?>;
const resultBox = document.getElementById('result');
const logList = document.getElementById('log');
let lastScanned = null;
let lastScanTime = 0;

function onScanSuccess(decodedText) {
    const now = Date.now();
    // debounce: ignore the same code re-fired within 3s (camera re-reads fast)
    if (decodedText === lastScanned && (now - lastScanTime) < 3000) return;
    lastScanned = decodedText;
    lastScanTime = now;

    fetch('mark_qr.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ session_id: sessionId, barcode: decodedText, csrf: csrfToken })
    })
    .then(r => r.json())
    .then(data => {
        resultBox.textContent = data.message;
        resultBox.className = 'result ' + (data.success ? 'success' : 'error');
        const li = document.createElement('li');
        li.textContent = data.message;
        logList.prepend(li);
    })
    .catch(() => {
        resultBox.textContent = 'Network error, try again.';
        resultBox.className = 'result error';
    });
}

const scanner = new Html5Qrcode("reader");
Html5Qrcode.getCameras().then(cameras => {
    if (cameras && cameras.length) {
        scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            onScanSuccess
        );
    }
}).catch(err => {
    document.getElementById('reader').textContent = 'Camera access denied or unavailable: ' + err;
});
</script>
</body>
</html>
