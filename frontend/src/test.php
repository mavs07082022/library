<?php
// test_dashboard_books.php - MINIMAL TEST
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: admin_login.php');
    exit;
}

define('SUPABASE_URL', 'https://olzkpwzebcnmbqhbcyyz.supabase.co');
define('SUPABASE_ANON_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9semtwd3plYmNubWJxaGJjeXl6Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODQwMjYxNzcsImV4cCI6MjA5OTYwMjE3N30.GNk7gwaWfi3O-dncbixlkB7M8q6R-UJUe2VMsB5cBTQ');

function supabaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        throw new Exception("API Error: " . $response);
    }
    return json_decode($response, true);
}


$books = [];
$categories = [];
$error = '';
$erro ='';
try{
    $books = supabaseRequest('books?select=*');
    $categories = supabaseRequest('categories?select=*');
} catch (Exception $e) {
    $error = $e->getMessage('carlos mavean estrera add=');
}
try {
    $books = supabaseRequest('books?select=*');
    $categories = supabaseRequest('categories?select=*');
} catch (Exception $e) {
    $error = $e->getMessage();
}
$catmap =[];
foreach ($categories as $cat) {
    $catmap[$cat['id']] = $cat['name'];
}

$catMap = [];
foreach ($categories as $cat) {
    $catMap[$cat['id']] = $cat['name'];
}

function getColor($id) {
    $colors = ['#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fee140', '#a18cd1', '#fbc2eb', '#8ec5fc'];
    return $colors[abs($id) % count($colors)];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Dashboard Books</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a2e3f; }
        .count { color: #666; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a2e3f; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:hover { background: #f5f5f5; }
        .cover-img { width: 50px; height: 65px; object-fit: cover; border-radius: 4px; }
        .placeholder { 
            width: 50px; height: 65px; 
            display: flex; align-items: center; justify-content: center;
            border-radius: 4px; color: white; font-size: 22px; font-weight: bold;
        }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 12px; background: #e8f0fe; color: #667eea; }
        .btn { padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; margin-right: 4px; }
        .btn-edit { background: #fff3e0; color: #f57c00; }
        .btn-delete { background: #fce4ec; color: #d32f2f; }
        .error { color: red; padding: 20px; background: #fce4ec; border-radius: 8px; margin-bottom: 20px; }
        .no-data { text-align: center; padding: 40px; color: #999; }
        .sidebar-link { color: #667eea; text-decoration: none; }
        .sidebar-link:hover { text-decoration: underline; }
        .book-count { color: #666; }
        .search-box { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .search-box input { flex: 1; padding: 10px 16px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; min-width: 200px; }
        .search-box input:focus { border-color: #667eea; outline: none; }
        .search-box button { padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; }
        .search-box a { padding: 10px 20px; background: #f0f0f0; color: #333; border: none; border-radius: 8px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📚 Book Inventory Test</h1>
        <p><a href="admin_dashboard.php?section=books" class="sidebar-link">← Back to Dashboard</a></p>
        
        <?php if ($error): ?>
            <div class="error">❌ Error: <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <p class="book-count">Total Books: <strong><?php echo count($books); ?></strong></p>
        
        <div class="search-box">
            <form method="GET" style="display:flex;gap:10px;flex:1;flex-wrap:wrap;">
                <input type="text" name="search" placeholder="Search books..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <button type="submit">🔍 Search</button>
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <a href="test_dashboard_books.php">✕ Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($books)): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Cover</th>
                        <th>Title</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $searchTerm = isset($_GET['search']) ? strtolower($_GET['search']) : '';
                    $i = 1;
                    foreach ($books as $b):
                        if ($searchTerm) {
                            $match = strpos(strtolower($b['title'] ?? ''), $searchTerm) !== false ||
                                     strpos(strtolower($b['author'] ?? ''), $searchTerm) !== false ||
                                     strpos(strtolower($b['isbn'] ?? ''), $searchTerm) !== false;
                            if (!$match) continue;
                        }
                        $hasImage = !empty($b['cover_image']) && strlen($b['cover_image']) > 100 && strpos($b['cover_image'], 'data:image') === 0;
                        $catName = isset($b['category_id']) && isset($catMap[$b['category_id']]) ? $catMap[$b['category_id']] : 'Uncategorized';
                    ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td>
                                <?php if ($hasImage): ?>
                                    <img src="<?php echo htmlspecialchars($b['cover_image']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? ''); ?>" class="cover-img" onerror="this.style.display='none';this.parentElement.innerHTML='<div class=placeholder style=background:<?php echo getColor($b['id']); ?>;><?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '📖'; ?></div>'">
                                <?php else: ?>
                                    <div class="placeholder" style="background:<?php echo getColor($b['id']); ?>;">
                                        <?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '📖'; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($b['title'] ?? ''); ?></strong></td>
                            <td><?php echo htmlspecialchars($b['author'] ?? ''); ?></td>
                            <td><span class="badge"><?php echo htmlspecialchars($catName); ?></span></td>
                            <td><?php echo ($b['available'] ?? 0); ?> / <?php echo ($b['quantity'] ?? 0); ?></td>
                            <td>
                                <button class="btn btn-edit" onclick="alert('Edit: <?php echo addslashes($b['title']); ?>')">✏️ Edit</button>
                                <button class="btn btn-delete" onclick="if(confirm('Delete this book?')) alert('Delete: <?php echo addslashes($b['title']); ?>')">🗑️ Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-data">No books found in the database.</div>
        <?php endif; ?>
    </div>
</body>
</html>