<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::startSession();
Auth::logout();

redirect(BASE_URL . '/admin/login');
