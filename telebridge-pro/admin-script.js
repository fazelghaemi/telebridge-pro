/**
 * Ready Telegram Importer - Admin Script
 * Handles AJAX requests for Webhook setup and UI interactions.
 * * @author Ready Studio
 * @version 1.0.0
 */

jQuery(document).ready(function($) {
    'use strict';

    // کش کردن المان‌ها برای دسترسی سریع‌تر
    const $webhookBtn = $('#rti_set_webhook_btn');
    const $responseDiv = $('#rti_webhook_response');
    const $webhookCode = $('.rti-webhook-card code');

    /**
     * 1. هندل کردن کلیک روی دکمه "ست کردن وب‌هوک"
     */
    $webhookBtn.on('click', function(e) {
        e.preventDefault();

        // پاک کردن پیام‌های قبلی
        $responseDiv.hide().removeClass('rti-success rti-error').html('');

        // تغییر وضعیت دکمه به Loading
        const originalText = $webhookBtn.text();
        $webhookBtn.prop('disabled', true).text('⏳ در حال برقراری ارتباط با تلگرام...');

        // ارسال درخواست AJAX
        $.ajax({
            url: rti_vars.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'rti_set_webhook', // نام اکشن تعریف شده در PHP
                nonce: rti_vars.nonce      // توکن امنیتی
            },
            success: function(res) {
                if (res.success) {
                    // موفقیت: نمایش پیام سبز
                    $responseDiv
                        .html('<span class="dashicons dashicons-yes-alt"></span> ' + res.data)
                        .addClass('rti-success')
                        .fadeIn();
                } else {
                    // خطای منطقی (مثلاً توکن اشتباه): نمایش پیام قرمز
                    $responseDiv
                        .html('<span class="dashicons dashicons-warning"></span> ' + res.data)
                        .addClass('rti-error')
                        .fadeIn();
                }
            },
            error: function(xhr, status, error) {
                // خطای شبکه یا سرور (500/404)
                $responseDiv
                    .html('<span class="dashicons dashicons-dismiss"></span> خطای سرور: ارتباط برقرار نشد. لطفاً اینترنت سرور را بررسی کنید.')
                    .addClass('rti-error')
                    .fadeIn();
                console.error('RTI Ajax Error:', error);
            },
            complete: function() {
                // بازگرداندن دکمه به حالت اولیه
                setTimeout(function() {
                    $webhookBtn.prop('disabled', false).text(originalText);
                }, 500); // تاخیر نیم ثانیه‌ای برای حس بهتر
            }
        });
    });

    /**
     * 2. UX: انتخاب خودکار متن وب‌هوک با کلیک
     * این ویژگی کپی کردن آدرس را برای کاربر راحت‌تر می‌کند.
     */
    $webhookCode.on('click', function() {
        const range = document.createRange();
        range.selectNodeContents(this);
        const sel = window.getSelection();
        sel.removeAllRanges();
        sel.addRange(range);
        
        // افکت ویژوال لحظه‌ای برای کپی
        $(this).css('background-color', '#fff9c4');
        setTimeout(() => {
            $(this).css('background-color', '#fff');
        }, 300);
    });

    /**
     * 3. اعتبارسنجی ساده در سمت کلاینت (UX Enhancement)
     * اگر کاربر فیلدهای ضروری را خالی گذاشته باشد، قبل از ارسال هشدار می‌دهد.
     */
    $('.rti-form').on('submit', function(e) {
        let hasError = false;
        
        $(this).find('input[required]').each(function() {
            if (!$(this).val()) {
                hasError = true;
                $(this).css('border-color', '#d93025');
            } else {
                $(this).css('border-color', '#dadce0');
            }
        });

        if (hasError) {
            e.preventDefault();
            alert('لطفاً تمام فیلدهای ضروری (توکن، آیدی کانال و کلید API) را پر کنید.');
            // اسکرول به بالا برای دیدن فیلدهای خالی
            $('html, body').animate({ scrollTop: 0 }, 'fast');
        }
    });

});

