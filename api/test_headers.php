<?php
// First, test if headers are working
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'Headers are working!',
    'headers' => getallheaders()
]);
?>