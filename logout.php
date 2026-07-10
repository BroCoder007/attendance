<?php
require_once __DIR__ . '/config.php';
unset($_SESSION['teacher_id']);
unset($_SESSION['teacher_name']);
unset($_SESSION['student_roll_no']);
unset($_SESSION['student_name']);
session_destroy();
header('Location: index.php');
exit;
