<?php
$pageTitle   = $pageTitle ?? SITE_NAME;
$bodyClass   = $bodyClass ?? '';
$extraHead   = $extraHead ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> &mdash; <?= SITE_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= SITE_URL ?>/public/assets/css/style.css">
  <?= $extraHead ?>
</head>
<body class="<?= e($bodyClass) ?>">
