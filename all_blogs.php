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

$successMessage = $_SESSION['blog_success'] ?? null;
unset($_SESSION['blog_success']);
$operationError = $_SESSION['blog_error'] ?? null;
unset($_SESSION['blog_error']);

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
} catch (Throwable $e) {
  error_log('Failed to ensure blogs table exists: ' . $e->getMessage());
  $error = 'Unable to load blogs at the moment. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === null) {
  csrf_check($_POST['csrf'] ?? '');
  $action = (string)($_POST['action'] ?? '');

  if ($action === 'delete') {
    rl_hit('delete-blog', 20);
    $blogId = (int)($_POST['blog_id'] ?? 0);

    if ($blogId <= 0) {
      $_SESSION['blog_error'] = 'Invalid blog selected for deletion.';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT image_path FROM blogs WHERE id = :id');
        $stmt->execute([':id' => $blogId]);
        $blog = $stmt->fetch();

        if (!$blog) {
          $_SESSION['blog_error'] = 'The selected blog could not be found.';
        } else {
          $stmt = $pdo->prepare('DELETE FROM blogs WHERE id = :id');
          $stmt->execute([':id' => $blogId]);

          $imagePath = (string)($blog['image_path'] ?? '');
          if ($imagePath !== '') {
            $uploadsDir = realpath(__DIR__ . '/assets/uploads/blogs');
            $imageFull = __DIR__ . '/' . ltrim($imagePath, '/');
            if ($uploadsDir && is_file($imageFull)) {
              $imageReal = realpath($imageFull);
              if ($imageReal && strncmp($imageReal, $uploadsDir, strlen($uploadsDir)) === 0) {
                @unlink($imageReal);
              }
            }
          }

          $_SESSION['blog_success'] = 'Blog deleted successfully.';
        }
      } catch (Throwable $e) {
        error_log('Failed to delete blog: ' . $e->getMessage());
        $_SESSION['blog_error'] = 'An unexpected error occurred while deleting the blog.';
      }
    }

    header('Location: all_blogs.php');
    exit;
  } else {
    $_SESSION['blog_error'] = 'Unsupported action requested.';
    header('Location: all_blogs.php');
    exit;
  }
}

if ($error === null) {
  try {
    $stmt = $pdo->query(
      'SELECT id, image_path, heading, banner_description, author_name, content, created_at
         FROM blogs
        ORDER BY created_at DESC, id DESC'
    );
    $blogs = $stmt->fetchAll();
  } catch (Throwable $e) {
    error_log('Failed to fetch blogs: ' . $e->getMessage());
    $error = 'Unable to load blogs at the moment. Please try again later.';
  }
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

$blogsForJson = array_map(
  static fn(array $blog): array => [
    'id'                 => (int)($blog['id'] ?? 0),
    'image_path'         => (string)($blog['image_path'] ?? ''),
    'heading'            => (string)($blog['heading'] ?? ''),
    'banner_description' => (string)($blog['banner_description'] ?? ''),
    'author_name'        => (string)($blog['author_name'] ?? ''),
    'content'            => (string)($blog['content'] ?? ''),
  ],
  $blogs
);

render_head('All Blogs');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('all-blogs');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">All Blogs</h2>
      <p class="para mb-0">Review existing blog posts, edit them through the Add Blogs form, and manage their content.</p>
    </div>
  </div>

  <?php if ($successMessage): ?>
    <div class="alert alert-success fade show" role="alert" id="blog-operation-alert">
      <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($operationError): ?>
    <div class="alert alert-danger" role="alert">
      <?= htmlspecialchars($operationError, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
      <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="alert alert-info" role="alert">
    To edit an existing blog entry, open it in the
    <a href="add_blogs.php" class="alert-link">Add Blogs</a> form and update the content from there.
  </div>

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
                $blogId = (int)($blog['id'] ?? 0);
                $imagePath = (string)($blog['image_path'] ?? '');
                $heading = (string)($blog['heading'] ?? '');
                $banner = (string)($blog['banner_description'] ?? '');
                $author = (string)($blog['author_name'] ?? '');
                $createdAt = $blog['created_at'] ?? null;
                $excerpt = all_blogs_excerpt($banner);
                $formattedDate = all_blogs_format_date(is_string($createdAt) ? $createdAt : null);
              ?>
              <tr data-blog-id="<?= htmlspecialchars((string)$blogId, ENT_QUOTES, 'UTF-8') ?>">
                <td class="fw-semibold text-nowrap">#<?= htmlspecialchars((string)$blogId, ENT_QUOTES, 'UTF-8') ?></td>
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
                    <a class="btn btn-sm btn-outline-primary"
                       href="add_blogs.php?id=<?= htmlspecialchars((string)$blogId, ENT_QUOTES, 'UTF-8') ?>"
                       title="Edit blog">
                      <i class="bi bi-pencil-square"></i>
                    </a>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            title="Delete blog"
                            data-delete-blog="1"
                            data-blog-id="<?= htmlspecialchars((string)$blogId, ENT_QUOTES, 'UTF-8') ?>">
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
<form method="post" class="d-none" id="delete-blog-form">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
  <input type="hidden" name="action" value="delete">
  <input type="hidden" name="blog_id" id="delete-blog-id" value="">
</form>
<?php
$blogsJson = json_encode($blogsForJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<script>
  window.allBlogsData = <?= $blogsJson ?: '[]' ?>;
</script>
<script>
  (function () {
    const deleteForm = document.getElementById('delete-blog-form');
    const deleteId = document.getElementById('delete-blog-id');
    const blogsData = Array.isArray(window.allBlogsData) ? window.allBlogsData : [];

    document.querySelectorAll('[data-delete-blog="1"]').forEach(button => {
      button.addEventListener('click', () => {
        const blogId = parseInt(button.getAttribute('data-blog-id') || '', 10);
        if (!blogId || !deleteForm || !deleteId) {
          return;
        }
        const blog = blogsData.find(item => Number(item.id) === blogId);
        const title = blog && blog.heading ? blog.heading : 'this blog';
        const confirmDelete = window.confirm(`Are you sure you want to delete "${title}"? This action cannot be undone.`);
        if (!confirmDelete) {
          return;
        }
        deleteId.value = String(blogId);
        deleteForm.submit();
      });
    });

    const alertBox = document.getElementById('blog-operation-alert');
    if (alertBox) {
      window.setTimeout(() => {
        alertBox.classList.remove('show');
        alertBox.classList.add('d-none');
      }, 5000);
    }
  })();
</script>
<?php
render_footer();
