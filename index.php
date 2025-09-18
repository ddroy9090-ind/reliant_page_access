<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/handlers.php';

process_logout();

if (!is_authenticated()) {
  handle_login();
}

$pdo = db();

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
  handle_logs_ajax($pdo);
}

if (isset($_GET['export'])) {
  handle_export($pdo);
}

if (isset($_GET['analytics'])) {
  handle_analytics($pdo);
}

render_layout();
