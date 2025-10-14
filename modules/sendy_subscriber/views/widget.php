<?php
/**
 * Sendy Newsletter Widget Template
 * 
 * This template renders a newsletter subscription widget that can be used in:
 * - Sidebar widgets
 * - Footer widgets
 * - Shortcodes
 * - Page builder elements
 * 
 * Available variables:
 * $attributes - Widget/shortcode attributes
 * $settings - Module settings
 * $this - SendyModule instance
 */

// Prevent direct access
if (!defined('ABSPATH') && !isset($this)) {
    exit('Direct access not allowed');
}

// Extract attributes with defaults
$widget_id = $attributes['id'] ?? 'sendy-widget-' . uniqid();
$title = $attributes['title'] ?? 'Newsletter Signup';
$description = $attributes['description'] ?? 'Stay updated with our latest news and offers.';
$list_id = $attributes['list_id'] ?? '';
$sendy_installation = $attributes['sendy_installation'] ?? ($this->getSetting('default_installation', 'default'));
$style = $attributes['style'] ?? ($this->getSetting('display_settings.widget_style', 'default'));
$button_text = $attributes['button_text'] ?? ($this->getSetting('form_settings.button_text', 'Subscribe'));
$show_name = filter_var($attributes['show_name'] ?? $this->getSetting('form_settings.show_name_field', true), FILTER_VALIDATE_BOOLEAN);
$show_powered_by = $this->getSetting('display_settings.show_powered_by', false);
$custom_class = $attributes['class'] ?? 'sendy-newsletter-widget';

// Get available lists for this installation
$available_lists = $this->getSendyLists($sendy_installation);

// If no list_id specified and we have available lists, use the first one
if (empty($list_id) && !empty($available_lists)) {
    $first_list = reset($available_lists);
    $list_id = $first_list['id'];
}

// Check for session messages
$success_message = '';
$error_message = '';
$form_data = [];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['sendy_success'])) {
    $success_message = $_SESSION['sendy_success'];
    unset($_SESSION['sendy_success']);
}

if (isset($_SESSION['sendy_error'])) {
    $error_message = $_SESSION['sendy_error'];
    unset($_SESSION['sendy_error']);
}

if (isset($_SESSION['sendy_form_data'])) {
    $form_data = $_SESSION['sendy_form_data'];
    unset($_SESSION['sendy_form_data']);
}

// Generate nonce for security
$nonce = function_exists('wp_create_nonce') ? wp_create_nonce('sendy_subscribe_form') : bin2hex(random_bytes(16));

// Widget CSS classes based on style
$widget_classes = ['sendy-newsletter-widget', 'sendy-widget', "sendy-widget-{$style}"];
if (!empty($custom_class)) {
    $widget_classes[] = $custom_class;
}
if ($this->getSetting('display_settings.responsive_design', true)) {
    $widget_classes[] = 'sendy-responsive';
}

$widget_class = implode(' ', $widget_classes);
?>

<div id="<?php echo esc_attr($widget_id); ?>" class="<?php echo esc_attr($widget_class); ?>">
    
    <?php if (!empty($title)): ?>
    <div class="sendy-widget-header">
        <h3 class="sendy-widget-title"><?php echo esc_html($title); ?></h3>
        <?php if (!empty($description)): ?>
        <p class="sendy-widget-description"><?php echo esc_html($description); ?></p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="sendy-widget-content">
        
        <?php if ($success_message): ?>
        <div class="sendy-message sendy-success" role="alert">
            <span class="sendy-message-icon">✓</span>
            <span class="sendy-message-text"><?php echo esc_html($success_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="sendy-message sendy-error" role="alert">
            <span class="sendy-message-icon">⚠</span>
            <span class="sendy-message-text"><?php echo esc_html($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if (empty($list_id)): ?>
        <div class="sendy-message sendy-warning" role="alert">
            <span class="sendy-message-icon">⚠</span>
            <span class="sendy-message-text">No mailing list configured. Please set up your lists in the admin panel.</span>
        </div>
        <?php else: ?>

        <form class="sendy-subscription-form sendy-widget-form" method="post" action="" novalidate>
            
            <!-- Security fields -->
            <?php if (function_exists('wp_nonce_field')): ?>
                <?php wp_nonce_field('sendy_subscribe_form'); ?>
            <?php else: ?>
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
            <?php endif; ?>
            
            <!-- Form data -->
            <input type="hidden" name="sendy_subscribe" value="1">
            <input type="hidden" name="list_id" value="<?php echo esc_attr($list_id); ?>">
            <input type="hidden" name="sendy_installation" value="<?php echo esc_attr($sendy_installation); ?>">
            <input type="hidden" name="source" value="widget">

            <div class="sendy-form-fields">
                
                <?php if ($show_name): ?>
                <div class="sendy-field sendy-field-name">
                    <label for="<?php echo esc_attr($widget_id); ?>_name" class="sendy-label">
                        <?php if ($this->getSetting('form_settings.require_name_field', false)): ?>
                            Name <span class="sendy-required">*</span>
                        <?php else: ?>
                            Name
                        <?php endif; ?>
                    </label>
                    <input type="text" 
                           id="<?php echo esc_attr($widget_id); ?>_name"
                           name="name" 
                           class="sendy-input sendy-input-name"
                           placeholder="Enter your name"
                           value="<?php echo esc_attr($form_data['name'] ?? ''); ?>"
                           autocomplete="given-name"
                           <?php echo $this->getSetting('form_settings.require_name_field', false) ? 'required' : ''; ?>>
                </div>
                <?php endif; ?>

                <div class="sendy-field sendy-field-email">
                    <label for="<?php echo esc_attr($widget_id); ?>_email" class="sendy-label">
                        Email Address <span class="sendy-required">*</span>
                    </label>
                    <input type="email" 
                           id="<?php echo esc_attr($widget_id); ?>_email"
                           name="email" 
                           class="sendy-input sendy-input-email"
                           placeholder="Enter your email"
                           value="<?php echo esc_attr($form_data['email'] ?? ''); ?>"
                           autocomplete="email"
                           required>
                </div>

                <?php if ($this->getSetting('privacy_settings.consent_required', false)): ?>
                <div class="sendy-field sendy-field-consent">
                    <label class="sendy-consent-label">
                        <input type="checkbox" 
                               name="consent" 
                               value="1" 
                               class="sendy-consent-checkbox"
                               required>
                        <span class="sendy-consent-text">
                            <?php echo esc_html($this->getSetting('privacy_settings.consent_text', 'I agree to receive marketing emails and understand I can unsubscribe at any time.')); ?>
                        </span>
                    </label>
                </div>
                <?php endif; ?>

                <div class="sendy-field sendy-field-submit">
                    <button type="submit" class="sendy-submit-btn sendy-widget-submit">
                        <span class="sendy-btn-text"><?php echo esc_html($button_text); ?></span>
                        <span class="sendy-btn-loading" style="display: none;">
                            <span class="sendy-spinner"></span>
                            Subscribing...
                        </span>
                    </button>
                </div>

            </div>

            <?php if ($this->getSetting('privacy_settings.gdpr_compliance', true) && !empty($this->getSetting('privacy_settings.privacy_notice'))): ?>
            <div class="sendy-privacy-notice">
                <small><?php echo esc_html($this->getSetting('privacy_settings.privacy_notice')); ?></small>
            </div>
            <?php endif; ?>

        </form>

        <?php endif; ?>

        <?php if ($show_powered_by): ?>
        <div class="sendy-powered-by">
            <small>
                Powered by <a href="https://sendy.co" target="_blank" rel="noopener">Sendy</a>
            </small>
        </div>
        <?php endif; ?>

    </div>

</div>

<style>
/* Sendy Widget Styles */
.sendy-newsletter-widget {
    max-width: 100%;
    margin-bottom: 20px;
}

.sendy-widget-header {
    margin-bottom: 15px;
}

.sendy-widget-title {
    margin: 0 0 8px 0;
    font-size: 1.2em;
    font-weight: 600;
    color: #333;
}

.sendy-widget-description {
    margin: 0;
    font-size: 0.9em;
    color: #666;
    line-height: 1.4;
}

.sendy-widget-content {
    position: relative;
}

/* Messages */
.sendy-message {
    padding: 10px 12px;
    margin-bottom: 15px;
    border-radius: 4px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-size: 0.9em;
    line-height: 1.4;
}

.sendy-message-icon {
    flex-shrink: 0;
    font-weight: bold;
}

.sendy-message-text {
    flex: 1;
}

.sendy-success {
    background-color: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.sendy-error {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

.sendy-warning {
    background-color: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

/* Form Styles */
.sendy-subscription-form {
    width: 100%;
}

.sendy-form-fields {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.sendy-field {
    position: relative;
}

.sendy-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    font-size: 0.9em;
    color: #333;
}

.sendy-required {
    color: #d63638;
}

.sendy-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    line-height: 1.4;
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box;
}

.sendy-input:focus {
    outline: none;
    border-color: #0073aa;
    box-shadow: 0 0 0 1px rgba(0, 115, 170, 0.3);
}

.sendy-input:invalid {
    border-color: #d63638;
}

.sendy-input::placeholder {
    color: #999;
}

/* Consent Field */
.sendy-field-consent {
    margin-top: 5px;
}

.sendy-consent-label {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    cursor: pointer;
    font-weight: normal;
    margin-bottom: 0;
}

.sendy-consent-checkbox {
    width: auto;
    margin: 0;
    flex-shrink: 0;
    margin-top: 2px;
}

.sendy-consent-text {
    font-size: 0.85em;
    line-height: 1.4;
    color: #555;
}

/* Submit Button */
.sendy-submit-btn {
    width: 100%;
    padding: 12px 20px;
    background-color: #0073aa;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease;
    position: relative;
    overflow: hidden;
}

.sendy-submit-btn:hover:not(:disabled) {
    background-color: #005a87;
    transform: translateY(-1px);
}

.sendy-submit-btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

.sendy-btn-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.sendy-spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    border-top-color: white;
    animation: sendy-spin 1s ease-in-out infinite;
}

@keyframes sendy-spin {
    to { transform: rotate(360deg); }
}

/* Privacy Notice */
.sendy-privacy-notice {
    margin-top: 10px;
    font-size: 0.8em;
    color: #666;
    line-height: 1.3;
}

/* Powered By */
.sendy-powered-by {
    margin-top: 10px;
    text-align: center;
}

.sendy-powered-by small {
    color: #999;
    font-size: 0.75em;
}

.sendy-powered-by a {
    color: #0073aa;
    text-decoration: none;
}

.sendy-powered-by a:hover {
    text-decoration: underline;
}

/* Widget Style Variations */

/* Compact Style */
.sendy-widget-compact .sendy-widget-title {
    font-size: 1em;
    margin-bottom: 5px;
}

.sendy-widget-compact .sendy-widget-description {
    font-size: 0.8em;
    margin-bottom: 10px;
}

.sendy-widget-compact .sendy-form-fields {
    gap: 8px;
}

.sendy-widget-compact .sendy-input {
    padding: 8px 10px;
    font-size: 13px;
}

.sendy-widget-compact .sendy-submit-btn {
    padding: 8px 16px;
    font-size: 13px;
}

/* Modern Style */
.sendy-widget-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
}

.sendy-widget-modern .sendy-widget-title,
.sendy-widget-modern .sendy-widget-description,
.sendy-widget-modern .sendy-label {
    color: white;
}

.sendy-widget-modern .sendy-input {
    border: none;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(10px);
}

.sendy-widget-modern .sendy-submit-btn {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.sendy-widget-modern .sendy-submit-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Card Style */
.sendy-widget-card {
    background: white;
    border: 1px solid #e1e1e1;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Banner Style */
.sendy-widget-banner {
    background: #f8f9fa;
    border-left: 4px solid #0073aa;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.sendy-widget-banner .sendy-widget-header {
    flex: 1;
    margin-bottom: 0;
}

.sendy-widget-banner .sendy-form-fields {
    flex-direction: row;
    gap: 10px;
    align-items: flex-end;
}

.sendy-widget-banner .sendy-field-email {
    flex: 1;
}

.sendy-widget-banner .sendy-field-submit {
    flex-shrink: 0;
}

.sendy-widget-banner .sendy-submit-btn {
    width: auto;
    white-space: nowrap;
}

/* Responsive Design */
.sendy-responsive {
    width: 100%;
}

@media (max-width: 480px) {
    .sendy-widget-banner {
        flex-direction: column;
        align-items: stretch;
        gap: 15px;
    }
    
    .sendy-widget-banner .sendy-form-fields {
        flex-direction: column;
        gap: 12px;
    }
    
    .sendy-widget-banner .sendy-submit-btn {
        width: 100%;
    }
    
    .sendy-widget-modern {
        padding: 15px;
    }
    
    .sendy-widget-card {
        padding: 15px;
    }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
    .sendy-newsletter-widget {
        color: #e1e1e1;
    }
    
    .sendy-widget-title {
        color: #fff;
    }
    
    .sendy-widget-description {
        color: #b1b1b1;
    }
    
    .sendy-label {
        color: #e1e1e1;
    }
    
    .sendy-input {
        background: #2a2a2a;
        border-color: #444;
        color: white;
    }
    
    .sendy-input:focus {
        border-color: #4a9eff;
        box-shadow: 0 0 0 1px rgba(74, 158, 255, 0.3);
    }
    
    .sendy-widget-card {
        background: #1a1a1a;
        border-color: #333;
    }
    
    .sendy-widget-banner {
        background: #2a2a2a;
        border-left-color: #4a9eff;
    }
}

/* High Contrast Mode Support */
@media (prefers-contrast: high) {
    .sendy-input {
        border-width: 2px;
    }
    
    .sendy-submit-btn {
        border: 2px solid transparent;
    }
    
    .sendy-submit-btn:focus {
        outline: 2px solid;
        outline-offset: 2px;
    }
}

/* Reduced Motion Support */
@media (prefers-reduced-motion: reduce) {
    .sendy-submit-btn {
        transition: none;
    }
    
    .sendy-spinner {
        animation: none;
    }
    
    .sendy-submit-btn:hover {
        transform: none;
    }
}

/* Print Styles */
@media print {
    .sendy-newsletter-widget {
        break-inside: avoid;
    }
    
    .sendy-submit-btn {
        background: white !important;
        color: black !important;
        border: 1px solid black !important;
    }
}
</style>

<script>
(function() {
    'use strict';
    
    // Initialize widget functionality when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSendyWidget);
    } else {
        initSendyWidget();
    }
    
    function initSendyWidget() {
        var widget = document.getElementById('<?php echo esc_js($widget_id); ?>');
        if (!widget) return;
        
        var form = widget.querySelector('.sendy-subscription-form');
        if (!form) return;
        
        var submitBtn = form.querySelector('.sendy-submit-btn');
        var btnText = submitBtn.querySelector('.sendy-btn-text');
        var btnLoading = submitBtn.querySelector('.sendy-btn-loading');
        
        // Handle form submission with AJAX if enabled
        <?php if ($this->getSetting('form_settings.enable_ajax', true)): ?>
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (submitBtn.disabled) return;
            
            // Validate form
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Show loading state
            submitBtn.disabled = true;
            btnText.style.display = 'none';
            btnLoading.style.display = 'flex';
            
            // Clear previous messages
            var messages = widget.querySelectorAll('.sendy-message');
            messages.forEach(function(msg) {
                msg.remove();
            });
            
            // Prepare form data
            var formData = new FormData(form);
            formData.append('action', 'sendy_subscribe');
            
            <?php if (function_exists('wp_create_nonce')): ?>
            formData.append('nonce', '<?php echo wp_create_nonce("sendy_module_nonce"); ?>');
            <?php endif; ?>
            
            // Send AJAX request
            var xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url("admin-ajax.php"); ?>');
            xhr.onload = function() {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                
                if (xhr.status === 200) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        showMessage(response.success ? 'success' : 'error', response.data.message);
                        
                        if (response.success) {
                            form.reset();
                            
                            // Send Google Analytics event if enabled
                            <?php if ($this->getSetting('analytics_settings.google_analytics_integration', false)): ?>
                            if (typeof gtag !== 'undefined') {
                                gtag('event', '<?php echo esc_js($this->getSetting('analytics_settings.google_analytics_event', 'newsletter_signup')); ?>', {
                                    'event_category': 'Newsletter',
                                    'event_label': '<?php echo esc_js($title); ?>',
                                    'transport_type': 'beacon'
                                });
                            } else if (typeof ga !== 'undefined') {
                                ga('send', 'event', 'Newsletter', 'Signup', '<?php echo esc_js($title); ?>');
                            }
                            <?php endif; ?>
                        }
                    } catch (e) {
                        showMessage('error', 'An unexpected error occurred. Please try again.');
                    }
                } else {
                    showMessage('error', 'Network error. Please check your connection and try again.');
                }
            };
            
            xhr.onerror = function() {
                submitBtn.disabled = false;
                btnText.style.display = 'inline';
                btnLoading.style.display = 'none';
                showMessage('error', 'Network error. Please try again.');
            };
            
            xhr.send(formData);
        });
        <?php endif; ?>
        
        function showMessage(type, message) {
            var messageEl = document.createElement('div');
            messageEl.className = 'sendy-message sendy-' + type;
            messageEl.setAttribute('role', 'alert');
            messageEl.innerHTML = 
                '<span class="sendy-message-icon">' + (type === 'success' ? '✓' : '⚠') + '</span>' +
                '<span class="sendy-message-text">' + escapeHtml(message) + '</span>';
            
            var content = widget.querySelector('.sendy-widget-content');
            content.insertBefore(messageEl, content.firstChild);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    if (messageEl.parentNode) {
                        messageEl.remove();
                    }
                }, 5000);
            }
            
            // Scroll message into view
            messageEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        
        function escapeHtml(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Enhanced accessibility
        var inputs = form.querySelectorAll('input[required]');
        inputs.forEach(function(input) {
            input.addEventListener('invalid', function() {
                input.setAttribute('aria-invalid', 'true');
            });
            
            input.addEventListener('input', function() {
                if (input.validity.valid) {
                    input.removeAttribute('aria-invalid');
                }
            });
        });
        
        // Real-time email validation
        var emailInput = form.querySelector('input[type="email"]');
        if (emailInput) {
            emailInput.addEventListener('blur', function() {
                if (this.value && !this.validity.valid) {
                    this.setCustomValidity('Please enter a valid email address.');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    }
})();
</script>

<?php
// Helper functions
function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
}

function esc_js($text) {
    return addslashes($text);
}

function admin_url($path) {
    if (function_exists('admin_url')) {
        return admin_url($path);
    }
    return '/wp-admin/' . $path;
}
?>