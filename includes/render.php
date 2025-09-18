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
</head>
<body{$bodyAttr}>
HTML;
}

function render_sidebar(string $active): void
{
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  $items = [
    'home'      => ['href' => 'index.php', 'icon' => 'bi-speedometer2',     'label' => 'Overview'],
    'logs'      => ['href' => 'page_access_logs.php', 'icon' => 'bi-file-text',       'label' => 'Page Access Logs'],
    'analytics' => ['href' => 'analytics_dashboard.php', 'icon' => 'bi-graph-up-arrow', 'label' => 'Analytics Dashboard'],
  ];

  echo '<aside class="col-12 col-md-3 col-lg-2 sidebar p-3">';
  echo '<div class="d-flex align-items-center justify-content-between mb-2">';
  echo '<h4 class="brand mb-0">Logs Center</h4>';
  echo '<form method="post" class="mb-0">';
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
  echo '<hr class="border-secondary">';
  echo '<small>Use the search box or date filters to refine results. Analytics is based on Page Access logs.</small>';
  echo '</aside>';
}

function render_footer(bool $includeCharts = false): void
{
  echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
  if ($includeCharts) {
    echo '\n<script src="https://cdn.plot.ly/plotly-2.29.1.min.js"></script>';
  }
  echo '\n</body>\n</html>';
}

function render_login_page(?string $error): void
{
  render_head('BTSPL ADMIN PORTAL', 'login-body');
  $errorHtml = '';
  if ($error) {
    $escaped = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
    $errorHtml = "<div class=\"alert alert-danger py-2 mb-3\">{$escaped}</div>";
  }
  $csrf = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
  echo <<<HTML
<div class="login-wrapper d-flex align-items-center justify-content-center">
  <div class="login-card box text-light">
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
  }

  function destroyChart(id) {
    if (window.Plotly && document.getElementById(id)) {
      Plotly.purge(id);
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
        renderAnalytics(data);
      })
      .catch(() => {
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
    const visits = pages.map(p => p.value || p.visits || 0);

    destroyChart('chart-popularity-bar');
    Plotly.newPlot('chart-popularity-bar', [{
      type: 'bar',
      x: visits,
      y: labels,
      orientation: 'h',
      marker: { color: '#1d4ed8' }
    }], {
      margin: { t: 10, r: 20, l: 160, b: 40 }
    }, { displayModeBar: false });

    destroyChart('chart-popularity-pie');
    Plotly.newPlot('chart-popularity-pie', [{
      type: 'pie',
      labels,
      values: visits,
      hole: 0.35,
      textinfo: 'label+percent'
    }], {
      margin: { t: 10, b: 10 }
    }, { displayModeBar: false });

    destroyChart('chart-popularity-treemap');
    Plotly.newPlot('chart-popularity-treemap', [{
      type: 'treemap',
      labels,
      parents: labels.map(() => ''),
      values: visits,
      textinfo: 'label+value'
    }], {
      margin: { t: 10, l: 0, r: 0, b: 0 }
    }, { displayModeBar: false });
  }

  function drawTrafficCharts(traffic) {
    const dailyTotals = traffic.dailyTotals || [];
    destroyChart('chart-traffic-line');
    Plotly.newPlot('chart-traffic-line', [{
      x: dailyTotals.map(d => d.date),
      y: dailyTotals.map(d => d.total),
      type: 'scatter',
      mode: 'lines+markers',
      line: { color: '#1d4ed8', width: 3 }
    }], {
      margin: { t: 10, r: 20, l: 60, b: 60 },
      xaxis: { title: 'Date' },
      yaxis: { title: 'Visits' }
    }, { displayModeBar: false });

    const perPage = traffic.perPage || [];
    destroyChart('chart-traffic-area');
    const areaData = perPage.map(page => ({
      x: page.series.map(p => p.date),
      y: page.series.map(p => p.visits),
      stackgroup: 'one',
      name: page.page,
      mode: 'lines',
      line: { width: 0.5 }
    }));
    Plotly.newPlot('chart-traffic-area', areaData, {
      margin: { t: 10, r: 20, l: 60, b: 60 },
      xaxis: { title: 'Date' },
      yaxis: { title: 'Visits' }
    }, { displayModeBar: false });
  }

  function drawEngagementCharts(engagement) {
    const avgTime = engagement.avgTime || [];
    destroyChart('chart-engagement-time');
    Plotly.newPlot('chart-engagement-time', [{
      type: 'bar',
      x: avgTime.map(i => i.page || i.label),
      y: avgTime.map(i => Math.round(i.seconds || i.value || 0)),
      marker: { color: '#22c55e' }
    }], {
      margin: { t: 10, r: 20, l: 50, b: 80 },
      yaxis: { title: 'Seconds' }
    }, { displayModeBar: false });

    const bounce = engagement.bounceRate || [];
    destroyChart('chart-engagement-bounce');
    Plotly.newPlot('chart-engagement-bounce', [{
      type: 'bar',
      x: bounce.map(i => i.page || i.label),
      y: bounce.map(i => Math.round((i.rate || i.value || 0) * 100)),
      marker: { color: '#f97316' }
    }], {
      margin: { t: 10, r: 20, l: 50, b: 80 },
      yaxis: { title: 'Bounce %' }
    }, { displayModeBar: false });

    const exits = engagement.exitPages || [];
    destroyChart('chart-engagement-exit');
    Plotly.newPlot('chart-engagement-exit', [{
      type: 'bar',
      x: exits.map(i => i.count || i.value),
      y: exits.map(i => i.page || i.label),
      orientation: 'h',
      marker: { color: '#a855f7' }
    }], {
      margin: { t: 10, r: 20, l: 160, b: 40 },
      xaxis: { title: 'Sessions ending here' }
    }, { displayModeBar: false });
  }

  function drawNavigationCharts(navigation) {
    const sankey = navigation.sankey || { nodes: [], links: [] };
    destroyChart('chart-navigation-sankey');
    if ((sankey.nodes || []).length && (sankey.links || []).length) {
      Plotly.newPlot('chart-navigation-sankey', [{
        type: 'sankey',
        node: { label: sankey.nodes },
        link: {
          source: sankey.links.map(l => l.source),
          target: sankey.links.map(l => l.target),
          value: sankey.links.map(l => l.value)
        }
      }], {
        margin: { t: 10, l: 30, r: 30, b: 10 }
      }, { displayModeBar: false });
    } else if (document.getElementById('chart-navigation-sankey')) {
      document.getElementById('chart-navigation-sankey').innerHTML = '<p class="text-muted">Not enough navigation data.</p>';
    }

    const funnel = navigation.funnel || { steps: [] };
    destroyChart('chart-navigation-funnel');
    if ((funnel.steps || []).length) {
      Plotly.newPlot('chart-navigation-funnel', [{
        type: 'funnel',
        y: funnel.steps.map(s => s.label),
        x: funnel.steps.map(s => s.value)
      }], {
        margin: { t: 10, l: 150, r: 40, b: 40 }
      }, { displayModeBar: false });
    } else if (document.getElementById('chart-navigation-funnel')) {
      document.getElementById('chart-navigation-funnel').innerHTML = '<p class="text-muted">Not enough funnel data.</p>';
    }
  }

  function drawDeviceCharts(devicesSources) {
    const devices = devicesSources.devices || [];
    destroyChart('chart-device-donut');
    Plotly.newPlot('chart-device-donut', [{
      type: 'pie',
      labels: devices.map(i => i.device || i.label),
      values: devices.map(i => i.visits || i.value || 0),
      hole: 0.35
    }], {
      margin: { t: 10, b: 10 }
    }, { displayModeBar: false });

    const sources = devicesSources.sourcesByPage || [];
    destroyChart('chart-source-stacked');
    if (sources.length) {
      const pages = sources.map(item => item.page);
      const sourceNames = sources.reduce((acc, item) => {
        item.sources.forEach(src => {
          if (!acc.includes(src.name)) acc.push(src.name);
        });
        return acc;
      }, []);
      const traces = sourceNames.map(source => ({
        name: source,
        type: 'bar',
        x: pages,
        y: sources.map(item => {
          const found = item.sources.find(src => src.name === source);
          return found ? found.visits : 0;
        })
      }));
      Plotly.newPlot('chart-source-stacked', traces, {
        barmode: 'stack',
        margin: { t: 10, r: 20, l: 60, b: 80 },
        xaxis: { title: 'Page' },
        yaxis: { title: 'Visits' }
      }, { displayModeBar: false });
    } else if (document.getElementById('chart-source-stacked')) {
      document.getElementById('chart-source-stacked').innerHTML = '<p class="text-muted">Not enough referral data.</p>';
    }
  }

  function drawGeoCharts(geography) {
    const countries = geography.countries || [];
    destroyChart('chart-geo-heat');
    Plotly.newPlot('chart-geo-heat', [{
      type: 'choropleth',
      locations: countries.map(i => i.country || i.label),
      locationmode: 'country names',
      z: countries.map(i => i.count || i.value || 0),
      colorscale: 'Blues'
    }], {
      margin: { t: 10, r: 0, l: 0, b: 0 }
    }, { displayModeBar: false });

    const regions = geography.regions || [];
    destroyChart('chart-geo-bar');
    Plotly.newPlot('chart-geo-bar', [{
      type: 'bar',
      x: regions.map(i => i.count || i.value || 0),
      y: regions.map(i => i.region || i.label),
      orientation: 'h',
      marker: { color: '#0ea5e9' }
    }], {
      margin: { t: 10, r: 20, l: 160, b: 40 }
    }, { displayModeBar: false });
  }

  function drawAdvancedCharts(advanced) {
    const correlation = advanced.correlation || [];
    destroyChart('chart-advanced-correlation');
    if (correlation.length) {
      const pages = correlation.map(item => item.page);
      const metrics = ['avg_time', 'bounce_rate', 'exit_rate', 'visits'];
      const z = metrics.map(metric => correlation.map(item => item[metric] || 0));
      Plotly.newPlot('chart-advanced-correlation', [{
        type: 'heatmap',
        z,
        x: pages,
        y: metrics
      }], {
        margin: { t: 10, r: 20, l: 80, b: 80 }
      }, { displayModeBar: false });
    } else if (document.getElementById('chart-advanced-correlation')) {
      document.getElementById('chart-advanced-correlation').innerHTML = '<p class="text-muted">Not enough data.</p>';
    }

    const scatter = advanced.scatter || [];
    destroyChart('chart-advanced-scatter');
    Plotly.newPlot('chart-advanced-scatter', [{
      mode: 'markers',
      type: 'scatter',
      x: scatter.map(point => point.visits || 0),
      y: scatter.map(point => point.avg_time || 0),
      text: scatter.map(point => point.page),
      marker: {
        size: scatter.map(point => (point.bounce_rate || 0) * 60 + 8),
        color: scatter.map(point => point.exit_rate || 0),
        colorscale: 'Turbo',
        showscale: true,
        colorbar: { title: 'Exit Rate' }
      }
    }], {
      margin: { t: 10, r: 20, l: 80, b: 80 },
      xaxis: { title: 'Visits' },
      yaxis: { title: 'Avg time on page (s)' }
    }, { displayModeBar: false });
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
