<?php
$pageTitle = 'Compare Plans (v2) - PhPstrap';
$metaDescription = 'Side-by-side comparison with differences-only toggle and column highlight.';
$metaKeywords = 'pricing comparison, differences only, highlight column, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Header -->
  <header class="text-center mb-4">
    <h1 class="display-6 fw-bold mb-2">Compare plans</h1>
    <p class="text-muted mb-0">Filter to see only differences and highlight the plan you’re evaluating.</p>
  </header>

  <!-- Controls -->
  <section class="d-flex flex-column flex-md-row align-items-md-center justify-content-md-between gap-3 my-4">
    <!-- Billing Toggle -->
    <div class="d-inline-flex align-items-center gap-2 border rounded-pill px-3 py-2">
      <span class="small text-muted">Monthly</span>
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" role="switch" id="billingToggle" aria-label="Toggle yearly billing">
      </div>
      <span class="small">Yearly <span class="badge bg-success-subtle text-success border border-success-subtle">Save 20%</span></span>
    </div>

    <!-- Differences + Highlight -->
    <div class="d-flex align-items-center gap-3">
      <div class="form-check form-switch m-0">
        <input class="form-check-input" type="checkbox" id="diffToggle">
        <label class="form-check-label small" for="diffToggle">Show differences only</label>
      </div>
      <div class="d-flex align-items-center gap-2">
        <label class="small text-muted" for="highlightPlan">Highlight</label>
        <select id="highlightPlan" class="form-select form-select-sm" style="width:auto">
          <option value="">None</option>
          <option value="2">Community</option>
          <option value="3" selected>Pro</option>
          <option value="4">Enterprise</option>
        </select>
      </div>
    </div>
  </section>

  <!-- Table -->
  <section class="mb-5">
    <div class="table-responsive">
      <table class="table table-hover align-middle compare-table">
        <thead class="table-light">
          <tr>
            <th scope="col" class="sticky-col">Features</th>
            <th scope="col" class="text-center">Community<br>
              <span class="fw-semibold" data-price data-monthly="$0" data-yearly="$0">$0</span>
              <div class="small text-muted"><span data-cycle>per month</span></div>
            </th>
            <th scope="col" class="text-center">Pro<br>
              <span class="fw-semibold" data-price data-monthly="$15" data-yearly="$144">$15</span>
              <div class="small text-muted"><span data-cycle>per month</span></div>
            </th>
            <th scope="col" class="text-center">Enterprise<br>
              <span class="fw-semibold" data-price data-monthly="$79" data-yearly="$758">$79</span>
              <div class="small text-muted"><span data-cycle>per month</span></div>
            </th>
          </tr>
        </thead>
        <tbody>

          <!-- Core -->
          <tr class="table-group"><th class="sticky-col">Core</th><td></td><td></td><td></td></tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">Source code</th>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">Starter modules</th>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Support -->
          <tr class="table-group"><th class="sticky-col">Support</th><td></td><td></td><td></td></tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">
              Email support
              <i class="fa-regular fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" title="Average first response time."></i>
            </th>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="48h">48h</td>
            <td class="text-center" data-val="SLA">SLA</td>
          </tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">Priority queue</th>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Development -->
          <tr class="table-group"><th class="sticky-col">Development</th><td></td><td></td><td></td></tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">Custom module help</th>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">Deployment assistance</th>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

          <!-- Security -->
          <tr class="table-group"><th class="sticky-col">Security</th><td></td><td></td><td></td></tr>
          <tr data-compare-row>
            <th scope="row" class="sticky-col">
              Security review
              <i class="fa-regular fa-circle-question text-muted ms-1" data-bs-toggle="tooltip" title="Headers, CSP, escaping, and dependency audit."></i>
            </th>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="none"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center" data-val="check"><i class="fa-solid fa-check text-success"></i></td>
          </tr>

        </tbody>
        <tfoot class="table-light">
          <tr>
            <th class="sticky-col">Actions</th>
            <td class="text-center"><a href="/download.php" class="btn btn-sm btn-outline-primary">Get Community</a></td>
            <td class="text-center"><a href="/buy.php?plan=pro" class="btn btn-sm btn-primary">Buy Pro</a></td>
            <td class="text-center"><a href="/contact.php" class="btn btn-sm btn-outline-secondary">Talk to Sales</a></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <p class="text-muted small mt-2">Features may evolve; see docs for the most current details.</p>
  </section>

  <!-- Copyable toolbar snippet -->
  <section class="py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="h6 mb-0">Copy this “differences + highlight” toolbar</h2>
      <button id="copyToolbarSnippet" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
<pre class="bg-light border p-3 rounded small mb-0"><code id="toolbarSnippet">&lt;div class="d-flex align-items-center gap-3"&gt;
  &lt;div class="form-check form-switch m-0"&gt;
    &lt;input class="form-check-input" type="checkbox" id="diffToggle"&gt;
    &lt;label class="form-check-label small" for="diffToggle"&gt;Show differences only&lt;/label&gt;
  &lt;/div&gt;
  &lt;div class="d-flex align-items-center gap-2"&gt;
    &lt;label class="small text-muted" for="highlightPlan"&gt;Highlight&lt;/label&gt;
    &lt;select id="highlightPlan" class="form-select form-select-sm" style="width:auto"&gt;
      &lt;option value=""&gt;None&lt;/option&gt;
      &lt;option value="2"&gt;Community&lt;/option&gt;
      &lt;option value="3"&gt;Pro&lt;/option&gt;
      &lt;option value="4"&gt;Enterprise&lt;/option&gt;
    &lt;/select&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Subtle helpers */
.badge.bg-success-subtle { background-color: rgba(25,135,84,.12); }
.border-success-subtle { border-color: rgba(25,135,84,.25) !important; }

/* Sticky feature column on md+ */
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

/* Highlighted plan column */
.compare-table td.hl, .compare-table th.hl {
  background: var(--bs-primary-bg-subtle);
}
</style>

<script>
// Enable tooltips
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

// Billing toggle updates all [data-price] + [data-cycle]
(function () {
  const toggle = document.getElementById('billingToggle');
  if (!toggle) return;
  const prices = () => document.querySelectorAll('[data-price]');
  const cycles = () => document.querySelectorAll('[data-cycle]');
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

// Differences-only toggle
(function () {
  const diff = document.getElementById('diffToggle');
  if (!diff) return;
  const rows = () => Array.from(document.querySelectorAll('tr[data-compare-row]'));
  const values = r => Array.from(r.querySelectorAll('td[data-val]')).map(td => td.getAttribute('data-val') || '');
  const update = () => {
    const showDiffs = diff.checked;
    rows().forEach(r => {
      const vals = values(r);
      const allEqual = vals.length && vals.every(v => v === vals[0]);
      r.style.display = (showDiffs && allEqual) ? 'none' : '';
    });
  };
  diff.addEventListener('change', update);
  update();
})();

// Highlight selected plan column
(function () {
  const sel = document.getElementById('highlightPlan');
  if (!sel) return;
  const table = document.querySelector('.compare-table');
  const clear = () => table.querySelectorAll('.hl').forEach(el => el.classList.remove('hl'));
  const apply = (idx) => {
    if (!idx) return;
    table.querySelectorAll('tr').forEach(tr => {
      const cells = tr.children;
      if (cells[idx]) cells[idx].classList.add('hl');
    });
  };
  sel.addEventListener('change', () => { clear(); apply(parseInt(sel.value,10)); });
  // init
  clear(); apply(parseInt(sel.value,10));
})();

// Copy toolbar snippet
(function () {
  const btn = document.getElementById('copyToolbarSnippet');
  const code = document.getElementById('toolbarSnippet');
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