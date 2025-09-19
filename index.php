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

render_head('Reliant Monitor Portal');
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
        <h5>Overall page access logs <i class="bi bi-currency-dollar icon"></i></h5>
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

    <section class="row g-4 chart-section">
      <div class="col-12 col-lg-8">
        <div class="chart-card h-100">
          <div class="chart-header">
            <h5 class="mb-1">Monthly page access trend</h5>
            <p class="chart-subtitle mb-0">Showing the last six months of activity.</p>
          </div>
          <div class="chart-wrapper">
            <canvas id="monthlyTrendChart"></canvas>
          </div>
        </div>
      </div>
      <div class="col-12 col-lg-4">
        <div class="chart-card h-100">
          <div class="chart-header">
            <h5 class="mb-1">Device distribution</h5>
            <p class="chart-subtitle mb-0">Breakdown of traffic sources.</p>
          </div>
          <div class="chart-wrapper">
            <canvas id="deviceBreakdownChart"></canvas>
          </div>
        </div>
      </div>
    </section>

  <?php endif; ?>
</main>

<?php
echo '</div>';
echo '</div>';
?>

<?php if (!$error): ?>
  <script>
    window.addEventListener('load', () => {
      if (!window.Chart) {
        console.warn('Chart.js failed to load.');
        return;
      }

      const monthlyLabels = <?= json_encode($monthlyLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      const monthlyCounts = <?= json_encode($monthlyCounts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      const deviceLabels = <?= json_encode($deviceChartLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
      const deviceCounts = <?= json_encode($deviceChartCounts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

      const primaryColor = '#d01f28';
      const accentPalette = ['#d01f28', '#e14a51', '#e76c72', '#ed8f93', '#f3b1b4'];

      const monthlyCanvas = document.getElementById('monthlyTrendChart');
      if (monthlyCanvas) {
        const ctx = monthlyCanvas.getContext('2d');
        new window.Chart(ctx, {
          type: 'line',
          data: {
            labels: monthlyLabels,
            datasets: [{
              label: 'Page accesses',
              data: monthlyCounts,
              borderColor: primaryColor,
              backgroundColor: 'rgba(208, 31, 40, 0.15)',
              pointBackgroundColor: primaryColor,
              pointBorderWidth: 2,
              tension: 0.35,
              fill: true,
              borderWidth: 3
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: '#1f1f1f',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                padding: 12,
                borderColor: 'rgba(255, 255, 255, 0.15)',
                borderWidth: 1
              }
            },
            scales: {
              x: {
                ticks: { color: '#4b4b4b' },
                grid: { color: 'rgba(208, 31, 40, 0.08)', drawBorder: false }
              },
              y: {
                beginAtZero: true,
                ticks: { color: '#4b4b4b' },
                grid: { color: 'rgba(208, 31, 40, 0.05)', drawBorder: false }
              }
            }
          }
        });
      }

      const deviceCanvas = document.getElementById('deviceBreakdownChart');
      if (deviceCanvas) {
        const ctx = deviceCanvas.getContext('2d');
        const colors = deviceLabels.map((_, index) => accentPalette[index % accentPalette.length]);
        new window.Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: deviceLabels,
            datasets: [{
              data: deviceCounts,
              backgroundColor: colors,
              borderColor: '#ffffff',
              borderWidth: 2,
              hoverOffset: 6
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '58%',
            plugins: {
              legend: {
                position: 'bottom',
                labels: {
                  color: '#4b4b4b',
                  usePointStyle: true,
                  pointStyle: 'circle'
                }
              },
              tooltip: {
                backgroundColor: '#1f1f1f',
                titleColor: '#ffffff',
                bodyColor: '#ffffff',
                padding: 12
              }
            }
          }
        });
      }
    });
  </script>
<?php endif; ?>

<?php
render_footer(false, true);
