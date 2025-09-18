<?php
declare(strict_types=1);

function is_authenticated(): bool
{
  return ($_SESSION['auth'] ?? false) === true;
}

function authenticate(): void
{
  $_SESSION['auth'] = true;
}

function process_logout(): void
{
  if (!isset($_POST['logout'])) {
    return;
  }
  csrf_check($_POST['csrf'] ?? '');
  session_destroy();
  header('Location: login.php');
  exit;
}

function handle_login(string $redirect = 'index.php'): void
{
  $error = null;
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rl_hit('login', 10);
    csrf_check($_POST['csrf'] ?? '');
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === ADMIN_USER && $p === ADMIN_PASS) {
      authenticate();
      header('Location: ' . $redirect);
      exit;
    }
    $error = 'Invalid username or password';
  }
  render_login_page($error);
  exit;
}
