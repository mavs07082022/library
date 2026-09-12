<?php
// check_fpdf.php — temporary diagnostic for FPDF deployment issues
// DELETE this file after debugging

header('Content-Type: text/plain; charset=utf-8');

echo "=== FPDF Deployment Diagnostic ===\n\n";

// 1. Where is this file?
echo "check_fpdf.php __DIR__: " . __DIR__ . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'unknown') . "\n";
echo "SCRIPT_FILENAME: " . ($_SERVER['SCRIPT_FILENAME'] ?? 'unknown') . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n\n";

// 2. Is fpdf.php in the same folder?
$fpdfPath = __DIR__ . '/fpdf.php';
echo "Looking for fpdf.php at: {$fpdfPath}\n";
echo "Exists: " . (file_exists($fpdfPath) ? 'YES' : 'NO') . "\n";
if (file_exists($fpdfPath)) {
    echo "Size: " . filesize($fpdfPath) . " bytes\n";
    // Check for BOM (first 3 bytes)
    $first3 = file_get_contents($fpdfPath, false, null, 0, 3);
    if ($first3 === "\xEF\xBB\xBF") {
        echo "⚠ WARNING: File has UTF-8 BOM! This will cause problems.\n";
    } else {
        echo "✓ No BOM detected (first 3 bytes: " . bin2hex($first3) . ")\n";
    }
    // Check the last 20 bytes for stray ?>
    $last20 = file_get_contents($fpdfPath, false, null, max(0, filesize($fpdfPath) - 20), 20);
    if (strpos($last20, '?>') !== false) {
        echo "⚠ WARNING: File contains trailing '?>' at the end! This may cause issues.\n";
    } else {
        echo "✓ No trailing '?>' detected\n";
    }
}
echo "\n";

// 3. Is the font folder there?
$fontPath = __DIR__ . '/font/';
echo "Looking for font/ folder at: {$fontPath}\n";
echo "Exists: " . (is_dir($fontPath) ? 'YES' : 'NO') . "\n";
if (is_dir($fontPath)) {
    $jsonFiles = glob($fontPath . '*.json');
    echo "JSON font files found: " . count($jsonFiles) . "\n";
    foreach (array_slice($jsonFiles, 0, 5) as $f) {
        echo "  - " . basename($f) . "\n";
    }
    if (count($jsonFiles) > 5) {
        echo "  ... and " . (count($jsonFiles) - 5) . " more\n";
    }
} else {
    // Check if font files are directly next to fpdf.php instead
    $directFonts = glob(__DIR__ . '/*.json');
    echo "JSON files directly in __DIR__: " . count($directFonts) . "\n";
}
echo "\n";

// 4. List contents of __DIR__
echo "Contents of " . __DIR__ . ":\n";
$items = @scandir(__DIR__);
if ($items !== false) {
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = __DIR__ . '/' . $item;
        $type = is_dir($path) ? '[DIR] ' : '[FILE]';
        $size = is_file($path) ? ' (' . filesize($path) . ' bytes)' : '';
        echo "  {$type} {$item}{$size}\n";
    }
} else {
    echo "  ERROR: Cannot read directory\n";
}
echo "\n";

// 5. Try to load FPDF
echo "=== Attempting to load FPDF ===\n";
if (file_exists($fpdfPath)) {
    // Capture any errors
    $errBefore = error_get_last();
    @include $fpdfPath;
    $errAfter = error_get_last();
    
    if ($errBefore !== $errAfter && $errAfter !== null) {
        echo "PHP error while including fpdf.php:\n";
        echo "  " . $errAfter['message'] . "\n";
        echo "  File: " . $errAfter['file'] . " Line: " . $errAfter['line'] . "\n\n";
    }
    
    if (class_exists('FPDF')) {
        echo "✓ FPDF class loaded successfully!\n";
        
        // Try generating a minimal PDF
        echo "\n=== Testing PDF generation ===\n";
        try {
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(40, 10, 'FPDF works on this server!');
            $buffer = $pdf->Output('S');
            echo "✓ PDF generated successfully (" . strlen($buffer) . " bytes)\n";
        } catch (Exception $e) {
            echo "✗ PDF generation failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✗ FPDF class NOT loaded after include. Possible reasons:\n";
        echo "  - Syntax error in fpdf.php (check the error above)\n";
        echo "  - BOM character at start of file\n";
        echo "  - File is corrupted or incomplete\n";
    }
} else {
    echo "✗ Cannot test — fpdf.php doesn't exist at expected location\n";
}
