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

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  handle_logs_ajax($pdo);
}

if (isset($_GET['export'])) {
  handle_export($pdo);
}

$type = $_GET['type'] ?? 'page';
$type = in_array($type, ['page', 'cert'], true) ? $type : 'page';
$page = max(1, (int)($_GET['page'] ?? 1));
$per = min(100, max(1, (int)($_GET['per'] ?? 10)));
$q = trim($_GET['q'] ?? '');
$start = trim($_GET['start'] ?? '');
$end = trim($_GET['end'] ?? '');

render_head('Page Access Logs');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('logs');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between mb-3 gap-3">
    <div>
      <h3 id="title"  class="title-heading">Page Access Logs</h3>
      <!-- <span class="badge-soft mt-2 d-inline-flex align-items-center gap-2"><i class="bi bi-filter"></i><span>Interactive filters enabled</span></span> -->
    </div>
    <div class="log-type-toggle btn-group" role="group" aria-label="Log type">
      <a href="#" class="btn btn-primary" data-log-type="page"><i class="bi bi-file-text me-1"></i>Page access</a>
      <a href="#" class="btn btn-primary" data-log-type="cert"><i class="bi bi-patch-check me-1"></i>Certificate searches</a>
    </div>
  </div>

  <div class="row g-3 align-items-end mb-3 mt-5" id="filters-row">
    <div class="col-lg-3">
      <label for="per" class="form-label">Rows</label>
      <div class="d-flex align-items-center gap-2">
        <select id="per" class="form-select form-control">
          <option value="10">10 / page</option>
          <option value="25">25 / page</option>
          <option value="50">50 / page</option>
          <option value="100">100 / page</option>
        </select>
      </div>
    </div>
    <div class="col-lg-3">
      <label class="form-label" for="start-date">Start date</label>
      <input type="date" class="form-control" id="start-date" value="<?= htmlspecialchars($start, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-lg-3">
      <label class="form-label" for="end-date">End date</label>
      <input type="date" class="form-control" id="end-date" value="<?= htmlspecialchars($end, ENT_QUOTES, 'UTF-8') ?>">
    </div>
    <div class="col-lg-3">
      <button class="btn btn-outline-primary" id="export-csv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
      <button class="btn btn-primary" id="open-analytics"><i class="bi bi-graph-up me-1"></i>View Analytics</button>
    </div>
  </div>

  <div class="searchbar mb-3">
    <input id="q" class="form-control form-control-lg" placeholder="Search (IP, UA, URI, referer, certificate no., status)" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
    <div id="spin" class="spinner-border spinner-border-sm text-secondary spinner"></div>
  </div>

  <div class="box">
    <div class="table-responsive">
      <table class="table table-hover">
        <thead id="thead" class="table-secondary"></thead>
        <tbody id="tbody">
          <tr>
            <td colspan="6" class="text-center py-4 text-muted">Loading…</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div class="p-3 bg-light border-top d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
      <div class="small text-muted" id="meta">Total: – | Page – of –</div>
      <nav id="pager"></nav>
    </div>
  </div>
</main>
<?php
$initialState = [
  'type' => $type,
  'page' => $page,
  'per' => $per,
  'q' => $q,
  'start' => $start,
  'end' => $end,
];
echo '</div>';
echo '</div>';
render_logs_script($initialState);
render_footer();
