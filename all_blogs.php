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
$formErrors = [];
$prefillData = null;

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

  if ($action === 'update') {
    rl_hit('update-blog', 20);

    $blogId = (int)($_POST['blog_id'] ?? 0);
    $heading = trim((string)($_POST['heading'] ?? ''));
    $bannerDescription = trim((string)($_POST['banner_description'] ?? ''));
    $authorName = trim((string)($_POST['author_name'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));

    $existingBlog = null;
    if ($blogId <= 0) {
      $formErrors[] = 'Invalid blog selected.';
    } else {
      try {
        $stmt = $pdo->prepare('SELECT * FROM blogs WHERE id = :id');
        $stmt->execute([':id' => $blogId]);
        $existingBlog = $stmt->fetch();
        if (!$existingBlog) {
          $formErrors[] = 'The selected blog could not be found.';
        }
      } catch (Throwable $e) {
        error_log('Failed to load blog for editing: ' . $e->getMessage());
        $formErrors[] = 'Unable to load the blog for editing. Please try again.';
      }
    }

    if ($heading === '') {
      $formErrors[] = 'Blog Heading is required.';
    }
    if ($bannerDescription === '') {
      $formErrors[] = 'Blog Banner Description is required.';
    }
    if ($authorName === '') {
      $formErrors[] = 'Author Name is required.';
    }
    if ($content === '') {
      $formErrors[] = 'Blog Details content is required.';
    }

    $newImagePath = $existingBlog['image_path'] ?? '';
    $oldImageToDelete = null;
    $newImageAbsolutePath = null;

    if (!$formErrors && $existingBlog) {
      $file = $_FILES['image'] ?? null;
      if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
          $formErrors[] = 'There was a problem uploading the image. Please try again.';
        } else {
          $tmpName = (string)($file['tmp_name'] ?? '');
          if (!is_uploaded_file($tmpName)) {
            $formErrors[] = 'Invalid upload received.';
          } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $tmpName) : null;
            if ($finfo) {
              finfo_close($finfo);
            }
            $allowed = [
              'image/jpeg' => 'jpg',
              'image/png'  => 'png',
              'image/gif'  => 'gif',
              'image/webp' => 'webp',
            ];
            if (!isset($allowed[$mime ?? ''])) {
              $formErrors[] = 'Only JPEG, PNG, GIF, or WEBP images are allowed.';
            } else {
              $uploadDir = __DIR__ . '/assets/uploads/blogs';
              if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $formErrors[] = 'Unable to prepare the upload directory.';
              } else {
                try {
                  $safeName = bin2hex(random_bytes(8));
                } catch (Throwable $e) {
                  error_log('Failed to generate image filename: ' . $e->getMessage());
                  $formErrors[] = 'Unable to process the uploaded image. Please try again.';
                  $safeName = null;
                }
                if ($safeName) {
                  $targetPath = $uploadDir . '/' . $safeName . '.' . $allowed[$mime];
                  if (!move_uploaded_file($tmpName, $targetPath)) {
                    $formErrors[] = 'Failed to save the uploaded image.';
                  } else {
                    $newImagePath = 'assets/uploads/blogs/' . $safeName . '.' . $allowed[$mime];
                    $oldImageToDelete = (string)($existingBlog['image_path'] ?? '');
                    $newImageAbsolutePath = $targetPath;
                  }
                }
              }
            }
          }
        }
      }
    }

    if (!$formErrors && $existingBlog) {
      try {
        $stmt = $pdo->prepare(
          'UPDATE blogs
             SET image_path = :image_path,
                 heading = :heading,
                 banner_description = :banner_description,
                 author_name = :author_name,
                 content = :content
           WHERE id = :id'
        );
        $stmt->execute([
          ':image_path'         => $newImagePath,
          ':heading'            => $heading,
          ':banner_description' => $bannerDescription,
          ':author_name'        => $authorName,
          ':content'            => $content,
          ':id'                 => $blogId,
        ]);

        if ($oldImageToDelete) {
          $uploadsDir = realpath(__DIR__ . '/assets/uploads/blogs');
          $oldImageFull = __DIR__ . '/' . ltrim($oldImageToDelete, '/');
          if ($uploadsDir && is_file($oldImageFull)) {
            $oldImageReal = realpath($oldImageFull);
            if ($oldImageReal && strncmp($oldImageReal, $uploadsDir, strlen($uploadsDir)) === 0) {
              @unlink($oldImageReal);
            }
          }
        }

        $_SESSION['blog_success'] = 'Blog updated successfully.';
        header('Location: all_blogs.php');
        exit;
      } catch (Throwable $e) {
        error_log('Failed to update blog: ' . $e->getMessage());
        $formErrors[] = 'An unexpected error occurred while updating the blog.';
        if ($newImageAbsolutePath && is_file($newImageAbsolutePath)) {
          @unlink($newImageAbsolutePath);
        }
      }
    }

    if ($formErrors) {
      $prefillData = [
        'id'                 => $blogId,
        'heading'            => $heading,
        'banner_description' => $bannerDescription,
        'author_name'        => $authorName,
        'content'            => $content,
        'image_path'         => (string)($existingBlog['image_path'] ?? ''),
      ];
    }
  } elseif ($action === 'delete') {
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
    $formErrors[] = 'Unsupported action requested.';
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
      <p class="para mb-0">Review existing blog posts and manage their content.</p>
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

  <div class="box mb-4">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
      <h3 class="h5 mb-0">Edit Blog</h3>
      <span class="badge text-bg-light">Updates are saved instantly after submission.</span>
    </div>

    <?php if ($formErrors): ?>
      <div class="alert alert-danger" role="alert">
        <ul class="mb-0">
          <?php foreach ($formErrors as $formError): ?>
            <li><?= htmlspecialchars((string)$formError, ENT_QUOTES, 'UTF-8') ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <p class="text-muted small mb-3" id="edit-blog-helper">
      Select a blog from the table below and click &ldquo;Edit&rdquo; to load its details.
    </p>

    <form method="post" enctype="multipart/form-data" class="row g-3" id="edit-blog-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="blog_id" id="edit-blog-id" value="">

      <div class="col-12">
        <label for="edit-blog-image" class="form-label">Blog Image</label>
        <input type="file" class="form-control" id="edit-blog-image" name="image" accept="image/*">
        <div class="form-text">Leave empty to keep the current image.</div>
        <div class="small text-muted mt-1" id="current-image-preview"></div>
      </div>

      <div class="col-12 col-md-6">
        <label for="edit-blog-heading" class="form-label">Blog Heading</label>
        <input type="text" class="form-control" id="edit-blog-heading" name="heading" value="" required>
      </div>

      <div class="col-12 col-md-6">
        <label for="edit-blog-author" class="form-label">Author Name</label>
        <input type="text" class="form-control" id="edit-blog-author" name="author_name" value="" required>
      </div>

      <div class="col-12">
        <label for="edit-blog-banner" class="form-label">Blog Banner Description</label>
        <textarea class="form-control" id="edit-blog-banner" name="banner_description" rows="3" required></textarea>
      </div>

      <div class="col-12">
        <label for="edit-blog-content" class="form-label">Blog Details</label>
        <textarea class="form-control" id="edit-blog-content" name="content" rows="8" required></textarea>
      </div>

      <div class="col-12 d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-secondary" id="edit-blog-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="edit-blog-submit">Update Blog</button>
      </div>
    </form>
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
                    <button type="button"
                            class="btn btn-sm btn-outline-primary"
                            title="Edit blog"
                            data-edit-blog="1"
                            data-blog-id="<?= htmlspecialchars((string)$blogId, ENT_QUOTES, 'UTF-8') ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
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
$prefillJson = $prefillData
  ? json_encode(
      [
        'id'                 => (int)($prefillData['id'] ?? 0),
        'heading'            => (string)($prefillData['heading'] ?? ''),
        'banner_description' => (string)($prefillData['banner_description'] ?? ''),
        'author_name'        => (string)($prefillData['author_name'] ?? ''),
        'content'            => (string)($prefillData['content'] ?? ''),
        'image_path'         => (string)($prefillData['image_path'] ?? ''),
      ],
      JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    )
  : 'null';
?>
<script>
  window.allBlogsData = <?= $blogsJson ?: '[]' ?>;
  window.blogServerPrefill = <?= $prefillJson ?>;
</script>
<script>
  (function () {
    const form = document.getElementById('edit-blog-form');
    if (!form) {
      return;
    }

    const fields = {
      id: document.getElementById('edit-blog-id'),
      image: document.getElementById('edit-blog-image'),
      heading: document.getElementById('edit-blog-heading'),
      author: document.getElementById('edit-blog-author'),
      banner: document.getElementById('edit-blog-banner'),
      content: document.getElementById('edit-blog-content'),
    };
    const submitBtn = document.getElementById('edit-blog-submit');
    const cancelBtn = document.getElementById('edit-blog-cancel');
    const helper = document.getElementById('edit-blog-helper');
    const currentImage = document.getElementById('current-image-preview');
    const deleteForm = document.getElementById('delete-blog-form');
    const deleteId = document.getElementById('delete-blog-id');
    let activeRow = null;

    const blogsData = Array.isArray(window.allBlogsData) ? window.allBlogsData : [];

    const setFormEnabled = enabled => {
      const elements = [fields.image, fields.heading, fields.author, fields.banner, fields.content, submitBtn, cancelBtn];
      elements.forEach(el => {
        if (!el) {
          return;
        }
        if (enabled) {
          el.removeAttribute('disabled');
        } else {
          el.setAttribute('disabled', 'disabled');
        }
      });
    };

    const clearActiveRow = () => {
      if (activeRow) {
        activeRow.classList.remove('table-active');
        activeRow = null;
      }
    };

    const showCurrentImage = imagePath => {
      if (!currentImage) {
        return;
      }
      const value = (imagePath || '').toString().trim();
      if (value === '') {
        currentImage.textContent = 'No image selected.';
        return;
      }
      const encoded = encodeURI(value);
      currentImage.innerHTML = `Current image: <a href="${encoded}" target="_blank" rel="noopener">View</a>`;
    };

    const updateHelper = blogId => {
      if (!helper) {
        return;
      }
      if (blogId) {
        helper.textContent = `Editing blog #${blogId}. Update the fields and submit to save changes.`;
      } else {
        helper.textContent = 'Select a blog from the table below and click “Edit” to load its details.';
      }
    };

    const populateForm = blog => {
      if (!blog) {
        return;
      }
      setFormEnabled(true);
      if (fields.id) fields.id.value = blog.id || '';
      if (fields.heading) fields.heading.value = blog.heading || '';
      if (fields.author) fields.author.value = blog.author_name || '';
      if (fields.banner) fields.banner.value = blog.banner_description || '';
      if (fields.content) fields.content.value = blog.content || '';
      if (fields.image) fields.image.value = '';
      showCurrentImage(blog.image_path || '');
      updateHelper(blog.id);

      clearActiveRow();
      const row = document.querySelector(`tr[data-blog-id="${blog.id}"]`);
      if (row) {
        row.classList.add('table-active');
        activeRow = row;
      }

      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const resetForm = () => {
      form.reset();
      if (fields.id) fields.id.value = '';
      showCurrentImage('');
      setFormEnabled(false);
      updateHelper('');
      clearActiveRow();
    };

    setFormEnabled(false);
    showCurrentImage('');
    updateHelper('');

    if (cancelBtn) {
      cancelBtn.addEventListener('click', event => {
        event.preventDefault();
        resetForm();
      });
    }

    document.querySelectorAll('[data-edit-blog="1"]').forEach(button => {
      button.addEventListener('click', () => {
        const blogId = parseInt(button.getAttribute('data-blog-id') || '', 10);
        if (!blogId) {
          return;
        }
        const blog = blogsData.find(item => Number(item.id) === blogId);
        if (blog) {
          populateForm(blog);
        }
      });
    });

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

    if (window.blogServerPrefill) {
      populateForm(window.blogServerPrefill);
    }

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
