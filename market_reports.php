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

$errors = [];
$success = null;

$heading = '';
$subheading = '';
$shortDescription = '';
$mockupHeading = '';
$mockupDescription = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $heading = trim((string)($_POST['heading'] ?? ''));
  $subheading = trim((string)($_POST['subheading'] ?? ''));
  $shortDescription = trim((string)($_POST['short_description'] ?? ''));
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

  if ($mockupHeading === '') {
    $errors[] = 'Mockup heading is required.';
  }

  if ($mockupDescription === '') {
    $errors[] = 'Mockup description is required.';
  }

  if (!$errors) {
    $success = 'Market report details captured successfully. File upload handling can be implemented to persist the report.';
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
    <div class="alert alert-success" role="alert">
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
      <form class="row g-3" method="post" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="col-12">
          <label class="form-label" for="heading">Heading</label>
          <input
            type="text"
            class="form-control"
            id="heading"
            name="heading"
            value="<?= htmlspecialchars($heading, ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <div class="col-12">
          <label class="form-label" for="subheading">Subheading</label>
          <input
            type="text"
            class="form-control"
            id="subheading"
            name="subheading"
            value="<?= htmlspecialchars($subheading, ENT_QUOTES, 'UTF-8') ?>"
            required
          >
        </div>

        <div class="col-12">
          <label class="form-label" for="short_description">Short description</label>
          <textarea
            class="form-control"
            id="short_description"
            name="short_description"
            rows="4"
            required
          ><?= htmlspecialchars($shortDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
          <div class="form-text">Provide a concise overview of the report to help users understand its focus.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_image">Upload Report Image</label>
          <input class="form-control" type="file" id="report_image" name="report_image" accept="image/*">
          <div class="form-text">Preferred formats: JPG, PNG or WEBP.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_pdf">Upload Report PDF</label>
          <input class="form-control" type="file" id="report_pdf" name="report_pdf" accept="application/pdf">
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
            required
          >
        </div>

        <div class="col-12">
          <label class="form-label" for="mockup_description">Mockup Description</label>
          <textarea
            class="form-control"
            id="mockup_description"
            name="mockup_description"
            rows="4"
            required
          ><?= htmlspecialchars($mockupDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="report_mockup">Upload Report Mockup</label>
          <input class="form-control" type="file" id="report_mockup" name="report_mockup" accept="image/*">
          <div class="form-text">Upload a mockup or preview image that showcases the report design.</div>
        </div>

        <div class="col-md-6">
          <label class="form-label" for="form_banner">Upload Form Banner</label>
          <input class="form-control" type="file" id="form_banner" name="form_banner" accept="image/*">
          <div class="form-text">This banner appears above the report download form on the public site.</div>
        </div>

        <div class="col-12 d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Save Market Report</button>
        </div>
      </form>
    </div>
  </div>
</main>

<?php
render_footer();
