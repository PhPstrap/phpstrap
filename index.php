<?php
$pageTitle = 'Welcome to PhPstrap Membership';
$metaDescription = 'Build a modern membership site using PhPstrap and Bootstrap 5.';
$metaKeywords = 'PhPstrap, PHP membership site, Bootstrap, login, register';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

    <!-- Hero Section -->
    <div class="text-center py-5 mb-5 bg-light rounded shadow-sm">
        <h1 class="display-4 fw-bold">Welcome to PhPstrap</h1>
        <p class="lead">Build secure and scalable membership websites with PHP and Bootstrap 5 — fast.</p>
        <div class="d-flex justify-content-center gap-3 mt-4">
            <a href="/login/register.php" class="btn btn-primary btn-lg">Sign Up</a>
            <a href="/login/index.php" class="btn btn-outline-secondary btn-lg">Log In</a>
        </div>
    </div>

    <!-- Features Section -->
    <div class="row text-center mb-5">
        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <i class="fas fa-user-shield fa-2x mb-3 text-primary"></i>
                    <h5 class="card-title">User Management</h5>
                    <p class="card-text">Register, log in, manage roles, and control access with powerful user tools.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <i class="fas fa-puzzle-piece fa-2x mb-3 text-success"></i>
                    <h5 class="card-title">Modular Design</h5>
                    <p class="card-text">Extend functionality easily with a modular plug-and-play system.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <i class="fas fa-lock fa-2x mb-3 text-danger"></i>
                    <h5 class="card-title">Secure & Modern</h5>
                    <p class="card-text">Built with security in mind, using the latest PHP standards and Bootstrap 5.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="text-center mt-5">
        <h2>Launch your membership site today</h2>
        <p class="lead">PhPstrap gives you everything you need to get started quickly.</p>
        <a href="/login/register.php" class="btn btn-success btn-lg me-2">Create an Account</a>
        <a href="/login/index.php" class="btn btn-outline-dark btn-lg">Access My Account</a>
    </div>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>