/**
 * Telebridge Pro - Admin JavaScript
 * Complete interactive functionality for plugin admin panel
 * Author: Ready Studio
 * Version: 6.0.0
 */

(function($) {
    'use strict';

    /**
     * Telebridge Admin Module
     * Encapsulated module pattern to avoid global namespace pollution
     */
    const TelebridgeAdmin = {
        
        // Configuration
        config: {
            ajaxUrl: telebridge_vars?.ajax_url || '',
            nonce: telebridge_vars?.nonce || '',
            debounceDelay: 500,
            animationDuration: 300,
            toastDuration: 3000
        },

        // Cached DOM elements
        $elements: {},

        /**
         * Initialize module on document ready
         */
        init: function() {
            this.cacheDOMElements();
            this.bindEvents();
            this.setupValidation();
            this.initializeStateManagement();
            this.logInitialization();
        },

        /**
         * Cache frequently used DOM elements
         */
        cacheDOMElements: function() {
            this.$elements = {
                // Buttons
                webhookBtn: $('#rti_webhook_btn'),
                submitBtn: $('button[type="submit"]'),

                // Response/Message areas
                responseDiv: $('#rti_msg'),
                webhookResponse: $('#rti_webhook_response'),

                // Webhook section
                webhookCode: $('.rti-webhook-box code'),
                webhookBox: $('.rti-webhook-box'),

                // Form elements
                form: $('.rti-form'),
                inputs: $('.rti-form input[required], .rti-form select[required]'),
                allInputs: $('.rti-form input, .rti-form select, .rti-form textarea'),

                // Cards
                cards: $('.rti-card'),

                // Body
                body: $('body')
            };
        },

        /**
         * Bind all event listeners
         */
        bindEvents: function() {
            const self = this;

            // Webhook setup button
            this.$elements.webhookBtn.on('click', (e) => this.handleWebhookSetup(e));

            // Webhook code copy to clipboard
            this.$elements.webhookCode.on('click', (e) => this.handleWebhookCodeCopy(e));

            // Form submission
            this.$elements.form.on('submit', (e) => this.handleFormSubmit(e));

            // Input focus/blur events
            this.$elements.allInputs.on('focus', (e) => this.handleInputFocus(e));
            this.$elements.allInputs.on('blur', (e) => this.handleInputBlur(e));

            // Real-time input validation
            this.$elements.inputs.on('input change', (e) => this.validateInput(e));

            // Keyboard shortcuts
            $(document).on('keydown', (e) => this.handleKeyboardShortcuts(e));

            // Auto-dismiss messages
            this.$elements.responseDiv.on('shown.bs.alert', () => this.autoDismissMessage(this.$elements.responseDiv));
            this.$elements.webhookResponse.on('shown.bs.alert', () => this.autoDismissMessage(this.$elements.webhookResponse));

            // Close messages on Escape
            $(document).on('keyup', (e) => {
                if (e.key === 'Escape') {
                    this.$elements.responseDiv.fadeOut();
                    this.$elements.webhookResponse.fadeOut();
                }
            });
        },

        /**
         * Handle webhook setup button click
         */
        handleWebhookSetup: function(e) {
            e.preventDefault();

            const btn = $(e.currentTarget);
            const originalText = btn.text();
            const originalHTML = btn.html();

            // Validate form first
            if (!this.validateForm()) {
                this.showMessage(
                    this.$elements.responseDiv,
                    '<span class="dashicons dashicons-warning"></span> لطفاً تمام فیلدهای ضروری را پر کنید',
                    'error'
                );
                return;
            }

            // Clear previous messages
            this.$elements.responseDiv.fadeOut(200, function() {
                $(this).removeClass('rti-success rti-error rti-warning rti-info').html('');
            });

            // Disable button and show loading state
            btn.prop('disabled', true);
            btn.html('<span class="dashicons dashicons-update rti-loading"></span> درحال برقراری ارتباط...');

            // Send AJAX request
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'telebridge_set_webhook',
                    nonce: this.config.nonce
                },
                timeout: 15000,
                success: (res) => this.handleWebhookSuccess(res, btn, originalHTML),
                error: (xhr, status, error) => this.handleWebhookError(xhr, status, error, btn, originalHTML),
                complete: () => {
                    // Always restore button state
                    setTimeout(() => {
                        btn.prop('disabled', false).html(originalHTML);
                    }, 500);
                }
            });
        },

        /**
         * Handle successful webhook setup
         */
        handleWebhookSuccess: function(res, btn, originalHTML) {
            if (res.success) {
                this.showMessage(
                    this.$elements.responseDiv,
                    '<span class="dashicons dashicons-yes-alt"></span> ' + res.data,
                    'success'
                );
                this.triggerSuccessAnimation(btn);
                this.log('✅ Webhook setup successful', 'success');
            } else {
                this.showMessage(
                    this.$elements.responseDiv,
                    '<span class="dashicons dashicons-warning"></span> ' + res.data,
                    'error'
                );
                this.log('⚠️ Webhook error: ' + res.data, 'warning');
            }
        },

        /**
         * Handle webhook setup error
         */
        handleWebhookError: function(xhr, status, error, btn, originalHTML) {
            let errorMsg = 'خطای نامشخص رخ داد';

            if (status === 'timeout') {
                errorMsg = 'زمان اتصال پایان یافت. لطفاً بعداً دوباره تلاش کنید';
            } else if (xhr.status === 0) {
                errorMsg = 'خطای شبکه: اینترنت را بررسی کنید';
            } else if (xhr.status === 403) {
                errorMsg = 'خطای دسترسی: توکن نامعتبر است';
            } else if (xhr.status === 500) {
                errorMsg = 'خطای سرور: سرور پاسخگو نیست';
            } else if (xhr.status === 404) {
                errorMsg = 'خطای 404: آدرس Webhook یافت نشد';
            } else if (xhr.responseJSON?.data) {
                errorMsg = xhr.responseJSON.data;
            }

            this.showMessage(
                this.$elements.responseDiv,
                '<span class="dashicons dashicons-dismiss"></span> ' + errorMsg,
                'error'
            );

            this.log('❌ AJAX Error: ' + error + ' (Status: ' + xhr.status + ')', 'error');
        },

        /**
         * Handle webhook code copy to clipboard
         */
        handleWebhookCodeCopy: function(e) {
            const codeElement = $(e.currentTarget);
            const text = codeElement.text();

            // Use modern Clipboard API if available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showCopyFeedback(codeElement);
                }).catch(() => {
                    this.fallbackCopy(codeElement, text);
                });
            } else {
                this.fallbackCopy(codeElement, text);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopy: function(element, text) {
            const range = document.createRange();
            const sel = window.getSelection();

            try {
                range.selectNodeContents(element[0]);
                sel.removeAllRanges();
                sel.addRange(range);
                document.execCommand('copy');

                this.showCopyFeedback(element);
            } catch (err) {
                this.log('❌ Copy failed: ' + err, 'error');
                this.showToast('خطا در کپی‌کردن', 'error');
            } finally {
                sel.removeAllRanges();
            }
        },

        /**
         * Show visual feedback for copy action
         */
        showCopyFeedback: function(element) {
            const originalBg = element.css('background-color');

            element.css({
                'background-color': '#fff9c4',
                'transition': 'background-color 0.3s ease'
            });

            setTimeout(() => {
                element.css('background-color', originalBg);
            }, 500);

            this.showToast('آدرس Webhook کپی شد ✓');
            this.log('✅ Webhook URL copied to clipboard', 'success');
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            if (!this.validateForm()) {
                e.preventDefault();
                this.showMessage(
                    this.$elements.form,
                    '<span class="dashicons dashicons-warning"></span> لطفاً تمام فیلدهای الزامی را پر کنید',
                    'error'
                );
                this.scrollToFirstError();
                return;
            }

            this.log('✅ Form submitted successfully', 'success');
        },

        /**
         * Validate entire form
         */
        validateForm: function() {
            let isValid = true;

            this.$elements.inputs.each((index, input) => {
                const $input = $(input);
                const value = $input.val().trim();

                if (!value) {
                    this.markInputAsInvalid($input);
                    isValid = false;
                } else {
                    this.markInputAsValid($input);
                }
            });

            return isValid;
        },

        /**
         * Validate individual input
         */
        validateInput: function(e) {
            const $input = $(e.target);
            const value = $input.val().trim();
            const isRequired = $input.prop('required');

            if (isRequired && !value) {
                this.markInputAsInvalid($input);
                return false;
            } else if (isRequired && value) {
                this.markInputAsValid($input);
            }

            // Additional validation based on input type
            if ($input.attr('type') === 'number') {
                if (value && isNaN(value)) {
                    this.markInputAsInvalid($input);
                    return false;
                }
            }

            if ($input.attr('type') === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (value && !emailRegex.test(value)) {
                    this.markInputAsInvalid($input);
                    return false;
                }
            }

            if ($input.attr('type') === 'url') {
                try {
                    if (value) new URL(value);
                } catch (_) {
                    this.markInputAsInvalid($input);
                    return false;
                }
            }

            return true;
        },

        /**
         * Mark input as invalid
         */
        markInputAsInvalid: function($input) {
            $input.css({
                'border-color': '#d93025',
                'background-color': 'rgba(217, 48, 37, 0.05)'
            });

            $input.closest('.rti-col').addClass('has-error');
        },

        /**
         * Mark input as valid
         */
        markInputAsValid: function($input) {
            $input.css({
                'border-color': '#dadce0',
                'background-color': '#fff'
            });

            $input.closest('.rti-col').removeClass('has-error');
        },

        /**
         * Handle input focus
         */
        handleInputFocus: function(e) {
            const $input = $(e.target);
            $input.closest('.rti-col').addClass('focused');

            // Add visual feedback
            $input.css({
                'box-shadow': '0 0 0 3px rgba(26, 115, 232, 0.2)'
            });
        },

        /**
         * Handle input blur
         */
        handleInputBlur: function(e) {
            const $input = $(e.target);
            $input.closest('.rti-col').removeClass('focused');

            // Validate on blur
            if ($input.prop('required')) {
                this.validateInput(e);
            }
        },

        /**
         * Handle keyboard shortcuts
         */
        handleKeyboardShortcuts: function(e) {
            // Ctrl+Enter or Cmd+Enter to submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                if (this.$elements.form.length) {
                    this.$elements.form.submit();
                }
            }

            // Escape to close messages
            if (e.key === 'Escape') {
                this.$elements.responseDiv.fadeOut();
                this.$elements.webhookResponse.fadeOut();
            }
        },

        /**
         * Show message with animation
         */
        showMessage: function($container, message, type = 'info') {
            const messageHTML = `<div class="rti-${type}">${message}</div>`;

            $container
                .html(messageHTML)
                .removeClass('rti-success rti-error rti-warning rti-info')
                .addClass(`rti-${type}`)
                .stop()
                .fadeOut(0)
                .fadeIn(this.config.animationDuration);
        },

        /**
         * Show toast notification
         */
        showToast: function(message, type = 'info', duration = null) {
            const toastDuration = duration || this.config.toastDuration;

            const $toast = $(`
                <div style="
                    position: fixed;
                    bottom: 20px;
                    left: 20px;
                    background: ${type === 'error' ? '#d93025' : '#188038'};
                    color: white;
                    padding: 12px 20px;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                    z-index: 9999;
                    animation: slideDown 0.3s ease;
                    max-width: 300px;
                    word-wrap: break-word;
                    font-size: 13px;
                ">
                    ${message}
                </div>
            `);

            $('body').append($toast);

            setTimeout(() => {
                $toast.fadeOut(200, function() {
                    $(this).remove();
                });
            }, toastDuration);
        },

        /**
         * Auto-dismiss message after delay
         */
        autoDismissMessage: function($element, delay = 5000) {
            setTimeout(() => {
                $element.fadeOut(300);
            }, delay);
        },

        /**
         * Scroll to first invalid input
         */
        scrollToFirstError: function() {
            const $firstError = this.$elements.form.find('input[required][value=""], select[required]').first();

            if ($firstError.length) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 400);

                $firstError.focus();
            }
        },

        /**
         * Trigger success animation
         */
        triggerSuccessAnimation: function($element) {
            $element.css({
                'background': 'linear-gradient(135deg, #188038 0%, #0d5a2e 100%)',
                'transform': 'scale(0.98)'
            });

            setTimeout(() => {
                $element.css('transform', 'scale(1)');
            }, 100);
        },

        /**
         * Setup form state management
         */
        initializeStateManagement: function() {
            // Save form state on input change
            this.$elements.allInputs.on('change', () => {
                this.saveFormState();
            });

            // Restore form state on page load
            this.restoreFormState();

            // Warn user before leaving if form has changes
            $(window).on('beforeunload', (e) => {
                if (this.hasFormChanges()) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });
        },

        /**
         * Save form state to sessionStorage
         */
        saveFormState: function() {
            const formData = this.$elements.form.serialize();
            try {
                sessionStorage.setItem('telebridge_form_state', formData);
                this.log('💾 Form state saved', 'info');
            } catch (e) {
                this.log('⚠️ SessionStorage not available', 'warning');
            }
        },

        /**
         * Restore form state from sessionStorage
         */
        restoreFormState: function() {
            try {
                const savedState = sessionStorage.getItem('telebridge_form_state');
                if (savedState && confirm('آیا می‌خواهید آخرین تغییرات را بازیابی کنید؟')) {
                    const params = new URLSearchParams(savedState);
                    params.forEach((value, key) => {
                        const $input = $(`[name="${key}"]`);
                        if ($input.length) {
                            $input.val(value);
                        }
                    });
                    this.log('✅ Form state restored', 'success');
                }
            } catch (e) {
                this.log('⚠️ Failed to restore form state', 'warning');
            }
        },

        /**
         * Check if form has unsaved changes
         */
        hasFormChanges: function() {
            try {
                const currentState = this.$elements.form.serialize();
                const savedState = sessionStorage.getItem('telebridge_form_state');
                return currentState !== savedState && savedState;
            } catch (e) {
                return false;
            }
        },

        /**
         * Logging utility
         */
        log: function(message, type = 'log') {
            const timestamp = new Date().toLocaleTimeString('fa-IR');
            const prefix = `[Telebridge ${timestamp}]`;

            switch (type) {
                case 'success':
                    console.log(`%c${prefix} ${message}`, 'color: #188038; font-weight: bold; font-size: 12px;');
                    break;
                case 'error':
                    console.error(`%c${prefix} ${message}`, 'color: #d93025; font-weight: bold; font-size: 12px;');
                    break;
                case 'warning':
                    console.warn(`%c${prefix} ${message}`, 'color: #f9ab00; font-weight: bold; font-size: 12px;');
                    break;
                case 'info':
                    console.log(`%c${prefix} ${message}`, 'color: #1a73e8; font-weight: bold; font-size: 12px;');
                    break;
                default:
                    console.log(`${prefix} ${message}`);
            }
        },

        /**
         * Log initialization
         */
        logInitialization: function() {
            this.log('✅ Telebridge Admin initialized', 'success');
            this.log('Version: 6.0.0', 'info');
            this.log('AJAX URL: ' + this.config.ajaxUrl, 'info');
        }
    };

    /**
     * Initialize on DOM ready
     */
    $(document).ready(function() {
        TelebridgeAdmin.init();
    });

    /**
     * Debounce utility function
     * Prevents function from being called too frequently
     */
    window.debounce = function(func, wait) {
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

    /**
     * Throttle utility function
     * Limits function calls to once per specified time period
     */
    window.throttle = function(func, limit) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };

    /**
     * Export module for external use
     */
    window.TelebridgeAdmin = TelebridgeAdmin;

})(jQuery);

/**
 * Additional utility for form validation
 */
if (!window.FormValidator) {
    window.FormValidator = {
        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Validate URL format
         */
        isValidURL: function(url) {
            try {
                new URL(url);
                return true;
            } catch (_) {
                return false;
            }
        },

        /**
         * Validate number
         */
        isValidNumber: function(value) {
            return !isNaN(value) && value !== '';
        },

        /**
         * Validate required field
         */
        isRequired: function(value) {
            return value && value.trim() !== '';
        }
    };
}