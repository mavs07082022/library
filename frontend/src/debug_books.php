<?php
// debug_books.php - Run this to see what's happening
session_start();

define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');

function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("API Error (HTTP $httpCode): " . $response);
    }

    return json_decode($response, true);
}

echo "<h1>Debug - Books</h1>";

try {
    // Fetch books
    $books = supabaseRequest('books?select=*');
    echo "<h2>Books Count: " . count($books) . "</h2>";
    
    if (!empty($books)) {
        echo "<h3>First Book:</h3>";
        echo "<pre>";
        print_r($books[0]);
        echo "</pre>";
        
        echo "<h3>All Books:</h3>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Title</th><th>Author</th><th>Category ID</th><th>Available</th><th>Quantity</th></tr>";
        foreach ($books as $b) {
            echo "<tr>";
            echo "<td>" . ($b['id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($b['title'] ?? 'N/A') . "</td>";
            echo "<td>" . ($b['author'] ?? 'N/A') . "</td>";
            echo "<td>" . ($b['category_id'] ?? 'N/A') . "</td>";
            echo "<td>" . ($b['available'] ?? '0') . "</td>";
            echo "<td>" . ($b['quantity'] ?? '0') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No books found!</p>";
    }
    
    // Fetch categories
    $categories = supabaseRequest('categories?select=*');
    echo "<h2>Categories Count: " . count($categories) . "</h2>";
    if (!empty($categories)) {
        echo "<pre>";
        print_r($categories);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>