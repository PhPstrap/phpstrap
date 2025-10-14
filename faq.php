<?php
$pageTitle = 'FAQ - PhPstrap Membership';
$metaDescription = 'Frequently asked questions about PhPstrap: installation, modules, security, and licensing.';
$metaKeywords = 'PhPstrap, FAQ, Bootstrap 5, modules, security, installation, licensing';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Header -->
  <header class="text-center mb-5">
    <h1 class="display-6 fw-bold mb-2">Frequently Asked Questions</h1>
    <p class="text-muted mb-0">Quick answers about installation, modules, security, and licensing.</p>
  </header>

  <!-- Search -->
  <section class="mb-4">
    <div class="input-group">
      <span class="input-group-text" id="faq-search-addon" aria-hidden="true">🔎</span>
      <input id="faqSearch" type="search" class="form-control" placeholder="Search FAQs…" aria-describedby="faq-search-addon" autocomplete="off">
    </div>
    <div class="form-text">Type keywords like “install”, “module”, “license”, “security”</div>
  </section>

  <!-- Accordion -->
  <section class="mb-5">
    <div class="accordion" id="faqAccordion">
      <!-- Q1 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q1-h">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1" aria-expanded="true" aria-controls="q1">
            How do I install PhPstrap?
          </button>
        </h2>
        <div id="q1" class="accordion-collapse collapse show" aria-labelledby="q1-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Upload the files to your server, configure <code>includes/site-config.php</code>, set up the database using the installer, then visit your domain to finish setup. For local dev, use PHP’s built-in server: <code>php -S localhost:8080 -t public</code>.
          </div>
        </div>
      </div>

      <!-- Q2 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q2-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" aria-expanded="false" aria-controls="q2">
            How do modules work in PhPstrap?
          </button>
        </h2>
        <div id="q2" class="accordion-collapse collapse" aria-labelledby="q2-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Modules live in <code>/modules</code> and register via a manifest (e.g., <code>module.json</code>). They can add menu items, routes, and settings. Enable/disable modules from the Admin &rarr; Modules page.
          </div>
        </div>
      </div>

      <!-- Q3 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q3-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3" aria-expanded="false" aria-controls="q3">
            Does PhPstrap support Bootstrap 5 out of the box?
          </button>
        </h2>
        <div id="q3" class="accordion-collapse collapse" aria-labelledby="q3-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Yes. All core pages and components are styled with Bootstrap 5 defaults. You can extend styles with a lightweight <code>admin.css</code> without overriding Bootstrap variables.
          </div>
        </div>
      </div>

      <!-- Q4 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q4-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q4" aria-expanded="false" aria-controls="q4">
            What are best practices for security?
          </button>
        </h2>
        <div id="q4" class="accordion-collapse collapse" aria-labelledby="q4-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Use HTTPS, secure cookies, and a strict Content Security Policy. Sanitize inputs, escape outputs with <code>htmlspecialchars()</code>, rotate API keys, and keep dependencies updated. PhPstrap includes sensible headers and helpers to reduce risk.
          </div>
        </div>
      </div>

      <!-- Q5 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q5-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5" aria-expanded="false" aria-controls="q5">
            How do I customize navigation and the footer?
          </button>
        </h2>
        <div id="q5" class="accordion-collapse collapse" aria-labelledby="q5-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Edit <code>includes/nav.php</code> and <code>includes/footer.php</code>. For dynamic menus, expose a <code>menu()</code> helper or a config array and include it from your layout to keep consistency across pages.
          </div>
        </div>
      </div>

      <!-- Q6 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q6-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6" aria-expanded="false" aria-controls="q6">
            Can I use PhPstrap for membership sites?
          </button>
        </h2>
        <div id="q6" class="accordion-collapse collapse" aria-labelledby="q6-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Yes—PhPstrap ships with auth scaffolding, role checks, and Bootstrap UI patterns. Add subscription logic and payment gateways as modules to keep your core clean.
          </div>
        </div>
      </div>

      <!-- Q7 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q7-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q7" aria-expanded="false" aria-controls="q7">
            What license does PhPstrap use?
          </button>
        </h2>
        <div id="q7" class="accordion-collapse collapse" aria-labelledby="q7-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            PhPstrap is open-source. Check the root <code>LICENSE</code> file for the exact terms. You can build commercial projects while contributing improvements back via pull requests.
          </div>
        </div>
      </div>

      <!-- Q8 -->
      <div class="accordion-item" data-faq>
        <h2 class="accordion-header" id="q8-h">
          <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q8" aria-expanded="false" aria-controls="q8">
            Where do I report bugs or request features?
          </button>
        </h2>
        <div id="q8" class="accordion-collapse collapse" aria-labelledby="q8-h" data-bs-parent="#faqAccordion">
          <div class="accordion-body">
            Open an issue on GitHub with steps to reproduce, environment info, and any logs. For features, include a short problem statement and expected outcome.
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- Helpful links -->
  <section class="mb-5">
    <div class="card border-0 shadow-sm">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Helpful Links</h2>
        <ul class="list-unstyled mb-0">
          <li class="mb-1">• <a href="#">Documentation</a></li>
          <li class="mb-1">• <a href="#">Module Developer Guide</a></li>
          <li class="mb-1">• <a href="#">Security Checklist</a></li>
          <li class="mb-0">• <a href="#">GitHub Issues</a></li>
        </ul>
      </div>
    </div>
  </section>

  <!-- Copyable snippet -->
  <section class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="h6 mb-0">Copy this FAQ snippet</h2>
      <button id="copySnippetBtn" class="btn btn-sm btn-outline-secondary">Copy</button>
    </div>
    <pre class="bg-light border p-3 rounded small mb-0"><code id="faqSnippet">&lt;!-- Bootstrap 5 FAQ accordion --&gt;
&lt;div class="accordion" id="faqAccordion"&gt;
  &lt;div class="accordion-item"&gt;
    &lt;h2 class="accordion-header" id="q1-h"&gt;
      &lt;button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1" aria-expanded="true" aria-controls="q1"&gt;
        How do I install PhPstrap?
      &lt;/button&gt;
    &lt;/h2&gt;
    &lt;div id="q1" class="accordion-collapse collapse show" aria-labelledby="q1-h" data-bs-parent="#faqAccordion"&gt;
      &lt;div class="accordion-body"&gt;
        Upload files, configure &lt;code&gt;includes/site-config.php&lt;/code&gt;, run the installer, then visit your domain.
      &lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;

  &lt;div class="accordion-item"&gt;
    &lt;h2 class="accordion-header" id="q2-h"&gt;
      &lt;button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2" aria-expanded="false" aria-controls="q2"&gt;
        How do modules work?
      &lt;/button&gt;
    &lt;/h2&gt;
    &lt;div id="q2" class="accordion-collapse collapse" aria-labelledby="q2-h" data-bs-parent="#faqAccordion"&gt;
      &lt;div class="accordion-body"&gt;
        Place modules in &lt;code&gt;/modules&lt;/code&gt; with a manifest. Enable from Admin &rarr; Modules.
      &lt;/div&gt;
    &lt;/div&gt;
  &lt;/div&gt;
&lt;/div&gt;</code></pre>
  </section>
</main>

<!-- JSON-LD FAQ schema for SEO -->
<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"FAQPage",
  "mainEntity":[
    {"@type":"Question","name":"How do I install PhPstrap?","acceptedAnswer":{"@type":"Answer","text":"Upload files, configure includes/site-config.php, run the installer, and visit your domain."}},
    {"@type":"Question","name":"How do modules work in PhPstrap?","acceptedAnswer":{"@type":"Answer","text":"Modules live in /modules with a manifest. Enable/disable them from the Admin → Modules page."}},
    {"@type":"Question","name":"Does PhPstrap support Bootstrap 5 out of the box?","acceptedAnswer":{"@type":"Answer","text":"Yes, core pages use Bootstrap 5. Extend with a small admin.css if needed."}},
    {"@type":"Question","name":"What are best practices for security?","acceptedAnswer":{"@type":"Answer","text":"Use HTTPS, strict CSP, escape output, rotate keys, and keep dependencies updated."}},
    {"@type":"Question","name":"How do I customize navigation and the footer?","acceptedAnswer":{"@type":"Answer","text":"Edit includes/nav.php and includes/footer.php or use a config-driven menu helper."}},
    {"@type":"Question","name":"Can I use PhPstrap for membership sites?","acceptedAnswer":{"@type":"Answer","text":"Yes—use the built-in auth scaffolding and add billing as modules."}},
    {"@type":"Question","name":"What license does PhPstrap use?","acceptedAnswer":{"@type":"Answer","text":"See the LICENSE file in the repo; commercial use is supported."}},
    {"@type":"Question","name":"Where do I report bugs or request features?","acceptedAnswer":{"@type":"Answer","text":"Open a detailed issue on GitHub with steps to reproduce and environment info."}}
  ]
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
// Simple client-side search/filter for accordion items
(function () {
  const input = document.getElementById('faqSearch');
  const items = Array.from(document.querySelectorAll('[data-faq]'));
  if (!input || !items.length) return;

  input.addEventListener('input', function () {
    const q = this.value.trim().toLowerCase();
    items.forEach(item => {
      const text = item.innerText.toLowerCase();
      item.style.display = q === '' || text.includes(q) ? '' : 'none';
    });
  });
})();

// Copy snippet button
(function () {
  const btn = document.getElementById('copySnippetBtn');
  const code = document.getElementById('faqSnippet');
  if (!btn || !code) return;

  btn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(code.textContent);
      const original = btn.textContent;
      btn.textContent = 'Copied!';
      btn.classList.remove('btn-outline-secondary');
      btn.classList.add('btn-success');
      setTimeout(() => {
        btn.textContent = original;
        btn.classList.add('btn-outline-secondary');
        btn.classList.remove('btn-success');
      }, 1600);
    } catch (e) {
      // Fallback
      const range = document.createRange();
      range.selectNode(code);
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(range);
      document.execCommand('copy');
      sel.removeAllRanges();
    }
  });
})();
</script>