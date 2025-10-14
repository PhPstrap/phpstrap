<?php
$pageTitle = 'FAQ - PhPstrap Membership';
$metaDescription = 'Frequently asked questions about PhPstrap grouped by category.';
$metaKeywords = 'PhPstrap, FAQ, categories, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">
  <!-- Page Header -->
  <header class="text-center mb-5">
    <h1 class="display-6 fw-bold mb-2">Frequently Asked Questions</h1>
    <p class="text-muted mb-0">Browse by category or expand questions for quick answers</p>
  </header>

  <div class="row row-cols-1 row-cols-md-2 g-4">
    <!-- Column 1 -->
    <div class="col">
      <h2 class="h5 fw-semibold mb-3">Getting Started</h2>
      <div class="accordion" id="faqCol1">
        <!-- Install -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q1-h">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q1">
              How do I install PhPstrap?
            </button>
          </h2>
          <div id="q1" class="accordion-collapse collapse show" data-bs-parent="#faqCol1">
            <div class="accordion-body">
              Upload files, configure <code>includes/site-config.php</code>, run the installer, then visit your domain.
            </div>
          </div>
        </div>

        <!-- Modules -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q2-h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q2">
              How do modules work?
            </button>
          </h2>
          <div id="q2" class="accordion-collapse collapse" data-bs-parent="#faqCol1">
            <div class="accordion-body">
              Modules live in <code>/modules</code> with a manifest. Enable or disable them from the Admin → Modules page.
            </div>
          </div>
        </div>

        <!-- Bootstrap -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q3-h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q3">
              Does PhPstrap support Bootstrap 5?
            </button>
          </h2>
          <div id="q3" class="accordion-collapse collapse" data-bs-parent="#faqCol1">
            <div class="accordion-body">
              Yes, all templates use Bootstrap 5 defaults. Extend styling via a lightweight <code>admin.css</code>.
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Column 2 -->
    <div class="col">
      <h2 class="h5 fw-semibold mb-3">Advanced &amp; Community</h2>
      <div class="accordion" id="faqCol2">
        <!-- Security -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q4-h">
            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#q4">
              What are best practices for security?
            </button>
          </h2>
          <div id="q4" class="accordion-collapse collapse show" data-bs-parent="#faqCol2">
            <div class="accordion-body">
              Use HTTPS, strict CSP, escape output, rotate API keys, and keep dependencies updated.
            </div>
          </div>
        </div>

        <!-- Membership -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q5-h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q5">
              Can I use PhPstrap for membership sites?
            </button>
          </h2>
          <div id="q5" class="accordion-collapse collapse" data-bs-parent="#faqCol2">
            <div class="accordion-body">
              Yes—PhPstrap has built-in auth scaffolding. Add subscription logic as modules to keep core clean.
            </div>
          </div>
        </div>

        <!-- License -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q6-h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q6">
              What license does PhPstrap use?
            </button>
          </h2>
          <div id="q6" class="accordion-collapse collapse" data-bs-parent="#faqCol2">
            <div class="accordion-body">
              PhPstrap is open-source under the terms in the <code>LICENSE</code> file. Commercial use is allowed.
            </div>
          </div>
        </div>

        <!-- Bugs -->
        <div class="accordion-item">
          <h2 class="accordion-header" id="q7-h">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#q7">
              Where do I report bugs?
            </button>
          </h2>
          <div id="q7" class="accordion-collapse collapse" data-bs-parent="#faqCol2">
            <div class="accordion-body">
              Open an issue on GitHub with steps to reproduce, environment info, and logs if possible.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>