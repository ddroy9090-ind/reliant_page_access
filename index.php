<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';

process_logout();

if (!is_authenticated()) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$pageTotal = $certTotal = $pageWeek = $certWeek = 0;
$latestPage = $latestCert = null;
$monthlyLabels = $monthlyCounts = [];
$deviceChartLabels = $deviceChartCounts = [];
$error = null;

try {
  $pageTotal = (int)$pdo->query('SELECT COUNT(*) FROM page_access_logs')->fetchColumn();
  $certTotal = (int)$pdo->query('SELECT COUNT(*) FROM certificate_search_logs')->fetchColumn();

  $pageWeekStmt = $pdo->query("SELECT COUNT(*) FROM page_access_logs WHERE accessed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
  $pageWeek = (int)$pageWeekStmt->fetchColumn();

  $certWeekStmt = $pdo->query("SELECT COUNT(*) FROM certificate_search_logs WHERE searched_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
  $certWeek = (int)$certWeekStmt->fetchColumn();

  $latestPageStmt = $pdo->query('SELECT MAX(accessed_at) FROM page_access_logs');
  $latestPage = $latestPageStmt->fetchColumn() ?: null;

  $latestCertStmt = $pdo->query('SELECT MAX(searched_at) FROM certificate_search_logs');
  $latestCert = $latestCertStmt->fetchColumn() ?: null;

  $currentMonth = new DateTime('first day of this month');
  $currentMonth->setTime(0, 0, 0);
  $periodStart = (clone $currentMonth)->modify('-5 months');
  $periodEnd = (clone $currentMonth)->modify('+1 month');

  $monthlyStmt = $pdo->prepare(
    'SELECT DATE_FORMAT(accessed_at, "%Y-%m") AS month_key, COUNT(*) AS total
     FROM page_access_logs
     WHERE accessed_at >= :start AND accessed_at < :end
     GROUP BY month_key'
  );

  $monthlyStmt->execute([
    ':start' => $periodStart->format('Y-m-01 00:00:00'),
    ':end'   => $periodEnd->format('Y-m-01 00:00:00'),
  ]);

  $monthlyMap = [];
  foreach ($monthlyStmt as $row) {
    $monthlyMap[$row['month_key']] = (int)$row['total'];
  }

  $period = new DatePeriod($periodStart, new DateInterval('P1M'), 6);
  foreach ($period as $month) {
    $key = $month->format('Y-m');
    $monthlyLabels[] = $month->format('M Y');
    $monthlyCounts[] = $monthlyMap[$key] ?? 0;
  }

  $deviceCounts = [
    'Desktop' => 0,
    'Mobile'  => 0,
    'Tablet'  => 0,
    'Bot'     => 0,
    'Unknown' => 0,
  ];

  $deviceStmt = $pdo->prepare(
    'SELECT user_agent
       FROM page_access_logs
      WHERE accessed_at >= :start AND accessed_at < :end'
  );

  $deviceStmt->execute([
    ':start' => $periodStart->format('Y-m-01 00:00:00'),
    ':end'   => $periodEnd->format('Y-m-01 00:00:00'),
  ]);

  foreach ($deviceStmt as $row) {
    $device = detect_device($row);
    $deviceCounts[$device] = ($deviceCounts[$device] ?? 0) + 1;
  }

  arsort($deviceCounts);

  foreach ($deviceCounts as $label => $count) {
    if ($count > 0) {
      $deviceChartLabels[] = $label;
      $deviceChartCounts[] = $count;
    }
  }

  if (!$deviceChartLabels) {
    $deviceChartLabels = array_keys($deviceCounts);
    $deviceChartCounts = array_values($deviceCounts);
  }
} catch (Throwable $e) {
  $error = 'Unable to fetch summary data at this time.';
}

render_head('BTSPL ADMIN PORTAL');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('home');
?>

<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">Dashboard Overview</h2>
      <p class="para">Welcome to your ERP system dashboard.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-primary" href="page_access_logs.php"><i class="bi bi-list-ul me-1"></i>View Logs</a>
      <a class="btn btn-outline-primary" href="analytics_dashboard.php"><i class="bi bi-graph-up-arrow me-1"></i>Analytics</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-warning"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php else: ?>
    <section class="stat-grid mb-4">
      <div class="stat-card">
        <h5>Total page access logs <i class="bi bi-currency-dollar icon"></i></h5>
        <div class="value"><?= number_format($pageTotal) ?></div>
        <div class="growth text-success">Last 7 days: <?= number_format($pageWeek) ?></div>
      </div>
      <div class="stat-card">
        <h5>Active Users <i class="bi bi-people-fill icon"></i></h5>
        <div class="value">200</div>
        <div class="growth text-success">+180.1% from last month</div>
      </div>
      <div class="stat-card">
        <h5>Total Visits <i class="bi bi-activity icon"></i></h5>
        <div class="value">12,234</div>
        <div class="growth text-success">+19% from last month</div>
      </div>
      <div class="stat-card">
        <h5>Total certificate search logs <i class="bi bi-graph-up-arrow icon"></i></h5>
        <div class="value"><?= number_format($certTotal) ?></div>
        <div class="growth text-success">Last 7 days: <?= number_format($certWeek) ?></div>
      </div>
    </section>

  <?php endif; ?>
</main>

<?php
echo '</div>';
echo '</div>';
render_footer();
