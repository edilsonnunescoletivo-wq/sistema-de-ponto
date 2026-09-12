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

if ($path === 'employees/assign-schedule' && $method === 'POST') {
    check_csrf();
    $employee=(int)$_POST['employee_id']; $schedule=(int)$_POST['schedule_id'];
    $valid=db()->prepare('SELECT COUNT(*) FROM employees e,schedules s WHERE e.id=? AND s.id=? AND e.company_id=? AND s.company_id=?');
    $valid->execute([$employee,$schedule,user()['company_id'],user()['company_id']]);
    if(!$valid->fetchColumn()){http_response_code(422);exit('Colaborador ou jornada inválida.');}
    db()->prepare('UPDATE employee_schedule_assignments SET ends_on=? WHERE employee_id=? AND ends_on IS NULL')->execute([date('Y-m-d',strtotime('-1 day')),$employee]);
    db()->prepare('INSERT INTO employee_schedule_assignments (employee_id,schedule_id,starts_on) VALUES (?,?,?)')->execute([$employee,$schedule,$_POST['starts_on']]);
    $name=db()->prepare('SELECT name FROM schedules WHERE id=?');$name->execute([$schedule]);
    db()->prepare('UPDATE employees SET schedule_name=? WHERE id=?')->execute([$name->fetchColumn(),$employee]);
    audit('assign','schedule',(string)$schedule,['employee_id'=>$employee]);
    $_SESSION['flash']='Jornada atribuída ao colaborador.';redirect('employees');
}

if ($path === 'treatment/approve' && $method === 'POST') {
    check_csrf();
    require_role(['admin','rh']);
    $date=db()->prepare('SELECT work_date FROM adjustments WHERE id=? AND company_id=?');$date->execute([(int)$_POST['adjustment_id'],user()['company_id']]);$workDate=$date->fetchColumn();
    if(!$workDate||period_is_closed(user()['company_id'],$workDate)){$_SESSION['flash']='O tratamento pertence a um período fechado.';redirect('treatment');}
    $status=($_POST['decision']??'approve')==='reject'?'rejected':'approved';
    $stmt=db()->prepare("UPDATE adjustments SET status=?,approved_by=?,approved_at=CURRENT_TIMESTAMP WHERE id=? AND company_id=? AND status='pending'");
    $stmt->execute([$status,user()['id'],(int)$_POST['adjustment_id'],user()['company_id']]);
    audit($status,'adjustment',(string)$_POST['adjustment_id']);$_SESSION['flash']=$status==='approved'?'Tratamento aprovado.':'Tratamento rejeitado.';redirect('treatment');
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
    if(period_is_closed(user()['company_id'],$_POST['work_date'])){$_SESSION['flash']='Este período está fechado e não aceita novos tratamentos.';redirect('treatment');}
    $stmt=db()->prepare('INSERT INTO adjustments (company_id,employee_id,work_date,kind,status,adjusted_value,reason,created_by) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([user()['company_id'],(int)$_POST['employee_id'],$_POST['work_date'],$_POST['kind'],'pending',trim($_POST['adjusted_value']??''),trim($_POST['reason']),user()['id']]);
    audit('create','adjustment',(string)db()->lastInsertId(),['kind'=>$_POST['kind']]);
    $_SESSION['flash']='Tratamento registrado e enviado para aprovação.'; redirect('treatment');
}

if ($path === 'punches/import' && $method === 'POST') {
    check_csrf();
    if(!isset($_FILES['afd'])||$_FILES['afd']['error']!==UPLOAD_ERR_OK){$_SESSION['flash']='Não foi possível receber o arquivo.';redirect('punches');}
    try{$result=(new AfdImporter())->import($_FILES['afd']['tmp_name'],user()['company_id'],user()['id']);audit('import','afd',null,$result);$_SESSION['flash']="Importação concluída: {$result['imported']} novas, {$result['duplicates']} duplicadas e {$result['unknown']} não identificadas.";}
    catch(Throwable $e){$_SESSION['flash']='Falha na importação: '.$e->getMessage();}
    redirect('punches');
}

if ($path === 'treatment/process' && $method === 'POST') {
    check_csrf();$from=$_POST['from']??date('Y-m-01');$to=$_POST['to']??date('Y-m-d');
    if($from>$to){$_SESSION['flash']='O período informado é inválido.';redirect('treatment');}
    $result=(new PeriodProcessor())->process(user()['company_id'],$from,$to,user()['id']);audit('process','period',null,['from'=>$from,'to'=>$to]+$result);
    $_SESSION['flash']="Processamento concluído: {$result['days']} dias analisados e {$result['issues']} ocorrências encontradas.";redirect('treatment');
}

if ($path === 'period/close' && $method === 'POST') {
    check_csrf();$from=$_POST['from'];$to=$_POST['to'];
    require_role(['admin','rh']);
    $pending=db()->prepare("SELECT COUNT(*) FROM adjustments WHERE company_id=? AND status='pending' AND work_date BETWEEN ? AND ?");$pending->execute([user()['company_id'],$from,$to]);
    if($pending->fetchColumn()){$_SESSION['flash']='Existem tratamentos pendentes. Aprove ou rejeite antes de fechar.';redirect('reports');}
    $sql=env('DB_DRIVER','sqlite')==='mysql'?'INSERT INTO period_closures(company_id,starts_on,ends_on,status,closed_by,closed_at) VALUES(?,?,?,\'closed\',?,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status=\'closed\',closed_by=VALUES(closed_by),closed_at=CURRENT_TIMESTAMP':'INSERT INTO period_closures(company_id,starts_on,ends_on,status,closed_by,closed_at) VALUES(?,?,?,\'closed\',?,CURRENT_TIMESTAMP) ON CONFLICT(company_id,starts_on,ends_on) DO UPDATE SET status=\'closed\',closed_by=excluded.closed_by,closed_at=CURRENT_TIMESTAMP';
    db()->prepare($sql)->execute([user()['company_id'],$from,$to,user()['id']]);audit('close','period',null,['from'=>$from,'to'=>$to]);$_SESSION['flash']='Período fechado com sucesso.';redirect('reports');
}

if ($path === 'period/reopen' && $method === 'POST') {
    check_csrf();require_role(['admin']);
    $stmt=db()->prepare("UPDATE period_closures SET status='open',closed_by=NULL,closed_at=NULL WHERE company_id=? AND starts_on=? AND ends_on=?");$stmt->execute([user()['company_id'],$_POST['from'],$_POST['to']]);
    audit('reopen','period',null,['from'=>$_POST['from'],'to'=>$_POST['to']]);$_SESSION['flash']='Período reaberto pelo administrador.';redirect('reports');
}

if ($path === 'reports/payroll.csv') {
    $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');audit('export','payroll',null,['from'=>$from,'to'=>$to]);ReportExporter::payrollCsv(user()['company_id'],$from,$to);
}

if ($path === 'reports/aej.txt') {
    $from=$_GET['from']??date('Y-m-01');$to=$_GET['to']??date('Y-m-d');header('Content-Type: text/plain; charset=UTF-8');header('Content-Disposition: attachment; filename="AEJ-'.$from.'-'.$to.'.txt"');echo "AEJ;1;$from;$to\r\n";
    $q=db()->prepare('SELECT e.registration,e.cpf,p.punched_at,p.nsr FROM punches p JOIN employees e ON e.id=p.employee_id WHERE p.company_id=? AND DATE(p.punched_at) BETWEEN ? AND ? ORDER BY p.punched_at');$q->execute([user()['company_id'],$from,$to]);foreach($q as $r)echo implode(';',[$r['nsr'],$r['registration'],preg_replace('/\D/','',$r['cpf']??''),$r['punched_at']])."\r\n";audit('export','aej',null,['from'=>$from,'to'=>$to]);exit;
}

if ($path === 'users/new' && $method === 'POST') {
    check_csrf();require_role(['admin']);$stmt=db()->prepare('INSERT INTO users(company_id,name,email,password_hash,role) VALUES(?,?,?,?,?)');$stmt->execute([user()['company_id'],trim($_POST['name']),strtolower(trim($_POST['email'])),password_hash($_POST['password'],PASSWORD_DEFAULT),$_POST['role']]);audit('create','user',(string)db()->lastInsertId());$_SESSION['flash']='Usuário cadastrado.';redirect('users');
}

$allowed = ['dashboard','employees','punches','treatment','reports','schedules','companies','users','audit','settings'];
$page = $path === '' ? 'dashboard' : $path;
if (!in_array($page, $allowed, true)) { http_response_code(404); $page = '404'; }
require dirname(__DIR__) . '/views/app.php';
