<?php
$pageTitle = 'PhPstrap Membership - Secure, Sustainable & Modular';
$metaDescription = 'Build modern, secure membership sites with our open-source PHP framework. Featuring modular design and sustainable collaboration.';
$metaKeywords = 'PhPstrap, PHP membership, secure, open source, Bootstrap 5, modular design';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

    <!-- Hero Section -->
    <section class="text-center py-5 mb-5 rounded-3" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
        <div class="row py-lg-5">
            <div class="col-lg-8 col-md-10 mx-auto">
                <h1 class="display-4 fw-bold mb-4">Build Secure Membership Sites</h1>
                <p class="lead mb-4">PhPstrap combines <span class="text-primary fw-bold">security</span>, <span class="text-success fw-bold">sustainability</span>, and <span class="text-info fw-bold">modularity</span> in an open-source PHP framework.</p>
                <div class="d-flex justify-content-center gap-3 mt-4 flex-wrap">
                    <a href="/login/register.php" class="btn btn-primary btn-lg px-4">Get Started</a>
                    <a href="#features" class="btn btn-outline-secondary btn-lg px-4">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Value Propositions -->
    <div class="row mb-5">
        <div class="col-md-4 text-center mb-4">
            <div class="p-4 bg-white rounded-3 shadow-sm h-100">
                <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3">
                    <i class="fas fa-shield-alt fa-2x text-primary"></i>
                </div>
                <h3 class="h4">Secure & Open Source</h3>
                <p>Transparent security architecture with community-reviewed code. Regular updates and vulnerability patching.</p>
            </div>
        </div>
        
        <div class="col-md-4 text-center mb-4">
            <div class="p-4 bg-white rounded-3 shadow-sm h-100">
                <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3">
                    <i class="fas fa-users fa-2x text-success"></i>
                </div>
                <h3 class="h4">Sustain & Maintain</h3>
                <p>Open collaboration model ensures long-term sustainability with community-driven maintenance and improvements.</p>
            </div>
        </div>
        
        <div class="col-md-4 text-center mb-4">
            <div class="p-4 bg-white rounded-3 shadow-sm h-100">
                <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex p-4 mb-3">
                    <i class="fas fa-cubes fa-2x text-info"></i>
                </div>
                <h3 class="h4">Modular & Extensible</h3>
                <p>Flexible plugin system with seamless integrations. Build custom functionality with our modular add-ons.</p>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section id="features" class="mb-5">
        <div class="text-center mb-5">
            <h2 class="fw-bold">Powerful Features</h2>
            <p class="lead text-muted">Everything you need to build and manage your membership platform</p>
        </div>
        
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="d-flex">
                    <div class="me-4">
                        <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-user-lock fa-lg text-primary"></i>
                        </div>
                    </div>
                    <div>
                        <h4>Secure Authentication</h4>
                        <p>Industry-standard encryption, secure session management, and protection against common vulnerabilities.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="d-flex">
                    <div class="me-4">
                        <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-code-branch fa-lg text-success"></i>
                        </div>
                    </div>
                    <div>
                        <h4>Open Collaboration</h4>
                        <p>Community-driven development with transparent processes and contribution guidelines.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="d-flex">
                    <div class="me-4">
                        <div class="bg-info bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-puzzle-piece fa-lg text-info"></i>
                        </div>
                    </div>
                    <div>
                        <h4>Modular Architecture</h4>
                        <p>Add or remove features with our plugin system. Create custom modules tailored to your needs.</p>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="d-flex">
                    <div class="me-4">
                        <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                            <i class="fas fa-tools fa-lg text-warning"></i>
                        </div>
                    </div>
                    <div>
                        <h4>Easy Customization</h4>
                        <p>Flexible theming system with Bootstrap 5 foundation. Modify layouts and styles without breaking functionality.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="text-center py-5 mt-5 bg-light rounded-3">
        <h2 class="fw-bold mb-3">Ready to Get Started?</h2>
        <p class="lead mb-4">Join our community of developers building secure, sustainable membership platforms</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/login/register.php" class="btn btn-success btn-lg px-4">Create Free Account</a>
            <a href="#" class="btn btn-outline-dark btn-lg px-4">View Documentation</a>
            <a href="#" class="btn btn-outline-primary btn-lg px-4">GitHub Repository</a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>