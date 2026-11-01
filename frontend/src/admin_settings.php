<?php
/**
 * Administrator settings panel. Included by admin_dashboard.php after the
 * administrator session guard and the shared supabaseRequest() helper.
 * Persistent settings are stored as JSON in public.system_settings.
 */
$settingsTabLabels = [
    'general' => 'General', 'users' => 'User & Role Management',
    'circulation' => 'Circulation Rules', 'notifications' => 'Notifications',
    'security' => 'Security', 'backup' => 'Backup & Data', 'system' => 'System'
];
$settingsTab = (string)($_GET['settings_tab'] ?? 'general');
if (!isset($settingsTabLabels[$settingsTab])) $settingsTab = 'general';
$settingsMessage = '';
$settingsError = '';
$settingsDefaults = [
    'general' => [
        'school_name' => 'St. Agnes Academy of Caloocan Inc.',
        'logo_path' => 'img/agustinnb.png',
        'address' => 'Blk. 24 Lot 8, Santan Road, Almar Subdivision, Camarin, Caloocan City, Philippines, 1422',
        'email' => 'agnesian2015@gmail.com', 'phone' => '289901206',
        'opening_time' => '08:00', 'closing_time' => '17:00',
        'academic_year' => date('Y') . '-' . (date('Y') + 1), 'semester' => '1st Semester', 'holiday_dates' => '',
        'date_format' => 'M d, Y', 'time_format' => '12'
    ],
    'users' => [
        'role_permissions' => ['admin'=>['view'=>1,'add'=>1,'edit'=>1,'delete'=>1], 'librarian'=>['view'=>1,'add'=>1,'edit'=>1,'delete'=>0], 'teacher'=>['view'=>1,'add'=>1,'edit'=>0,'delete'=>0], 'student'=>['view'=>1,'add'=>0,'edit'=>0,'delete'=>0]],
        'allow_admin_password_reset' => 0, 'force_password_change' => 1
    ],
    'circulation' => [
        'student_loan_days' => 7, 'teacher_loan_days' => 14, 'student_max_books' => 2,
        'teacher_class_set_request' => 1, 'renewal_limit' => 1, 'renewal_requires_clearance' => 1,
        'fine_per_day' => 50, 'maximum_fine' => 1000, 'grace_period' => 0,
        'lost_book_penalty' => 'Replacement cost of the book plus applicable processing fee.',
        'damaged_book_penalty' => 'Assessment by the librarian; repair or replacement cost may apply.',
        'block_unpaid' => 1
    ],
    'notifications' => [
        'email_enabled' => 1, 'in_app_enabled' => 1, 'reminder_days' => 2,
        'due_template' => 'Reminder: your borrowed library material is due soon.',
        'overdue_template' => 'Your borrowed library material is overdue. Please return it or contact the library.',
        'reservation_template' => 'Your reserved library material is ready for pickup.',
        'fine_template' => 'You have an outstanding library fine. Please settle it with the library.',
        'holiday_dates' => '', 'announcement' => ''
    ],
    'security' => [
        'minimum_password_length' => 8, 'require_uppercase' => 1, 'require_lowercase' => 1,
        'require_number' => 1, 'require_symbol' => 0, 'session_timeout_minutes' => 30,
        'max_login_attempts' => 5, 'audit_retention_days' => 365, 'two_factor_enabled' => 0,
        'privacy_notice' => 'Personal data is processed for library operations in accordance with Republic Act No. 10173 (Data Privacy Act of 2012).'
    ],
    'backup' => [
        'scheduled_backup' => 0, 'backup_frequency' => 'weekly', 'archive_after_days' => 365,
        'retention_rule' => 'Retain only records necessary for legitimate library and institutional purposes. Review retention schedules with the school Data Protection Officer.'
    ],
    'system' => [
        'maintenance_mode' => 0, 'primary_color' => '#b40f7d', 'secondary_color' => '#1a1020',
        'system_version' => '1.0.0'
    ]
];
$settingsData = $settingsDefaults;
$settingsTableAvailable = true;
try {
    $rows = supabaseRequest('system_settings?select=setting_key,setting_value');
    if (is_array($rows)) {
        foreach ($rows as $row) {
            $key = (string)($row['setting_key'] ?? '');
            $value = $row['setting_value'] ?? null;
            if (isset($settingsData[$key]) && is_array($value)) $settingsData[$key] = array_replace_recursive($settingsData[$key], $value);
        }
    }
} catch (Throwable $e) {
    $settingsTableAvailable = false;
    $settingsError = 'The system_settings table is not available yet. Run the included SQL migration before saving settings.';
    error_log('Admin settings load: ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settings_group'])) {
    $group = (string)$_POST['settings_group'];
    if (!isset($settingsDefaults[$group])) {
        $settingsError = 'Invalid settings section.';
    } elseif (!$settingsTableAvailable) {
        $settingsError = 'Settings cannot be saved until the SQL migration has been applied.';
    } else {
        $clean = [];
        foreach ($settingsDefaults[$group] as $key => $default) {
            if (is_array($default)) {
                $clean[$key] = $default;
                continue;
            }
            if (is_int($default)) $clean[$key] = isset($_POST[$key]) ? (int)$_POST[$key] : 0;
            else $clean[$key] = trim((string)($_POST[$key] ?? $default));
        }
        if ($group === 'users') {
            $clean['role_permissions'] = [];
            foreach (['admin','librarian','teacher','student'] as $role) {
                $clean['role_permissions'][$role] = [];
                foreach (['view','add','edit','delete'] as $permission) $clean['role_permissions'][$role][$permission] = isset($_POST['perm'][$role][$permission]) ? 1 : 0;
            }
        }
        if ($group === 'general') {
            $clean['school_name'] = $clean['school_name'] ?: $settingsDefaults['general']['school_name'];
            $clean['email'] = filter_var($clean['email'], FILTER_VALIDATE_EMAIL) ? $clean['email'] : $settingsDefaults['general']['email'];
        }
        if ($group === 'circulation') {
            foreach (['student_loan_days','teacher_loan_days','student_max_books','renewal_limit','fine_per_day','maximum_fine','grace_period'] as $n) $clean[$n] = max(0, (float)$clean[$n]);
            $clean['teacher_class_set_request'] = isset($_POST['teacher_class_set_request']) ? 1 : 0;
            $clean['renewal_requires_clearance'] = isset($_POST['renewal_requires_clearance']) ? 1 : 0;
            $clean['block_unpaid'] = isset($_POST['block_unpaid']) ? 1 : 0;
        }
        foreach (['users','notifications','security','backup','system'] as $boolGroup) {
            if ($group === $boolGroup) {
                $boolKeys = [
                    'users'=>['allow_admin_password_reset','force_password_change'],
                    'notifications'=>['email_enabled','in_app_enabled'],
                    'security'=>['require_uppercase','require_lowercase','require_number','require_symbol','two_factor_enabled'],
                    'backup'=>['scheduled_backup'],
                    'system'=>['maintenance_mode']
                ][$group];
                foreach ($boolKeys as $key) $clean[$key] = isset($_POST[$key]) ? 1 : 0;
            }
        }
        try {
            $existing = supabaseRequest('system_settings?select=setting_key&setting_key=eq.' . rawurlencode($group) . '&limit=1');
            if (!empty($existing)) supabaseRequest('system_settings?setting_key=eq.' . rawurlencode($group), 'PATCH', ['setting_value'=>$clean, 'updated_at'=>date('c'), 'updated_by'=>$_SESSION['user_id'] ?? null]);
            else supabaseRequest('system_settings', 'POST', ['setting_key'=>$group, 'setting_value'=>$clean, 'updated_at'=>date('c'), 'updated_by'=>$_SESSION['user_id'] ?? null]);
            $settingsData[$group] = $clean;
            $settingsMessage = 'Settings saved successfully.';
            // Keep existing fine calculation settings in sync with the active circulation policy.
            if ($group === 'circulation') {
                try {
                    $fineRows = supabaseRequest('fine_settings?select=id&limit=1');
                    $finePayload = ['fine_per_day'=>$clean['fine_per_day'], 'lost_book_fee'=>500, 'damaged_book_fee'=>200, 'grace_period'=>$clean['grace_period']];
                    if (!empty($fineRows[0]['id'])) supabaseRequest('fine_settings?id=eq.' . rawurlencode((string)$fineRows[0]['id']), 'PATCH', $finePayload);
                    else supabaseRequest('fine_settings', 'POST', $finePayload);
                } catch (Throwable $fineError) { error_log('Fine settings sync failed: ' . $fineError->getMessage()); }
            }
        } catch (Throwable $e) {
            $settingsError = 'Could not save settings: ' . $e->getMessage();
            error_log('Admin settings save: ' . $e->getMessage());
        }
    }
}
function settingEsc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$cfg = $settingsData[$settingsTab];
?>
<style>
.admin-settings{padding:4px 0 28px;color:#251a27}.admin-settings h1{font-size:26px;margin:0 0 6px}.admin-settings .intro{color:#786b79;margin:0 0 20px}.settings-tabs{display:flex;gap:7px;flex-wrap:wrap;margin:18px 0;border-bottom:1px solid #eadce8;padding-bottom:10px}.settings-tabs a{padding:9px 13px;border-radius:9px;text-decoration:none;color:#665766;background:#f8f2f7;font-size:13px}.settings-tabs a.active{background:#b40f7d;color:#fff}.settings-card{background:#fff;border:1px solid #efdfeb;border-radius:15px;padding:22px;margin-bottom:16px;box-shadow:0 3px 12px rgba(50,20,45,.035)}.settings-card h2{font-size:17px;margin:0 0 16px}.settings-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.settings-field{display:flex;flex-direction:column;gap:6px}.settings-field.full{grid-column:1/-1}.settings-field label{font-size:12px;font-weight:700;color:#655666}.settings-field input,.settings-field select,.settings-field textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e7d7e4;border-radius:9px;background:#fff;color:#281d2a;font:inherit;font-size:13px}.settings-field textarea{min-height:85px;resize:vertical}.settings-check{display:flex;align-items:center;gap:9px;padding:9px 0;font-size:13px;color:#4e4050}.settings-check input{accent-color:#b40f7d}.settings-save{border:0;border-radius:9px;padding:11px 18px;background:#b40f7d;color:#fff;font-weight:700;cursor:pointer;margin-top:16px}.settings-note{padding:12px 14px;border-radius:10px;background:#f8f2f7;color:#675768;font-size:12px;line-height:1.6;margin:12px 0}.settings-msg{padding:12px 14px;border-radius:10px;margin:12px 0 16px;font-size:13px}.settings-msg.ok{background:#eaf7ef;color:#17643b}.settings-msg.err{background:#fff0f0;color:#a32929}.perm-table{width:100%;border-collapse:collapse;font-size:13px}.perm-table th,.perm-table td{padding:10px;border-bottom:1px solid #eee;text-align:center}.perm-table th:first-child,.perm-table td:first-child{text-align:left}.settings-link{color:#a20b70;font-weight:700;text-decoration:none}.settings-warning{background:#fff8e7;border:1px solid #f3dfaa;color:#765514;padding:13px;border-radius:10px;font-size:12px;line-height:1.6}@media(max-width:700px){.settings-grid{grid-template-columns:1fr}.settings-field.full{grid-column:auto}.settings-card{padding:16px}.perm-table{font-size:11px}.perm-table th,.perm-table td{padding:7px 4px}}
</style>
<div class="admin-settings">
    <h1>Administrator Settings</h1><p class="intro">Configure school information, library policies, notifications, security and system behavior.</p>
    <?php if ($settingsMessage): ?><div class="settings-msg ok"><?php echo settingEsc($settingsMessage); ?></div><?php endif; ?>
    <?php if ($settingsError): ?><div class="settings-msg err"><?php echo settingEsc($settingsError); ?></div><?php endif; ?>
    <div class="settings-tabs"><?php foreach ($settingsTabLabels as $key=>$label): ?><a class="<?php echo $settingsTab===$key?'active':''; ?>" href="admin_dashboard.php?section=settings&amp;settings_tab=<?php echo settingEsc($key); ?>"><?php echo settingEsc($label); ?></a><?php endforeach; ?></div>

    <?php if ($settingsTab === 'general'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=general"><input type="hidden" name="settings_group" value="general">
      <div class="settings-card"><h2>School Profile & Contact</h2><div class="settings-grid">
        <div class="settings-field full"><label>School name</label><input name="school_name" value="<?php echo settingEsc($cfg['school_name']); ?>"></div>
        <div class="settings-field"><label>Logo path (relative to frontend/src)</label><input name="logo_path" value="<?php echo settingEsc($cfg['logo_path']); ?>"></div>
        <div class="settings-field"><label>Contact email</label><input type="email" name="email" value="<?php echo settingEsc($cfg['email']); ?>"></div>
        <div class="settings-field full"><label>School address</label><textarea name="address"><?php echo settingEsc($cfg['address']); ?></textarea></div>
        <div class="settings-field"><label>Contact number</label><input name="phone" value="<?php echo settingEsc($cfg['phone']); ?>"></div>
        <div class="settings-field"><label>Opening time</label><input type="time" name="opening_time" value="<?php echo settingEsc($cfg['opening_time']); ?>"></div>
        <div class="settings-field"><label>Closing time</label><input type="time" name="closing_time" value="<?php echo settingEsc($cfg['closing_time']); ?>"></div>
        <div class="settings-field"><label>Academic year</label><input name="academic_year" value="<?php echo settingEsc($cfg['academic_year']); ?>" placeholder="2026-2027"></div>
        <div class="settings-field"><label>Semester</label><select name="semester"><?php foreach(['1st Semester','2nd Semester','Summer'] as $v): ?><option <?php echo $cfg['semester']===$v?'selected':''; ?>><?php echo $v; ?></option><?php endforeach; ?></select></div>
        <div class="settings-field"><label>Date format</label><select name="date_format"><?php foreach(['M d, Y'=>'Nov 01, 2026','Y-m-d'=>'2026-11-01','d/m/Y'=>'01/11/2026','F j, Y'=>'November 1, 2026'] as $v=>$label): ?><option value="<?php echo $v; ?>" <?php echo $cfg['date_format']===$v?'selected':''; ?>><?php echo $label; ?></option><?php endforeach; ?></select></div>
        <div class="settings-field"><label>Time format</label><select name="time_format"><option value="12" <?php echo $cfg['time_format']==='12'?'selected':''; ?>>12-hour</option><option value="24" <?php echo $cfg['time_format']==='24'?'selected':''; ?>>24-hour</option></select></div>
        <div class="settings-field full"><label>Holiday / no-class dates (one date or event per line)</label><textarea name="holiday_dates" placeholder="2026-12-08 — School holiday"><?php echo settingEsc($cfg['holiday_dates']); ?></textarea></div>
      </div></div>
      <div class="settings-card"><h2>Holidays, No-Class Days & Announcements</h2><p class="settings-note">Holiday calendar and pinned announcements are managed in the Notifications tab. This profile section stores school hours and academic-year defaults.</p></div><button class="settings-save">Save General Settings</button>
    </form>

    <?php elseif ($settingsTab === 'users'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=users"><input type="hidden" name="settings_group" value="users">
      <div class="settings-card"><h2>Role Permissions Matrix</h2><p class="settings-note">This matrix records the intended permissions for each role. It does not override the authorization checks in individual PHP pages; those must be enforced in code before relying on it as a security boundary.</p>
      <div style="overflow-x:auto"><table class="perm-table"><thead><tr><th>Role</th><th>View</th><th>Add</th><th>Edit</th><th>Delete</th></tr></thead><tbody><?php foreach(['admin'=>'Administrator','librarian'=>'Librarian','teacher'=>'Teacher','student'=>'Student'] as $role=>$label): ?><tr><td><strong><?php echo $label; ?></strong></td><?php foreach(['view','add','edit','delete'] as $perm): ?><td><input type="checkbox" name="perm[<?php echo $role; ?>][<?php echo $perm; ?>]" <?php echo !empty($cfg['role_permissions'][$role][$perm])?'checked':''; ?>></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div></div>
      <div class="settings-card"><h2>Account Recovery</h2><label class="settings-check"><input type="checkbox" name="allow_admin_password_reset" <?php echo !empty($cfg['allow_admin_password_reset'])?'checked':''; ?>> Allow administrators to initiate password recovery</label><label class="settings-check"><input type="checkbox" name="force_password_change" <?php echo !empty($cfg['force_password_change'])?'checked':''; ?>> Require a password change after an administrator-issued reset</label><div class="settings-warning">Password resets must be performed through a trusted server-side Supabase Auth Admin API using a secret key stored in HostForge environment variables. Never place a service-role key in browser JavaScript or commit it to GitHub. These toggles are policy settings only; secure reset execution is not added by this settings page.</div><p><a class="settings-link" href="admin_dashboard.php?section=users">Open User Management →</a></p></div><button class="settings-save">Save User & Role Settings</button>
    </form>

    <?php elseif ($settingsTab === 'circulation'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=circulation"><input type="hidden" name="settings_group" value="circulation">
      <div class="settings-card"><h2>Loan Periods & Borrowing Limits</h2><div class="settings-grid">
        <?php foreach(['student_loan_days'=>'Student loan period (days)','teacher_loan_days'=>'Teacher loan period (days)','student_max_books'=>'Maximum books per student','renewal_limit'=>'Renewal limit'] as $key=>$label): ?><div class="settings-field"><label><?php echo $label; ?></label><input type="number" min="0" name="<?php echo $key; ?>" value="<?php echo settingEsc($cfg[$key]); ?>"></div><?php endforeach; ?>
      </div><label class="settings-check"><input type="checkbox" name="teacher_class_set_request" <?php echo !empty($cfg['teacher_class_set_request'])?'checked':''; ?>> Teachers may submit formal class-set borrowing requests</label><label class="settings-check"><input type="checkbox" name="renewal_requires_clearance" <?php echo !empty($cfg['renewal_requires_clearance'])?'checked':''; ?>> Require clearance form or librarian log before renewal</label></div>
      <div class="settings-card"><h2>Fines & Penalties</h2><div class="settings-grid"><?php foreach(['fine_per_day'=>'Overdue fine per day (₱)','maximum_fine'=>'Maximum fine (₱)','grace_period'=>'Grace period (days)'] as $key=>$label): ?><div class="settings-field"><label><?php echo $label; ?></label><input type="number" min="0" step="0.01" name="<?php echo $key; ?>" value="<?php echo settingEsc($cfg[$key]); ?>"></div><?php endforeach; ?><div class="settings-field full"><label>Lost book penalty notes</label><textarea name="lost_book_penalty"><?php echo settingEsc($cfg['lost_book_penalty']); ?></textarea></div><div class="settings-field full"><label>Damaged book penalty notes</label><textarea name="damaged_book_penalty"><?php echo settingEsc($cfg['damaged_book_penalty']); ?></textarea></div></div><label class="settings-check"><input type="checkbox" name="block_unpaid" <?php echo !empty($cfg['block_unpaid'])?'checked':''; ?>> Block borrowing when fines or overdue items are unpaid</label></div><button class="settings-save">Save Circulation Rules</button>
    </form>

    <?php elseif ($settingsTab === 'notifications'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=notifications"><input type="hidden" name="settings_group" value="notifications"><div class="settings-card"><h2>Delivery & Reminder Schedule</h2><label class="settings-check"><input type="checkbox" name="email_enabled" <?php echo !empty($cfg['email_enabled'])?'checked':''; ?>> Enable email notifications</label><label class="settings-check"><input type="checkbox" name="in_app_enabled" <?php echo !empty($cfg['in_app_enabled'])?'checked':''; ?>> Enable in-app notifications</label><div class="settings-field" style="max-width:260px"><label>Due-date reminder (days before due date)</label><input type="number" min="0" name="reminder_days" value="<?php echo settingEsc($cfg['reminder_days']); ?>"></div></div><div class="settings-card"><h2>Notification Templates</h2><div class="settings-grid"><?php foreach(['due_template'=>'Due-date reminder','overdue_template'=>'Overdue notice','reservation_template'=>'Reservation-ready alert','fine_template'=>'Fine notice'] as $key=>$label): ?><div class="settings-field full"><label><?php echo $label; ?></label><textarea name="<?php echo $key; ?>"><?php echo settingEsc($cfg[$key]); ?></textarea></div><?php endforeach; ?><div class="settings-field full"><label>Pinned announcement / no-class notice</label><textarea name="announcement" placeholder="Announcement shown to library users..."><?php echo settingEsc($cfg['announcement']); ?></textarea></div></div></div><button class="settings-save">Save Notification Settings</button></form>

    <?php elseif ($settingsTab === 'security'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=security"><input type="hidden" name="settings_group" value="security"><div class="settings-card"><h2>Password & Login Policy</h2><div class="settings-grid"><div class="settings-field"><label>Minimum password length</label><input type="number" min="8" max="128" name="minimum_password_length" value="<?php echo settingEsc($cfg['minimum_password_length']); ?>"></div><div class="settings-field"><label>Session timeout (minutes)</label><input type="number" min="1" name="session_timeout_minutes" value="<?php echo settingEsc($cfg['session_timeout_minutes']); ?>"></div><div class="settings-field"><label>Maximum login attempts</label><input type="number" min="1" name="max_login_attempts" value="<?php echo settingEsc($cfg['max_login_attempts']); ?>"></div><div class="settings-field"><label>Audit log retention (days)</label><input type="number" min="1" name="audit_retention_days" value="<?php echo settingEsc($cfg['audit_retention_days']); ?>"></div></div><?php foreach(['require_uppercase'=>'Require uppercase letter','require_lowercase'=>'Require lowercase letter','require_number'=>'Require a number','require_symbol'=>'Require a symbol','two_factor_enabled'=>'Enable optional two-factor authentication'] as $key=>$label): ?><label class="settings-check"><input type="checkbox" name="<?php echo $key; ?>" <?php echo !empty($cfg[$key])?'checked':''; ?>> <?php echo $label; ?></label><?php endforeach; ?></div><div class="settings-card"><h2>Data Privacy</h2><div class="settings-field"><label>Privacy and retention notice</label><textarea name="privacy_notice"><?php echo settingEsc($cfg['privacy_notice']); ?></textarea></div><p class="settings-note">Align actual retention, access, breach response and data-subject request procedures with your school's Data Protection Officer and Republic Act No. 10173. Saving this notice does not by itself establish legal compliance.</p></div><button class="settings-save">Save Security Settings</button></form>

    <?php elseif ($settingsTab === 'backup'): ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=backup"><input type="hidden" name="settings_group" value="backup"><div class="settings-card"><h2>Backup Schedule & Retention</h2><label class="settings-check"><input type="checkbox" name="scheduled_backup" <?php echo !empty($cfg['scheduled_backup'])?'checked':''; ?>> Enable scheduled backup policy</label><div class="settings-grid"><div class="settings-field"><label>Backup frequency</label><select name="backup_frequency"><option value="daily" <?php echo $cfg['backup_frequency']==='daily'?'selected':''; ?>>Daily</option><option value="weekly" <?php echo $cfg['backup_frequency']==='weekly'?'selected':''; ?>>Weekly</option><option value="monthly" <?php echo $cfg['backup_frequency']==='monthly'?'selected':''; ?>>Monthly</option></select></div><div class="settings-field"><label>Archive records older than (days)</label><input type="number" min="1" name="archive_after_days" value="<?php echo settingEsc($cfg['archive_after_days']); ?>"></div><div class="settings-field full"><label>Retention rule</label><textarea name="retention_rule"><?php echo settingEsc($cfg['retention_rule']); ?></textarea></div></div></div><div class="settings-card"><h2>Export & Restore</h2><p class="settings-note">Use these existing administrator exports for supported datasets. For a full backup and restore, use Supabase Dashboard → Database → Backups or export the database with pg_dump. Do not import unvalidated SQL from unknown sources.</p><p><a class="settings-link" href="admin_dashboard.php?section=books&amp;action=export&amp;format=csv">Export book catalog (CSV) →</a></p><p><a class="settings-link" href="admin_dashboard.php?section=reports&amp;action=export&amp;format=csv">Export reports / transactions (CSV) →</a></p><div class="settings-warning">Scheduled backup execution, full database restore, bulk user import and purge/archive jobs require a protected server-side worker and storage destination. These controls store the desired policy only; they do not create a backup job by themselves.</div></div><button class="settings-save">Save Backup & Data Settings</button></form>

    <?php else: ?>
    <form method="post" action="admin_dashboard.php?section=settings&amp;settings_tab=system"><input type="hidden" name="settings_group" value="system"><div class="settings-card"><h2>System Operation & Branding</h2><label class="settings-check"><input type="checkbox" name="maintenance_mode" <?php echo !empty($cfg['maintenance_mode'])?'checked':''; ?>> Enable maintenance mode (policy flag only)</label><div class="settings-grid"><div class="settings-field"><label>Primary branding color</label><input type="color" name="primary_color" value="<?php echo settingEsc($cfg['primary_color']); ?>"></div><div class="settings-field"><label>Secondary branding color</label><input type="color" name="secondary_color" value="<?php echo settingEsc($cfg['secondary_color']); ?>"></div><div class="settings-field"><label>System version</label><input name="system_version" value="<?php echo settingEsc($cfg['system_version']); ?>"></div></div></div><div class="settings-card"><h2>System Health</h2><p>PHP version: <strong><?php echo settingEsc(PHP_VERSION); ?></strong></p><p>Server time: <strong><?php echo date('M d, Y g:i:s A'); ?> (Asia/Manila)</strong></p><p>Settings storage: <strong><?php echo $settingsTableAvailable?'Connected':'Migration required'; ?></strong></p><p><a class="settings-link" href="../../health.php" target="_blank" rel="noopener">Open application health check →</a></p></div><button class="settings-save">Save System Settings</button></form>
    <?php endif; ?>
</div>
