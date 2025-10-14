<?php
$pageTitle = 'Pricing - PhPstrap';
$metaDescription = 'Pricing layouts with tabs for personas and add-ons.';
$metaKeywords = 'pricing, Bootstrap 5, tabs, personas';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Header -->
  <header class="text-center mb-4">
    <h1 class="display-6 fw-bold mb-2">Pick a plan that fits</h1>
    <p class="text-muted mb-0">Simple options for solo devs, teams, and enterprises.</p>
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

  <!-- Personas Tabs -->
  <ul class="nav nav-pills justify-content-center gap-2 mb-4" id="pricingTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="solo-tab" data-bs-toggle="pill" data-bs-target="#solo" type="button" role="tab">Solo</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="team-tab" data-bs-toggle="pill" data-bs-target="#team" type="button" role="tab">Team</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="enterprise-tab" data-bs-toggle="pill" data-bs-target="#enterprise" type="button" role="tab">Enterprise</button>
    </li>
  </ul>

  <div class="tab-content">

    <!-- Solo -->
    <div class="tab-pane fade show active" id="solo" role="tabpanel" aria-labelledby="solo-tab">
      <div class="row row-cols-1 row-cols-md-2 g-4">
        <!-- Community -->
        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="h5">Community</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$0" data-yearly="$0">$0</p>
              <p class="text-muted small"><span data-cycle>per month</span> · MIT License</p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
                <li>• Full source code</li>
                <li>• Starter modules</li>
                <li>• GitHub Issues</li>
              </ul>
              <a href="/download.php" class="btn btn-outline-primary w-100 mt-2">Get started</a>
            </div>
          </div>
        </div>

        <!-- Solo Pro -->
        <div class="col">
          <div class="card h-100 border-0 shadow-sm border-primary">
            <div class="card-body text-center">
              <div class="badge bg-primary mb-2">Popular</div>
              <h3 class="h5">Solo Pro</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$12" data-yearly="$115">$12</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
                <li>• Email support (48h)</li>
                <li>• Install guidance</li>
                <li>• Module patterns</li>
              </ul>
              <a href="/buy.php?plan=solo-pro" class="btn btn-primary w-100 mt-2">Buy Solo Pro</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Team -->
    <div class="tab-pane fade" id="team" role="tabpanel" aria-labelledby="team-tab">
      <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <!-- Team Starter -->
        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="h5">Team Starter</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$29" data-yearly="$278">$29</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
                <li>• 3 seats</li>
                <li>• Email support (48h)</li>
                <li>• Private repo guidance</li>
              </ul>
              <a href="/buy.php?plan=team-starter" class="btn btn-outline-primary w-100 mt-2">Choose Team Starter</a>
            </div>
          </div>
        </div>

        <!-- Team Plus -->
        <div class="col">
          <div class="card h-100 border-0 shadow-sm border-primary">
            <div class="card-body text-center">
              <div class="badge bg-primary mb-2">Popular</div>
              <h3 class="h5">Team Plus</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$49" data-yearly="$470">$49</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
                <li>• 5 seats</li>
                <li>• Priority queue</li>
                <li>• Module review</li>
              </ul>
              <a href="/buy.php?plan=team-plus" class="btn btn-primary w-100 mt-2">Choose Team Plus</a>
            </div>
          </div>
        </div>

        <!-- Team Scale -->
        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="h5">Team Scale</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$79" data-yearly="$758">$79</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:260px;">
                <li>• 10 seats</li>
                <li>• Architecture guidance</li>
                <li>• Staging deploy help</li>
              </ul>
              <a href="/buy.php?plan=team-scale" class="btn btn-outline-secondary w-100 mt-2">Choose Team Scale</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Enterprise -->
    <div class="tab-pane fade" id="enterprise" role="tabpanel" aria-labelledby="enterprise-tab">
      <div class="row row-cols-1 row-cols-lg-2 g-4">
        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="h5">Enterprise</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$199" data-yearly="$1908">$199</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:360px;">
                <li>• SLA support</li>
                <li>• Custom modules</li>
                <li>• Security review</li>
              </ul>
              <a href="/contact.php" class="btn btn-primary w-100 mt-2">Talk to sales</a>
            </div>
          </div>
        </div>

        <div class="col">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
              <h3 class="h5">Enterprise Plus</h3>
              <p class="display-6 fw-bold mb-1" data-price data-monthly="$349" data-yearly="$3340">$349</p>
              <p class="text-muted small"><span data-cycle>per month</span></p>
              <ul class="list-unstyled small text-start mx-auto" style="max-width:360px;">
                <li>• Dedicated engineer</li>
                <li>• Onboarding workshops</li>
                <li>• Deployment assistance</li>
              </ul>
              <a href="/contact.php" class="btn btn-outline-secondary w-100 mt-2">Request a quote</a>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /tab-content -->

  <!-- Add-ons -->
  <section class="py-5">
    <div class="text-center mb-4">
      <h2 class="h5 fw-semibold">Add-ons</h2>
      <p class="text-muted mb-0">Bundle extras with any paid plan.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-1">Code Review</h3>
            <p class="text-muted small mb-2">Per session</p>
            <div class="d-flex align-items-baseline gap-2">
              <div class="fs-4" data-price data-monthly="$49" data-yearly="$49">$49</div>
            </div>
            <p class="small mt-2 mb-0">Structured feedback on modules and routes.</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-1">Install Assist</h3>
            <p class="text-muted small mb-2">One-time</p>
            <div class="d-flex align-items-baseline gap-2">
              <div class="fs-4" data-price data-monthly="$79" data-yearly="$79">$79</div>
            </div>
            <p class="small mt-2 mb-0">We set up configs, DB, and bootstrap your app.</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h6 mb-1">Security Scan</h3>
            <p class="text-muted small mb-2">Per project</p>
            <div class="d-flex align-items-baseline gap-2">
              <div class="fs-4" data-price data-monthly="$149" data-yearly="$149">$149</div>
            </div>
            <p class="small mt-2 mb-0">Headers, CSP, escaping, and dependency review.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Final CTA -->
  <section class="text-center py-4">
    <h2 class="h4 fw-semibold mb-2">Start for free, grow when you’re ready</h2>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
      <a href="/download.php" class="btn btn-outline-primary btn-lg">Get Community</a>
      <a href="/buy.php?plan=team-plus" class="btn btn-primary btn-lg">Choose Team Plus</a>
    </div>
  </section>

  <!-- Copyable snippet -->
  <section class="py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h3 class="h6 mb-0">Copy this “tabs + cards” snippet</h3>
      <button id="copyPricingSnippet" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
<pre class="bg-light border p-3 rounded small mb-0"><code id="pricingSnippet">&lt;ul class="nav nav-pills mb-3" role="tablist"&gt;
  &lt;li class="nav-item"&gt;&lt;button class="nav-link active" data-bs-toggle="pill" data-bs-target="#solo"&gt;Solo&lt;/button&gt;&lt;/li&gt;
  &lt;li class="nav-item"&gt;&lt;button class="nav-link" data-bs-toggle="pill" data-bs-target="#team"&gt;Team&lt;/button&gt;&lt;/li&gt;
&lt;/ul&gt;
&lt;div class="tab-content"&gt;
  &lt;div class="tab-pane fade show active" id="solo"&gt;
    &lt;div class="row row-cols-1 row-cols-md-2 g-4"&gt;…cards…&lt;/div&gt;
  &lt;/div&gt;
  &lt;div class="tab-pane fade" id="team"&gt;
    &lt;div class="row row-cols-1 row-cols-md-3 g-4"&gt;…cards…&lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
  </section>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
.badge.bg-success-subtle { background-color: rgba(25,135,84,.12); }
.border-success-subtle { border-color: rgba(25,135,84,.25) !important; }
.border-primary { border-width: 2px !important; }
</style>

<script>
// Billing toggle updates all [data-price] on page
(function () {
  const toggle = document.getElementById('billingToggle');
  const prices = () => document.querySelectorAll('[data-price]');
  const cycleEls = () => document.querySelectorAll('[data-cycle]');
  if (!toggle) return;

  const update = () => {
    const yearly = toggle.checked;
    prices().forEach(el => {
      const v = el.getAttribute(yearly ? 'data-yearly' : 'data-monthly');
      if (v) el.textContent = v;
    });
    cycleEls().forEach(el => { el.textContent = yearly ? 'per year' : 'per month'; });
  };

  document.addEventListener('shown.bs.tab', update);
  toggle.addEventListener('change', update);
  update();
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