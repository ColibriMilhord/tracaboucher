<?php
require_once __DIR__ . '/includes/auth.php';
deconnexion();
header('Location: login.php');
exit;
