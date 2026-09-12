<?php
header('Content-Type: text/plain; charset=utf-8');

echo "=== FPDF Deployment Diagnostic ===\n\n";
echo "PHP version: " . phpversion() . "\n";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'unknown') . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "DOCUMENT_ROOT: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'unknown') . "\n\n";

echo "--- FILE: fpdf.php ---\n";
$fpdfPath = __DIR__ . '/fpdf.php';
echo "Looking for: {$fpdfPath}\n";
echo "Exists: " . (file_exists($fpdfPath) ? 'YES' : 'NO') . "\n";
if (file_exists($fpdfPath)) {
    echo "Size: " . filesize($fpdfPath) . " bytes\n";
    $first3 = file_get_contents($fpdfPath, false, null, 0, 3);
    echo "First 3 bytes (hex): " . bin2hex($first3) . "\n";
    if ($first3 === "\xEF\xBB\xBF") {
        echo "** HAS BOM - THIS IS A PROBLEM **\n";
    }
    $last20 = file_get_contents($fpdfPath, false, null, max(0, filesize($fpdfPath) - 20), 20);
    echo "Last 20 bytes: " . json_encode($last20) . "\n";
} else {
    echo "** fpdf.php IS NOT IN THE CONTAINER **\n";
}
echo "\n";

echo "--- FOLDER: font/ ---\n";
$fontPath = __DIR__ . '/font/';
echo "Looking for: {$fontPath}\n";
echo "Exists: " . (is_dir($fontPath) ? 'YES' : 'NO') . "\n";
if (is_dir($fontPath)) {
    $files = scandir($fontPath);
    $jsons = glob($fontPath . '*.json');
    echo "Total files: " . (count($files) - 2) . "\n";
    echo "JSON files: " . count($jsons) . "\n";
    foreach (array_slice($jsons, 0, 8) as $f) {
        echo "  - " . basename($f) . "\n";
    }
} else {
    echo "** font/ FOLDER IS NOT IN THE CONTAINER **\n";
}
echo "\n";

echo "--- Files in " . __DIR__ . " ---\n";
$items = @scandir(__DIR__);
if ($items) {
    foreach ($items as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = __DIR__ . '/' . $f;
        $type = is_dir($p) ? '[DIR ]' : '[FILE]';
        $size = is_file($p) ? ' (' . filesize($p) . 'b)' : '';
        echo "{$type} {$f}{$size}\n";
    }
}
echo "\n";

echo "--- Try to load FPDF ---\n";
if (file_exists($fpdfPath)) {
    @include $fpdfPath;
    if (class_exists('FPDF')) {
        echo "FPDF class: LOADED OK\n";
        try {
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(40, 10, 'Test');
            $buf = $pdf->Output('S');
            echo "PDF generation: OK (" . strlen($buf) . " bytes)\n";
        } catch (Exception $e) {
            echo "PDF generation: FAILED - " . $e->getMessage() . "\n";
        }
    } else {
        echo "FPDF class: NOT LOADED (include failed silently)\n";
    }
}
