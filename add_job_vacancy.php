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

function job_vacancy_ensure_table(PDO $pdo): void
{
  $pdo->exec(
    'CREATE TABLE IF NOT EXISTS job_vacancies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      banner_path VARCHAR(255) NOT NULL,
      banner_description TEXT NOT NULL,
      job_posted_date DATE NOT NULL,
      job_title VARCHAR(255) NOT NULL,
      job_id VARCHAR(100) NOT NULL,
      vacancy VARCHAR(255) NOT NULL,
      education VARCHAR(255) NOT NULL,
      gender VARCHAR(20) NOT NULL,
      location VARCHAR(255) NOT NULL,
      job_description LONGTEXT NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
  );
}

$pdo = db();

$errors = [];
$success = null;

$bannerDescription = '';
$jobPostedDate = '';
$jobTitle = '';
$jobId = '';
$vacancy = '';
$education = '';
$gender = '';
$location = '';
$jobDescription = '';
$uploadedBannerPath = '';

try {
  job_vacancy_ensure_table($pdo);
  $columnCheck = $pdo->query("SHOW COLUMNS FROM job_vacancies LIKE 'job_title'");
  if ($columnCheck && $columnCheck->fetchColumn() === false) {
    $pdo->exec("ALTER TABLE job_vacancies ADD COLUMN job_title VARCHAR(255) NOT NULL DEFAULT '' AFTER job_posted_date");
  }

  $columnCheck = $pdo->query("SHOW COLUMNS FROM job_vacancies LIKE 'job_id'");
  if ($columnCheck && $columnCheck->fetchColumn() === false) {
    $pdo->exec("ALTER TABLE job_vacancies ADD COLUMN job_id VARCHAR(100) NOT NULL DEFAULT '' AFTER job_title");
  }
} catch (Throwable $tableError) {
  error_log('Failed to ensure job_vacancies table exists: ' . $tableError->getMessage());
  $errors[] = 'Unable to prepare the job vacancy storage. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $newBannerAbsolutePath = null;

  try {
    csrf_check($_POST['csrf'] ?? '');
    rl_hit('add-job-vacancy', 20);

    $bannerDescription = trim((string)($_POST['banner_description'] ?? ''));
    $jobPostedDate = trim((string)($_POST['job_posted_date'] ?? ''));
    $jobTitle = trim((string)($_POST['job_title'] ?? ''));
    $jobId = trim((string)($_POST['job_id'] ?? ''));
    $vacancy = trim((string)($_POST['vacancy'] ?? ''));
    $education = trim((string)($_POST['education'] ?? ''));
    $gender = strtolower(trim((string)($_POST['gender'] ?? '')));
    $location = trim((string)($_POST['location'] ?? ''));
    $jobDescription = trim((string)($_POST['job_description'] ?? ''));

    if ($bannerDescription === '') {
      $errors[] = 'Career Banner Description is required.';
    }

    if ($jobPostedDate === '') {
      $errors[] = 'Job Posted Date is required.';
    } else {
      $date = DateTimeImmutable::createFromFormat('Y-m-d', $jobPostedDate);
      if (!$date) {
        $errors[] = 'Job Posted Date must be a valid date.';
      } else {
        $jobPostedDate = $date->format('Y-m-d');
      }
    }

    if ($jobTitle === '') {
      $errors[] = 'Job Title is required.';
    }

    if ($jobId === '') {
      $errors[] = 'Job ID is required.';
    }

    if ($vacancy === '') {
      $errors[] = 'Vacancy information is required.';
    }

    if ($education === '') {
      $errors[] = 'Education requirement is required.';
    }

    $allowedGenders = ['male', 'female'];
    if ($gender === '') {
      $errors[] = 'Please select a gender requirement.';
    } elseif (!in_array($gender, $allowedGenders, true)) {
      $errors[] = 'Invalid gender selection.';
    }

    if ($location === '') {
      $errors[] = 'Location is required.';
    }

    if ($jobDescription === '') {
      $errors[] = 'Job Description is required.';
    }

    $file = $_FILES['career_banner'] ?? null;
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      $errors[] = 'Please upload a career banner image.';
    } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
      $errors[] = 'There was a problem uploading the career banner. Please try again.';
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
          $errors[] = 'Only JPEG, PNG, GIF, or WEBP images are allowed for the career banner.';
        } else {
          $uploadDir = __DIR__ . '/assets/uploads/careers';
          if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            $errors[] = 'Unable to prepare the upload directory for career banners.';
          } else {
            try {
              $safeName = bin2hex(random_bytes(8));
            } catch (Throwable $randomError) {
              error_log('Failed to generate career banner filename: ' . $randomError->getMessage());
              $errors[] = 'Unable to process the uploaded career banner. Please try again.';
              $safeName = null;
            }

            if ($safeName) {
              $targetPath = $uploadDir . '/' . $safeName . '.' . $allowed[$mime];
              if (!move_uploaded_file($tmpName, $targetPath)) {
                $errors[] = 'Failed to save the uploaded career banner.';
              } else {
                $uploadedBannerPath = 'assets/uploads/careers/' . $safeName . '.' . $allowed[$mime];
                $newBannerAbsolutePath = $targetPath;
              }
            }
          }
        }
      }
    }

    if (!$errors) {
      $storedGender = $gender === 'female' ? 'Female' : 'Male';
      $stmt = $pdo->prepare(
        'INSERT INTO job_vacancies (
            banner_path,
            banner_description,
            job_posted_date,
            job_title,
            job_id,
            vacancy,
            education,
            gender,
            location,
            job_description,
            created_at
          ) VALUES (
            :banner_path,
            :banner_description,
            :job_posted_date,
            :job_title,
            :job_id,
            :vacancy,
            :education,
            :gender,
            :location,
            :job_description,
            NOW()
          )'
      );

      $stmt->execute([
        ':banner_path'        => $uploadedBannerPath,
        ':banner_description' => $bannerDescription,
        ':job_posted_date'    => $jobPostedDate,
        ':job_title'          => $jobTitle,
        ':job_id'             => $jobId,
        ':vacancy'            => $vacancy,
        ':education'          => $education,
        ':gender'             => $storedGender,
        ':location'           => $location,
        ':job_description'    => $jobDescription,
      ]);

      $success = 'Job vacancy created successfully.';
      $bannerDescription = $jobPostedDate = $jobTitle = $jobId = $vacancy = $education = $location = $jobDescription = '';
      $gender = '';
      $uploadedBannerPath = '';
    } elseif ($newBannerAbsolutePath && is_file($newBannerAbsolutePath)) {
      @unlink($newBannerAbsolutePath);
      $uploadedBannerPath = '';
    }
  } catch (Throwable $error) {
    error_log('Failed to add job vacancy: ' . $error->getMessage());
    $errors[] = 'An unexpected error occurred while saving the job vacancy. Please try again.';

    if ($newBannerAbsolutePath && is_file($newBannerAbsolutePath)) {
      @unlink($newBannerAbsolutePath);
    }
  }
}

$pageTitle = 'Add Job Vacancy';
$pageDescription = 'Create a new job opportunity for the careers page.';

render_head($pageTitle);
echo '<div class="container-fluid layout">';
echo '<div class="row g-0">';
render_sidebar('add-job-vacancy');
?>
<main class="col-12 col-md-9 col-lg-10 content">
  <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
    <div>
      <h2 class="title-heading"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h2>
      <p class="para mb-0"><?= htmlspecialchars($pageDescription, ENT_QUOTES, 'UTF-8') ?></p>
    </div>
  </div>

  <?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
      <h4 class="alert-heading">Please fix the following issues:</h4>
      <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
          <li><?= htmlspecialchars((string)$error, ENT_QUOTES, 'UTF-8') ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success" role="alert">
      <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
    </div>
  <?php endif; ?>

  <div class="box">
    <form id="add-job-vacancy-form" method="post" enctype="multipart/form-data" class="row g-4" novalidate>
      <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <div class="col-12">
        <label for="career_banner" class="form-label">Upload Career Banner</label>
        <input type="file" class="form-control" id="career_banner" name="career_banner" accept="image/jpeg,image/png,image/gif,image/webp" required>
        <div class="form-text">Supported formats: JPG, PNG, GIF, or WEBP.</div>
      </div>
      <div class="col-12">
        <label for="banner_description" class="form-label">Career Banner Description</label>
        <textarea class="form-control" id="banner_description" name="banner_description" rows="3" required><?= htmlspecialchars($bannerDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>
      <div class="col-12 col-md-6">
        <label for="job_posted_date" class="form-label">Job Posted Date</label>
        <input type="date" class="form-control" id="job_posted_date" name="job_posted_date" value="<?= htmlspecialchars($jobPostedDate, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label for="job_title" class="form-label">Job Title</label>
        <input type="text" class="form-control" id="job_title" name="job_title" value="<?= htmlspecialchars($jobTitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., Senior Analyst" required>
      </div>
      <div class="col-12 col-md-6">
        <label for="job_id" class="form-label">Job ID</label>
        <input type="text" class="form-control" id="job_id" name="job_id" value="<?= htmlspecialchars($jobId, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., JOB-2024-001" required>
      </div>
      <div class="col-12 col-md-6">
        <label for="vacancy" class="form-label">Vacancy</label>
        <input type="text" class="form-control" id="vacancy" name="vacancy" value="<?= htmlspecialchars($vacancy, ENT_QUOTES, 'UTF-8') ?>" placeholder="e.g., 1 Person" required>
      </div>
      <div class="col-12 col-md-6">
        <label for="education" class="form-label">Education</label>
        <input type="text" class="form-control" id="education" name="education" value="<?= htmlspecialchars($education, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label d-block">Gender</label>
        <div class="d-flex align-items-center gap-4">
          <div class="form-check">
            <input class="form-check-input" type="radio" name="gender" id="gender_male" value="male" <?= $gender === 'male' ? 'checked' : '' ?> required>
            <label class="form-check-label" for="gender_male">Male</label>
          </div>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="gender" id="gender_female" value="female" <?= $gender === 'female' ? 'checked' : '' ?>>
            <label class="form-check-label" for="gender_female">Female</label>
          </div>
        </div>
      </div>
      <div class="col-12 col-md-6">
        <label for="location" class="form-label">Location</label>
        <input type="text" class="form-control" id="location" name="location" value="<?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>" required>
      </div>
      <div class="col-12">
        <label for="job_description" class="form-label">Job Description</label>
        <textarea class="form-control" id="job_description" name="job_description" rows="10" required><?= htmlspecialchars($jobDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="form-text">Use the rich text editor to format the job description.</div>
      </div>
      <div class="col-12">
        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Create Job Vacancy</button>
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
    const textarea = document.getElementById('job_description');
    if (!textarea) {
      return;
    }

    ClassicEditor
      .create(textarea)
      .then(editor => {
        const form = document.getElementById('add-job-vacancy-form');
        if (form) {
          form.addEventListener('submit', () => {
            textarea.value = editor.getData();
          });
        }
      })
      .catch(error => {
        console.error('CKEditor initialization failed', error);
      });
  })();
</script>
<?php
render_footer();
