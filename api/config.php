<?php
// api/config.php - Complete with NLP integration

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================
// SUPABASE CONFIGURATION
// ============================================
define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');

// ============================================
// NLP SERVICE CONFIGURATION
// ============================================
define('NLP_SERVICE_URL', 'https://lib-nlp-service-2.onrender.com');
define('NLP_HEALTH', NLP_SERVICE_URL . '/health');
define('NLP_SEARCH', NLP_SERVICE_URL . '/search');
define('NLP_PREDICT', NLP_SERVICE_URL . '/predict');
define('NLP_CLASSIFY', NLP_SERVICE_URL . '/classify');
define('NLP_ANALYZE', NLP_SERVICE_URL . '/analyze_session');
define('NLP_TIMEOUT', 10);

// ============================================
// SUPABASE REQUEST FUNCTION
// ============================================
function supabaseRequest($endpoint, $method = 'GET', $data = null, $headers = []) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $defaultHeaders = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("API Error: " . $response);
    }
    
    return json_decode($response, true);
}

// ============================================
// NLP SERVICE HEALTH CHECK - FIXED
// ============================================
function isNLPServiceRunning() {
    $ch = curl_init(NLP_HEALTH);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check if response is valid and model is loaded
    if ($httpCode === 200 && $response !== false) {
        try {
            $data = json_decode($response, true);
            // Check if model_loaded is true
            if (isset($data['model_loaded']) && $data['model_loaded'] === true) {
                return true;
            }
            // Also check semantic_available as fallback
            if (isset($data['semantic_available']) && $data['semantic_available'] === true) {
                return true;
            }
        } catch (Exception $e) {
            // If JSON parsing fails, fall back to HTTP check
            return true;
        }
    }
    
    return false;
}

// ============================================
// GET NLP SERVICE STATUS WITH DETAILS
// ============================================
function getNLPStatus() {
    $ch = curl_init(NLP_HEALTH);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response !== false) {
        try {
            $data = json_decode($response, true);
            return [
                'available' => isset($data['model_loaded']) && $data['model_loaded'] === true,
                'details' => $data
            ];
        } catch (Exception $e) {
            return ['available' => true, 'details' => null];
        }
    }
    
    return ['available' => false, 'details' => null];
}

// ============================================
// PERFORM NLP SEARCH
// ============================================
function performNLPSearch($query, $type = 'semantic', $limit = 20) {
    $payload = json_encode([
        'query' => $query,
        'type' => $type,
        'limit' => $limit
    ]);
    
    $ch = curl_init(NLP_SEARCH);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, NLP_TIMEOUT);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response !== false) {
        return json_decode($response, true);
    }
    
    return null;
}

// ============================================
// PERFORM BASIC SEARCH (FALLBACK)
// ============================================
function performBasicSearch($query) {
    $queryLower = strtolower($query);
    $queryWords = array_filter(explode(' ', $queryLower), function($w) { return strlen($w) > 2; });
    
    try {
        $books = supabaseRequest('books?select=*,categories(name)', 'GET');
        $results = [];
        
        foreach ($books as $book) {
            $title = strtolower($book['title'] ?? '');
            $author = strtolower($book['author'] ?? '');
            $description = strtolower($book['description'] ?? '');
            $category = strtolower($book['categories']['name'] ?? '');
            $keywords = strtolower($book['keywords'] ?? '');
            
            $score = 0;
            
            // Title match
            if (strpos($title, $queryLower) !== false) {
                $score += 50;
            } elseif (count($queryWords) > 0 && count(array_filter($queryWords, function($w) use ($title) { return strpos($title, $w) !== false; })) > 0) {
                $score += 20;
            }
            
            // Author match
            if (strpos($author, $queryLower) !== false) {
                $score += 30;
            } elseif (count($queryWords) > 0 && count(array_filter($queryWords, function($w) use ($author) { return strpos($author, $w) !== false; })) > 0) {
                $score += 15;
            }
            
            // Description match
            if (strpos($description, $queryLower) !== false) {
                $score += 20;
            } elseif (count($queryWords) > 0 && count(array_filter($queryWords, function($w) use ($description) { return strpos($description, $w) !== false; })) > 0) {
                $score += 10;
            }
            
            // Category match
            if (strpos($category, $queryLower) !== false) {
                $score += 15;
            } elseif (count($queryWords) > 0 && count(array_filter($queryWords, function($w) use ($category) { return strpos($category, $w) !== false; })) > 0) {
                $score += 8;
            }
            
            // Keywords match
            if (strpos($keywords, $queryLower) !== false) {
                $score += 15;
            }
            
            if ($score > 0) {
                $book['relevance'] = min($score, 100);
                $book['search_type'] = 'basic';
                $results[] = $book;
            }
        }
        
        usort($results, function($a, $b) {
            return $b['relevance'] - $a['relevance'];
        });
        
        return $results;
        
    } catch (Exception $e) {
        return [];
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

function getInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?: [];
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
