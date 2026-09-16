<?php
// change_password_ajax.php - Handles AJAX password changes for any logged-in user
header('Content-Type: application/json');
require_once __DIR__ . '/auth.php';
requireAjaxAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$user = getCurrentUser();
if (!$user || empty($user['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Please log in again.']);
    exit;
}

$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword     = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

$result = changeUserPassword((int)$user['id'], $currentPassword, $newPassword, $confirmPassword);

if (!$result['success']) {
    echo json_encode([
        'success' => false,
        'error'   => $result['error'] ?? 'Could not change password.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $result['message'] ?? 'Password changed successfully!'
]);
