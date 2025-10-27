<?php
$pageTitle = 'Search – LexBoard';
$metaDescription = 'Search Canadian court decisions to power LexBoard metrics.';
$metaKeywords = 'LexBoard, search, legal analytics, Canada';
include __DIR__ . '/includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
?>
<main class="container py-5">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h3 mb-0">Search</h1>
    <?php if ($q): ?>
      <span class="text-muted small">Query: <code><?= htmlspecialchars($q) ?></code></span>
    <?php endif; ?>
  </div>

  <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
    <i class="fas fa-circle-info mt-1"></i>
    <div>
      <strong>Coming soon:</strong> live search across Ontario decisions, counsel, and firms.
      We’re setting up the database and ingestion pipeline.
    </div>
  </div>

  <!-- Placeholder filters (disabled) -->
  <form class="row g-3 mb-4">
    <div class="col-md-4">
      <label class="form-label">Jurisdiction</label>
      <select class="form-select" disabled>
        <option selected>Ontario (active)</option>
        <option>— Other provinces (soon)</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Court</label>
      <select class="form-select" disabled>
        <option selected>Any</option>
        <option>ONSC</option>
        <option>ONCA</option>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Date range</label>
      <input type="text" class="form-control" placeholder="YYYY-MM-DD to YYYY-MM-DD" disabled>
    </div>
  </form>

  <!-- Placeholder results table -->
  <div class="card border-0 shadow-sm">
    <div class="card-body">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h2 class="h5 mb-0">Results</h2>
        <span class="badge text-bg-secondary">Preview</span>
      </div>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 110px;">Date</th>
              <th style="width: 140px;">Citation</th>
              <th>Case</th>
              <th style="width: 130px;">Outcome</th>
              <th style="width: 160px;">Counsel parsed</th>
              <th style="width: 90px;">Link</th>
            </tr>
          </thead>
          <tbody>
            <tr class="text-muted">
              <td colspan="6"><em>No live data yet — ingestion in progress.</em></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080">
    <div id="comingToast" class="toast align-items-center text-bg-dark border-0" role="status" aria-live="polite" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          🔍 Search is coming soon. We’ve recorded your query<?= $q ? ': ' . htmlspecialchars($q) : '' ?>.
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>
</main>

<script>
  // Auto-show toast on any visit to /search.php (so hitting Enter on the homepage feels responsive)
  document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('comingToast');
    if (el) new bootstrap.Toast(el, { delay: 3500 }).show();
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>