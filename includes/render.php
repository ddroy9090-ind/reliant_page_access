<?php

declare(strict_types=1);

function render_head(string $title): void
{
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$title}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{$csrf}">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    body{background:#eef2f7;font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;}
    .layout{min-height:100vh;}
    .sidebar{min-height:100vh;background:#0f172a;color:#cbd5e1;}
    .sidebar .brand{color:#e2e8f0;font-weight:700;letter-spacing:.4px;}
    .sidebar .nav-link{color:#cbd5e1;border-radius:10px;font-weight:500;}
    .sidebar .nav-link.active,.sidebar .nav-link:hover{background:#1c2943;color:#fff;}
    .sidebar small{color:#94a3b8;}
    .content{padding:24px;}
    .sticky-head th{position:sticky;top:0;z-index:2;}
    .box{background:#fff;border-radius:16px;box-shadow:0 16px 32px rgba(15,23,42,.08);}
    .box-header{padding:20px 24px;border-bottom:1px solid #e2e8f0;}
    .box-body{padding:24px;}
    .searchbar{position:relative;}
    .searchbar .spinner{position:absolute;right:12px;top:50%;transform:translateY(-50%);display:none;}
    .text-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,'Liberation Mono','Courier New',monospace;}
    .table td,.table th{vertical-align:middle;}
    .truncate{max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .analytics-grid{display:grid;gap:24px;}
    @media(min-width:992px){
      .analytics-grid.two-col{grid-template-columns:repeat(2,1fr);}
      .analytics-grid.three-col{grid-template-columns:repeat(3,1fr);}
    }
    .chart-card{background:#fff;border-radius:16px;box-shadow:0 16px 32px rgba(15,23,42,.08);padding:24px;}
    .chart-card h5{font-weight:600;font-size:1rem;margin-bottom:12px;}
    .chart-container{width:100%;height:320px;}
    .chart-container.large{height:380px;}
    .badge-soft{background:rgba(37,99,235,.12);color:#1d4ed8;border-radius:999px;font-size:.75rem;padding:.35rem .75rem;}
  </style>
</head>
<body>
HTML;
}

function render_login_page(?string $error): void
{
  render_head('BTSPL ADMIN PORTAL');
  $errorHtml = '';
  if ($error) {
    $escaped = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    $errorHtml = "<div class=\"alert alert-danger py-2 mb-3\">{$escaped}</div>";
  }
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  echo <<<HTML
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;background:#0d1b2a;">
  <div class="box" style="width:100%;max-width:380px;background:#1b263b;border-radius:16px;padding:32px;box-shadow:0 20px 60px rgba(0,0,0,.35);color:#e2e8f0;">
    <h3 class="text-center fw-bold mb-4">BTSPL ADMIN PORTAL</h3>
    {$errorHtml}
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required>
      </div>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>
  </div>
</div>
</body>
</html>
HTML;
}

function render_layout(): void
{
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  render_head('BTSPL ADMIN PORTAL');
  echo <<<HTML
<div class="container-fluid layout">
  <div class="row g-0">
    <aside class="col-12 col-md-3 col-lg-2 sidebar p-3">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h4 class="brand mb-0">Logs Center</h4>
        <form method="post" class="mb-0">
          <input type="hidden" name="csrf" value="{$csrf}">
          <button class="btn btn-outline-light btn-sm" name="logout" title="Logout"><i class="bi bi-box-arrow-right me-1"></i>Logout</button>
        </form>
      </div>
      <nav class="nav flex-column gap-1" id="sidebar-nav">
        <a href="#" class="nav-link active" data-view="logs" data-type="page"><i class="bi bi-file-text me-2"></i>Page Access Logs</a>
        <a href="#" class="nav-link" data-view="logs" data-type="cert"><i class="bi bi-patch-check me-2"></i>Certificate Search Logs</a>
        <hr class="border-secondary">
        <a href="#" class="nav-link" data-view="analytics"><i class="bi bi-graph-up-arrow me-2"></i>Analytics Dashboard</a>
      </nav>
      <hr class="border-secondary">
      <small>Use the search box or date filters to refine results. Analytics is based on Page Access logs.</small>
    </aside>
    <main class="col-12 col-md-9 col-lg-10 content">
      <div id="logs-view">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between mb-3 gap-3">
          <div>
            <h3 id="title" class="mb-0">Page Access Logs</h3>
            <span class="badge-soft mt-2 d-inline-flex align-items-center gap-2"><i class="bi bi-filter"></i><span>Interactive filters enabled</span></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <label for="per" class="form-label mb-0 text-muted">Rows</label>
            <select id="per" class="form-select form-select-sm" style="width:140px;">
              <option value="10" selected>10 / page</option>
              <option value="25">25 / page</option>
              <option value="50">50 / page</option>
              <option value="100">100 / page</option>
            </select>
          </div>
        </div>

        <div class="row g-3 align-items-end mb-3" id="filters-row">
          <div class="col-sm-6 col-md-3">
            <label class="form-label">Start date</label>
            <input type="date" class="form-control" id="start-date">
          </div>
          <div class="col-sm-6 col-md-3">
            <label class="form-label">End date</label>
            <input type="date" class="form-control" id="end-date">
          </div>
          <div class="col-12 col-md-6 text-md-end d-flex gap-2 justify-content-md-end">
            <button class="btn btn-outline-primary" id="export-csv"><i class="bi bi-filetype-csv me-1"></i>Export CSV</button>
            <button class="btn btn-primary" id="open-analytics"><i class="bi bi-graph-up me-1"></i>View Analytics</button>
          </div>
        </div>

        <div class="searchbar mb-3">
          <input id="q" class="form-control form-control-lg" placeholder="Search (IP, UA, URI, referer, certificate no., status)">
          <div id="spin" class="spinner-border spinner-border-sm text-secondary spinner"></div>
        </div>

        <div class="box">
          <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0">
              <thead id="thead" class="table-dark sticky-head"></thead>
              <tbody id="tbody">
                <tr><td colspan="6" class="text-center py-4 text-muted">Loading…</td></tr>
              </tbody>
            </table>
          </div>
          <div class="p-3 bg-light border-top d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
            <div class="small text-muted" id="meta">Total: – | Page – of –</div>
            <nav id="pager"></nav>
          </div>
        </div>
      </div>

      <div id="analytics-view" class="d-none">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
          <div>
            <h3 class="mb-0">Analytics Dashboard</h3>
            <small class="text-muted">Insights generated from page access logs within the selected date range.</small>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" id="refresh-analytics"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</button>
            <button class="btn btn-primary" id="back-to-logs"><i class="bi bi-list-ul me-1"></i>Back to Logs</button>
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
      </div>
    </main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.plot.ly/plotly-2.29.1.min.js"></script>
HTML;
  echo <<<'JS'
<script>
const state = {
  type: 'page',
  view: 'logs',
  page: 1,
  per: 10,
  q: '',
  start: '',
  end: '',
  debounce: null,
  analyticsKey: '',
  analyticsData: null
};

const dom = {
  q: document.getElementById('q'),
  per: document.getElementById('per'),
  thead: document.getElementById('thead'),
  tbody: document.getElementById('tbody'),
  meta: document.getElementById('meta'),
  pager: document.getElementById('pager'),
  spin: document.getElementById('spin'),
  title: document.getElementById('title'),
  start: document.getElementById('start-date'),
  end: document.getElementById('end-date'),
  exportBtn: document.getElementById('export-csv'),
  analyticsBtn: document.getElementById('open-analytics'),
  refreshAnalytics: document.getElementById('refresh-analytics'),
  backToLogs: document.getElementById('back-to-logs'),
  analyticsView: document.getElementById('analytics-view'),
  logsView: document.getElementById('logs-view'),
  analyticsAlert: document.getElementById('analytics-alert'),
  analyticsContent: document.getElementById('analytics-content'),
  analyticsLoading: document.getElementById('analytics-loading'),
  sidebarLinks: document.querySelectorAll('#sidebar-nav .nav-link')
};

const chartRefs = {};

function setActiveSidebar(link) {
  dom.sidebarLinks.forEach(el => el.classList.remove('active'));
  if (link) {
    link.classList.add('active');
  }
}

function switchView(link) {
  const view = link.dataset.view || 'logs';
  if (view === 'analytics') {
    state.view = 'analytics';
    dom.logsView.classList.add('d-none');
    dom.analyticsView.classList.remove('d-none');
    dom.analyticsBtn.classList.add('btn-outline-secondary');
    dom.analyticsBtn.classList.remove('btn-primary');
    maybeLoadAnalytics();
  } else {
    state.view = 'logs';
    const type = link.dataset.type || state.type;
    state.type = type;
    state.page = 1;
    state.q = '';
    dom.q.value = '';
    dom.logsView.classList.remove('d-none');
    dom.analyticsView.classList.add('d-none');
    dom.analyticsBtn.classList.remove('btn-outline-secondary');
    dom.analyticsBtn.classList.add('btn-primary');
    updateTitle();
    renderHead();
    loadLogs();
  }
  setActiveSidebar(link);
}

function updateTitle() {
  dom.title.textContent = state.type === 'page' ? 'Page Access Logs' : 'Certificate Search Logs';
}

function renderHead() {
  if (state.type === 'page') {
    dom.thead.innerHTML = `
      <tr>
        <th>ID</th>
        <th>IP</th>
        <th class="truncate">User Agent</th>
        <th class="truncate">Referer</th>
        <th class="truncate">URI</th>
        <th class="text-nowrap">Timestamp</th>
      </tr>`;
  } else {
    dom.thead.innerHTML = `
      <tr>
        <th>ID</th>
        <th>IP</th>
        <th class="text-mono">Certificate No</th>
        <th>Status</th>
        <th class="truncate">User Agent</th>
        <th class="text-nowrap">Timestamp</th>
      </tr>`;
  }
}

function showSpin(flag) {
  dom.spin.style.display = flag ? 'block' : 'none';
}

function encodeQuery(params) {
  return Object.entries(params)
    .filter(([, value]) => value !== undefined && value !== null && value !== '')
    .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
    .join('&');
}

function loadLogs() {
  showSpin(true);
  const params = {
    ajax: '1',
    type: state.type,
    page: state.page,
    per: state.per,
    q: state.q,
    start: state.start,
    end: state.end
  };
  fetch(`?${encodeQuery(params)}`)
    .then(r => r.json())
    .then(data => renderRows(data))
    .catch(() => {
      const cols = state.type === 'page' ? 6 : 6;
      dom.tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4 text-danger">Error loading data</td></tr>`;
      dom.meta.textContent = 'Total: – | Page – of –';
      dom.pager.innerHTML = '';
    })
    .finally(() => showSpin(false));
}

function escapeHtml(str) {
  return (str ?? '').toString().replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[m]));
}

function renderRows(data) {
  const rows = data.rows || [];
  const total = data.total || 0;
  let html = '';
  if (rows.length === 0) {
    const cols = state.type === 'page' ? 6 : 6;
    html = `<tr><td colspan="${cols}" class="text-center py-4 text-muted">No results</td></tr>`;
  } else if (state.type === 'page') {
    html = rows.map(r => `
      <tr>
        <td>${r.id}</td>
        <td>${escapeHtml(r.ip_address || '')}</td>
        <td class="truncate" title="${escapeHtml(r.user_agent || '')}">${escapeHtml(r.user_agent || '')}</td>
        <td class="truncate" title="${escapeHtml(r.referer || '')}">${escapeHtml(r.referer || '')}</td>
        <td class="truncate" title="${escapeHtml(r.request_uri || '')}">${escapeHtml(r.request_uri || '')}</td>
        <td>${escapeHtml(r.accessed_at || '')}</td>
      </tr>`).join('');
  } else {
    html = rows.map(r => {
      const status = (r.status || '').toLowerCase();
      const badge = status === 'found' ? 'success' : (status === 'not_found' ? 'warning' : (status === 'invalid' ? 'danger' : 'secondary'));
      return `
      <tr>
        <td>${r.id}</td>
        <td>${escapeHtml(r.ip_address || '')}</td>
        <td class="text-mono">${escapeHtml(r.certificate_no || '')}</td>
        <td><span class="badge bg-${badge}">${escapeHtml(r.status || '')}</span></td>
        <td class="truncate" title="${escapeHtml(r.user_agent || '')}">${escapeHtml(r.user_agent || '')}</td>
        <td>${escapeHtml(r.searched_at || '')}</td>
      </tr>`;
    }).join('');
  }
  dom.tbody.innerHTML = html;
  const pages = Math.max(1, Math.ceil(total / state.per));
  dom.meta.innerHTML = `Total: <strong>${total}</strong> | Page <strong>${state.page}</strong> of <strong>${pages}</strong>`;
  renderPager(pages);
}

function renderPager(pages) {
  if (pages <= 1) {
    dom.pager.innerHTML = '';
    return;
  }
  const win = 2;
  let html = '<ul class="pagination mb-0">';
  const prev = Math.max(1, state.page - 1);
  html += `<li class="page-item ${state.page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${prev}">Prev</a></li>`;
  html += pageBtn(1);
  if (state.page > win + 3) {
    html += dots();
  }
  const start = Math.max(2, state.page - win);
  const end = Math.min(pages - 1, state.page + win);
  for (let i = start; i <= end; i++) {
    html += pageBtn(i);
  }
  if (state.page < pages - (win + 2)) {
    html += dots();
  }
  if (pages > 1) {
    html += pageBtn(pages);
  }
  const next = Math.min(pages, state.page + 1);
  html += `<li class="page-item ${state.page === pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${next}">Next</a></li>`;
  html += '</ul>';
  dom.pager.innerHTML = html;
  dom.pager.querySelectorAll('a[data-page]').forEach(el => {
    el.addEventListener('click', e => {
      e.preventDefault();
      const target = parseInt(el.dataset.page, 10);
      if (!Number.isNaN(target)) {
        state.page = target;
        loadLogs();
      }
    });
  });
}

function pageBtn(i) {
  return `<li class="page-item ${i === state.page ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
}

function dots() {
  return '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
}

dom.per.addEventListener('change', e => {
  state.per = parseInt(e.target.value, 10) || 10;
  state.page = 1;
  loadLogs();
});

dom.q.addEventListener('input', e => {
  clearTimeout(state.debounce);
  state.q = e.target.value;
  state.debounce = setTimeout(() => {
    state.page = 1;
    loadLogs();
  }, 500);
});

function bindDatePicker(input, key) {
  input.addEventListener('change', () => {
    state[key] = input.value;
    state.page = 1;
    loadLogs();
    if (state.view === 'analytics') {
      maybeLoadAnalytics(true);
    }
  });
}

bindDatePicker(dom.start, 'start');
bindDatePicker(dom.end, 'end');

dom.exportBtn.addEventListener('click', () => {
  if (!state.start || !state.end) {
    alert('Please select both a start and end date before exporting.');
    return;
  }
  const params = {
    export: state.type,
    start: state.start,
    end: state.end
  };
  const url = `?${encodeQuery(params)}`;
  window.location.href = url;
});

dom.analyticsBtn.addEventListener('click', () => {
  const analyticsLink = Array.from(dom.sidebarLinks).find(link => link.dataset.view === 'analytics');
  if (analyticsLink) {
    switchView(analyticsLink);
  }
});

dom.refreshAnalytics.addEventListener('click', () => {
  maybeLoadAnalytics(true);
});

dom.backToLogs.addEventListener('click', () => {
  const logLink = Array.from(dom.sidebarLinks).find(link => link.dataset.view === 'logs' && link.dataset.type === state.type);
  if (logLink) {
    switchView(logLink);
  }
});

document.querySelectorAll('#sidebar-nav .nav-link').forEach(link => {
  link.addEventListener('click', e => {
    e.preventDefault();
    switchView(link);
  });
});

document.getElementById('open-analytics').addEventListener('click', () => {
  const analyticsLink = Array.from(dom.sidebarLinks).find(link => link.dataset.view === 'analytics');
  if (analyticsLink) {
    switchView(analyticsLink);
  }
});

document.getElementById('back-to-logs').addEventListener('click', () => {
  const logLink = Array.from(dom.sidebarLinks).find(link => link.dataset.view === 'logs' && link.dataset.type === state.type);
  if (logLink) {
    switchView(logLink);
  }
});

function destroyChart(id) {
  if (chartRefs[id]) {
    Plotly.purge(id);
    delete chartRefs[id];
  }
}

function maybeLoadAnalytics(force = false) {
  const params = {
    analytics: '1',
    start: state.start,
    end: state.end
  };
  const key = JSON.stringify(params);
  if (!force && key === state.analyticsKey && state.analyticsData) {
    renderAnalytics(state.analyticsData);
    return;
  }
  state.analyticsKey = key;
  dom.analyticsAlert.classList.add('d-none');
  dom.analyticsContent.classList.add('d-none');
  dom.analyticsLoading.classList.remove('d-none');
  fetch(`?${encodeQuery(params)}`)
    .then(r => r.json())
    .then(data => {
      state.analyticsData = data;
      renderAnalytics(data);
    })
    .catch(() => {
      dom.analyticsAlert.textContent = 'Failed to load analytics data.';
      dom.analyticsAlert.classList.remove('d-none');
    })
    .finally(() => {
      dom.analyticsLoading.classList.add('d-none');
    });
}

function renderAnalytics(data) {
  if (data.error) {
    dom.analyticsAlert.textContent = data.error;
    dom.analyticsAlert.classList.remove('d-none');
    dom.analyticsContent.classList.add('d-none');
    return;
  }
  dom.analyticsAlert.classList.add('d-none');
  dom.analyticsContent.classList.remove('d-none');
  drawPopularityCharts(data.popularity || {});
  drawTrafficCharts(data.traffic || {});
  drawEngagementCharts(data.engagement || {});
  drawNavigationCharts(data.navigation || {});
  drawDeviceSourceCharts(data.devicesSources || {});
  drawGeographyCharts(data.geography || {});
  drawAdvancedCharts(data.advanced || {});
}

function drawPopularityCharts(popularity) {
  const pages = popularity.pages || [];
  const labels = pages.map(p => p.page);
  const visits = pages.map(p => p.visits);
  destroyChart('chart-popularity-bar');
  Plotly.newPlot('chart-popularity-bar', [{
    type: 'bar',
    x: labels,
    y: visits,
    marker: {color: '#2563eb'}
  }], {
    margin: {t: 10, r: 20, l: 50, b: 80},
    xaxis: {title: 'Page'},
    yaxis: {title: 'Visits'}
  }, {displayModeBar: false});

  destroyChart('chart-popularity-pie');
  Plotly.newPlot('chart-popularity-pie', [{
    type: 'pie',
    labels,
    values: visits,
    hole: 0.35,
    textinfo: 'label+percent'
  }], {
    margin: {t: 10, b: 10}
  }, {displayModeBar: false});

  destroyChart('chart-popularity-treemap');
  Plotly.newPlot('chart-popularity-treemap', [{
    type: 'treemap',
    labels,
    parents: labels.map(() => ''),
    values: visits,
    textinfo: 'label+value'
  }], {
    margin: {t: 10, l: 0, r: 0, b: 0}
  }, {displayModeBar: false});
}

function drawTrafficCharts(traffic) {
  const dailyTotals = traffic.dailyTotals || [];
  destroyChart('chart-traffic-line');
  Plotly.newPlot('chart-traffic-line', [{
    x: dailyTotals.map(d => d.date),
    y: dailyTotals.map(d => d.total),
    type: 'scatter',
    mode: 'lines+markers',
    line: {color: '#1d4ed8', width: 3}
  }], {
    margin: {t: 10, r: 20, l: 60, b: 60},
    xaxis: {title: 'Date'},
    yaxis: {title: 'Visits'}
  }, {displayModeBar: false});

  const perPage = traffic.perPage || [];
  destroyChart('chart-traffic-area');
  const areaData = perPage.map(page => ({
    x: page.series.map(p => p.date),
    y: page.series.map(p => p.visits),
    stackgroup: 'one',
    name: page.page,
    mode: 'lines',
    line: {width: 0.5}
  }));
  Plotly.newPlot('chart-traffic-area', areaData, {
    margin: {t: 10, r: 20, l: 60, b: 60},
    xaxis: {title: 'Date'},
    yaxis: {title: 'Visits'}
  }, {displayModeBar: false});
}

function drawEngagementCharts(engagement) {
  const avgTime = engagement.avgTime || [];
  destroyChart('chart-engagement-time');
  Plotly.newPlot('chart-engagement-time', [{
    type: 'bar',
    x: avgTime.map(i => i.page),
    y: avgTime.map(i => Math.round(i.seconds)),
    marker: {color: '#22c55e'}
  }], {
    margin: {t: 10, r: 20, l: 50, b: 80},
    yaxis: {title: 'Seconds'}
  }, {displayModeBar: false});

  const bounce = engagement.bounceRate || [];
  destroyChart('chart-engagement-bounce');
  Plotly.newPlot('chart-engagement-bounce', [{
    type: 'bar',
    x: bounce.map(i => i.page),
    y: bounce.map(i => Math.round(i.rate * 100)),
    marker: {color: '#f97316'}
  }], {
    margin: {t: 10, r: 20, l: 50, b: 80},
    yaxis: {title: 'Bounce %'}
  }, {displayModeBar: false});

  const exits = engagement.exitPages || [];
  destroyChart('chart-engagement-exit');
  Plotly.newPlot('chart-engagement-exit', [{
    type: 'bar',
    x: exits.map(i => i.count),
    y: exits.map(i => i.page),
    orientation: 'h',
    marker: {color: '#a855f7'}
  }], {
    margin: {t: 10, r: 20, l: 160, b: 40},
    xaxis: {title: 'Sessions ending here'}
  }, {displayModeBar: false});
}

function drawNavigationCharts(navigation) {
  const sankey = navigation.sankey || {nodes: [], links: []};
  destroyChart('chart-navigation-sankey');
  if ((sankey.nodes || []).length && (sankey.links || []).length) {
    Plotly.newPlot('chart-navigation-sankey', [{
      type: 'sankey',
      node: {label: sankey.nodes},
      link: {
        source: sankey.links.map(l => l.source),
        target: sankey.links.map(l => l.target),
        value: sankey.links.map(l => l.value)
      }
    }], {
      margin: {t: 10, l: 30, r: 30, b: 10}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-navigation-sankey').innerHTML = '<p class="text-muted">Not enough navigation data.</p>';
  }

  const funnel = navigation.funnel || {steps: []};
  destroyChart('chart-navigation-funnel');
  if ((funnel.steps || []).length) {
    Plotly.newPlot('chart-navigation-funnel', [{
      type: 'funnel',
      y: funnel.steps.map(s => s.label),
      x: funnel.steps.map(s => s.value)
    }], {
      margin: {t: 10, l: 150, r: 40, b: 40}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-navigation-funnel').innerHTML = '<p class="text-muted">Not enough funnel data.</p>';
  }
}

function drawDeviceSourceCharts(devicesSources) {
  const devices = devicesSources.devices || [];
  destroyChart('chart-device-donut');
  Plotly.newPlot('chart-device-donut', [{
    type: 'pie',
    hole: 0.45,
    labels: devices.map(d => d.device),
    values: devices.map(d => d.visits),
    textinfo: 'label+percent'
  }], {
    margin: {t: 10, b: 10}
  }, {displayModeBar: false});

  const sources = devicesSources.sourcesByPage || [];
  destroyChart('chart-source-stacked');
  const pages = sources.map(s => s.page);
  const categories = [...new Set(sources.flatMap(s => Object.keys(s.breakdown || {})))];
  const traces = categories.map(cat => ({
    type: 'bar',
    name: cat,
    x: pages,
    y: sources.map(s => (s.breakdown && s.breakdown[cat]) || 0)
  }));
  Plotly.newPlot('chart-source-stacked', traces, {
    barmode: 'stack',
    margin: {t: 10, r: 20, l: 50, b: 80},
    yaxis: {title: 'Visits'}
  }, {displayModeBar: false});
}

function drawGeographyCharts(geography) {
  const countries = geography.countries || [];
  destroyChart('chart-geo-heat');
  if (countries.length) {
    Plotly.newPlot('chart-geo-heat', [{
      type: 'choropleth',
      locationmode: 'country names',
      locations: countries.map(c => c.country),
      z: countries.map(c => c.count),
      colorscale: 'Blues'
    }], {
      margin: {t: 10, r: 0, l: 0, b: 0}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-geo-heat').innerHTML = '<p class="text-muted">Geographic data not available.</p>';
  }

  const regions = geography.regions || [];
  destroyChart('chart-geo-bar');
  if (regions.length) {
    Plotly.newPlot('chart-geo-bar', [{
      type: 'bar',
      x: regions.map(r => r.region),
      y: regions.map(r => r.count),
      marker: {color: '#3b82f6'}
    }], {
      margin: {t: 10, r: 20, l: 50, b: 80},
      yaxis: {title: 'Visits'}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-geo-bar').innerHTML = '<p class="text-muted">Regional insights not available.</p>';
  }
}

function drawAdvancedCharts(advanced) {
  const matrix = advanced.correlationMatrix || {labels: [], matrix: []};
  destroyChart('chart-advanced-correlation');
  if (matrix.labels.length) {
    Plotly.newPlot('chart-advanced-correlation', [{
      type: 'heatmap',
      x: matrix.labels,
      y: matrix.labels,
      z: matrix.matrix,
      colorscale: 'RdBu',
      reversescale: true,
      zmin: -1,
      zmax: 1
    }], {
      margin: {t: 10, r: 20, l: 80, b: 80}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-advanced-correlation').innerHTML = '<p class="text-muted">Not enough data for correlation analysis.</p>';
  }

  const scatter = advanced.scatter || [];
  destroyChart('chart-advanced-scatter');
  if (scatter.length) {
    Plotly.newPlot('chart-advanced-scatter', [{
      type: 'scatter',
      mode: 'markers',
      x: scatter.map(p => Math.round(p.bounceRate * 100)),
      y: scatter.map(p => Math.round(p.avgTime)),
      text: scatter.map(p => p.page),
      marker: {
        size: scatter.map(p => Math.max(10, Math.sqrt(p.visits))),
        color: '#ec4899',
        opacity: 0.8
      }
    }], {
      margin: {t: 10, r: 20, l: 60, b: 60},
      xaxis: {title: 'Bounce %'},
      yaxis: {title: 'Average Time (s)'}
    }, {displayModeBar: false});
  } else {
    document.getElementById('chart-advanced-scatter').innerHTML = '<p class="text-muted">Scatter insight unavailable.</p>';
  }
}

function init() {
  renderHead();
  loadLogs();
}

init();
</script>
JS;
}
