<?php
// admin_dashboard.php - Complete Admin Dashboard with AI Features

session_start();

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: homepage.php');
    exit;
}

if (file_exists('fpdf.php')) {
    require_once('fpdf.php');
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

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
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

$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ===== FETCH REQUESTS DATA =====
$bookRequests = [];
$pendingRequests = [];
$requestSearchTerm = isset($_GET['request_search']) ? $_GET['request_search'] : '';
$requestFilterStatus = isset($_GET['request_filter']) ? $_GET['request_filter'] : 'all';

try {
    $requestsRaw = supabaseRequest('book_requests?select=*');
    
    $bookRequests = [];
    if (is_array($requestsRaw) && !empty($requestsRaw)) {
        foreach ($requestsRaw as $r) {
            $bookTitle = 'Unknown Book';
            $bookAuthor = 'Unknown Author';
            try {
                if (!empty($r['book_id'])) {
                    $bookData = supabaseRequest('books?select=title,author&id=eq.' . $r['book_id']);
                    if (!empty($bookData)) {
                        $bookTitle = $bookData[0]['title'] ?? 'Unknown Book';
                        $bookAuthor = $bookData[0]['author'] ?? 'Unknown Author';
                    }
                }
            } catch (Exception $e) {}
            
            $userFullName = 'Unknown User';
            try {
                if (!empty($r['user_id'])) {
                    $userData = supabaseRequest('users?select=full_name,user_id,username&id=eq.' . $r['user_id']);
                    if (!empty($userData)) {
                        $userFullName = $userData[0]['full_name'] ?? 'Unknown User';
                    }
                }
            } catch (Exception $e) {}
            
            $bookRequests[] = [
                'id' => $r['id'] ?? null,
                'user_id' => $r['user_id'] ?? null,
                'book_id' => $r['book_id'] ?? null,
                'request_type' => $r['request_type'] ?? 'borrow',
                'student_id' => $r['student_id'] ?? 'N/A',
                'full_name' => $r['full_name'] ?? $userFullName,
                'year_level' => $r['year_level'] ?? 'N/A',
                'section' => $r['section'] ?? 'N/A',
                'purpose' => $r['purpose'] ?? '',
                'status' => $r['status'] ?? 'Pending',
                'verified_by' => $r['verified_by'] ?? null,
                'verification_date' => $r['verification_date'] ?? null,
                'verification_notes' => $r['verification_notes'] ?? '',
                'created_at' => $r['created_at'] ?? null,
                'book_title' => $bookTitle,
                'book_author' => $bookAuthor,
                'user_full_name' => $userFullName
            ];
        }
    }
    
    $pendingRequests = array_filter($bookRequests, function($r) {
        return ($r['status'] ?? '') === 'Pending';
    });
} catch (Exception $e) {
    error_log('Error fetching requests: ' . $e->getMessage());
}

// Filter requests
$filteredRequests = array_filter($bookRequests, function($r) use ($requestSearchTerm, $requestFilterStatus) {
    if ($requestFilterStatus !== 'all' && ($r['status'] ?? '') !== $requestFilterStatus) {
        return false;
    }
    if (empty($requestSearchTerm)) return true;
    $search = strtolower($requestSearchTerm);
    return strpos(strtolower($r['full_name'] ?? ''), $search) !== false ||
           strpos(strtolower($r['book_title'] ?? ''), $search) !== false ||
           strpos(strtolower($r['student_id'] ?? ''), $search) !== false ||
           strpos(strtolower($r['user_full_name'] ?? ''), $search) !== false;
});

// ===== REQUEST VERIFICATION HANDLING =====
if ($section === 'requests' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $requestId = isset($_GET['id']) ? $_GET['id'] : '';
    $adminId = $_SESSION['user_id'];
    $notes = isset($_GET['notes']) ? $_GET['notes'] : '';
    
    if (($action === 'approve' || $action === 'reject') && $requestId) {
        try {
            $status = $action === 'approve' ? 'Approved' : 'Rejected';
            
            $updateData = [
                'status' => $status,
                'verified_by' => $adminId,
                'verification_date' => date('Y-m-d H:i:s'),
                'verification_notes' => $notes
            ];
            
            supabaseRequest('book_requests?id=eq.' . $requestId, 'PATCH', $updateData);
            
            $requestData = supabaseRequest('book_requests?select=*&id=eq.' . $requestId);
            if (!empty($requestData)) {
                $req = $requestData[0];
                $bookTitle = 'Book';
                try {
                    if (!empty($req['book_id'])) {
                        $bookInfo = supabaseRequest('books?select=title&id=eq.' . $req['book_id']);
                        if (!empty($bookInfo)) {
                            $bookTitle = $bookInfo[0]['title'] ?? 'Book';
                        }
                    }
                } catch (Exception $e) {}
                
                $notifData = [
                    'user_id' => $req['user_id'],
                    'title' => 'Request ' . $status,
                    'message' => 'Your request to ' . ($req['request_type'] ?? 'borrow') . ' "' . $bookTitle . '" has been ' . strtolower($status) . '.',
                    'type' => 'request',
                    'icon' => $status === 'Approved' ? '✅' : '❌',
                    'is_read' => false,
                    'action_url' => 'student_dashboard.php?section=requests',
                    'action_label' => 'View Requests'
                ];
                supabaseRequest('notifications', 'POST', $notifData);
            }
            
            header('Location: admin_dashboard.php?section=requests&msg=Request ' . strtolower($status) . ' successfully');
            exit;
        } catch (Exception $e) {
            header('Location: admin_dashboard.php?section=requests&msg=Error updating request: ' . $e->getMessage());
            exit;
        }
    }
}

// ===== BOOK EXPORT =====
if ($section === 'books' && $action === 'export' && isset($_GET['format'])) {
    try {
        $books = supabaseRequest('books?select=*');
        $categories = supabaseRequest('categories?select=*');
        $catMap = [];
        foreach ($categories as $cat) {
            $catMap[$cat['id']] = $cat['name'];
        }
        
        $exportData = [];
        foreach ($books as $b) {
            $exportData[] = [
                'Title' => $b['title'] ?? 'N/A',
                'Author' => $b['author'] ?? 'N/A',
                'ISBN' => $b['isbn'] ?? 'N/A',
                'Publisher' => $b['publisher'] ?? 'N/A',
                'Year' => $b['year_published'] ?? 'N/A',
                'Category' => isset($b['category_id']) && isset($catMap[$b['category_id']]) ? $catMap[$b['category_id']] : 'Uncategorized',
                'Quantity' => $b['quantity'] ?? 0,
                'Available' => $b['available'] ?? 0,
                'Location' => $b['location'] ?? 'N/A'
            ];
        }
        
        if ($_GET['format'] === 'pdf') {
            if (class_exists('FPDF')) {
                $pdf = new FPDF('L', 'mm', 'A4');
                $pdf->AddPage();
                
                $primaryColor = array(180, 15, 125);
                
                $pdf->SetFont('Arial', 'B', 20);
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->Cell(0, 15, 'Book Inventory Report', 0, 1, 'C');
                
                $pdf->SetFont('Arial', '', 11);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell(0, 8, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
                $pdf->Cell(0, 8, 'Total Books: ' . count($exportData), 0, 1, 'C');
                $pdf->Ln(8);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetTextColor(255, 255, 255);
                
                $headers = array_keys($exportData[0]);
                $colWidths = [30, 30, 25, 30, 15, 25, 18, 18, 20];
                $totalWidth = array_sum($colWidths);
                $pageWidth = 270;
                $startX = ($pageWidth - $totalWidth) / 2;
                $pdf->SetX($startX);
                
                $cleanHeaders = array_map(function($h) {
                    return preg_replace('/[^\x20-\x7E]/', '', $h);
                }, $headers);
                
                foreach ($cleanHeaders as $i => $header) {
                    $pdf->Cell($colWidths[$i] ?? 20, 9, $header, 1, 0, 'C', 1);
                }
                $pdf->Ln();
                
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $fill = false;
                $rowCount = 0;
                
                foreach ($exportData as $row) {
                    $rowData = array_values($row);
                    $pdf->SetX($startX);
                    
                    if ($fill) {
                        $pdf->SetFillColor(240, 244, 248);
                    } else {
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    foreach ($rowData as $i => $cell) {
                        $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                        $pdf->Cell($colWidths[$i] ?? 20, 7, substr($cleanCell, 0, 25), 1, 0, 'L', true);
                    }
                    $pdf->Ln();
                    $fill = !$fill;
                    $rowCount++;
                    
                    if ($rowCount % 25 == 0) {
                        $pdf->AddPage();
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->SetX($startX);
                        foreach ($cleanHeaders as $i => $header) {
                            $pdf->Cell($colWidths[$i] ?? 20, 9, $header, 1, 0, 'C', 1);
                        }
                        $pdf->Ln();
                        $pdf->SetFont('Arial', '', 8);
                        $pdf->SetTextColor(0, 0, 0);
                        $fill = false;
                    }
                }
                
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'I', 8);
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->Cell(0, 8, 'Generated from Library Management System | ' . date('F j, Y g:i A'), 0, 1, 'C');
                
                $pdf->Output('D', 'books_export_' . date('Y-m-d') . '.pdf');
                exit;
            } else {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="books_export_' . date('Y-m-d') . '.pdf"');
                
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Book Inventory Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { color: #b40f7d; text-align: center; }
                        .header { text-align: center; margin-bottom: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background: #b40f7d; color: white; padding: 10px; text-align: left; }
                        td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
                        tr:nth-child(even) { background: #f4f6f9; }
                        .footer { margin-top: 30px; text-align: center; color: #b40f7d; font-size: 12px; }
                        @media print {
                            body { padding: 10px; }
                            th { background: #b40f7d !important; color: white !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Book Inventory Report</h1>
                        <p>Generated: ' . date('F j, Y g:i A') . '</p>
                        <p>Total Books: ' . count($exportData) . '</p>
                    </div>
                    <table>
                        <thead>
                            <tr>';
                foreach (array_keys($exportData[0]) as $header) {
                    $cleanHeader = preg_replace('/[^\x20-\x7E]/', '', $header);
                    echo '<th>' . $cleanHeader . '</th>';
                }
                echo '      </tr>
                        </thead>
                        <tbody>';
                foreach ($exportData as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                        echo '<td>' . htmlspecialchars($cleanCell) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '      </tbody>
                    </table>
                    <div class="footer">
                        <p>Generated from Library Management System | ' . date('F j, Y g:i A') . '</p>
                    </div>
                </body>
                </html>';
                exit;
            }
        } elseif ($_GET['format'] === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="books_export_' . date('Y-m-d') . '.xls"');
            
            echo '<table border="1">';
            echo '<tr style="background:#b40f7d;color:white;">';
            foreach (array_keys($exportData[0]) as $header) {
                $cleanHeader = preg_replace('/[^\x20-\x7E]/', '', $header);
                echo '<th style="padding:8px;">' . $cleanHeader . '</th>';
            }
            echo '</tr>';
            foreach ($exportData as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                    echo '<td style="padding:8px;">' . htmlspecialchars($cleanCell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }
    } catch (Exception $e) {
        echo 'Export error: ' . $e->getMessage();
        exit;
    }
}

// ===== REPORTS EXPORT =====
if ($section === 'reports' && $action === 'export' && isset($_GET['format'])) {
    try {
        $borrowings = supabaseRequest('borrowings?select=*,books(title),users(full_name,user_id,username)');
        
        $exportData = [];
        
        if (is_array($borrowings) && !empty($borrowings)) {
            foreach ($borrowings as $b) {
                $bookTitle = 'N/A';
                if (isset($b['books']) && is_array($b['books'])) {
                    $bookTitle = $b['books']['title'] ?? 'N/A';
                }
                
                $userName = 'N/A';
                if (isset($b['users']) && is_array($b['users'])) {
                    $userName = $b['users']['full_name'] ?? 'N/A';
                }
                
                $exportData[] = [
                    'Book' => $bookTitle,
                    'User' => $userName,
                    'Borrow Date' => $b['borrow_date'] ?? 'N/A',
                    'Due Date' => $b['due_date'] ?? 'N/A',
                    'Return Date' => $b['return_date'] ?? 'N/A',
                    'Status' => $b['status'] ?? 'N/A',
                    'Fine Amount' => isset($b['fine_amount']) ? '₱' . number_format(floatval($b['fine_amount']), 2) : '₱0.00'
                ];
            }
        }
        
        if (empty($exportData)) {
            $exportData[] = [
                'Book' => 'No records found',
                'User' => 'No records found',
                'Borrow Date' => 'N/A',
                'Due Date' => 'N/A',
                'Return Date' => 'N/A',
                'Status' => 'N/A',
                'Fine Amount' => '₱0.00'
            ];
        }
        
        if ($_GET['format'] === 'pdf') {
            if (class_exists('FPDF')) {
                $pdf = new FPDF('L', 'mm', 'A4');
                $pdf->AddPage();
                
                $primaryColor = array(180, 15, 125);
                
                $pdf->SetFont('Arial', 'B', 20);
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->Cell(0, 15, 'Borrowing Reports', 0, 1, 'C');
                
                $pdf->SetFont('Arial', '', 11);
                $pdf->SetTextColor(80, 80, 80);
                $pdf->Cell(0, 8, 'Generated: ' . date('F j, Y g:i A'), 0, 1, 'C');
                $pdf->Cell(0, 8, 'Total Borrowings: ' . count($exportData), 0, 1, 'C');
                $pdf->Ln(8);
                
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->SetTextColor(255, 255, 255);
                
                $headers = array_keys($exportData[0]);
                $colWidths = [50, 50, 30, 30, 30, 30, 30];
                $totalWidth = array_sum($colWidths);
                $pageWidth = 270;
                $startX = ($pageWidth - $totalWidth) / 2;
                $pdf->SetX($startX);
                
                $cleanHeaders = array_map(function($h) {
                    return preg_replace('/[^\x20-\x7E]/', '', $h);
                }, $headers);
                
                foreach ($cleanHeaders as $i => $header) {
                    $pdf->Cell($colWidths[$i] ?? 20, 9, $header, 1, 0, 'C', 1);
                }
                $pdf->Ln();
                
                $pdf->SetFont('Arial', '', 8);
                $pdf->SetTextColor(0, 0, 0);
                $fill = false;
                $rowCount = 0;
                
                foreach ($exportData as $row) {
                    $rowData = array_values($row);
                    $pdf->SetX($startX);
                    
                    if ($fill) {
                        $pdf->SetFillColor(240, 244, 248);
                    } else {
                        $pdf->SetFillColor(255, 255, 255);
                    }
                    
                    foreach ($rowData as $i => $cell) {
                        $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                        $pdf->Cell($colWidths[$i] ?? 20, 7, substr($cleanCell, 0, 30), 1, 0, 'L', true);
                    }
                    $pdf->Ln();
                    $fill = !$fill;
                    $rowCount++;
                    
                    if ($rowCount % 25 == 0) {
                        $pdf->AddPage();
                        $pdf->SetFont('Arial', 'B', 10);
                        $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                        $pdf->SetTextColor(255, 255, 255);
                        $pdf->SetX($startX);
                        foreach ($cleanHeaders as $i => $header) {
                            $pdf->Cell($colWidths[$i] ?? 20, 9, $header, 1, 0, 'C', 1);
                        }
                        $pdf->Ln();
                        $pdf->SetFont('Arial', '', 8);
                        $pdf->SetTextColor(0, 0, 0);
                        $fill = false;
                    }
                }
                
                $pdf->Ln(5);
                $pdf->SetFont('Arial', 'I', 8);
                $pdf->SetTextColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
                $pdf->Cell(0, 8, 'Generated from Library Management System | ' . date('F j, Y g:i A'), 0, 1, 'C');
                
                $pdf->Output('D', 'borrowings_export_' . date('Y-m-d') . '.pdf');
                exit;
            } else {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="borrowings_export_' . date('Y-m-d') . '.pdf"');
                
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Borrowing Reports</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                        h1 { color: #b40f7d; text-align: center; }
                        .header { text-align: center; margin-bottom: 20px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                        th { background: #b40f7d; color: white; padding: 10px; text-align: left; }
                        td { padding: 8px 10px; border-bottom: 1px solid #ddd; }
                        tr:nth-child(even) { background: #f4f6f9; }
                        .footer { margin-top: 30px; text-align: center; color: #b40f7d; font-size: 12px; }
                        @media print {
                            body { padding: 10px; }
                            th { background: #b40f7d !important; color: white !important; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <h1>Borrowing Reports</h1>
                        <p>Generated: ' . date('F j, Y g:i A') . '</p>
                        <p>Total Borrowings: ' . count($exportData) . '</p>
                    </div>
                    <table>
                        <thead>
                            <tr>';
                foreach (array_keys($exportData[0]) as $header) {
                    $cleanHeader = preg_replace('/[^\x20-\x7E]/', '', $header);
                    echo '<th>' . $cleanHeader . '</th>';
                }
                echo '      </tr>
                        </thead>
                        <tbody>';
                foreach ($exportData as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                        echo '<td>' . htmlspecialchars($cleanCell) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '      </tbody>
                    </table>
                    <div class="footer">
                        <p>Generated from Library Management System | ' . date('F j, Y g:i A') . '</p>
                    </div>
                </body>
                </html>';
                exit;
            }
        } elseif ($_GET['format'] === 'excel') {
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="borrowings_export_' . date('Y-m-d') . '.xls"');
            
            echo '<table border="1">';
            echo '<tr style="background:#b40f7d;color:white;">';
            foreach (array_keys($exportData[0]) as $header) {
                $cleanHeader = preg_replace('/[^\x20-\x7E]/', '', $header);
                echo '<th style="padding:8px;">' . $cleanHeader . '</th>';
            }
            echo '</tr>';
            foreach ($exportData as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    $cleanCell = preg_replace('/[^\x20-\x7E]/', '', $cell);
                    echo '<td style="padding:8px;">' . htmlspecialchars($cleanCell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
            exit;
        }
    } catch (Exception $e) {
        echo 'Export error: ' . $e->getMessage();
        exit;
    }
}

// ===== BOOK CRUD =====
if ($section === 'books' && $action === 'add_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $year_published = $_POST['year_published'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    $available = intval($_POST['available'] ?? 1);
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $cover_image = $_POST['cover_image'] ?? '';
    
    if ($title && $author) {
        try {
            $data = [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'publisher' => $publisher,
                'year_published' => $year_published ? intval($year_published) : null,
                'category_id' => $category_id ?: null,
                'quantity' => $quantity,
                'available' => $available,
                'location' => $location,
                'description' => $description
            ];
            
            if (!empty($cover_image)) {
                $data['cover_image'] = $cover_image;
            }
            
            $result = supabaseRequest('books', 'POST', $data);
            
            // After adding book, trigger AI classification
            if (!empty($result) && isset($result[0]['id'])) {
                $bookId = $result[0]['id'];
                // Call classification API asynchronously
                $ch = curl_init('api\classify_book.php');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'book_id' => $bookId,
                    'title' => $title,
                    'description' => $description,
                    'author' => $author
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($ch);
                curl_close($ch);
            }
            
            header('Location: admin_dashboard.php?section=books&msg=Book added successfully' . (!empty($result) ? '&ai_classified=1' : ''));
            exit;
        } catch (Exception $e) {
            header('Location: admin_dashboard.php?section=books&msg=Error adding book');
            exit;
        }
    }
    header('Location: admin_dashboard.php?section=books&msg=Title and author are required');
    exit;
}

if ($section === 'books' && $action === 'edit_book' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = $_POST['book_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $author = $_POST['author'] ?? '';
    $isbn = $_POST['isbn'] ?? '';
    $publisher = $_POST['publisher'] ?? '';
    $year_published = $_POST['year_published'] ?? null;
    $category_id = $_POST['category_id'] ?? null;
    $quantity = intval($_POST['quantity'] ?? 1);
    $available = intval($_POST['available'] ?? 1);
    $location = $_POST['location'] ?? '';
    $description = $_POST['description'] ?? '';
    $cover_image = $_POST['cover_image'] ?? '';
    
    if ($bookId && $title && $author) {
        try {
            $data = [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'publisher' => $publisher,
                'year_published' => $year_published ? intval($year_published) : null,
                'category_id' => $category_id ?: null,
                'quantity' => $quantity,
                'available' => $available,
                'location' => $location,
                'description' => $description
            ];
            
            if (!empty($cover_image)) {
                $data['cover_image'] = $cover_image;
            }
            
            supabaseRequest('books?id=eq.' . $bookId, 'PATCH', $data);
            header('Location: admin_dashboard.php?section=books&msg=Book updated successfully');
            exit;
        } catch (Exception $e) {
            header('Location: admin_dashboard.php?section=books&msg=Error updating book');
            exit;
        }
    }
    header('Location: admin_dashboard.php?section=books&msg=Title and author are required');
    exit;
}

// ===== USER CRUD =====
if ($section === 'users' && isset($_GET['action'])) {
    $action = $_GET['action'];
    $userId = isset($_GET['id']) ? $_GET['id'] : '';
    
    if ($action === 'toggle' && $userId) {
        try {
            $users = supabaseRequest('users?select=is_active&id=eq.' . $userId);
            if (!empty($users)) {
                $newStatus = !$users[0]['is_active'];
                supabaseRequest('users?id=eq.' . $userId, 'PATCH', ['is_active' => $newStatus]);
                header('Location: admin_dashboard.php?section=users&msg=Status updated');
                exit;
            }
        } catch (Exception $e) {}
    }
    
    if ($action === 'delete' && $userId) {
        try {
            supabaseRequest('users?id=eq.' . $userId, 'DELETE');
            header('Location: admin_dashboard.php?section=users&msg=User deleted');
            exit;
        } catch (Exception $e) {}
    }
    
    if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        $full_name = $_POST['full_name'] ?? '';
        $role = $_POST['role'] ?? 'librarian';
        
        if ($username && $email && $password && $full_name) {
            try {
                $newUser = [
                    'username' => $username,
                    'email' => $email,
                    'password' => $password,
                    'full_name' => $full_name,
                    'role' => $role,
                    'user_id' => strtoupper(substr($role, 0, 3)) . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                    'is_verified' => true,
                    'is_active' => true
                ];
                supabaseRequest('users', 'POST', $newUser);
                header('Location: admin_dashboard.php?section=users&msg=User added');
                exit;
            } catch (Exception $e) {}
        }
        header('Location: admin_dashboard.php?section=users&msg=Error adding user');
        exit;
    }
}

// ===== FETCH DATA =====
$books = [];
$categories = [];
$users = [];
$borrowings = [];
$fines = [];
$fineSettings = [];
$academicYears = [];
$bookError = '';
$userMessage = isset($_GET['msg']) ? $_GET['msg'] : '';
$bookSearchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$userSearchTerm = isset($_GET['user_search']) ? $_GET['user_search'] : '';
$userFilterRole = isset($_GET['user_filter']) ? $_GET['user_filter'] : 'all';
$aiClassified = isset($_GET['ai_classified']) ? $_GET['ai_classified'] : '';

try {
    $books = supabaseRequest('books?select=*');
    $categories = supabaseRequest('categories?select=*');
    $users = supabaseRequest('users?select=*');
    
    $borrowingsRaw = supabaseRequest('borrowings?select=*,books(title),users(full_name,user_id,username)');
    
    $borrowings = [];
    if (is_array($borrowingsRaw) && !empty($borrowingsRaw)) {
        foreach ($borrowingsRaw as $b) {
            $bookTitle = 'N/A';
            if (isset($b['books']) && is_array($b['books'])) {
                $bookTitle = $b['books']['title'] ?? 'N/A';
            }
            
            $userFullName = 'N/A';
            if (isset($b['users']) && is_array($b['users'])) {
                $userFullName = $b['users']['full_name'] ?? 'N/A';
            }
            
            $borrowings[] = [
                'id' => $b['id'] ?? null,
                'book_id' => $b['book_id'] ?? null,
                'user_id' => $b['user_id'] ?? null,
                'borrow_date' => $b['borrow_date'] ?? null,
                'due_date' => $b['due_date'] ?? null,
                'return_date' => $b['return_date'] ?? null,
                'status' => $b['status'] ?? null,
                'fine_amount' => $b['fine_amount'] ?? null,
                'is_returned' => $b['is_returned'] ?? null,
                'notes' => $b['notes'] ?? null,
                'created_at' => $b['created_at'] ?? null,
                'updated_at' => $b['updated_at'] ?? null,
                'book_title' => $bookTitle,
                'user_full_name' => $userFullName
            ];
        }
    }
    
    $fines = supabaseRequest('fines?select=*');
    $fineSettings = supabaseRequest('fine_settings?select=*');
    $academicYears = supabaseRequest('academic_years?select=*');
} catch (Exception $e) {
    $bookError = $e->getMessage();
}

$catMap = [];
foreach ($categories as $cat) {
    $catMap[$cat['id']] = $cat['name'];
}

$filteredUsers = array_filter($users, function($u) use ($userSearchTerm, $userFilterRole) {
    if ($userFilterRole !== 'all' && ($u['role'] ?? '') !== $userFilterRole) {
        return false;
    }
    if (empty($userSearchTerm)) return true;
    $search = strtolower($userSearchTerm);
    return strpos(strtolower($u['full_name'] ?? ''), $search) !== false ||
           strpos(strtolower($u['username'] ?? ''), $search) !== false ||
           strpos(strtolower($u['email'] ?? ''), $search) !== false ||
           strpos(strtolower($u['user_id'] ?? ''), $search) !== false;
});

$filteredBooks = array_filter($books, function($b) use ($bookSearchTerm) {
    if (empty($bookSearchTerm)) return true;
    $search = strtolower($bookSearchTerm);
    return strpos(strtolower($b['title'] ?? ''), $search) !== false ||
           strpos(strtolower($b['author'] ?? ''), $search) !== false ||
           strpos(strtolower($b['isbn'] ?? ''), $search) !== false;
});

function getPlaceholderColor($id) {
    $colors = ['#b40f7d', '#8a0a5f', '#cf1fa9', '#d460b8', '#e8a0d0', '#f0c8e0', '#f5e0ee', '#faf0f5'];
    $hash = crc32($id);
    if ($hash < 0) $hash = -$hash;
    return $colors[$hash % count($colors)];
}

$stats = [
    'totalUsers' => count($users),
    'totalStudents' => count(array_filter($users, function($u) { return $u['role'] === 'student'; })),
    'totalLibrarians' => count(array_filter($users, function($u) { return $u['role'] === 'librarian'; })),
    'totalBooks' => count($books),
    'totalBorrowings' => count($borrowings),
    'totalOverdue' => count(array_filter($borrowings, function($b) { return $b['status'] === 'Overdue'; })),
    'totalFines' => array_sum(array_column($fines, 'amount')),
    'totalFinesCount' => count($fines),
    'paidFines' => count(array_filter($fines, function($f) { return $f['status'] === 'Paid'; }))
];

$borrowingRate = $stats['totalBooks'] > 0 ? round(($stats['totalBorrowings'] / $stats['totalBooks']) * 100) : 0;
$availableRate = $stats['totalBooks'] > 0 ? round((($stats['totalBooks'] - $stats['totalBorrowings']) / $stats['totalBooks']) * 100) : 0;
$overdueRate = $stats['totalBooks'] > 0 ? round(($stats['totalOverdue'] / $stats['totalBooks']) * 100) : 0;
$activeUsersRate = $stats['totalUsers'] > 0 ? round(($stats['totalUsers'] / ($stats['totalUsers'] + 10)) * 100) : 0;

$total = $stats['totalUsers'] > 0 ? $stats['totalUsers'] : 1;
$present = min(85, round(($stats['totalBorrowings'] / ($total * 2)) * 100));
$late = min(15, round(($stats['totalOverdue'] / ($total * 2)) * 100));
$absent = max(0, 100 - $present - $late - 5);
$excused = 5;
$presentCirc = ($present / 100) * 251.2;
$lateCirc = ($late / 100) * 188.4;
$absentCirc = ($absent / 100) * 125.6;

$fineSettingsData = !empty($fineSettings) ? $fineSettings[0] : ['fine_per_day' => 50, 'lost_book_fee' => 500, 'damaged_book_fee' => 200, 'grace_period' => 0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - St. Agnes Academy Caloocan Inc.</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; 
            background: #f8f0f5;
            color: #1a1a2e;
        }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f5e8f0; }
        ::-webkit-scrollbar-thumb { background: #d4a8c0; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #b88aa8; }

        .admin-app { display: flex; min-height: 100vh; }
        .admin-sidebar {
            width: 240px;
            background: #010107;
            color: #e8dce8;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
            border-right: 1px solid #2a2a3e;
        }
        .sidebar-header { 
            padding: 28px 24px 20px; 
            border-bottom: 1px solid rgba(255,255,255,0.06);
            text-align: left;
        }
        .sidebar-header .sidebar-logo {
            max-width: 72px;
            height: auto;
            display: block;
            margin-bottom: 12px;
        }
        .sidebar-header .school-name {
            color: #f0e8e8;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.5px;
            line-height: 1.2;
        }
        .sidebar-header .school-sub {
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            letter-spacing: 1px;
            margin-top: 2px;
            font-weight: 300;
        }
        .sidebar-header .user-info {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .sidebar-header .user-info p { 
            margin: 0; 
            font-size: 13px;
            color: #e8dce8;
            font-weight: 500;
        }
        .sidebar-header .user-info small { 
            opacity: 0.4; 
            font-size: 11px; 
            display: block;
            color: #e8dce8;
        }
        
        .sidebar-nav { flex: 1; padding: 12px 0; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 24px;
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            font-size: 14px;
            font-weight: 400;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
            position: relative;
        }
        .sidebar-nav a:hover { 
            color: #f0e8e8; 
            background: rgba(255,255,255,0.04);
            border-left-color: rgba(255,255,255,0.15);
        }
        .sidebar-nav a.active { 
            color: #f0e8e8; 
            background: rgba(180, 15, 125, 0.18);
            border-left-color: #b40f7d;
        }
        .sidebar-nav a .nav-icon {
            font-size: 17px;
            width: 22px;
            text-align: center;
            opacity: 0.6;
        }
        .sidebar-nav a.active .nav-icon {
            opacity: 1;
        }
        .sidebar-nav a .nav-label {
            flex: 1;
        }
        .sidebar-nav a .request-count-badge {
            background: #b40f7d;
            color: white;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-left: auto;
        }

        .sidebar-footer { 
            padding: 16px 24px 24px; 
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .logout-btn {
            width: 100%;
            padding: 10px 16px;
            background: rgba(180, 15, 125, 0.15);
            color: #d460b8;
            border: 1px solid rgba(180, 15, 125, 0.2);
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            display: block;
            font-weight: 500;
        }
        .logout-btn:hover { 
            background: rgba(180, 15, 125, 0.2);
            border-color: rgba(180, 15, 125, 0.3);
        }

        .admin-content { 
            margin-left: 240px; 
            flex: 1; 
            padding: 32px 40px; 
            background: #f8f0f5; 
            min-height: 100vh; 
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 2000;
            background: #1a1a2e;
            color: #e8dce8;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            padding: 10px 12px;
            cursor: pointer;
            min-height: 44px;
            min-width: 44px;
        }
        .hamburger-icon { display: flex; flex-direction: column; gap: 4px; width: 22px; }
        .hamburger-icon span { display: block; height: 2px; width: 100%; background: #e8dce8; border-radius: 2px; transition: 0.3s; }
        .mobile-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 999;
        }

        .dashboard-content { padding: 0; }
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
            padding: 28px 32px;
            background: linear-gradient(135deg, #08080a 0%, #0a090a 30%, #d41688b0 60%, #ff0199a8 80%, #ff0199c9 100%);
            border-radius: 16px;
            position: relative;
            overflow: hidden;
        }
        .dashboard-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -5%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 200, 230, 0.08) 0%, transparent 70%);
            border-radius: 50%;
        }
        .dashboard-header .header-left {
            position: relative;
            z-index: 1;
        }
        .dashboard-header h1 { 
            font-size: 22px; 
            color: #f0e8e8; 
            margin: 0;
            font-weight: 600;
            letter-spacing: -0.3px;
        }
        .dashboard-header .header-sub {
            color: rgba(255,255,255,0.5);
            font-size: 13px;
            margin: 4px 0 0;
        }
        .header-time {
            text-align: right;
            background: rgba(255,255,255,0.06);
            padding: 8px 20px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.06);
            position: relative;
            z-index: 1;
        }
        .header-time .time { 
            font-size: 20px; 
            font-weight: 600; 
            color: #000000; 
            display: block; 
            letter-spacing: 0.3px;
        }
        .header-time .date { 
            font-size: 12px; 
            color: rgba(0, 0, 0, 0.99); 
            letter-spacing: 0.3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #ffffff;
            padding: 16px 14px;
            border-radius: 12px;
            border: 1px solid #f0e0ee;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .stat-card:hover {
            border-color: #d460b8;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .stat-number { 
            font-size: 24px; 
            font-weight: 700; 
            color: #1a1a2e; 
            letter-spacing: -0.3px;
        }
        .stat-label { 
            font-size: 11px; 
            color: #8a7a8a; 
            margin-top: 2px; 
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stat-sub { 
            font-size: 10px; 
            color: #b8a8b8; 
            margin-top: 2px; 
        }

        .analytics-summary {
            background: #ffffff;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 24px;
            border: 1px solid #f0e0ee;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .analytics-header h2 { 
            font-size: 16px; 
            color: #1a1a2e; 
            margin: 0; 
            font-weight: 600;
        }
        .btn-view-analytics {
            padding: 8px 20px;
            background: #1a1a2e;
            color: #f0e8e8;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-view-analytics:hover { 
            background: #4a1a4a;
            transform: translateY(-1px);
        }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        .analytics-item { display: flex; flex-direction: column; gap: 6px; }
        .analytics-label { font-size: 13px; color: #8a7a8a; font-weight: 400; }
        .progress-bar { 
            width: 100%; 
            height: 4px; 
            background: #f0e8ee; 
            border-radius: 2px; 
            overflow: hidden; 
        }
        .progress-fill { 
            height: 100%; 
            background: #b40f7d; 
            border-radius: 2px; 
            transition: width 0.6s ease; 
        }
        .analytics-value { 
            font-size: 14px; 
            font-weight: 600; 
            color: #1a1a2e; 
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #f0e0ee;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            border-bottom: 1px solid #f5eef5;
        }
        .card-header h3 { 
            font-size: 15px; 
            color: #1a1a2e; 
            margin: 0; 
            font-weight: 600;
        }
        .view-all { 
            color: #b40f7d; 
            text-decoration: none; 
            font-size: 13px; 
            font-weight: 500;
        }
        .view-all:hover { text-decoration: underline; }
        .card-body { padding: 16px 24px; }

        .no-activity { text-align: center; padding: 32px 0; }
        .no-activity-icon { 
            font-size: 36px; 
            display: block; 
            margin-bottom: 8px; 
            opacity: 0.3;
            color: #b40f7d;
        }
        .no-activity p { color: #b8a8b8; font-size: 14px; margin: 0; }
        .no-activity-hint { color: #d4c8d4; font-size: 12px; }

        .breakdown-chart {
            display: flex;
            align-items: center;
            gap: 30px;
            justify-content: center;
            padding: 8px 0;
        }
        .breakdown-circle { position: relative; width: 120px; height: 120px; }
        .donut-chart { width: 120px; height: 120px; transform: rotate(-90deg); }
        .breakdown-center {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        .breakdown-percent { 
            font-size: 22px; 
            font-weight: 700; 
            color: #1a1a2e; 
            display: block; 
        }
        .breakdown-label { font-size: 10px; color: #8a7a8a; }
        .breakdown-date { font-size: 12px; color: #b8a8b8; }
        .breakdown-legend { display: flex; flex-direction: column; gap: 8px; }
        .legend-item { display: flex; align-items: center; gap: 10px; }
        .legend-color { 
            width: 10px; 
            height: 10px; 
            border-radius: 50%; 
        }
        .legend-color.present { background: #b40f7d; }
        .legend-color.late { background: #d460b8; }
        .legend-color.absent { background: #8a7a8a; }
        .legend-color.excused { background: #c8b8c8; }
        .legend-label { font-size: 13px; color: #6a5a6a; flex: 1; }
        .legend-value { font-size: 13px; font-weight: 600; color: #1a1a2e; }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .quick-action-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 16px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid #f0e0ee;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
        }
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.06);
            border-color: #d460b8;
        }
        .action-icon { 
            font-size: 22px; 
            display: block; 
            margin-bottom: 6px; 
            opacity: 0.6;
            color: #b40f7d;
        }
        .action-label { font-size: 13px; color: #4a3a4a; font-weight: 500; }
        .action-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            background: #b40f7d;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
        }

        .dashboard-footer {
            display: flex;
            justify-content: center;
            gap: 24px;
            padding: 16px 0;
            color: #8a7a8a;
            font-size: 13px;
            border-top: 1px solid #f0e0ee;
        }

        .book-management, .user-management, .reports-content, .settings-content, .requests-management { padding: 0; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .section-header h1 { 
            font-size: 22px; 
            color: #1a1a2e; 
            margin: 0; 
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .header-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
        .btn-add, .btn-save, .btn-export {
            padding: 10px 20px;
            background: #1a1a2e;
            color: #f0e8e8;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-add:hover, .btn-save:hover, .btn-export:hover { 
            background: #4a1a4a;
            transform: translateY(-1px);
        }
        .btn-export { background: #4a1a4a; }
        .btn-export:hover { background: #5a2a5a; }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .search-bar .search-input-wrapper {
            flex: 1;
            position: relative;
            min-width: 200px;
        }
        .search-bar .search-input-wrapper input {
            width: 100%;
            padding: 12px 16px 12px 44px;
            border: 2px solid #f0e0ee;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #ffffff;
            color: #1a1a2e;
        }
        .search-bar .search-input-wrapper input:focus {
            border-color: #b40f7d;
            outline: none;
            box-shadow: 0 0 0 3px rgba(180, 15, 125, 0.12);
        }
        .search-bar .search-input-wrapper .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #b8a8b8;
            font-size: 16px;
        }
        .search-bar .search-input-wrapper .clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #b8a8b8;
            cursor: pointer;
            font-size: 18px;
            display: none;
            padding: 4px 8px;
        }
        .search-bar .search-input-wrapper .clear-btn.visible { display: block; }
        .search-bar .search-input-wrapper .clear-btn:hover { color: #4a3a4a; }
        .count-badge { 
            color: #8a7a8a; 
            font-size: 14px; 
            white-space: nowrap; 
            font-weight: 400;
        }

        .filter-dropdown {
            padding: 10px 16px;
            border: 2px solid #f0e0ee;
            border-radius: 10px;
            font-size: 14px;
            background: #ffffff;
            color: #1a1a2e;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 140px;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238a7a8a' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }
        .filter-dropdown:focus {
            border-color: #b40f7d;
            outline: none;
            box-shadow: 0 0 0 3px rgba(180, 15, 125, 0.12);
        }
        .filter-dropdown:hover {
            border-color: #d460b8;
        }

        .message { 
            padding: 14px 20px; 
            border-radius: 10px; 
            margin-bottom: 20px; 
            font-weight: 500;
        }
        .message.success { 
            background: #f0e8ee; 
            color: #3a2a3a; 
            border-left: 4px solid #b40f7d;
        }
        .message.error { 
            background: #f0e0e8; 
            color: #8a2a5a; 
            border-left: 4px solid #d460b8;
        }
        .message.info { 
            background: #e8e4e8; 
            color: #3a3a3a; 
            border-left: 4px solid #b8a8b8;
        }

        .table-container {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #f0e0ee;
            overflow-x: auto;
        }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th {
            background: #f5eef5;
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: #4a3a4a;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .data-table td { 
            padding: 12px 16px; 
            border-top: 1px solid #f5eef5; 
            vertical-align: middle;
            color: #2a2a2a;
            font-size: 14px;
        }
        .data-table tr:hover td {
            background: #faf5fa;
        }

        .cover-cell { width: 60px; min-width: 60px; padding: 4px !important; text-align: center; }
        .book-cover-small {
            width: 50px; height: 65px;
            object-fit: cover;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: block;
            margin: 0 auto;
            background: #f5eef5;
        }
        .cover-placeholder-small {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px; height: 65px;
            border-radius: 4px;
            color: #f0e8e8;
            font-size: 22px;
            font-weight: 600;
            margin: 0 auto;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .btn-edit {
            padding: 4px 14px;
            background: #f5eef5;
            color: #4a3a4a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 4px;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        .btn-edit:hover { background: #e8dce8; }
        .btn-delete {
            padding: 4px 14px;
            background: #f0e0e8;
            color: #8a2a5a;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-delete:hover { background: #e8d0dc; }
        .btn-toggle {
            padding: 4px 14px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin-right: 4px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        .btn-toggle.activate { background: #f0e8ee; color: #3a2a3a; }
        .btn-toggle.deactivate { background: #f0e0e8; color: #8a2a5a; }
        .btn-toggle.activate:hover { background: #e8dce8; }
        .btn-toggle.deactivate:hover { background: #e8d0dc; }

        .role-badge { 
            padding: 2px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            display: inline-block; 
            font-weight: 500;
        }
        .role-admin { background: #f0e0e8; color: #8a2a5a; }
        .role-librarian { background: #f0e8ee; color: #4a3a4a; }
        .role-student { background: #e8f0ee; color: #2a4a4a; }
        .status-badge { 
            padding: 2px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            display: inline-block; 
            font-weight: 500;
        }
        .status-badge.active { background: #f0e8ee; color: #3a2a3a; }
        .status-badge.inactive { background: #f0e0e8; color: #8a2a5a; }
        .status-badge.pending { background: #f0e8ee; color: #6a5a6a; }
        .status-badge.paid { background: #e8f0ee; color: #2a4a4a; }
        .status-badge.overdue { background: #f0e0e8; color: #8a2a5a; }
        .status-badge.approved { background: #e8f0ee; color: #2a4a4a; }
        .status-badge.rejected { background: #f0e0e8; color: #8a2a5a; }
        .status-badge.fulfilled { background: #e8f0ee; color: #2a4a4a; }

        .no-data { text-align: center; padding: 30px !important; color: #b8a8b8; }

        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: #ffffff;
            padding: 32px 36px;
            border-radius: 16px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal h3 { 
            margin-top: 0; 
            color: #1a1a2e; 
            margin-bottom: 24px; 
            font-weight: 600;
            font-size: 20px;
        }
        .modal .form-group { margin-bottom: 16px; }
        .modal .form-group label { 
            display: block; 
            font-weight: 600; 
            font-size: 14px; 
            color: #1a1a2e; 
            margin-bottom: 4px; 
        }
        .modal .form-group input, 
        .modal .form-group select, 
        .modal .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #f0e0ee;
            border-radius: 8px;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
            background: #faf5fa;
            transition: all 0.2s ease;
        }
        .modal .form-group input:focus, 
        .modal .form-group select:focus, 
        .modal .form-group textarea:focus { 
            border-color: #b40f7d; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(180, 15, 125, 0.12);
        }
        .modal .form-group textarea { resize: vertical; min-height: 60px; }
        .modal .modal-actions { 
            display: flex; 
            gap: 10px; 
            margin-top: 24px; 
            justify-content: flex-end; 
        }
        .btn-cancel { 
            padding: 10px 24px; 
            background: #f5eef5; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-cancel:hover { background: #e8dce8; }
        .btn-confirm { 
            padding: 10px 24px; 
            background: #1a1a2e; 
            color: #f0e8e8; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        .btn-confirm:hover { background: #4a1a4a; }

        .cover-upload-container {
            border: 2px dashed #f0e0ee;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            min-height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            transition: all 0.3s ease;
            background: #faf5fa;
        }
        .cover-upload-container:hover { border-color: #b40f7d; }
        .cover-input {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        .cover-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            color: #8a7a8a;
        }
        .cover-icon { font-size: 36px; opacity: 0.4; color: #b40f7d; }
        .cover-hint { font-size: 12px; color: #c8b8c8; }
        .cover-preview-container { position: relative; display: inline-block; }
        .cover-preview {
            max-width: 150px;
            max-height: 200px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .btn-remove-cover {
            position: absolute;
            top: -8px; right: -8px;
            width: 26px; height: 26px;
            border-radius: 50%;
            background: #f0e0e8;
            color: #8a2a5a;
            border: 2px solid #ffffff;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }
        .btn-remove-cover:hover { background: #e8d0dc; }

        .stats-grid-reports {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .stat-card-report {
            background: #ffffff;
            padding: 20px 24px;
            border-radius: 12px;
            border: 1px solid #f0e0ee;
        }
        .stat-card-report h3 { 
            font-size: 28px; 
            margin: 0; 
            color: #1a1a2e; 
            font-weight: 700;
            letter-spacing: -0.5px;
        }
        .stat-card-report p { margin: 4px 0 0; color: #8a7a8a; }
        .export-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }

        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .setting-item { display: flex; flex-direction: column; gap: 4px; }
        .setting-item label { 
            font-weight: 600; 
            font-size: 14px; 
            color: #4a3a4a; 
        }
        .setting-item input {
            padding: 10px 14px;
            border: 2px solid #f0e0ee;
            border-radius: 8px;
            font-size: 14px;
            background: #faf5fa;
            transition: all 0.2s ease;
        }
        .setting-item input:focus { 
            border-color: #b40f7d; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(180, 15, 125, 0.12);
        }
        .academic-year-item {
            display: flex;
            gap: 20px;
            padding: 12px 0;
            border-bottom: 1px solid #f5eef5;
            flex-wrap: wrap;
            align-items: center;
        }
        .academic-year-item .current { 
            color: #b40f7d; 
            font-weight: 600; 
            font-size: 13px;
        }

        /* AI Classification Styles */
        .ai-classification-msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 16px;
            display: none;
            font-size: 14px;
            background: #e8f0ee;
            color: #2a4a4a;
            border-left: 4px solid #34a853;
        }
        .ai-classification-msg.visible {
            display: block;
        }
        .ai-badge {
            display: inline-block;
            background: #34a853;
            color: #ffffff;
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 10px;
            margin-left: 8px;
            font-weight: 500;
        }
        .ai-suggestion-tag {
            display: inline-block;
            background: #e8f0ee;
            color: #2a4a4a;
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 10px;
            margin: 2px 4px 2px 0;
        }

        @media (max-width: 1200px) { 
            .stats-grid { grid-template-columns: repeat(4, 1fr); } 
        }
        @media (max-width: 992px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; }
            .analytics-grid { grid-template-columns: repeat(2, 1fr); }
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .admin-sidebar { width: 70px; }
            .sidebar-header .school-name, .sidebar-header .school-sub, 
            .sidebar-header .user-info, .sidebar-nav a .nav-label { display: none; }
            .sidebar-nav a { justify-content: center; padding: 14px; font-size: 20px; }
            .sidebar-nav a .nav-icon { font-size: 22px; }
            .admin-content { margin-left: 70px; padding: 20px 24px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-header { flex-direction: column; align-items: flex-start; }
            .header-time { width: 100%; text-align: left; }
            .breakdown-chart { flex-direction: column; }
            .analytics-grid { grid-template-columns: 1fr; }
            .quick-actions { grid-template-columns: 1fr 1fr; }
            .cover-cell { width: 45px; min-width: 45px; }
            .book-cover-small { width: 40px; height: 52px; }
            .cover-placeholder-small { width: 40px; height: 52px; font-size: 18px; }
        }
        @media (max-width: 480px) {
            .mobile-menu-toggle { display: flex !important; align-items: center; justify-content: center; }
            .admin-sidebar {
                position: fixed !important;
                top: 0 !important; left: 0 !important;
                width: 280px !important;
                height: 100vh !important;
                z-index: 1000 !important;
                transform: translateX(-100%) !important;
                transition: transform 0.3s ease !important;
                box-shadow: 2px 0 30px rgba(0,0,0,0.2) !important;
                padding-top: 60px !important;
            }
            .admin-sidebar.mobile-open { transform: translateX(0) !important; }
            .mobile-overlay { display: block !important; }
            .admin-content { margin-left: 0 !important; padding: 70px 12px 12px !important; }
            .sidebar-header .school-name, .sidebar-header .school-sub, 
            .sidebar-header .user-info, .sidebar-nav a .nav-label { display: block !important; }
            .sidebar-nav a { justify-content: flex-start; padding: 12px 20px; font-size: 14px; }
            .sidebar-nav a .nav-icon { font-size: 18px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-number { font-size: 20px; }
            .cover-cell { width: 35px; min-width: 35px; }
            .book-cover-small { width: 28px; height: 36px; }
            .cover-placeholder-small { width: 28px; height: 36px; font-size: 12px; }
            .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .dashboard-header { padding: 20px; }
            .dashboard-header h1 { font-size: 18px; }
            .modal { padding: 24px 20px; }
        }
    </style>
</head>
<body>
    <div class="admin-app">
        <button class="mobile-menu-toggle" onclick="toggleMobileMenu()">
            <span class="hamburger-icon"><span></span><span></span><span></span></span>
        </button>

        <div class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="img/agustinnb.png" alt="SAAC Logo" class="sidebar-logo">
                <div class="school-name">ST. AGNES ACADEMY</div>
                <div class="school-sub">Caloocan Inc.</div>
                <div class="user-info">
                    <p><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrator'); ?></p>
                    <small>ID: <?php echo htmlspecialchars($_SESSION['user_id_display'] ?? 'N/A'); ?></small>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="admin_dashboard.php?section=dashboard" class="<?php echo $section === 'dashboard' ? 'active' : ''; ?>">
                    <span class="nav-icon">🗠</span>
                    <span class="nav-label">Dashboard</span>
                </a>
                <a href="admin_dashboard.php?section=users" class="<?php echo $section === 'users' ? 'active' : ''; ?>">
                    <span class="nav-icon">◈</span>
                    <span class="nav-label">Users</span>
                </a>
                <a href="admin_dashboard.php?section=books" class="<?php echo $section === 'books' ? 'active' : ''; ?>">
                    <span class="nav-icon">🕮</span>
                    <span class="nav-label">Books</span>
                    <?php if ($aiClassified == '1'): ?>
                        <span class="ai-badge">AI</span>
                    <?php endif; ?>
                </a>
                <a href="admin_dashboard.php?section=requests" class="<?php echo $section === 'requests' ? 'active' : ''; ?>">
                    <span class="nav-icon">🖺</span>
                    <span class="nav-label">Requests</span>
                    <?php if (!empty($pendingRequests)): ?>
                        <span class="request-count-badge"><?php echo count($pendingRequests); ?></span>
                    <?php endif; ?>
                </a>
                <a href="admin_dashboard.php?section=reports" class="<?php echo $section === 'reports' ? 'active' : ''; ?>">
                    <span class="nav-icon">🖹</span>
                    <span class="nav-label">Reports</span>
                </a>
                <a href="admin_dashboard.php?section=settings" class="<?php echo $section === 'settings' ? 'active' : ''; ?>">
                    <span class="nav-icon">⚙</span>
                    <span class="nav-label">Settings</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="admin_logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <div class="mobile-overlay" id="mobileOverlay" onclick="toggleMobileMenu()"></div>

        <div class="admin-content">
            <?php if ($userMessage): ?>
                <div class="message info"><?php echo htmlspecialchars($userMessage); ?></div>
            <?php endif; ?>

            <?php if ($aiClassified == '1'): ?>
                <div class="message success">🤖 Book added with AI Classification! Tags and category were auto-suggested.</div>
            <?php endif; ?>

            <?php if ($section === 'dashboard'): ?>
            <!-- DASHBOARD CONTENT -->
            <div class="dashboard-content">
                <div class="dashboard-header">
                    <div class="header-left">
                        <h1>Library Management System</h1>
                        <p class="header-sub">Overview — <span id="currentDate"><?php echo date('F j, Y'); ?></span></p>
                    </div>
                    <div class="header-time">
                        <span class="time" id="currentTime"><?php echo date('g:i A'); ?></span>
                        <span class="date" id="currentDateDisplay"><?php echo date('F j, Y'); ?></span>
                    </div>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['totalUsers']; ?></div>
                        <div class="stat-label">Total Users</div>
                        <div class="stat-sub"><?php echo $stats['totalStudents']; ?> Students, <?php echo $stats['totalLibrarians']; ?> Librarians</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['totalBooks']; ?></div>
                        <div class="stat-label">Total Books</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['totalBorrowings']; ?></div>
                        <div class="stat-label">Total Borrowings</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo min($present, 100); ?>%</div>
                        <div class="stat-label">Active Borrowings</div>
                        <div class="stat-sub"><?php echo $stats['totalBorrowings']; ?> active</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo min($late, 100); ?>%</div>
                        <div class="stat-label">Late Returns</div>
                        <div class="stat-sub"><?php echo $stats['totalOverdue']; ?> overdue</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo min($absent, 100); ?>%</div>
                        <div class="stat-label">Inactive Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $excused; ?>%</div>
                        <div class="stat-label">Reserved Books</div>
                    </div>
                </div>
                <div class="analytics-summary">
                    <div class="analytics-header">
                        <h2>Library Analytics Summary</h2>
                        <a href="admin_dashboard.php?section=reports" class="btn-view-analytics">View Full Analytics →</a>
                    </div>
                    <div class="analytics-grid">
                        <div class="analytics-item">
                            <div class="analytics-label">Book Borrowing Rate</div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min($borrowingRate, 100); ?>%;"></div></div>
                            <div class="analytics-value"><?php echo $borrowingRate; ?>%</div>
                        </div>
                        <div class="analytics-item">
                            <div class="analytics-label">Active Users Rate</div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min($activeUsersRate, 100); ?>%;"></div></div>
                            <div class="analytics-value"><?php echo $activeUsersRate; ?>%</div>
                        </div>
                        <div class="analytics-item">
                            <div class="analytics-label">Book Availability</div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min($availableRate, 100); ?>%;"></div></div>
                            <div class="analytics-value"><?php echo $availableRate; ?>%</div>
                        </div>
                        <div class="analytics-item">
                            <div class="analytics-label">Overdue Rate</div>
                            <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min($overdueRate, 100); ?>%; background: #8a7a8a;"></div></div>
                            <div class="analytics-value"><?php echo $overdueRate; ?>%</div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header"><h3>Recent Activity</h3><a href="admin_dashboard.php?section=reports" class="view-all">View all →</a></div>
                        <div class="card-body">
                            <div class="no-activity">
                                <span class="no-activity-icon">▣</span>
                                <p>No recent activity yet</p>
                                <span class="no-activity-hint">Records will appear here</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header"><h3>Today's Breakdown</h3><span class="breakdown-date"><?php echo date('F j, Y'); ?></span></div>
                        <div class="card-body">
                            <div class="breakdown-chart">
                                <div class="breakdown-circle">
                                    <svg viewBox="0 0 100 100" class="donut-chart">
                                        <circle cx="50" cy="50" r="40" fill="none" stroke="#f0e0ee" stroke-width="12"/>
                                        <circle cx="50" cy="50" r="40" fill="none" stroke="#b40f7d" stroke-width="12" stroke-dasharray="<?php echo $presentCirc; ?> 251.2" stroke-dashoffset="0" transform="rotate(-90 50 50)"/>
                                        <circle cx="50" cy="50" r="30" fill="none" stroke="#d460b8" stroke-width="12" stroke-dasharray="<?php echo $lateCirc; ?> 188.4" stroke-dashoffset="0" transform="rotate(-90 50 50)"/>
                                        <circle cx="50" cy="50" r="20" fill="none" stroke="#8a7a8a" stroke-width="12" stroke-dasharray="<?php echo $absentCirc; ?> 125.6" stroke-dashoffset="0" transform="rotate(-90 50 50)"/>
                                    </svg>
                                    <div class="breakdown-center">
                                        <span class="breakdown-percent"><?php echo min($present, 100); ?>%</span>
                                        <span class="breakdown-label">Attendance</span>
                                    </div>
                                </div>
                                <div class="breakdown-legend">
                                    <div class="legend-item"><span class="legend-color present"></span><span class="legend-label">Present</span><span class="legend-value"><?php echo min($present, 100); ?>%</span></div>
                                    <div class="legend-item"><span class="legend-color late"></span><span class="legend-label">Late</span><span class="legend-value"><?php echo min($late, 100); ?>%</span></div>
                                    <div class="legend-item"><span class="legend-color absent"></span><span class="legend-label">Absent</span><span class="legend-value"><?php echo min($absent, 100); ?>%</span></div>
                                    <div class="legend-item"><span class="legend-color excused"></span><span class="legend-label">Excused</span><span class="legend-value"><?php echo $excused; ?>%</span></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="quick-actions">
                    <a href="admin_dashboard.php?section=books" class="quick-action-card">
                        <span class="action-icon">▣</span>
                        <span class="action-label">Add Book</span>
                        <span class="ai-badge" style="font-size:8px;position:absolute;top:4px;right:4px;">AI</span>
                    </a>
                    <a href="admin_dashboard.php?section=users" class="quick-action-card">
                        <span class="action-icon">◈</span>
                        <span class="action-label">Add User</span>
                    </a>
                    <a href="admin_dashboard.php?section=reports" class="quick-action-card">
                        <span class="action-icon">◉</span>
                        <span class="action-label">Export Reports</span>
                    </a>
                    <a href="admin_dashboard.php?section=requests" class="quick-action-card" style="position:relative;">
                        <span class="action-icon">📋</span>
                        <span class="action-label">Manage Requests</span>
                        <?php if (!empty($pendingRequests)): ?>
                            <span class="action-badge"><?php echo count($pendingRequests); ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="dashboard-footer">
                    <span>28°C</span>
                    <span>Partly cloudy</span>
                    <span id="footerTime"><?php echo date('g:i A'); ?></span>
                    <span id="footerDate"><?php echo date('F j, Y'); ?></span>
                </div>
            </div>

            <?php elseif ($section === 'books'): ?>
            <!-- BOOKS CONTENT -->
            <div class="book-management">
                <div class="section-header">
                    <h1>Book Inventory (<?php echo count($books); ?> books) <?php if ($aiClassified == '1'): ?><span class="ai-badge">AI Classified</span><?php endif; ?></h1>
                    <div class="header-actions">
                        <a href="admin_dashboard.php?section=books&action=export&format=pdf" class="btn-export" target="_blank">Export PDF</a>
                        <a href="admin_dashboard.php?section=books&action=export&format=excel" class="btn-export">Export Excel</a>
                        <button onclick="openAddBookModal()" class="btn-add">+ Add Book</button>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="bookSearchInput" placeholder="Search books by title, author, or ISBN..." value="<?php echo htmlspecialchars($bookSearchTerm); ?>" onkeyup="searchBooks(this.value)">
                        <button class="clear-btn <?php echo !empty($bookSearchTerm) ? 'visible' : ''; ?>" id="clearSearchBtn" onclick="clearSearch()">✕</button>
                    </div>
                    <span class="count-badge" id="bookCount"><?php echo count($filteredBooks); ?> books found</span>
                </div>

                <?php if ($bookError): ?>
                    <div class="message error">Error: <?php echo htmlspecialchars($bookError); ?></div>
                <?php endif; ?>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Category</th>
                                <th>Available</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filteredBooks)): ?>
                                <?php foreach ($filteredBooks as $b): 
                                    $hasValidImage = !empty($b['cover_image']) && strlen($b['cover_image']) > 100 && strpos($b['cover_image'], 'data:image') === 0;
                                    $categoryName = isset($b['category_id']) && isset($catMap[$b['category_id']]) ? $catMap[$b['category_id']] : 'Uncategorized';
                                ?>
                                    <tr>
                                        <td class="cover-cell">
                                            <?php if ($hasValidImage): ?>
                                                <img src="<?php echo htmlspecialchars($b['cover_image']); ?>" alt="<?php echo htmlspecialchars($b['title'] ?? ''); ?>" class="book-cover-small" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                                                <div class="cover-placeholder-small" style="display:none;background-color:<?php echo getPlaceholderColor($b['id']); ?>;"><?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '▣'; ?></div>
                                            <?php else: ?>
                                                <div class="cover-placeholder-small" style="background-color:<?php echo getPlaceholderColor($b['id']); ?>;"><?php echo isset($b['title']) ? strtoupper(substr($b['title'], 0, 1)) : '▣'; ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($b['title'] ?? ''); ?></strong></td>
                                        <td><?php echo htmlspecialchars($b['author'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($categoryName); ?></td>
                                        <td><?php echo ($b['available'] ?? 0); ?> / <?php echo ($b['quantity'] ?? 0); ?></td>
                                        <td>
                                            <button class="btn-edit" onclick="openEditBookModal('<?php echo $b['id']; ?>', '<?php echo addslashes($b['title']); ?>', '<?php echo addslashes($b['author']); ?>', '<?php echo addslashes($b['isbn'] ?? ''); ?>', '<?php echo addslashes($b['publisher'] ?? ''); ?>', '<?php echo $b['year_published'] ?? ''; ?>', '<?php echo $b['category_id'] ?? ''; ?>', '<?php echo $b['quantity'] ?? 1; ?>', '<?php echo $b['available'] ?? 1; ?>', '<?php echo addslashes($b['location'] ?? ''); ?>', '<?php echo addslashes($b['description'] ?? ''); ?>', '<?php echo addslashes($b['cover_image'] ?? ''); ?>')">Edit</button>
                                            <a href="admin_dashboard.php?section=books&delete_book=<?php echo $b['id']; ?>" class="btn-delete" onclick="return confirm('Delete this book?')">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="no-data">No books found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add Book Modal -->
            <div class="modal-overlay" id="addBookModal">
                <div class="modal">
                    <h3>Add New Book <span class="ai-badge">AI Powered</span></h3>
                    <div class="ai-classification-msg" id="aiClassificationMsg">
                        🤖 AI is analyzing your book...
                    </div>
                    <form method="POST" action="admin_dashboard.php?section=books&action=add_book" enctype="multipart/form-data" id="addBookForm">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="add_book_title" required>
                        </div>
                        <div class="form-group">
                            <label>Author *</label>
                            <input type="text" name="author" id="add_book_author" required>
                        </div>
                        <div class="form-group">
                            <label>Description <span style="font-weight:400;color:#8a7a8a;">(for AI classification)</span></label>
                            <textarea name="description" id="add_book_description" rows="3" placeholder="Describe the book content for AI classification..."></textarea>
                        </div>
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn">
                        </div>
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher">
                        </div>
                        <div class="form-group">
                            <label>Year Published</label>
                            <input type="number" name="year_published" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Category <span style="font-weight:400;color:#8a7a8a;">(AI will suggest)</span></label>
                            <select name="category_id" id="add_book_category">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label>Available</label>
                            <input type="number" name="available" min="0" value="1">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" placeholder="Shelf A-1">
                        </div>
                        <div class="form-group">
                            <label>Keywords/Tags <span style="font-weight:400;color:#8a7a8a;">(AI will suggest)</span></label>
                            <input type="text" name="keywords" id="add_book_keywords" placeholder="e.g. Philippine History, Revolution, Grade 7">
                        </div>
                        <div class="form-group">
                            <label>Cover Image</label>
                            <div class="cover-upload-container">
                                <input type="file" id="coverImageInput" accept="image/*" onchange="handleCoverImageUpload(event)" class="cover-input">
                                <div id="coverPlaceholder" class="cover-placeholder">
                                    <span class="cover-icon">▣</span>
                                    <span>No cover image selected</span>
                                    <span class="cover-hint">Click to upload (JPEG, PNG, GIF, WEBP)</span>
                                </div>
                                <div id="coverPreviewContainer" class="cover-preview-container" style="display:none;">
                                    <img id="coverPreview" class="cover-preview" alt="Cover Preview">
                                    <button type="button" onclick="removeCoverImage()" class="btn-remove-cover">✕</button>
                                </div>
                                <input type="hidden" name="cover_image" id="coverImageData" value="">
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="closeModal('addBookModal')" class="btn-cancel">Cancel</button>
                            <button type="submit" class="btn-confirm" id="addBookSubmitBtn">Add Book</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Edit Book Modal -->
            <div class="modal-overlay" id="editBookModal">
                <div class="modal">
                    <h3>Edit Book</h3>
                    <form method="POST" action="admin_dashboard.php?section=books&action=edit_book" enctype="multipart/form-data">
                        <input type="hidden" name="book_id" id="edit_book_id" value="">
                        <div class="form-group">
                            <label>Title *</label>
                            <input type="text" name="title" id="edit_title" required>
                        </div>
                        <div class="form-group">
                            <label>Author *</label>
                            <input type="text" name="author" id="edit_author" required>
                        </div>
                        <div class="form-group">
                            <label>ISBN</label>
                            <input type="text" name="isbn" id="edit_isbn">
                        </div>
                        <div class="form-group">
                            <label>Publisher</label>
                            <input type="text" name="publisher" id="edit_publisher">
                        </div>
                        <div class="form-group">
                            <label>Year Published</label>
                            <input type="number" name="year_published" id="edit_year" min="1000" max="<?php echo date('Y'); ?>">
                        </div>
                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="edit_category">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity *</label>
                            <input type="number" name="quantity" id="edit_quantity" min="1" required>
                        </div>
                        <div class="form-group">
                            <label>Available</label>
                            <input type="number" name="available" id="edit_available" min="0">
                        </div>
                        <div class="form-group">
                            <label>Location</label>
                            <input type="text" name="location" id="edit_location" placeholder="Shelf A-1">
                        </div>
                        <div class="form-group">
                            <label>Cover Image</label>
                            <div class="cover-upload-container">
                                <input type="file" id="editCoverImageInput" accept="image/*" onchange="handleEditCoverImageUpload(event)" class="cover-input">
                                <div id="editCoverPlaceholder" class="cover-placeholder">
                                    <span class="cover-icon">▣</span>
                                    <span>No cover image selected</span>
                                    <span class="cover-hint">Click to upload (JPEG, PNG, GIF, WEBP)</span>
                                </div>
                                <div id="editCoverPreviewContainer" class="cover-preview-container" style="display:none;">
                                    <img id="editCoverPreview" class="cover-preview" alt="Cover Preview">
                                    <button type="button" onclick="removeEditCoverImage()" class="btn-remove-cover">✕</button>
                                </div>
                                <input type="hidden" name="cover_image" id="editCoverImageData" value="">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" rows="3"></textarea>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="closeModal('editBookModal')" class="btn-cancel">Cancel</button>
                            <button type="submit" class="btn-confirm">Update Book</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'users'): ?>
            <!-- USERS CONTENT -->
            <div class="user-management">
                <div class="section-header">
                    <h1>User Management</h1>
                    <div class="header-actions">
                        <span class="count-badge">Total: <?php echo count($users); ?> users</span>
                        <button onclick="openModal('addUserModal')" class="btn-add">+ Add User</button>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="userSearchInput" placeholder="Search users by name, username, email, or ID..." value="<?php echo htmlspecialchars($userSearchTerm); ?>" onkeyup="searchUsers(this.value)">
                        <button class="clear-btn <?php echo !empty($userSearchTerm) ? 'visible' : ''; ?>" id="clearUserSearchBtn" onclick="clearUserSearch()">✕</button>
                    </div>
                    
                    <select class="filter-dropdown" id="userFilterSelect" onchange="filterUsers()">
                        <option value="all" <?php echo $userFilterRole === 'all' ? 'selected' : ''; ?>>All Users</option>
                        <option value="librarian" <?php echo $userFilterRole === 'librarian' ? 'selected' : ''; ?>>Librarians</option>
                        <option value="student" <?php echo $userFilterRole === 'student' ? 'selected' : ''; ?>>Students</option>
                    </select>
                    
                    <span class="count-badge" id="userCount"><?php echo count($filteredUsers); ?> users found</span>
                </div>

                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($filteredUsers)): ?>
                                <?php foreach ($filteredUsers as $u): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($u['user_id'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($u['full_name'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($u['username'] ?? ''); ?></td>
                                        <td><?php echo htmlspecialchars($u['email'] ?? ''); ?></td>
                                        <td><span class="role-badge role-<?php echo $u['role'] ?? 'student'; ?>"><?php echo $u['role'] ?? 'student'; ?></span></td>
                                        <td><span class="status-badge <?php echo ($u['is_active'] ?? true) ? 'active' : 'inactive'; ?>"><?php echo ($u['is_active'] ?? true) ? 'Active' : 'Inactive'; ?></span></td>
                                        <td>
                                            <a href="admin_dashboard.php?section=users&action=toggle&id=<?php echo $u['id']; ?>" class="btn-toggle <?php echo ($u['is_active'] ?? true) ? 'deactivate' : 'activate'; ?>" onclick="return confirm('Toggle user status?')"><?php echo ($u['is_active'] ?? true) ? 'Deactivate' : 'Activate'; ?></a>
                                            <?php if (($u['role'] ?? '') !== 'admin'): ?>
                                                <a href="admin_dashboard.php?section=users&action=delete&id=<?php echo $u['id']; ?>" class="btn-delete" onclick="return confirm('Delete this user?')">Delete</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="no-data">No users found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add User Modal -->
            <div class="modal-overlay" id="addUserModal">
                <div class="modal">
                    <h3>Add New User</h3>
                    <form method="POST" action="admin_dashboard.php?section=users&action=add">
                        <div class="form-group">
                            <label>Username *</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Password *</label>
                            <input type="password" name="password" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="full_name" required>
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role">
                                <option value="librarian">Librarian</option>
                                <option value="student">Student</option>
                            </select>
                        </div>
                        <div class="modal-actions">
                            <button type="button" onclick="closeModal('addUserModal')" class="btn-cancel">Cancel</button>
                            <button type="submit" class="btn-confirm">Add User</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php elseif ($section === 'requests'): ?>
            <!-- REQUESTS MANAGEMENT -->
            <div class="requests-management">
                <div class="section-header">
                    <h1>Book Requests (<?php echo count($bookRequests); ?> total)</h1>
                    <div class="header-actions">
                        <span class="count-badge">Pending: <?php echo count($pendingRequests); ?></span>
                    </div>
                </div>

                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">⌕</span>
                        <input type="text" id="requestSearchInput" placeholder="Search requests by student name, book title, or student ID..." value="<?php echo htmlspecialchars($requestSearchTerm); ?>" onkeyup="searchRequests(this.value)">
                        <button class="clear-btn <?php echo !empty($requestSearchTerm) ? 'visible' : ''; ?>" id="clearRequestSearchBtn" onclick="clearRequestSearch()">✕</button>
                    </div>
                    
                    <select class="filter-dropdown" id="requestFilterSelect" onchange="filterRequests()">
                        <option value="all" <?php echo $requestFilterStatus === 'all' ? 'selected' : ''; ?>>All Requests</option>
                        <option value="Pending" <?php echo $requestFilterStatus === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $requestFilterStatus === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $requestFilterStatus === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="Fulfilled" <?php echo $requestFilterStatus === 'Fulfilled' ? 'selected' : ''; ?>>Fulfilled</option>
                    </select>
                    
                    <span class="count-badge" id="requestCount"><?php echo count($filteredRequests); ?> requests found</span>
                </div>

                <?php if (!empty($filteredRequests)): ?>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Book</th>
                                    <th>Type</th>
                                    <th>Year/Section</th>
                                    <th>Purpose</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($filteredRequests as $r): 
                                    $isPending = ($r['status'] ?? '') === 'Pending';
                                    $isApproved = ($r['status'] ?? '') === 'Approved';
                                    $isRejected = ($r['status'] ?? '') === 'Rejected';
                                    $isFulfilled = ($r['status'] ?? '') === 'Fulfilled';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['full_name'] ?? 'N/A'); ?></strong>
                                            <br><small style="color:#8a7a8a;">ID: <?php echo htmlspecialchars($r['student_id'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($r['book_title'] ?? 'N/A'); ?></strong>
                                            <br><small style="color:#8a7a8a;">by <?php echo htmlspecialchars($r['book_author'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo ($r['request_type'] ?? 'borrow') === 'borrow' ? 'status-borrowed' : 'status-pending'; ?>">
                                                <?php echo ucfirst($r['request_type'] ?? 'borrow'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($r['year_level'] ?? 'N/A'); ?>
                                            <br><small style="color:#8a7a8a;"><?php echo htmlspecialchars($r['section'] ?? 'N/A'); ?></small>
                                        </td>
                                        <td>
                                            <div style="max-width:150px;font-size:13px;color:#6a5a6a;word-wrap:break-word;">
                                                <?php echo htmlspecialchars(substr($r['purpose'] ?? '', 0, 50)) . (strlen($r['purpose'] ?? '') > 50 ? '...' : ''); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($r['created_at'] ?? 'now')); ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $isPending ? 'status-pending' : ($isApproved ? 'status-approved' : ($isRejected ? 'status-rejected' : 'status-fulfilled')); ?>">
                                                <?php echo $r['status'] ?? 'Pending'; ?>
                                            </span>
                                            <?php if (!empty($r['verification_notes'])): ?>
                                                <br><small style="color:#8a7a8a;font-size:11px;">Note: <?php echo htmlspecialchars($r['verification_notes']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isPending): ?>
                                                <button class="btn-edit" onclick="openApproveModal('<?php echo $r['id']; ?>', '<?php echo addslashes($r['full_name']); ?>', '<?php echo addslashes($r['book_title']); ?>')">Approve</button>
                                                <button class="btn-delete" onclick="openRejectModal('<?php echo $r['id']; ?>', '<?php echo addslashes($r['full_name']); ?>', '<?php echo addslashes($r['book_title']); ?>')">Reject</button>
                                            <?php elseif ($isApproved): ?>
                                                <span style="color:#2a4a3a;font-size:12px;">✓ Approved</span>
                                            <?php elseif ($isRejected): ?>
                                                <span style="color:#8a3a2a;font-size:12px;">✗ Rejected</span>
                                            <?php elseif ($isFulfilled): ?>
                                                <span style="color:#4a3a2e;font-size:12px;">✓ Fulfilled</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="background:#ffffff;border-radius:16px;padding:60px 40px;text-align:center;border:1px solid #f0e0ee;">
                        <span style="font-size:56px;display:block;margin-bottom:16px;opacity:0.4;">📋</span>
                        <h3 style="color:#1a1a2e;margin-bottom:8px;">No Requests Found</h3>
                        <p style="color:#8a7a8a;font-size:15px;">No book requests have been submitted yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php elseif ($section === 'reports'): ?>
            <!-- REPORTS CONTENT -->
            <div class="reports-content">
                <h1>Reports & Analytics</h1>

                <div class="stats-grid-reports">
                    <div class="stat-card-report">
                        <h3><?php echo $stats['totalBorrowings']; ?></h3>
                        <p>Total Borrowings</p>
                    </div>
                    <div class="stat-card-report">
                        <h3><?php echo $stats['totalOverdue']; ?></h3>
                        <p>Overdue Books</p>
                    </div>
                    <div class="stat-card-report">
                        <h3>₱<?php echo number_format($stats['totalFines'], 2); ?></h3>
                        <p>Total Fines</p>
                    </div>
                    <div class="stat-card-report">
                        <h3><?php echo $stats['paidFines']; ?></h3>
                        <p>Paid Fines</p>
                    </div>
                </div>

                <?php if (!empty($borrowings)): ?>
                <div class="table-container" style="margin-bottom:20px;">
                    <h3 style="padding:15px 20px;margin:0;color:#1a1a2e;font-weight:600;">Recent Borrowings</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>User</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($borrowings, 0, 10) as $b): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($b['book_title'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($b['user_full_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($b['borrow_date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($b['due_date'] ?? 'N/A'); ?></td>
                                    <td><span class="status-badge <?php echo strtolower($b['status'] ?? 'pending'); ?>"><?php echo $b['status'] ?? 'Pending'; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="export-actions">
                    <a href="admin_dashboard.php?section=reports&action=export&format=pdf" class="btn-export">Export Borrowings PDF</a>
                    <a href="admin_dashboard.php?section=reports&action=export&format=excel" class="btn-export">Export Borrowings Excel</a>
                </div>
            </div>

            <?php elseif ($section === 'settings'): ?>
            <!-- SETTINGS CONTENT -->
            <div class="settings-content">
                <h1>System Settings</h1>

                <div style="background:#ffffff;padding:24px 28px;border-radius:16px;border:1px solid #f0e0ee;margin-bottom:20px;">
                    <h2 style="margin:0 0 16px 0;color:#1a1a2e;font-size:18px;font-weight:600;">Fine Settings</h2>
                    <form method="POST" action="admin_dashboard.php?section=settings&action=update_fines">
                        <div class="settings-grid">
                            <div class="setting-item">
                                <label>Fine per Day (₱)</label>
                                <input type="number" name="fine_per_day" value="<?php echo $fineSettingsData['fine_per_day'] ?? 50; ?>" step="0.50" min="0">
                            </div>
                            <div class="setting-item">
                                <label>Lost Book Fee (₱)</label>
                                <input type="number" name="lost_book_fee" value="<?php echo $fineSettingsData['lost_book_fee'] ?? 500; ?>" step="50" min="0">
                            </div>
                            <div class="setting-item">
                                <label>Damaged Book Fee (₱)</label>
                                <input type="number" name="damaged_book_fee" value="<?php echo $fineSettingsData['damaged_book_fee'] ?? 200; ?>" step="50" min="0">
                            </div>
                            <div class="setting-item">
                                <label>Grace Period (days)</label>
                                <input type="number" name="grace_period" value="<?php echo $fineSettingsData['grace_period'] ?? 0; ?>" min="0">
                            </div>
                        </div>
                        <button type="submit" class="btn-save" style="margin-top:16px;">Save Fine Settings</button>
                    </form>
                </div>

                <div style="background:#ffffff;padding:24px 28px;border-radius:16px;border:1px solid #f0e0ee;">
                    <h2 style="margin:0 0 16px 0;color:#1a1a2e;font-size:18px;font-weight:600;">Academic Years</h2>
                    <div class="academic-years-list">
                        <?php if (!empty($academicYears)): ?>
                            <?php foreach ($academicYears as $year): ?>
                                <div class="academic-year-item">
                                    <span><?php echo htmlspecialchars($year['year_name'] ?? ''); ?></span>
                                    <span><?php echo htmlspecialchars($year['start_date'] ?? ''); ?> - <?php echo htmlspecialchars($year['end_date'] ?? ''); ?></span>
                                    <span class="<?php echo ($year['is_current'] ?? false) ? 'current' : ''; ?>"><?php echo ($year['is_current'] ?? false) ? 'Current' : ''; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color:#b8a8b8;padding:20px 0;text-align:center;">No academic years set</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- APPROVE REQUEST MODAL -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="approveModal">
        <div class="modal">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #f0e0ee;">
                <h3 style="margin:0;color:#1a1a2e;font-weight:600;font-size:20px;">✅ Approve Request</h3>
                <button class="close-modal" onclick="closeModal('approveModal')" style="background:none;border:none;font-size:28px;color:#8a7a8a;cursor:pointer;">&times;</button>
            </div>
            <div style="background:#f0e8ee;border-radius:12px;padding:16px 20px;margin-bottom:20px;border-left:4px solid #b40f7d;">
                <p style="margin:0;font-size:14px;"><strong>Student:</strong> <span id="approveStudentName">Loading...</span></p>
                <p style="margin:4px 0 0;font-size:14px;"><strong>Book:</strong> <span id="approveBookTitle">Loading...</span></p>
            </div>
            <form method="GET" action="admin_dashboard.php">
                <input type="hidden" name="section" value="requests">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="approveRequestId" value="">
                
                <div class="form-group">
                    <label for="approveNotes">Verification Notes (Optional)</label>
                    <textarea id="approveNotes" name="notes" placeholder="Add any notes about this approval..." rows="3" style="width:100%;padding:10px 14px;border:2px solid #f0e0ee;border-radius:8px;font-size:14px;font-family:inherit;background:#faf5fa;transition:all 0.2s ease;resize:vertical;"></textarea>
                </div>
                
                <div class="modal-actions" style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
                    <button type="button" onclick="closeModal('approveModal')" class="btn-cancel" style="padding:10px 24px;background:#f5eef5;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;transition:all 0.2s ease;">Cancel</button>
                    <button type="submit" class="btn-confirm" style="padding:10px 24px;background:#2a4a3a;color:#f0e8e8;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;transition:all 0.2s ease;">Confirm Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ============================================ -->
    <!-- REJECT REQUEST MODAL -->
    <!-- ============================================ -->
    <div class="modal-overlay" id="rejectModal">
        <div class="modal">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:16px;border-bottom:2px solid #f0e0ee;">
                <h3 style="margin:0;color:#1a1a2e;font-weight:600;font-size:20px;">❌ Reject Request</h3>
                <button class="close-modal" onclick="closeModal('rejectModal')" style="background:none;border:none;font-size:28px;color:#8a7a8a;cursor:pointer;">&times;</button>
            </div>
            <div style="background:#f0e0e8;border-radius:12px;padding:16px 20px;margin-bottom:20px;border-left:4px solid #d460b8;">
                <p style="margin:0;font-size:14px;"><strong>Student:</strong> <span id="rejectStudentName">Loading...</span></p>
                <p style="margin:4px 0 0;font-size:14px;"><strong>Book:</strong> <span id="rejectBookTitle">Loading...</span></p>
            </div>
            <form method="GET" action="admin_dashboard.php">
                <input type="hidden" name="section" value="requests">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="rejectRequestId" value="">
                
                <div class="form-group">
                    <label for="rejectNotes">Rejection Reason (Optional)</label>
                    <textarea id="rejectNotes" name="notes" placeholder="Enter the reason for rejection..." rows="3" style="width:100%;padding:10px 14px;border:2px solid #f0e0ee;border-radius:8px;font-size:14px;font-family:inherit;background:#faf5fa;transition:all 0.2s ease;resize:vertical;"></textarea>
                </div>
                
                <div class="modal-actions" style="display:flex;gap:10px;margin-top:24px;justify-content:flex-end;">
                    <button type="button" onclick="closeModal('rejectModal')" class="btn-cancel" style="padding:10px 24px;background:#f5eef5;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;transition:all 0.2s ease;">Cancel</button>
                    <button type="submit" class="btn-confirm" style="padding:10px 24px;background:#8a2a5a;color:#f0e8e8;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;transition:all 0.2s ease;">Confirm Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // ===== EXISTING FUNCTIONS =====
        let currentCoverImageData = '';
        let editCoverImageData = '';
        let searchTimeout;
        let userSearchTimeout;
        let requestSearchTimeout;

        function handleCoverImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPEG, PNG, GIF, WEBP)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size should be less than 5MB');
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = function() {
                    const imageData = reader.result;
                    currentCoverImageData = imageData;
                    document.getElementById('coverImageData').value = imageData;
                    document.getElementById('coverPlaceholder').style.display = 'none';
                    document.getElementById('coverPreviewContainer').style.display = 'inline-block';
                    document.getElementById('coverPreview').src = imageData;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeCoverImage() {
            currentCoverImageData = '';
            document.getElementById('coverImageData').value = '';
            document.getElementById('coverPlaceholder').style.display = 'flex';
            document.getElementById('coverPreviewContainer').style.display = 'none';
            document.getElementById('coverPreview').src = '';
            document.getElementById('coverImageInput').value = '';
        }

        function handleEditCoverImageUpload(event) {
            const file = event.target.files[0];
            if (file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/jpg'];
                if (!validTypes.includes(file.type)) {
                    alert('Please upload a valid image file (JPEG, PNG, GIF, WEBP)');
                    event.target.value = '';
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    alert('Image size should be less than 5MB');
                    event.target.value = '';
                    return;
                }

                const reader = new FileReader();
                reader.onloadend = function() {
                    const imageData = reader.result;
                    editCoverImageData = imageData;
                    document.getElementById('editCoverImageData').value = imageData;
                    document.getElementById('editCoverPlaceholder').style.display = 'none';
                    document.getElementById('editCoverPreviewContainer').style.display = 'inline-block';
                    document.getElementById('editCoverPreview').src = imageData;
                };
                reader.readAsDataURL(file);
            }
        }

        function removeEditCoverImage() {
            editCoverImageData = '';
            document.getElementById('editCoverImageData').value = '';
            document.getElementById('editCoverPlaceholder').style.display = 'flex';
            document.getElementById('editCoverPreviewContainer').style.display = 'none';
            document.getElementById('editCoverPreview').src = '';
            document.getElementById('editCoverImageInput').value = '';
        }

        function openEditBookModal(id, title, author, isbn, publisher, year, category, quantity, available, location, description, coverImage) {
            document.getElementById('edit_book_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_author').value = author;
            document.getElementById('edit_isbn').value = isbn;
            document.getElementById('edit_publisher').value = publisher;
            document.getElementById('edit_year').value = year;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_quantity').value = quantity;
            document.getElementById('edit_available').value = available;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_description').value = description;
            
            if (coverImage && coverImage.length > 100) {
                editCoverImageData = coverImage;
                document.getElementById('editCoverImageData').value = coverImage;
                document.getElementById('editCoverPlaceholder').style.display = 'none';
                document.getElementById('editCoverPreviewContainer').style.display = 'inline-block';
                document.getElementById('editCoverPreview').src = coverImage;
            } else {
                removeEditCoverImage();
            }
            
            openModal('editBookModal');
        }

        function searchBooks(query) {
            const clearBtn = document.getElementById('clearSearchBtn');
            if (query.length > 0) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                const url = new URL(window.location.href);
                if (query.length > 0) {
                    url.searchParams.set('search', query);
                } else {
                    url.searchParams.delete('search');
                }
                window.location.href = url.toString();
            }, 400);
        }

        function clearSearch() {
            document.getElementById('bookSearchInput').value = '';
            document.getElementById('clearSearchBtn').classList.remove('visible');
            const url = new URL(window.location.href);
            url.searchParams.delete('search');
            window.location.href = url.toString();
        }

        function searchUsers(query) {
            const clearBtn = document.getElementById('clearUserSearchBtn');
            if (query.length > 0) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
            
            clearTimeout(userSearchTimeout);
            userSearchTimeout = setTimeout(function() {
                const url = new URL(window.location.href);
                if (query.length > 0) {
                    url.searchParams.set('user_search', query);
                } else {
                    url.searchParams.delete('user_search');
                }
                const filter = document.getElementById('userFilterSelect')?.value || 'all';
                if (filter !== 'all') {
                    url.searchParams.set('user_filter', filter);
                }
                window.location.href = url.toString();
            }, 400);
        }

        function clearUserSearch() {
            document.getElementById('userSearchInput').value = '';
            document.getElementById('clearUserSearchBtn').classList.remove('visible');
            const url = new URL(window.location.href);
            url.searchParams.delete('user_search');
            window.location.href = url.toString();
        }

        function filterUsers() {
            const filter = document.getElementById('userFilterSelect').value;
            const url = new URL(window.location.href);
            if (filter !== 'all') {
                url.searchParams.set('user_filter', filter);
            } else {
                url.searchParams.delete('user_filter');
            }
            window.location.href = url.toString();
        }

        // REQUEST FUNCTIONS WITH MODALS
        function openApproveModal(requestId, studentName, bookTitle) {
            document.getElementById('approveRequestId').value = requestId;
            document.getElementById('approveStudentName').textContent = studentName || 'Unknown Student';
            document.getElementById('approveBookTitle').textContent = bookTitle || 'Unknown Book';
            document.getElementById('approveNotes').value = '';
            openModal('approveModal');
        }

        function openRejectModal(requestId, studentName, bookTitle) {
            document.getElementById('rejectRequestId').value = requestId;
            document.getElementById('rejectStudentName').textContent = studentName || 'Unknown Student';
            document.getElementById('rejectBookTitle').textContent = bookTitle || 'Unknown Book';
            document.getElementById('rejectNotes').value = '';
            openModal('rejectModal');
        }

        function searchRequests(query) {
            const clearBtn = document.getElementById('clearRequestSearchBtn');
            if (query.length > 0) {
                clearBtn.classList.add('visible');
            } else {
                clearBtn.classList.remove('visible');
            }
            
            clearTimeout(requestSearchTimeout);
            requestSearchTimeout = setTimeout(function() {
                const url = new URL(window.location.href);
                if (query.length > 0) {
                    url.searchParams.set('request_search', query);
                } else {
                    url.searchParams.delete('request_search');
                }
                const filter = document.getElementById('requestFilterSelect')?.value || 'all';
                if (filter !== 'all') {
                    url.searchParams.set('request_filter', filter);
                }
                window.location.href = url.toString();
            }, 400);
        }

        function clearRequestSearch() {
            document.getElementById('requestSearchInput').value = '';
            document.getElementById('clearRequestSearchBtn').classList.remove('visible');
            const url = new URL(window.location.href);
            url.searchParams.delete('request_search');
            window.location.href = url.toString();
        }

        function filterRequests() {
            const filter = document.getElementById('requestFilterSelect').value;
            const url = new URL(window.location.href);
            if (filter !== 'all') {
                url.searchParams.set('request_filter', filter);
            } else {
                url.searchParams.delete('request_filter');
            }
            window.location.href = url.toString();
        }

        // EXISTING FUNCTIONS (continue)
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Asia/Manila' });
            const dateString = now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric', timeZone: 'Asia/Manila' });
            
            const timeEl = document.getElementById('currentTime');
            const dateEl = document.getElementById('currentDate');
            const dateDisplayEl = document.getElementById('currentDateDisplay');
            const footerTimeEl = document.getElementById('footerTime');
            const footerDateEl = document.getElementById('footerDate');
            
            if (timeEl) timeEl.textContent = timeString;
            if (dateEl) dateEl.textContent = dateString;
            if (dateDisplayEl) dateDisplayEl.textContent = dateString;
            if (footerTimeEl) footerTimeEl.textContent = timeString;
            if (footerDateEl) footerDateEl.textContent = dateString;
        }
        setInterval(updateClock, 1000);
        updateClock();

        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.toggle('mobile-open');
            overlay.style.display = sidebar.classList.contains('mobile-open') ? 'block' : 'none';
        }

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        function openAddBookModal() {
            openModal('addBookModal');
            removeCoverImage();
            // Reset AI classification message
            const msgDiv = document.getElementById('aiClassificationMsg');
            if (msgDiv) {
                msgDiv.classList.remove('visible');
                msgDiv.textContent = '🤖 AI is analyzing your book...';
            }
        }

        document.querySelectorAll('.modal-overlay').forEach(function(modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // ============================================
        // AI AUTO-CLASSIFICATION FOR BOOK ADD
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
    const addBookForm = document.getElementById('addBookForm');
    if (!addBookForm) return;
    
    const titleInput = document.getElementById('add_book_title');
    const descInput = document.getElementById('add_book_description');
    const categorySelect = document.getElementById('add_book_category');
    const keywordsInput = document.getElementById('add_book_keywords');
    const msgDiv = document.getElementById('aiClassificationMsg');
    const submitBtn = document.getElementById('addBookSubmitBtn');
    
    // Auto-classify when description changes (after typing stops)
    let classifyTimeout = null;
    
    function triggerClassification() {
        const title = titleInput?.value?.trim();
        const description = descInput?.value?.trim();
        const author = document.getElementById('add_book_author')?.value?.trim();
        
        // Only classify if we have title and description
        if (!title || title.length < 3 || !description || description.length < 10) {
            if (msgDiv) {
                msgDiv.textContent = '📝 Add a title and detailed description for AI classification...';
                msgDiv.className = 'ai-classification-msg';
                msgDiv.classList.remove('visible');
            }
            return;
        }
        
        // Show loading
        if (msgDiv) {
            msgDiv.textContent = '🤖 AI is analyzing your book...';
            msgDiv.className = 'ai-classification-msg visible';
            msgDiv.style.background = '#f0e8ee';
            msgDiv.style.color = '#4a3a4a';
            msgDiv.style.borderLeft = '4px solid #b40f7d';
        }
        
        // Call classification API
        fetch('/update-libV2/api/classify_book.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                title: title,
                description: description,
                author: author || ''
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.suggestions && data.suggestions.length > 0) {
                const suggestion = data.suggestions[0];
                
                // Auto-fill category if available
                if (suggestion.category_id && categorySelect) {
                    const option = categorySelect.querySelector(`option[value="${suggestion.category_id}"]`);
                    if (option) {
                        categorySelect.value = suggestion.category_id;
                    } else {
                        // Try to match by name
                        if (suggestion.category_name) {
                            const options = categorySelect.options;
                            for (let i = 0; i < options.length; i++) {
                                if (options[i].text.toLowerCase().includes(suggestion.category_name.toLowerCase())) {
                                    categorySelect.value = options[i].value;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Auto-fill keywords/tags
                if (data.tags && data.tags.length > 0 && keywordsInput) {
                    keywordsInput.value = data.tags.join(', ');
                } else if (suggestion.tags && suggestion.tags.length > 0 && keywordsInput) {
                    keywordsInput.value = suggestion.tags.join(', ');
                } else if (suggestion.subject && keywordsInput) {
                    let tags = [suggestion.subject];
                    if (suggestion.grade_level && suggestion.grade_level !== 'All Grades') {
                        tags.push(suggestion.grade_level);
                    }
                    keywordsInput.value = tags.join(', ');
                }
                
                // Show success message
                if (msgDiv) {
                    let categoryName = suggestion.category_name || 'suggested category';
                    let tagCount = (data.tags || suggestion.tags || []).length;
                    msgDiv.innerHTML = `✅ AI Classification Complete! <strong>Category:</strong> ${categoryName} • <strong>${tagCount}</strong> tags added.`;
                    msgDiv.className = 'ai-classification-msg visible';
                    msgDiv.style.background = '#e8f0ee';
                    msgDiv.style.color = '#2a4a4a';
                    msgDiv.style.borderLeft = '4px solid #34a853';
                }
            } else {
                if (msgDiv) {
                    msgDiv.textContent = '⚠️ AI classification available. Add more details for better suggestions.';
                    msgDiv.className = 'ai-classification-msg visible';
                    msgDiv.style.background = '#f0e0e8';
                    msgDiv.style.color = '#8a2a5a';
                    msgDiv.style.borderLeft = '4px solid #d460b8';
                }
            }
        })
        .catch(err => {
            console.error('Classification error:', err);
            if (msgDiv) {
                msgDiv.textContent = '⚠️ AI classification unavailable. You can still add the book manually.';
                msgDiv.className = 'ai-classification-msg visible';
                msgDiv.style.background = '#f0e0e8';
                msgDiv.style.color = '#8a2a5a';
                msgDiv.style.borderLeft = '4px solid #d460b8';
            }
        });
    }
    
    // Trigger classification on input with debounce
    if (descInput) {
        descInput.addEventListener('input', function() {
            clearTimeout(classifyTimeout);
            classifyTimeout = setTimeout(triggerClassification, 800);
        });
    }
    
    if (titleInput) {
        titleInput.addEventListener('input', function() {
            clearTimeout(classifyTimeout);
            classifyTimeout = setTimeout(triggerClassification, 800);
        });
    }
    
    // Also trigger when form is submitted
    addBookForm.addEventListener('submit', function(e) {
        // If AI hasn't classified yet, trigger it first
        const title = titleInput?.value?.trim();
        const description = descInput?.value?.trim();
        
        if (title && description && description.length > 10) {
            // Check if classification was done
            const msgText = msgDiv?.textContent || '';
            if (!msgText.includes('✅ AI Classification Complete')) {
                // Trigger classification and wait
                e.preventDefault();
                triggerClassification();
                
                // Wait 2 seconds then submit
                setTimeout(() => {
                    addBookForm.submit();
                }, 2000);
            }
        }
    });
});

// Also make sure categories exist in the dropdown
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('add_book_category');
    if (categorySelect && categorySelect.options.length <= 1) {
        // Try to fetch categories
        fetch('api/get_categories.php')
            .then(response => response.json())
            .then(data => {
                if (data.categories && data.categories.length > 0) {
                    data.categories.forEach(cat => {
                        const option = document.createElement('option');
                        option.value = cat.id;
                        option.textContent = cat.name;
                        categorySelect.appendChild(option);
                    });
                }
            })
            .catch(err => console.error('Error fetching categories:', err));
    }
});
    </script>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postAction = $_POST['action'] ?? '';
        
        if ($postAction === 'update_fines') {
            try {
                $data = [
                    'fine_per_day' => floatval($_POST['fine_per_day'] ?? 50),
                    'lost_book_fee' => floatval($_POST['lost_book_fee'] ?? 500),
                    'damaged_book_fee' => floatval($_POST['damaged_book_fee'] ?? 200),
                    'grace_period' => intval($_POST['grace_period'] ?? 0)
                ];
                if (!empty($fineSettings)) {
                    supabaseRequest('fine_settings?id=eq.' . $fineSettings[0]['id'], 'PATCH', $data);
                } else {
                    supabaseRequest('fine_settings', 'POST', $data);
                }
                header('Location: admin_dashboard.php?section=settings&msg=Settings updated');
                exit;
            } catch (Exception $e) {
                header('Location: admin_dashboard.php?section=settings&msg=Error updating settings');
                exit;
            }
        }
    }
    
    if ($section === 'books' && isset($_GET['delete_book'])) {
        try {
            supabaseRequest('books?id=eq.' . $_GET['delete_book'], 'DELETE');
            echo '<script>window.location.href="admin_dashboard.php?section=books&msg=Book deleted";</script>';
            exit;
        } catch (Exception $e) {
            echo '<script>window.location.href="admin_dashboard.php?section=books&msg=Error deleting book";</script>';
            exit;
        }
    }
    ?>
</body>
</html>