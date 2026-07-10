<?php
require_once __DIR__ . '/config.php';

if (!empty($_SESSION['student_roll_no'])) {
    header('Location: student_dashboard.php');
    exit;
}

$error = '';
$showNameField = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf'] ?? '')) {
        $error = 'Form expired, please try again.';
    } else {
        $rollNo = strtoupper(trim($_POST['roll_no'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        
        if ($rollNo === '') {
            $error = 'Roll number is required.';
        } else {
            $pdo = get_db();
            $stmt = $pdo->prepare("SELECT * FROM students WHERE roll_no = ?");
            $stmt->execute([$rollNo]);
            $student = $stmt->fetch();
            
            if ($student) {
                // Log in
                $_SESSION['student_roll_no'] = $student['roll_no'];
                $_SESSION['student_name'] = $student['name'];
                header('Location: student_dashboard.php');
                exit;
            } else {
                // Try registering
                if ($name === '') {
                    $error = 'Roll number not found. If this is your first time, please enter your name to register.';
                    $showNameField = true;
                } else {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO students (roll_no, name) VALUES (?, ?)");
                        $stmt->execute([$rollNo, $name]);
                        
                        $_SESSION['student_roll_no'] = $rollNo;
                        $_SESSION['student_name'] = $name;
                        header('Location: student_dashboard.php');
                        exit;
                    } catch (PDOException $e) {
                        $error = 'Registration failed: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
    <a href="index.php" style="color: var(--text-secondary); text-decoration: none; font-size: 0.9rem;">← Back to Portal</a>
    <h1 style="margin-top: 15px; text-align: left;">Student Portal</h1>
    
    <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    
    <form method="POST" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label>Roll Number
            <input type="text" name="roll_no" value="<?= htmlspecialchars($_POST['roll_no'] ?? '') ?>" placeholder="e.g. 22BCE1234" required autofocus>
        </label>
        
        <?php if ($showNameField): ?>
        <label>Full Name
            <input type="text" name="name" placeholder="e.g. Alex Smith" required>
        </label>
        <?php endif; ?>
        
        <button type="submit"><?= $showNameField ? 'Register & Log In' : 'Sign In' ?></button>
    </form>
    
    <?php if (!$showNameField): ?>
    <p class="hint">First-time users will be prompted to register their name automatically.</p>
    <?php endif; ?>
</div>
</body>
</html>
