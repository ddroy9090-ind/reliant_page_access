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



function render_footer(bool $includeECharts = false, bool $includeChartJs = false): void
{
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';

  if ($includeECharts) {
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js\"></script>";
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/echarts@5/map/js/world.js\"></script>";
  }

  if ($includeChartJs) {
    echo "\n<script src=\"https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js\"></script>";
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
    <h3 class="text-center fw-bold mb-4">Reliant Monitor Portal</h3>
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

  function ensureEChartsReady(timeout = 10000) {
    if (window.echarts && typeof window.echarts.init === 'function') {
      return Promise.resolve(window.echarts);
    }
    return new Promise((resolve, reject) => {
      const start = Date.now();
      const timer = setInterval(() => {
        if (window.echarts && typeof window.echarts.init === 'function') {
          clearInterval(timer);
          resolve(window.echarts);
          return;
        }
        if (Date.now() - start >= timeout) {
          clearInterval(timer);
          reject(new Error('ECharts failed to load'));
        }
      }, 50);
    });
  }

  function resizeCharts() {
    charts.forEach(chart => {
      if (chart && typeof chart.resize === 'function') {
        chart.resize();
      }
    });
  }

  function destroyChart(id) {
    const chart = charts.get(id);
    if (chart && typeof chart.dispose === 'function') {
      chart.dispose();
    }
    charts.delete(id);
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = '';
    }
  }

  function setNoDataMessage(id, message) {
    destroyChart(id);
    const el = document.getElementById(id);
    if (el) {
      el.innerHTML = `<p class="text-muted">${message}</p>`;
    }
  }

  function initChart(id, option) {
    const el = document.getElementById(id);
    if (!el || !window.echarts || typeof window.echarts.init !== 'function') {
      return null;
    }
    destroyChart(id);
    const chart = window.echarts.init(el);
    chart.setOption(option);
    charts.set(id, chart);
    if (!resizeHandlerAttached) {
      window.addEventListener('resize', resizeCharts);
      resizeHandlerAttached = true;
    }
    setTimeout(() => chart.resize(), 0);
    return chart;
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
        return ensureEChartsReady().then(() => {
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
    const pages = popularity.pages || [];
    const labels = pages.map(p => p.label || p.page || '');
    const visits = pages.map(p => Number(p.value || p.visits || 0));

    if (!labels.length) {
      setNoDataMessage('chart-popularity-bar', 'No page view data available.');
      setNoDataMessage('chart-popularity-pie', 'No traffic distribution data available.');
      setNoDataMessage('chart-popularity-treemap', 'No treemap data available.');
      return;
    }

    initChart('chart-popularity-bar', {
      color: ['#1d4ed8'],
      grid: { left: 140, right: 24, top: 20, bottom: 30 },
      tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
      xAxis: { type: 'value', name: 'Visits' },
      yAxis: { type: 'category', data: labels, inverse: true, axisLabel: { interval: 0 } },
      series: [{
        type: 'bar',
        data: visits,
        itemStyle: { borderRadius: [0, 6, 6, 0] }
      }]
    });

    initChart('chart-popularity-pie', {
      color: palette,
      tooltip: { trigger: 'item' },
      legend: { type: 'scroll', bottom: 0 },
      series: [{
        name: 'Visits',
        type: 'pie',
        radius: ['40%', '70%'],
        data: labels.map((label, idx) => ({ value: visits[idx], name: label })),
        label: { formatter: '{b}: {d}%' }
      }]
    });

    initChart('chart-popularity-treemap', {
      tooltip: {
        formatter: params => `${params.name}: ${params.value}`
      },
      series: [{
        type: 'treemap',
        data: labels.map((label, idx) => ({ name: label, value: visits[idx] })),
        roam: false,
        leafDepth: 1,
        label: { formatter: '{b}\n{c}' },
        breadcrumb: { show: false }
      }]
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
        color: ['#2563eb'],
        grid: { left: 60, right: 30, top: 20, bottom: 60 },
        tooltip: { trigger: 'axis' },
        xAxis: { type: 'category', boundaryGap: false, data: lineLabels },
        yAxis: { type: 'value', name: 'Visits' },
        series: [{
          type: 'line',
          data: lineValues,
          smooth: true,
          symbol: 'circle',
          symbolSize: 8,
          areaStyle: { opacity: 0.1 }
        }]
      });
    }

    const dates = lineLabels.length
      ? lineLabels
      : Array.from(new Set(perPage.flatMap(page => page.series.map(point => point.date))));
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
          name: page.page,
          type: 'line',
          stack: 'total',
          smooth: true,
          areaStyle: { opacity: 0.35 },
          emphasis: { focus: 'series' },
          lineStyle: { width: 1.5 },
          itemStyle: { color },
          data
        };
      });

      initChart('chart-traffic-area', {
        color: palette,
        grid: { left: 60, right: 30, top: 20, bottom: 60 },
        tooltip: { trigger: 'axis' },
        legend: { top: 0 },
        xAxis: { type: 'category', boundaryGap: false, data: dates },
        yAxis: { type: 'value', name: 'Visits' },
        series: areaSeries
      });
    }
  }

  function drawEngagementCharts(engagement) {
    const avgTime = Array.isArray(engagement.avgTime) ? engagement.avgTime : [];
    if (!avgTime.length) {
      setNoDataMessage('chart-engagement-time', 'No average time data available.');
    } else {
      initChart('chart-engagement-time', {
        color: ['#22c55e'],
        grid: { left: 60, right: 30, top: 20, bottom: 80 },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'category', data: avgTime.map(i => i.page || i.label) },
        yAxis: { type: 'value', name: 'Seconds' },
        series: [{
          type: 'bar',
          data: avgTime.map(i => Math.round(Number(i.seconds || i.value || 0))),
          itemStyle: { borderRadius: [6, 6, 0, 0] }
        }]
      });
    }

    const bounce = Array.isArray(engagement.bounceRate) ? engagement.bounceRate : [];
    if (!bounce.length) {
      setNoDataMessage('chart-engagement-bounce', 'No bounce rate data available.');
    } else {
      initChart('chart-engagement-bounce', {
        color: ['#f97316'],
        grid: { left: 60, right: 30, top: 20, bottom: 80 },
        tooltip: {
          trigger: 'axis',
          axisPointer: { type: 'shadow' },
          valueFormatter: value => `${value}%`
        },
        xAxis: { type: 'category', data: bounce.map(i => i.page || i.label) },
        yAxis: { type: 'value', name: 'Bounce %' },
        series: [{
          type: 'bar',
          data: bounce.map(i => Math.round(Number(i.rate || i.value || 0) * 100)),
          itemStyle: { borderRadius: [6, 6, 0, 0] }
        }]
      });
    }

    const exits = Array.isArray(engagement.exitPages) ? engagement.exitPages : [];
    if (!exits.length) {
      setNoDataMessage('chart-engagement-exit', 'No exit page data available.');
    } else {
      initChart('chart-engagement-exit', {
        color: ['#8b5cf6'],
        grid: { left: 180, right: 30, top: 20, bottom: 40 },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'value', name: 'Sessions ending here' },
        yAxis: { type: 'category', inverse: true, data: exits.map(i => i.page || i.label) },
        series: [{
          type: 'bar',
          data: exits.map(i => Number(i.count || i.value)),
          itemStyle: { borderRadius: [0, 6, 6, 0] }
        }]
      });
    }
  }

  function drawNavigationCharts(navigation) {
    const sankey = navigation.sankey || { nodes: [], links: [] };
    const sankeyNodes = Array.isArray(sankey.nodes) ? sankey.nodes.map(name => ({ name })) : [];
    const sankeyLinks = Array.isArray(sankey.links)
      ? sankey.links.map(link => ({
        source: sankey.nodes && sankey.nodes[link.source] ? sankey.nodes[link.source] : link.source,
        target: sankey.nodes && sankey.nodes[link.target] ? sankey.nodes[link.target] : link.target,
        value: Number(link.value || 0)
      })).filter(link => link.source !== undefined && link.target !== undefined)
      : [];

    if (!sankeyNodes.length || !sankeyLinks.length) {
      setNoDataMessage('chart-navigation-sankey', 'Not enough navigation data.');
    } else {
      initChart('chart-navigation-sankey', {
        tooltip: { trigger: 'item', triggerOn: 'mousemove' },
        series: [{
          type: 'sankey',
          data: sankeyNodes,
          links: sankeyLinks,
          emphasis: { focus: 'adjacency' },
          lineStyle: { color: 'source', curveness: 0.5 }
        }]
      });
    }

    const funnel = navigation.funnel || { steps: [] };
    const steps = Array.isArray(funnel.steps) ? funnel.steps : [];
    if (!steps.length) {
      setNoDataMessage('chart-navigation-funnel', 'Not enough funnel data.');
    } else {
      initChart('chart-navigation-funnel', {
        color: palette,
        tooltip: { trigger: 'item', formatter: params => `${params.name}: ${params.value}` },
        legend: { show: false },
        series: [{
          type: 'funnel',
          sort: 'descending',
          gap: 4,
          label: { formatter: '{b}: {c}' },
          data: steps.map(step => ({ name: step.label, value: Number(step.value || 0) }))
        }]
      });
    }
  }

  function drawDeviceCharts(devicesSources) {
    const devices = Array.isArray(devicesSources.devices) ? devicesSources.devices : [];
    if (!devices.length) {
      setNoDataMessage('chart-device-donut', 'No device data available.');
    } else {
      initChart('chart-device-donut', {
        color: palette,
        tooltip: { trigger: 'item' },
        legend: { bottom: 0 },
        series: [{
          name: 'Visits',
          type: 'pie',
          radius: ['45%', '70%'],
          avoidLabelOverlap: false,
          data: devices.map(device => ({
            value: Number(device.visits || device.value || 0),
            name: device.device || device.label
          })),
          label: { formatter: '{b}: {d}%' }
        }]
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
      const stackedSeries = sourceNames.map((sourceName, index) => ({
        name: sourceName,
        type: 'bar',
        stack: 'total',
        emphasis: { focus: 'series' },
        itemStyle: { color: palette[index % palette.length] },
        data: pages.map(page => {
          const pageEntry = sources.find(item => item.page === page);
          if (!pageEntry) return 0;
          const entry = (pageEntry.sources || []).find(src => (src.name || 'Unknown') === sourceName);
          return Number(entry ? entry.visits : 0);
        })
      }));

      initChart('chart-source-stacked', {
        grid: { left: 70, right: 30, top: 40, bottom: 80 },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        legend: { top: 0 },
        xAxis: { type: 'category', data: pages },
        yAxis: { type: 'value', name: 'Visits' },
        series: stackedSeries
      });
    }
  }

  function drawGeoCharts(geography) {
    const countries = Array.isArray(geography.countries) ? geography.countries : [];
    if (!countries.length || !window.echarts || !window.echarts.getMap || !window.echarts.getMap('world')) {
      setNoDataMessage('chart-geo-heat', 'No geographic data available.');
    } else {
      const heatData = countries.map(item => ({
        name: item.country || item.label,
        value: Number(item.count || item.value || 0)
      }));
      const values = heatData.map(item => item.value);
      const max = values.reduce((acc, value) => Math.max(acc, value), 0);

      initChart('chart-geo-heat', {
        tooltip: { trigger: 'item' },
        visualMap: {
          min: 0,
          max: max || 1,
          left: 'left',
          bottom: 0,
          text: ['High', 'Low'],
          calculable: true
        },
        series: [{
          type: 'map',
          map: 'world',
          roam: true,
          emphasis: { label: { show: false } },
          data: heatData
        }]
      });
    }

    const regions = Array.isArray(geography.regions) ? geography.regions : [];
    if (!regions.length) {
      setNoDataMessage('chart-geo-bar', 'No regional data available.');
    } else {
      initChart('chart-geo-bar', {
        color: ['#0ea5e9'],
        grid: { left: 160, right: 30, top: 20, bottom: 40 },
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' } },
        xAxis: { type: 'value', name: 'Visits' },
        yAxis: {
          type: 'category',
          inverse: true,
          data: regions.map(region => region.region || region.label)
        },
        series: [{
          type: 'bar',
          data: regions.map(region => Number(region.count || region.value || 0)),
          itemStyle: { borderRadius: [0, 6, 6, 0] }
        }]
      });
    }
  }

  function drawAdvancedCharts(advanced) {
    const correlation = Array.isArray(advanced.correlation)
      ? advanced.correlation
      : (advanced.correlationMatrix && advanced.correlationMatrix.matrix
        ? advanced.correlationMatrix
        : []);

    if (Array.isArray(correlation)) {
      if (!correlation.length) {
        setNoDataMessage('chart-advanced-correlation', 'Not enough correlation data.');
      } else {
        const metrics = [
          { key: 'avg_time', label: 'Avg Time' },
          { key: 'bounce_rate', label: 'Bounce Rate' },
          { key: 'exit_rate', label: 'Exit Rate' },
          { key: 'visits', label: 'Visits' }
        ];
        const pages = correlation.map(item => item.page);
        const heatData = [];
        metrics.forEach((metric, row) => {
          correlation.forEach((item, col) => {
            const raw = item[metric.key];
            const value = typeof raw === 'number' ? raw : Number(raw || 0);
            heatData.push([col, row, value]);
          });
        });
        const values = heatData.map(entry => entry[2]);
        const max = values.reduce((acc, value) => Math.max(acc, value), 0);

        initChart('chart-advanced-correlation', {
          tooltip: {
            position: 'top',
            formatter: params => {
              const metric = metrics[params.data[1]];
              const page = pages[params.data[0]];
              return `${metric.label} • ${page}: ${params.data[2]}`;
            }
          },
          grid: { left: 90, right: 30, top: 20, bottom: 90 },
          xAxis: { type: 'category', data: pages, axisLabel: { rotate: 30 } },
          yAxis: { type: 'category', data: metrics.map(metric => metric.label) },
          visualMap: {
            min: 0,
            max: max || 1,
            calculable: true,
            orient: 'horizontal',
            left: 'center',
            bottom: 20
          },
          series: [{
            type: 'heatmap',
            data: heatData,
            label: {
              show: true,
              formatter: params => {
                const value = params.data[2];
                return typeof value === 'number' ? value.toFixed(1) : value;
              }
            }
          }]
        });
      }
    } else if (correlation && Array.isArray(correlation.matrix) && Array.isArray(correlation.labels)) {
      const matrix = correlation.matrix;
      const labels = correlation.labels;
      const heatData = [];
      matrix.forEach((row, rowIndex) => {
        row.forEach((value, colIndex) => {
          heatData.push([colIndex, rowIndex, Number(value || 0)]);
        });
      });

      initChart('chart-advanced-correlation', {
        tooltip: {
          position: 'top',
          formatter: params => {
            const row = labels[params.data[1]] || '';
            const col = labels[params.data[0]] || '';
            return `${row} ↔ ${col}: ${params.data[2].toFixed(2)}`;
          }
        },
        grid: { left: 90, right: 30, top: 20, bottom: 90 },
        xAxis: { type: 'category', data: labels, axisLabel: { rotate: 30 } },
        yAxis: { type: 'category', data: labels },
        visualMap: {
          min: -1,
          max: 1,
          calculable: true,
          orient: 'horizontal',
          left: 'center',
          bottom: 20
        },
        series: [{
          type: 'heatmap',
          data: heatData,
          label: { show: true, formatter: params => params.data[2].toFixed(2) }
        }]
      });
    } else {
      setNoDataMessage('chart-advanced-correlation', 'Not enough correlation data.');
    }

    const scatter = Array.isArray(advanced.scatter) ? advanced.scatter : [];
    if (!scatter.length) {
      setNoDataMessage('chart-advanced-scatter', 'Not enough engagement data for scatter plot.');
    } else {
      const scatterData = scatter.map(point => ([
        Number(point.visits || 0),
        Number(point.avg_time || 0),
        Number(point.bounce_rate || 0),
        Number(point.exit_rate || 0),
        point.page || ''
      ]));
      const exitValues = scatterData.map(item => item[3]);
      const exitMax = exitValues.reduce((acc, value) => Math.max(acc, value), 0);

      initChart('chart-advanced-scatter', {
        grid: { left: 80, right: 60, top: 20, bottom: 80 },
        tooltip: {
          formatter: params => {
            const value = params.value;
            const bounce = (value[2] * 100).toFixed(1);
            const exit = (value[3] * 100).toFixed(1);
            return `${value[4]}<br/>Visits: ${value[0]}<br/>Avg time: ${value[1].toFixed(1)}s<br/>Bounce: ${bounce}%<br/>Exit: ${exit}%`;
          }
        },
        xAxis: { name: 'Visits' },
        yAxis: { name: 'Avg time on page (s)' },
        visualMap: {
          type: 'continuous',
          min: 0,
          max: exitMax || 0.1,
          dimension: 3,
          right: 0,
          top: 20,
          text: ['High exit', 'Low exit'],
          inRange: { color: ['#22c55e', '#ef4444'] }
        },
        series: [{
          type: 'scatter',
          data: scatterData,
          symbolSize: value => (value[2] * 60) + 8,
          emphasis: { focus: 'series' },
          encode: { x: 0, y: 1 },
          itemStyle: {
            shadowBlur: 10,
            shadowColor: 'rgba(0,0,0,0.15)',
            shadowOffsetY: 5
          }
        }]
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
