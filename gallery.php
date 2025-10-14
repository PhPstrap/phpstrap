<?php
$pageTitle = 'Photo Gallery - PhPstrap Membership';
$metaDescription = 'Explore our gallery showcasing PhPstrap capabilities with Bootstrap 5.';
$metaKeywords = 'PhPstrap, gallery, Bootstrap 5, photos, images';
include __DIR__ . '/includes/header.php';
?>

<main class="container py-5">

    <!-- Page Header -->
    <div class="text-center py-4 mb-5">
        <h1 class="display-4 fw-bold">PhPstrap Gallery</h1>
        <p class="lead">Showcasing the visual capabilities of Bootstrap 5 with PhPstrap</p>
    </div>

    <!-- Filter Controls -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-center flex-wrap">
                <button class="btn btn-outline-primary m-1 active" data-filter="all">All Images</button>
                <button class="btn btn-outline-primary m-1" data-filter="nature">Nature</button>
                <button class="btn btn-outline-primary m-1" data-filter="technology">Technology</button>
                <button class="btn btn-outline-primary m-1" data-filter="abstract">Abstract</button>
            </div>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="row" id="gallery">
        <!-- Gallery Item 1 -->
        <div class="col-lg-4 col-md-6 mb-4" data-category="nature">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1501854140801-50d01698950b?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Nature landscape">
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
        <div class="col-lg-4 col-md-6 mb-4" data-category="technology">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1518770660439-4636190af475?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Technology">
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
        <div class="col-lg-4 col-md-6 mb-4" data-category="abstract">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1541701494587-cb58502866ab?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Abstract art">
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
        <div class="col-lg-4 col-md-6 mb-4" data-category="nature">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1470240731273-7821a6eeb6bd?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Forest">
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
        <div class="col-lg-4 col-md-6 mb-4" data-category="technology">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1517430816045-df4b7de11d1d?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Coding">
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
        <div class="col-lg-4 col-md-6 mb-4" data-category="abstract">
            <div class="card h-100 shadow-sm gallery-item">
                <img src="https://images.unsplash.com/photo-1543857778-c4a1a569eafe?ixlib=rb-1.2.1&auto=format&fit=crop&w=600&h=400&q=80" 
                     class="card-img-top" alt="Abstract design">
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

    <!-- Pagination -->
    <nav aria-label="Gallery navigation" class="mt-5">
        <ul class="pagination justify-content-center">
            <li class="page-item disabled">
                <a class="page-link" href="#" tabindex="-1" aria-disabled="true">Previous</a>
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
                <a class="page-link" href="#">Next</a>
            </li>
        </ul>
    </nav>

</main>

<!-- Modal for Image View -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalLabel">Image Title</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img src="" class="img-fluid" alt="Modal image">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Download</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<style>
.gallery-item {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    cursor: pointer;
}
.gallery-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.15) !important;
}
</style>

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
    
    // Add modal functionality for images
    const galleryImages = document.querySelectorAll('.gallery-item img');
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    const modalTitle = document.getElementById('imageModalLabel');
    const modalImage = document.querySelector('#imageModal img');
    
    galleryImages.forEach(image => {
        image.addEventListener('click', function() {
            const card = this.closest('.card');
            const title = card.querySelector('.card-title').textContent;
            
            modalTitle.textContent = title;
            modalImage.src = this.src;
            modal.show();
        });
    });
});
</script>