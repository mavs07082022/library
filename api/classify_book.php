<?php
// api/classify_book.php - Fallback classification only
// AI classification now runs in the browser via Firebase AI Logic (see ai_functions.js)

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// Handle GET request for testing
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'Classification API is working (fallback mode)',
        'test' => true
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$title = $input['title'] ?? '';
$description = $input['description'] ?? '';
$author = $input['author'] ?? '';

if (empty($title)) {
    echo json_encode(['success' => false, 'message' => 'Title required']);
    exit;
}

$text = strtolower($title . ' ' . $description . ' ' . $author);

// Basic keyword-based classification fallback
$subjectMap = [
    'History' => ['history', 'historical', 'revolution', 'philippine', 'colonial', 'war', 'ancient'],
    'Science' => ['biology', 'chemistry', 'physics', 'science', 'genetics', 'ecology', 'evolution'],
    'Mathematics' => ['algebra', 'calculus', 'geometry', 'math', 'statistics', 'probability'],
    'English' => ['literature', 'poetry', 'grammar', 'writing', 'novel', 'drama'],
    'Filipino' => ['panitikan', 'wika', 'filipino', 'tula', 'akda', 'kwento'],
    'Technology' => ['programming', 'coding', 'software', 'computer', 'database', 'network'],
    'Psychology' => ['psychology', 'mental', 'behavior', 'mind', 'cognitive', 'therapy'],
    'Business' => ['business', 'management', 'marketing', 'finance', 'accounting'],
    'Design' => ['design', 'art', 'creative', 'visual', 'graphic', 'architecture']
];

$bestSubject = 'General';
$bestScore = 0;

foreach ($subjectMap as $subject => $keywords) {
    $score = 0;
    foreach ($keywords as $kw) {
        if (strpos($text, $kw) !== false) $score += 10;
    }
    if ($score > $bestScore) {
        $bestScore = $score;
        $bestSubject = $subject;
    }
}

// Generate tags from words
$tags = [$bestSubject, 'Book'];
$words = preg_split('/[\s,.:;!?()"\']+/', $text);
$commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'for', 'on', 'at', 'to', 'in', 'with', 'by', 'of', 'from'];

foreach ($words as $word) {
    $word = trim($word);
    if (strlen($word) > 3 && !in_array($word, $commonWords) && !is_numeric($word)) {
        $tags[] = ucfirst($word);
    }
    if (count($tags) >= 8) break;
}

$tags = array_slice(array_unique($tags), 0, 8);

echo json_encode([
    'success' => true,
    'suggestions' => [[
        'category_id' => null,
        'category_name' => $bestSubject,
        'subject' => $bestSubject,
        'grade_level' => 'All Grades',
        'score' => min(30 + $bestScore, 95),
        'tags' => $tags
    ]],
    'tags' => $tags,
    'message' => 'Fallback classification (AI runs in browser)'
]);
?>
