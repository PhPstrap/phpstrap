<?php
$pageTitle = 'PhPstrap — Secure, Modular PHP + Bootstrap Starter';
$metaDescription = 'Build secure, modern web apps faster with PhPstrap. Modular structure, Bootstrap 5 UI, and clean PHP helpers.';
$metaKeywords = 'PhPstrap, Bootstrap 5, PHP starter, modules, secure, open source';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

  <!-- HERO -->
  <section class="py-5 text-center">
    <p class="text-uppercase text-muted small mb-2">Open-Source · PHP + Bootstrap 5</p>
    <h1 class="display-5 fw-bold mb-3">Ship secure web apps faster</h1>
    <p class="lead mx-auto" style="max-width: 680px;">
      PhPstrap gives you a modular PHP foundation with Bootstrap 5 UI patterns,
      sensible security defaults, and a tidy file structure you won’t fight later.
    </p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center mt-4">
      <a href="/download.php" class="btn btn-primary btn-lg">Download</a>
      <a href="/docs/" class="btn btn-outline-secondary btn-lg">Read the docs</a>
    </div>
    <div class="text-muted small mt-3">MIT licensed · Works on shared hosting</div>
  </section>

  <!-- TRUST / LOGOS -->
  <section class="py-4">
    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-6 g-4 text-center align-items-center">
      <div class="col"><span class="text-muted">Bootstrap 5</span></div>
      <div class="col"><span class="text-muted">PHP 8+</span></div>
      <div class="col"><span class="text-muted">MariaDB/MySQL</span></div>
      <div class="col"><span class="text-muted">Cloudflare</span></div>
      <div class="col"><span class="text-muted">Sendy</span></div>
      <div class="col"><span class="text-muted">WHMCS</span></div>
    </div>
  </section>

  <!-- FEATURE GRID -->
  <section class="py-5">
    <div class="text-center mb-4">
      <h2 class="h3 fw-semibold">Why PhPstrap?</h2>
      <p class="text-muted mb-0">Start simple, scale cleanly, and keep your UI consistent.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h5 mb-2"><i class="fa-solid fa-layer-group me-2"></i>Modular by design</h3>
            <p class="mb-0">Pluggable modules for routes, menus, and settings keep core lean and upgrades easy.</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h5 mb-2"><i class="fa-solid fa-shield-halved me-2"></i>Sane security defaults</h3>
            <p class="mb-0">Headers, escaping helpers, and CSRF patterns so you don’t start from zero every time.</p>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body">
            <h3 class="h5 mb-2"><i class="fa-solid fa-bolt me-2"></i>Bootstrap-native UI</h3>
            <p class="mb-0">Leans on Bootstrap utilities and components; minimal overrides, maximum velocity.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CODE / DEMO -->
  <section class="py-5">
    <div class="row g-4 align-items-center">
      <div class="col-lg-6">
        <h2 class="h4 fw-semibold mb-2">Drop-in pages in minutes</h2>
        <p class="text-muted">Use ready-made layouts (Team, FAQ, Pricing, Dashboard). Bring your own modules and keep styling consistent.</p>
        <ul class="list-unstyled mb-4">
          <li>• Auth scaffolding &amp; role checks</li>
          <li>• Config-driven menus &amp; layouts</li>
          <li>• Clean includes structure</li>
        </ul>
        <a href="/examples/" class="btn btn-outline-primary">View examples</a>
      </div>
      <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
          <div class="card-header bg-light">routes/example.php</div>
          <div class="card-body">
<pre class="small mb-0"><code>&lt;?php
require __DIR__.'/../includes/bootstrap.php';
guard(); // auth check
render('header', ['pageTitle' =&gt; 'Example']);
?&gt;

&lt;main class="container py-5"&gt;
  &lt;h1 class="h3"&gt;Hello, PhPstrap!&lt;/h1&gt;
  &lt;p&gt;This page uses your global header/footer and Bootstrap defaults.&lt;/p&gt;
&lt;/main&gt;

&lt;?php render('footer'); ?&gt;</code></pre>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STATS / SOCIAL PROOF -->
  <section class="py-5">
    <div class="row row-cols-1 row-cols-md-3 g-3 text-center">
      <div class="col">
        <div class="p-4 border rounded-3">
          <div class="display-6 fw-bold">5<span class="text-primary">×</span></div>
          <div class="text-muted">Faster project kickoff</div>
        </div>
      </div>
      <div class="col">
        <div class="p-4 border rounded-3">
          <div class="display-6 fw-bold">0</div>
          <div class="text-muted">Vendor lock-in</div>
        </div>
      </div>
      <div class="col">
        <div class="p-4 border rounded-3">
          <div class="display-6 fw-bold">&lt; 15m</div>
          <div class="text-muted">From zip to running</div>
        </div>
      </div>
    </div>
  </section>

  <!-- TESTIMONIAL -->
  <section class="py-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4 p-md-5">
        <figure class="mb-0">
          <blockquote class="blockquote">
            <p class="mb-2">“PhPstrap lets us launch new tools in days instead of weeks. Clean, pragmatic, fast.”</p>
          </blockquote>
          <figcaption class="blockquote-footer mb-0">
            A happy developer, <cite title="Source Title">Indie studio</cite>
          </figcaption>
        </figure>
      </div>
    </div>
  </section>

  <!-- PRICING (optional) -->
  <section class="py-5">
    <div class="text-center mb-4">
      <h2 class="h3 fw-semibold">Choose your path</h2>
      <p class="text-muted mb-0">Open source forever. Optional support plans if you need a hand.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center">
            <h3 class="h5">Community</h3>
            <p class="display-6 fw-bold mb-1">$0</p>
            <p class="text-muted small">MIT License</p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width: 260px;">
              <li>• Full source code</li>
              <li>• Starter modules</li>
              <li>• GitHub Issues</li>
            </ul>
            <a href="/download.php" class="btn btn-outline-primary">Get started</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm border-primary">
          <div class="card-body text-center">
            <div class="badge bg-primary mb-2">Popular</div>
            <h3 class="h5">Pro Support</h3>
            <p class="display-6 fw-bold mb-1">$99</p>
            <p class="text-muted small">one-time</p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width: 260px;">
              <li>• Email support</li>
              <li>• Install help</li>
              <li>• Module guidance</li>
            </ul>
            <a href="/buy.php?plan=pro" class="btn btn-primary">Buy Pro</a>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body text-center">
            <h3 class="h5">Enterprise</h3>
            <p class="display-6 fw-bold mb-1">Custom</p>
            <p class="text-muted small">by quote</p>
            <ul class="list-unstyled small text-start mx-auto" style="max-width: 260px;">
              <li>• Priority support</li>
              <li>• Custom modules</li>
              <li>• Deployment help</li>
            </ul>
            <a href="/contact.php" class="btn btn-outline-secondary">Talk to us</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MINI FAQ -->
  <section class="py-5">
    <div class="row g-4">
      <div class="col-lg-6">
        <h2 class="h5 fw-semibold mb-3">FAQ</h2>
        <div class="accordion" id="lpFaq">
          <div class="accordion-item">
            <h2 class="accordion-header" id="f1-h">
              <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#f1">Is it really free?</button>
            </h2>
            <div id="f1" class="accordion-collapse collapse show" data-bs-parent="#lpFaq">
              <div class="accordion-body">Yes. PhPstrap is open-source (MIT). Keep it, fork it, ship it.</div>
            </div>
          </div>
          <div class="accordion-item">
            <h2 class="accordion-header" id="f2-h">
              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#f2">What do I need to run it?</button>
            </h2>
            <div id="f2" class="accordion-collapse collapse" data-bs-parent="#lpFaq">
              <div class="accordion-body">PHP 8+, MySQL/MariaDB, and a web server. Works fine on shared hosting.</div>
            </div>
          </div>
        </div>
      </div>
      <!-- CTA box -->
      <div class="col-lg-6">
        <div class="card h-100 border-0 shadow-sm">
          <div class="card-body p-4">
            <h3 class="h5">Ready to try it?</h3>
            <p class="text-muted">Grab the starter, deploy in minutes, and add modules as you grow.</p>
            <div class="d-flex flex-column flex-sm-row gap-2">
              <a href="/download.php" class="btn btn-primary">Download</a>
              <a href="/docs/" class="btn btn-outline-secondary">Docs</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="py-5 text-center">
    <h2 class="h3 fw-semibold mb-2">Build something great today</h2>
    <p class="text-muted mb-4">From prototype to production without the framework overhead.</p>
    <a href="/download.php" class="btn btn-primary btn-lg">Get PhPstrap</a>
  </section>

  <!-- COPYABLE HERO SNIPPET -->
  <section class="py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="h6 mb-0">Copy this hero snippet</h2>
      <button id="copyHero" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
<pre class="bg-light border p-3 rounded small mb-0"><code id="heroSnippet">&lt;section class="py-5 text-center"&gt;
  &lt;p class="text-uppercase text-muted small mb-2"&gt;Open-Source · PHP + Bootstrap 5&lt;/p&gt;
  &lt;h1 class="display-5 fw-bold mb-3"&gt;Ship secure web apps faster&lt;/h1&gt;
  &lt;p class="lead mx-auto" style="max-width: 680px;"&gt;PhPstrap gives you a modular PHP foundation with Bootstrap 5 UI patterns.&lt;/p&gt;
  &lt;div class="d-flex flex-column flex-sm-row gap-2 justify-content-center mt-4"&gt;
    &lt;a href="/download.php" class="btn btn-primary btn-lg"&gt;Download&lt;/a&gt;
    &lt;a href="/docs/" class="btn btn-outline-secondary btn-lg"&gt;Read the docs&lt;/a&gt;
  &lt;/div&gt;
&lt;/section&gt;</code></pre>
  </section>

</main>

<!-- Optional: JSON-LD for a software product -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "PhPstrap",
  "applicationCategory": "DeveloperTool",
  "operatingSystem": "Web",
  "offers": {"@type":"Offer","price":"0","priceCurrency":"USD"},
  "description": "A secure, modular PHP starter with Bootstrap 5 UI."
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Tiny optional tweaks */
.border-primary { border-width: 2px !important; }
</style>

<script>
(function () {
  const btn = document.getElementById('copyHero');
  const code = document.getElementById('heroSnippet');
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