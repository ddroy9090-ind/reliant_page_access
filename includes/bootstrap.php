<?php
declare(strict_types=1);

$secureConn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
  || (($_SERVER['SERVER_PORT'] ?? null) == 443);

session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => '',
  'secure'   => $secureConn,
  'httponly' => true,
  'samesite' => 'Strict',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer-when-downgrade');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if ($secureConn) {
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}
header(
  "Content-Security-Policy: default-src 'self' cdn.jsdelivr.net cdn.plot.ly; img-src 'self' data:; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdn.plot.ly; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'"
);

const ADMIN_USER = 'admin';
const ADMIN_PASS = 'NoIdea@321';

if (!isset($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function csrf_token(): string
{
  return $_SESSION['csrf'];
}

function csrf_check(string $token): void
{
  if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
    http_response_code(403);
    exit('CSRF validation failed');
  }
}

function rl_hit(string $bucket, int $limitPerMinute): void
{
  $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
  $key = "rl:$bucket:$ip:" . floor(time() / 60);
  $_SESSION[$key] = (int)(($_SESSION[$key] ?? 0) + 1);
  if ($_SESSION[$key] > $limitPerMinute) {
    http_response_code(429);
    exit('Too many requests. Please try again soon.');
  }
}

function db(): PDO
{
  $host = 'localhost'; // instead of localhost
  $db   = 'certificate';
  $user = 'root';
  $pass = ''; // change if you set a password
  $dsn  = "mysql:host=$host;port=3306;dbname=$db;charset=utf8mb4";

  return new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ]);
}


function build_like_where(array $columns, string $term): array
{
  if ($term === '') {
    return ['', []];
  }
  $likes  = [];
  $params = [];
  foreach ($columns as $i => $col) {
    $key = ':q' . ($i + 1);
    $likes[] = "$col LIKE $key";
    $params[$key] = '%' . $term . '%';
  }
  return ['(' . implode(' OR ', $likes) . ')', $params];
}

function sanitize_date(?string $value, bool $endOfDay = false): ?DateTimeImmutable
{
  if ($value === null) {
    return null;
  }
  $value = trim($value);
  if ($value === '') {
    return null;
  }
  $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);
  if (!$dt) {
    return null;
  }
  return $endOfDay ? $dt->setTime(23, 59, 59) : $dt->setTime(0, 0, 0);
}

function sanitize_range(?string $start, ?string $end, string $column): array
{
  $startDt = sanitize_date($start, false);
  $endDt   = sanitize_date($end, true);
  if ($startDt && $endDt && $startDt > $endDt) {
    [$startDt, $endDt] = [$endDt, $startDt];
  }
  $whereParts = [];
  $params     = [];
  if ($startDt) {
    $whereParts[]          = "$column >= :start_date";
    $params[':start_date'] = $startDt->format('Y-m-d H:i:s');
  }
  if ($endDt) {
    $whereParts[]        = "$column <= :end_date";
    $params[':end_date'] = $endDt->format('Y-m-d H:i:s');
  }
  return [$whereParts, $params, $startDt, $endDt];
}

function get_log_view_columns(PDO $pdo, string $table): array
{
  static $cache = [];
  if (isset($cache[$table])) {
    return $cache[$table];
  }
  $stmt = $pdo->prepare(
    'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
  );
  $stmt->execute([':table' => $table]);
  $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
  $cache[$table] = $columns ?: [];
  return $cache[$table];
}

function has_column(PDO $pdo, string $table, string $column): bool
{
  return in_array($column, get_log_view_columns($pdo, $table), true);
}

function respond_json(array $payload, int $status = 200): void
{
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}
