<?php
$pageTitle = $pageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &mdash; <?= SITE_NAME ?> Admin</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/public/assets/css/style.css">
  <?= $extraHead ?? '' ?>
</head>
<body>
<!-- Top nav -->
<nav class="navbar navbar-dark bg-dark navbar-expand-lg">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= SITE_URL ?>/admin/index.php">
      <i class="bi bi-ticket-perforated-fill me-1"></i><?= SITE_NAME ?> Admin
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav ms-auto align-items-center gap-2">
        <?php if (in_array(currentAdminRole(), ['admin', 'scanner', 'box_office'])): ?>
        <li class="nav-item d-md-none">
          <a class="nav-link fw-semibold text-warning" href="<?= SITE_URL ?>/public/scan.php">
            <i class="bi bi-camera-fill me-1"></i>Gate Scanner
          </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/public/index.php" target="_blank">
            <i class="bi bi-box-arrow-up-right me-1"></i>View Site
          </a>
        </li>
        <li class="nav-item">
          <span class="nav-link text-muted small">
            <i class="bi bi-person-circle me-1"></i><?= e(currentAdminName()) ?>
            <span class="badge bg-secondary ms-1"><?= e(currentAdminRole()) ?></span>
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?= SITE_URL ?>/admin/logout.php">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid">
<div class="row">
  <!-- Sidebar -->
  <nav class="col-md-2 d-none d-md-block admin-sidebar py-3">
    <ul class="nav flex-column">
      <?php
      $role = currentAdminRole();
      $current = basename($_SERVER['PHP_SELF']);

      $navItems = [];
      if (in_array($role, ['admin', 'box_office'])) {
          $navItems[] = ['index.php', 'bi-speedometer2', 'Dashboard'];
      }
      if (in_array($role, ['admin'])) {
          $navItems[] = ['events.php', 'bi-calendar-event', 'Events'];
          $navItems[] = ['ticket-types.php', 'bi-tags', 'Ticket Types'];
      }
      if (in_array($role, ['admin', 'box_office'])) {
          $navItems[] = ['orders.php', 'bi-receipt', 'Orders'];
      }
      if (in_array($role, ['admin', 'scanner'])) {
          $navItems[] = ['scans.php', 'bi-qr-code-scan', 'Scan Report'];
      }
      if (in_array($role, ['admin'])) {
          $navItems[] = ['reports.php', 'bi-bar-chart-line', 'Reports'];
          $navItems[] = ['settings.php', 'bi-gear', 'Site Settings'];
      }
      // Operational links
      $navItems[] = ['__sep__', '', ''];
      if (in_array($role, ['admin', 'scanner', 'box_office'])) {
          $navItems[] = ['__link__' . SITE_URL . '/public/scan.php', 'bi-camera', 'Gate Scanner'];
          $navItems[] = ['__link__' . SITE_URL . '/public/willcall.php', 'bi-person-badge', 'Will Call'];
      }

      foreach ($navItems as [$file, $icon, $label]):
          if ($file === '__sep__'):
      ?>
          <li class="nav-item mt-2"><small class="text-muted px-3 opacity-50 text-uppercase small">Operations</small></li>
      <?php elseif (strncmp($file, '__link__', 8) === 0):
          $link = substr($file, 8);
      ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= $link ?>">
              <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
            </a>
          </li>
      <?php else: ?>
          <li class="nav-item">
            <a class="nav-link <?= $current === $file ? 'active' : '' ?>"
               href="<?= SITE_URL ?>/admin/<?= $file ?>">
              <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
            </a>
          </li>
      <?php endif; endforeach; ?>
    </ul>
  </nav>

  <!-- Main Content -->
  <main class="col-md-10 ms-sm-auto px-md-4 py-4">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <?= renderFlash() ?>
