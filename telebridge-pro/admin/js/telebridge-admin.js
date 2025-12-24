(function( $ ) {
	'use strict';

	/**
	 * All of the code for your admin-facing JavaScript source
	 * should reside in this file.
	 */
	$(document).ready(function() {

		// 1. Tab Navigation Logic
		// ----------------------------------------------------------------
		$('.nav-tab-wrapper a').on('click', function(e) {
			e.preventDefault();
			
			// Switch Tab Classes
			$('.nav-tab-wrapper a').removeClass('nav-tab-active');
			$(this).addClass('nav-tab-active');
			
			// Show Target Content
			var target = $(this).attr('href');
			$('.telebridge-tab-content').hide();
			$(target).show();
			
			// Optional: Persist tab selection in URL hash
			window.location.hash = target;
		});

		// Check URL hash on load to open correct tab
		if (window.location.hash) {
			var hash = window.location.hash;
			if ($(hash).length) {
				$('.nav-tab-wrapper a[href="' + hash + '"]').click();
			}
		}

		// 2. AI Provider Accordion (Show/Hide Settings)
		// ----------------------------------------------------------------
		function updateAIProviderVisibility() {
			var selectedProvider = $('#ai-provider-selector').val();
			
			// Hide all settings blocks with a slide effect
			$('.ai-settings-block').hide();
			
			// Show the selected one
			$('#settings-' + selectedProvider).fadeIn(300);
		}

		// Run on change
		$('#ai-provider-selector').on('change', updateAIProviderVisibility);
		
		// Run on init
		updateAIProviderVisibility();

		// 3. AJAX API Connection Test
		// ----------------------------------------------------------------
		$('.telebridge-test-api').on('click', function(e) {
			e.preventDefault();
			
			var $btn = $(this);
			var provider = $btn.data('provider');
			var apiKeyInput = $('#api_key_' + provider);
			var apiKey = apiKeyInput.val();
			var $resultSpan = $btn.next('.test-result');

			// Basic Client-side Validation
			if (!apiKey) {
				$resultSpan.css('color', 'red').text('Please enter an API Key first.');
				apiKeyInput.focus();
				return;
			}

			// UI Loading State
			$btn.prop('disabled', true).text(telebridgeData.strings.testing);
			$resultSpan.text('');

			// Send AJAX Request
			$.ajax({
				url: telebridgeData.ajax_url,
				type: 'POST',
				data: {
					action: 'telebridge_test_api', // Matches wp_ajax_ hook
					security: telebridgeData.nonce,
					provider: provider,
					api_key: apiKey
				},
				success: function(response) {
					if (response.success) {
						$resultSpan.css('color', 'green').text('✔ ' + response.data.message);
					} else {
						$resultSpan.css('color', 'red').text('✘ ' + response.data.message);
					}
				},
				error: function() {
					$resultSpan.css('color', 'red').text('Server Error. Check console.');
				},
				complete: function() {
					// Reset Button
					$btn.prop('disabled', false).text('Test Connection');
				}
			});
		});

	});

})( jQuery );