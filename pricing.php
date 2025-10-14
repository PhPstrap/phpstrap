<?php
$pageTitle = 'Pricing - PhPstrap';
$metaDescription = 'Simple pricing for PhPstrap with monthly and yearly options.';
$metaKeywords = 'PhPstrap, pricing, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Header -->
  <header class="text-center mb-4">
    <h1 class="display-6 fw-bold mb-2">Simple, transparent pricing</h1>
    <p class="text-muted mb-0">Open source forever. Optional support if you want a hand.</p>
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

  <!-- Pricing Cards -->
  <section class="py-3">
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <!-- Community -->
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center">
            <h3 class="h5">Community</h3>
            <p class="display-6 fw-bold mb-1" data-price data-monthly="$0" data-yearly="$0">$0</p>
            <p class="text-muted small">MIT License</p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
              <li>• Full source code</li>
              <li>• Starter modules</li>
              <li>• GitHub Issues</li>
            </ul>
            <a href="/download.php" class="btn btn-outline-primary w-100 mt-2">Get started</a>
          </div>
        </div>
      </div>

      <!-- Pro (Popular) -->
      <div class="col">
        <div class="card h-100 border-0 shadow-sm border-primary">
          <div class="card-body text-center">
            <div class="badge bg-primary mb-2">Popular</div>
            <h3 class="h5">Pro Support</h3>
            <p class="display-6 fw-bold mb-1" data-price data-monthly="$15" data-yearly="$144">$15</p>
            <p class="text-muted small"><span data-cycle>per month</span></p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
              <li>• Email support (48h)</li>
              <li>• Install guidance</li>
              <li>• Module best practices</li>
            </ul>
            <a href="/buy.php?plan=pro" class="btn btn-primary w-100 mt-2">Buy Pro</a>
          </div>
        </div>
      </div>

      <!-- Enterprise -->
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center">
            <h3 class="h5">Enterprise</h3>
            <p class="display-6 fw-bold mb-1" data-price data-monthly="$79" data-yearly="$758">$79</p>
            <p class="text-muted small"><span data-cycle>per month</span></p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
              <li>• Priority support (SLA)</li>
              <li>• Custom modules</li>
              <li>• Deployment help</li>
            </ul>
            <a href="/contact.php" class="btn btn-outline-secondary w-100 mt-2">Talk to us</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Comparison Table -->
  <section class="py-5">
    <h2 class="h5 fw-semibold mb-3 text-center">Compare features</h2>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th scope="col">Feature</th>
            <th scope="col" class="text-center">Community</th>
            <th scope="col" class="text-center">Pro</th>
            <th scope="col" class="text-center">Enterprise</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <th scope="row">Source code</th>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr>
            <th scope="row">Starter modules</th>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr>
            <th scope="row">Email support</th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center">48h</td>
            <td class="text-center">SLA</td>
          </tr>
          <tr>
            <th scope="row">Custom module help</th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
          <tr>
            <th scope="row">Deployment assistance</th>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-minus text-muted"></i></td>
            <td class="text-center"><i class="fa-solid fa-check text-success"></i></td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>

  <!-- Final CTA -->
  <section class="text-center py-4">
    <h2 class="h4 fw-semibold mb-2">Start for free, upgrade anytime</h2>
    <p class="text-muted mb-4">Switch plans or billing cycles without friction.</p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
      <a href="/download.php" class="btn btn-outline-primary btn-lg">Get Community</a>
      <a href="/buy.php?plan=pro" class="btn btn-primary btn-lg">Buy Pro</a>
    </div>
  </section>

  <!-- Copyable snippet -->
  <section class="py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h3 class="h6 mb-0">Copy this pricing cards snippet</h3>
      <button id="copyPricingSnippet" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
<pre class="bg-light border p-3 rounded small mb-0"><code id="pricingSnippet">&lt;div class="row row-cols-1 row-cols-md-3 g-4"&gt;
  &lt;div class="col"&gt;
    &lt;div class="card h-100 border-0 shadow-sm text-center"&gt;
      &lt;div class="card-body"&gt;
        &lt;h3 class="h5"&gt;Community&lt;/h3&gt;
        &lt;p class="display-6 fw-bold mb-1"&gt;$0&lt;/p&gt;
        &lt;p class="text-muted small"&gt;MIT License&lt;/p&gt;
        &lt;ul class="list-unstyled small text-start mx-auto" style="max-width:260px;"&gt;
          &lt;li&gt;• Full source code&lt;/li&gt;
          &lt;li&gt;• Starter modules&lt;/li&gt;
          &lt;li&gt;• GitHub Issues&lt;/li&gt;
        &lt;/ul&gt;
        &lt;a href="#" class="btn btn-outline-primary w-100 mt-2"&gt;Get started&lt;/a&gt;
      &lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
  &lt;div class="col"&gt;
    &lt;div class="card h-100 border-0 shadow-sm border-primary text-center"&gt;
      &lt;div class="card-body"&gt;
        &lt;div class="badge bg-primary mb-2"&gt;Popular&lt;/div&gt;
        &lt;h3 class="h5"&gt;Pro Support&lt;/h3&gt;
        &lt;p class="display-6 fw-bold mb-1"&gt;$15&lt;/p&gt;
        &lt;p class="text-muted small"&gt;per month&lt;/p&gt;
        &lt;ul class="list-unstyled small text-start mx-auto" style="max-width:260px;"&gt;
          &lt;li&gt;• Email support (48h)&lt;/li&gt;
          &lt;li&gt;• Install guidance&lt;/li&gt;
          &lt;li&gt;• Module best practices&lt;/li&gt;
        &lt;/ul&gt;
        &lt;a href="#" class="btn btn-primary w-100 mt-2"&gt;Buy Pro&lt;/a&gt;
      &lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
  &lt;div class="col"&gt;
    &lt;div class="card h-100 border-0 shadow-sm text-center"&gt;
      &lt;div class="card-body"&gt;
        &lt;h3 class="h5"&gt;Enterprise&lt;/h3&gt;
        &lt;p class="display-6 fw-bold mb-1"&gt;$79&lt;/p&gt;
        &lt;p class="text-muted small"&gt;per month&lt;/p&gt;
        &lt;ul class="list-unstyled small text-start mx-auto" style="max-width:260px;"&gt;
          &lt;li&gt;• Priority support (SLA)&lt;/li&gt;
          &lt;li&gt;• Custom modules&lt;/li&gt;
          &lt;li&gt;• Deployment help&lt;/li&gt;
        &lt;/ul&gt;
        &lt;a href="#" class="btn btn-outline-secondary w-100 mt-2"&gt;Talk to us&lt;/a&gt;
      &lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Subtle badge & border helpers (optional) */
.badge.bg-success-subtle { background-color: rgba(25,135,84,.12); }
.border-success-subtle { border-color: rgba(25,135,84,.25) !important; }
.border-primary { border-width: 2px !important; }
</style>

<script>
// Pricing toggle (monthly/yearly)
(function () {
  const toggle = document.getElementById('billingToggle');
  const prices = document.querySelectorAll('[data-price]');
  const cycleEls = document.querySelectorAll('[data-cycle]');
  if (!toggle || !prices.length) return;

  const update = () => {
    const yearly = toggle.checked;
    prices.forEach(el => {
      const v = el.getAttribute(yearly ? 'data-yearly' : 'data-monthly');
      if (v) el.textContent = v;
    });
    cycleEls.forEach(el => { el.textContent = yearly ? 'per year' : 'per month'; });
  };

  toggle.addEventListener('change', update);
  update(); // init
})();

// Copy snippet
(function () {
  const btn = document.getElementById('copyPricingSnippet');
  const code = document.getElementById('pricingSnippet');
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