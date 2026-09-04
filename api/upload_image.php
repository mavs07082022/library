<?php
// api/upload_image.php - Handle image uploads
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Configuration
$uploadDir = __DIR__ . '/../frontend/public/images/books/';
$maxFileSize = 5 * 1024 * 1024; // 5MB
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Create directory if not exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Check if file was uploaded
if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];
    $error = $_FILES['cover_image']['error'];
    $message = $errorMessages[$error] ?? 'Unknown upload error';
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

$file = $_FILES['cover_image'];

// Validate file type
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Allowed: JPEG, PNG, GIF, WEBP']);
    exit;
}

// Validate file size
if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max 5MB']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('book_') . '.' . $extension;
$filepath = $uploadDir . $filename;

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Return the URL path
    $imageUrl = '/library-system/frontend/public/images/books/' . $filename;
    
    echo json_encode([
        'success' => true,
        'url' => $imageUrl,
        'filename' => $filename,
        'message' => 'Image uploaded successfully'
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
}
?>