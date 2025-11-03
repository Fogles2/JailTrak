<?php
function redirectTo($url, $statusCode = 302) {
    header('Location: ' . $url, true, $statusCode);
    exit();
}

function formatDate($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

function timeAgo($datetime) {
    // Implement time ago functionality
}

function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length));
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function jsonResponse($data, $statusCode = 200) {
    header('Content-Type: application/json', true, $statusCode);
    echo json_encode($data);
    exit();
}

function formatBytes($bytes, $dec = 2) {
    // Implement bytes formatting
}

function truncateText($text, $length = 100, $suffix = '...') {
    return strlen($text) > $length ? substr($text, 0, $length) . $suffix : $text;
}

function getCurrentUrl() {
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

function isValidUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function slugify($text) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text)));
}

function dd($data) {
    echo '<pre>' . print_r($data, true) . '</pre>'; 
    die();
}

?>