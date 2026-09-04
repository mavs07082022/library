<?php
// admin_users.php
// This file is included by admin_dashboard.php

$users = [];
$userMessage = '';
$searchUser = isset($_GET['search']) ? $_GET['search'] : '';

try {
    $query = 'users?select=*';
    if (!empty($searchUser)) {
        $query .= '&or=(username.ilike.%' . urlencode($searchUser) . '%,full_name.ilike.%' . urlencode($searchUser) . '%,email.ilike.%' . urlencode($searchUser) . '%,user_id.ilike.%' . urlencode($searchUser) . '%)';
    }
    $users = supabaseRequest($query);
} catch (Exception $e) {
    $userMessage = '❌ Failed to load users';
}

// Handle Add Librarian
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_librarian') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    
    if (!empty($username) && !empty($email) && !empty($password) && !empty($full_name)) {
        try {
            $newUser = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'full_name' => $full_name,
                'role' => 'librarian',
                'user_id' => 'LIB' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
                'is_verified' => true,
                'is_active' => true
            ];
            supabaseRequest('users', 'POST', $newUser);
            $userMessage = '✅ Librarian created successfully!';
            echo '<meta http-equiv="refresh" content="1">';
        } catch (Exception $e) {
            $userMessage = '❌ Failed to create librarian';
        }
    }
}

// Handle Delete User
if (isset($_GET['delete_user'])) {
    $userId = $_GET['delete_user'];
    try {
        supabaseRequest('users?id=eq.' . $userId, 'DELETE');
        $userMessage = '✅ User deleted successfully';
        echo '<meta http-equiv="refresh" content="1">';
    } catch (Exception $e) {
        $userMessage = '❌ Failed to delete user';
    }
}

// Handle Toggle User Status
if (isset($_GET['toggle_user'])) {
    $userId = $_GET['toggle_user'];
    $currentStatus = $_GET['status'] === 'true';
    try {
        supabaseRequest('users?id=eq.' . $userId, 'PATCH', ['is_active' => !$currentStatus]);
        $userMessage = '✅ User status updated';
        echo '<meta http-equiv="refresh" content="1">';
    } catch (Exception $e) {
        $userMessage = '❌ Failed to update user';
    }
}

$filteredUsers = array_filter($users, function($u) use ($searchUser) {
    if (empty($searchUser)) return true;
    $search = strtolower($searchUser);
    return strpos(strtolower($u['username'] ?? ''), $search) !== false ||
           strpos(strtolower($u['full_name'] ?? ''), $search) !== false ||
           strpos(strtolower($u['email'] ?? ''), $search) !== false ||
           strpos(strtolower($u['user_id'] ?? ''), $search) !== false;
});
?>
<style>
    .user-management { padding: 20px; }
    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .section-header h1 { font-size: 24px; color: #1a2e3f; margin: 0; }
    .header-actions { display: flex; align-items: center; gap: 15px; flex-wrap: wrap; }
    .role-count { color: #666; font-size: 14px; }
    .btn-add {
        padding: 10px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
    }
    .btn-add:hover { background: #5a6fd6; }
    .search-bar {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .search-bar form {
        flex: 1;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .search-bar input {
        flex: 1;
        padding: 12px 16px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        font-size: 14px;
        min-width: 200px;
    }
    .search-bar input:focus { border-color: #667eea; outline: none; }
    .btn-search {
        padding: 12px 20px;
        background: #667eea;
        color: white;
        border: none;
        border-radius: 10px;
        cursor: pointer;
    }
    .btn-search:hover { background: #5a6fd6; }
    .btn-clear {
        padding: 12px 20px;
        background: #f0f0f0;
        color: #333;
        border: none;
        border-radius: 10px;
        text-decoration: none;
    }
    .btn-clear:hover { background: #e0e0e0; }
    .search-count { color: #666; font-size: 14px; white-space: nowrap; }
    .message { padding: 12px; border-radius: 8px; margin-bottom: 15px; }
    .message.success { background: #dff0e6; color: #14653b; }
    .message.error { background: #fce4ec; color: #d32f2f; }
    .table-container {
        background: white;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        overflow-x: auto;
    }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th {
        background: #f8f9fa;
        padding: 12px 16px;
        text-align: left;
        font-weight: 600;
        color: #555;
        font-size: 13px;
        text-transform: uppercase;
    }
    .data-table td { padding: 12px 16px; border-top: 1px solid #f0f0f0; }
    .role-badge { padding: 2px 10px; border-radius: 12px; font-size: 12px; display: inline-block; }
    .role-admin { background: #fce4ec; color: #d32f2f; }
    .role-librarian { background: #fff3e0; color: #f57c00; }
    .role-student { background: #e3f2fd; color: #1976d2; }
    .status-badge { padding: 2px 10px; border-radius: 12px; font-size: 12px; display: inline-block; }
    .status-badge.active { background: #dff0e6; color: #14653b; }
    .status-badge.inactive { background: #fce4ec; color: #d32f2f; }
    .btn-toggle { padding: 4px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 4px; text-decoration: none; display: inline-block; }
    .btn-toggle.activate { background: #dff0e6; color: #14653b; }
    .btn-toggle.deactivate { background: #fce4ec; color: #d32f2f; }
    .btn-delete { padding: 4px 12px; background: #fce4ec; color: #d32f2f; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; }
    .no-data { text-align: center; padding: 30px !important; color: #999; }
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        padding: 20px;
    }
    .modal {
        background: white;
        padding: 30px;
        border-radius: 16px;
        max-width: 450px;
        width: 90%;
    }
    .modal h3 { margin-top: 0; color: #1a2e3f; }
    .form-group { margin-bottom: 15px; }
    .form-group label { display: block; font-weight: 600; font-size: 14px; color: #1a2e3f; margin-bottom: 5px; }
    .form-group input {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        box-sizing: border-box;
    }
    .form-group input:focus { border-color: #667eea; outline: none; }
    .modal-actions { display: flex; gap: 10px; margin-top: 20px; justify-content: flex-end; }
    .btn-cancel { padding: 8px 20px; background: #f0f0f0; border: none; border-radius: 8px; cursor: pointer; }
    .btn-cancel:hover { background: #e0e0e0; }
    .btn-confirm { padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer; }
    .btn-confirm:hover { background: #5a6fd6; }
    @media (max-width: 768px) {
        .section-header { flex-direction: column; align-items: flex-start; gap: 10px; }
        .search-bar { flex-direction: column; align-items: stretch; }
        .data-table th, .data-table td { padding: 8px 10px; font-size: 12px; }
        .modal { max-width: 95%; padding: 20px; }
    }
</style>
<div class="user-management">
    <div class="section-header">
        <h1>👥 User Management</h1>
        <div class="header-actions">
            <span class="role-count">Total: <?php echo count($users); ?> users</span>
            <button onclick="document.getElementById('addLibrarianModal').style.display='flex'" class="btn-add">+ Add Librarian</button>
        </div>
    </div>

    <div class="search-bar">
        <form method="GET" action="admin_dashboard.php">
            <input type="hidden" name="section" value="users">
            <input type="text" name="search" placeholder="Search users by name, username, email, or ID..." value="<?php echo htmlspecialchars($searchUser); ?>">
            <button type="submit" class="btn-search">🔍 Search</button>
            <?php if (!empty($searchUser)): ?>
                <a href="admin_dashboard.php?section=users" class="btn-clear">✕ Clear</a>
            <?php endif; ?>
        </form>
        <span class="search-count"><?php echo count($filteredUsers); ?> users found</span>
    </div>

    <?php if ($userMessage): ?>
        <div class="message <?php echo strpos($userMessage, '❌') !== false ? 'error' : 'success'; ?>"><?php echo $userMessage; ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th>
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
                            <td>
                                <span class="role-badge role-<?php echo $u['role'] ?? 'student'; ?>">
                                    <?php echo $u['role'] ?? 'student'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo ($u['is_active'] ?? true) ? 'active' : 'inactive'; ?>">
                                    <?php echo ($u['is_active'] ?? true) ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="admin_dashboard.php?section=users&toggle_user=<?php echo $u['id']; ?>&status=<?php echo $u['is_active'] ?? true; ?>" 
                                   class="btn-toggle <?php echo ($u['is_active'] ?? true) ? 'deactivate' : 'activate'; ?>"
                                   onclick="return confirm('Toggle user status?')">
                                    <?php echo ($u['is_active'] ?? true) ? '🔴 Deactivate' : '🟢 Activate'; ?>
                                </a>
                                <?php if (($u['role'] ?? '') !== 'admin'): ?>
                                    <a href="admin_dashboard.php?section=users&delete_user=<?php echo $u['id']; ?>" 
                                       class="btn-delete"
                                       onclick="return confirm('Delete this user?')">
                                        🗑️ Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-data"><?php echo !empty($searchUser) ? 'No users found matching your search' : 'No users found'; ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Add Librarian Modal -->
    <div id="addLibrarianModal" class="modal-overlay" style="display:none;">
        <div class="modal">
            <h3>Add New Librarian</h3>
            <form method="POST" action="admin_dashboard.php?section=users">
                <input type="hidden" name="action" value="add_librarian">
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
                <div class="modal-actions">
                    <button type="button" onclick="document.getElementById('addLibrarianModal').style.display='none'" class="btn-cancel">Cancel</button>
                    <button type="submit" class="btn-confirm">Create Librarian</button>
                </div>
            </form>
        </div>
    </div>
</div>