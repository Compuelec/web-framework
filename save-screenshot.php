<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['name'] ?? 'unknown');

    $dir = __DIR__ . '/screenshots/';
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    if (isset($_FILES['image'])) {
        $path = $dir . $name . '.jpg';
        move_uploaded_file($_FILES['image']['tmp_name'], $path);
        echo json_encode(['ok' => true, 'path' => $path, 'size' => filesize($path)]);
    } elseif (isset($_POST['data'])) {
        $data = $_POST['data'];
        $data = preg_replace('/^data:image\/\w+;base64,/', '', $data);
        $data = base64_decode($data);
        $path = $dir . $name . '.jpg';
        file_put_contents($path, $data);
        echo json_encode(['ok' => true, 'path' => $path, 'size' => strlen($data)]);
    } else {
        echo json_encode(['error' => 'no data']);
    }
} else {
    echo json_encode(['info' => 'POST only']);
}
