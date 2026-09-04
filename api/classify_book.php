<?php
// api/classify_book.php - Auto-classify books with AI
// Updated for update-libV2 folder

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config from the same directory
require_once __DIR__ . '/config.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET request for testing
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'Classification API is working',
        'test' => true,
        'folder' => __DIR__
    ]);
    exit;
}

// Only accept POST requests for classification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Get input data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$title = $input['title'] ?? '';
$description = $input['description'] ?? '';
$author = $input['author'] ?? '';
$bookId = $input['book_id'] ?? null;

// Log the request
error_log("Classification request in update-libV2: Title: $title");

// Validate input
if (empty($title) || empty($description)) {
    echo json_encode([
        'success' => true,
        'suggestions' => [
            [
                'category_id' => null,
                'category_name' => 'General',
                'subject' => 'General',
                'grade_level' => 'All Grades',
                'score' => 30,
                'tags' => ['Book', 'General']
            ]
        ],
        'tags' => ['Book', 'General'],
        'message' => 'Please provide title and description for better classification'
    ]);
    exit;
}

try {
    // Get categories from database
    $categories = supabaseRequest('categories?select=*');
    
    // If no categories, create default ones
    if (empty($categories)) {
        $defaultCategories = [
            ['name' => 'History', 'description' => 'History and historical events'],
            ['name' => 'Science', 'description' => 'Science and technology'],
            ['name' => 'Mathematics', 'description' => 'Mathematics and numbers'],
            ['name' => 'English', 'description' => 'English language and literature'],
            ['name' => 'Filipino', 'description' => 'Filipino language and literature'],
            ['name' => 'Araling Panlipunan', 'description' => 'Social studies and culture'],
            ['name' => 'Technology', 'description' => 'Technology and computing'],
            ['name' => 'Self-Help', 'description' => 'Self-improvement and personal growth'],
            ['name' => 'Psychology', 'description' => 'Psychology and mental health'],
            ['name' => 'Literature', 'description' => 'Literature and fiction'],
            ['name' => 'Design', 'description' => 'Design and arts'],
            ['name' => 'Business', 'description' => 'Business and management'],
            ['name' => 'Fiction', 'description' => 'Fiction and novels'],
            ['name' => 'Non-Fiction', 'description' => 'Non-fiction and reference']
        ];
        
        foreach ($defaultCategories as $cat) {
            try {
                supabaseRequest('categories', 'POST', $cat);
            } catch (Exception $e) {
                // Category might already exist
            }
        }
        
        $categories = supabaseRequest('categories?select=*');
    }
    
    // Analyze text
    $text = strtolower($title . ' ' . $description . ' ' . $author);
    
    // Subject detection
    $subjectMap = [
        'history' => ['history', 'historical', 'revolution', 'colonial', 'philippine', 'spanish', 'american', 'war', 'ancient', 'medieval', 'world war', 'independence'],
        'science' => ['biology', 'chemistry', 'physics', 'science', 'cells', 'genetics', 'ecology', 'evolution', 'molecules', 'atoms', 'energy', 'force', 'experiment', 'laboratory'],
        'mathematics' => ['algebra', 'calculus', 'geometry', 'trigonometry', 'math', 'statistics', 'probability', 'equations', 'functions', 'numbers', 'mathematical'],
        'english' => ['literature', 'poetry', 'essay', 'grammar', 'writing', 'reading', 'comprehension', 'novel', 'short story', 'drama', 'english'],
        'filipino' => ['panitikan', 'wika', 'filipino', 'tula', 'akda', 'kwento', 'salin', 'gramatika', 'pagbasa', 'filipino'],
        'araling panlipunan' => ['araling panlipunan', 'ap', 'kabihasnan', 'lipunan', 'kultura', 'politika', 'ekonomiya', 'kasaysayan', 'panlipunan'],
        'technology' => ['programming', 'coding', 'software', 'hardware', 'computer', 'database', 'network', 'system', 'development', 'tech'],
        'psychology' => ['psychology', 'mental', 'behavior', 'mind', 'thinking', 'cognitive', 'emotional', 'therapy', 'psych'],
        'business' => ['business', 'management', 'marketing', 'finance', 'accounting', 'entrepreneur', 'leadership', 'economy'],
        'design' => ['design', 'art', 'creative', 'visual', 'graphic', 'ui', 'ux', 'architecture', 'creative', 'drawing']
    ];
    
    // Grade detection
    $gradeMap = [
        'Grade 7' => ['grade 7', '7th grade', 'grade seven', 'junior high'],
        'Grade 8' => ['grade 8', '8th grade', 'grade eight', 'junior high'],
        'Grade 9' => ['grade 9', '9th grade', 'grade nine', 'junior high'],
        'Grade 10' => ['grade 10', '10th grade', 'grade ten', 'junior high'],
        'Grade 11' => ['grade 11', '11th grade', 'grade eleven', 'senior high'],
        'Grade 12' => ['grade 12', '12th grade', 'grade twelve', 'senior high']
    ];
    
    // Find subject
    $suggestedSubject = 'General';
    $subjectScore = 0;
    
    foreach ($subjectMap as $subject => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score += 10;
            }
        }
        if ($score > $subjectScore) {
            $subjectScore = $score;
            $suggestedSubject = ucfirst($subject);
        }
    }
    
    // Find grade
    $suggestedGrade = 'All Grades';
    $gradeScore = 0;
    
    foreach ($gradeMap as $grade => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            if (strpos($text, $keyword) !== false) {
                $score += 15;
            }
        }
        if ($score > $gradeScore) {
            $gradeScore = $score;
            $suggestedGrade = $grade;
        }
    }
    
    // Find category
    $suggestedCategory = null;
    $suggestedCategoryName = 'General';
    $categoryScore = 0;
    
    if (!empty($categories)) {
        foreach ($categories as $cat) {
            $catName = strtolower($cat['name']);
            $catDesc = strtolower($cat['description'] ?? '');
            $score = 0;
            
            if (strpos($text, $catName) !== false) {
                $score += 20;
            }
            if (strpos($text, $catDesc) !== false) {
                $score += 10;
            }
            
            if (strpos($catName, strtolower($suggestedSubject)) !== false ||
                strpos($suggestedSubject, $catName) !== false) {
                $score += 15;
            }
            
            if ($score > $categoryScore) {
                $categoryScore = $score;
                $suggestedCategory = $cat['id'];
                $suggestedCategoryName = $cat['name'];
            }
        }
    }
    
    // Generate tags
    $tags = [];
    $words = explode(' ', str_replace([',', '.', ':', ';', '!', '?', '(', ')', '"', "'"], ' ', $text));
    $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'for', 'on', 'at', 'to', 'in', 'with', 'without', 'by', 'of', 'from', 'into', 'through', 'during', 'including'];
    
    foreach ($words as $word) {
        $word = trim($word);
        if (strlen($word) > 3 && !in_array($word, $commonWords) && !is_numeric($word)) {
            $tags[] = ucfirst($word);
        }
    }
    
    $tags = array_slice(array_unique($tags), 0, 8);
    
    if ($suggestedSubject && $suggestedSubject !== 'General') {
        $tags[] = $suggestedSubject;
    }
    if ($suggestedGrade && $suggestedGrade !== 'All Grades') {
        $tags[] = $suggestedGrade;
    }
    
    $confidence = min(30 + $subjectScore + $gradeScore + $categoryScore, 95);
    
    $response = [
        'success' => true,
        'suggestions' => [
            [
                'category_id' => $suggestedCategory,
                'category_name' => $suggestedCategoryName,
                'subject' => $suggestedSubject,
                'grade_level' => $suggestedGrade,
                'score' => $confidence,
                'tags' => $tags
            ]
        ],
        'tags' => $tags,
        'message' => 'Book classified successfully',
        'debug' => [
            'subject_score' => $subjectScore,
            'grade_score' => $gradeScore,
            'category_score' => $categoryScore,
            'confidence' => $confidence
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => true,
        'suggestions' => [
            [
                'category_id' => null,
                'category_name' => 'General',
                'subject' => 'General',
                'grade_level' => 'All Grades',
                'score' => 40,
                'tags' => ['Book', 'General']
            ]
        ],
        'tags' => ['Book', 'General'],
        'message' => 'Classification with fallback',
        'error' => $e->getMessage()
    ]);
}
?>