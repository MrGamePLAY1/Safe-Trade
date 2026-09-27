<?php
require_once __DIR__ . '/../src/layout.php';

$u = require_login();
$id = (int)($_GET['id'] ?? 0);

$doc = db_row(
    'SELECT d.*, l.user_id, l.status
       FROM documents d
       JOIN listings l ON l.id = d.listing_id
      WHERE d.id = ?',
    [$id]
);

if (!$doc || !$doc['file_path']) {
    http_response_code(404);
    exit('Document not found.');
}

$isOwner = (int)$doc['user_id'] === (int)$u['id'];
if (!$isOwner && $doc['status'] !== 'live') {
    http_response_code(404);
    exit('Document not found.');
}

$base = realpath(__DIR__ . '/../storage/uploads');
$file = $base ? realpath($base . DIRECTORY_SEPARATOR . $doc['file_path']) : false;
if (!$base || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit('Document file not found.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file) ?: 'application/octet-stream';
$allowed = ['application/pdf', 'image/jpeg', 'image/png'];
if (!in_array($mime, $allowed, true)) {
    http_response_code(415);
    exit('Unsupported document type.');
}

$ext = match ($mime) {
    'application/pdf' => 'pdf',
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    default           => 'bin',
};

$download = preg_replace('/[^A-Za-z0-9._-]+/', '-', $doc['title']) ?: 'vehicle-document';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $download . '.' . $ext . '"');
header('X-Content-Type-Options: nosniff');
readfile($file);
