<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle ?? 'PhPstrap') ?></title>
  <meta name="description" content="<?= htmlspecialchars($metaDescription ?? '') ?>">
  <meta name="keywords" content="<?= htmlspecialchars($metaKeywords ?? '') ?>">

  <!-- Optional Open Graph -->
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? '') ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription ?? '') ?>">
  <?php if (!empty($metaOgImage)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($metaOgImage) ?>">
  <?php endif; ?>

  <?php include __DIR__ . '/header-scripts.php'; ?>
</head>
<body>

<?php include __DIR__ . '/nav.php'; ?>