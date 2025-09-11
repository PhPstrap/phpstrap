<?php
// checkout.php - xyz.am Premium Checkout
session_start();
include '../site/includes/header-scripts.php';
require_once '../site/includes/whmcs-api.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login?redirect=checkout');
    exit;
}

// Get current user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Check if already premium
if ($user['membership_status'] == 'premium') {
    header('Location: /dashboard?already_premium=1');
    exit;
}

$error_message = '';
$success_message = '';

// Process form submission
if ($_POST && isset($_POST['process_checkout'])) {
    try {
        // Validate form data
        $required_fields = ['firstName', 'lastName', 'email', 'address', 'city', 'state', 'zip', 'country'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Prepare customer data for WHMCS
        $customer_data = [
            'firstname' => trim($_POST['firstName']),
            'lastname' => trim($_POST['lastName']),
            'email' => trim($_POST['email']),
            'address1' => trim($_POST['address']),
            'address2' => trim($_POST['address2']) ?? '',
            'city' => trim($_POST['city']),
            'state' => trim($_POST['state']),
            'postcode' => trim($_POST['zip']),
            'country' => trim($_POST['country']),
            'phonenumber' => trim($_POST['phone']) ?? '',
        ];

        // Create or get WHMCS client
        $whmcs_client = createOrGetWHMCSClient($customer_data, $user['email']);
        
        if (!$whmcs_client['success']) {
            throw new Exception("Error creating customer account: " . $whmcs_client['message']);
        }

        // Create WHMCS order for xyz.am Premium
        $order_data = [
            'clientid' => $whmcs_client['clientid'],
            'pid' => [1], // xyz.am Premium product ID in WHMCS
            'domain' => $user['name'] . '.xyz.am',
            'billingcycle' => ['annually'],
            'customfields' => [
                1 => $user['name'], // xyz_username custom field
                2 => $user['email'] // user_email custom field
            ],
            'paymentmethod' => $_POST['paymentMethod'] ?? 'stripe'
        ];

        $order_result = createWHMCSOrder($order_data);
        
        if (!$order_result['success']) {
            throw new Exception("Error processing order: " . $order_result['message']);
        }

        // Update user record with WHMCS IDs
        $stmt = $conn->prepare("UPDATE users SET whmcs_client_id = ?, whmcs_order_id = ? WHERE id = ?");
        $stmt->bind_param("iii", $whmcs_client['clientid'], $order_result['orderid'], $user_id);
        $stmt->execute();

        // Redirect to WHMCS payment page
        $payment_url = "https://thexyz.com/whmcs/viewinvoice.php?id=" . $order_result['invoiceid'];
        header("Location: " . $payment_url);
        exit;

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

?>

<title>Upgrade to Premium - <?php echo SITENAME; ?></title>

<!-- Meta Tags -->
<meta name="description" content="Upgrade to xyz.am Premium - Get your custom subdomain, unlimited email forwards, and advanced features for just $59/year.">
<meta name="robots" content="noindex, nofollow">

<!-- Theme Color -->
<meta name="theme-color" content="<?php echo THEME_COLOR; ?>">

<style>
.checkout-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem 0;
}
.feature-highlight {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
}
.price-highlight {
    background: linear-gradient(135deg, #28a745, #20c997);
    color: white;
    border-radius: 8px;
    padding: 1.5rem;
}
</style>
</head>
<body>

<?php include 'site/includes/header.php'; ?>

<!-- Hero Section -->
<section class="checkout-hero">
    <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">Upgrade to xyz.am Premium</h1>
        <p class="lead">Unlock your full potential with professional features</p>
    </div>
</section>

<div class="container my-5">
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="row g-5">
        <!-- Order Summary -->
        <div class="col-md-5 col-lg-4 order-md-last">
            <div class="price-highlight text-center mb-4">
                <h3 class="mb-0">xyz.am Premium</h3>
                <p class="mb-0">Just $4.92/month</p>
                <h2 class="display-4 fw-bold">$59<small>/year</small></h2>
            </div>

            <h4 class="d-flex justify-content-between align-items-center mb-3">
                <span class="text-primary">What's Included</span>
                <span class="badge bg-success rounded-pill">Premium</span>
            </h4>
            
            <div class="list-group mb-3">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-globe text-primary me-2"></i>Custom Subdomain</h6>
                        <small class="text-muted"><?php echo $user['name']; ?>.xyz.am</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
                
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-envelope text-primary me-2"></i>Unlimited Email Forwards</h6>
                        <small class="text-muted">Professional email addresses</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
                
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-chart-line text-primary me-2"></i>Advanced Analytics</h6>
                        <small class="text-muted">Detailed visitor insights</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
                
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-infinity text-primary me-2"></i>Unlimited Everything</h6>
                        <small class="text-muted">Links, QR codes, vCards</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
                
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-cogs text-primary me-2"></i>Full DNS Control</h6>
                        <small class="text-muted">Host websites, manage records</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
                
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-1"><i class="fas fa-headset text-primary me-2"></i>Priority Support</h6>
                        <small class="text-muted">Skip the line</small>
                    </div>
                    <span class="text-success"><i class="fas fa-check"></i></span>
                </div>
            </div>

            <div class="card bg-light">
                <div class="card-body text-center">
                    <h5 class="card-title">Order Summary</h5>
                    <div class="d-flex justify-content-between">
                        <span>xyz.am Premium (Annual)</span>
                        <strong>$59.00</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span><strong>Total</strong></span>
                        <strong>$59.00</strong>
                    </div>
                    <small class="text-muted">Billed annually • Cancel anytime</small>
                </div>
            </div>
        </div>

        <!-- Checkout Form -->
        <div class="col-md-7 col-lg-8">
            <h4 class="mb-3">Complete Your Upgrade</h4>
            
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="process_checkout" value="1">
                
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="firstName" class="form-label">First name *</label>
                        <input type="text" class="form-control" id="firstName" name="firstName" 
                               value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                        <div class="invalid-feedback">
                            Valid first name is required.
                        </div>
                    </div>

                    <div class="col-sm-6">
                        <label for="lastName" class="form-label">Last name *</label>
                        <input type="text" class="form-control" id="lastName" name="lastName" 
                               value="" required>
                        <div class="invalid-feedback">
                            Valid last name is required.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="email" class="form-label">Email address *</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        <div class="invalid-feedback">
                            Please enter a valid email address.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="phone" class="form-label">Phone number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone_number'] ?? ''); ?>">
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">Address *</label>
                        <input type="text" class="form-control" id="address" name="address" 
                               placeholder="1234 Main St" required>
                        <div class="invalid-feedback">
                            Please enter your address.
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="address2" class="form-label">Address 2 <span class="text-muted">(Optional)</span></label>
                        <input type="text" class="form-control" id="address2" name="address2" 
                               placeholder="Apartment, studio, or floor">
                    </div>

                    <div class="col-md-5">
                        <label for="country" class="form-label">Country *</label>
                        <select class="form-select" id="country" name="country" required>
                            <option value="">Choose...</option>
                            <option value="US">United States</option>
                            <option value="CA">Canada</option>
                            <option value="GB">United Kingdom</option>
                            <option value="AU">Australia</option>
                            <option value="DE">Germany</option>
                            <option value="FR">France</option>
                            <option value="NL">Netherlands</option>
                            <option value="SE">Sweden</option>
                            <option value="NO">Norway</option>
                            <option value="DK">Denmark</option>
                            <option value="FI">Finland</option>
                        </select>
                        <div class="invalid-feedback">
                            Please select a valid country.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="state" class="form-label">State/Province *</label>
                        <input type="text" class="form-control" id="state" name="state" required>
                        <div class="invalid-feedback">
                            Please provide a valid state.
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="zip" class="form-label">Zip/Postal Code *</label>
                        <input type="text" class="form-control" id="zip" name="zip" required>
                        <div class="invalid-feedback">
                            Zip code required.
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <h4 class="mb-3">Payment Method</h4>
                <div class="my-3">
                    <div class="form-check">
                        <input id="credit" name="paymentMethod" type="radio" class="form-check-input" 
                               value="stripe" checked required>
                        <label class="form-check-label" for="credit">
                            <i class="fab fa-cc-stripe me-2"></i>Credit/Debit Card
                        </label>
                    </div>
                    <div class="form-check">
                        <input id="paypal" name="paymentMethod" type="radio" class="form-check-input" 
                               value="paypal" required>
                        <label class="form-check-label" for="paypal">
                            <i class="fab fa-paypal me-2"></i>PayPal
                        </label>
                    </div>
                </div>

                <hr class="my-4">

                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="/legal/terms" target="_blank">Terms of Service</a> 
                        and <a href="/legal/privacy-policy" target="_blank">Privacy Policy</a>
                    </label>
                    <div class="invalid-feedback">
                        You must agree to the terms and conditions.
                    </div>
                </div>

                <div class="feature-highlight">
                    <h6><i class="fas fa-shield-alt text-success me-2"></i>Secure & Risk-Free</h6>
                    <small class="text-muted">
                        • SSL encrypted checkout<br>
                        • 30-day money-back guarantee<br>
                        • Cancel anytime, no questions asked
                    </small>
                </div>

                <button class="w-100 btn btn-primary btn-lg mt-3" type="submit">
                    <i class="fas fa-lock me-2"></i>Complete Upgrade - $59/year
                </button>
                
                <p class="text-center mt-3 text-muted small">
                    You'll be redirected to secure payment processing
                </p>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript for form validation -->
<script>
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Auto-populate state/province based on country
document.getElementById('country').addEventListener('change', function() {
    const stateField = document.getElementById('state');
    const country = this.value;
    
    if (country === 'US') {
        stateField.placeholder = 'e.g., California';
    } else if (country === 'CA') {
        stateField.placeholder = 'e.g., Ontario';
    } else {
        stateField.placeholder = 'State/Province';
    }
});
</script>

<?php include '../site/includes/footer.php'; ?>