<?php
// admin_settings.php
// This file is included by admin_dashboard.php

$settingsMessage = '';
$fineSettings = ['fine_per_day' => 50, 'lost_book_fee' => 500, 'damaged_book_fee' => 200, 'grace_period' => 0];
$academicYears = [];

try {
    $settingsData = supabaseRequest('fine_settings?select=*');
    if (!empty($settingsData)) {
        $fineSettings = $settingsData[0];
    }
    $academicYears = supabaseRequest('academic_years?select=*&order=year_name.desc');
} catch (Exception $e) {
    $settingsMessage = '❌ Failed to load settings: ' . $e->getMessage();
}

// Handle Update Fine Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_fines') {
    $data = [
        'fine_per_day' => floatval($_POST['fine_per_day'] ?? 50),
        'lost_book_fee' => floatval($_POST['lost_book_fee'] ?? 500),
        'damaged_book_fee' => floatval($_POST['damaged_book_fee'] ?? 200),
        'grace_period' => intval($_POST['grace_period'] ?? 0)
    ];
    try {
        if (!empty($fineSettings['id'])) {
            supabaseRequest('fine_settings?id=eq.' . $fineSettings['id'], 'PATCH', $data);
        } else {
            supabaseRequest('fine_settings', 'POST', $data);
        }
        $settingsMessage = '✅ Settings updated!';
        echo '<meta http-equiv="refresh" content="1">';
    } catch (Exception $e) {
        $settingsMessage = '❌ Failed to update settings: ' . $e->getMessage();
    }
}

$activeTab = isset($_GET['settings_tab']) ? $_GET['settings_tab'] : 'fines';
?>
<style>
    .settings-content { padding: 20px; }
    .settings-content h1 { font-size: 24px; color: #1a2e3f; margin-bottom: 20px; }
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
    .settings-section {
        background: white;
        padding: 20px;
        border-radius: 16px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }
    .settings-section h2 { margin: 0 0 15px 0; color: #1a2e3f; font-size: 18px; }
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    .setting-item { display: flex; flex-direction: column; gap: 5px; }
    .setting-item label { font-weight: 600; font-size: 14px; color: #555; }
    .setting-item input {
        padding: 10px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }
    .setting-item input:focus { border-color: #667eea; outline: none; }
    .btn-save {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 15px;
    }
    .btn-save:hover { background: #5a6fd6; }
    .academic-years-list { margin-bottom: 15px; }
    .academic-year-item {
        display: flex;
        gap: 20px;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
        flex-wrap: wrap;
        align-items: center;
    }
    .academic-year-item .current { color: #2e7d32; font-weight: 600; }
    .btn-add-year {
        padding: 8px 16px;
        background: #e8f0fe;
        color: #667eea;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    .btn-add-year:hover { background: #d2e0fc; }
    .no-data { color: #999; padding: 20px 0; text-align: center; }
    .no-data-text { text-align: center; color: #999; padding: 20px; }
    .message { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
    .message.success { background: #dff0e6; color: #14653b; }
    .message.error { background: #fce4ec; color: #d32f2f; }
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
        border-bottom: 2px solid #e0e0e0;
    }
    .data-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f0; }
    .audit-table-container { overflow-x: auto; }
    @media (max-width: 768px) {
        .settings-grid { grid-template-columns: 1fr; }
        .academic-year-item { flex-direction: column; gap: 5px; }
    }
</style>
<div class="settings-content">
    <h1>⚙️ System Settings</h1>

    <?php if ($settingsMessage): ?>
        <div class="message <?php echo strpos($settingsMessage, '❌') !== false ? 'error' : 'success'; ?>"><?php echo $settingsMessage; ?></div>
    <?php endif; ?>

    <div class="tabs">
        <a href="admin_dashboard.php?section=settings&settings_tab=fines" class="tab <?php echo $activeTab === 'fines' ? 'active' : ''; ?>">Fine Settings</a>
        <a href="admin_dashboard.php?section=settings&settings_tab=academic" class="tab <?php echo $activeTab === 'academic' ? 'active' : ''; ?>">Academic Years</a>
        <a href="admin_dashboard.php?section=settings&settings_tab=audit" class="tab <?php echo $activeTab === 'audit' ? 'active' : ''; ?>">Audit Logs</a>
    </div>

    <?php if ($activeTab === 'fines'): ?>
        <div class="settings-section">
            <h2>Fine Settings</h2>
            <form method="POST" action="admin_dashboard.php?section=settings&settings_tab=fines">
                <input type="hidden" name="action" value="update_fines">
                <div class="settings-grid">
                    <div class="setting-item">
                        <label>Fine per Day (₱)</label>
                        <input type="number" name="fine_per_day" value="<?php echo $fineSettings['fine_per_day'] ?? 50; ?>" step="0.50" min="0">
                    </div>
                    <div class="setting-item">
                        <label>Lost Book Fee (₱)</label>
                        <input type="number" name="lost_book_fee" value="<?php echo $fineSettings['lost_book_fee'] ?? 500; ?>" step="50" min="0">
                    </div>
                    <div class="setting-item">
                        <label>Damaged Book Fee (₱)</label>
                        <input type="number" name="damaged_book_fee" value="<?php echo $fineSettings['damaged_book_fee'] ?? 200; ?>" step="50" min="0">
                    </div>
                    <div class="setting-item">
                        <label>Grace Period (days)</label>
                        <input type="number" name="grace_period" value="<?php echo $fineSettings['grace_period'] ?? 0; ?>" min="0">
                    </div>
                </div>
                <button type="submit" class="btn-save">💾 Save Fine Settings</button>
            </form>
        </div>
    <?php elseif ($activeTab === 'academic'): ?>
        <div class="settings-section">
            <h2>Academic Years</h2>
            <div class="academic-years-list">
                <?php if (!empty($academicYears)): ?>
                    <?php foreach ($academicYears as $year): ?>
                        <div class="academic-year-item">
                            <span><?php echo htmlspecialchars($year['year_name'] ?? ''); ?></span>
                            <span><?php echo htmlspecialchars($year['start_date'] ?? ''); ?> - <?php echo htmlspecialchars($year['end_date'] ?? ''); ?></span>
                            <span class="<?php echo ($year['is_current'] ?? false) ? 'current' : ''; ?>"><?php echo ($year['is_current'] ?? false) ? '✅ Current' : ''; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-data">No academic years set</p>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="settings-section">
            <h2>Audit Logs</h2>
            <div class="audit-table-container">
                <table class="data-table">
                    <thead><tr><th>User</th><th>Action</th><th>Details</th><th>Date</th></tr></thead>
                    <tbody>
                        <tr><td colspan="4" class="no-data-text">No audit logs available</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>