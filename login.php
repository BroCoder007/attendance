<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['teacher_id'])) {
    header('Location: teacher_dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Form expired, please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE email = ?");
        $stmt->execute([$email]);
        $teacher = $stmt->fetch();
        
        if ($teacher && password_verify($password, $teacher['password_hash'])) {
            // Success
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['teacher_id'] = $teacher['id'];
            $_SESSION['teacher_name'] = $teacher['name'];
            
            header('Location: teacher_dashboard.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Login</title>
    <!-- Use correct relative path depending on your setup. Usually just style.css if it's in the same folder -->
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
    <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">← Back to Portal</a>
    <h1 style="margin-top: 15px; text-align: left;">Staff Login</h1>
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label>Email Address
            <input type="email" name="email" required autofocus>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button type="submit">Login to Dashboard</button>
    </form>
</div>
</body>
</html>
