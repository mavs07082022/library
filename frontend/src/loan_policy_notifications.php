<?php
/** Shared borrowing policy, notification and fine helpers. Requires supabaseRequest(). */
if (!function_exists('libraryLoanPolicy')) {
function libraryLoanPolicy($role) {
    $defaults = ['student'=>3, 'teacher'=>14, 'librarian'=>14, 'admin'=>14];
    $role = strtolower((string)$role);
    try {
        $rows = supabaseRequest('borrowing_policies?select=role,borrow_days,grace_period&role=eq.' . rawurlencode($role) . '&limit=1');
        if (!empty($rows)) return ['days'=>max(1,(int)$rows[0]['borrow_days']), 'grace'=>max(0,(int)($rows[0]['grace_period'] ?? 0))];
    } catch (Throwable $e) { error_log('Borrowing policy lookup: '.$e->getMessage()); }
    return ['days'=>$defaults[$role] ?? 3, 'grace'=>0];
}
function libraryNotify($userId, $title, $message, $type='borrowing', $icon='📚', $url='') {
    if (!$userId) return;
    try {
        $q = 'notifications?select=id&user_id=eq.'.rawurlencode($userId).'&title=eq.'.rawurlencode($title).'&message=eq.'.rawurlencode($message).'&limit=1';
        if (!empty(supabaseRequest($q))) return;
        supabaseRequest('notifications','POST',[
            'user_id'=>$userId,'title'=>$title,'message'=>$message,'type'=>$type,'icon'=>$icon,
            'is_read'=>false,'action_url'=>$url ?: 'student_dashboard.php?section=borrowings','action_label'=>'View Borrowings'
        ]);
    } catch (Throwable $e) { error_log('Loan notification failed: '.$e->getMessage()); }
}
function libraryDashboardForUser($userId) {
    try {
        $u = supabaseRequest('users?select=role&id=eq.' . rawurlencode((string)$userId) . '&limit=1');
        if (!empty($u) && strtolower($u[0]['role'] ?? '') === 'teacher') return 'teacher_dashboard.php';
    } catch (Throwable $e) { error_log('User role lookup for notification failed: '.$e->getMessage()); }
    return 'student_dashboard.php';
}
function libraryCreateBorrowNotice($userId, $bookTitle, $borrowDays, $dueDate, $dashboard='student_dashboard.php') {
    $msg = 'You may keep "'.$bookTitle.'" for '.$borrowDays.' day(s). Please return it on or before '.date('F d, Y',strtotime($dueDate)).' to avoid late fees.';
    libraryNotify($userId,'Book borrowed — due date reminder',$msg,'borrowing','📅',$dashboard.'?section=borrowings');
}
function libraryGenerateDueNotices($userId, $dashboard='student_dashboard.php') {
    if (!$userId) return;
    try {
        $loans = supabaseRequest('borrowings?select=id,due_date,status,books(title)&user_id=eq.'.rawurlencode($userId).'&status=neq.Returned');
        $today = new DateTimeImmutable(date('Y-m-d'));
        foreach (($loans ?: []) as $loan) {
            if (in_array(($loan['status'] ?? ''), ['Returned','Lost','Damaged'], true)) continue;
            if (empty($loan['due_date'])) continue;
            $due = new DateTimeImmutable(substr($loan['due_date'],0,10));
            $diff = (int)$today->diff($due)->format('%r%a');
            $title = $loan['books']['title'] ?? 'your borrowed book';
            if ($diff === 2 || $diff === 1) {
                libraryNotify($userId,'Book due soon','"'.$title.'" is due in '.$diff.' day(s), on '.$due->format('F d, Y').'. Please return it on time.','reminder','⏰',$dashboard.'?section=borrowings');
            } elseif ($diff === 0) {
                libraryNotify($userId,'Book due today','"'.$title.'" is due today. Please return it before the library closes.','reminder','📅',$dashboard.'?section=borrowings');
            } elseif ($diff < 0) {
                libraryNotify($userId,'Book overdue','"'.$title.'" was due on '.$due->format('F d, Y').'. Please return it as soon as possible. Late fees may apply.','overdue','⚠️',$dashboard.'?section=borrowings');
                try { supabaseRequest('borrowings?id=eq.'.rawurlencode($loan['id']).'&status=eq.Borrowed','PATCH',['status'=>'Overdue']); } catch (Throwable $e) {}
            }
        }
    } catch (Throwable $e) { error_log('Due notice generation failed: '.$e->getMessage()); }
}
function libraryFineSettings() {
    $defaults=['fine_per_day'=>50.00,'lost_book_fee'=>500.00,'damaged_book_fee'=>200.00,'grace_period'=>0];
    try { $rows=supabaseRequest('fine_settings?select=*&limit=1'); if (!empty($rows)) return array_merge($defaults,$rows[0]); }
    catch (Throwable $e) { error_log('Fine settings lookup: '.$e->getMessage()); }
    return $defaults;
}
function libraryProcessBookReturn($borrowing, $issue='none', $actorDashboard='librarian_dashboard.php') {
    $loanId=$borrowing['id'] ?? '';
    $bookId=$borrowing['book_id'] ?? '';
    $userId=$borrowing['user_id'] ?? '';
    $due=$borrowing['due_date'] ?? date('Y-m-d');
    $issue=strtolower($issue);
    if (!in_array($issue,['none','lost','damaged'],true)) $issue='none';
    $settings=libraryFineSettings();
    $today=new DateTimeImmutable(date('Y-m-d'));
    $dueDate=new DateTimeImmutable(substr($due,0,10));
    $lateDays=max(0,(int)$dueDate->diff($today)->format('%r%a')-(int)($settings['grace_period'] ?? 0));
    $amount=0.0; $reason=''; $newStatus='Returned';
    if ($issue==='lost') { $amount=(float)$settings['lost_book_fee']; $reason='Lost Book'; $newStatus='Lost'; }
    elseif ($issue==='damaged') { $amount=(float)$settings['damaged_book_fee']; $reason='Damaged Book'; $newStatus='Damaged'; }
    elseif ($lateDays>0) { $amount=$lateDays*(float)$settings['fine_per_day']; $reason='Late Return ('.$lateDays.' day(s))'; }
    // Update loan first; do not restore inventory for lost/damaged copies.
    supabaseRequest('borrowings?id=eq.'.rawurlencode($loanId),'PATCH',[
        'status'=>$newStatus,'return_date'=>date('Y-m-d'),'fine_amount'=>$amount,'is_returned'=>($newStatus==='Returned')
    ]);
    if ($newStatus==='Returned' && $bookId) {
        $book=supabaseRequest('books?select=available&id=eq.'.rawurlencode($bookId));
        if (!empty($book)) supabaseRequest('books?id=eq.'.rawurlencode($bookId),'PATCH',['available'=>max(0,(int)$book[0]['available']+1)]);
    }
    if ($issue!=='none') {
        try { supabaseRequest('reports','POST',[
            'book_id'=>$bookId,'user_id'=>$userId,'borrowing_id'=>$loanId,'report_type'=>ucfirst($issue),
            'description'=>ucfirst($issue).' book recorded during return processing.','status'=>'Pending','fine_amount'=>$amount,'reported_date'=>date('Y-m-d')
        ]); } catch (Throwable $e) { error_log('Issue report insert failed: '.$e->getMessage()); }
    }
    if ($amount>0 && $userId) {
        $existing=supabaseRequest('fines?select=id&borrowing_id=eq.'.rawurlencode($loanId).'&status=neq.Waived&limit=1');
        if (empty($existing)) supabaseRequest('fines','POST',[
            'borrowing_id'=>$loanId,'user_id'=>$userId,'amount'=>$amount,'reason'=>$reason,'status'=>'Pending','due_date'=>date('Y-m-d')
        ]);
        $userDashboard = libraryDashboardForUser($userId);
        libraryNotify($userId,'Library fine recorded','A '.$reason.' charge of ₱'.number_format($amount,2).' has been recorded. Please contact the librarian if you have questions.','fine','💰',$userDashboard.'?section=fines');
    } else {
        $userDashboard = libraryDashboardForUser($userId);
        libraryNotify($userId,'Book return recorded','Your book return has been recorded successfully.','borrowing','✅',$userDashboard.'?section=borrowings');
    }
    return ['amount'=>$amount,'reason'=>$reason,'status'=>$newStatus,'late_days'=>$lateDays];
}
}
