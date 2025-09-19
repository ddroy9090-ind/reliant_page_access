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
$submissions = [];
$error = null;

try {
  $stmt = $pdo->query(
    'SELECT id, full_name, email, phone, company, ip_address, user_agent, created_at FROM popup_enquiry ORDER BY created_at DESC'
  );
  $submissions = $stmt->fetchAll();
} catch (Throwable $e) {
  $error = 'Unable to load popup form submissions at this time.';
}

$totalSubmissions = count($submissions);

render_head('Popup Form Submissions');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('popup-form');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">Popup Form Submissions</h2>
      <p class="para mb-0">View enquiries captured from the popup enquiry form.</p>
    </div>
    <div class="text-lg-end">
      <span class="badge bg-primary-subtle text-primary fw-semibold">Total submissions: <?= number_format($totalSubmissions) ?></span>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-warning" role="alert">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php elseif (!$submissions): ?>
    <div class="alert alert-info" role="alert">
      No popup form submissions found.
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
              <th scope="col">Phone</th>
              <th scope="col">Company</th>
              <th scope="col">IP Address</th>
              <th scope="col">User Agent</th>
              <th scope="col">Submitted At</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($submissions as $submission): ?>
              <?php
                $createdAt = $submission['created_at'] ?? '';
                $createdAtFormatted = '—';
                if ($createdAt) {
                  try {
                    $createdAtFormatted = (new DateTime($createdAt))->format('d M Y H:i');
                  } catch (Throwable $e) {
                    $createdAtFormatted = (string)$createdAt;
                  }
                }
              ?>
              <tr>
                <td><?= (int)$submission['id'] ?></td>
                <td><?= htmlspecialchars((string)($submission['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if (!empty($submission['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($submission['email'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($submission['email'], ENT_QUOTES, 'UTF-8') ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($submission['phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($submission['company'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($submission['ip_address'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-break">
                  <?php if (!empty($submission['user_agent'])): ?>
                    <small><?= htmlspecialchars($submission['user_agent'], ENT_QUOTES, 'UTF-8') ?></small>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($createdAtFormatted, ENT_QUOTES, 'UTF-8') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</main>
<?php

echo '</div>';
echo '</div>';
render_footer();
