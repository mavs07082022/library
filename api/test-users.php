<?php
// test-users.php - Fixed version
header('Content-Type: text/plain');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

echo "=== CONNECTION TEST ===\n\n";

// Test 1: Check if Supabase URL is set
echo "1. Supabase URL: " . SUPABASE_URL . "\n";
echo "2. Supabase Key: " . substr(SUPABASE_ANON_KEY, 0, 20) . "...\n\n";

// Test 2: Try to fetch users directly
echo "3. Fetching users from Supabase...\n";

try {
    $url = SUPABASE_URL . '/rest/v1/users?select=*';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Status Code: " . $httpCode . "\n";
    
    if ($httpCode === 200) {
        $users = json_decode($response, true);
        echo "Users found: " . count($users) . "\n\n";
        
        if (!empty($users)) {
            echo "=== USERS IN DATABASE ===\n\n";
            foreach ($users as $user) {
                echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
                echo "  Email: " . ($user['email'] ?? 'N/A') . "\n";
                echo "  Role: " . ($user['role'] ?? 'N/A') . "\n";
                echo "  Password: " . ($user['password'] ?? 'N/A') . "\n";
                echo "  ---\n";
            }
            
            echo "\n=== TEST LOGIN ===\n";
            $testCredentials = [
                ['username' => 'admin', 'password' => 'admin123'],
                ['username' => 'libms', 'password' => 'library123'],
                ['username' => 'student1', 'password' => 'student123']
            ];
            
            foreach ($testCredentials as $test) {
                $found = false;
                foreach ($users as $user) {
                    if (($user['username'] ?? '') === $test['username']) {
                        $found = true;
                        $storedPassword = $user['password'] ?? '';
                        if ($storedPassword === $test['password']) {
                            echo "✅ " . $test['username'] . " - Password MATCHES!\n";
                        } else {
                            echo "❌ " . $test['username'] . " - Password MISMATCH!\n";
                            echo "   Expected: " . $test['password'] . "\n";
                            echo "   Actual: " . $storedPassword . "\n";
                        }
                        break;
                    }
                }
                if (!$found) {
                    echo "❌ " . $test['username'] . " - NOT FOUND!\n";
                }
            }
        } else {
            echo "No users found in the database!\n";
        }
    } else {
        echo "Error response: " . $response . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETE ===\n";
?>