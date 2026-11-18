<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$path = $_GET['path'] ?? '';
if (empty($path) || strpos($path, '..') !== false) {
    http_response_code(400);
    exit();
}

$file = __DIR__ . '/' . ltrim($path, '/');

if (!file_exists($file) || !is_file($file)) {
    http_response_code(404);
    exit();
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
header("Content-Type: $mime");

readfile($file);
?>