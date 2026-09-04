<?php
// api/books.php - Updated with keyword generation
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? $_GET['id'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// ============================================
// HELPER: Generate keywords from book data
// ============================================
function generateKeywords($title, $description, $author, $category) {
    $text = strtolower($title . ' ' . ($description ?? '') . ' ' . $author . ' ' . $category);
    $words = preg_split('/\s+/', $text);
    
    $stopwords = ['the', 'and', 'for', 'are', 'but', 'not', 'you', 'all', 'can', 'had',
                  'her', 'was', 'one', 'our', 'out', 'use', 'how', 'its', 'now', 'see',
                  'two', 'way', 'who', 'has', 'any', 'new', 'day', 'may', 'get', 'his',
                  'she', 'him', 'did', 'made', 'than', 'into', 'some', 'such', 'them',
                  'then', 'these', 'they', 'time', 'what', 'when', 'which', 'will',
                  'with', 'your', 'about', 'after', 'also', 'because', 'been', 'before',
                  'between', 'both', 'could', 'does', 'even', 'every', 'from', 'good',
                  'have', 'here', 'like', 'more', 'much', 'must', 'only', 'other',
                  'over', 'same', 'should', 'so', 'than', 'too', 'very', 'where'];
    
    $keywords = [];
    foreach ($words as $word) {
        $word = trim($word, '.,!?;:()"\'');
        if (strlen($word) > 2 && !in_array($word, $stopwords)) {
            $keywords[] = $word;
        }
    }
    
    $freq = array_count_values($keywords);
    arsort($freq);
    
    return implode(' ', array_slice(array_keys($freq), 0, 15));
}

// ============================================
// GET BOOKS
// ============================================
if ($method === 'GET') {
    try {
        if ($id) {
            $result = supabaseRequest('books?select=*,categories(name)&id=eq.' . urlencode($id));
            $book = $result[0] ?? null;
            jsonResponse($book);
        } else {
            $query = 'books?select=*,categories(name)';
            if ($search) {
                $query .= '&or=(title.ilike.%' . urlencode($search) . '%,author.ilike.%' . urlencode($search) . '%,isbn.ilike.%' . urlencode($search) . '%,keywords.ilike.%' . urlencode($search) . '%)';
            }
            $result = supabaseRequest($query);
            jsonResponse($result);
        }
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// ADD BOOK
// ============================================
if ($method === 'POST') {
    $input = getInput();
    
    if (empty($input['title']) || empty($input['author'])) {
        jsonResponse(['error' => 'Title and author are required'], 400);
    }
    
    try {
        // Get category name
        $categoryName = '';
        if (!empty($input['category_id'])) {
            $catResult = supabaseRequest('categories?select=name&id=eq.' . urlencode($input['category_id']));
            if (!empty($catResult)) {
                $categoryName = $catResult[0]['name'] ?? '';
            }
        }
        
        // Generate keywords
        $keywords = generateKeywords(
            $input['title'],
            $input['description'] ?? '',
            $input['author'],
            $categoryName
        );
        
        $bookData = [
            'title' => $input['title'],
            'author' => $input['author'],
            'isbn' => $input['isbn'] ?? '',
            'publisher' => $input['publisher'] ?? '',
            'year_published' => isset($input['year_published']) ? intval($input['year_published']) : null,
            'category_id' => $input['category_id'] ?? null,
            'quantity' => intval($input['quantity'] ?? 1),
            'available' => intval($input['available'] ?? $input['quantity'] ?? 1),
            'location' => $input['location'] ?? '',
            'e_book_url' => $input['e_book_url'] ?? '',
            'description' => $input['description'] ?? '',
            'keywords' => $keywords,
            'searchable' => true
        ];
        
        if (!empty($input['cover_image']) && strpos($input['cover_image'], 'data:image') === 0) {
            $bookData['cover_image'] = $input['cover_image'];
        }
        
        $result = supabaseRequest('books', 'POST', $bookData);
        jsonResponse(['success' => true, 'message' => 'Book added successfully', 'book' => $result[0] ?? null], 201);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// UPDATE BOOK
// ============================================
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'Book ID required'], 400);
    }
    
    $input = getInput();
    
    try {
        $current = supabaseRequest('books?select=*&id=eq.' . $id);
        if (empty($current)) {
            jsonResponse(['error' => 'Book not found'], 404);
        }
        $current = $current[0];
        
        $bookData = [];
        if (isset($input['title'])) $bookData['title'] = $input['title'];
        if (isset($input['author'])) $bookData['author'] = $input['author'];
        if (isset($input['isbn'])) $bookData['isbn'] = $input['isbn'];
        if (isset($input['publisher'])) $bookData['publisher'] = $input['publisher'];
        if (isset($input['year_published'])) $bookData['year_published'] = intval($input['year_published']);
        if (isset($input['category_id'])) $bookData['category_id'] = $input['category_id'];
        if (isset($input['quantity'])) $bookData['quantity'] = intval($input['quantity']);
        if (isset($input['available'])) $bookData['available'] = intval($input['available']);
        if (isset($input['location'])) $bookData['location'] = $input['location'];
        if (isset($input['e_book_url'])) $bookData['e_book_url'] = $input['e_book_url'];
        if (isset($input['description'])) $bookData['description'] = $input['description'];
        
        // Regenerate keywords if needed
        if (isset($input['title']) || isset($input['description']) || isset($input['author']) || isset($input['category_id'])) {
            $categoryName = '';
            $catId = $input['category_id'] ?? $current['category_id'];
            if (!empty($catId)) {
                $catResult = supabaseRequest('categories?select=name&id=eq.' . urlencode($catId));
                if (!empty($catResult)) {
                    $categoryName = $catResult[0]['name'] ?? '';
                }
            }
            
            $bookData['keywords'] = generateKeywords(
                $input['title'] ?? $current['title'],
                $input['description'] ?? $current['description'],
                $input['author'] ?? $current['author'],
                $categoryName
            );
        }
        
        if (isset($input['cover_image'])) {
            if (empty($input['cover_image'])) {
                $bookData['cover_image'] = null;
            } elseif (strpos($input['cover_image'], 'data:image') === 0) {
                $bookData['cover_image'] = $input['cover_image'];
            }
        }
        
        supabaseRequest('books?id=eq.' . $id, 'PATCH', $bookData);
        jsonResponse(['success' => true, 'message' => 'Book updated successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

// ============================================
// DELETE BOOK
// ============================================
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'Book ID required'], 400);
    }
    
    try {
        supabaseRequest('books?id=eq.' . $id, 'DELETE');
        jsonResponse(['success' => true, 'message' => 'Book deleted successfully']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
    exit;
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>