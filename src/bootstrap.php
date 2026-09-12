<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);

date_default_timezone_set('America/Bahia');

function base_path(string $path = ''): string
{
    return dirname(__DIR__) . ($path ? '/' . ltrim($path, '/') : '');
}

function load_env(): array
{
    $values = [];
    $file = base_path('.env');
    if (!is_file($file)) $file = base_path('.env.example');
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $values[$key] = trim($value, "\"'");
    }
    return $values;
}

function env(string $key, ?string $default = null): ?string
{
    static $data = null;
    $data ??= load_env();
    return $_ENV[$key] ?? $data[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    if (env('DB_DRIVER', 'sqlite') === 'mysql') {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', env('DB_HOST','localhost'), env('DB_PORT','3306'), env('DB_DATABASE'));
        $pdo = new PDO($dsn, env('DB_USERNAME'), env('DB_PASSWORD'));
    } else {
        $file = base_path(env('DB_DATABASE', 'storage/ponto.sqlite'));
        if (!is_dir(dirname($file))) mkdir(dirname($file), 0775, true);
        $pdo = new PDO('sqlite:' . $file);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    if (env('DB_DRIVER', 'sqlite') === 'sqlite') initialize_sqlite($pdo);
    return $pdo;
}

function initialize_sqlite(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, document TEXT UNIQUE NOT NULL, timezone TEXT DEFAULT 'America/Bahia', created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, password_hash TEXT NOT NULL, role TEXT DEFAULT 'admin', active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS employees (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL, registration TEXT NOT NULL, name TEXT NOT NULL, cpf TEXT, department TEXT NOT NULL, job_title TEXT NOT NULL, schedule_name TEXT DEFAULT '44h semanais', status TEXT DEFAULT 'active', created_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(company_id, registration));
    CREATE TABLE IF NOT EXISTS punches (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL, employee_id INTEGER NOT NULL, punched_at TEXT NOT NULL, source TEXT DEFAULT 'agent', nsr TEXT, original_hash TEXT UNIQUE NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE IF NOT EXISTS audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, company_id INTEGER NOT NULL, user_id INTEGER, action TEXT NOT NULL, entity_type TEXT NOT NULL, entity_id TEXT, details TEXT, ip_address TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);"
    );
    if ((int)$pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn() === 0) {
        $pdo->beginTransaction();
        $pdo->exec("INSERT INTO companies (name, document) VALUES ('Condomínio Demonstração', '00.000.000/0001-00')");
        $stmt = $pdo->prepare('INSERT INTO users (company_id,name,email,password_hash,role) VALUES (1,?,?,?,?)');
        $stmt->execute(['Administrador', 'admin@pontocerto.local', password_hash('Admin@123', PASSWORD_DEFAULT), 'admin']);
        $pdo->exec("INSERT INTO employees (company_id,registration,name,cpf,department,job_title,schedule_name,status) VALUES
        (1,'0001','Ana Souza','000.000.000-00','Administrativo','Assistente administrativa','Seg–Sex • 08:00–17:00','active'),
        (1,'0002','Carlos Lima','111.111.111-11','Operacional','Agente de portaria','12×36 • 07:00–19:00','active'),
        (1,'0003','Mariana Alves','222.222.222-22','Serviços Gerais','Auxiliar de serviços gerais','Seg–Sáb • 07:00–15:20','leave'),
        (1,'0004','João Santos','333.333.333-33','Manutenção','Auxiliar de manutenção','Seg–Sex • 08:00–17:00','active')");
        $today = date('Y-m-d');
        $punch = $pdo->prepare('INSERT INTO punches (company_id,employee_id,punched_at,source,nsr,original_hash) VALUES (1,?,?,?,?,?)');
        foreach ([[1,'07:58:12'],[1,'12:01:03'],[1,'13:00:45'],[1,'17:06:20'],[2,'06:54:10'],[2,'12:02:31'],[4,'08:17:05']] as $i => [$employee,$time]) {
            $at = "$today $time";
            $punch->execute([$employee,$at,'agent',(string)(1000+$i),hash('sha256',"$employee|$at|$i")]);
        }
        $pdo->commit();
    }
}

function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function user(): ?array { return $_SESSION['user'] ?? null; }
function require_auth(): void { if (!user()) redirect('login'); }
function csrf_token(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(24)); }
function check_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_token'] ?? '')) { http_response_code(419); exit('Sessão expirada. Atualize a página.'); } }

function audit(string $action, string $entity, ?string $id = null, array $details = []): void
{
    if (!user()) return;
    $stmt = db()->prepare('INSERT INTO audit_logs (company_id,user_id,action,entity_type,entity_id,details,ip_address) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([user()['company_id'],user()['id'],$action,$entity,$id,json_encode($details, JSON_UNESCAPED_UNICODE),$_SERVER['REMOTE_ADDR'] ?? null]);
}

