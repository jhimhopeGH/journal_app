<?php
// logout.php - Logs out the current user and clears session
require_once __DIR__ . '/auth.php';

logActivity('logout', 'auth', 'Logged out');
logoutUser();
header('Location: login.php?msg=logged_out');
exit;
