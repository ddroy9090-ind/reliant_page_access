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
$errors = [];
$success = null;

$heading = trim((string)($_POST['heading'] ?? ''));
$bannerDescription = trim((string)($_POST['banner_description'] ?? ''));
$authorName = trim((string)($_POST['author_name'] ?? ''));
$content = trim((string)($_POST['content'] ?? ''));
$imagePath = null;
$pendingUpload = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    csrf_check($_POST['csrf'] ?? '');
    rl_hit('add-blog', 20);

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

    $file = $_FILES['image'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Please upload a blog image.';
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      $errors[] = 'There was a problem uploading the image. Please try again.';
    } else {
      $tmpName = $file['tmp_name'] ?? '';
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
          $pendingUpload = [
            'tmp_name'  => $tmpName,
            'extension' => $allowed[$mime],
          ];
        }
      }
    }

    if (!$errors && $pendingUpload) {
      $uploadDir = __DIR__ . '/assets/uploads/blogs';
      if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        $errors[] = 'Unable to prepare the upload directory.';
      } else {
        $safeName = bin2hex(random_bytes(8));
        $targetPath = $uploadDir . '/' . $safeName . '.' . $pendingUpload['extension'];
        if (!move_uploaded_file($pendingUpload['tmp_name'], $targetPath)) {
          $errors[] = 'Failed to save the uploaded image.';
        } else {
          $imagePath = 'assets/uploads/blogs/' . $safeName . '.' . $pendingUpload['extension'];
        }
      }
    }

    if (!$errors) {
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

      $stmt = $pdo->prepare(
        'INSERT INTO blogs (image_path, heading, banner_description, author_name, content, created_at)
         VALUES (:image_path, :heading, :banner_description, :author_name, :content, NOW())'
      );
      $stmt->execute([
        ':image_path'          => $imagePath,
        ':heading'             => $heading,
        ':banner_description'  => $bannerDescription,
        ':author_name'         => $authorName,
        ':content'             => $content,
      ]);

      $success = 'Blog entry added successfully.';
      $heading = $bannerDescription = $authorName = $content = '';
      $imagePath = null;
    }
  } catch (Throwable $e) {
    error_log('Add blog failure: ' . $e->getMessage());
    $errors[] = 'An unexpected error occurred while saving the blog entry.';
  }
}

render_head('Add Blogs');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('add-blogs');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading">Add Blogs</h2>
      <p class="para mb-0">Create a new blog entry for the website.</p>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success" role="alert" id="blog-success-alert">
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
      <div class="col-12">
        <label for="image" class="form-label">Upload Blog Images</label>
        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
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
      <div class="col-12">
        <label for="content" class="form-label">Text Editor for Blog Details Page</label>
        <textarea class="form-control" id="content" name="content" rows="10" required><?= htmlspecialchars($content, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="form-text">Use the editor to format headings, links, images, and more.</div>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Submit Blog</button>
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
    ClassicEditor
      .create(textarea)
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
    if (form) {
      form.reset();
    }

    window.setTimeout(() => {
      alertBox.classList.add('d-none');
    }, 5000);
  })();
</script>
<?php
render_footer();
