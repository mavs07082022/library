<?php
echo "Test file is working!<br>";
echo "Current directory: " . __DIR__ . "<br>";

// Check if homepage exists
$homepagePath = __DIR__ . '/frontend/src/homepage.php';
if (file_exists($homepagePath)) {
    echo "✅ homepage.php exists at: " . $homepagePath . "<br>";
    echo "File size: " . filesize($homepagePath) . " bytes<br>";
} else {
    echo "❌ homepage.php NOT found at: " . $homepagePath . "<br>";
}

// List files in frontend/src/
$dir = __DIR__ . '/frontend/src/';
if (is_dir($dir)) {
    echo "<br>Files in frontend/src/:<br>";
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            echo "- " . $file . "<br>";
        }
    }
} else {
    echo "❌ frontend/src/ directory not found!";
}
?>
