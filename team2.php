<?php
$pageTitle = 'Our Creative Team - PhPstrap Membership';
$metaDescription = 'Meet the innovative minds behind PhPstrap. Our team combines expertise in security, sustainability, and modular design.';
$metaKeywords = 'PhPstrap, creative team, developers, designers, security experts, Bootstrap 5';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

    <!-- Animated Header -->
    <section class="text-center py-5 mb-5">
        <div class="hero-shape position-relative">
            <h1 class="display-3 fw-bold mb-3">Our <span class="text-primary">Creative</span> Team</h1>
            <p class="lead mb-4">The innovative minds behind PhPstrap's secure and modular platform</p>
            <div class="hero-dots">
                <span class="dot dot-1"></span>
                <span class="dot dot-2"></span>
                <span class="dot dot-3"></span>
                <span class="dot dot-4"></span>
            </div>
        </div>
    </section>

    <!-- Team Navigation -->
    <section class="mb-5">
        <ul class="nav nav-pills justify-content-center mb-5" id="teamTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="leadership-tab" data-bs-toggle="pill" data-bs-target="#leadership" type="button" role="tab">Leadership</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="developers-tab" data-bs-toggle="pill" data-bs-target="#developers" type="button" role="tab">Developers</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="designers-tab" data-bs-toggle="pill" data-bs-target="#designers" type="button" role="tab">Designers</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="community-tab" data-bs-toggle="pill" data-bs-target="#community" type="button" role="tab">Community</button>
            </li>
        </ul>
        
        <div class="tab-content" id="teamTabsContent">
            <!-- Leadership Tab -->
            <div class="tab-pane fade show active" id="leadership" role="tabpanel">
                <div class="row row-cols-1 row-cols-md-2 g-4">
                    <!-- CEO -->
                    <div class="col">
                        <div class="card creative-card h-100 border-0">
                            <div class="card-header creative-card-header bg-primary text-white position-relative">
                                <div class="hexagon-shape">
                                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&h=400&q=80" 
                                         class="hexagon-img" alt="Sarah Johnson">
                                </div>
                                <h5 class="mt-4">Sarah Johnson</h5>
                                <p class="mb-0">Founder & CEO</p>
                            </div>
                            <div class="card-body text-center">
                                <p class="card-text">Visionary leader with 15+ years in PHP development and security. Passionate about open-source solutions.</p>
                                <div class="skill-bars mt-4">
                                    <div class="skill-bar mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Security</span>
                                            <span>95%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 95%"></div>
                                        </div>
                                    </div>
                                    <div class="skill-bar mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Leadership</span>
                                            <span>90%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: 90%"></div>
                                        </div>
                                    </div>
                                    <div class="skill-bar">
                                        <div class="d-flex justify-content-between">
                                            <span>Innovation</span>
                                            <span>88%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 88%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer creative-card-footer bg-transparent border-0 text-center">
                                <div class="social-links">
                                    <a href="#" class="btn btn-sm btn-outline-primary rounded-circle me-1"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-primary rounded-circle me-1"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-primary rounded-circle"><i class="fab fa-github"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- CTO -->
                    <div class="col">
                        <div class="card creative-card h-100 border-0">
                            <div class="card-header creative-card-header bg-info text-white position-relative">
                                <div class="hexagon-shape">
                                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&h=400&q=80" 
                                         class="hexagon-img" alt="Michael Chen">
                                </div>
                                <h5 class="mt-4">Michael Chen</h5>
                                <p class="mb-0">Chief Technology Officer</p>
                            </div>
                            <div class="card-body text-center">
                                <p class="card-text">Specializes in modular architecture and scalable systems. Ensures our technology remains cutting-edge.</p>
                                <div class="skill-bars mt-4">
                                    <div class="skill-bar mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Architecture</span>
                                            <span>92%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: 92%"></div>
                                        </div>
                                    </div>
                                    <div class="skill-bar mb-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Scalability</span>
                                            <span>89%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-primary" role="progressbar" style="width: 89%"></div>
                                        </div>
                                    </div>
                                    <div class="skill-bar">
                                        <div class="d-flex justify-content-between">
                                            <span>Innovation</span>
                                            <span>94%</span>
                                        </div>
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: 94%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer creative-card-footer bg-transparent border-0 text-center">
                                <div class="social-links">
                                    <a href="#" class="btn btn-sm btn-outline-info rounded-circle me-1"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-info rounded-circle me-1"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-info rounded-circle"><i class="fab fa-github"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Developers Tab -->
            <div class="tab-pane fade" id="developers" role="tabpanel">
                <div class="row row-cols-1 row-cols-md-3 g-4">
                    <!-- Dev 1 -->
                    <div class="col">
                        <div class="card creative-dev-card h-100 border-0 text-center">
                            <div class="position-relative">
                                <div class="dev-img-container mx-auto">
                                    <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&h=400&q=80" 
                                         class="dev-img" alt="Emma Rodriguez">
                                    <div class Dev-overlay"></div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Emma Rodriguez</h5>
                                <p class="card-text text-success">Lead Developer</p>
                                <p class="card-text">Focuses on core functionality and ensures our codebase remains clean and efficient.</p>
                                <div class="dev-tags">
                                    <span class="badge bg-light text-dark me-1">PHP</span>
                                    <span class="badge bg-light text-dark me-1">Security</span>
                                    <span class="badge bg-light text-dark">API</span>
                                </div>
                            </div>
                            <div class="card-footer creative-card-footer bg-transparent border-0">
                                <div class="social-links">
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle"><i class="fab fa-github"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dev 2 -->
                    <div class="col">
                        <div class="card creative-dev-card h-100 border-0 text-center">
                            <div class="position-relative">
                                <div class="dev-img-container mx-auto">
                                    <img src="https://images.unsplash.com/photo-1618073193730-8afc6b3b3781?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&h=400&q=80" 
                                         class="dev-img" alt="David Kim">
                                    <div class="dev-overlay"></div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">David Kim</h5>
                                <p class="card-text text-success">Security Specialist</p>
                                <p class="card-text">Implements best practices and conducts regular security audits for our platform.</p>
                                <div class="dev-tags">
                                    <span class="badge bg-light text-dark me-1">Security</span>
                                    <span class="badge bg-light text-dark me-1">Encryption</span>
                                    <span class="badge bg-light text-dark">Auditing</span>
                                </div>
                            </div>
                            <div class="card-footer creative-card-footer bg-transparent border-0">
                                <div class="social-links">
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle"><i class="fab fa-github"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dev 3 -->
                    <div class="col">
                        <div class="card creative-dev-card h-100 border-0 text-center">
                            <div class="position-relative">
                                <div class="dev-img-container mx-auto">
                                    <img src="https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?ixlib=rb-1.2.1&auto=format&fit=crop&w=400&h=400&q=80" 
                                         class="dev-img" alt="Priya Patel">
                                    <div class="dev-overlay"></div>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title">Priya Patel</h5>
                                <p class="card-text text-success">Module Developer</p>
                                <p class="card-text">Designs and builds the modular components that make PhPstrap extensible.</p>
                                <div class="dev-tags">
                                    <span class="badge bg-light text-dark me-1">Modules</span>
                                    <span class="badge bg-light text-dark me-1">Plugins</span>
                                    <span class="badge bg-light text-dark">API</span>
                                </div>
                            </div>
                            <div class="card-footer creative-card-footer bg-transparent border-0">
                                <div class="social-links">
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-twitter"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle me-1"><i class="fab fa-linkedin-in"></i></a>
                                    <a href="#" class="btn btn-sm btn-outline-success rounded-circle"><i class="fab fa-github"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Designers Tab -->
            <div class="tab-pane fade" id="designers" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-8">
                        <div class="card creative-card border-0 text-center p-5">
                            <i class="fas fa-palette fa-4x text-warning mb-3"></i>
                            <h3>Our Design Philosophy</h3>
                            <p class="lead">At PhPstrap, we believe in creating interfaces that are not only beautiful but also intuitive, accessible, and user-friendly.</p>
                            <p>Our design team works closely with developers to ensure a seamless experience that aligns with our core values of security, sustainability, and modularity.</p>
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-eye fa-2x text-primary"></i>
                                    </div>
                                    <h5>Visual Appeal</h5>
                                    <p>Creating interfaces that are aesthetically pleasing</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-hand-pointer fa-2x text-success"></i>
                                    </div>
                                    <h5>Usability</h5>
                                    <p>Ensuring intuitive and easy-to-use experiences</p>
                                </div>
                                <div class="col-md-4">
                                    <div class="feature-icon mb-3">
                                        <i class="fas fa-puzzle-piece fa-2x text-info"></i>
                                    </div>
                                    <h5>Consistency</h5>
                                    <p>Maintaining a cohesive design language throughout</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Community Tab -->
            <div class="tab-pane fade" id="community" role="tabpanel">
                <div class="row justify-content-center">
                    <div class="col-lg-10">
                        <div class="card creative-community-card border-0 text-center p-5">
                            <i class="fas fa-users fa-4x text-primary mb-3"></i>
                            <h3>Our Open Source Community</h3>
                            <p class="lead">PhPstrap thrives thanks to our amazing community of contributors from around the world.</p>
                            
                            <div class="row mt-5">
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Become a Contributor</h5>
                                            <p class="card-text">Join our community of developers improving PhPstrap</p>
                                            <a href="#" class="btn btn-outline-primary">Contribution Guide</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-0 shadow-sm mb-4">
                                        <div class="card-body">
                                            <h5 class="card-title">Report Issues</h5>
                                            <p class="card-text">Help us improve by reporting bugs or suggesting features</p>
                                            <a href="#" class="btn btn-outline-primary">GitHub Issues</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h4>Community Stats</h4>
                                <div class="row mt-4">
                                    <div class="col-4">
                                        <div class="display-6 fw-bold text-primary">250+</div>
                                        <div class="text-muted">Contributors</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="display-6 fw-bold text-success">1.2K</div>
                                        <div class="text-muted">Commits</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="display-6 fw-bold text-info">84</div>
                                        <div class="text-muted">Modules</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="text-center py-5 mt-3">
        <div class="cta-background p-5 rounded-3">
            <h2 class="fw-bold mb-3">Join Our Creative Team</h2>
            <p class="lead mb-4">We're always looking for talented individuals who are passionate about security and open-source</p>
            <a href="#" class="btn btn-primary btn-lg me-2">View Open Positions</a>
            <a href="/contact.php" class="btn btn-outline-light btn-lg">Contact Us</a>
        </div>
    </section>

</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Creative styling */
.hero-shape {
    overflow: hidden;
}

.hero-dots {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    z-index: -1;
}

.dot {
    position: absolute;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.2);
}

.dot-1 {
    top: 20%;
    left: 20%;
    animation: float 6s ease-in-out infinite;
}

.dot-2 {
    top: 60%;
    right: 20%;
    background: rgba(25, 135, 84, 0.2);
    animation: float 8s ease-in-out infinite;
}

.dot-3 {
    bottom: 30%;
    left: 30%;
    background: rgba(13, 202, 240, 0.2);
    animation: float 7s ease-in-out infinite 1s;
}

.dot-4 {
    top: 30%;
    right: 30%;
    background: rgba(255, 193, 7, 0.2);
    animation: float 9s ease-in-out infinite 0.5s;
}

@keyframes float {
    0%, 100% { transform: translateY(0) translateX(0); }
    50% { transform: translateY(-20px) translateX(20px); }
}

.creative-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    overflow: hidden;
}

.creative-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}

.creative-card-header {
    padding: 2rem 1rem 3rem;
    text-align: center;
}

.hexagon-shape {
    width: 120px;
    height: 120px;
    margin: 0 auto;
    position: relative;
    overflow: hidden;
    border-radius: 50%;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translateY(30px);
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
}

.hexagon-img {
    width: 110px;
    height: 110px;
    object-fit: cover;
    border-radius: 50%;
}

.creative-dev-card {
    transition: all 0.3s ease;
}

.creative-dev-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1) !important;
}

.dev-img-container {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    overflow: hidden;
    position: relative;
    margin-top: -75px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dev-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dev-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(transparent 70%, rgba(0,0,0,0.7));
}

.dev-tags {
    margin: 1rem 0;
}

.creative-community-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.cta-background {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
    color: white;
}

.feature-icon {
    width: 70px;
    height: 70px;
    line-height: 70px;
    border-radius: 50%;
    background: rgba(13, 110, 253, 0.1);
    margin: 0 auto;
}

.social-links .btn {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.skill-bar .progress {
    border-radius: 3px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add animation to elements when they come into view
    const animatedElements = document.querySelectorAll('.creative-card, .creative-dev-card');
    
    const observerOptions = {
        threshold: 0.2,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries, observer) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = 1;
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    animatedElements.forEach(element => {
        element.style.opacity = 0;
        element.style.transform = 'translateY(20px)';
        element.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
        observer.observe(element);
    });
    
    // Add tab activation animation
    const tabPanes = document.querySelectorAll('.tab-pane');
    tabPanes.forEach(pane => {
        pane.addEventListener('show.bs.tab', function() {
            this.style.opacity = 0;
            setTimeout(() => {
                this.style.opacity = 1;
            }, 150);
        });
    });
});
</script>