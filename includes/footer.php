<?php
$_footerEmail    = getSiteSetting('support_email', SUPPORT_EMAIL);
$_footerPhone    = getSiteSetting('support_phone', SUPPORT_PHONE);
$_footerLogoPath = BASE_PATH . '/public/assets/img/logo.png';
$_footerLogo     = file_exists($_footerLogoPath) ? (SITE_URL . '/assets/img/logo.png') : null;
?>
<footer class="bg-dark text-light py-4 mt-5">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6">
        <?php if ($_footerLogo): ?>
          <img src="<?= e($_footerLogo) ?>?<?= filemtime($_footerLogoPath) ?>"
               alt="<?= e(SITE_NAME) ?>" style="max-height:50px;max-width:180px" class="mb-1">
        <?php else: ?>
          <h6 class="fw-bold mb-1"><i class="bi bi-ticket-perforated-fill me-1"></i><?= SITE_NAME ?></h6>
        <?php endif; ?>
        <p class="text-muted small mb-0">Secure online event ticketing.</p>
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <?php if ($_footerEmail): ?>
          <p class="small mb-0">
            Need help? <a href="mailto:<?= e($_footerEmail) ?>" class="text-light"><?= e($_footerEmail) ?></a>
            <?php if ($_footerPhone): ?> &bull; <?= e($_footerPhone) ?><?php endif; ?>
          </p>
        <?php endif; ?>
        <p class="text-muted small mt-1">&copy; <?= date('Y') ?> <?= SITE_NAME ?></p>
      </div>
    </div>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/htmx.org@1.9.12/dist/htmx.min.js"></script>
<script src="<?= SITE_URL ?>/public/assets/js/app.js"></script>
<?= $extraScript ?? '' ?>
</body>
</html>
