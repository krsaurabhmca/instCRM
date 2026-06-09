<?php
// index.php
require_once __DIR__ . '/config/app.php';

if (is_logged_in()) {
    redirect('/dashboard.php');
} else {
    redirect('/auth/login.php');
}
?>
