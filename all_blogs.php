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
$error = null;
$blogs = [];

try {
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS blogs (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      image_path VARCHAR(255) NOT NULL,
      heading VARCHAR(255) NOT NULL,
      banner_description TEXT NOT NULL,
      author_name VARCHAR(255) NOT NULL,
      content LONGTEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );

  $stmt = $pdo->query(
    'SELECT id, image_path, heading, banner_description, author_name, created_at
       FROM blogs
      ORDER BY created_at DESC, id DESC'
  );
  $blogs = $stmt->fetchAll();
} catch (Throwable $e) {
  error_log('Failed to fetch blogs: ' . $e->getMessage());
  $error = 'Unable to load blogs at the moment. Please try again later.';
}

function all_blogs_excerpt(string $text, int $length = 120): string
{
  $stripped = trim(strip_tags($text));
  if ($stripped === '') {
    return '';
  }

  if (function_exists('mb_strimwidth')) {
    return mb_strimwidth($stripped, 0, $length, '…', 'UTF-8');
  }

  $excerpt = substr($stripped, 0, $length);
  if (strlen($stripped) > $length) {
    $excerpt .= '…';
  }
  return $excerpt;
}

function all_blogs_format_date(?string $value): string
{
  if ($value === null || $value === '') {
    return '—';
  }

  try {
    $dt = new DateTimeImmutable($value);
    return $dt->format('M j, Y g:i A');
  } catch (Throwable $e) {
    return $value;
  }
}

render_head('All Blogs');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('all-blogs');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">All Blogs</h2>
      <p class="para mb-0">Review existing blog posts and manage their content.</p>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="box">
    <div class="table-responsive">
      <table class="table table-striped table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th scope="col" class="text-nowrap">ID</th>
            <th scope="col" class="text-nowrap">Image</th>
            <th scope="col">Blog Details</th>
            <th scope="col" class="text-nowrap">Author</th>
            <th scope="col" class="text-nowrap">Published</th>
            <th scope="col" class="text-nowrap">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($blogs): ?>
            <?php foreach ($blogs as $blog): ?>
              <?php
                $imagePath = (string)($blog['image_path'] ?? '');
                $heading = (string)($blog['heading'] ?? '');
                $banner = (string)($blog['banner_description'] ?? '');
                $author = (string)($blog['author_name'] ?? '');
                $createdAt = $blog['created_at'] ?? null;
                $excerpt = all_blogs_excerpt($banner);
                $formattedDate = all_blogs_format_date(is_string($createdAt) ? $createdAt : null);
              ?>
              <tr>
                <td class="fw-semibold text-nowrap">#<?= htmlspecialchars((string)($blog['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if ($imagePath !== ''): ?>
                    <img src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                         alt="Blog image for <?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>"
                         class="rounded"
                         style="width: 80px; height: 50px; object-fit: cover;">
                  <?php else: ?>
                    <span class="text-muted">No image</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="fw-semibold mb-1"><?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php if ($excerpt !== ''): ?>
                    <div class="text-muted small"><?= htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap"><?= htmlspecialchars($author, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-nowrap"><?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" title="Edit blog">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete blog">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" class="text-center py-4 text-muted">No blog entries found.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
<?php
echo '</div>';
echo '</div>';
render_footer();
