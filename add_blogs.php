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

/**
 * Ensure the blogs table exists before performing any operations.
 */
function add_blogs_ensure_table(PDO $pdo): void
{
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS blogs (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      image_path VARCHAR(255) NOT NULL,
      heading VARCHAR(255) NOT NULL,
      banner_description TEXT NOT NULL,
      author_name VARCHAR(255) NOT NULL,
      content LONGTEXT NOT NULL,
      meta_title VARCHAR(255) DEFAULT NULL,
      meta_keywords TEXT DEFAULT NULL,
      meta_description TEXT DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );

  $columnsToEnsure = [
    'meta_title'       => 'ALTER TABLE blogs ADD COLUMN meta_title VARCHAR(255) DEFAULT NULL',
    'meta_keywords'    => 'ALTER TABLE blogs ADD COLUMN meta_keywords TEXT DEFAULT NULL',
    'meta_description' => 'ALTER TABLE blogs ADD COLUMN meta_description TEXT DEFAULT NULL',
  ];

  foreach ($columnsToEnsure as $column => $alterSql) {
    try {
      $stmt = $pdo->prepare('SHOW COLUMNS FROM blogs LIKE :column');
      $stmt->execute([':column' => $column]);
      $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable $columnCheckError) {
      $exists = false;
    }

    if ($exists) {
      continue;
    }

    try {
      $pdo->exec($alterSql);
    } catch (Throwable $alterError) {
      error_log(sprintf('Failed to ensure column %s on blogs table: %s', $column, $alterError->getMessage()));
    }
  }
}

/**
 * Fetch a single blog entry by id.
 */
function add_blogs_find_blog(PDO $pdo, int $id): ?array
{
  $stmt = $pdo->prepare('SELECT * FROM blogs WHERE id = :id');
  $stmt->execute([':id' => $id]);
  $blog = $stmt->fetch();

  return $blog ?: null;
}

$errors = [];
$success = null;

$heading = '';
$bannerDescription = '';
$authorName = '';
$content = '';
$metaTitle = '';
$metaKeywords = '';
$metaDescription = '';
$currentImagePath = '';
$editingId = 0;
$isEditing = false;
$existingBlog = null;

$pdo = db();

$imagePath = null;

try {
  add_blogs_ensure_table($pdo);
} catch (Throwable $e) {
  error_log('Failed to ensure blogs table exists: ' . $e->getMessage());
  $errors[] = 'Unable to prepare the blogs storage. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $oldImageToDelete = null;
  $newImageAbsolutePath = null;

  try {
    csrf_check($_POST['csrf'] ?? '');

    $editingId = (int)($_POST['blog_id'] ?? 0);
    $isEditing = $editingId > 0;

    if ($isEditing) {
      rl_hit('update-blog', 20);
    } else {
      rl_hit('add-blog', 20);
    }

    $heading = trim((string)($_POST['heading'] ?? ''));
    $bannerDescription = trim((string)($_POST['banner_description'] ?? ''));
    $authorName = trim((string)($_POST['author_name'] ?? ''));
    $content = trim((string)($_POST['content'] ?? ''));
    $metaTitle = trim((string)($_POST['meta_title'] ?? ''));
    $metaKeywords = trim((string)($_POST['meta_keywords'] ?? ''));
    $metaDescription = trim((string)($_POST['meta_description'] ?? ''));

    if ($isEditing) {
      try {
        $existingBlog = add_blogs_find_blog($pdo, $editingId);
      } catch (Throwable $fetchError) {
        error_log('Failed to load blog for editing: ' . $fetchError->getMessage());
        $existingBlog = null;
      }

      if (!$existingBlog) {
        $errors[] = 'The selected blog could not be found.';
        $isEditing = false;
        $editingId = 0;
      } else {
        $currentImagePath = (string)($existingBlog['image_path'] ?? '');
      }
    }

    if ($heading === '') {
      $errors[] = 'Blog Heading is required.';
    }

    if ($bannerDescription === '') {
      $errors[] = 'Blog Banner Description is required.';
    }

    if ($authorName === '') {
      $errors[] = 'Author Name is required.';
    }

    if ($content === '') {
      $errors[] = 'Blog Details content is required.';
    }

    $imagePath = $currentImagePath;
    $file = $_FILES['image'] ?? null;

    if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
      if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $errors[] = 'There was a problem uploading the image. Please try again.';
      } else {
        $tmpName = (string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmpName)) {
          $errors[] = 'Invalid upload received.';
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
            $errors[] = 'Only JPEG, PNG, GIF, or WEBP images are allowed.';
          } else {
            $uploadDir = __DIR__ . '/assets/uploads/blogs';
            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
              $errors[] = 'Unable to prepare the upload directory.';
            } else {
              try {
                $safeName = bin2hex(random_bytes(8));
              } catch (Throwable $randomError) {
                error_log('Failed to generate image filename: ' . $randomError->getMessage());
                $errors[] = 'Unable to process the uploaded image. Please try again.';
                $safeName = null;
              }

              if ($safeName) {
                $targetPath = $uploadDir . '/' . $safeName . '.' . $allowed[$mime];
                if (!move_uploaded_file($tmpName, $targetPath)) {
                  $errors[] = 'Failed to save the uploaded image.';
                } else {
                  $imagePath = 'assets/uploads/blogs/' . $safeName . '.' . $allowed[$mime];
                  $newImageAbsolutePath = $targetPath;
                  if ($isEditing) {
                    $oldImageToDelete = $currentImagePath;
                  }
                }
              }
            }
          }
        }
      }
    } elseif (!$isEditing) {
      $errors[] = 'Please upload a blog image.';
    }

    if (!$errors) {
      if ($isEditing) {
        $stmt = $pdo->prepare(
          'UPDATE blogs
              SET image_path = :image_path,
                  heading = :heading,
                  banner_description = :banner_description,
                  author_name = :author_name,
                  content = :content,
                  meta_title = :meta_title,
                  meta_keywords = :meta_keywords,
                  meta_description = :meta_description
            WHERE id = :id'
        );
        $stmt->execute([
          ':image_path'         => $imagePath,
          ':heading'            => $heading,
          ':banner_description' => $bannerDescription,
          ':author_name'        => $authorName,
          ':content'            => $content,
          ':meta_title'         => $metaTitle !== '' ? $metaTitle : null,
          ':meta_keywords'      => $metaKeywords !== '' ? $metaKeywords : null,
          ':meta_description'   => $metaDescription !== '' ? $metaDescription : null,
          ':id'                 => $editingId,
        ]);

        $success = 'Blog updated successfully.';
        $currentImagePath = (string)$imagePath;

        if ($oldImageToDelete && $currentImagePath !== $oldImageToDelete) {
          $uploadsDir = realpath(__DIR__ . '/assets/uploads/blogs');
          $oldImageFull = __DIR__ . '/' . ltrim($oldImageToDelete, '/');
          if ($uploadsDir && is_file($oldImageFull)) {
            $oldImageReal = realpath($oldImageFull);
            if ($oldImageReal && strncmp($oldImageReal, $uploadsDir, strlen($uploadsDir)) === 0) {
              @unlink($oldImageReal);
            }
          }
        }
      } else {
        $stmt = $pdo->prepare(
          'INSERT INTO blogs (
              image_path,
              heading,
              banner_description,
              author_name,
              content,
              meta_title,
              meta_keywords,
              meta_description,
              created_at
            ) VALUES (
              :image_path,
              :heading,
              :banner_description,
              :author_name,
              :content,
              :meta_title,
              :meta_keywords,
              :meta_description,
              NOW()
            )'
        );
        $stmt->execute([
          ':image_path'          => $imagePath,
          ':heading'             => $heading,
          ':banner_description'  => $bannerDescription,
          ':author_name'         => $authorName,
          ':content'             => $content,
          ':meta_title'          => $metaTitle !== '' ? $metaTitle : null,
          ':meta_keywords'       => $metaKeywords !== '' ? $metaKeywords : null,
          ':meta_description'    => $metaDescription !== '' ? $metaDescription : null,
        ]);

        $success = 'Your Blog has been added successfully.';
        $heading = $bannerDescription = $authorName = $content = '';
        $metaTitle = $metaKeywords = $metaDescription = '';
        $currentImagePath = '';
        $editingId = 0;
        $isEditing = false;
      }
    }
  } catch (Throwable $e) {
    error_log('Add blog failure: ' . $e->getMessage());
    $errors[] = $isEditing
      ? 'An unexpected error occurred while updating the blog entry.'
      : 'An unexpected error occurred while saving the blog entry.';

    if (isset($newImageAbsolutePath) && $newImageAbsolutePath && is_file($newImageAbsolutePath)) {
      @unlink($newImageAbsolutePath);
    }
  }
} else {
  $editingId = (int)($_GET['id'] ?? 0);
  if ($editingId > 0 && !$errors) {
    try {
      $existingBlog = add_blogs_find_blog($pdo, $editingId);
    } catch (Throwable $fetchError) {
      error_log('Failed to load blog for editing: ' . $fetchError->getMessage());
      $existingBlog = null;
    }

    if ($existingBlog) {
      $isEditing = true;
      $heading = (string)($existingBlog['heading'] ?? '');
      $bannerDescription = (string)($existingBlog['banner_description'] ?? '');
      $authorName = (string)($existingBlog['author_name'] ?? '');
      $content = (string)($existingBlog['content'] ?? '');
      $metaTitle = (string)($existingBlog['meta_title'] ?? '');
      $metaKeywords = (string)($existingBlog['meta_keywords'] ?? '');
      $metaDescription = (string)($existingBlog['meta_description'] ?? '');
      $currentImagePath = (string)($existingBlog['image_path'] ?? '');
    } else {
      $errors[] = 'The requested blog could not be found or may have been removed.';
    }
  }
}

$pageTitle = $isEditing ? 'Edit Blog' : 'Add Blogs';
$pageDescription = $isEditing
  ? 'Update an existing blog entry and keep your content up to date.'
  : 'Create a new blog entry for the website.';
$resetFormOnSuccess = $success !== null && !$isEditing;

render_head($pageTitle);
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('add-blogs');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="para mb-0"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <?php if ($isEditing && $editingId > 0): ?>
    <div class="alert alert-info" role="alert">
      You are editing blog #<?= htmlspecialchars((string)$editingId, ENT_QUOTES, 'UTF-8') ?>.
      <a href="all_blogs.php" class="alert-link">View all blogs</a>
      or <a href="add_blogs.php" class="alert-link">create a new blog entry</a> instead.
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success fade show" role="alert" id="blog-success-alert"<?php if ($resetFormOnSuccess): ?> data-reset-form="1"<?php endif; ?>>
      <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="box">
    <form method="post" enctype="multipart/form-data" class="row g-3" id="add-blog-form">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="blog_id" value="<?= htmlspecialchars($isEditing ? (string)$editingId : '', ENT_QUOTES, 'UTF-8') ?>">
      <div class="col-12">
        <label for="image" class="form-label">Blog Image</label>
        <input type="file" class="form-control" id="image" name="image" accept="image/*"<?php if (!$isEditing): ?> required<?php endif; ?>>
        <div class="form-text">
          <?php if ($isEditing): ?>
            Upload a new image to replace the existing one. Leave this field empty to keep the current image.
          <?php else: ?>
            Upload a featured image for the blog post.
          <?php endif; ?>
        </div>
        <?php if ($isEditing): ?>
          <div class="small text-muted mt-1">
            <?php if ($currentImagePath !== ''): ?>
              Current image:
              <a href="<?= htmlspecialchars($currentImagePath, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">View</a>
            <?php else: ?>
              This blog does not currently have an image.
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="col-12 col-md-6">
        <label for="heading" class="form-label">Blog Heading</label>
        <input type="text" class="form-control" id="heading" name="heading" value="<?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label for="author_name" class="form-label">Author Name</label>
        <input type="text" class="form-control" id="author_name" name="author_name" value="<?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-12">
        <label for="banner_description" class="form-label">Blog Banner Description</label>
        <textarea class="form-control" id="banner_description" name="banner_description" rows="3" required><?= htmlspecialchars($bannerDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="col-12 col-md-6">
        <label for="meta_title" class="form-label">Meta Title</label>
        <input type="text" class="form-control" id="meta_title" name="meta_title" value="<?= htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
        <div class="form-text">Optional title used for SEO metadata.</div>
      </div>
      <div class="col-12 col-md-6">
        <label for="meta_keywords" class="form-label">Meta Keywords</label>
        <textarea class="form-control" id="meta_keywords" name="meta_keywords" rows="1"><?= htmlspecialchars($metaKeywords, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="form-text">Optional comma-separated keywords for search engines.</div>
      </div>
      <div class="col-12">
        <label for="meta_description" class="form-label">Meta Description</label>
        <textarea class="form-control" id="meta_description" name="meta_description" rows="3"><?= htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="form-text">Optional description that appears in search results.</div>
      </div>
      <div class="col-12">
        <label for="content" class="form-label">Text Editor for Blog Details Page</label>
        <textarea class="form-control" id="content" name="content" rows="10" required><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="form-text">Use the editor to format headings, links, images, and more.</div>
      </div>
      <div class="col-12">
        <div class="d-flex justify-content-end gap-2">
          <?php if ($isEditing): ?>
            <a href="add_blogs.php" class="btn btn-outline-secondary">Cancel Editing</a>
          <?php endif; ?>
          <button type="submit" class="btn btn-primary"><?= $isEditing ? 'Update Blog' : 'Submit Blog' ?></button>
        </div>
      </div>
    </form>
  </div>
</main>
<?php
echo '</div>';
echo '</div>';
?>
<script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@39.0.1/build/ckeditor.js"></script>
<script>
  (function() {
    const textarea = document.querySelector('#content');
    if (!textarea) {
      return;
    }
    window.blogEditorReadyQueue = window.blogEditorReadyQueue || [];
    ClassicEditor
      .create(textarea)
      .then(editor => {
        window.blogEditorInstance = editor;
        if (textarea.hasAttribute('required')) {
          textarea.removeAttribute('required');
        }
        window.blogEditorReadyQueue.forEach(callback => {
          try {
            callback(editor);
          } catch (callbackError) {
            console.error('Blog editor ready callback failed', callbackError);
          }
        });
        window.blogEditorReadyQueue = [];

        const form = document.getElementById('add-blog-form');
        if (form) {
          form.addEventListener('submit', () => {
            textarea.value = editor.getData();
          });
          form.addEventListener('reset', () => {
            editor.setData('');
            textarea.value = '';
          });
        }
      })
      .catch(error => {
        console.error('CKEditor initialization failed', error);
      });
  })();
</script>
<script>
  (function() {
    const alertBox = document.getElementById('blog-success-alert');
    if (!alertBox) {
      return;
    }

    const form = document.getElementById('add-blog-form');
    const shouldReset = alertBox.getAttribute('data-reset-form') === '1';

    if (shouldReset) {
      if (form) {
        form.reset();
      }

      const resetEditor = editor => {
        if (editor && typeof editor.setData === 'function') {
          editor.setData('');
        }
        const textarea = document.getElementById('content');
        if (textarea) {
          textarea.value = '';
        }
      };

      if (window.blogEditorInstance) {
        resetEditor(window.blogEditorInstance);
      } else {
        window.blogEditorReadyQueue = window.blogEditorReadyQueue || [];
        window.blogEditorReadyQueue.push(resetEditor);
      }
    }

    window.setTimeout(() => {
      alertBox.classList.remove('show');
      alertBox.classList.add('d-none');
    }, 5000);
  })();
</script>
<?php
render_footer();
