<?php
$pageTitle = 'Our Team - PhPstrap Membership';
$metaDescription = 'Meet the talented team behind PhPstrap. Learn about our experts in security, development, and design.';
$metaKeywords = 'PhPstrap, team, developers, designers, security experts, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

  <!-- Page Header -->
  <header class="text-center mb-5">
    <p class="text-uppercase text-muted mb-2 small">PhPstrap Team</p>
    <h1 class="display-5 fw-bold mb-2">Meet Our Team</h1>
    <p class="lead mb-0">The people behind our secure, sustainable, and modular platform.</p>
  </header>

  <!-- Leadership -->
  <section class="mb-5">
    <h2 class="h3 text-center fw-semibold mb-4">Leadership</h2>

    <div class="row g-4 align-items-stretch justify-content-center">
      <!-- Leader 1 -->
      <div class="col-xl-5 col-lg-6">
        <article class="card h-100 border-0 shadow-sm">
          <div class="row g-0 h-100">
            <div class="col-md-5">
              <div class="ratio ratio-4x5">
                <img
                  src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
                  class="img-fluid rounded-start" alt="Portrait of Sarah Johnson, Founder and CEO">
              </div>
            </div>
            <div class="col-md-7">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 mb-1">Sarah Johnson</h3>
                <p class="text-primary mb-3">Founder &amp; CEO</p>
                <p class="mb-4">
                  With 15+ years in PHP development and security, Sarah sets the vision for open-source, secure solutions.
                </p>
                <ul class="list-inline mt-auto mb-0">
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Sarah on X/Twitter"><i class="fab fa-twitter"></i></a></li>
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Sarah on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Sarah on GitHub"><i class="fab fa-github"></i></a></li>
                </ul>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Leader 2 -->
      <div class="col-xl-5 col-lg-6">
        <article class="card h-100 border-0 shadow-sm">
          <div class="row g-0 h-100">
            <div class="col-md-5">
              <div class="ratio ratio-4x5">
                <img
                  src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
                  class="img-fluid rounded-start" alt="Portrait of Michael Chen, CTO">
              </div>
            </div>
            <div class="col-md-7">
              <div class="card-body d-flex flex-column">
                <h3 class="h5 mb-1">Michael Chen</h3>
                <p class="text-primary mb-3">CTO</p>
                <p class="mb-4">
                  Specialist in modular architecture and scalable systems, keeping our stack reliable and future-ready.
                </p>
                <ul class="list-inline mt-auto mb-0">
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Michael on X/Twitter"><i class="fab fa-twitter"></i></a></li>
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Michael on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
                  <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Michael on GitHub"><i class="fab fa-github"></i></a></li>
                </ul>
              </div>
            </div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- Development Team -->
  <section class="mb-5">
    <h2 class="h3 text-center fw-semibold mb-4">Development Team</h2>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
      <!-- Dev 1 -->
      <div class="col">
        <article class="card h-100 border-0 shadow-sm text-center">
          <div class="p-4 pb-0">
            <img
              src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
              alt="Emma Rodriguez"
              class="rounded-circle img-fluid"
              style="width:120px;height:120px;object-fit:cover;">
          </div>
          <div class="card-body">
            <h3 class="h6 mb-1">Emma Rodriguez</h3>
            <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">Lead Developer</span>
            <p class="small mb-0">Keeps the codebase clean and efficient across core functionality.</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <ul class="list-inline mb-0">
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Emma on X/Twitter"><i class="fab fa-twitter"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Emma on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Emma on GitHub"><i class="fab fa-github"></i></a></li>
            </ul>
          </div>
        </article>
      </div>

      <!-- Dev 2 -->
      <div class="col">
        <article class="card h-100 border-0 shadow-sm text-center">
          <div class="p-4 pb-0">
            <img
              src="https://images.unsplash.com/photo-1618073193730-8afc6b3b3781?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
              alt="David Kim"
              class="rounded-circle img-fluid"
              style="width:120px;height:120px;object-fit:cover;">
          </div>
          <div class="card-body">
            <h3 class="h6 mb-1">David Kim</h3>
            <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">Security Specialist</span>
            <p class="small mb-0">Implements best practices and regular security audits.</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <ul class="list-inline mb-0">
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="David on X/Twitter"><i class="fab fa-twitter"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="David on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="David on GitHub"><i class="fab fa-github"></i></a></li>
            </ul>
          </div>
        </article>
      </div>

      <!-- Dev 3 -->
      <div class="col">
        <article class="card h-100 border-0 shadow-sm text-center">
          <div class="p-4 pb-0">
            <img
              src="https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
              alt="Alex Turner"
              class="rounded-circle img-fluid"
              style="width:120px;height:120px;object-fit:cover;">
          </div>
          <div class="card-body">
            <h3 class="h6 mb-1">Alex Turner</h3>
            <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">Frontend Developer</span>
            <p class="small mb-0">Builds responsive, accessible UIs that feel great to use.</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <ul class="list-inline mb-0">
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Alex on X/Twitter"><i class="fab fa-twitter"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Alex on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Alex on GitHub"><i class="fab fa-github"></i></a></li>
            </ul>
          </div>
        </article>
      </div>

      <!-- Dev 4 -->
      <div class="col">
        <article class="card h-100 border-0 shadow-sm text-center">
          <div class="p-4 pb-0">
            <img
              src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?ixlib=rb-1.2.1&auto=format&fit=crop&w=800&q=80"
              alt="Priya Patel"
              class="rounded-circle img-fluid"
              style="width:120px;height:120px;object-fit:cover;">
          </div>
          <div class="card-body">
            <h3 class="h6 mb-1">Priya Patel</h3>
            <span class="badge bg-success-subtle text-success border border-success-subtle mb-2">Module Developer</span>
            <p class="small mb-0">Designs the extensible modules that power PhPstrap.</p>
          </div>
          <div class="card-footer bg-transparent border-0">
            <ul class="list-inline mb-0">
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Priya on X/Twitter"><i class="fab fa-twitter"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Priya on LinkedIn"><i class="fab fa-linkedin"></i></a></li>
              <li class="list-inline-item"><a class="link-secondary" href="#" aria-label="Priya on GitHub"><i class="fab fa-github"></i></a></li>
            </ul>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- Community -->
  <section class="mb-5">
    <div class="text-center mb-4">
      <h2 class="h3 fw-semibold">Community Contributors</h2>
      <p class="mb-0 text-muted">PhPstrap thrives thanks to our amazing open-source community.</p>
    </div>

    <div class="row justify-content-center">
      <div class="col-lg-10">
        <div class="card border-0 shadow-sm">
          <div class="card-body p-4 p-md-5 text-center">
            <i class="fas fa-users fa-2x text-primary mb-3" aria-hidden="true"></i>
            <h3 class="h5">Join Our Community</h3>
            <p class="mb-4">We welcome contributors of all skill levels. Pair up on issues, submit PRs, and help shape the roadmap.</p>
            <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
              <a href="#" class="btn btn-primary"><i class="fab fa-github me-2" aria-hidden="true"></i>GitHub</a>
              <a href="#" class="btn btn-outline-secondary">Documentation</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- CTA -->
  <section class="text-center py-4">
    <h2 class="h3 fw-semibold mb-2">Want to Join Our Team?</h2>
    <p class="lead fs-5 text-muted mb-4">We’re always looking for people passionate about open-source and security.</p>
    <div class="d-flex flex-column flex-sm-row gap-2 justify-content-center">
      <a href="#" class="btn btn-primary btn-lg">View Open Positions</a>
      <a href="/contact.php" class="btn btn-outline-secondary btn-lg">Contact Us</a>
    </div>
  </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Minimal tweaks to complement Bootstrap defaults */
.badge.bg-success-subtle {
  background-color: rgba(25, 135, 84, .1);
}
.border-success-subtle {
  border-color: rgba(25, 135, 84, .2) !important;
}
</style>