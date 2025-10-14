<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visualize - Creative Gallery & Portfolio</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6f42c1;
            --secondary: #20c997;
            --dark: #212529;
            --light: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            overflow-x: hidden;
        }
        
        .navbar {
            padding: 1rem 2rem;
            background: rgba(255, 255, 255, 0.95) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.8rem;
        }
        
        .nav-link {
            font-weight: 500;
            margin: 0 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(45deg, var(--primary), #8a63d2);
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 50px;
            font-weight: 500;
        }
        
        .hero-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1518837695005-2083093ee35b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1500&q=80');
            background-size: cover;
            background-position: center;
            padding: 8rem 0;
            color: white;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 3rem;
            font-weight: 700;
        }
        
        .section-title:after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            margin: 15px auto 0;
            border-radius: 2px;
        }
        
        .feature-box {
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 30px;
            background: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .feature-box:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .gallery-item {
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }
        
        .gallery-item:hover {
            transform: translateY(-8px);
        }
        
        .testimonial-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin: 1rem;
        }
        
        .testimonial-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary);
        }
        
        .cta-section {
            background: linear-gradient(45deg, #6f42c1, #20c997);
            color: white;
            padding: 5rem 0;
        }
        
        footer {
            background: var(--dark);
            color: white;
            padding: 4rem 0 2rem;
        }
        
        .social-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .social-icon:hover {
            background: var(--primary);
            transform: translateY(-3px);
        }
        
        .btn-filter {
            border: 2px solid transparent;
            border-radius: 50px;
            padding: 0.5rem 1.5rem;
            transition: all 0.3s ease;
            background: transparent;
            color: #6c757d;
            margin: 0 5px 10px;
        }
        
        .btn-filter.active, .btn-filter:hover {
            background: linear-gradient(45deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">Visualize</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Gallery</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Contact</a>
                    </li>
                </ul>
                <div class="ms-3">
                    <button class="btn btn-primary">Get Started</button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-4">Showcase Your Creativity With Visualize</h1>
                    <p class="lead mb-4">A stunning gallery template built with Bootstrap 5 to showcase your photography, art, or portfolio in style.</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button class="btn btn-primary btn-lg">Explore Gallery</button>
                        <button class="btn btn-outline-light btn-lg">Learn More</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Why Choose Visualize</h2>
                <p class="lead">Discover the features that make our gallery template stand out</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-palette"></i>
                        </div>
                        <h4>Beautiful Design</h4>
                        <p>Elegant and modern design that showcases your images in the best light with smooth animations.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <h4>Fully Responsive</h4>
                        <p>Looks perfect on all devices, from mobile phones to desktop computers with adaptive layouts.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h4>Easy Customization</h4>
                        <p>Change colors, fonts, and layouts with simple CSS variables and well-organized code.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Preview Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">Featured Gallery</h2>
                <p class="lead">A selection of our best work showcased in various categories</p>
            </div>
            
            <!-- Filter Controls -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-center flex-wrap">
                        <button class="btn btn-filter active" data-filter="all">All Images</button>
                        <button class="btn btn-filter" data-filter="nature">Nature</button>
                        <button class="btn btn-filter" data-filter="technology">Technology</button>
                        <button class="btn btn-filter" data-filter="abstract">Abstract</button>
                    </div>
                </div>
            </div>
            
            <!-- Gallery Grid -->
            <div class="row">
                <div class="col-lg-4 col-md-6" data-category="nature">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1501854140801-50d01698950b?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Nature landscape">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-category="technology">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Technology">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-category="abstract">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1541701494587-cb58502866ab?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Abstract art">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-category="nature">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1470240731273-7821a6eeb6bd?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Forest">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-category="technology">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Coding">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6" data-category="abstract">
                    <div class="gallery-item">
                        <img src="https://images.unsplash.com/photo-1543857778-c4a1a569eafe?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                             class="img-fluid" alt="Abstract design">
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button class="btn btn-primary">View Full Gallery</button>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">What Our Users Say</h2>
                <p class="lead">Hear from photographers and artists who use our template</p>
            </div>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" 
                                 class="testimonial-img" alt="User">
                            <div class="ms-3">
                                <h5 class="mb-0">Michael Chen</h5>
                                <p class="text-muted mb-0">Professional Photographer</p>
                            </div>
                        </div>
                        <p class="mb-0">"This gallery template has completely transformed how I showcase my work to clients. The filtering system is intuitive and the design is stunning."</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" 
                                 class="testimonial-img" alt="User">
                            <div class="ms-3">
                                <h5 class="mb-0">Sarah Johnson</h5>
                                <p class="text-muted mb-0">Digital Artist</p>
                            </div>
                        </div>
                        <p class="mb-0">"I've tried many gallery templates, but this one stands out for its flexibility and modern design. My artwork has never looked better!"</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=200&q=80" 
                                 class="testimonial-img" alt="User">
                            <div class="ms-3">
                                <h5 class="mb-0">David Wilson</h5>
                                <p class="text-muted mb-0">Graphic Designer</p>
                            </div>
                        </div>
                        <p class="mb-0">"The attention to detail in this template is impressive. It's clear that the developers understand what artists need to showcase their work effectively."</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section text-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <h2 class="display-5 fw-bold mb-4">Ready to Showcase Your Work?</h2>
                    <p class="lead mb-4">Join thousands of creatives who use Visualize to present their portfolios</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <button class="btn btn-light btn-lg">Get Started Now</button>
                        <button class="btn btn-outline-light btn-lg">View Demo</button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h4 class="text-white mb-4">Visualize</h4>
                    <p>The perfect gallery template for photographers, artists, and creatives to showcase their work.</p>
                    <div class="mt-3">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="text-white mb-4">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Gallery</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Pricing</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-4 mb-4">
                    <h5 class="text-white mb-4">Support</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">FAQs</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Documentation</a></li>
                        <li class="mb-2"><a href="#" class="text-white text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-lg-4 col-md-4 mb-4">
                    <h5 class="text-white mb-4">Subscribe to Our Newsletter</h5>
                    <p>Stay updated with our latest templates and news.</p>
                    <form>
                        <div class="input-group">
                            <input type="email" class="form-control" placeholder="Your email address">
                            <button class="btn btn-primary" type="submit">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
            <hr class="mt-4 mb-4">
            <div class="text-center">
                <p class="mb-0">&copy; 2023 Visualize. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterButtons = document.querySelectorAll('[data-filter]');
            const galleryItems = document.querySelectorAll('.gallery-item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const filterValue = this.getAttribute('data-filter');
                    
                    // Update active button
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Filter items
                    galleryItems.forEach(item => {
                        const category = item.parentElement.getAttribute('data-category');
                        
                        if (filterValue === 'all' || filterValue === category) {
                            item.parentElement.style.display = 'block';
                        } else {
                            item.parentElement.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>