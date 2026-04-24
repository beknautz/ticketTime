<footer class="bg-dark text-light py-4 mt-5">
  <div class="container">
    <div class="row">
      <div class="col-md-6">
        <h6 class="fw-bold"><i class="bi bi-ticket-perforated-fill me-1"></i><?= SITE_NAME ?></h6>
        <p class="text-muted small mb-0">Secure online event ticketing.</p>
      </div>
      <div class="col-md-6 text-md-end mt-3 mt-md-0">
        <p class="small mb-0">
          Need help? <a href="mailto:<?= e(SUPPORT_EMAIL) ?>" class="text-light"><?= e(SUPPORT_EMAIL) ?></a>
          <?php if (SUPPORT_PHONE): ?> &bull; <?= e(SUPPORT_PHONE) ?><?php endif; ?>
        </p>
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
