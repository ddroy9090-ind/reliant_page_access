<?php
declare(strict_types=1);

function build_page_analytics(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end): array
{
  $countStmt = $pdo->prepare('SELECT COUNT(*) FROM page_access_logs WHERE accessed_at BETWEEN :start AND :end');
  $countStmt->execute([
    ':start' => $start->format('Y-m-d H:i:s'),
    ':end'   => $end->format('Y-m-d H:i:s'),
  ]);
  $totalRows = (int)$countStmt->fetchColumn();
  $limit = 200000;
  $fetchLimit = min($totalRows, $limit);
  $rows = fetch_page_logs_for_analytics($pdo, $start, $end, $fetchLimit ?: $limit);

  $pageCounts = [];
  $dailyTotals = [];
  $dailyPages = [];
  $deviceCounts = [];
  $sourceCounts = [];
  $sourceByPage = [];
  $countryCounts = [];
  $regionCounts = [];
  $sessions = [];
  $ipState = [];
  $rowsProcessed = 0;

  foreach ($rows as $row) {
    if (!isset($row['request_uri'])) {
      continue;
    }
    $page = normalise_page($row['request_uri']);
    $timeStr = $row['accessed_at'] ?? null;
    if (!$timeStr) {
      continue;
    }
    $time = new DateTimeImmutable($timeStr);
    $rowsProcessed++;

    $pageCounts[$page] = ($pageCounts[$page] ?? 0) + 1;
    $day = $time->format('Y-m-d');
    $dailyTotals[$day] = ($dailyTotals[$day] ?? 0) + 1;
    if (!isset($dailyPages[$day])) {
      $dailyPages[$day] = [];
    }
    $dailyPages[$day][$page] = ($dailyPages[$day][$page] ?? 0) + 1;

    $device = detect_device($row);
    $deviceCounts[$device] = ($deviceCounts[$device] ?? 0) + 1;

    $source = detect_source($row['referer'] ?? null, $row['traffic_source'] ?? null);
    $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
    if (!isset($sourceByPage[$page])) {
      $sourceByPage[$page] = [];
    }
    $sourceByPage[$page][$source] = ($sourceByPage[$page][$source] ?? 0) + 1;

    if ($country = detect_country($row)) {
      $countryCounts[$country] = ($countryCounts[$country] ?? 0) + 1;
    }
    if ($region = detect_region($row)) {
      $regionCounts[$region] = ($regionCounts[$region] ?? 0) + 1;
    }

    $ip = $row['ip_address'] ?? 'unknown';
    $sessionIdentifier = detect_session_identifier($row);
    $sessionKey = null;
    if ($sessionIdentifier) {
      $sessionKey = $ip . '|' . $sessionIdentifier;
      if (!isset($sessions[$sessionKey])) {
        $sessions[$sessionKey] = ['ip' => $ip, 'events' => []];
      }
    } else {
      $state = $ipState[$ip] ?? ['index' => 0, 'lastTime' => null, 'sessionKey' => null];
      if (!$state['sessionKey'] || !$state['lastTime'] || ($time->getTimestamp() - $state['lastTime']->getTimestamp()) > 1800) {
        $state['index']++;
        $state['sessionKey'] = $ip . '|' . $state['index'];
        $sessions[$state['sessionKey']] = ['ip' => $ip, 'events' => []];
      }
      $state['lastTime'] = $time;
      $ipState[$ip] = $state;
      $sessionKey = $state['sessionKey'];
    }

    $sessions[$sessionKey]['events'][] = [
      'page' => $page,
      'time' => $time
    ];
  }

  $sessionFirstPage = [];
  $sessionLastPage = [];
  $bounceCounts = [];
  $pageTimeTotals = [];
  $pageTimeSamples = [];
  $pageSessionVisits = [];
  $transitionCounts = [];

  foreach ($sessions as $session) {
    $events = $session['events'];
    $totalHits = count($events);
    if ($totalHits === 0) {
      continue;
    }
    $firstPage = $events[0]['page'];
    $lastPage  = $events[$totalHits - 1]['page'];
    $sessionFirstPage[$firstPage] = ($sessionFirstPage[$firstPage] ?? 0) + 1;
    $sessionLastPage[$lastPage]   = ($sessionLastPage[$lastPage] ?? 0) + 1;
    if ($totalHits === 1) {
      $bounceCounts[$firstPage] = ($bounceCounts[$firstPage] ?? 0) + 1;
    }
    $uniquePages = [];
    for ($i = 0; $i < $totalHits; $i++) {
      $event = $events[$i];
      $page  = $event['page'];
      $uniquePages[$page] = true;
      if ($i < $totalHits - 1) {
        $next = $events[$i + 1];
        $diff = $next['time']->getTimestamp() - $event['time']->getTimestamp();
        if ($diff > 0) {
          $diff = min($diff, 1800);
          $pageTimeTotals[$page] = ($pageTimeTotals[$page] ?? 0) + $diff;
          $pageTimeSamples[$page] = ($pageTimeSamples[$page] ?? 0) + 1;
        }
        $transitionKey = $page . '||' . $next['page'];
        $transitionCounts[$transitionKey] = ($transitionCounts[$transitionKey] ?? 0) + 1;
      }
    }
    foreach (array_keys($uniquePages) as $visitedPage) {
      $pageSessionVisits[$visitedPage] = ($pageSessionVisits[$visitedPage] ?? 0) + 1;
    }
  }

  $avgTime = [];
  foreach ($pageTimeTotals as $page => $totalSeconds) {
    $samples = $pageTimeSamples[$page] ?? 0;
    $avgTime[$page] = $samples > 0 ? $totalSeconds / $samples : 0.0;
  }

  $bounceRates = [];
  foreach ($sessionFirstPage as $page => $starts) {
    $bounces = $bounceCounts[$page] ?? 0;
    $bounceRates[$page] = $starts > 0 ? $bounces / $starts : 0.0;
  }

  $exitCounts = [];
  $exitRates = [];
  foreach ($sessionLastPage as $page => $count) {
    $exitCounts[$page] = $count;
    $visits = $pageSessionVisits[$page] ?? 0;
    $exitRates[$page] = $visits > 0 ? $count / $visits : 0.0;
  }

  $topPages = array_keys($pageCounts);
  arsort($pageCounts);
  $topPages = array_keys(array_slice($pageCounts, 0, 15, true));

  $popularity = [
    'pages' => format_sorted_list($pageCounts, 15),
    'sessions' => format_sorted_list($pageSessionVisits, 15)
  ];

  $traffic = [
    'dailyTotals' => format_daily_series($dailyTotals),
    'perPage'     => format_per_page_series($dailyPages, $topPages)
  ];

  $engagement = [
    'avgTime'    => format_metric_list($avgTime, 15, 'seconds'),
    'bounceRate' => array_map(static fn ($page, $rate) => ['page' => $page, 'rate' => $rate], array_keys($bounceRates), array_values($bounceRates)),
    'exitPages'  => array_map(static fn ($page, $count) => ['page' => $page, 'count' => $count], array_keys($exitCounts), array_values($exitCounts))
  ];

  $navigation = [
    'sankey' => build_sankey($transitionCounts, 50),
    'funnel' => build_funnel($sessionFirstPage, $transitionCounts)
  ];

  $devicesSources = [
    'devices'       => array_map(static fn ($device, $count) => ['device' => $device, 'visits' => $count], array_keys($deviceCounts), array_values($deviceCounts)),
    'sources'       => array_map(static fn ($source, $count) => ['source' => $source, 'visits' => $count], array_keys($sourceCounts), array_values($sourceCounts)),
    'sourcesByPage' => format_sources_by_page($sourceByPage, 10, $topPages)
  ];

  $countryList = array_map(static fn ($country, $count) => ['country' => $country, 'count' => $count], array_keys($countryCounts), array_values($countryCounts));
  $regionList  = array_map(static fn ($region, $count) => ['region' => $region, 'count' => $count], array_keys($regionCounts), array_values($regionCounts));

  $advancedMetrics = [];
  foreach ($avgTime as $page => $seconds) {
    $advancedMetrics[$page] = [
      'avg_time'   => $seconds,
      'bounce_rate'=> $bounceRates[$page] ?? 0.0,
      'exit_rate'  => $exitRates[$page] ?? 0.0,
      'visits'     => $pageCounts[$page] ?? 0,
    ];
  }

  $scatter = [];
  foreach ($advancedMetrics as $page => $metrics) {
    $scatter[] = [
      'page'       => $page,
      'avgTime'    => $metrics['avg_time'],
      'bounceRate' => $metrics['bounce_rate'],
      'exitRate'   => $metrics['exit_rate'],
      'visits'     => $metrics['visits'],
    ];
  }

  $correlation = compute_correlation_matrix($advancedMetrics);

  return [
    'rowsProcessed' => $rowsProcessed,
    'popularity'    => $popularity,
    'traffic'       => $traffic,
    'engagement'    => $engagement,
    'navigation'    => $navigation,
    'devicesSources'=> $devicesSources,
    'geography'     => [
      'countries' => $countryList,
      'regions'   => $regionList,
    ],
    'advanced' => [
      'correlationMatrix' => $correlation,
      'scatter'           => $scatter,
    ],
  ];
}

function fetch_page_logs_for_analytics(PDO $pdo, DateTimeImmutable $start, DateTimeImmutable $end, int $limit): array
{
  $columns = resolve_analytics_columns($pdo);
  if (!$columns) {
    return [];
  }
  $select = implode(', ', array_map(static fn ($col) => "`{$col}`", $columns));
  $sql = "SELECT {$select} FROM page_access_logs WHERE accessed_at BETWEEN :start AND :end ORDER BY accessed_at ASC LIMIT :limit";
  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':start', $start->format('Y-m-d H:i:s'));
  $stmt->bindValue(':end', $end->format('Y-m-d H:i:s'));
  $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
  $stmt->execute();
  return $stmt->fetchAll();
}

function resolve_analytics_columns(PDO $pdo): array
{
  $available = get_log_view_columns($pdo, 'page_access_logs');
  $base = ['id', 'ip_address', 'user_agent', 'referer', 'request_uri', 'accessed_at'];
  $optional = [
    'country', 'country_name', 'countryCode', 'country_code', 'country_iso',
    'region', 'region_name', 'state', 'state_name',
    'city', 'city_name', 'device_type', 'traffic_source',
    'session_id', 'session_key', 'session_uuid', 'session_identifier'
  ];
  $columns = [];
  foreach ($base as $col) {
    if (in_array($col, $available, true)) {
      $columns[] = $col;
    }
  }
  foreach ($optional as $col) {
    if (in_array($col, $available, true)) {
      $columns[] = $col;
    }
  }
  return $columns;
}

function normalise_page(?string $uri): string
{
  $uri = trim((string)$uri);
  return $uri !== '' ? $uri : '(unknown)';
}

function detect_session_identifier(array $row): ?string
{
  foreach (['session_id', 'session_key', 'session_uuid', 'session_identifier'] as $field) {
    if (!empty($row[$field])) {
      return (string)$row[$field];
    }
  }
  return null;
}

function detect_device(array $row): string
{
  if (!empty($row['device_type'])) {
    return ucfirst((string)$row['device_type']);
  }
  $ua = strtolower((string)($row['user_agent'] ?? ''));
  if ($ua === '') {
    return 'Unknown';
  }
  if (str_contains($ua, 'bot') || str_contains($ua, 'spider') || str_contains($ua, 'crawl')) {
    return 'Bot';
  }
  if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
    return 'Tablet';
  }
  if (str_contains($ua, 'mobile') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
    return 'Mobile';
  }
  return 'Desktop';
}

function detect_source(?string $referer, ?string $hint): string
{
  if ($hint) {
    return ucfirst($hint);
  }
  if (!$referer) {
    return 'Direct';
  }
  $host = parse_url($referer, PHP_URL_HOST) ?: $referer;
  $host = strtolower((string)$host);
  $socialHosts = ['facebook', 'instagram', 'twitter', 'linkedin', 't.co', 'youtube'];
  foreach ($socialHosts as $social) {
    if (str_contains($host, $social)) {
      return 'Social';
    }
  }
  $organicHosts = ['google', 'bing', 'yahoo', 'duckduckgo', 'baidu'];
  foreach ($organicHosts as $engine) {
    if (str_contains($host, $engine)) {
      return 'Organic';
    }
  }
  return 'Referral';
}

function detect_country(array $row): ?string
{
  foreach (['country', 'country_name', 'countryCode', 'country_code', 'country_iso'] as $field) {
    if (!empty($row[$field])) {
      return (string)$row[$field];
    }
  }
  return null;
}

function detect_region(array $row): ?string
{
  foreach (['region', 'region_name', 'state', 'state_name'] as $field) {
    if (!empty($row[$field])) {
      return (string)$row[$field];
    }
  }
  return null;
}

function format_sorted_list(array $data, int $limit, string $valueKey = 'page'): array
{
  arsort($data);
  $list = [];
  foreach (array_slice($data, 0, $limit, true) as $key => $value) {
    $list[] = [$valueKey => $key, 'visits' => $value, 'count' => $value, 'page' => $key];
  }
  return array_map(static function ($item) use ($valueKey) {
    if (!isset($item[$valueKey])) {
      $item[$valueKey] = $item['page'] ?? '';
    }
    return $item;
  }, $list);
}

function format_metric_list(array $data, int $limit, string $metricKey): array
{
  arsort($data);
  $list = [];
  foreach (array_slice($data, 0, $limit, true) as $page => $value) {
    $list[] = ['page' => $page, $metricKey => $value, 'value' => $value];
  }
  return $list;
}

function format_daily_series(array $dailyTotals): array
{
  ksort($dailyTotals);
  $series = [];
  foreach ($dailyTotals as $day => $count) {
    $series[] = ['date' => $day, 'total' => $count];
  }
  return $series;
}

function format_per_page_series(array $dailyPages, array $topPages): array
{
  ksort($dailyPages);
  $topPages = array_slice($topPages, 0, 6);
  $result = [];
  foreach ($topPages as $page) {
    $result[$page] = [];
  }
  foreach ($dailyPages as $day => $pages) {
    foreach ($topPages as $page) {
      $result[$page][] = ['date' => $day, 'visits' => $pages[$page] ?? 0];
    }
  }
  $formatted = [];
  foreach ($result as $page => $series) {
    $formatted[] = ['page' => $page, 'series' => $series];
  }
  return $formatted;
}

function format_sources_by_page(array $sourceByPage, int $limit, array $preferredPages): array
{
  $order = array_flip($preferredPages);
  $pages = array_keys($sourceByPage);
  usort($pages, function ($a, $b) use ($order, $sourceByPage) {
    $countA = array_sum($sourceByPage[$a] ?? []);
    $countB = array_sum($sourceByPage[$b] ?? []);
    $rankA = $order[$a] ?? PHP_INT_MAX;
    $rankB = $order[$b] ?? PHP_INT_MAX;
    if ($rankA === $rankB) {
      return $countB <=> $countA;
    }
    return $rankA <=> $rankB;
  });
  $pages = array_slice($pages, 0, $limit);
  $list = [];
  foreach ($pages as $page) {
    $list[] = ['page' => $page, 'breakdown' => $sourceByPage[$page]];
  }
  return $list;
}

function build_sankey(array $transitions, int $limit): array
{
  arsort($transitions);
  $transitions = array_slice($transitions, 0, $limit, true);
  $nodes = [];
  $links = [];
  $index = [];
  foreach ($transitions as $key => $value) {
    [$from, $to] = explode('||', $key);
    if (!isset($index[$from])) {
      $index[$from] = count($nodes);
      $nodes[] = $from;
    }
    if (!isset($index[$to])) {
      $index[$to] = count($nodes);
      $nodes[] = $to;
    }
    $links[] = ['source' => $index[$from], 'target' => $index[$to], 'value' => $value];
  }
  return ['nodes' => $nodes, 'links' => $links];
}

function build_funnel(array $sessionFirstPage, array $transitions): array
{
  if (!$sessionFirstPage) {
    return ['steps' => []];
  }
  arsort($sessionFirstPage);
  $startPage = array_key_first($sessionFirstPage);
  $steps = [['label' => $startPage, 'value' => $sessionFirstPage[$startPage] ?? 0]];
  $current = $startPage;
  $used = [$startPage];
  for ($i = 0; $i < 3; $i++) {
    $candidates = [];
    foreach ($transitions as $key => $value) {
      [$from, $to] = explode('||', $key);
      if ($from === $current && !in_array($to, $used, true)) {
        $candidates[$to] = ($candidates[$to] ?? 0) + $value;
      }
    }
    if (!$candidates) {
      break;
    }
    arsort($candidates);
    $nextPage = array_key_first($candidates);
    $steps[] = ['label' => $nextPage, 'value' => $candidates[$nextPage]];
    $used[] = $nextPage;
    $current = $nextPage;
  }
  return ['steps' => $steps];
}

function compute_correlation_matrix(array $metrics): array
{
  $labels = ['avg_time', 'bounce_rate', 'exit_rate'];
  $matrix = [];
  foreach ($labels as $rowMetric) {
    $row = [];
    foreach ($labels as $colMetric) {
      $row[] = pearson_correlation($metrics, $rowMetric, $colMetric);
    }
    $matrix[] = $row;
  }
  return ['labels' => ['Avg Time', 'Bounce Rate', 'Exit Rate'], 'matrix' => $matrix];
}

function pearson_correlation(array $metrics, string $xKey, string $yKey): float
{
  $x = [];
  $y = [];
  foreach ($metrics as $data) {
    if (isset($data[$xKey], $data[$yKey])) {
      $x[] = (float)$data[$xKey];
      $y[] = (float)$data[$yKey];
    }
  }
  $n = count($x);
  if ($n < 2) {
    return 0.0;
  }
  $meanX = array_sum($x) / $n;
  $meanY = array_sum($y) / $n;
  $sumNum = 0.0;
  $sumDenX = 0.0;
  $sumDenY = 0.0;
  for ($i = 0; $i < $n; $i++) {
    $diffX = $x[$i] - $meanX;
    $diffY = $y[$i] - $meanY;
    $sumNum += $diffX * $diffY;
    $sumDenX += $diffX ** 2;
    $sumDenY += $diffY ** 2;
  }
  if ($sumDenX <= 0 || $sumDenY <= 0) {
    return 0.0;
  }
  return max(-1.0, min(1.0, $sumNum / sqrt($sumDenX * $sumDenY)));
}
