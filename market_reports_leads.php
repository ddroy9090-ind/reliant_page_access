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
$perPage = 10;
$page = filter_input(
  INPUT_GET,
  'page',
  FILTER_VALIDATE_INT,
  ['options' => ['default' => 1, 'min_range' => 1]]
);
$offset = ($page - 1) * $perPage;
$totalRecords = 0;
$totalPages = 0;
$leads = [];
$error = null;

$operationSuccess = $_SESSION['market_reports_success'] ?? null;
unset($_SESSION['market_reports_success']);
$operationError = $_SESSION['market_reports_error'] ?? null;
unset($_SESSION['market_reports_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'delete') {
    rl_hit('delete-market-report-lead', 20);
    $leadId = (int)($_POST['lead_id'] ?? 0);

    if ($leadId <= 0) {
      $_SESSION['market_reports_error'] = 'Invalid lead selected for deletion.';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT id FROM reports_form_data WHERE id = :id');
        $stmt->execute([':id' => $leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
          $_SESSION['market_reports_error'] = 'The selected lead could not be found.';
        } else {
          $deleteStmt = $pdo->prepare('DELETE FROM reports_form_data WHERE id = :id');
          $deleteStmt->execute([':id' => $leadId]);
          $_SESSION['market_reports_success'] = 'Lead deleted successfully.';
        }
      } catch (Throwable $e) {
        error_log('Failed to delete market report lead: ' . $e->getMessage());
        $_SESSION['market_reports_error'] = 'An unexpected error occurred while deleting the lead.';
      }
    }
  } else {
    $_SESSION['market_reports_error'] = 'Unsupported action requested.';
  }

  header('Location: market_reports_leads.php');
  exit;
}

try {
  $countStmt = $pdo->query('SELECT COUNT(*) FROM reports_form_data');
  $totalRecords = (int)$countStmt->fetchColumn();

  if ($totalRecords > 0) {
    $totalPages = (int)ceil($totalRecords / $perPage);
    if ($page > $totalPages) {
      $page = $totalPages;
      $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare(
      'SELECT id, full_name, email, phone, company, category FROM reports_form_data ORDER BY submitted_at DESC, id DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $leads = $stmt->fetchAll();
  } else {
    $page = 1;
    $offset = 0;
  }
} catch (Throwable $e) {
  $error = 'Unable to load market report leads at this time.';
}

$paginationBasePath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/market_reports_leads.php';
$paginationQueryParams = $_GET;
unset($paginationQueryParams['page']);
$buildPaginationUrl = static function (int $targetPage) use ($paginationBasePath, $paginationQueryParams): string {
  $params = $paginationQueryParams;
  $params['page'] = $targetPage;
  $queryString = http_build_query($params);
  $url = $paginationBasePath . ($queryString ? '?' . $queryString : '');
  return $url === '' ? '#' : $url;
};

render_head('Market Reports Leads');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('market-report-leads');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">Market Reports Leads</h2>
      <p class="para mb-0">Review leads submitted through the market reports form.</p>
    </div>
    <div class="text-lg-end">
      <span class="badge bg-primary-subtle text-primary fw-semibold">Total leads: <?= number_format($totalRecords) ?></span>
    </div>
  </div>

  <?php if ($operationSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show market-report-alert" role="alert">
      <?= htmlspecialchars($operationSuccess, ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($operationError): ?>
    <div class="alert alert-danger alert-dismissible fade show market-report-alert" role="alert">
      <?= htmlspecialchars($operationError, ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-warning" role="alert">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php elseif (!$leads): ?>
    <div class="alert alert-info" role="alert">
      No market report leads found.
    </div>
  <?php else: ?>
    <div class="box">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-secondary">
            <tr>
              <th scope="col">#</th>
              <th scope="col">Full Name</th>
              <th scope="col">Email</th>
              <th scope="col">Phone</th>
              <th scope="col">Company</th>
              <th scope="col">Category</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leads as $lead): ?>
              <tr>
                <td><?= (int)$lead['id'] ?></td>
                <td><?= htmlspecialchars((string)($lead['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if (!empty($lead['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($lead['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($lead['company'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($lead['category'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end text-nowrap">
                  <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Delete lead"
                    data-delete-market-lead="1"
                    data-lead-id="<?= htmlspecialchars((string)$lead['id'], ENT_QUOTES, 'UTF-8') ?>"
                    data-lead-name="<?= htmlspecialchars((string)($lead['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav aria-label="Market reports leads pagination" class="mt-3">
          <ul class="pagination mb-0">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
              <?php if ($page <= 1): ?>
                <span class="page-link">Previous</span>
              <?php else: ?>
                <a class="page-link" href="<?= htmlspecialchars($buildPaginationUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous">Previous</a>
              <?php endif; ?>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item<?= $i === $page ? ' active' : '' ?>"<?php if ($i === $page): ?> aria-current="page"<?php endif; ?>>
                <?php if ($i === $page): ?>
                  <span class="page-link"><?= $i ?></span>
                <?php else: ?>
                  <a class="page-link" href="<?= htmlspecialchars($buildPaginationUrl($i), ENT_QUOTES, 'UTF-8') ?>"><?= $i ?></a>
                <?php endif; ?>
              </li>
            <?php endfor; ?>
            <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
              <?php if ($page >= $totalPages): ?>
                <span class="page-link">Next</span>
              <?php else: ?>
                <a class="page-link" href="<?= htmlspecialchars($buildPaginationUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next">Next</a>
              <?php endif; ?>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</main>
<form method="post" class="d-none" id="delete-lead-form">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="lead_id" id="delete-lead-id" value="">
</form>
<script>
  (function() {
    const deleteForm = document.getElementById('delete-lead-form');
    const deleteIdInput = document.getElementById('delete-lead-id');

    document.querySelectorAll('[data-delete-market-lead="1"]').forEach(button => {
      button.addEventListener('click', () => {
        if (!deleteForm || !deleteIdInput) {
          return;
        }

        const leadId = parseInt(button.getAttribute('data-lead-id') || '', 10);
        if (!leadId) {
          return;
        }

        const leadName = button.getAttribute('data-lead-name') || '';
        const label = leadName ? `"${leadName}"` : 'this lead';
        const confirmDelete = window.confirm(`Are you sure you want to delete ${label}? This action cannot be undone.`);
        if (!confirmDelete) {
          return;
        }

        deleteIdInput.value = String(leadId);
        deleteForm.submit();
      });
    });

    const alerts = document.querySelectorAll('.market-report-alert');
    if (alerts.length > 0) {
      window.setTimeout(() => {
        alerts.forEach(alert => {
          alert.classList.remove('show');
          alert.classList.add('d-none');
        });
      }, 5000);
    }
  })();
</script>
<?php
echo '</div>';
echo '</div>';
render_footer();
