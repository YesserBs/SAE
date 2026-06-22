<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Auth.php';

// Détruit complètement la session
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;