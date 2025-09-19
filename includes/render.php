<?php

declare(strict_types=1);

function render_head(string $title, string $bodyClass = ''): void
{
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  $bodyAttr = $bodyClass !== ''
    ? ' class="' . htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') . '"'
    : '';
  echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$title}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="csrf-token" content="{$csrf}">
  <link rel="shortcut icon" href="assets/images/favicon.png" type="image/x-icon">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/custom.css">
 
</head>
<body{$bodyAttr}>
HTML;
}



function render_sidebar(string $active): void
{
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  $items = [
    'home'      => ['href' => 'index.php', 'icon' => 'bi-house',     'label' => 'Dashboard'],
    'logs'      => ['href' => 'page_access_logs.php', 'icon' => 'bi-universal-access-circle',       'label' => 'Page Access Logs'],
    'analytics' => ['href' => 'analytics_dashboard.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Analytics Dashboard'],
    'contac-form' => ['href' => 'contact_form_submissions.php', 'icon' => 'bi-person-rolodex', 'label' => 'Contact Submisiions'],
  ];

  echo '<aside class="col-12 col-md-3 col-lg-2 sidebar p-3">';
  echo '<div class="mb-2 pb-2">';
  echo '<img src="assets/images/logo.webp" alt="" class="logo">';
  echo '<form method="post" class="mb-0 logoutform">';
  echo '<input type="hidden" name="csrf" value="' . $csrf . '">';
  echo '<button class="btn btn-outline-light btn-sm" name="logout" title="Logout">';
  echo '<i class="bi bi-box-arrow-right me-1"></i>Logout';
  echo '</button>';
  echo '</form>';
  echo '</div>';

  echo '<nav class="nav flex-column gap-1">';
  foreach ($items as $key => $item) {
    $isActive = $key === $active ? ' active' : '';
    $icon = htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8');
    $href = htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
    printf('<a href="%s" class="nav-link%s"><i class="bi %s me-2"></i>%s</a>', $href, $isActive, $icon, $label);
  }
  echo '</nav>';
  echo '';
  echo '';
  echo '</aside>';
}



function render_footer(bool $includeECharts = false, bool $includeChartJs = false, bool $includeChartJsExtras = false): void
{
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';

  if ($includeECharts) {
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js\"></script>";
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/echarts@5/map/js/world.js\"></script>";
  }

  if ($includeChartJs) {
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js\"></script>";
    if ($includeChartJsExtras) {
      echo "\n<script src=\"https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@2/dist/chartjs-chart-matrix.min.js\"></script>";
    }
  }



  echo '</body></html>';
}


function render_login_page(?string $error): void
{
  render_head('Reliant Monitor Portal', 'login-body');
  $errorHtml = '';
  if ($error) {
    $escaped = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    $errorHtml = "<div class=\"alert alert-danger py-2 mb-3\">{$escaped}</div>";
  }
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  echo <<<HTML
<div class="login-wrapper d-flex align-items-center justify-content-center">
  <div class="login-card box text-light">
    <div class="login-logo">
        <img src="assets/images/logo.png" alt="">
    </div>
    <h3 class="text-center mb-4">Reliant Monitor Portal</h3>
    {$errorHtml}
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="{$csrf}">
      <div class="mb-3 position-relative login-icon">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" required autofocus>
        <i class="bi bi-person"></i>
      </div>
      <div class="mb-3 position-relative login-icon">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" required>
        <i class="bi bi-lock"></i>
      </div>
      <button class="btn btn-primary w-100">Sign in</button>
    </form>

  </div>
</div>
HTML;
  render_footer();
}

function render_logs_script(array $initialState): void
{
  $stateJson = json_encode($initialState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $script = <<<'JS'
<script>
(() => {
  const endpoint = 'page_access_logs.php';
  const state = Object.assign({
    type: 'page',
    page: 1,
    per: 10,
    q: '',
    start: '',
    end: '',
    debounce: null
  }, __STATE_JSON__);

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
    typeButtons: document.querySelectorAll('[data-log-type]')
  };

  if (dom.q) dom.q.value = state.q;
  if (dom.per) dom.per.value = String(state.per);
  if (dom.start) dom.start.value = state.start;
  if (dom.end) dom.end.value = state.end;

  dom.typeButtons.forEach(btn => {
    btn.classList.toggle('active', btn.dataset.logType === state.type);
    btn.addEventListener('click', event => {
      event.preventDefault();
      const { logType } = btn.dataset;
      if (!logType || logType === state.type) {
        return;
      }
      state.type = logType;
      state.page = 1;
      state.q = '';
      state.start = '';
      state.end = '';
      if (dom.q) dom.q.value = '';
      if (dom.start) dom.start.value = '';
      if (dom.end) dom.end.value = '';
      updateTitle();
      renderTableHead();
      loadLogs();
      dom.typeButtons.forEach(inner => inner.classList.toggle('active', inner.dataset.logType === state.type));
    });
  });

  if (dom.per) {
    dom.per.addEventListener('change', () => {
      state.per = parseInt(dom.per.value, 10) || 10;
      state.page = 1;
      loadLogs();
    });
  }

  if (dom.q) {
    dom.q.addEventListener('input', () => {
      if (state.debounce) {
        clearTimeout(state.debounce);
      }
      state.debounce = setTimeout(() => {
        state.q = dom.q.value.trim();
        state.page = 1;
        loadLogs();
      }, 350);
    });
  }

  if (dom.start) {
    dom.start.addEventListener('change', () => {
      state.start = dom.start.value;
      state.page = 1;
      loadLogs();
    });
  }

  if (dom.end) {
    dom.end.addEventListener('change', () => {
      state.end = dom.end.value;
      state.page = 1;
      loadLogs();
    });
  }

  if (dom.exportBtn) {
    dom.exportBtn.addEventListener('click', event => {
      event.preventDefault();
      const params = {
        export: state.type,
        start: state.start,
        end: state.end
      };
      const qs = encodeQuery(params);
      window.location.href = `${endpoint}?${qs}`;
    });
  }

  if (dom.analyticsBtn) {
    dom.analyticsBtn.addEventListener('click', event => {
      event.preventDefault();
      const params = {};
      if (state.start) params.start = state.start;
      if (state.end) params.end = state.end;
      const qs = encodeQuery(params);
      const target = qs ? `analytics_dashboard.php?${qs}` : 'analytics_dashboard.php';
      window.location.href = target;
    });
  }

  function updateTitle() {
    if (!dom.title) return;
    dom.title.textContent = state.type === 'page' ? 'Page Access Logs' : 'Certificate Search Logs';
  }

  function renderTableHead() {
    if (!dom.thead) return;
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
    if (dom.spin) {
      dom.spin.style.display = flag ? 'block' : 'none';
    }
  }

  function encodeQuery(params) {
    return Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null && value !== '')
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');
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
    const qs = encodeQuery(params);
    if (window.history && window.history.replaceState) {
      const historyParams = Object.assign({}, params);
      delete historyParams.ajax;
      window.history.replaceState(null, '', `?${encodeQuery(historyParams)}`);
    }
    fetch(`${endpoint}?${qs}`)
      .then(r => {
        if (!r.ok) throw new Error('Failed request');
        return r.json();
      })
      .then(data => renderRows(data))
      .catch(() => {
        const cols = 6;
        if (dom.tbody) {
          dom.tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center py-4 text-danger">Error loading data</td></tr>`;
        }
        if (dom.meta) {
          dom.meta.textContent = 'Total: – | Page – of –';
        }
        if (dom.pager) {
          dom.pager.innerHTML = '';
        }
      })
      .finally(() => showSpin(false));
  }

  function renderRows(data) {
    const rows = data.rows || [];
    const total = data.total || 0;
    let html = '';
    if (rows.length === 0) {
      html = '<tr><td colspan="6" class="text-center py-4 text-muted">No results</td></tr>';
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
    if (dom.tbody) {
      dom.tbody.innerHTML = html;
    }
    const pages = Math.max(1, Math.ceil(total / state.per));
    if (dom.meta) {
      dom.meta.innerHTML = `Total: <strong>${total}</strong> | Page <strong>${state.page}</strong> of <strong>${pages}</strong>`;
    }
    renderPager(pages);
  }

  function renderPager(pages) {
    if (!dom.pager) return;
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
        if (!Number.isNaN(target) && target !== state.page) {
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

  updateTitle();
  renderTableHead();
  loadLogs();
})();
</script>
JS;
  echo str_replace('__STATE_JSON__', $stateJson, $script);
}

function render_analytics_script(array $initialState): void
{
  $stateJson = json_encode($initialState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  $script = <<<'JS'
<script>
(() => {
  const endpoint = 'analytics_dashboard.php';
  const state = Object.assign({
    start: '',
    end: '',
    loading: false
  }, __STATE_JSON__);

  const palette = [
    '#2563eb', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
    '#14b8a6', '#f97316', '#6366f1', '#0ea5e9', '#22d3ee'
  ];
  const charts = new Map();
  let resizeHandlerAttached = false;
  let defaultsRegistered = false;

  const matrixValuePlugin = {
    id: 'matrixValuePlugin',
    afterDatasetsDraw(chart) {
      const options = chart.options?.plugins?.matrixValue || {};
      if (options.display === false) {
        return;
      }
      const dataset = chart.data.datasets?.[0];
      if (!dataset) {
        return;
      }
      const meta = chart.getDatasetMeta(0);
      const ctx = chart.ctx;
      const fontSize = options.fontSize || 12;
      const fontFamily = options.fontFamily || "Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
      const decimals = typeof options.decimals === 'number' ? options.decimals : 1;
      const color = options.color || '#0f172a';
      ctx.save();
      ctx.fillStyle = color;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = `${fontSize}px ${fontFamily}`;
      meta.data.forEach((element, index) => {
        const raw = dataset.data?.[index];
        if (!raw || typeof raw.v !== 'number') {
          return;
        }
        const text = raw.v.toFixed(decimals);
        ctx.fillText(text, element.x, element.y);
      });
      ctx.restore();
    }
  };

  const dom = {
    start: document.getElementById('start-date'),
    end: document.getElementById('end-date'),
    loadBtn: document.getElementById('refresh-analytics'),
    alert: document.getElementById('analytics-alert'),
    content: document.getElementById('analytics-content'),
    loading: document.getElementById('analytics-loading')
  };

  if (dom.start) dom.start.value = state.start;
  if (dom.end) dom.end.value = state.end;

  function encodeQuery(params) {
    return Object.entries(params)
      .filter(([, value]) => value !== undefined && value !== null && value !== '')
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');
  }

  function showAlert(message, type = 'info') {
    if (!dom.alert) return;
    dom.alert.classList.remove('d-none', 'alert-info', 'alert-danger', 'alert-success');
    dom.alert.classList.add(`alert-${type}`);
    dom.alert.textContent = message;
  }

  function hideAlert() {
    if (!dom.alert) return;
    dom.alert.classList.add('d-none');
  }

  function registerNamespace(ns) {
    if (!window.Chart || !ns) return;
    Object.values(ns).forEach(entry => {
      if (!entry) return;
      try {
        window.Chart.register(entry);
      } catch (err) {
        /* ignore */
      }
    });
  }

  function registerChartDefaults() {
    if (defaultsRegistered || !window.Chart) {
      return;
    }
    defaultsRegistered = true;
    const Chart = window.Chart;
    Chart.defaults.color = '#1f2937';
    Chart.defaults.font.family = "Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 10;
    Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
    Chart.defaults.plugins.tooltip.titleColor = '#ffffff';
    Chart.defaults.plugins.tooltip.bodyColor = '#ffffff';
    Chart.defaults.plugins.tooltip.padding = 12;
    Chart.defaults.plugins.tooltip.cornerRadius = 6;
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.responsive = true;

    registerNamespace(window.ChartMatrix);
  }

  function ensureChartJsReady(timeout = 10000) {
    if (window.Chart) {
      registerChartDefaults();
      return Promise.resolve(window.Chart);
    }
    return new Promise((resolve, reject) => {
      const start = Date.now();
      const timer = setInterval(() => {
        if (window.Chart) {
          clearInterval(timer);
          registerChartDefaults();
          resolve(window.Chart);
          return;
        }
        if (Date.now() - start >= timeout) {
          clearInterval(timer);
          reject(new Error('Chart.js failed to load'));
        }
      }, 50);
    });
  }

  function getContainer(id) {
    return document.getElementById(id);
  }

  function getCanvas(id) {
    const container = getContainer(id);
    if (!container) {
      return null;
    }
    let canvas = container.querySelector('canvas');
    if (!canvas) {
      canvas = document.createElement('canvas');
      container.innerHTML = '';
      container.appendChild(canvas);
    }
    return canvas;
  }

  function destroyChart(id) {
    const chart = charts.get(id);
    if (chart && typeof chart.destroy === 'function') {
      chart.destroy();
    }
    charts.delete(id);
  }

  function setNoDataMessage(id, message) {
    destroyChart(id);
    const container = getContainer(id);
    if (container) {
      container.innerHTML = `<p class="text-muted text-center small mb-0">${message}</p>`;
    }
  }

  function hasChartType(type) {
    const Chart = window.Chart;
    if (!Chart || !Chart.registry || typeof Chart.registry.getController !== 'function') {
      return false;
    }
    try {
      return !!Chart.registry.getController(type);
    } catch (err) {
      return false;
    }
  }

  function initChart(id, config) {
    if (!window.Chart) {
      return null;
    }
    destroyChart(id);
    const canvas = getCanvas(id);
    if (!canvas) {
      return null;
    }
    const ctx = canvas.getContext('2d');
    const chart = new window.Chart(ctx, config);
    charts.set(id, chart);
    if (!resizeHandlerAttached) {
      window.addEventListener('resize', resizeCharts);
      resizeHandlerAttached = true;
    }
    return chart;
  }

  function resizeCharts() {
    charts.forEach(chart => {
      if (chart && typeof chart.resize === 'function') {
        chart.resize();
      }
    });
  }

  function setLoading(flag) {
    state.loading = flag;
    if (dom.loading) {
      dom.loading.classList.toggle('d-none', !flag);
    }
    if (dom.content) {
      dom.content.classList.toggle('d-none', flag);
    }
    if (dom.loadBtn) {
      dom.loadBtn.disabled = flag;
    }
    if (!flag) {
      setTimeout(resizeCharts, 100);
    }
  }

  function hexToRgba(hex, alpha) {
    const cleaned = hex.replace('#', '');
    if (cleaned.length !== 6) {
      return hex;
    }
    const bigint = parseInt(cleaned, 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  }

  function mixColors(colorA, colorB, amount) {
    const parse = color => {
      const hex = color.replace('#', '');
      return [
        parseInt(hex.substring(0, 2), 16),
        parseInt(hex.substring(2, 4), 16),
        parseInt(hex.substring(4, 6), 16)
      ];
    };
    const [r1, g1, b1] = parse(colorA);
    const [r2, g2, b2] = parse(colorB);
    const blend = (a, b) => Math.round(a + (b - a) * amount);
    return `rgb(${blend(r1, r2)}, ${blend(g1, g2)}, ${blend(b1, b2)})`;
  }

  function loadAnalytics() {
    const params = { analytics: '1', start: state.start, end: state.end };
    const qs = encodeQuery(params);
    if (window.history && window.history.replaceState) {
      const historyParams = { start: state.start, end: state.end };
      const historyQs = encodeQuery(historyParams);
      window.history.replaceState(null, '', historyQs ? `?${historyQs}` : '');
    }
    hideAlert();
    setLoading(true);
    fetch(`${endpoint}?${qs}`)
      .then(r => {
        if (!r.ok) throw new Error('Failed');
        return r.json();
      })
      .then(data => {
        if (data.error) {
          showAlert(data.error, 'danger');
          return;
        }
        return ensureChartJsReady().then(() => {
          try {
            renderAnalytics(data);
          } catch (err) {
            console.error('[ANALYTICS_RENDER]', err);
            showAlert('Unable to render analytics data. Please check the console for details.', 'danger');
          }
        });
      })
      .catch(err => {
        console.error('[ANALYTICS_LOAD]', err);
        showAlert('Unable to load analytics data. Please try again later.', 'danger');
      })
      .finally(() => setLoading(false));
  }

  function renderAnalytics(data) {
    if (!dom.content) return;
    dom.content.classList.remove('d-none');
    drawPopularityCharts(data.popularity || {});
    drawTrafficCharts(data.traffic || {});
    drawEngagementCharts(data.engagement || {});
    drawNavigationCharts(data.navigation || {});
    drawDeviceCharts(data.devicesSources || {});
    drawGeoCharts(data.geography || {});
    drawAdvancedCharts(data.advanced || {});
  }

  function drawPopularityCharts(popularity) {
    const pages = Array.isArray(popularity.pages) ? popularity.pages : [];
    const labels = pages.map(p => p.label || p.page || '');
    const visits = pages.map(p => Number(p.value || p.visits || 0));
    const totalVisits = visits.reduce((sum, value) => sum + value, 0);

    if (!labels.length) {
      setNoDataMessage('chart-popularity-bar', 'No page view data available.');
      setNoDataMessage('chart-popularity-pie', 'No traffic distribution data available.');
      setNoDataMessage('chart-popularity-treemap', 'No cumulative distribution data available.');
      return;
    }

    initChart('chart-popularity-bar', {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Visits',
          data: visits,
          backgroundColor: '#1d4ed8',
          borderRadius: 8,
          maxBarThickness: 24
        }]
      },
      options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        scales: {
          x: {
            beginAtZero: true,
            title: { display: true, text: 'Visits' },
            grid: { color: 'rgba(37, 99, 235, 0.08)' }
          },
          y: {
            ticks: { autoSkip: false },
            grid: { display: false }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => `${context.formattedValue} visits`
            }
          }
        }
      }
    });

    initChart('chart-popularity-pie', {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: visits,
          backgroundColor: labels.map((_, idx) => palette[idx % palette.length]),
          borderWidth: 0
        }]
      },
      options: {
        cutout: '45%',
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: context => {
                const value = typeof context.parsed === 'number' ? context.parsed : Number(context.parsed || 0);
                const percent = totalVisits ? ((value / totalVisits) * 100).toFixed(1) : '0.0';
                return `${context.label}: ${context.formattedValue} (${percent}%)`;
              }
            }
          }
        }
      }
    });

    const cumulativeShare = [];
    let runningTotal = 0;
    visits.forEach(value => {
      runningTotal += value;
      const percent = totalVisits ? (runningTotal / totalVisits) * 100 : 0;
      cumulativeShare.push(Number(percent.toFixed(1)));
    });

    initChart('chart-popularity-treemap', {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Cumulative share (%)',
          data: cumulativeShare,
          tension: 0.35,
          fill: true,
          borderColor: '#14b8a6',
          backgroundColor: hexToRgba('#14b8a6', 0.18),
          pointRadius: 3,
          pointHoverRadius: 5
        }]
      },
      options: {
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: context => {
                const idx = context.dataIndex ?? 0;
                const visitCount = visits[idx] ?? 0;
                const visitPercent = totalVisits ? ((visitCount / totalVisits) * 100).toFixed(1) : '0.0';
                return `${context.formattedValue}% cumulative • ${visitCount} visits (${visitPercent}%)`;
              }
            }
          }
        },
        scales: {
          x: { ticks: { autoSkip: false } },
          y: {
            beginAtZero: true,
            max: 100,
            title: { display: true, text: 'Cumulative share (%)' }
          }
        }
      }
    });
  }

  function drawTrafficCharts(traffic) {
    const dailyTotals = Array.isArray(traffic.dailyTotals) ? traffic.dailyTotals : [];
    const perPage = Array.isArray(traffic.perPage) ? traffic.perPage : [];
    const lineLabels = dailyTotals.map(d => d.date);
    const lineValues = dailyTotals.map(d => Number(d.total || 0));

    if (!dailyTotals.length) {
      setNoDataMessage('chart-traffic-line', 'No traffic data available.');
    } else {
      initChart('chart-traffic-line', {
        type: 'line',
        data: {
          labels: lineLabels,
          datasets: [{
            label: 'Visits',
            data: lineValues,
            tension: 0.4,
            fill: true,
            borderColor: '#2563eb',
            backgroundColor: hexToRgba('#2563eb', 0.15),
            pointRadius: 3,
            pointHoverRadius: 5
          }]
        },
        options: {
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { display: false } },
          scales: {
            x: { title: { display: true, text: 'Date' } },
            y: { beginAtZero: true, title: { display: true, text: 'Visits' } }
          }
        }
      });
    }

    const dates = lineLabels.length
      ? lineLabels
      : Array.from(new Set(perPage.flatMap(page => page.series?.map(point => point.date) || [])));
    if (!perPage.length || !dates.length) {
      setNoDataMessage('chart-traffic-area', 'Not enough per-page traffic data.');
    } else {
      const areaSeries = perPage.map((page, index) => {
        const color = palette[index % palette.length];
        const data = dates.map(date => {
          const point = (page.series || []).find(item => item.date === date);
          return Number(point ? point.visits : 0);
        });
        return {
          label: page.page,
          data,
          fill: true,
          tension: 0.35,
          stack: 'traffic',
          borderColor: color,
          backgroundColor: hexToRgba(color, 0.35),
          pointRadius: 0,
          pointHoverRadius: 3
        };
      });

      initChart('chart-traffic-area', {
        type: 'line',
        data: { labels: dates, datasets: areaSeries },
        options: {
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { position: 'bottom' } },
          scales: {
            x: { title: { display: true, text: 'Date' } },
            y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Visits' } }
          }
        }
      });
    }
  }

  function drawEngagementCharts(engagement) {
    const avgTime = Array.isArray(engagement.avgTime) ? engagement.avgTime : [];
    if (!avgTime.length) {
      setNoDataMessage('chart-engagement-time', 'No average time data available.');
    } else {
      initChart('chart-engagement-time', {
        type: 'bar',
        data: {
          labels: avgTime.map(i => i.page || i.label),
          datasets: [{
            label: 'Seconds',
            data: avgTime.map(i => Math.round(Number(i.seconds || i.value || 0))),
            backgroundColor: '#22c55e',
            borderRadius: 10,
            maxBarThickness: 36
          }]
        },
        options: {
          plugins: { legend: { display: false } },
          scales: {
            x: { ticks: { autoSkip: false }, grid: { display: false } },
            y: { beginAtZero: true, title: { display: true, text: 'Seconds' } }
          }
        }
      });
    }

    const bounce = Array.isArray(engagement.bounceRate) ? engagement.bounceRate : [];
    if (!bounce.length) {
      setNoDataMessage('chart-engagement-bounce', 'No bounce rate data available.');
    } else {
      initChart('chart-engagement-bounce', {
        type: 'bar',
        data: {
          labels: bounce.map(i => i.page || i.label),
          datasets: [{
            label: 'Bounce %',
            data: bounce.map(i => Math.round(Number(i.rate || i.value || 0) * 100)),
            backgroundColor: '#f97316',
            borderRadius: 10,
            maxBarThickness: 36
          }]
        },
        options: {
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: { label: context => `${context.formattedValue}%` }
            }
          },
          scales: {
            x: { ticks: { autoSkip: false }, grid: { display: false } },
            y: { beginAtZero: true, title: { display: true, text: 'Bounce %' } }
          }
        }
      });
    }

    const exits = Array.isArray(engagement.exitPages) ? engagement.exitPages : [];
    if (!exits.length) {
      setNoDataMessage('chart-engagement-exit', 'No exit page data available.');
    } else {
      initChart('chart-engagement-exit', {
        type: 'bar',
        data: {
          labels: exits.map(i => i.page || i.label),
          datasets: [{
            label: 'Sessions ending here',
            data: exits.map(i => Number(i.count || i.value || 0)),
            backgroundColor: '#8b5cf6',
            borderRadius: 10,
            maxBarThickness: 24
          }]
        },
        options: {
          indexAxis: 'y',
          plugins: { legend: { display: false } },
          scales: {
            x: { beginAtZero: true, title: { display: true, text: 'Sessions' } },
            y: { ticks: { autoSkip: false }, grid: { display: false } }
          }
        }
      });
    }
  }

  function drawNavigationCharts(navigation) {
    const sankey = navigation.sankey || { nodes: [], links: [] };
    const sankeyNodes = Array.isArray(sankey.nodes) ? sankey.nodes : [];
    const sankeyLinks = Array.isArray(sankey.links) ? sankey.links : [];

    const nodeLabels = sankeyNodes.map(node => (typeof node === 'string' ? node : String(node ?? '')));
    const sankeyData = sankeyLinks.map(link => {
      const flow = Number(link.value || 0);
      const source = typeof link.source === 'number' ? nodeLabels[link.source] : link.source;
      const target = typeof link.target === 'number' ? nodeLabels[link.target] : link.target;
      return { from: source, to: target, flow };
    }).filter(item => item.flow > 0 && item.from && item.to);

    if (!sankeyData.length) {
      setNoDataMessage('chart-navigation-sankey', 'Not enough navigation data.');
    } else {
      const topFlows = sankeyData
        .slice()
        .sort((a, b) => b.flow - a.flow)
        .slice(0, 12);
      const flowLabels = topFlows.map(item => `${item.from} → ${item.to}`);
      const flowValues = topFlows.map(item => item.flow);

      initChart('chart-navigation-sankey', {
        type: 'bar',
        data: {
          labels: flowLabels,
          datasets: [{
            label: 'Sessions',
            data: flowValues,
            backgroundColor: flowLabels.map((_, idx) => palette[idx % palette.length]),
            borderRadius: 8,
            maxBarThickness: 28
          }]
        },
        options: {
          indexAxis: 'y',
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => `${context.formattedValue} sessions`
              }
            }
          },
          scales: {
            x: { beginAtZero: true, title: { display: true, text: 'Sessions' } },
            y: { ticks: { autoSkip: false }, grid: { display: false } }
          }
        }
      });
    }

    const funnel = navigation.funnel || { steps: [] };
    const steps = Array.isArray(funnel.steps) ? funnel.steps : [];
    if (!steps.length) {
      setNoDataMessage('chart-navigation-funnel', 'Not enough funnel data.');
    } else {
      const labels = steps.map(step => step.label || step.name || '');
      const values = steps.map(step => Number(step.value || 0));
      const startingValue = values[0] || 0;
      const conversionRates = values.map(value => {
        if (!startingValue) return '0.0';
        return ((value / startingValue) * 100).toFixed(1);
      });

      initChart('chart-navigation-funnel', {
        type: 'line',
        data: {
          labels,
          datasets: [{
            label: 'Sessions',
            data: values,
            tension: 0.35,
            fill: true,
            borderColor: '#2563eb',
            backgroundColor: hexToRgba('#2563eb', 0.18),
            pointRadius: 4,
            pointHoverRadius: 6
          }]
        },
        options: {
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => {
                  const idx = context.dataIndex ?? 0;
                  const rate = conversionRates[idx] ?? '0.0';
                  return `${context.formattedValue} sessions (${rate}%)`;
                }
              }
            }
          },
          scales: {
            x: { ticks: { autoSkip: false } },
            y: { beginAtZero: true, title: { display: true, text: 'Sessions' } }
          }
        }
      });
    }
  }

  function drawDeviceCharts(devicesSources) {
    const devices = Array.isArray(devicesSources.devices) ? devicesSources.devices : [];
    if (!devices.length) {
      setNoDataMessage('chart-device-donut', 'No device data available.');
    } else {
      initChart('chart-device-donut', {
        type: 'doughnut',
        data: {
          labels: devices.map(device => device.device || device.label),
          datasets: [{
            data: devices.map(device => Number(device.visits || device.value || 0)),
            backgroundColor: devices.map((_, idx) => palette[idx % palette.length]),
            borderWidth: 0
          }]
        },
        options: {
          cutout: '55%',
          plugins: { legend: { position: 'bottom' } }
        }
      });
    }

    const sources = Array.isArray(devicesSources.sourcesByPage) ? devicesSources.sourcesByPage : [];
    if (!sources.length) {
      setNoDataMessage('chart-source-stacked', 'Not enough referral data.');
    } else {
      const pages = sources.map(item => item.page);
      const sourceNames = sources.reduce((acc, item) => {
        (item.sources || []).forEach(src => {
          const name = src.name || 'Unknown';
          if (!acc.includes(name)) {
            acc.push(name);
          }
        });
        return acc;
      }, []);
      const stackedDatasets = sourceNames.map((name, index) => ({
        label: name,
        data: pages.map(page => {
          const pageEntry = sources.find(item => item.page === page);
          const entry = pageEntry ? (pageEntry.sources || []).find(src => (src.name || 'Unknown') === name) : null;
          return Number(entry ? entry.visits : 0);
        }),
        backgroundColor: palette[index % palette.length],
        stack: 'sources'
      }));

      initChart('chart-source-stacked', {
        type: 'bar',
        data: { labels: pages, datasets: stackedDatasets },
        options: {
          interaction: { mode: 'index', intersect: false },
          plugins: { legend: { position: 'bottom' } },
          scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Visits' } }
          }
        }
      });
    }
  }

  function drawGeoCharts(geography) {
    const countries = Array.isArray(geography.countries) ? geography.countries : [];
    if (!countries.length) {
      setNoDataMessage('chart-geo-heat', 'No geographic data available.');
    } else {
      const processedCountries = countries
        .map(item => ({
          label: item.country || item.label || 'Unknown',
          value: Number(item.count || item.value || 0)
        }))
        .filter(item => item.label)
        .sort((a, b) => b.value - a.value)
        .slice(0, 10);

      if (!processedCountries.length) {
        setNoDataMessage('chart-geo-heat', 'No geographic data available.');
      } else {
        const labels = processedCountries.map(item => item.label);
        const values = processedCountries.map(item => item.value);
        const total = values.reduce((sum, value) => sum + value, 0);

        initChart('chart-geo-heat', {
          type: 'doughnut',
          data: {
            labels,
            datasets: [{
              data: values,
              backgroundColor: labels.map((_, idx) => palette[idx % palette.length]),
              borderWidth: 0
            }]
          },
          options: {
            cutout: '45%',
            plugins: {
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: context => {
                    const rawValue = typeof context.parsed === 'number'
                      ? context.parsed
                      : Number(context.parsed || 0);
                    const percent = total ? ((rawValue / total) * 100).toFixed(1) : '0.0';
                    return `${context.label}: ${context.formattedValue} (${percent}%)`;
                  }
                }
              }
            }
          }
        });
      }
    }

    const regions = Array.isArray(geography.regions) ? geography.regions : [];
    if (!regions.length) {
      setNoDataMessage('chart-geo-bar', 'No regional data available.');
    } else {
      initChart('chart-geo-bar', {
        type: 'bar',
        data: {
          labels: regions.map(region => region.region || region.label),
          datasets: [{
            label: 'Visits',
            data: regions.map(region => Number(region.count || region.value || 0)),
            backgroundColor: '#0ea5e9',
            borderRadius: 10,
            maxBarThickness: 24
          }]
        },
        options: {
          indexAxis: 'y',
          plugins: { legend: { display: false } },
          scales: {
            x: { beginAtZero: true, title: { display: true, text: 'Visits' } },
            y: { ticks: { autoSkip: false }, grid: { display: false } }
          }
        }
      });
    }
  }

  function drawAdvancedCharts(advanced) {
    const correlationRaw = Array.isArray(advanced.correlation)
      ? advanced.correlation
      : (advanced.correlationMatrix && advanced.correlationMatrix.matrix ? advanced.correlationMatrix : null);
    const matrixAvailable = hasChartType('matrix');

    if (Array.isArray(correlationRaw) && correlationRaw.length) {
      const metrics = [
        { key: 'avg_time', label: 'Avg Time' },
        { key: 'bounce_rate', label: 'Bounce Rate' },
        { key: 'exit_rate', label: 'Exit Rate' },
        { key: 'visits', label: 'Visits' }
      ];
      const pages = correlationRaw.map(item => item.page || '');
      const matrixData = [];
      correlationRaw.forEach((item, col) => {
        metrics.forEach((metric, row) => {
          const raw = item[metric.key];
          const value = typeof raw === 'number' ? raw : Number(raw || 0);
          matrixData.push({ x: pages[col], y: metric.label, v: value });
        });
      });
      const values = matrixData.map(entry => entry.v);
      const min = values.reduce((acc, value) => Math.min(acc, value), values[0] ?? 0);
      const max = values.reduce((acc, value) => Math.max(acc, value), values[0] ?? 0);
      initChart('chart-advanced-correlation', {
        type: matrixAvailable ? 'matrix' : 'bar',
        data: matrixAvailable ? {
          datasets: [{
            label: 'Correlation',
            data: matrixData,
            backgroundColor: ctx => {
              const value = ctx.raw?.v ?? 0;
              const ratio = max === min ? 0.5 : (value - min) / (max - min);
              return mixColors('#bfdbfe', '#1d4ed8', ratio);
            },
            borderWidth: 1,
            borderColor: '#ffffff',
            width: ctx => {
              const chart = ctx.chart;
              return chart.chartArea ? chart.chartArea.width / pages.length - 6 : 20;
            },
            height: ctx => {
              const chart = ctx.chart;
              return chart.chartArea ? chart.chartArea.height / metrics.length - 6 : 20;
            }
          }]
        } : {
          labels: metrics.map(metric => metric.label),
          datasets: pages.map((page, idx) => ({
            label: page,
            data: metrics.map(metric => {
              const entry = correlationRaw[idx];
              const raw = entry ? entry[metric.key] : 0;
              return typeof raw === 'number' ? raw : Number(raw || 0);
            }),
            backgroundColor: hexToRgba(palette[idx % palette.length], 0.6)
          }))
        },
        options: {
          plugins: {
            legend: { position: matrixAvailable ? 'bottom' : 'top' },
            tooltip: matrixAvailable ? {
              callbacks: {
                title: items => {
                  const item = items?.[0];
                  return item ? `${item.raw?.y} • ${item.raw?.x}` : '';
                },
                label: item => `${item.raw?.v ?? 0}`
              }
            } : undefined,
            matrixValue: matrixAvailable ? { decimals: 1 } : undefined
          },
          scales: matrixAvailable ? {
            x: { type: 'category', labels: pages, offset: true },
            y: { type: 'category', labels: metrics.map(metric => metric.label), offset: true }
          } : {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true }
          }
        },
        plugins: matrixAvailable ? [matrixValuePlugin] : []
      });
    } else if (correlationRaw && Array.isArray(correlationRaw.matrix) && Array.isArray(correlationRaw.labels)) {
      const matrix = correlationRaw.matrix;
      const labels = correlationRaw.labels;
      const data = [];
      matrix.forEach((row, rowIndex) => {
        row.forEach((value, colIndex) => {
          data.push({ x: labels[colIndex], y: labels[rowIndex], v: Number(value || 0) });
        });
      });
      const values = data.map(entry => entry.v);
      const min = Math.min(...values);
      const max = Math.max(...values);
      initChart('chart-advanced-correlation', {
        type: matrixAvailable ? 'matrix' : 'bar',
        data: matrixAvailable ? {
          datasets: [{
            label: 'Correlation',
            data,
            backgroundColor: ctx => {
              const value = ctx.raw?.v ?? 0;
              const ratio = max === min ? 0.5 : (value - min) / (max - min);
              return mixColors('#fed7aa', '#ea580c', ratio);
            },
            borderWidth: 1,
            borderColor: '#ffffff',
            width: ctx => {
              const chart = ctx.chart;
              return chart.chartArea ? chart.chartArea.width / labels.length - 6 : 20;
            },
            height: ctx => {
              const chart = ctx.chart;
              return chart.chartArea ? chart.chartArea.height / labels.length - 6 : 20;
            }
          }]
        } : {
          labels,
          datasets: matrix.map((row, rowIndex) => ({
            label: labels[rowIndex] || `Series ${rowIndex + 1}`,
            data: row.map(value => Number(value || 0)),
            backgroundColor: hexToRgba(palette[rowIndex % palette.length], 0.6)
          }))
        },
        options: {
          plugins: {
            legend: { position: matrixAvailable ? 'bottom' : 'top' },
            tooltip: matrixAvailable ? {
              callbacks: {
                title: items => {
                  const item = items?.[0];
                  return item ? `${item.raw?.y} ↔ ${item.raw?.x}` : '';
                },
                label: item => `${Number(item.raw?.v ?? 0).toFixed(2)}`
              }
            } : undefined,
            matrixValue: matrixAvailable ? { decimals: 2 } : undefined
          },
          scales: matrixAvailable ? {
            x: { type: 'category', labels, offset: true },
            y: { type: 'category', labels, offset: true }
          } : {
            x: { beginAtZero: true },
            y: { beginAtZero: true }
          }
        },
        plugins: matrixAvailable ? [matrixValuePlugin] : []
      });
    } else {
      setNoDataMessage('chart-advanced-correlation', 'Not enough correlation data.');
    }

    const scatter = Array.isArray(advanced.scatter) ? advanced.scatter : [];
    if (!scatter.length) {
      setNoDataMessage('chart-advanced-scatter', 'Not enough engagement data for scatter plot.');
    } else {
      const scatterData = scatter.map(point => ({
        x: Number(point.visits || 0),
        y: Number(point.avg_time || 0),
        bounce: Number(point.bounce_rate || 0),
        exit: Number(point.exit_rate || 0),
        label: point.page || ''
      }));
      const exitValues = scatterData.map(item => item.exit);
      const exitMax = exitValues.reduce((acc, value) => Math.max(acc, value), 0);
      initChart('chart-advanced-scatter', {
        type: 'scatter',
        data: {
          datasets: [{
            label: 'Pages',
            data: scatterData,
            parsing: false,
            pointRadius: ctx => {
              const bounce = ctx.raw?.bounce ?? 0;
              return Math.max(6, bounce * 60);
            },
            pointHoverRadius: ctx => {
              const bounce = ctx.raw?.bounce ?? 0;
              return Math.max(8, bounce * 70);
            },
            backgroundColor: ctx => {
              const exit = ctx.raw?.exit ?? 0;
              const ratio = exitMax ? Math.min(exit / exitMax, 1) : 0;
              return mixColors('#22c55e', '#ef4444', ratio);
            },
            borderColor: '#ffffff',
            borderWidth: 1.5
          }]
        },
        options: {
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: context => {
                  const value = context.raw || {};
                  const bounce = (value.bounce * 100).toFixed(1);
                  const exit = (value.exit * 100).toFixed(1);
                  return [`${value.label}`, `Visits: ${value.x}`, `Avg time: ${value.y.toFixed(1)}s`, `Bounce: ${bounce}%`, `Exit: ${exit}%`];
                }
              }
            }
          },
          scales: {
            x: { title: { display: true, text: 'Visits' } },
            y: { title: { display: true, text: 'Avg time on page (s)' } }
          }
        }
      });
    }
  }

  if (dom.loadBtn) {
    dom.loadBtn.addEventListener('click', event => {
      event.preventDefault();
      state.start = dom.start ? dom.start.value : '';
      state.end = dom.end ? dom.end.value : '';
      loadAnalytics();
    });
  }

  loadAnalytics();
})();
</script>
JS;
  echo str_replace('__STATE_JSON__', $stateJson, $script);
}

