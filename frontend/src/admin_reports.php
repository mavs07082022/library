<?php
// admin_reports.php
// This file is included by admin_dashboard.php

$reportsData = [];
$finesData = [];
$totalFines = 0;

try {
    $borrowings = supabaseRequest('borrowings?select=*');
    $finesData = supabaseRequest('fines?select=*');
    $reportsData = [
        'stats' => $borrowings,
        'totalBorrowings' => count($borrowings),
        'activeBorrowings' => count(array_filter($borrowings, function($b) { return $b['status'] === 'Borrowed'; }))
    ];
    $totalFines = array_reduce($finesData, function($sum, $f) { return $sum + floatval($f['amount'] ?? 0); }, 0);
} catch (Exception $e) {
    error_log('Error fetching reports: ' . $e->getMessage());
}

$activeTab = isset($_GET['report_tab']) ? $_GET['report_tab'] : 'borrowing';
?>
<style>
    .reports-content { padding: 20px; }
    .reports-content h1 { font-size: 24px; color: #1a2e3f; margin-bottom: 20px; }
    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #f0f0f0;
        flex-wrap: wrap;
    }
    .tab {
        padding: 10px 20px;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        color: #666;
        border-bottom: 2px solid transparent;
        transition: 0.3s;
        text-decoration: none;
    }
    .tab.active { color: #667eea; border-bottom-color: #667eea; }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .stat-card h3 { font-size: 28px; margin: 0; color: #1a2e3f; }
    .stat-card p { margin: 5px 0 0; color: #666; }
    .fine-list, .activity-list {
        background: white;
        padding: 20px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        margin-bottom: 20px;
    }
    .fine-list h3, .activity-list h3 { margin: 0 0 15px 0; color: #1a2e3f; }
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    .data-table th {
        background: #f8f9fa;
        padding: 10px 14px;
        text-align: left;
        font-weight: 600;
        color: #555;
        font-size: 13px;
    }
    .data-table td { padding: 10px 14px; border-top: 1px solid #f0f0f0; }
    .status-badge { padding: 2px 10px; border-radius: 12px; font-size: 12px; display: inline-block; }
    .status-badge.pending { background: #fff3e0; color: #a8681a; }
    .status-badge.paid { background: #dff0e6; color: #14653b; }
    .status-badge.waived { background: #f0f0f0; color: #666; }
    .no-data-text { color: #999; padding: 20px; text-align: center; }
    .export-actions { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .btn-export {
        padding: 10px 20px;
        background: #1a2e3f;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: 0.3s;
    }
    .btn-export:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
</style>
<div class="reports-content">
    <h1>📈 Reports & Analytics</h1>

    <div class="tabs">
        <a href="admin_dashboard.php?section=reports&report_tab=borrowing" class="tab <?php echo $activeTab === 'borrowing' ? 'active' : ''; ?>">Borrowing Reports</a>
        <a href="admin_dashboard.php?section=reports&report_tab=fines" class="tab <?php echo $activeTab === 'fines' ? 'active' : ''; ?>">Fine Analytics</a>
        <a href="admin_dashboard.php?section=reports&report_tab=activity" class="tab <?php echo $activeTab === 'activity' ? 'active' : ''; ?>">User Activity</a>
    </div>

    <?php if ($activeTab === 'borrowing'): ?>
        <div class="stats-grid">
            <div class="stat-card"><h3><?php echo count($reportsData['stats'] ?? []); ?></h3><p>Borrowing Statuses</p></div>
            <div class="stat-card"><h3><?php echo $reportsData['totalBorrowings'] ?? 0; ?></h3><p>Total Borrowings</p></div>
            <div class="stat-card"><h3><?php echo $reportsData['activeBorrowings'] ?? 0; ?></h3><p>Active Borrowings</p></div>
        </div>
        <div class="export-actions">
            <button onclick="alert('Export PDF functionality')" class="btn-export">📄 Export PDF</button>
            <button onclick="alert('Export Excel functionality')" class="btn-export">📊 Export Excel</button>
        </div>
    <?php elseif ($activeTab === 'fines'): ?>
        <div class="stats-grid">
            <div class="stat-card"><h3>₱<?php echo number_format($totalFines, 2); ?></h3><p>Total Fines</p></div>
            <div class="stat-card"><h3><?php echo count($finesData); ?></h3><p>Fine Records</p></div>
            <div class="stat-card"><h3><?php echo count(array_filter($finesData, function($f) { return $f['status'] === 'Paid'; })); ?></h3><p>Paid Fines</p></div>
        </div>
        <?php if (!empty($finesData)): ?>
            <div class="fine-list">
                <h3>Fine Breakdown</h3>
                <table class="data-table">
                    <thead><tr><th>Reason</th><th>Amount</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($finesData, 0, 10) as $fine): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($fine['reason'] ?? 'Late Return'); ?></td>
                                <td>₱<?php echo number_format(floatval($fine['amount'] ?? 0), 2); ?></td>
                                <td><span class="status-badge <?php echo strtolower($fine['status'] ?? 'pending'); ?>"><?php echo $fine['status'] ?? 'Pending'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <div class="export-actions">
            <button onclick="alert('Export PDF functionality')" class="btn-export">📄 Export PDF</button>
            <button onclick="alert('Export Excel functionality')" class="btn-export">📊 Export Excel</button>
        </div>
    <?php else: ?>
        <div class="stats-grid">
            <div class="stat-card"><h3>0</h3><p>Total Activities</p></div>
            <div class="stat-card"><h3>0</h3><p>Logins</p></div>
            <div class="stat-card"><h3>0</h3><p>Borrowings</p></div>
        </div>
        <div class="activity-list">
            <h3>Recent User Activity</h3>
            <p class="no-data-text">No activity records available</p>
        </div>
        <div class="export-actions">
            <button onclick="alert('Export PDF functionality')" class="btn-export">📄 Export PDF</button>
            <button onclick="alert('Export Excel functionality')" class="btn-export">📊 Export Excel</button>
        </div>
    <?php endif; ?>
</div>