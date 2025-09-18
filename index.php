<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';

process_logout();

if (!is_authenticated()) {
  header('Location: login.php');
  exit;
}

$pdo = db();
$pageTotal = $certTotal = $pageWeek = $certWeek = 0;
$latestPage = $latestCert = null;
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
      <h2 class="fw-bold mb-1">Welcome back</h2>
      <p class="text-muted mb-0">Monitor access activity, review certificate searches and explore analytics in one place.</p>
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
        <h4><?= number_format($pageTotal) ?></h4>
        <p class="mb-1">Total page access logs</p>
        <small class="text-muted">Last 7 days: <?= number_format($pageWeek) ?></small>
      </div>
      <div class="stat-card">
        <h4><?= number_format($certTotal) ?></h4>
        <p class="mb-1">Total certificate search logs</p>
        <small class="text-muted">Last 7 days: <?= number_format($certWeek) ?></small>
      </div>
    </section>
    <div class="row g-3">
      <div class="col-md-6">
        <div class="box p-4 h-100">
          <h5 class="fw-semibold mb-2">Latest page access</h5>
          <p class="mb-0 text-muted"><?= $latestPage ? htmlspecialchars($latestPage, ENT_QUOTES, 'UTF-8') : 'No entries yet' ?></p>
        </div>
      </div>
      <div class="col-md-6">
        <div class="box p-4 h-100">
          <h5 class="fw-semibold mb-2">Latest certificate search</h5>
          <p class="mb-0 text-muted"><?= $latestCert ? htmlspecialchars($latestCert, ENT_QUOTES, 'UTF-8') : 'No entries yet' ?></p>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php
echo '</div>';
echo '</div>';
render_footer();
