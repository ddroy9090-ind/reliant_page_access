<?php
declare(strict_types=1);

require_once __DIR__ . '/analytics.php';

function handle_logs_ajax(PDO $pdo): void
{
  rl_hit('logs', 120);
  $type = $_GET['type'] ?? 'page';
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per  = min(100, max(1, (int)($_GET['per'] ?? 10)));
  $offset = ($page - 1) * $per;
  $q = trim($_GET['q'] ?? '');
  $start = $_GET['start'] ?? null;
  $end   = $_GET['end'] ?? null;

  if ($type === 'page') {
    $table = 'page_access_logs';
    $whereParts = [];
    $params = [];
    [$rangeParts, $rangeParams] = sanitize_range($start, $end, 'accessed_at');
    if ($rangeParts) {
      $whereParts = array_merge($whereParts, $rangeParts);
      $params = array_merge($params, $rangeParams);
    }
    [$likeSql, $likeParams] = build_like_where(['ip_address', 'user_agent', 'referer', 'request_uri'], $q);
    if ($likeSql) {
      $whereParts[] = $likeSql;
      $params = array_merge($params, $likeParams);
    }
    $whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $countSql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    $columns = ['id', 'ip_address', 'user_agent', 'referer', 'request_uri', 'accessed_at'];
    $selectSql = "SELECT " . implode(', ', $columns) . " FROM {$table} {$whereSql} ORDER BY accessed_at DESC LIMIT :offset, :per";
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per', $per, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    respond_json(['total' => $total, 'rows' => $rows]);
  } elseif ($type === 'cert') {
    $table = 'certificate_search_logs';
    $whereParts = [];
    $params = [];
    [$rangeParts, $rangeParams] = sanitize_range($start, $end, 'searched_at');
    if ($rangeParts) {
      $whereParts = array_merge($whereParts, $rangeParts);
      $params = array_merge($params, $rangeParams);
    }
    $columns = ['ip_address', 'certificate_no', 'status', 'user_agent'];
    [$likeSql, $likeParams] = build_like_where($columns, $q);
    if ($likeSql) {
      $whereParts[] = $likeSql;
      $params = array_merge($params, $likeParams);
    }
    $whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

    $countSql = "SELECT COUNT(*) FROM {$table} {$whereSql}";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    $selectColumns = ['id', 'ip_address', 'certificate_no', 'status', 'user_agent', 'searched_at'];
    $selectSql = "SELECT " . implode(', ', $selectColumns) . " FROM {$table} {$whereSql} ORDER BY searched_at DESC LIMIT :offset, :per";
    $stmt = $pdo->prepare($selectSql);
    foreach ($params as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':per', $per, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    respond_json(['total' => $total, 'rows' => $rows]);
  } else {
    respond_json(['error' => 'Invalid log type'], 400);
  }
}

function handle_export(PDO $pdo): void
{
  $type = $_GET['export'] ?? '';
  $start = $_GET['start'] ?? null;
  $end   = $_GET['end'] ?? null;
  if (!$start || !$end) {
    respond_json(['error' => 'Start and end date are required for export'], 400);
  }
  [$rangeParts, $rangeParams, $startDt, $endDt] = sanitize_range($start, $end, $type === 'cert' ? 'searched_at' : 'accessed_at');
  if (!$startDt || !$endDt) {
    respond_json(['error' => 'Invalid date range provided'], 400);
  }
  $column = $type === 'cert' ? 'searched_at' : 'accessed_at';
  $table  = $type === 'cert' ? 'certificate_search_logs' : 'page_access_logs';
  $select = $type === 'cert'
    ? 'id, ip_address, certificate_no, status, user_agent, searched_at'
    : 'id, ip_address, user_agent, referer, request_uri, accessed_at';
  $whereSql = $rangeParts ? 'WHERE ' . implode(' AND ', $rangeParts) : '';

  $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} {$whereSql}");
  foreach ($rangeParams as $k => $v) {
    $countStmt->bindValue($k, $v);
  }
  $countStmt->execute();
  $total = (int)$countStmt->fetchColumn();
  if ($total > 1000000) {
    respond_json(['error' => 'Requested export exceeds the limit of 1,000,000 rows. Please narrow the date range.'], 400);
  }

  $filename = sprintf('%s_logs_%s_%s.csv', $type === 'cert' ? 'certificate' : 'page', $startDt->format('Ymd'), $endDt->format('Ymd'));
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="' . $filename . '"');
  $out = fopen('php://output', 'w');
  if ($type === 'cert') {
    fputcsv($out, ['ID', 'IP Address', 'Certificate No', 'Status', 'User Agent', 'Searched At']);
  } else {
    fputcsv($out, ['ID', 'IP Address', 'User Agent', 'Referer', 'Request URI', 'Accessed At']);
  }
  $batch = 5000;
  $offset = 0;
  $sql = "SELECT {$select} FROM {$table} {$whereSql} ORDER BY {$column} ASC LIMIT :offset, :limit";
  while (true) {
    $stmt = $pdo->prepare($sql);
    foreach ($rangeParams as $k => $v) {
      $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $batch, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    if (!$rows) {
      break;
    }
    foreach ($rows as $row) {
      fputcsv($out, array_values($row));
    }
    $offset += $batch;
  }
  fclose($out);
  exit;
}

function handle_analytics(PDO $pdo): void
{
  try {
    $start = $_GET['start'] ?? null;
    $end   = $_GET['end'] ?? null;
    [$rangeParts, , $startDt, $endDt] = sanitize_range($start, $end, 'accessed_at');
    if (!$startDt || !$endDt) {
      $endDt   = new DateTimeImmutable('today 23:59:59');
      $startDt = $endDt->modify('-29 days')->setTime(0, 0, 0);
    }
    $analytics = build_page_analytics($pdo, $startDt, $endDt);
    respond_json($analytics);
  } catch (Throwable $e) {
    error_log('[ANALYTICS_ERROR] ' . $e->getMessage());
    respond_json(['error' => 'Failed to compute analytics'], 500);
  }
}
