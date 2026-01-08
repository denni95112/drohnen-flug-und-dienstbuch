<?php
require_once __DIR__ . '/../includes/error_reporting.php';
include(__DIR__ . '/../includes/auth.php');
logout();
header('Location: ../index.php');
exit();
?>
