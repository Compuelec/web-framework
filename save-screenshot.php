<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

// Require active CMS admin session
session_start();
if (empty($_SESSION['admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['info' => 'POST only']);
    exit;
}

$name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? 'unknown');
if (empty($name)) {
    $name = 'screenshot_' . time();
}

$dir = __DIR__ . '/screenshots/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

if (isset($_FILES['image'])) {
    // Validate that the uploaded file is actually an image
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mimeType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid file type']);
        exit;
    }

    $path = $dir . $name . '.jpg';
    if (move_uploaded_file($_FILES['image']['tmp_name'], $path)) {
        echo json_encode(['ok' => true, 'size' => filesize($path)]);
    } else {
        echo json_encode(['error' => 'Upload failed']);
    }
} elseif (isset($_POST['data'])) {
    $data = $_POST['data'];
    $data = preg_replace('/^data:image\/\w+;base64,/', '', $data);
    $decoded = base64_decode($data, true);

    if ($decoded === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image data']);
        exit;
    }

    // Verify decoded data is a valid image
    $imageInfo = @getimagesizefromstring($decoded);
    if ($imageInfo === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid image data']);
        exit;
    }

    $path = $dir . $name . '.jpg';
    file_put_contents($path, $decoded);
    echo json_encode(['ok' => true, 'size' => strlen($decoded)]);
} else {
    echo json_encode(['error' => 'no data']);
}
