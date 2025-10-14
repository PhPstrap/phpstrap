<?php
// Ensure this file is being included properly
if (!defined('ABSPATH') && !isset($this)) {
    exit('Direct access not allowed');
}

// Get current settings
$settings = $this->getSettings();
$form_id = $attributes['id'] ?? 'sendy-form-' . uniqid();

// Check if we should show list selector
$show_list_selector = ($attributes['show_list_selector'] ?? 'false') === 'true';

// Get available lists if needed
if ($show_list_selector) {
    $available_lists = $this->getEnabledLists();
} else {
    // Get specific list info
    $list_key = 'list_' . ($attributes['list'] ?? '1');
    $list_id = $settings[$list_key . '_id'] ?? '';
    $list_name = $settings[$list_key . '_name'] ?? 'Newsletter';
}

// Check for session messages
$success_message = '';
$error_message = '';

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

$nonce = function_exists('wp_create_nonce') ? wp_create_nonce('sendy_subscribe_form') : bin2hex(random_bytes(16));
?>

<div class="sendy-form-container" id="<?php echo esc_attr($form_id); ?>">
    
    <?php if ($success_message): ?>
        <div class="sendy-message sendy-success" role="alert">
            <strong>Success!</strong>
            <p><?php echo esc_html($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="sendy-message sendy-error" role="alert">
            <strong>Error!</strong>
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" class="sendy-subscription-form" data-sendy-form>
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="sendy_subscribe" value="1">
        
        <?php if (!$show_list_selector): ?>
            <input type="hidden" name="list_id" value="<?php echo esc_attr($list_id); ?>">
        <?php endif; ?>

        <?php if (!empty($attributes['title'])): ?>
            <h3 class="sendy-form-title"><?php echo esc_html($attributes['title']); ?></h3>
        <?php endif; ?>
        
        <?php if (!empty($attributes['description'])): ?>
            <p class="sendy-form-description"><?php echo esc_html($attributes['description']); ?></p>
        <?php endif; ?>

        <?php if ($attributes['show_name'] !== 'false'): ?>
        <div class="sendy-form-field">
            <label for="<?php echo esc_attr($form_id); ?>_name" class="sendy-field-label">
                Name
                <?php if ($settings['require_name_field']): ?>
                    <span class="sendy-required">*</span>
                <?php endif; ?>
            </label>
            <input type="text" 
                   id="<?php echo esc_attr($form_id); ?>_name" 
                   name="name" 
                   class="sendy-input sendy-name-input" 
                   placeholder="Enter your name"
                   <?php if ($settings['require_name_field']): ?>required<?php endif; ?>>
        </div>
        <?php endif; ?>

        <div class="sendy-form-field">
            <label for="<?php echo esc_attr($form_id); ?>_email" class="sendy-field-label">
                Email Address <span class="sendy-required">*</span>
            </label>
            <input type="email" 
                   id="<?php echo esc_attr($form_id); ?>_email" 
                   name="email" 
                   class="sendy-input sendy-email-input" 
                   placeholder="Enter your email address"
                   required>
        </div>

        <?php if ($show_list_selector && !empty($available_lists)): ?>
        <div class="sendy-form-field">
            <label for="<?php echo esc_attr($form_id); ?>_list" class="sendy-field-label">
                Select Newsletter <span class="sendy-required">*</span>
            </label>
            <select id="<?php echo esc_attr($form_id); ?>_list" 
                    name="list_id" 
                    class="sendy-input sendy-list-select"
                    required>
                <option value="">Choose a newsletter...</option>
                <?php foreach ($available_lists as $list): ?>
                    <option value="<?php echo esc_attr($list['id']); ?>">
                        <?php echo esc_html($list['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <?php if ($settings['gdpr_compliance'] && $settings['consent_required']): ?>
        <div class="sendy-form-field sendy-consent-field">
            <label class="sendy-checkbox-label">
                <input type="checkbox" 
                       name="gdpr_consent" 
                       value="1" 
                       required
                       class="sendy-checkbox">
                <span class="sendy-checkbox-text">
                    <?php echo esc_html($settings['consent_text']); ?>
                    <span class="sendy-required">*</span>
                </span>
            </label>
        </div>
        <?php endif; ?>

        <?php if (!empty($settings['privacy_notice'])): ?>
        <div class="sendy-privacy-notice">
            <small><?php echo esc_html($settings['privacy_notice']); ?></small>
        </div>
        <?php endif; ?>

        <div class="sendy-form-field">
            <button type="submit" class="sendy-submit-button">
                <span class="sendy-button-text"><?php echo esc_html($attributes['button_text']); ?></span>
                <span class="sendy-button-loading" style="display: none;">
                    <span class="sendy-spinner"></span>
                    Subscribing...
                </span>
            </button>
        </div>
    </form>
</div>

<?php
// Helper functions
function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
}
?>