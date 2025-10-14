<?php
$pageTitle = 'Creative Gallery - PhPstrap Membership';
$metaDescription = 'Explore our stunning gallery showcasing PhPstrap capabilities with Bootstrap 5.';
$metaKeywords = 'PhPstrap, gallery, Bootstrap 5, photos, images, creative';
include __DIR__ . '/includes/header.php';
?>

<main class="container-fluid px-0">

    <!-- Hero Section with Parallax Effect -->
    <section class="hero-section bg-dark text-white py-5">
        <div class="parallax-bg" style="background-image: url('https://images.unsplash.com/photo-1518837695005-2083093ee35b?ixlib=rb-1.2.1&auto=format&fit=crop&w=1500&q=80');"></div>
        <div class="container position-relative py-5" style="z-index: 2;">
            <div class="row justify-content-center text-center py-5">
                <div class="col-lg-8">
                    <h1 class="display-3 fw-bold mb-3">PhPstrap Creative Gallery</h1>
                    <p class="lead mb-4">A visually stunning showcase of Bootstrap 5 capabilities with PhPstrap</p>
                    <a href="#gallery" class="btn btn-primary btn-lg rounded-pill px-4 py-2">Explore Gallery</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Filter Controls with Creative Design -->
    <section class="sticky-top bg-white shadow-sm py-3" id="gallery">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12">
                    <div class="d-flex justify-content-center flex-wrap filter-controls">
                        <button class="btn btn-filter m-1 active" data-filter="all">
                            <i class="fas fa-th me-2"></i>All Images
                        </button>
                        <button class="btn btn-filter m-1" data-filter="nature">
                            <i class="fas fa-tree me-2"></i>Nature
                        </button>
                        <button class="btn btn-filter m-1" data-filter="technology">
                            <i class="fas fa-laptop-code me-2"></i>Technology
                        </button>
                        <button class="btn btn-filter m-1" data-filter="abstract">
                            <i class="fas fa-paint-brush me-2"></i>Abstract
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Grid with Masonry Layout -->
    <section class="py-5">
        <div class="container">
            <div class="row grid" id="gallery-grid" data-masonry='{"percentPosition": true}'>
                <!-- Gallery Item 1 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="nature">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1501854140801-50d01698950b?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Nature landscape">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Mountain Landscape</h5>
                            <p class="card-text">Beautiful natural scenery showcasing the outdoors.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">September 12, 2023</small>
                                <span class="badge bg-success">Nature</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Item 2 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="technology">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Technology">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Modern Technology</h5>
                            <p class="card-text">Cutting-edge devices and innovation.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">August 28, 2023</small>
                                <span class="badge bg-info">Technology</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Item 3 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="abstract">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1541701494587-cb58502866ab?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Abstract art">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Colorful Abstract</h5>
                            <p class="card-text">Vibrant colors and patterns in abstract form.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">July 15, 2023</small>
                                <span class="badge bg-warning text-dark">Abstract</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Item 4 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="nature">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1470240731273-7821a6eeb6bd?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Forest">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Forest Pathway</h5>
                            <p class="card-text">Sunlight filtering through a dense forest.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">June 30, 2023</small>
                                <span class="badge bg-success">Nature</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Item 5 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="technology">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Coding">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Web Development</h5>
                            <p class="card-text">Programming and code in action.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">May 22, 2023</small>
                                <span class="badge bg-info">Technology</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gallery Item 6 -->
                <div class="col-lg-4 col-md-6 mb-4 grid-item" data-category="abstract">
                    <div class="card creative-card h-100 shadow-sm gallery-item">
                        <div class="image-container">
                            <img src="https://images.unsplash.com/photo-1543857778-c4a1a569eafe?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                                 class="card-img-top" alt="Abstract design">
                            <div class="image-overlay">
                                <div class="overlay-content">
                                    <h5 class="text-white">View Details</h5>
                                    <button class="btn btn-sm btn-light rounded-pill">View</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Geometric Patterns</h5>
                            <p class="card-text">Modern geometric shapes and patterns.</p>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">April 18, 2023</small>
                                <span class="badge bg-warning text-dark">Abstract</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination with Creative Styling -->
            <nav aria-label="Gallery navigation" class="mt-5">
                <ul class="pagination justify-content-center creative-pagination">
                    <li class="page-item">
                        <a class="page-link rounded-pill me-1" href="#" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <li class="page-item active" aria-current="page">
                        <a class="page-link" href="#">1</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">2</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="#">3</a>
                    </li>
                    <li class="page-item">
                        <a class="page-link rounded-pill ms-1" href="#" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </section>

</main>

<!-- Modal for Image View -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image Title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" class="img-fluid rounded" alt="Modal image">
                <div class="mt-3">
                    <p class="image-description">Image description will appear here.</p>
                    <div class="d-flex justify-content-center mt-3">
                        <span class="badge bg-secondary me-2">Category</span>
                        <small class="text-muted">Date: September 12, 2023</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary"><i class="fas fa-download me-2"></i>Download</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
/* Hero Section with Parallax */
.hero-section {
    position: relative;
    overflow: hidden;
}

.parallax-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    opacity: 0.6;
    z-index: 1;
}

/* Filter Buttons */
.btn-filter {
    border: 2px solid transparent;
    border-radius: 50px;
    padding: 0.5rem 1.5rem;
    transition: all 0.3s ease;
    background: transparent;
    color: #6c757d;
}

.btn-filter.active, .btn-filter:hover {
    background: linear-gradient(45deg, #0d6efd, #0dcaf0);
    color: white;
    border-color: transparent;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Creative Cards */
.creative-card {
    border: none;
    border-radius: 15px;
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.creative-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1) !important;
}

.image-container {
    position: relative;
    overflow: hidden;
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.creative-card:hover .image-overlay {
    opacity: 1;
}

.overlay-content {
    text-align: center;
    transform: translateY(20px);
    transition: transform 0.3s ease;
}

.creative-card:hover .overlay-content {
    transform: translateY(0);
}

/* Pagination */
.creative-pagination .page-item .page-link {
    border-radius: 50%;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 5px;
    border: none;
    color: #6c757d;
}

.creative-pagination .page-item.active .page-link {
    background: linear-gradient(45deg, #0d6efd, #0dcaf0);
    color: white;
}

.creative-pagination .page-item .page-link:hover {
    background-color: #f8f9fa;
}

/* Masonry Grid Layout */
.grid {
    transition: all 0.3s ease;
}

.grid-item {
    margin-bottom: 1.5rem;
}

/* Sticky Filter */
.sticky-top {
    z-index: 1020;
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95) !important;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .hero-section .display-3 {
        font-size: 2.5rem;
    }
    
    .btn-filter {
        padding: 0.4rem 1rem;
        font-size: 0.9rem;
    }
    
    .creative-pagination .page-item .page-link {
        width: 40px;
        height: 40px;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/masonry-layout@4.2.2/dist/masonry.pkgd.min.js"></script>
<script>
// Initialize Masonry layout
document.addEventListener('DOMContentLoaded', function() {
    const grid = document.querySelector('.grid');
    const masonry = new Masonry(grid, {
        itemSelector: '.grid-item',
        percentPosition: true
    });
    
    // Filter functionality
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
                const category = item.closest('.grid-item').getAttribute('data-category');
                
                if (filterValue === 'all' || filterValue === category) {
                    item.closest('.grid-item').style.display = 'block';
                } else {
                    item.closest('.grid-item').style.display = 'none';
                }
            });
            
            // Re-layout masonry after filtering
            masonry.layout();
        });
    });
    
    // Add modal functionality for images
    const galleryImages = document.querySelectorAll('.gallery-item');
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const modalTitle = document.getElementById('imageModalLabel');
    const modalImage = document.querySelector('#imageModal img');
    const modalDescription = document.querySelector('.image-description');
    const modalCategory = document.querySelector('#imageModal .badge');
    const modalDate = document.querySelector('#imageModal .text-muted');
    
    galleryImages.forEach(item => {
        item.addEventListener('click', function() {
            const card = this;
            const title = card.querySelector('.card-title').textContent;
            const description = card.querySelector('.card-text').textContent;
            const category = card.querySelector('.badge').textContent;
            const date = card.querySelector('.text-muted').textContent;
            const imageSrc = card.querySelector('img').src;
            
            modalTitle.textContent = title;
            modalImage.src = imageSrc;
            modalDescription.textContent = description;
            modalCategory.textContent = category;
            modalDate.textContent = `Date: ${date}`;
            modal.show();
        });
    });
});
</script>