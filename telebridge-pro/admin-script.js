/**
 * Telebridge Ultimate - Admin JavaScript
 * Complete interactive functionality based on Development Roadmap v7.x
 * Features: Advanced Error Handling, Live Search, Toast Notifications
 * Version: 7.2.0
 * Author: Ready Studio
 */

jQuery(document).ready(function($) {
    'use strict';

    const TelebridgeApp = {
        
        // Configuration & State
        config: {
            ajaxUrl: (typeof telebridge_vars !== 'undefined') ? telebridge_vars.ajax_url : ajaxurl,
            nonce: (typeof telebridge_vars !== 'undefined') ? telebridge_vars.nonce : '',
            
            // Translatable Strings (Hardcoded Persian for this project)
            text: {
                connecting: 'درحال برقراری ارتباط با تلگرام...',
                connected: 'اتصال برقرار شد ✅',
                retry: 'تلاش مجدد',
                copySuccess: 'آدرس کپی شد!',
                copyError: 'مرورگر شما اجازه کپی خودکار را نمی‌دهد.',
                searchPlaceholder: '🔍 جستجو در سرویس‌ها...',
                serverError: 'خطای سرور',
                parseError: 'خطای پردازش پاسخ (JSON)'
            }
        },

        // Cache DOM Elements
        dom: {},

        /**
         * Initialize the Application
         */
        init: function() {
            this.cacheDOM();
            this.bindEvents();
            this.initAISitesManager();
            this.log('🚀 Telebridge Admin Interface Loaded (v7.2.0)', 'info');
        },

        /**
         * Cache DOM Elements for Performance
         */
        cacheDOM: function() {
            this.dom = {
                body: $('body'),
                // Webhook Section
                webhookBtn: $('#rti_webhook_btn'),
                webhookMsg: $('#rti_msg'),
                webhookCode: $('.rti-webhook-box code'),
                
                // AI Meta Box Section
                aiWrap: $('.ai-wrap'),
                aiHeader: $('.ai-header-bar'),
                aiGrid: $('.ai-grid'),
                aiCheckboxes: $('.ai-wrap input[type="checkbox"]'),
                
                // System Prompts (New in v7.1)
                sysPrompts: $('.rti-form textarea')
            };
        },

        /**
         * Bind Event Listeners
         */
        bindEvents: function() {
            // Webhook Setup
            this.dom.webhookBtn.on('click', this.handleWebhookSetup.bind(this));
            
            // Clipboard Copy
            this.dom.webhookCode.on('click', this.handleCopyClipboard.bind(this));

            // AI Sites Manager Interactions
            this.dom.aiWrap.on('change', 'input[type="checkbox"]', this.handleCheckboxChange.bind(this));
        },

        /**
         * ------------------------------------------------------------------------
         * Webhook Logic (AJAX) - Enhanced Error Handling
         * ------------------------------------------------------------------------
         */
        handleWebhookSetup: function(e) {
            e.preventDefault();
            const self = this;
            const btn = this.dom.webhookBtn;
            const msg = this.dom.webhookMsg;

            // 1. UI Loading State
            btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> ' + this.config.text.connecting);
            msg.slideUp(100).removeClass('rti-success rti-error');

            // 2. Perform AJAX
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'telebridge_set_webhook',
                    nonce: this.config.nonce
                },
                success: function(res) {
                    if (res.success) {
                        // Success Handler
                        self.showNotice(msg, res.data, 'success');
                        btn.text(self.config.text.connected);
                        self.showToast(res.data, 'success');
                        self.log('✅ Webhook Set Successfully', 'success');
                    } else {
                        // Logical Error from PHP
                        const errorMsg = res.data || 'خطای ناشناخته از سمت سرور';
                        self.showNotice(msg, errorMsg, 'error');
                        btn.prop('disabled', false).text(self.config.text.retry);
                        self.log('⚠️ Webhook Logic Error: ' + errorMsg, 'warn');
                    }
                },
                error: function(xhr, status, error) {
                    // Critical Error Handler
                    self.handleAjaxError(xhr, status, error, msg, btn);
                }
            });
        },

        /**
         * Centralized AJAX Error Handler
         */
        handleAjaxError: function(xhr, status, error, msgEl, btnEl) {
            let userMsg = this.config.text.serverError;
            let debugInfo = '';

            // Console Grouping for easier debugging
            console.group('%c❌ Telebridge AJAX Error Report', 'color:red; font-size:12px; font-weight:bold;');
            console.error('Status Code:', xhr.status);
            console.error('Status Text:', status);
            console.error('Error Thrown:', error);
            console.warn('Raw Response (Check for PHP Notices/Fatal Errors):');
            console.log(xhr.responseText); 
            console.groupEnd();

            // Determine User Message
            if (status === 'parsererror') {
                userMsg = this.config.text.parseError;
                debugInfo = '<br><small style="display:block; margin-top:5px; color:#d63384;">پاسخ سرور معتبر نیست. لطفاً <b>کنسول مرورگر (F12)</b> را بررسی کنید (تداخل افزونه یا خطای PHP).</small>';
            } else if (status === 'timeout') {
                userMsg = 'زمان اتصال به پایان رسید (Timeout).';
            } else if (xhr.status === 403) {
                userMsg = 'دسترسی غیرمجاز (403). لطفاً صفحه را رفرش کنید.';
            } else if (xhr.status === 404) {
                userMsg = 'مسیر AJAX یافت نشد (404).';
            } else if (xhr.status === 500) {
                userMsg = 'خطای داخلی سرور (500).';
            } else if (xhr.responseJSON && xhr.responseJSON.data) {
                userMsg = xhr.responseJSON.data;
            }

            this.showNotice(msgEl, userMsg + debugInfo, 'error');
            if (btnEl) btnEl.prop('disabled', false).text(this.config.text.retry);
        },

        /**
         * ------------------------------------------------------------------------
         * Clipboard Logic
         * ------------------------------------------------------------------------
         */
        handleCopyClipboard: function(e) {
            const el = $(e.currentTarget);
            const text = el.text();
            const self = this;

            // Method 1: Modern API
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    self.successCopy(el);
                }).catch(function() {
                    self.fallbackCopy(text, el);
                });
            } else {
                // Method 2: Fallback
                self.fallbackCopy(text, el);
            }
        },

        fallbackCopy: function(text, el) {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed'; // Avoid scrolling to bottom
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                this.successCopy(el);
            } catch (err) {
                this.showToast(this.config.text.copyError, 'error');
                console.error('Fallback Copy Failed', err);
            }
        },

        successCopy: function(el) {
            this.showToast(this.config.text.copySuccess, 'success');
            
            // Visual Feedback
            const originalBg = el.css('background-color');
            el.css({
                'background-color': '#d4edda',
                'border-color': '#c3e6cb',
                'color': '#155724'
            });
            
            setTimeout(() => {
                el.css({
                    'background-color': '',
                    'border-color': '',
                    'color': ''
                });
            }, 600);
        },

        /**
         * ------------------------------------------------------------------------
         * AI Sites Manager (Meta Box) Logic
         * ------------------------------------------------------------------------
         */
        initAISitesManager: function() {
            if (!this.dom.aiWrap.length) return;

            // 1. Inject Search Input (if not exists)
            if (!this.dom.aiHeader.find('.ai-search-container').length) {
                const searchHTML = `
                    <div class="ai-search-container" style="flex-grow:1; max-width:250px; position:relative; margin-right:auto;">
                        <span class="dashicons dashicons-search" style="position:absolute; right:8px; top:50%; transform:translateY(-50%); color:#999; pointer-events:none;"></span>
                        <input type="text" class="ai-search-input" placeholder="${this.config.text.searchPlaceholder}" 
                               style="width:100%; padding:8px 30px 8px 10px; border:1px solid #ddd; border-radius:6px; font-size:13px; transition:all 0.2s;">
                    </div>
                `;
                this.dom.aiHeader.append(searchHTML);
            }

            // 2. Bind Live Search
            this.dom.aiHeader.on('keyup', '.ai-search-input', this.handleLiveSearch.bind(this));

            // 3. Highlight Initially Checked Items
            this.dom.aiCheckboxes.each(function() {
                if ($(this).is(':checked')) {
                    $(this).closest('label').addClass('checked');
                }
            });
        },

        handleLiveSearch: function(e) {
            const term = $(e.target).val().toLowerCase();
            const items = this.dom.aiWrap.find('.ai-grid label');

            // Filter Items
            items.each(function() {
                const text = $(this).text().toLowerCase();
                const isVisible = text.indexOf(term) > -1;
                $(this).toggle(isVisible);
            });

            // Handle Empty Groups
            this.dom.aiWrap.find('.ai-group').each(function() {
                const visibleItems = $(this).find('.ai-grid label:visible').length;
                if (visibleItems === 0) {
                    $(this).slideUp(200);
                } else {
                    $(this).slideDown(200);
                }
            });
        },

        handleCheckboxChange: function(e) {
            const checkbox = $(e.target);
            const label = checkbox.closest('label');

            if (checkbox.is(':checked')) {
                label.addClass('checked');
                // Micro-interaction: Bounce effect
                label.css({ transform: 'scale(0.95)' });
                setTimeout(() => label.css({ transform: 'scale(1)' }), 150);
            } else {
                label.removeClass('checked');
            }
        },

        /**
         * ------------------------------------------------------------------------
         * UI Utilities
         * ------------------------------------------------------------------------
         */
        
        /**
         * Show inline notice inside a container
         */
        showNotice: function(el, message, type) {
            const icon = type === 'success' ? '✅' : '⚠️';
            const bg = type === 'success' ? '#e6f4ea' : '#fce8e6';
            const color = type === 'success' ? '#188038' : '#d93025';
            const border = type === 'success' ? '#b7e1cd' : '#f5c6cb';

            el.css({
                'background': bg,
                'color': color,
                'border': `1px solid ${border}`,
                'padding': '12px',
                'border-radius': '6px',
                'margin-top': '15px',
                'display': 'none' // reset for slideDown
            })
            .html(`<strong>${icon}</strong> ${message}`)
            .slideDown();
        },

        /**
         * Show Floating Toast Notification
         */
        showToast: function(message, type = 'success') {
            // Remove existing toasts to prevent stacking
            $('.rti-toast').remove();

            const bgColor = type === 'success' ? '#188038' : '#d93025';
            const toast = $(`<div class="rti-toast">${message}</div>`);
            
            toast.css({
                'position': 'fixed',
                'bottom': '30px',
                'left': '30px',
                'background': bgColor,
                'color': '#fff',
                'padding': '12px 24px',
                'border-radius': '8px',
                'box-shadow': '0 4px 15px rgba(0,0,0,0.2)',
                'z-index': '999999',
                'font-family': 'inherit',
                'font-size': '14px',
                'font-weight': '500',
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px',
                'opacity': '0',
                'transform': 'translateY(20px)',
                'transition': 'all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275)'
            });

            this.dom.body.append(toast);
            
            // Animation In
            requestAnimationFrame(() => {
                toast.css({ 'opacity': '1', 'transform': 'translateY(0)' });
            });

            // Auto Dismiss
            setTimeout(() => {
                toast.css({ 'opacity': '0', 'transform': 'translateY(20px)' });
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        },

        /**
         * Console Logger with Styling
         */
        log: function(msg, type = 'log') {
            const styles = {
                'info': 'color: #1a73e8; font-weight: bold;',
                'success': 'color: #188038; font-weight: bold;',
                'warn': 'color: #f9ab00; font-weight: bold;',
                'error': 'color: #d93025; font-weight: bold;'
            };
            console.log(`%c[Telebridge] ${msg}`, styles[type] || styles.info);
        }
    };

    // Initialize App
    TelebridgeApp.init();

});