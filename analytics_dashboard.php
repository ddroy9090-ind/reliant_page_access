<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/handlers.php';

process_logout();

if (!is_authenticated()) {
  header('Location: login.php');
  exit;
}

$pdo = db();

if (isset($_GET['analytics'])) {
  handle_analytics($pdo);
}

$start = trim($_GET['start'] ?? '');
$end = trim($_GET['end'] ?? '');

render_head('Analytics Dashboard');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('analytics');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
    <div>
      <h3 class="mb-0">Analytics Dashboard</h3>
      <small class="text-muted">Insights generated from page access logs within the selected date range.</small>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="page_access_logs.php"><i class="bi bi-list-ul me-1"></i>Back to Logs</a>
      <button class="btn btn-primary" id="refresh-analytics"><i class="bi bi-arrow-clockwise me-1"></i>Load analytics</button>
    </div>
  </div>

  <div class="row g-3 align-items-end mb-3">
    <div class="col-sm-6 col-md-3">
      <label class="form-label" for="start-date">Start date</label>
      <input type="date" class="form-control" id="start-date" value="<?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-sm-6 col-md-3">
      <label class="form-label" for="end-date">End date</label>
      <input type="date" class="form-control" id="end-date" value="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>">
    </div>
  </div>

  <div id="analytics-alert" class="alert alert-info d-none"></div>

  <div id="analytics-loading" class="text-center py-5 d-none">
    <div class="spinner-border text-primary" role="status"></div>
    <p class="text-muted mt-3 mb-0">Crunching the numbers…</p>
  </div>

  <div id="analytics-content" class="d-none">
    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Page Popularity</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Visits per page (Top 15)</h5>
          <div id="chart-popularity-bar" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Traffic distribution</h5>
          <div id="chart-popularity-pie" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Treemap view</h5>
          <div id="chart-popularity-treemap" class="chart-container"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Traffic Over Time</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Daily trend</h5>
          <div id="chart-traffic-line" class="chart-container large"></div>
        </div>
        <div class="chart-card">
          <h5>Page contribution over time</h5>
          <div id="chart-traffic-area" class="chart-container large"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Engagement Metrics</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Average time on page</h5>
          <div id="chart-engagement-time" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Bounce rate</h5>
          <div id="chart-engagement-bounce" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Exit pages</h5>
          <div id="chart-engagement-exit" class="chart-container"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Navigation Flow</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Sankey diagram</h5>
          <div id="chart-navigation-sankey" class="chart-container large"></div>
        </div>
        <div class="chart-card">
          <h5>Funnel progression</h5>
          <div id="chart-navigation-funnel" class="chart-container large"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Device &amp; Source Breakdown</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Traffic by device</h5>
          <div id="chart-device-donut" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Visits by source</h5>
          <div id="chart-source-stacked" class="chart-container"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Geographic Reach</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Heat map</h5>
          <div id="chart-geo-heat" class="chart-container large"></div>
        </div>
        <div class="chart-card">
          <h5>Top regions</h5>
          <div id="chart-geo-bar" class="chart-container"></div>
        </div>
      </div>
    </section>

    <section class="mb-4">
      <h4 class="fw-semibold mb-3">Advanced Insights</h4>
      <div class="analytics-grid two-col">
        <div class="chart-card">
          <h5>Correlation matrix</h5>
          <div id="chart-advanced-correlation" class="chart-container"></div>
        </div>
        <div class="chart-card">
          <h5>Engagement scatter</h5>
          <div id="chart-advanced-scatter" class="chart-container"></div>
        </div>
      </div>
    </section>
  </div>
</main>
<?php
$initialState = [
  'start' => $start,
  'end' => $end,
];
echo '</div>';
echo '</div>';
render_analytics_script($initialState);
render_footer(true);
