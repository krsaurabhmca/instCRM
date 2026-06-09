<?php
// auth/logout.php
require_once dirname(__DIR__) . '/config/app.php';
session_unset();
session_destroy();
redirect('/auth/login.php');
?>
