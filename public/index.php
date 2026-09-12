<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';

$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/', '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($path === 'api/agent/punches' && $method === 'POST') {
    header('Content-Type: application/json');
    $token = $_SERVER['HTTP_X_AGENT_TOKEN'] ?? '';
    if (!hash_equals(env('APP_KEY',''), $token)) { http_response_code(401); echo json_encode(['error'=>'Token inválido']); exit; }
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $employee = db()->prepare('SELECT * FROM employees WHERE registration = ? LIMIT 1');
    $employee->execute([$data['registration'] ?? '']);
    $row = $employee->fetch();
    if (!$row || empty($data['punched_at'])) { http_response_code(422); echo json_encode(['error'=>'Dados inválidos']); exit; }
    $hash = hash('sha256', $row['company_id'].'|'.$row['id'].'|'.$data['punched_at'].'|'.($data['nsr'] ?? ''));
    $sql = env('DB_DRIVER', 'sqlite') === 'mysql'
        ? 'INSERT IGNORE INTO punches (company_id,employee_id,punched_at,source,nsr,original_hash) VALUES (?,?,?,?,?,?)'
        : 'INSERT OR IGNORE INTO punches (company_id,employee_id,punched_at,source,nsr,original_hash) VALUES (?,?,?,?,?,?)';
    $stmt = db()->prepare($sql);
    $stmt->execute([$row['company_id'],$row['id'],$data['punched_at'],'agent',$data['nsr'] ?? null,$hash]);
    echo json_encode(['ok'=>true,'id'=>db()->lastInsertId()]); exit;
}

if ($path === 'login') {
    if ($method === 'POST') {
        check_csrf();
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([strtolower(trim($_POST['email'] ?? ''))]);
        $account = $stmt->fetch();
        if ($account && password_verify($_POST['password'] ?? '', $account['password_hash'])) {
            session_regenerate_id(true);
            unset($account['password_hash']);
            $_SESSION['user'] = $account;
            redirect('dashboard');
        }
        $error = 'E-mail ou senha incorretos.';
    }
    require dirname(__DIR__) . '/views/login.php'; exit;
}

if ($path === 'logout') { session_destroy(); redirect('login'); }
require_auth();

if ($path === 'employees/new' && $method === 'POST') {
    check_csrf();
    $stmt = db()->prepare('INSERT INTO employees (company_id,registration,name,cpf,department,job_title,schedule_name,status) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([user()['company_id'],trim($_POST['registration']),trim($_POST['name']),trim($_POST['cpf']),trim($_POST['department']),trim($_POST['job_title']),trim($_POST['schedule_name']),'active']);
    audit('create','employee',(string)db()->lastInsertId(),['name'=>$_POST['name']]);
    $_SESSION['flash'] = 'Colaborador cadastrado com sucesso.';
    redirect('employees');
}

if ($path === 'schedules/new' && $method === 'POST') {
    check_csrf();
    $stmt=db()->prepare('INSERT INTO schedules (company_id,name,type,work_start,break_start,break_end,work_end,weekly_minutes,tolerance_minutes) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([user()['company_id'],trim($_POST['name']),$_POST['type'],$_POST['work_start'],$_POST['break_start']?:null,$_POST['break_end']?:null,$_POST['work_end'],(int)$_POST['weekly_minutes'],(int)$_POST['tolerance_minutes']]);
    audit('create','schedule',(string)db()->lastInsertId(),['name'=>$_POST['name']]);
    $_SESSION['flash']='Jornada cadastrada com sucesso.'; redirect('schedules');
}

if ($path === 'settings/save' && $method === 'POST') {
    check_csrf();
    $values=[(int)$_POST['daily_tolerance_minutes'],(float)$_POST['overtime_weekday_percent'],(float)$_POST['overtime_holiday_percent'],(float)$_POST['night_additional_percent'],(int)$_POST['night_hour_minutes'],(int)$_POST['closing_day'],user()['company_id']];
    $exists=db()->prepare('SELECT COUNT(*) FROM company_settings WHERE company_id=?');$exists->execute([user()['company_id']]);
    if($exists->fetchColumn()){$stmt=db()->prepare('UPDATE company_settings SET daily_tolerance_minutes=?,overtime_weekday_percent=?,overtime_holiday_percent=?,night_additional_percent=?,night_hour_minutes=?,closing_day=? WHERE company_id=?');}
    else{$stmt=db()->prepare('INSERT INTO company_settings (daily_tolerance_minutes,overtime_weekday_percent,overtime_holiday_percent,night_additional_percent,night_hour_minutes,closing_day,company_id) VALUES (?,?,?,?,?,?,?)');}
    $stmt->execute($values); audit('update','company_settings',(string)user()['company_id']);
    $_SESSION['flash']='Regras de apuração salvas.'; redirect('settings');
}

if ($path === 'treatment/save' && $method === 'POST') {
    check_csrf();
    $stmt=db()->prepare('INSERT INTO adjustments (company_id,employee_id,work_date,kind,status,adjusted_value,reason,created_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([user()['company_id'],(int)$_POST['employee_id'],$_POST['work_date'],$_POST['kind'],'pending',trim($_POST['adjusted_value']??''),trim($_POST['reason']),user()['id']]);
    audit('create','adjustment',(string)db()->lastInsertId(),['kind'=>$_POST['kind']]);
    $_SESSION['flash']='Tratamento registrado e enviado para aprovação.'; redirect('treatment');
}

$allowed = ['dashboard','employees','punches','treatment','reports','schedules','companies','audit','settings'];
$page = $path === '' ? 'dashboard' : $path;
if (!in_array($page, $allowed, true)) { http_response_code(404); $page = '404'; }
require dirname(__DIR__) . '/views/app.php';
