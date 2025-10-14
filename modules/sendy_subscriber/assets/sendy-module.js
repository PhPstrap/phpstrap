/**
 * Sendy Module Frontend JavaScript
 * 
 * Handles all frontend functionality for the Sendy Newsletter Integration module.
 * Features include:
 * - Form validation and submission
 * - AJAX handling
 * - User experience enhancements
 * - Accessibility improvements
 * - Analytics tracking
 * - Error handling and recovery
 */

(function(window, document) {
    'use strict';

    // Module namespace
    const SendyModule = {
        version: '1.0.0',
        debug: false,
        initialized: false,
        
        // Configuration (will be populated by localized script)
        config: {
            ajaxUrl: '',
            nonce: '',
            strings: {},
            analytics: {
                enabled: false,
                trackingId: '',
                eventName: 'newsletter_signup'
            }
        },
        
        // Storage for module data
        data: {
            forms: new Map(),
            widgets: new Map(),
            timers: new Map()
        },
        
        // Statistics
        stats: {
            formsInitialized: 0,
            successfulSubmissions: 0,
            failedSubmissions: 0,
            validationErrors: 0
        }
    };

    /**
     * Initialize the module
     */
    SendyModule.init = function() {
        if (this.initialized) {
            return;
        }

        this.log('Initializing Sendy Module v' + this.version);

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                this.setup();
            });
        } else {
            this.setup();
        }

        this.initialized = true;
    };

    /**
     * Setup module functionality
     */
    SendyModule.setup = function() {
        // Get configuration from localized script
        if (typeof sendyModule !== 'undefined') {
            this.config = Object.assign(this.config, sendyModule);
        }

        // Enable debug mode if specified
        if (this.config.debug) {
            this.debug = true;
        }

        // Initialize forms
        this.initializeForms();
        
        // Initialize widgets
        this.initializeWidgets();
        
        // Setup global event listeners
        this.setupGlobalEvents();
        
        // Initialize accessibility features
        this.initializeAccessibility();
        
        // Setup analytics if enabled
        if (this.config.analytics && this.config.analytics.enabled) {
            this.initializeAnalytics();
        }
        
        this.log('Sendy Module setup complete');
        this.log('Statistics:', this.stats);
    };

    /**
     * Initialize all subscription forms on the page
     */
    SendyModule.initializeForms = function() {
        const forms = document.querySelectorAll('[data-sendy-form]');
        
        forms.forEach(form => {
            this.initializeForm(form);
        });
    };

    /**
     * Initialize a single subscription form
     */
    SendyModule.initializeForm = function(form) {
        if (!form || form.dataset.sendyInitialized) {
            return;
        }

        const formId = form.id || 'sendy-form-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        form.id = formId;
        form.dataset.sendyInitialized = 'true';

        // Store form reference with configuration
        const formConfig = {
            element: form,
            container: form.closest('.sendy-form-container'),
            enableAjax: form.dataset.ajax !== 'false',
            validator: null,
            submitButton: form.querySelector('.sendy-submit-button'),
            emailInput: form.querySelector('.sendy-email-input'),
            nameInput: form.querySelector('.sendy-name-input'),
            originalData: new FormData(form)
        };

        this.data.forms.set(formId, formConfig);

        // Setup form features
        this.setupFormValidation(form, formConfig);
        this.setupFormSubmission(form, formConfig);
        this.setupFormEnhancements(form, formConfig);

        // Track initialization
        this.stats.formsInitialized++;
        
        this.log('Form initialized:', formId);
        
        // Trigger custom event
        this.triggerEvent('sendyFormInitialized', { formId, form, config: formConfig });
    };

    /**
     * Setup real-time form validation
     */
    SendyModule.setupFormValidation = function(form, config) {
        const { emailInput, nameInput } = config;
        
        // Email validation
        if (emailInput) {
            emailInput.addEventListener('blur', (e) => {
                this.validateEmail(e.target, form);
            });

            emailInput.addEventListener('input', (e) => {
                this.clearFieldError(e.target);
            });
        }

        // Name validation (if required)
        if (nameInput && nameInput.hasAttribute('required')) {
            nameInput.addEventListener('blur', (e) => {
                this.validateRequired(e.target, form);
            });

            nameInput.addEventListener('input', (e) => {
                this.clearFieldError(e.target);
            });
        }

        // Validate all required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (field === emailInput) return; // Already handled above
            
            field.addEventListener('blur', (e) => {
                this.validateRequired(e.target, form);
            });

            field.addEventListener('input', (e) => {
                this.clearFieldError(e.target);
            });
        });

        // Form submission validation
        form.addEventListener('submit', (e) => {
            if (!this.validateForm(form)) {
                e.preventDefault();
                this.focusFirstError(form);
                this.stats.validationErrors++;
                
                // Trigger validation error event
                this.triggerEvent('sendyValidationError', { form, formId: form.id });
                
                return false;
            }
        });
    };

    /**
     * Validate email field
     */
    SendyModule.validateEmail = function(field, form) {
        const email = field.value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        const isValid = email === '' || emailRegex.test(email);
        
        if (!isValid && email) {
            this.showFieldError(field, this.config.strings.invalid_email || 'Please enter a valid email address.');
            return false;
        } else {
            this.clearFieldError(field);
            return true;
        }
    };

    /**
     * Validate required field
     */
    SendyModule.validateRequired = function(field, form) {
        const value = field.value.trim();
        const isValid = value.length > 0;
        
        if (!isValid) {
            const fieldName = this.getFieldDisplayName(field);
            this.showFieldError(field, `${fieldName} is required.`);
            return false;
        } else {
            this.clearFieldError(field);
            return true;
        }
    };

    /**
     * Validate entire form
     */
    SendyModule.validateForm = function(form) {
        let isValid = true;
        
        // Validate email
        const emailInput = form.querySelector('.sendy-email-input');
        if (emailInput) {
            if (!emailInput.value.trim()) {
                this.showFieldError(emailInput, 'Email address is required.');
                isValid = false;
            } else if (!this.validateEmail(emailInput, form)) {
                isValid = false;
            }
        }
        
        // Validate all required fields
        const requiredFields = form.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!this.validateRequired(field, form)) {
                isValid = false;
            }
        });

        // Check honeypot
        const honeypot = form.querySelector('input[name="website"]');
        if (honeypot && honeypot.value !== '') {
            this.log('Honeypot triggered - potential spam');
            return false;
        }

        // Custom validation hook
        const customValidation = this.triggerEvent('sendyCustomValidation', { form, isValid });
        if (customValidation.defaultPrevented) {
            isValid = false;
        }

        return isValid;
    };

    /**
     * Show field error
     */
    SendyModule.showFieldError = function(field, message) {
        const fieldContainer = field.closest('.sendy-form-field');
        if (!fieldContainer) return;
        
        fieldContainer.classList.add('sendy-field-error');
        field.setAttribute('aria-invalid', 'true');
        
        let errorElement = fieldContainer.querySelector('.sendy-field-error-message');
        if (!errorElement) {
            errorElement = document.createElement('span');
            errorElement.className = 'sendy-field-error-message';
            errorElement.setAttribute('role', 'alert');
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
        
        // Add shake animation
        field.style.animation = 'none';
        field.offsetHeight; // Trigger reflow
        field.style.animation = 'sendy-shake 0.5s ease-in-out';
    };

    /**
     * Clear field error
     */
    SendyModule.clearFieldError = function(field) {
        const fieldContainer = field.closest('.sendy-form-field');
        if (!fieldContainer) return;
        
        fieldContainer.classList.remove('sendy-field-error');
        field.setAttribute('aria-invalid', 'false');
        
        const errorElement = fieldContainer.querySelector('.sendy-field-error-message');
        if (errorElement) {
            errorElement.remove();
        }
    };

    /**
     * Focus first error field
     */
    SendyModule.focusFirstError = function(form) {
        const firstError = form.querySelector('.sendy-field-error .sendy-input');
        if (firstError) {
            firstError.focus();
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    };

    /**
     * Setup form submission handling
     */
    SendyModule.setupFormSubmission = function(form, config) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            if (this.validateForm(form)) {
                if (config.enableAjax) {
                    this.submitFormAjax(form, config);
                } else {
                    this.submitFormTraditional(form, config);
                }
            }
        });
    };

    /**
     * Submit form via AJAX
     */
    SendyModule.submitFormAjax = function(form, config) {
        const formData = new FormData(form);
        
        // Add AJAX-specific parameters
        formData.append('action', 'sendy_subscribe');
        formData.append('nonce', this.config.nonce);
        formData.append('ajax', '1');
        
        // Set loading state
        this.setFormLoading(form, config, true);
        
        // Make API request
        fetch(this.config.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                this.handleSubmissionSuccess(form, config, data.data);
            } else {
                this.handleSubmissionError(form, config, data.data);
            }
        })
        .catch(error => {
            this.log('AJAX submission error:', error);
            this.handleSubmissionError(form, config, {
                message: this.config.strings.error || 'An error occurred. Please try again.'
            });
        })
        .finally(() => {
            this.setFormLoading(form, config, false);
        });
    };

    /**
     * Submit form traditionally (page reload)
     */
    SendyModule.submitFormTraditional = function(form, config) {
        this.setFormLoading(form, config, true);
        
        // Add traditional form parameters
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'sendy_subscribe';
        hiddenInput.value = '1';
        form.appendChild(hiddenInput);
        
        // Submit form
        form.submit();
    };

    /**
     * Handle successful form submission
     */
    SendyModule.handleSubmissionSuccess = function(form, config, data) {
        const message = data.message || this.config.strings.success || 'Successfully subscribed!';
        
        // Show success message
        this.showMessage(config.container, message, 'success');
        
        // Reset form
        form.reset();
        
        // Clear any existing errors
        const errorFields = form.querySelectorAll('.sendy-field-error');
        errorFields.forEach(field => {
            field.classList.remove('sendy-field-error');
        });
        
        const errorMessages = form.querySelectorAll('.sendy-field-error-message');
        errorMessages.forEach(msg => msg.remove());
        
        // Track success
        this.stats.successfulSubmissions++;
        
        // Track analytics
        this.trackAnalytics('subscription_success', {
            email: data.email,
            list_id: data.list_id,
            source: 'form'
        });
        
        // Trigger success event
        this.triggerEvent('sendySubmissionSuccess', {
            form,
            formId: form.id,
            data,
            message
        });
        
        // Auto-hide success message after 8 seconds
        setTimeout(() => {
            const successMessage = config.container.querySelector('.sendy-success');
            if (successMessage) {
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    if (successMessage.parentNode) {
                        successMessage.remove();
                    }
                }, 300);
            }
        }, 8000);
        
        this.log('Form submission successful:', data);
    };

    /**
     * Handle form submission error
     */
    SendyModule.handleSubmissionError = function(form, config, data) {
        const message = data.message || this.config.strings.error || 'Subscription failed. Please try again.';
        
        // Show error message
        this.showMessage(config.container, message, 'error');
        
        // Track failure
        this.stats.failedSubmissions++;
        
        // Track analytics
        this.trackAnalytics('subscription_error', {
            error: message,
            form_id: form.id
        });
        
        // Trigger error event
        this.triggerEvent('sendySubmissionError', {
            form,
            formId: form.id,
            data,
            message
        });
        
        this.log('Form submission failed:', data);
    };

    /**
     * Set form loading state
     */
    SendyModule.setFormLoading = function(form, config, isLoading) {
        const { submitButton } = config;
        
        if (!submitButton) return;
        
        const buttonText = submitButton.querySelector('.sendy-button-text');
        const loadingText = submitButton.querySelector('.sendy-button-loading');
        
        submitButton.disabled = isLoading;
        
        if (buttonText && loadingText) {
            buttonText.style.display = isLoading ? 'none' : 'inline';
            loadingText.style.display = isLoading ? 'inline-flex' : 'none';
        } else {
            submitButton.textContent = isLoading ? 
                (this.config.strings.subscribing || 'Subscribing...') : 
                submitButton.dataset.originalText || 'Subscribe';
        }
        
        // Add loading class to form
        form.classList.toggle('sendy-form-loading', isLoading);
        
        // Store original text if not already stored
        if (!submitButton.dataset.originalText) {
            submitButton.dataset.originalText = submitButton.textContent;
        }
    };

    /**
     * Show message in form container
     */
    SendyModule.showMessage = function(container, message, type) {
        this.removeExistingMessages(container);
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `sendy-message sendy-${type} sendy-animate-in`;
        messageDiv.setAttribute('role', 'alert');
        messageDiv.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
        
        const strong = document.createElement('strong');
        strong.textContent = type === 'success' ? 'Success!' : 'Error!';
        
        const p = document.createElement('p');
        p.textContent = message;
        
        messageDiv.appendChild(strong);
        messageDiv.appendChild(p);
        
        // Insert at the top of container
        const form = container.querySelector('.sendy-subscription-form');
        container.insertBefore(messageDiv, form);
        
        // Scroll to message
        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        
        // Announce to screen readers
        this.announceToScreenReader(message);
    };

    /**
     * Remove existing messages
     */
    SendyModule.removeExistingMessages = function(container) {
        const existingMessages = container.querySelectorAll('.sendy-message');
        existingMessages.forEach(msg => msg.remove());
    };

    /**
     * Setup form enhancements
     */
    SendyModule.setupFormEnhancements = function(form, config) {
        // Add placeholder animations
        this.enhancePlaceholders(form);
        
        // Add keyboard navigation
        this.enhanceKeyboardNavigation(form);
        
        // Add input formatting
        this.enhanceInputFormatting(form);
        
        // Add progress indication
        this.addFormProgress(form, config);
    };

    /**
     * Enhance placeholder animations
     */
    SendyModule.enhancePlaceholders = function(form) {
        const inputs = form.querySelectorAll('.sendy-input');
        
        inputs.forEach(input => {
            // Add focus/blur effects
            input.addEventListener('focus', () => {
                input.parentNode.classList.add('sendy-field-focused');
            });
            
            input.addEventListener('blur', () => {
                input.parentNode.classList.remove('sendy-field-focused');
                if (input.value.trim()) {
                    input.parentNode.classList.add('sendy-field-filled');
                } else {
                    input.parentNode.classList.remove('sendy-field-filled');
                }
            });
            
            // Check initial state
            if (input.value.trim()) {
                input.parentNode.classList.add('sendy-field-filled');
            }
        });
    };

    /**
     * Enhance keyboard navigation
     */
    SendyModule.enhanceKeyboardNavigation = function(form) {
        const inputs = form.querySelectorAll('.sendy-input');
        
        inputs.forEach((input, index) => {
            input.addEventListener('keydown', (e) => {
                // Enter key moves to next field or submits
                if (e.key === 'Enter') {
                    if (index < inputs.length - 1) {
                        e.preventDefault();
                        inputs[index + 1].focus();
                    }
                    // Let submit happen naturally for last field
                }
            });
        });
    };

    /**
     * Enhance input formatting
     */
    SendyModule.enhanceInputFormatting = function(form) {
        const emailInput = form.querySelector('.sendy-email-input');
        
        if (emailInput) {
            emailInput.addEventListener('input', (e) => {
                // Auto-lowercase email
                e.target.value = e.target.value.toLowerCase();
                
                // Remove spaces
                e.target.value = e.target.value.replace(/\s/g, '');
            });
        }
        
        const nameInput = form.querySelector('.sendy-name-input');
        if (nameInput) {
            nameInput.addEventListener('input', (e) => {
                // Capitalize first letter of each word
                e.target.value = e.target.value.replace(/\b\w/g, l => l.toUpperCase());
            });
        }
    };

    /**
     * Add form progress indication
     */
    SendyModule.addFormProgress = function(form, config) {
        const requiredFields = form.querySelectorAll('[required]');
        if (requiredFields.length <= 1) return; // Don't show for single field forms
        
        const formHeader = form.querySelector('.sendy-form-header');
        if (!formHeader) return;
        
        const progressContainer = document.createElement('div');
        progressContainer.className = 'sendy-form-progress';
        progressContainer.innerHTML = `
            <div class="sendy-progress-bar">
                <div class="sendy-progress-fill"></div>
            </div>
            <span class="sendy-progress-text">0% complete</span>
        `;
        
        formHeader.appendChild(progressContainer);
        
        const updateProgress = () => {
            const filledFields = Array.from(requiredFields).filter(field => {
                return field.value.trim() !== '';
            }).length;
            
            const percentage = Math.round((filledFields / requiredFields.length) * 100);
            const progressFill = progressContainer.querySelector('.sendy-progress-fill');
            const progressText = progressContainer.querySelector('.sendy-progress-text');
            
            progressFill.style.width = percentage + '%';
            progressText.textContent = percentage + '% complete';
        };
        
        requiredFields.forEach(field => {
            field.addEventListener('input', updateProgress);
            field.addEventListener('change', updateProgress);
        });
        
        updateProgress(); // Initial update
    };

    /**
     * Initialize widgets
     */
    SendyModule.initializeWidgets = function() {
        const widgets = document.querySelectorAll('.sendy-widget');
        
        widgets.forEach(widget => {
            this.initializeWidget(widget);
        });
    };

    /**
     * Initialize a single widget
     */
    SendyModule.initializeWidget = function(widget) {
        if (!widget || widget.dataset.sendyInitialized) {
            return;
        }

        const widgetId = widget.id || 'sendy-widget-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
        widget.id = widgetId;
        widget.dataset.sendyInitialized = 'true';

        // Find form within widget
        const form = widget.querySelector('.sendy-subscription-form');
        if (form) {
            this.initializeForm(form);
        }

        // Store widget reference
        this.data.widgets.set(widgetId, {
            element: widget,
            form: form
        });

        this.log('Widget initialized:', widgetId);
    };

    /**
     * Setup global event listeners
     */
    SendyModule.setupGlobalEvents = function() {
        // Handle escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.handleEscapeKey();
            }
        });
        
        // Handle window resize
        window.addEventListener('resize', this.debounce(() => {
            this.handleResize();
        }, 250));
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', () => {
            this.handleVisibilityChange();
        });
    };

    /**
     * Initialize accessibility features
     */
    SendyModule.initializeAccessibility = function() {
        // Add ARIA live region for announcements
        this.addLiveRegion();
        
        // Enhance keyboard navigation
        this.enhanceGlobalKeyboardNavigation();
        
        // Add skip links
        this.addSkipLinks();
    };

    /**
     * Add ARIA live region for screen reader announcements
     */
    SendyModule.addLiveRegion = function() {
        if (document.getElementById('sendy-live-region')) {
            return; // Already exists
        }
        
        const liveRegion = document.createElement('div');
        liveRegion.id = 'sendy-live-region';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'sendy-sr-only';
        
        document.body.appendChild(liveRegion);
    };

    /**
     * Announce message to screen readers
     */
    SendyModule.announceToScreenReader = function(message) {
        const liveRegion = document.getElementById('sendy-live-region');
        if (liveRegion) {
            liveRegion.textContent = message;
            
            // Clear after announcement
            setTimeout(() => {
                liveRegion.textContent = '';
            }, 1000);
        }
    };

    /**
     * Enhance global keyboard navigation
     */
    SendyModule.enhanceGlobalKeyboardNavigation = function() {
        // Add visible focus indicators
        const style = document.createElement('style');
        style.textContent = `
            .sendy-input:focus-visible,
            .sendy-submit-button:focus-visible {
                outline: 2px solid var(--sendy-primary);
                outline-offset: 2px;
            }
        `;
        document.head.appendChild(style);
    };

    /**
     * Add skip links for accessibility
     */
    SendyModule.addSkipLinks = function() {
        const forms = document.querySelectorAll('[data-sendy-form]');
        
        forms.forEach(form => {
            const skipLink = document.createElement('a');
            skipLink.href = '#' + form.id;
            skipLink.textContent = 'Skip to newsletter form';
            skipLink.className = 'sendy-sr-only';
            skipLink.style.cssText = `
                position: absolute;
                top: -40px;
                left: 6px;
                background: #000;
                color: #fff;
                padding: 8px;
                text-decoration: none;
                border-radius: 4px;
                z-index: 1000;
                font-weight: 600;
            `;
            
            skipLink.addEventListener('focus', () => {
                skipLink.style.top = '6px';
                skipLink.classList.remove('sendy-sr-only');
            });
            
            skipLink.addEventListener('blur', () => {
                skipLink.style.top = '-40px';
                skipLink.classList.add('sendy-sr-only');
            });
            
            form.parentNode.insertBefore(skipLink, form);
        });
    };

    /**
     * Initialize analytics tracking
     */
    SendyModule.initializeAnalytics = function() {
        this.log('Analytics initialized');
    };

    /**
     * Track analytics event
     */
    SendyModule.trackAnalytics = function(eventName, data) {
        if (!this.config.analytics || !this.config.analytics.enabled) {
            return;
        }

        // Google Analytics 4
        if (typeof gtag !== 'undefined') {
            gtag('event', eventName, {
                event_category: 'Newsletter',
                event_label: data.list_id || 'unknown',
                custom_data: data
            });
        }

        // Google Analytics Universal
        if (typeof ga !== 'undefined') {
            ga('send', 'event', 'Newsletter', eventName, data.list_id || 'unknown');
        }

        // Facebook Pixel
        if (typeof fbq !== 'undefined' && eventName === 'subscription_success') {
            fbq('track', 'Lead', data);
        }

        // Custom analytics hook
        this.triggerEvent('sendyAnalyticsTrack', { eventName, data });

        this.log('Analytics tracked:', eventName, data);
    };

    // Utility functions
    SendyModule.getFieldDisplayName = function(field) {
        const label = field.closest('.sendy-form-field').querySelector('.sendy-field-label');
        if (label) {
            return label.textContent.replace(/\s*\*\s*$/, ''); // Remove asterisk
        }
        return field.name || 'Field';
    };

    SendyModule.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };

    SendyModule.triggerEvent = function(eventName, detail) {
        const event = new CustomEvent(eventName, { 
            detail,
            cancelable: true,
            bubbles: true 
        });
        document.dispatchEvent(event);
        return event;
    };

    SendyModule.log = function(...args) {
        if (this.debug) {
            console.log('[Sendy Module]', ...args);
        }
    };

    SendyModule.handleEscapeKey = function() {
        // Close any modal forms
        const modalForms = document.querySelectorAll('.sendy-form-popup');
        modalForms.forEach(modal => {
            if (modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });
    };

    SendyModule.handleResize = function() {
        // Handle responsive changes
        this.log('Window resized');
        
        // Trigger resize event for any listeners
        this.triggerEvent('sendyResize', {
            width: window.innerWidth,
            height: window.innerHeight
        });
    };

    SendyModule.handleVisibilityChange = function() {
        if (document.hidden) {
            // Page is hidden - pause any running processes
            this.log('Page hidden - pausing processes');
        } else {
            // Page is visible again - resume processes
            this.log('Page visible - resuming processes');
        }
        
        this.triggerEvent('sendyVisibilityChange', { hidden: document.hidden });
    };

    /**
     * Public API methods
     */
    SendyModule.getStats = function() {
        return Object.assign({}, this.stats);
    };

    SendyModule.getForm = function(formId) {
        return this.data.forms.get(formId);
    };

    SendyModule.getAllForms = function() {
        return Array.from(this.data.forms.values());
    };

    SendyModule.submitForm = function(formId) {
        const formConfig = this.data.forms.get(formId);
        if (formConfig) {
            const form = formConfig.element;
            if (this.validateForm(form)) {
                if (formConfig.enableAjax) {
                    this.submitFormAjax(form, formConfig);
                } else {
                    this.submitFormTraditional(form, formConfig);
                }
                return true;
            }
        }
        return false;
    };

    SendyModule.resetForm = function(formId) {
        const formConfig = this.data.forms.get(formId);
        if (formConfig) {
            const form = formConfig.element;
            form.reset();
            
            // Clear errors
            const errorFields = form.querySelectorAll('.sendy-field-error');
            errorFields.forEach(field => {
                field.classList.remove('sendy-field-error');