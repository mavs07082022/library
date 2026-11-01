<?php
// Standalone Teacher Library Workspace - St. Agnes Academy
session_start();
date_default_timezone_set('Asia/Manila');
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!isset($_SESSION['user_id']) || !in_array($role, ['teacher','admin'], true)) {
    header('Location: ../login.php'); exit;
}
require_once dirname(__DIR__, 3) . '/api/config.php';
header('Content-Type: text/html; charset=UTF-8');

function tw_h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function tw_req($endpoint,$method='GET',$data=null){
    $url = SUPABASE_URL.'/rest/v1/'.$endpoint;
    $headers = ['apikey: '.SUPABASE_ANON_KEY,'Authorization: Bearer '.SUPABASE_ANON_KEY,'Content-Type: application/json','Prefer: return=representation'];
    $ch=curl_init($url);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_TIMEOUT=>25]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    elseif(in_array($method,['PATCH','DELETE'],true)){curl_setopt($ch,CURLOPT_CUSTOMREQUEST,$method);if($data!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    $raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false) throw new Exception('Connection error: '.$err);
    if($code>=400) throw new Exception('Database request failed ('.$code.'): '.$raw);
    $decoded=json_decode($raw,true);return is_array($decoded)?$decoded:[];
}
function tw_go($tab,$message,$error=false){header('Location: library_workspace.php?tab='.rawurlencode($tab).'&'.($error?'error':'msg').'='.rawurlencode($message));exit;}
function tw_post($k){return trim((string)($_POST[$k]??''));}
$uid=(string)$_SESSION['user_id'];$name=(string)($_SESSION['full_name']??'Teacher');
$isAdmin=$role==='admin';
if(empty($_SESSION['tw_csrf']))$_SESSION['tw_csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['tw_csrf'];
$tabs=['overview'=>'Overview','subjects'=>'Subject Resources','class_sets'=>'Class Sets','reservations'=>'Lesson Reservations','assignments'=>'Reading Assignments','activities'=>'Library Activities','purchases'=>'Purchase Requests','eresources'=>'E-Resources','reports'=>'Reports'];
$tab=(string)($_GET['tab']??'overview');if(!isset($tabs[$tab]))$tab='overview';
$ownerFilter=$isAdmin?tw_h($_GET['teacher']??''):$uid;
$err='';$msg=(string)($_GET['msg']??'');$error=(string)($_GET['error']??'');
try{
 if($_SERVER['REQUEST_METHOD']==='POST'){
  if(!hash_equals($csrf,(string)($_POST['csrf_token']??'')))throw new Exception('Session token expired. Refresh and try again.');
  $action=tw_post('action');
  if($action==='save_subject'){
   $subject=tw_post('subject_name');$grade=tw_post('grade_level');$section=tw_post('class_section');
   if($subject==='')throw new Exception('Enter a subject name.');
   tw_req('teacher_subjects','POST',['teacher_id'=>$uid,'subject_name'=>$subject,'grade_level'=>$grade,'class_section'=>$section,'is_active'=>true]);
   tw_go('subjects','Subject assignment saved.');
  }
  if($action==='save_book_subject'){
   $book=tw_post('book_id');$subject=tw_post('subject_name');
   if($book===''||$subject==='')throw new Exception('Select a book and enter its subject.');
   tw_req('subject_book_resources','POST',['teacher_id'=>$uid,'book_id'=>$book,'subject_name'=>$subject,'grade_level'=>tw_post('grade_level'),'notes'=>tw_post('notes')]);
   tw_go('subjects','Book linked to the subject.');
  }
  if($action==='class_set'){
   $title=tw_post('book_title');$copies=max(1,(int)tw_post('copies'));
   if($title==='')throw new Exception('Enter the requested book title.');
   $created=tw_req('class_set_requests','POST',['teacher_id'=>$uid,'book_title'=>$title,'book_id'=>tw_post('book_id')?:null,'copies_requested'=>$copies,'grade_level'=>tw_post('grade_level'),'class_section'=>tw_post('class_section'),'needed_from'=>tw_post('needed_from')?:null,'needed_until'=>tw_post('needed_until')?:null,'purpose'=>tw_post('purpose'),'status'=>'Pending']);
   $requestId=(string)($created[0]['id']??'');
   if($requestId==='') throw new Exception('The request was saved without a returned ID. Check class_set_requests permissions.');
   $teacherLabel=$name.' ('.$uid.')';
   $message=$teacherLabel.' requested '.$copies.' copies of "'.$title.'" for '.(tw_post('class_section')?:'a class').'. Needed from '.(tw_post('needed_from')?:'not specified').'.';
   $recipients=tw_req('users?select=id,role&role=in.(admin,librarian)&is_active=eq.true');
   $sent=0; $notifyErrors=[];
   foreach($recipients as $recipient){
     if(empty($recipient['id'])) continue;
     try {
       tw_req('notifications','POST',['user_id'=>(string)$recipient['id'],'title'=>'New class set / bulk book request','message'=>$message,'type'=>'request','icon'=>'▤','is_read'=>false,'action_url'=>'../librarian/librarian_dashboard.php?section=requests','action_label'=>'Review request']);
       $sent++;
     } catch(Throwable $notifyError){ $notifyErrors[]=$notifyError->getMessage(); }
   }
   if($sent===0) tw_go('class_sets','Request saved (#'.$requestId.'), but no librarian/admin notification could be delivered. Check notifications table permissions and role accounts: '.($notifyErrors[0]??'No active admin or librarian accounts found.'),true);
   tw_go('class_sets','Request #'.$requestId.' submitted and notifications sent to '.$sent.' librarian/admin account(s).');
  }
  if($action==='lesson_reservation'){
   $start=tw_post('start_at');$end=tw_post('end_at');$resource=tw_post('resource_name');
   if($resource===''||$start===''||$end===''||strtotime($end)<=strtotime($start))throw new Exception('Provide a resource and a valid start/end time.');
   $conf=tw_req('instructional_reservations?select=id&resource_name=eq.'.rawurlencode($resource).'&status=not.in.(Cancelled,Rejected)&start_at=lt.'.rawurlencode(date('c',strtotime($end))).'&end_at=gt.'.rawurlencode(date('c',strtotime($start))));
   if($conf)throw new Exception('That resource already has a reservation during the selected time.');
   tw_req('instructional_reservations','POST',['teacher_id'=>$uid,'resource_type'=>tw_post('resource_type'),'resource_name'=>$resource,'start_at'=>date('c',strtotime($start)),'end_at'=>date('c',strtotime($end)),'class_section'=>tw_post('class_section'),'purpose'=>tw_post('purpose'),'status'=>'Pending']);
   tw_go('reservations','Instructional reservation submitted.');
  }
  if($action==='assignment'){
   $title=tw_post('assignment_title');$due=tw_post('due_date');
   if($title===''||$due==='')throw new Exception('Enter an assignment title and due date.');
   tw_req('reading_assignments','POST',['teacher_id'=>$uid,'book_id'=>tw_post('book_id')?:null,'book_title'=>$title,'grade_level'=>tw_post('grade_level'),'class_section'=>tw_post('class_section'),'instructions'=>tw_post('instructions'),'due_date'=>$due,'status'=>'Active']);
   tw_go('assignments','Reading assignment created.');
  }
  if($action==='assignment_progress'){
   $assignment=tw_post('assignment_id');$student=tw_post('student_name');
   if($assignment===''||$student==='')throw new Exception('Select an assignment and enter a student name.');
   $owned=tw_req('reading_assignments?select=id&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'id=eq.'.rawurlencode($assignment));
   if(!$owned)throw new Exception('Assignment not found or not accessible.');
   tw_req('reading_assignment_progress','POST',['assignment_id'=>$assignment,'teacher_id'=>$isAdmin?tw_post('teacher_id')?:$uid:$uid,'student_name'=>$student,'student_identifier'=>tw_post('student_identifier'),'progress_status'=>tw_post('progress_status')?:'Assigned','notes'=>tw_post('progress_notes'),'updated_at'=>date('c')]);
   tw_go('assignments','Student reading progress saved.');
  }
  if($action==='activity'){
   $title=tw_post('activity_title');$date=tw_post('activity_date');
   if($title===''||$date==='')throw new Exception('Enter an activity title and date.');
   tw_req('library_activities','POST',['teacher_id'=>$uid,'title'=>$title,'activity_date'=>$date,'start_time'=>tw_post('start_time')?:null,'end_time'=>tw_post('end_time')?:null,'grade_level'=>tw_post('grade_level'),'class_section'=>tw_post('class_section'),'purpose'=>tw_post('purpose'),'status'=>'Planned']);
   tw_go('activities','Library activity created.');
  }
  if($action==='attendance'){
   $activity=tw_post('activity_id');$student=tw_post('student_name');
   if($activity===''||$student==='')throw new Exception('Select an activity and enter a student name.');
   tw_req('library_activity_attendance','POST',['activity_id'=>$activity,'teacher_id'=>$uid,'student_name'=>$student,'student_identifier'=>tw_post('student_identifier'),'attendance_status'=>tw_post('attendance_status')?:'Present','participation_notes'=>tw_post('participation_notes')]);
   tw_go('activities','Attendance recorded.');
  }
  if($action==='purchase'){
   $title=tw_post('requested_title');if($title==='')throw new Exception('Enter the recommended book title.');
   $priority=tw_post('priority')?:'Normal';
   $purchase=tw_req('book_purchase_requests','POST',['teacher_id'=>$uid,'requested_title'=>$title,'author'=>tw_post('author'),'subject_name'=>tw_post('subject_name'),'reason'=>tw_post('reason'),'priority'=>$priority,'status'=>'Pending']);
   $purchaseId=(string)($purchase[0]['id']??'');
   $message=$name.' submitted a '.$priority.' priority purchase request for "'.$title.'"'.(tw_post('author')!==''?' by '.tw_post('author'):'').'.';
   $sent=0;$notifyErrors=[];
   try{
    $recipients=tw_req('users?select=id,role&role=eq.admin&is_active=eq.true');
    foreach($recipients as $recipient){
     if(empty($recipient['id']))continue;
     try{tw_req('notifications','POST',['user_id'=>(string)$recipient['id'],'title'=>'New Teacher Purchase Request','message'=>$message,'type'=>'request','icon'=>'▤','is_read'=>false,'action_url'=>'admin_dashboard.php?section=purchase_requests','action_label'=>'Review Purchase Request']);$sent++;}
     catch(Throwable $notifyError){$notifyErrors[]=$notifyError->getMessage();}
    }
   }catch(Throwable $notifyError){$notifyErrors[]=$notifyError->getMessage();}
   if($sent===0)tw_go('purchases','Purchase request'.($purchaseId!==''?' #'.$purchaseId:'').' saved, but admin notification could not be delivered. Check notifications permissions and active admin accounts.'.(!empty($notifyErrors)?' '.$notifyErrors[0]:''),true);
   tw_go('purchases','Purchase request submitted. Notification sent to '.$sent.' administrator(s).');
  }
  if($action==='eresource'){
   $title=tw_post('resource_title');$url=tw_post('resource_url');
   if($title===''||!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true))throw new Exception('Enter a title and a valid HTTP(S) file link.');
   tw_req('teacher_eresources','POST',['teacher_id'=>$uid,'title'=>$title,'resource_url'=>$url,'subject_name'=>tw_post('subject_name'),'grade_level'=>tw_post('grade_level'),'description'=>tw_post('description'),'access_level'=>tw_post('access_level')?:'Class']);
   tw_go('eresources','E-resource link saved.');
  }
  if($action==='admin_status'&&$isAdmin){
   $table=tw_post('table');$id=tw_post('record_id');$status=tw_post('status');
   $allowed=['class_set_requests','instructional_reservations','book_purchase_requests'];
   if(!in_array($table,$allowed,true)||$id===''||!in_array($status,['Approved','Rejected','Ready','Cancelled'],true))throw new Exception('Invalid review action.');
   tw_req($table.'?id=eq.'.rawurlencode($id),'PATCH',['status'=>$status,'reviewed_by'=>$uid,'reviewed_at'=>date('c')]);
   tw_go($tab,'Request status updated.');
  }
 }
}catch(Throwable $e){$err=$e->getMessage();}

$tables=[
 'subjects'=>'teacher_subjects?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($ownerFilter).'&').'order=created_at.desc',
 'mappings'=>'subject_book_resources?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($ownerFilter).'&').'order=created_at.desc',
 'class_sets'=>'class_set_requests?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=created_at.desc',
 'reservations'=>'instructional_reservations?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=start_at.desc',
 'assignments'=>'reading_assignments?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=due_date.desc',
 'progress'=>'reading_assignment_progress?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=updated_at.desc',
 'activities'=>'library_activities?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=activity_date.desc',
 'attendance'=>'library_activity_attendance?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=created_at.desc',
 'purchases'=>'book_purchase_requests?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=created_at.desc',
 'eresources'=>'teacher_eresources?select=*&'.($isAdmin?'':'teacher_id=eq.'.rawurlencode($uid).'&').'order=created_at.desc'
];
$data=[];foreach($tables as $key=>$endpoint){try{$data[$key]=tw_req($endpoint);}catch(Throwable $e){$data[$key]=[];$err=$err?:('Workspace data request failed for '.strtok($endpoint,'?').': '.$e->getMessage());}}
$books=[];try{$books=tw_req('books?select=id,title,author&order=title.asc&limit=500');}catch(Throwable $e){}
// Resolve teacher UUIDs to readable names for administrator review queues.
$teacherNames=[];
if($isAdmin){
 try{
  $userRows=tw_req('users?select=id,full_name,username,email&limit=1000');
  foreach($userRows as $userRow){
   $userId=(string)($userRow['id']??'');
   if($userId==='') continue;
   $display=trim((string)($userRow['full_name']??''));
   if($display==='') $display=trim((string)($userRow['username']??''));
   if($display==='') $display=trim((string)($userRow['email']??''));
   if($display!=='') $teacherNames[$userId]=$display;
  }
 }catch(Throwable $e){ $err=$err?:('Could not load teacher names: '.$e->getMessage()); }
}
$teacherSubjects=$data['subjects']??[];
$activeBorrowings=[];try{$activeBorrowings=tw_req('borrowings?select=*,books(title)&status=neq.Returned'.($isAdmin?'':'&user_id=eq.'.rawurlencode($uid)));}catch(Throwable $e){}
$overdue=array_filter($activeBorrowings,fn($r)=>!empty($r['due_date'])&&strtotime($r['due_date'])<strtotime(date('Y-m-d')));
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Teacher Library Workspace</title><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--ink:#09070b;--pink:#b40f7d;--pink2:#d41688;--paper:#f7f1f5;--line:#eeddea;--muted:#8d7d89}*{box-sizing:border-box}body{margin:0;background:var(--paper);font:14px Inter,Arial;color:#211522}.side{position:fixed;inset:0 auto 0 0;width:238px;background:#050409;color:#fff;padding:20px 14px;overflow:auto}.brand{padding:4px 10px 20px;border-bottom:1px solid #29212a}.brand img{width:68px;height:68px;object-fit:contain}.brand b{display:block;font-size:13px;margin-top:8px}.brand small{color:#aa9aaa}.nav{display:grid;gap:4px;padding:16px 0}.nav a,.back{display:block;padding:11px 12px;border-radius:9px;color:#cbbdca;text-decoration:none;font-size:12px}.nav a.active,.nav a:hover{background:#b40f7d33;color:#fff}.teacher-name{font-weight:600;color:#251525}.teacher-id-hint{display:block;color:#9a8996;font-size:10px;margin-top:3px} .main{margin-left:238px;padding:26px;max-width:1700px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:20px}.top h1{font-size:22px;margin:0}.top p{color:var(--muted);font-size:12px}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 8px 28px #2a0c2308}.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.stat strong{font-size:24px;display:block}.stat span{font-size:10px;color:var(--muted);text-transform:uppercase}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.formgrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.field{display:grid;gap:5px}.field.full{grid-column:1/-1}label{font-size:11px;font-weight:700;color:#6d596b}input,select,textarea{width:100%;padding:10px;border:1px solid #e7d9e4;border-radius:8px;background:#fff;font:inherit;font-size:12px}textarea{min-height:76px;resize:vertical}.btn{border:0;border-radius:8px;padding:10px 14px;background:var(--pink);color:white;font-weight:700;font-size:12px;cursor:pointer}.btn.secondary{background:#f6e8f1;color:#8f1768}.btnrow{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}.tablewrap{overflow:auto}table{border-collapse:collapse;width:100%;min-width:620px}th,td{text-align:left;padding:10px;border-bottom:1px solid #f0e7ee;font-size:12px;vertical-align:top}th{color:#8a6e82;font-size:10px;text-transform:uppercase}.pill{display:inline-block;padding:4px 8px;border-radius:30px;background:#f6e8f1;color:#8f1768;font-size:10px}.alert{padding:12px;border-radius:9px;margin-bottom:12px;background:#eaf7ef;color:#17613f}.alert.err{background:#fff0f2;color:#9f2440}.muted{color:var(--muted);font-size:12px}.empty{padding:15px;color:var(--muted);font-size:12px}.review{display:inline-flex;gap:5px;align-items:center}.review select{width:auto;padding:6px}.hamb{display:none}@media(max-width:900px){.side{position:static;width:auto}.brand{display:flex;align-items:center;gap:12px}.brand b{margin:0}.nav{display:flex;overflow:auto}.nav a{white-space:nowrap}.main{margin:0;padding:17px}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:620px){.grid,.formgrid{grid-template-columns:1fr}.field.full{grid-column:auto}.top{align-items:flex-start;flex-direction:column}}
</style></head><body>
<aside class="side"><div class="brand"><img src="../img/agustinnb.png" alt="School logo"><div><b>ST. AGNES ACADEMY</b><small><?= $isAdmin?'Administrator Workspace':'Teacher Library Workspace' ?></small></div></div><nav class="nav"><?php foreach($tabs as $key=>$label):?><a class="<?=$tab===$key?'active':''?>" href="?tab=<?=$key?>"><?=tw_h($label)?></a><?php endforeach;?></nav><div style="margin-top:20px"><?php if($isAdmin): ?><a class="back" style="display:block;margin-bottom:9px;background:#fce7f3;color:#9d174d;border-color:#f9a8d4;font-weight:700" href="../admin_dashboard.php">← Back to Admin Dashboard</a><?php endif; ?><a class="back" href="../<?= $isAdmin?'admin_dashboard.php':'teacher/teacher_dashboard.php' ?>">← Back to dashboard</a></div></aside>
<main class="main"><div class="top"><div><h1><?=tw_h($tabs[$tab])?></h1><p>Hello <?=tw_h($name)?> · Library teaching support</p></div><span class="pill"><?= $isAdmin?'Admin access · all teachers':'Teacher account' ?></span></div>
<?php if($msg):?><div class="alert"><?=tw_h($msg)?></div><?php endif;?><?php if($error||$err):?><div class="alert err"><?=tw_h($error?:$err)?></div><?php endif;?>
<?php if($tab==='overview'):?>
<div class="stats"><div class="card stat"><strong><?=count($activeBorrowings)?></strong><span>Active borrowings</span></div><div class="card stat"><strong><?=count($overdue)?></strong><span>Overdue borrowings</span></div><div class="card stat"><strong><?=count($data['assignments'])?></strong><span>Reading assignments</span></div><div class="card stat"><strong><?=count($data['activities'])?></strong><span>Library activities</span></div></div>
<div class="grid"><div class="card"><h3>Teaching support</h3><p class="muted">Connect books to subjects, request class sets, reserve library resources, and track reading activities from one workspace.</p><div class="btnrow"><a class="btn" href="?tab=subjects">Map subject resources</a><a class="btn secondary" href="?tab=class_sets">Request class set</a></div></div><div class="card"><h3>Borrowing overview</h3><?php if(!$activeBorrowings):?><p class="muted">No active borrowing records found.</p><?php else:?><p><?=count($activeBorrowings)?> active book(s), including <?=count($overdue)?> overdue.</p><?php endif;?></div></div>
<?php elseif($tab==='subjects'):?>
<div class="grid"><section class="card"><h3>My subject / class load</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="save_subject"><div class="formgrid"><div class="field"><label>Subject</label><input name="subject_name" required placeholder="e.g. Science"></div><div class="field"><label>Grade level</label><input name="grade_level" placeholder="e.g. Grade 10"></div><div class="field full"><label>Class / section</label><input name="class_section" placeholder="e.g. 10 - Rizal"></div></div><div class="btnrow"><button class="btn">Save subject</button></div></form></section><section class="card"><h3>Link a book to a subject</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="save_book_subject"><div class="field"><label>Book in catalog</label><select name="book_id" required><option value="">Select book</option><?php foreach($books as $b):?><option value="<?=tw_h($b['id'])?>"><?=tw_h($b['title'])?><?=!empty($b['author'])?' — '.tw_h($b['author']):''?></option><?php endforeach;?></select></div><div class="formgrid"><div class="field"><label>Subject</label><input name="subject_name" required></div><div class="field"><label>Grade level</label><input name="grade_level"></div><div class="field full"><label>Teaching notes</label><textarea name="notes"></textarea></div></div><div class="btnrow"><button class="btn">Map resource</button></div></form></section></div>
<div class="card"><h3>Subject assignments</h3><div class="tablewrap"><table><tr><th>Subject</th><th>Grade</th><th>Section</th><th>Status</th></tr><?php foreach($data['subjects'] as $r):?><tr><td><?=tw_h($r['subject_name'])?></td><td><?=tw_h($r['grade_level'])?></td><td><?=tw_h($r['class_section'])?></td><td><span class="pill"><?=tw_h($r['is_active']?'Active':'Inactive')?></span></td></tr><?php endforeach;?></table></div></div>
<div class="card"><h3>Mapped books</h3><div class="tablewrap"><table><tr><th>Book</th><th>Subject</th><th>Grade</th><th>Notes</th></tr><?php foreach($data['mappings'] as $r): $mappedBook=null; foreach($books as $bookRow){if((string)$bookRow['id']===(string)$r['book_id']){$mappedBook=$bookRow;break;}}?><tr><td><?=tw_h($mappedBook['title']??$r['book_id'])?><?=!empty($mappedBook['author'])?' — '.tw_h($mappedBook['author']):''?></td><td><?=tw_h($r['subject_name'])?></td><td><?=tw_h($r['grade_level'])?></td><td><?=tw_h($r['notes'])?></td></tr><?php endforeach;?></table></div></div>
<?php elseif($tab==='class_sets'):?>
<div class="card"><h3>Request a class set / bulk borrowing</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="class_set"><div class="formgrid"><div class="field"><label>Catalog book (optional)</label><select name="book_id"><option value="">Choose if already listed</option><?php foreach($books as $b):?><option value="<?=tw_h($b['id'])?>"><?=tw_h($b['title'])?></option><?php endforeach;?></select></div><div class="field"><label>Book title</label><input name="book_title" required></div><div class="field"><label>Copies needed</label><input type="number" name="copies" min="1" max="500" value="30" required></div><div class="field"><label>Grade level</label><input name="grade_level" placeholder="Grade 7"></div><div class="field"><label>Class / section</label><input name="class_section"></div><div class="field"><label>Needed from</label><input type="date" name="needed_from"></div><div class="field"><label>Needed until</label><input type="date" name="needed_until"></div><div class="field full"><label>Purpose</label><textarea name="purpose"></textarea></div></div><button class="btn">Submit class set request</button></form></div>
<?php elseif($tab==='reservations'):?>
<div class="card"><h3>Reserve a room, AV equipment, or library resource</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="lesson_reservation"><div class="formgrid"><div class="field"><label>Resource type</label><select name="resource_type"><option>Library Room</option><option>Projector</option><option>Computer</option><option>Speaker / Microphone</option><option>Books / Materials</option><option>Other</option></select></div><div class="field"><label>Resource name</label><input name="resource_name" required placeholder="e.g. Library Discussion Room"></div><div class="field"><label>Start</label><input type="datetime-local" name="start_at" required></div><div class="field"><label>End</label><input type="datetime-local" name="end_at" required></div><div class="field"><label>Class / section</label><input name="class_section"></div><div class="field"><label>Purpose / lesson</label><input name="purpose"></div></div><button class="btn">Request reservation</button></form></div>
<?php elseif($tab==='assignments'):?>
<div class="card"><h3>Assign a reading task</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="assignment"><div class="formgrid"><div class="field"><label>Assignment / book title</label><input name="assignment_title" required></div><div class="field"><label>Book (optional)</label><select name="book_id"><option value="">Select catalog book</option><?php foreach($books as $b):?><option value="<?=tw_h($b['id'])?>"><?=tw_h($b['title'])?></option><?php endforeach;?></select></div><div class="field"><label>Grade</label><input name="grade_level"></div><div class="field"><label>Class / section</label><input name="class_section"></div><div class="field"><label>Due date</label><input type="date" name="due_date" required></div><div class="field full"><label>Instructions</label><textarea name="instructions"></textarea></div></div><button class="btn">Create reading assignment</button></form></div>
<div class="grid"><section class="card"><h3>Track a student's reading progress</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="assignment_progress"><div class="field"><label>Assignment</label><select name="assignment_id" required><option value="">Select assignment</option><?php foreach($data['assignments'] as $a):?><option value="<?=tw_h($a['id'])?>"><?=tw_h($a['book_title'])?> · <?=tw_h($a['class_section'])?></option><?php endforeach;?></select></div><div class="formgrid"><div class="field"><label>Student name</label><input name="student_name" required></div><div class="field"><label>Student ID</label><input name="student_identifier"></div><div class="field"><label>Progress</label><select name="progress_status"><option>Assigned</option><option>Borrowed</option><option>Reading</option><option>Completed</option><option>Returned</option><option>Overdue</option></select></div><div class="field"><label>Notes</label><input name="progress_notes"></div></div><button class="btn">Save progress</button></form></section><section class="card"><h3>Student progress records</h3><div class="tablewrap"><table><tr><th>Student</th><th>Assignment ID</th><th>Status</th><th>Notes</th></tr><?php foreach($data['progress'] as $p):?><tr><td><?=tw_h($p['student_name'])?></td><td><?=tw_h($p['assignment_id'])?></td><td><span class="pill"><?=tw_h($p['progress_status'])?></span></td><td><?=tw_h($p['notes'])?></td></tr><?php endforeach;?></table></div></section></div>
<?php elseif($tab==='activities'):?>
<div class="grid"><section class="card"><h3>Create library activity</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="activity"><div class="formgrid"><div class="field full"><label>Activity title</label><input name="activity_title" required></div><div class="field"><label>Date</label><input type="date" name="activity_date" required></div><div class="field"><label>Grade</label><input name="grade_level"></div><div class="field"><label>Start time</label><input type="time" name="start_time"></div><div class="field"><label>End time</label><input type="time" name="end_time"></div><div class="field"><label>Class / section</label><input name="class_section"></div><div class="field full"><label>Purpose</label><textarea name="purpose"></textarea></div></div><button class="btn">Create activity</button></form></section><section class="card"><h3>Record attendance / participation</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="attendance"><div class="field"><label>Activity</label><select name="activity_id" required><option value="">Select activity</option><?php foreach($data['activities'] as $a):?><option value="<?=tw_h($a['id'])?>"><?=tw_h($a['title'])?> · <?=tw_h($a['activity_date'])?></option><?php endforeach;?></select></div><div class="formgrid"><div class="field"><label>Student name</label><input name="student_name" required></div><div class="field"><label>Student ID (optional)</label><input name="student_identifier"></div><div class="field"><label>Attendance</label><select name="attendance_status"><option>Present</option><option>Absent</option><option>Excused</option></select></div><div class="field"><label>Participation notes</label><input name="participation_notes"></div></div><button class="btn">Save attendance</button></form></section></div>
<div class="card"><h3>My activities</h3><div class="tablewrap"><table><tr><th>Activity</th><th>Date</th><th>Class</th><th>Status</th></tr><?php foreach($data['activities'] as $a):?><tr><td><?=tw_h($a['title'])?></td><td><?=tw_h($a['activity_date'])?></td><td><?=tw_h($a['class_section'])?></td><td><span class="pill"><?=tw_h($a['status'])?></span></td></tr><?php endforeach;?></table></div><h3>Attendance records</h3><div class="tablewrap"><table><tr><th>Student</th><th>Activity ID</th><th>Attendance</th><th>Participation</th></tr><?php foreach($data['attendance'] as $a):?><tr><td><?=tw_h($a['student_name'])?></td><td><?=tw_h($a['activity_id'])?></td><td><?=tw_h($a['attendance_status'])?></td><td><?=tw_h($a['participation_notes'])?></td></tr><?php endforeach;?></table></div></div>
<?php elseif($tab==='purchases'):?>
<div class="card"><h3>Recommend a book for purchase</h3><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="purchase"><div class="formgrid"><div class="field"><label>Book title</label><input name="requested_title" required></div><div class="field"><label>Author</label><input name="author"></div><div class="field"><label>Subject</label><input name="subject_name"></div><div class="field"><label>Priority</label><select name="priority"><option>Normal</option><option>High</option><option>Urgent</option></select></div><div class="field full"><label>Reason / learning need</label><textarea name="reason"></textarea></div></div><button class="btn">Send recommendation</button></form></div>
<div class="card"><h3>My Purchase Requests</h3><?php if(!empty($data['purchases'])):?><div class="tablewrap"><table><tr><th>Book</th><th>Author</th><th>Subject</th><th>Priority</th><th>Submitted</th><th>Status</th></tr><?php foreach($data['purchases'] as $purchase):?><tr><td><?=tw_h($purchase['requested_title']??'—')?></td><td><?=tw_h($purchase['author']??'—')?></td><td><?=tw_h($purchase['subject_name']??'—')?></td><td><span class="pill"><?=tw_h($purchase['priority']??'Normal')?></span></td><td><?=tw_h(!empty($purchase['created_at'])?date('M d, Y',strtotime($purchase['created_at'])):'—')?></td><td><span class="pill"><?=tw_h($purchase['status']??'Pending')?></span></td></tr><?php endforeach;?></table></div><?php else:?><p class="muted">You have not submitted any purchase recommendations yet.</p><?php endif;?></div>
<?php elseif($tab==='eresources'):?>
<div class="card"><h3>Add an e-resource link</h3><p class="muted">For now, store a secure link to an approved PDF or worksheet. File upload storage can be connected once a storage bucket and access rules are confirmed.</p><form method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="eresource"><div class="formgrid"><div class="field"><label>Resource title</label><input name="resource_title" required></div><div class="field"><label>HTTP(S) file URL</label><input type="url" name="resource_url" required></div><div class="field"><label>Subject</label><input name="subject_name"></div><div class="field"><label>Grade level</label><input name="grade_level"></div><div class="field"><label>Access</label><select name="access_level"><option>Class</option><option>Teachers</option><option>Library</option></select></div><div class="field full"><label>Description</label><textarea name="description"></textarea></div></div><button class="btn">Save e-resource</button></form></div>
<div class="card"><h3>Saved resources</h3><div class="tablewrap"><table><tr><th>Title</th><th>Subject</th><th>Grade</th><th>Access</th><th>Open</th></tr><?php foreach($data['eresources'] as $r):?><tr><td><?=tw_h($r['title'])?></td><td><?=tw_h($r['subject_name'])?></td><td><?=tw_h($r['grade_level'])?></td><td><?=tw_h($r['access_level'])?></td><td><a target="_blank" rel="noopener" href="<?=tw_h($r['resource_url'])?>">Open resource ↗</a></td></tr><?php endforeach;?></table></div></div>
<?php elseif($tab==='reports'):?>
<div class="card"><h3>Teacher library reports</h3><div class="stats"><div><strong><?=count($data['class_sets'])?></strong><span>Class set requests</span></div><div><strong><?=count($data['assignments'])?></strong><span>Assignments</span></div><div><strong><?=count($data['activities'])?></strong><span>Activities</span></div><div><strong><?=count($data['attendance'])?></strong><span>Attendance entries</span></div></div><h3>Overdue borrowers</h3><div class="tablewrap"><table><tr><th>Book</th><th>Borrower ID</th><th>Due date</th></tr><?php foreach($overdue as $b):?><tr><td><?=tw_h($b['books']['title']??'Book')?></td><td><?=tw_h($b['user_id']??'')?></td><td><?=tw_h($b['due_date'])?></td></tr><?php endforeach;?></table></div></div>
<?php endif;?>
<?php if($isAdmin && in_array($tab,['class_sets','reservations','purchases'],true)): $list=$tab==='class_sets'?$data['class_sets']:($tab==='reservations'?$data['reservations']:$data['purchases']);$table=$tab==='class_sets'?'class_set_requests':($tab==='reservations'?'instructional_reservations':'book_purchase_requests');?>
<div class="card"><h3>Administrator review queue</h3><div class="tablewrap"><table><tr><th>Request</th><th>Teacher</th><th>Status</th><th>Review</th></tr><?php foreach($list as $r):?><tr><td><?=tw_h($r['book_title']??$r['resource_name']??$r['requested_title']??'Request')?></td><td><?php $teacherId=(string)($r['teacher_id']??''); $teacherDisplay=$teacherNames[$teacherId]??''; ?><span class="teacher-name"><?=tw_h($teacherDisplay!==''?$teacherDisplay:'Teacher account')?></span><?php if($teacherDisplay==='' && $teacherId!==''):?><small class="teacher-id-hint">Account ID: <?=tw_h(substr($teacherId,0,8))?>…</small><?php endif;?></td><td><?=tw_h($r['status']??'')?></td><td><form class="review" method="post"><input type="hidden" name="csrf_token" value="<?=tw_h($csrf)?>"><input type="hidden" name="action" value="admin_status"><input type="hidden" name="table" value="<?=tw_h($table)?>"><input type="hidden" name="record_id" value="<?=tw_h($r['id'])?>"><select name="status"><option>Approved</option><option>Rejected</option><option>Ready</option><option>Cancelled</option></select><button class="btn">Save</button></form></td></tr><?php endforeach;?></table></div></div><?php endif;?>
</main></body></html>
