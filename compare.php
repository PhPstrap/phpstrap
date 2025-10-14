<?php
$pageTitle = 'Compare Plans - PhPstrap';
$metaDescription = 'Side-by-side comparison of PhPstrap Community, Pro, and Enterprise plans.';
$metaKeywords = 'pricing comparison, feature matrix, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Header -->
  <header class="text-center mb-4">
    <h1 class="display-6 fw-bold mb-2">Compare plans</h1>
    <p class="text-muted mb-0">See what’s included across Community, Pro, and Enterprise.</p>
  </header>

  <!-- Billing Toggle -->
  <section class="text-center my-4">
    <div class="d-inline-flex align-items-center gap-2 border rounded-pill px-3 py-2">
      <span class="small text-muted">Monthly</span>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="billingToggle" aria-label="Toggle yearly billing">
      </div>
      <span class="small">Yearly <span class="badge bg-success-subtle text-success border border-success-subtle">Save 20%</span></span>
    </div>
  </section>

  <!-- Plans at a glance -->
  <section class="mb-4">
    <div class="row row-cols-1 row-cols-md-3 g-3 text-center">
      <div class="col">
        <div class="p-3 border rounded-3 h-100">
          <div class="small text-muted">Community</div>
          <div class="fs-4 fw-bold mb-0" data-price data-monthly="$0" data-yearly="$0">$0</div>
          <div class="small text-muted"><span data-cycle>per month</span></div>
        </div>
      </div>
      <div class="col">
        <div class="p-3 border rounded-3 h-100 border-primary">
          <div class="badge bg-primary mb-1">Popular</div>
          <div class="small text-muted">Pro</div>
          <div class="fs-4 fw-bold mb-0" data-price data-monthly="$15" data-yearly="$144">$15</div>
          <div class="small text-muted"><span data-cycle>per month</span></div>
        </div>
      </div>
      <div class="col">
        <div class="p-3 border rounded-3 h-100">
          <div class="small text-muted">Enterprise</div>
          <div class="fs-4 fw-bold mb-0" data-price data-monthly="$79" data-yearly="$758">$79</div>
          <div class="small text-muted"><span data-cycle>per month</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Comparison Table -->
  <section class="mb-5">
    <div class="table-responsive">
      <table class="table table-hover align-middle compare-table">
        <thead class="table-light">
          <tr>
            <th scope="col" class="sticky-col">Features</th>
            <th scope="col" class="text-center">Community</th>
            <th scope="col" class="text-center">Pro</th>
            <th scope="col" class="text-center">Enterprise</th>
          </tr>
        </thead>
        <tbody>

          <!-- Group: Core -->
          <tr class="table-group">
            <th class="sticky-col">Core</th>
            <td></td><td></td><td></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Source code
            </th>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Starter modules
            </th>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Group: Support -->
          <tr class="table-group">
            <th class="sticky-col">Support</th>
            <td></td><td></td><td></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Email support
              <i class="fa-regular fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" title="Average first response time."></i>
            </th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center">48h</td>
            <td class="text-center">SLA</td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Priority queue
            </th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Group: Development -->
          <tr class="table-group">
            <th class="sticky-col">Development</th>
            <td></td><td></td><td></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Custom module help
            </th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Deployment assistance
            </th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Group: Security -->
          <tr class="table-group">
            <th class="sticky-col">Security</th>
            <td></td><td></td><td></td>
          </tr>
          <tr>
            <th scope="row" class="sticky-col">
              Security review
              <i class="fa-regular fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" title="Headers, CSP, escaping, and dependency audit."></i>
            </th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

        </tbody>
        <tfoot>
          <tr class="table-light">
            <th class="sticky-col">Price</th>
            <td class="text-center fw-semibold" data-price data-monthly="$0" data-yearly="$0">$0</td>
            <td class="text-center fw-semibold" data-price data-monthly="$15" data-yearly="$144">$15</td>
            <td class="text-center fw-semibold" data-price data-monthly="$79" data-yearly="$758">$79</td>
          </tr>
          <tr>
            <th class="sticky-col"></th>
            <td class="text-center"><a href="/download.php" class="btn btn-sm btn-outline-primary">Get Community</a></td>
            <td class="text-center"><a href="/buy.php?plan=pro" class="btn btn-sm btn-primary">Buy Pro</a></td>
            <td class="text-center"><a href="/contact.php" class="btn btn-sm btn-outline-secondary">Talk to Sales</a></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="text-muted small mt-2">Features may evolve; see docs for the most current details.</p>
  </section>

  <!-- Mobile CTAs -->
  <section class="d-md-none">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-baseline">
          <strong>Pro</strong>
          <span class="fw-bold" data-price data-monthly="$15" data-yearly="$144">$15</span>
        </div>
        <div class="text-muted small mb-2"><span data-cycle>per month</span></div>
        <a href="/buy.php?plan=pro" class="btn btn-primary w-100">Buy Pro</a>
      </div>
    </div>
    <div class="card border-0 shadow-sm">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-baseline">
          <strong>Enterprise</strong>
          <span class="fw-bold" data-price data-monthly="$79" data-yearly="$758">$79</span>
        </div>
        <div class="text-muted small mb-2"><span data-cycle>per month</span></div>
        <a href="/contact.php" class="btn btn-outline-secondary w-100">Talk to Sales</a>
      </div>
    </div>
  </section>

  <!-- Copyable snippet -->
  <section class="py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="h6 mb-0">Copy this compare table snippet</h2>
      <button id="copyCompareSnippet" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
<pre class="bg-light border p-3 rounded small mb-0"><code id="compareSnippet">&lt;div class="table-responsive"&gt;
  &lt;table class="table align-middle"&gt;
    &lt;thead class="table-light"&gt;
      &lt;tr&gt;
        &lt;th&gt;Features&lt;/th&gt;&lt;th class="text-center"&gt;Community&lt;/th&gt;&lt;th class="text-center"&gt;Pro&lt;/th&gt;&lt;th class="text-center"&gt;Enterprise&lt;/th&gt;
      &lt;/tr&gt;
    &lt;/thead&gt;
    &lt;tbody&gt;
      &lt;tr class="table-group"&gt;&lt;th&gt;Core&lt;/th&gt;&lt;td&gt;&lt;/td&gt;&lt;td&gt;&lt;/td&gt;&lt;td&gt;&lt;/td&gt;&lt;/tr&gt;
      &lt;tr&gt;&lt;th&gt;Source code&lt;/th&gt;&lt;td class="text-center"&gt;✔&lt;/td&gt;&lt;td class="text-center"&gt;✔&lt;/td&gt;&lt;td class="text-center"&gt;✔&lt;/td&gt;&lt;/tr&gt;
    &lt;/tbody&gt;
  &lt;/table&gt;
&lt;/div&gt;</code></pre>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Subtle helpers */
.badge.bg-success-subtle { background-color: rgba(25,135,84,.12); }
.border-success-subtle { border-color: rgba(25,135,84,.25) !important; }

/* Sticky first column on md+ screens */
@media (min-width: 768px) {
  .compare-table .sticky-col {
    position: sticky;
    left: 0;
    background: var(--bs-body-bg);
    z-index: 1;
  }
  thead .sticky-col, tfoot .sticky-col { z-index: 2; }
}

/* Group header rows */
.compare-table .table-group th {
  background: var(--bs-light);
  font-weight: 600;
  border-top: 2px solid var(--bs-border-color);
}
</style>

<script>
// Enable tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

// Billing toggle updates all [data-price] and [data-cycle]
(function () {
  const toggle = document.getElementById('billingToggle');
  const prices = () => document.querySelectorAll('[data-price]');
  const cycles = () => document.querySelectorAll('[data-cycle]');
  if (!toggle) return;
  const update = () => {
    const yearly = toggle.checked;
    prices().forEach(el => {
      const v = el.getAttribute(yearly ? 'data-yearly' : 'data-monthly');
      if (v) el.textContent = v;
    });
    cycles().forEach(el => el.textContent = yearly ? 'per year' : 'per month');
  };
  toggle.addEventListener('change', update);
  update();
})();

// Copy snippet
(function () {
  const btn = document.getElementById('copyCompareSnippet');
  const code = document.getElementById('compareSnippet');
  if (!btn || !code) return;
  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(code.textContent);
      const t = btn.textContent;
      btn.textContent = 'Copied!';
      btn.classList.replace('btn-outline-secondary', 'btn-success');
      setTimeout(() => { btn.textContent = t; btn.classList.replace('btn-success', 'btn-outline-secondary'); }, 1600);
    } catch {}
  });
})();
</script>