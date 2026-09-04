<?php
// test-connection.php
$url = 'https://olzkpwzebcnmbqhbcyyz.supabase.co/rest/v1/users?select=count';
$key = 'YOUR_ANON_KEY_HERE'; // Replace with your actual anon key

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'apikey: ' . $key,
    'Authorization: Bearer ' . $key,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response: " . $response . "\n";

if ($httpCode === 200) {
    echo "✅ Connection successful!\n";
} else {
    echo "❌ Connection failed. Check your URL and API key.\n";
}
?>