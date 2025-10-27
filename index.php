<?php
$pageTitle = 'LexBoard – AI Legal Outcomes & Lawyer Metrics (Canada)';
$metaDescription = 'LexBoard uses public court decisions to surface data-driven insights about case outcomes and counsel performance. Start in Ontario; more provinces and territories coming soon.';
$metaKeywords = 'LexBoard, LexRank, lawyer rankings, win rate, case outcomes, Ontario, Canada, legal analytics, Bootstrap 5, PhPstrap';
include __DIR__ . '/includes/header.php';
?>

<style>
  /* Subtle, accessible greying for regions not yet available */
  .region-disabled {
    opacity: .55;
    pointer-events: none;
    filter: grayscale(30%);
  }
  .region-card:hover { transform: translateY(-2px); }
  .kb-badge {
    font-size: .8rem;
    letter-spacing: .02em;
  }
  .search-hero {
    background: radial-gradient(1200px 600px at 50% -10%, rgba(13,110,253,.08), transparent 60%),
                linear-gradient(135deg, var(--bs-body-bg) 0%, var(--bs-body-bg) 60%);
    border: 1px solid var(--bs-border-color);
  }
</style>

<main class="container py-5">

  <!-- Hero / Search -->
  <section class="search-hero rounded-4 p-4 p-md-5 mb-5 shadow-sm">
    <div class="row justify-content-center">
      <div class="col-xl-9 col-lg-10">
        <div class="text-center mb-4">
          <span class="badge text-bg-success kb-badge me-2">Ontario • Live</span>
          <span class="badge text-bg-secondary kb-badge">Other provinces • Soon</span>
        </div>
        <h1 class="display-5 fw-bold text-center mb-3">Legal outcomes, quantified.</h1>
        <p class="lead text-center text-secondary mb-4">
          Search Canadian decisions to power AI-based outcome metrics for lawyers and firms.<br class="d-none d-md-inline">
          Start with Ontario; more jurisdictions coming soon.
        </p>

        <!-- Placeholder search (wire up later) -->
        <form class="d-flex gap-2 flex-column flex-md-row" role="search" action="/search.php" method="get">
          <div class="input-group input-group-lg">
            <span class="input-group-text bg-transparent" id="lex-search-addon" aria-hidden="true">
              <i class="fas fa-magnifying-glass"></i>
            </span>
            <input
              type="search"
              name="q"
              class="form-control"
              placeholder="Try: “appeal dismissed” OR counsel name"
              aria-label="Search decisions, lawyers, or firms"
              aria-describedby="lex-search-addon"
              required
            >
            <button class="btn btn-primary px-4" type="submit" disabled title="Search coming soon">
              Search
            </button>
          </div>
        </form>

        <div class="text-center mt-3">
          <small class="text-muted">
            Placeholder search is disabled until the database is configured.
          </small>
        </div>
      </div>
    </div>
  </section>

  <!-- Provinces & Territories Grid -->
  <section aria-labelledby="regions-heading" class="mb-5">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
      <h2 id="regions-heading" class="h4 mb-0 fw-bold">Provinces & Territories</h2>
      <span class="text-muted small">Ontario is active; others are preview-only.</span>
    </div>

    <?php
      // Regions list with Ontario enabled
      $regions = [
        ['name'=>'Ontario','abbr'=>'ON','path'=>'/on/','enabled'=>true],
        ['name'=>'Alberta','abbr'=>'AB','path'=>null,'enabled'=>false],
        ['name'=>'British Columbia','abbr'=>'BC','path'=>null,'enabled'=>false],
        ['name'=>'Manitoba','abbr'=>'MB','path'=>null,'enabled'=>false],
        ['name'=>'New Brunswick','abbr'=>'NB','path'=>null,'enabled'=>false],
        ['name'=>'Newfoundland and Labrador','abbr'=>'NL','path'=>null,'enabled'=>false],
        ['name'=>'Nova Scotia','abbr'=>'NS','path'=>null,'enabled'=>false],
        ['name'=>'Prince Edward Island','abbr'=>'PE','path'=>null,'enabled'=>false],
        ['name'=>'Quebec','abbr'=>'QC','path'=>null,'enabled'=>false],
        ['name'=>'Saskatchewan','abbr'=>'SK','path'=>null,'enabled'=>false],
        ['name'=>'Northwest Territories','abbr'=>'NT','path'=>null,'enabled'=>false],
        ['name'=>'Nunavut','abbr'=>'NU','path'=>null,'enabled'=>false],
        ['name'=>'Yukon','abbr'=>'YT','path'=>null,'enabled'=>false],
      ];
    ?>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
      <?php foreach ($regions as $r): ?>
        <div class="col">
          <?php if ($r['enabled'] && $r['path']): ?>
            <a class="text-decoration-none" href="<?= htmlspecialchars($r['path']) ?>" aria-label="<?= htmlspecialchars($r['name']) ?> (active)">
              <div class="region-card card h-100 border-0 shadow-sm">
                <div class="card-body">
                  <div class="d-flex align-items-center justify-content-between">
                    <h3 class="h5 mb-1"><?= htmlspecialchars($r['name']) ?></h3>
                    <span class="badge text-bg-primary"><?= htmlspecialchars($r['abbr']) ?></span>
                  </div>
                  <p class="text-secondary mb-0 small">Explore courts, decisions, and counsel metrics</p>
                </div>
              </div>
            </a>
          <?php else: ?>
            <div class="region-card card h-100 border-0 shadow-sm region-disabled" aria-disabled="true">
              <div class="card-body">
                <div class="d-flex align-items-center justify-content-between">
                  <h3 class="h5 mb-1"><?= htmlspecialchars($r['name']) ?></h3>
                  <span class="badge text-bg-secondary"><?= htmlspecialchars($r['abbr']) ?></span>
                </div>
                <p class="text-secondary mb-1 small">Coming soon</p>
                <span class="badge text-bg-light text-muted">Preview</span>
              </div>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- Ontario Quick Links (optional helpful starting points) -->
  <section aria-labelledby="on-links" class="mb-5">
    <h2 id="on-links" class="h4 fw-bold mb-3">Ontario – Quick Start</h2>
    <div class="row g-3">
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-2"><i class="fas fa-scale-balanced me-2 text-primary"></i>Ontario Superior Court (ONSC)</h3>
            <p class="text-secondary small mb-3">Trials, motions, applications, estates list, etc.</p>
            <a class="btn btn-sm btn-outline-primary" href="/on/onsc/">View ONSC</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-2"><i class="fas fa-gavel me-2 text-primary"></i>Court of Appeal (ONCA)</h3>
            <p class="text-secondary small mb-3">Appeal outcomes: allowed, dismissed, varied.</p>
            <a class="btn btn-sm btn-outline-primary" href="/on/onca/">View ONCA</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-2"><i class="fas fa-briefcase me-2 text-primary"></i>By Counsel / Firm</h3>
            <p class="text-secondary small mb-3">Browse matters by counsel name or firm (beta).</p>
            <a class="btn btn-sm btn-outline-primary disabled" aria-disabled="true" href="#">Coming soon</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA: What is LexBoard -->
  <section class="text-center py-5 bg-light rounded-4 border">
    <h2 class="fw-bold mb-2">What is LexBoard?</h2>
    <p class="lead text-secondary mb-4">
      A neutral, data-driven layer on top of public decisions to help people understand outcomes and performance—fairly and transparently.
    </p>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
      <a href="/about/" class="btn btn-outline-dark">Learn More</a>
      <a href="/contact/" class="btn btn-primary">Contact</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>