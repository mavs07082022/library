<?php
// api/predict_search.php - Zero-query search prediction with NLP

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = getInput();
$userId = $input['user_id'] ?? null;
$partialQuery = $input['partial_query'] ?? '';

if (!$userId || empty($partialQuery) || strlen($partialQuery) < 2) {
    jsonResponse(['predictions' => [], 'message' => 'Invalid query'], 400);
}

try {
    // Get user's grade level and subjects
    $studentData = supabaseRequest('students?select=year_level&user_id=eq.' . $userId);
    $gradeLevel = !empty($studentData) ? $studentData[0]['year_level'] ?? 'Grade 10' : 'Grade 10';
    
    // Get user's subjects
    $subjects = supabaseRequest('student_subjects?select=subject_name&user_id=eq.' . $userId);
    $subjectList = array_column($subjects, 'subject_name');
    
    // Get user's search history
    $history = supabaseRequest(
        'user_search_history?select=query&user_id=eq.' . $userId . 
        '&order=created_at.desc&limit=10'
    );
    $historyQueries = array_column($history, 'query');
    
    // Try to call NLP service for prediction
    $nlpResult = callNLPServiceForPrediction($partialQuery, $gradeLevel, $subjectList, $historyQueries);
    
    if ($nlpResult && isset($nlpResult['predictions'])) {
        // Use NLP predictions
        jsonResponse([
            'success' => true,
            'predictions' => $nlpResult['predictions'],
            'count' => count($nlpResult['predictions']),
            'grade_level' => $gradeLevel,
            'source' => 'nlp',
            'message' => 'NLP predictions found'
        ]);
    } else {
        // Fallback to basic keyword matching
        $predictions = getBasicPredictions($partialQuery);
        jsonResponse([
            'success' => true,
            'predictions' => $predictions,
            'count' => count($predictions),
            'grade_level' => $gradeLevel,
            'source' => 'basic',
            'message' => 'Basic predictions (NLP unavailable)'
        ]);
    }
    
} catch (Exception $e) {
    // Fallback to basic predictions on error
    try {
        $predictions = getBasicPredictions($partialQuery);
        jsonResponse([
            'success' => true,
            'predictions' => $predictions,
            'count' => count($predictions),
            'message' => 'Fallback predictions',
            'error' => $e->getMessage()
        ]);
    } catch (Exception $e2) {
        jsonResponse([
            'success' => false,
            'predictions' => [],
            'error' => $e2->getMessage()
        ], 500);
    }
}

// ============================================
// Helper Functions
// ============================================

function callNLPServiceForPrediction($partialQuery, $gradeLevel, $subjects, $history) {
    try {
        $payload = [
            'partial_query' => $partialQuery,
            'grade_level' => $gradeLevel,
            'subjects' => $subjects,
            'search_history' => $history
        ];
        
        // Call the NLP service
        $ch = curl_init('http://localhost:5000/predict');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    } catch (Exception $e) {
        error_log('NLP prediction error: ' . $e->getMessage());
        return null;
    }
}

function getBasicPredictions($query) {
    $books = supabaseRequest('books?select=*');
    $query = strtolower($query);
    $predictions = [];
    
    foreach ($books as $book) {
        $title = strtolower($book['title'] ?? '');
        $author = strtolower($book['author'] ?? '');
        $description = strtolower($book['description'] ?? '');
        $keywords = strtolower($book['keywords'] ?? '');
        
        $score = 0;
        
        // Title match (highest priority)
        if (strpos($title, $query) === 0) {
            $score += 50;
        } elseif (strpos($title, $query) !== false) {
            $score += 30;
        }
        
        // Author match
        if (strpos($author, $query) !== false) {
            $score += 20;
        }
        
        // Description and keywords
        if (strpos($description, $query) !== false) {
            $score += 15;
        }
        if (strpos($keywords, $query) !== false) {
            $score += 15;
        }
        
        if ($score > 0) {
            $book['relevance'] = min($score, 100);
            $book['prediction_score'] = $book['relevance'];
            $book['is_prediction'] = true;
            $predictions[] = $book;
        }
    }
    
    // Sort by score
    usort($predictions, function($a, $b) {
        return ($b['relevance'] ?? 0) - ($a['relevance'] ?? 0);
    });
    
    return array_slice($predictions, 0, 5);
}
?>