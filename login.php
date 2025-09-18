<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/render.php';
require_once __DIR__ . '/includes/auth.php';

if (is_authenticated()) {
  header('Location: index.php');
  exit;
}

handle_login('index.php');
