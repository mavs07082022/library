<?php
/**
 * St. Agnes Academy LMS — Secure Profile API
 * Path: library/api/profile.php
 *
 * Required server environment variables:
 * SUPABASE_URL
 * SUPABASE_ANON_KEY
 * SUPABASE_SERVICE_ROLE_KEY
 *
 * Uses the existing PHP session login (session user_id = public.users.id).
 */
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function profile_json(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function profile_env(string $key): string {
    $v = getenv($key);
    return $v === false ? '' : trim($v);
}
function profile_curl(string $url, string $method, array $headers, ?string $body = null): array {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ];
    if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) throw new RuntimeException('Connection failed: ' . $err);
    $decoded = json_decode($raw, true);
    return ['code'=>$code, 'raw'=>$raw, 'json'=>is_array($decoded)?$decoded:[], 'content_type'=>null];
}
function profile_rest(string $base, string $key, string $endpoint, string $method='GET', ?array $data=null): array {
    $url = rtrim($base,'/') . '/rest/v1/' . $endpoint;
    $headers = [
        'apikey: '.$key,
        'Authorization: Bearer '.$key,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ];
    $r = profile_curl($url,$method,$headers,$data===null?null:json_encode($data));
    if ($r['code'] < 200 || $r['code'] >= 300) {
        throw new RuntimeException('Supabase REST error (' . $r['code'] . '): ' . substr($r['raw'],0,600));
    }
    return $r['json'];
}
function profile_csrf_check(): void {
    $expected = (string)($_SESSION['profile_csrf'] ?? '');
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
    if ($expected === '' || !hash_equals($expected,$provided)) profile_json(403,['ok'=>false,'error'=>'Invalid or expired security token. Refresh and try again.']);
}

$base = profile_env('SUPABASE_URL');
$anon = profile_env('SUPABASE_ANON_KEY');
$service = profile_env('SUPABASE_SERVICE_ROLE_KEY');
$userId = (string)($_SESSION['user_id'] ?? '');

if ($userId === '') profile_json(401,['ok'=>false,'error'=>'Your session has expired. Please sign in again.']);
if ($base==='' || $anon==='' || $service==='') profile_json(500,['ok'=>false,'error'=>'Server Supabase configuration is incomplete.']);

if (empty($_SESSION['profile_csrf'])) $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['profile_csrf'];

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'GET') {
    try {
        $rows = profile_rest($base,$service,
            'users?select=id,username,email,full_name,role,user_id,phone,address,birth_date,bio,avatar_url&id=eq.'.rawurlencode($userId)
        );
        if (!$rows) profile_json(404,['ok'=>false,'error'=>'Profile not found.']);
        $user = $rows[0];
        // Student extension table is optional for staff; query only for students.
        if (($user['role'] ?? '') === 'student') {
            $st = profile_rest($base,$service,
                'students?select=student_id,course,year_level,section,birth_date,address,phone,guardian_name,guardian_phone&user_id=eq.'.rawurlencode($userId)
            );
            $user['student_details'] = $st[0] ?? [];
        }
        profile_json(200,['ok'=>true,'csrf_token'=>$csrf,'profile'=>$user]);
    } catch (Throwable $e) {
        error_log('[profile API GET] '.$e->getMessage());
        profile_json(500,['ok'=>false,'error'=>'Could not load profile. Check server logs.']);
    }
}

if ($method !== 'POST') profile_json(405,['ok'=>false,'error'=>'Method not allowed.']);
profile_csrf_check();

$action = (string)($_POST['action'] ?? '');
try {
    if ($action === 'update') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $birthDate = trim((string)($_POST['birth_date'] ?? ''));
        $bio = trim((string)($_POST['bio'] ?? ''));

        if ($fullName === '' || mb_strlen($fullName)>120) profile_json(422,['ok'=>false,'error'=>'Full name is required and must be under 120 characters.']);
        if ($phone !== '' && mb_strlen($phone)>30) profile_json(422,['ok'=>false,'error'=>'Phone number is too long.']);
        if ($birthDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$birthDate)) profile_json(422,['ok'=>false,'error'=>'Birthday must be a valid date.']);
        if (mb_strlen($address)>1000 || mb_strlen($bio)>1000) profile_json(422,['ok'=>false,'error'=>'Address and bio must be under 1000 characters.']);

        $payload = [
            'full_name'=>$fullName,
            'phone'=>$phone ?: null,
            'address'=>$address ?: null,
            'birth_date'=>$birthDate ?: null,
            'bio'=>$bio ?: null,
        ];
        $updated = profile_rest($base,$service,'users?id=eq.'.rawurlencode($userId),'PATCH',$payload);
        if (!$updated) profile_json(404,['ok'=>false,'error'=>'No account was updated.']);
        $_SESSION['full_name'] = $fullName;

        // Keep student table's existing student-specific columns in sync.
        $role = strtolower((string)($updated[0]['role'] ?? $_SESSION['role'] ?? ''));
        if ($role === 'student') {
            $studentRows = profile_rest($base,$service,'students?select=id&user_id=eq.'.rawurlencode($userId));
            if ($studentRows) {
                profile_rest($base,$service,'students?user_id=eq.'.rawurlencode($userId),'PATCH',[
                    'birth_date'=>$birthDate ?: null,'address'=>$address ?: null,'phone'=>$phone ?: null
                ]);
            }
        }
        profile_json(200,['ok'=>true,'message'=>'Profile updated successfully.','profile'=>$updated[0]]);
    }

    if ($action === 'avatar') {
        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) profile_json(422,['ok'=>false,'error'=>'Choose an image to upload.']);
        $file = $_FILES['avatar'];
        if ($file['size'] > 5*1024*1024) profile_json(413,['ok'=>false,'error'=>'Image must be 5 MB or smaller.']);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if (!isset($allowed[$mime])) profile_json(415,['ok'=>false,'error'=>'Only JPG, PNG, or WEBP images are supported.']);
        $image = file_get_contents($file['tmp_name']);
        if ($image === false) profile_json(500,['ok'=>false,'error'=>'Could not read uploaded image.']);

        $bucket = 'profile-photos';
        $path = $userId.'/'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
        $storageUrl = rtrim($base,'/').'/storage/v1/object/'.rawurlencode($bucket).'/'.str_replace('%2F','/',rawurlencode($path));
        $r = profile_curl($storageUrl,'POST',[
            'apikey: '.$service,
            'Authorization: Bearer '.$service,
            'Content-Type: '.$mime,
            'x-upsert: true',
            'Cache-Control: max-age=3600'
        ],$image);
        if ($r['code']<200 || $r['code']>=300) {
            throw new RuntimeException('Storage upload error ('.$r['code'].'): '.substr($r['raw'],0,500));
        }
        $publicUrl = rtrim($base,'/').'/storage/v1/object/public/'.rawurlencode($bucket).'/'.str_replace('%2F','/',rawurlencode($path));
        $updated = profile_rest($base,$service,'users?id=eq.'.rawurlencode($userId),'PATCH',['avatar_url'=>$publicUrl]);
        if (!$updated) profile_json(500,['ok'=>false,'error'=>'Image uploaded but profile URL could not be saved.']);
        profile_json(200,['ok'=>true,'message'=>'Profile picture updated.','avatar_url'=>$publicUrl]);
    }
    profile_json(400,['ok'=>false,'error'=>'Unknown action.']);
} catch (Throwable $e) {
    error_log('[profile API POST] '.$e->getMessage());
    profile_json(500,['ok'=>false,'error'=>'Profile update failed. Check server logs.']);
}
