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
$perPage = 5;
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

$operationSuccess = $_SESSION['job_leads_success'] ?? null;
unset($_SESSION['job_leads_success']);
$operationError = $_SESSION['job_leads_error'] ?? null;
unset($_SESSION['job_leads_error']);

$downloadId = filter_input(INPUT_GET, 'download', FILTER_VALIDATE_INT, [
  'options' => ['default' => 0, 'min_range' => 1],
]);

if ($downloadId) {
  try {
    $stmt = $pdo->prepare(
      'SELECT resume_filename, resume_mime, resume_size, resume_blob FROM job_leads WHERE id = :id'
    );
    $stmt->execute([':id' => $downloadId]);
    $lead = $stmt->fetch();

    if (!$lead || empty($lead['resume_blob'])) {
      $_SESSION['job_leads_error'] = 'The requested resume could not be found.';
    } else {
      $filename = (string)($lead['resume_filename'] ?? 'resume-' . $downloadId);
      $filename = trim($filename) === '' ? 'resume-' . $downloadId : $filename;
      $safeFilename = str_replace(["\r", "\n", '"', '\\'], '_', $filename);
      $mimeType = (string)($lead['resume_mime'] ?? 'application/octet-stream');
      $size = isset($lead['resume_size']) ? (int)$lead['resume_size'] : null;
      $content = (string)$lead['resume_blob'];

      header('Content-Type: ' . $mimeType);
      header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
      header('X-Content-Type-Options: nosniff');
      if ($size !== null && $size > 0) {
        header('Content-Length: ' . $size);
      }

      echo $content;
      exit;
    }
  } catch (Throwable $e) {
    error_log('Failed to download resume: ' . $e->getMessage());
    $_SESSION['job_leads_error'] = 'Unable to download the requested resume at this time.';
  }

  header('Location: job_leads.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'delete') {
    rl_hit('delete-job-lead', 20);
    $leadId = (int)($_POST['lead_id'] ?? 0);

    if ($leadId <= 0) {
      $_SESSION['job_leads_error'] = 'Invalid lead selected for deletion.';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT id FROM job_leads WHERE id = :id');
        $stmt->execute([':id' => $leadId]);
        $lead = $stmt->fetch();

        if (!$lead) {
          $_SESSION['job_leads_error'] = 'The selected lead could not be found.';
        } else {
          $deleteStmt = $pdo->prepare('DELETE FROM job_leads WHERE id = :id');
          $deleteStmt->execute([':id' => $leadId]);
          $_SESSION['job_leads_success'] = 'Lead deleted successfully.';
        }
      } catch (Throwable $e) {
        error_log('Failed to delete job lead: ' . $e->getMessage());
        $_SESSION['job_leads_error'] = 'An unexpected error occurred while deleting the lead.';
      }
    }
  } else {
    $_SESSION['job_leads_error'] = 'Unsupported action requested.';
  }

  header('Location: job_leads.php');
  exit;
}

try {
  $countStmt = $pdo->query('SELECT COUNT(*) FROM job_leads');
  $totalRecords = (int)$countStmt->fetchColumn();

  if ($totalRecords > 0) {
    $totalPages = (int)ceil($totalRecords / $perPage);
    if ($page > $totalPages) {
      $page = $totalPages;
      $offset = ($page - 1) * $perPage;
    }

    $stmt = $pdo->prepare(
      'SELECT id, name, email, mobile, position, cover_letter, resume_filename, resume_blob IS NOT NULL AS has_resume, created_at '
      . 'FROM job_leads ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset'
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
  error_log('Failed to load job leads: ' . $e->getMessage());
  $error = 'Unable to load job leads at this time.';
}

$paginationBasePath = strtok((string)($_SERVER['REQUEST_URI'] ?? ''), '?') ?: '/job_leads.php';
$paginationQueryParams = $_GET;
unset($paginationQueryParams['page']);
unset($paginationQueryParams['download']);
$buildPaginationUrl = static function (int $targetPage) use ($paginationBasePath, $paginationQueryParams): string {
  $params = $paginationQueryParams;
  $params['page'] = $targetPage;
  $queryString = http_build_query($params);
  $url = $paginationBasePath . ($queryString ? '?' . $queryString : '');
  return $url === '' ? '#' : $url;
};

$buildDownloadUrl = static function (int $leadId, int $currentPage) use ($paginationBasePath, $paginationQueryParams): string {
  $params = $paginationQueryParams;
  $params['page'] = $currentPage;
  $params['download'] = $leadId;
  $queryString = http_build_query($params);
  $url = $paginationBasePath . ($queryString ? '?' . $queryString : '');
  return $url === '' ? '#' : $url;
};

render_head('Job Leads');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('job-leads');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">Job Leads</h2>
      <p class="para mb-0">Review applicants who submitted job inquiries.</p>
    </div>
    <div class="text-lg-end">
      <span class="badge bg-primary-subtle text-primary fw-semibold">Total leads: <?= number_format($totalRecords) ?></span>
    </div>
  </div>

  <?php if ($operationSuccess): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($operationSuccess, ENT_QUOTES, 'UTF-8') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($operationError): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
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
      No job leads found.
    </div>
  <?php else: ?>
    <div class="box">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
          <thead class="table-secondary">
            <tr>
              <th scope="col">#</th>
              <th scope="col">Name</th>
              <th scope="col">Email</th>
              <th scope="col">Mobile</th>
              <th scope="col">Position</th>
              <th scope="col">Cover Letter</th>
              <th scope="col">Submitted</th>
              <th scope="col">Resume</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($leads as $lead): ?>
              <tr>
                <td><?= (int)$lead['id'] ?></td>
                <td><?= htmlspecialchars((string)($lead['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if (!empty($lead['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($lead['email'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($lead['mobile'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($lead['position'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php
                  $cover = (string)($lead['cover_letter'] ?? '');
                  $trimmedCover = mb_strlen($cover) > 80 ? mb_substr($cover, 0, 77) . '…' : $cover;
                  ?>
                  <?= $trimmedCover === '' ? '<span class="text-muted">—</span>' : htmlspecialchars($trimmedCover, ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td>
                  <?php if (!empty($lead['created_at'])): ?>
                    <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$lead['created_at'])), ENT_QUOTES, 'UTF-8') ?>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($lead['has_resume'])): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($buildDownloadUrl((int)$lead['id'], $page), ENT_QUOTES, 'UTF-8') ?>">
                      Download Resume
                    </a>
                  <?php else: ?>
                    <span class="text-muted">No resume</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Delete lead"
                    data-delete-job-lead="1"
                    data-lead-id="<?= htmlspecialchars((string)$lead['id'], ENT_QUOTES, 'UTF-8') ?>"
                    data-lead-name="<?= htmlspecialchars((string)($lead['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <i class="bi bi-trash"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($totalPages > 1): ?>
        <nav aria-label="Job leads pagination" class="mt-3">
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
<form method="post" class="d-none" id="delete-job-lead-form">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="lead_id" id="delete-job-lead-id" value="">
</form>
<script>
  (function() {
    const deleteForm = document.getElementById('delete-job-lead-form');
    const deleteIdInput = document.getElementById('delete-job-lead-id');

    document.querySelectorAll('[data-delete-job-lead="1"]').forEach(button => {
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
  })();
</script>
<?php
echo '</div>';
echo '</div>';
render_footer();
