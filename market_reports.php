<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';

/**
 * Ensure the market_reports table exists before we attempt to insert data.
 */
function market_reports_ensure_table(PDO $pdo): void
{
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS market_reports (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      heading VARCHAR(255) NOT NULL,
      subheading VARCHAR(255) NOT NULL,
      short_description TEXT NOT NULL,
      long_description LONGTEXT NOT NULL,
      mockup_heading VARCHAR(255) NOT NULL,
      mockup_description TEXT NOT NULL,
      report_image_path VARCHAR(255) DEFAULT NULL,
      report_pdf_path VARCHAR(255) DEFAULT NULL,
      report_mockup_path VARCHAR(255) DEFAULT NULL,
      form_banner_path VARCHAR(255) DEFAULT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );
}

/**
 * Handle storing an uploaded file for the market report form.
 *
 * @return array{0: ?string, 1: ?string} The stored relative path and any error message.
 */
function market_reports_store_upload(?array $file, array $allowedMimeTypes, string $label, string $subDirectory = ''): array
{
  if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    return [null, null];
  }

  if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
    return [null, sprintf('There was a problem uploading the %s. Please try again.', $label)];
  }

  $tmpName = (string)($file['tmp_name'] ?? '');
  if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    return [null, sprintf('Invalid upload received for the %s.', $label)];
  }

  $finfo = finfo_open(FILEINFO_MIME_TYPE);
  $mime = $finfo ? finfo_file($finfo, $tmpName) : null;
  if ($finfo) {
    finfo_close($finfo);
  }

  if (!isset($allowedMimeTypes[$mime ?? ''])) {
    $extensions = array_unique(array_map(static fn(string $ext): string => strtoupper($ext), array_values($allowedMimeTypes)));
    $extensionList = implode(', ', $extensions);
    return [
      null,
      sprintf('Only %s files are allowed for the %s.', $extensionList !== '' ? $extensionList : 'the specified', $label),
    ];
  }

  $baseRelativeDir = 'assets/uploads/market_reports';
  $subDirectory = trim($subDirectory, '/');
  $relativeDir = $baseRelativeDir . ($subDirectory !== '' ? '/' . $subDirectory : '');
  $absoluteDir = __DIR__ . '/' . $relativeDir;

  if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
    return [null, 'Unable to prepare the upload directory.'];
  }

  try {
    $fileName = bin2hex(random_bytes(8));
  } catch (Throwable $e) {
    error_log('Failed to generate market report upload filename: ' . $e->getMessage());
    return [null, sprintf('Unable to process the %s. Please try again.', $label)];
  }

  $extension = $allowedMimeTypes[$mime ?? ''];
  $relativePath = $relativeDir . '/' . $fileName . '.' . $extension;
  $absolutePath = __DIR__ . '/' . $relativePath;

  if (!move_uploaded_file($tmpName, $absolutePath)) {
    error_log(sprintf('Failed to move uploaded %s to %s', $label, $absolutePath));
    return [null, sprintf('Failed to save the uploaded %s. Please try again.', $label)];
  }

  return [$relativePath, null];
}

process_logout();

if (!is_authenticated()) {
  header('Location: login.php');
  exit;
}

$errors = [];
$success = null;
$shouldResetForm = false;

$pdo = null;
$storageError = null;

try {
  $pdo = db();
  market_reports_ensure_table($pdo);
} catch (Throwable $e) {
  error_log('Failed to prepare market_reports table: ' . $e->getMessage());
  $storageError = 'Unable to prepare the market reports storage. Please try again later.';
  $pdo = null;
}

if ($storageError !== null) {
  $errors[] = $storageError;
}

$heading = '';
$subheading = '';
$shortDescription = '';
$longDescription = '';
$mockupHeading = '';
$mockupDescription = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  rl_hit('save-market-report', 25);

  $heading = trim((string)($_POST['heading'] ?? ''));
  $subheading = trim((string)($_POST['subheading'] ?? ''));
  $shortDescription = trim((string)($_POST['short_description'] ?? ''));
  $longDescription = trim((string)($_POST['long_description'] ?? ''));
  $mockupHeading = trim((string)($_POST['mockup_heading'] ?? ''));
  $mockupDescription = trim((string)($_POST['mockup_description'] ?? ''));

  if ($heading === '') {
    $errors[] = 'Heading is required.';
  }

  if ($subheading === '') {
    $errors[] = 'Subheading is required.';
  }

  if ($shortDescription === '') {
    $errors[] = 'Short description is required.';
  }

  if ($longDescription === '') {
    $errors[] = 'Long description is required.';
  }

  if ($mockupHeading === '') {
    $errors[] = 'Mockup heading is required.';
  }

  if ($mockupDescription === '') {
    $errors[] = 'Mockup description is required.';
  }

  $reportImagePath = null;
  $reportPdfPath = null;
  $reportMockupPath = null;
  $formBannerPath = null;

  if (!$errors && $storageError === null && $pdo instanceof PDO) {
    [$reportImagePath, $uploadError] = market_reports_store_upload(
      $_FILES['report_image'] ?? null,
      [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
      ],
      'Report Image',
      'images'
    );
    if ($uploadError) {
      $errors[] = $uploadError;
    }

    [$reportPdfPath, $pdfError] = market_reports_store_upload(
      $_FILES['report_pdf'] ?? null,
      [
        'application/pdf' => 'pdf',
      ],
      'Report PDF',
      'documents'
    );
    if ($pdfError) {
      $errors[] = $pdfError;
    }

    [$reportMockupPath, $mockupUploadError] = market_reports_store_upload(
      $_FILES['report_mockup'] ?? null,
      [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
      ],
      'Report Mockup',
      'mockups'
    );
    if ($mockupUploadError) {
      $errors[] = $mockupUploadError;
    }

    [$formBannerPath, $bannerUploadError] = market_reports_store_upload(
      $_FILES['form_banner'] ?? null,
      [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
      ],
      'Form Banner',
      'banners'
    );
    if ($bannerUploadError) {
      $errors[] = $bannerUploadError;
    }
  }

  if (!$errors && $storageError === null && $pdo instanceof PDO) {
    try {
      $stmt = $pdo->prepare(
        'INSERT INTO market_reports (
            heading,
            subheading,
            short_description,
            long_description,
            mockup_heading,
            mockup_description,
            report_image_path,
            report_pdf_path,
            report_mockup_path,
            form_banner_path,
            created_at
          ) VALUES (
            :heading,
            :subheading,
            :short_description,
            :long_description,
            :mockup_heading,
            :mockup_description,
            :report_image_path,
            :report_pdf_path,
            :report_mockup_path,
            :form_banner_path,
            NOW()
          )'
      );

      $stmt->execute([
        ':heading'            => $heading,
        ':subheading'         => $subheading,
        ':short_description'  => $shortDescription,
        ':long_description'   => $longDescription,
        ':mockup_heading'     => $mockupHeading,
        ':mockup_description' => $mockupDescription,
        ':report_image_path'  => $reportImagePath,
        ':report_pdf_path'    => $reportPdfPath,
        ':report_mockup_path' => $reportMockupPath,
        ':form_banner_path'   => $formBannerPath,
      ]);

      $success = 'Your Market Report Data has been submitted Successfully.';
      $shouldResetForm = true;

      $heading = '';
      $subheading = '';
      $shortDescription = '';
      $longDescription = '';
      $mockupHeading = '';
      $mockupDescription = '';
    } catch (Throwable $e) {
      error_log('Failed to save market report: ' . $e->getMessage());
      $errors[] = 'An unexpected error occurred while saving the market report. Please try again.';
    }
  }
}

render_head('Market Reports');
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('market-reports');
?>

<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-4 gap-3">
    <div>
      <h2 class="title-heading">Market Reports</h2>
      <p class="para mb-0">Create a new market report by providing descriptive copy and the related media assets.</p>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="alert alert-success" role="alert" data-auto-dismiss="5000">
      <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
      <ul class="mb-0 ps-3">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
      <h3 class="h5 mb-0">Add Market Report Form</h3>
    </div>
    <div class="card-body">
      <form
        id="market-report-form"
        class="row g-3"
        method="post"
        enctype="multipart/form-data"
        novalidate
        data-reset-on-load="<?= $shouldResetForm ? '1' : '0' ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-12">
          <label class="form-label" for="heading">Heading</label>
          <input
            type="text"
            class="form-control"
            id="heading"
            name="heading"
            value="<?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>"
            required>
        </div>

        <div class="col-12">
          <label class="form-label" for="subheading">Subheading</label>
          <input
            type="text"
            class="form-control"
            id="subheading"
            name="subheading"
            value="<?= htmlspecialchars($subheading, ENT_QUOTES, 'UTF-8') ?>"
            required>
        </div>

        <div class="col-12">
          <label class="form-label" for="short_description">Short description</label>
          <textarea
            class="form-control rich-text-editor"
            id="short_description"
            name="short_description"
            rows="4"
            required><?= htmlspecialchars($shortDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="form-text">Provide a concise overview of the report to help users understand its focus.</div>
        </div>

        <div class="col-12">
          <label class="form-label" for="long_description">Add Long Descriptions</label>
          <textarea
            class="form-control rich-text-editor"
            id="long_description"
            name="long_description"
            rows="6"
            required><?= htmlspecialchars($longDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="form-text">Provide detailed insights, methodology, and supporting data for the report.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_image">Upload Report Image</label>
          <div class="upload-box">
            <input class="form-control file-input" type="file" id="report_image" name="report_image" accept="image/*">
            <div class="upload-content">
              <img src="assets/images/file.png" alt="" width="30px">
              <p>Drop files here or click to upload</p>
            </div>
          </div>
          <div class="form-text">Preferred formats: JPG, PNG or WEBP.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_pdf">Upload Report PDF</label>
          <div class="upload-box">
            <input class="form-control file-input" type="file" id="report_pdf" name="report_pdf" accept="application/pdf">
            <div class="upload-content">
              <img src="assets/images/file.png" alt="" width="30px">
              <p>Drop files here or click to upload</p>
            </div>
          </div>
          <div class="form-text">Attach the full market report in PDF format.</div>
        </div>

        <div class="col-12">
          <label class="form-label" for="mockup_heading">Mockup Heading</label>
          <input
            type="text"
            class="form-control"
            id="mockup_heading"
            name="mockup_heading"
            value="<?= htmlspecialchars($mockupHeading, ENT_QUOTES, 'UTF-8') ?>"
            required>
        </div>

        <div class="col-12">
          <label class="form-label" for="mockup_description">Mockup Description</label>
          <textarea
            class="form-control rich-text-editor"
            id="mockup_description"
            name="mockup_description"
            rows="4"
            required><?= htmlspecialchars($mockupDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_mockup">Upload Report Mockup</label>
          <div class="upload-box">
            <input class="form-control file-input" type="file" id="report_mockup" name="report_mockup" accept="image/*">
            <div class="upload-content">
              <img src="assets/images/file.png" alt="" width="30px">
              <p>Drop files here or click to upload</p>
            </div>
          </div>
          <div class="form-text">Upload a mockup or preview image that showcases the report design.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="form_banner">Upload Form Banner</label>
          <div class="upload-box">
            <input class="form-control file-input" type="file" id="form_banner" name="form_banner" accept="image/*">
            <div class="upload-content">
              <img src="assets/images/file.png" alt="" width="30px">
              <p>Drop files here or click to upload</p>
            </div>
          </div>
          <div class="form-text">This banner appears above the report download form on the public site.</div>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Save Market Report</button>
        </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/@ckeditor/ckeditor5-build-classic@39.0.1/build/ckeditor.js"></script>
  <script>
    (() => {
      const form = document.getElementById('market-report-form');

      document.querySelectorAll('.alert[data-auto-dismiss]').forEach((alertEl) => {
        const delay = parseInt(alertEl.dataset.autoDismiss ?? '', 10);
        if (delay > 0) {
          setTimeout(() => {
            alertEl.classList.add('d-none');
          }, delay);
        }
      });

      const shouldResetForm = form && form.dataset.resetOnLoad === '1';

      if (!window.ClassicEditor) {
        if (shouldResetForm && form) {
          form.reset();
        }
        return;
      }

      const editors = [];

      const syncEditorData = () => {
        editors.forEach(({
          editor,
          textarea
        }) => {
          textarea.value = editor.getData();
        });
      };

      const resetEditors = () => {
        editors.forEach(({
          editor,
          textarea
        }) => {
          const defaultValue = textarea.dataset.defaultValue ?? '';
          editor.setData(defaultValue);
          textarea.value = defaultValue;
        });
      };

      if (form) {
        form.addEventListener('submit', syncEditorData);
        form.addEventListener('reset', resetEditors);

        if (shouldResetForm) {
          form.reset();
        }
      }

      document.querySelectorAll('.rich-text-editor').forEach((textarea) => {
        const defaultValue = textarea.value;
        textarea.dataset.defaultValue = defaultValue;

        ClassicEditor
          .create(textarea)
          .then((editor) => {
            editors.push({
              editor,
              textarea
            });

            if (textarea.hasAttribute('required')) {
              textarea.removeAttribute('required');
            }
          })
          .catch((error) => {
            console.error('CKEditor initialization failed', error);
          });
      });
    })();
  </script>
</main>

<?php
render_footer();
