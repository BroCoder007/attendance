<?php
require_once __DIR__ . '/config.php';

// If already logged in, redirect to respective dashboard
if (!empty($_SESSION['teacher_id'])) {
    header('Location: teacher_dashboard.php');
    exit;
}
if (!empty($_SESSION['student_roll_no'])) {
    header('Location: student_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduTrack - Attendance Simplified</title>
    <!-- Add cache-buster to ensure the newest CSS loads -->
    <link rel="stylesheet" href="style.css?v=<?= time() ?>">
</head>
<body>

    <nav class="navbar">
        <div class="logo">EduTrack</div>
    </nav>

    <main class="hero">
        <h1 class="hero-title">EduTrack.</h1>
        
        <div class="hero-image-wrapper">
            <div class="green-backdrop"></div>
            <div class="mockup-container">
                <img src="classroom_hero.png" alt="Classroom" class="hero-img">
                
                <!-- Floating Action Box over the image -->
                <div class="floating-box">
                    <h3>Welcome to EduTrack</h3>
                    <p>Select your portal to check-in or manage sessions.</p>
                    <div class="floating-buttons">
                        <a href="student_login.php" class="btn-primary">Student Check-In</a>
                        <a href="login.php" class="btn-dark">Staff Dashboard ↗</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>
