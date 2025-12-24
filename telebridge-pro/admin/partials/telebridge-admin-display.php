
<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @package    TeleBridge_Pro
 * @subpackage TeleBridge_Pro/admin/partials
 */
?>

<div class="wrap telebridge-wrap">
	
	<div class="telebridge-header">
		<h1><?php echo esc_html( get_admin_page_title() ); ?> <span class="badge">Pro</span></h1>
		<p class="description"><?php esc_html_e( 'Connect Telegram to WordPress using advanced AI.', 'telebridge-pro' ); ?></p>
	</div>

	<h2 class="nav-tab-wrapper">
		<a href="#tab-general" class="nav-tab nav-tab-active"><?php esc_html_e( 'Telegram Settings', 'telebridge-pro' ); ?></a>
		<a href="#tab-ai" class="nav-tab"><?php esc_html_e( 'AI Configuration', 'telebridge-pro' ); ?></a>
		<a href="#tab-mapping" class="nav-tab"><?php esc_html_e( 'Post Settings', 'telebridge-pro' ); ?></a>
	</h2>

	<form method="post" action="options.php" class="telebridge-form">
		<?php
			// This prints out all hidden setting fields
			settings_fields( 'telebridge_option_group' );
			do_settings_sections( 'telebridge_option_group' );
		?>

		<!-- TAB: General (Telegram) -->
		<div id="tab-general" class="telebridge-tab-content active">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Telegram Bot Token', 'telebridge-pro' ); ?></th>
					<td>
						<input type="text" name="telebridge_telegram_bot_token" value="<?php echo esc_attr( get_option('telebridge_telegram_bot_token') ); ?>" class="regular-text" placeholder="123456789:ABCdefGhI..." />
						<p class="description"><?php esc_html_e( 'Get this from @BotFather.', 'telebridge-pro' ); ?></p>
					</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Webhook URL', 'telebridge-pro' ); ?></th>
					<td>
						<code class="telebridge-code"><?php echo esc_url( site_url( '/?telebridge_webhook=' . get_option('telebridge_webhook_token') ) ); ?></code>
						<p class="description"><?php esc_html_e( 'Set this URL as the webhook for your bot.', 'telebridge-pro' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<!-- TAB: AI Configuration -->
		<div id="tab-ai" class="telebridge-tab-content">
			
			<div class="telebridge-card">
				<h3><?php esc_html_e( 'Active AI Provider', 'telebridge-pro' ); ?></h3>
				<p><?php esc_html_e( 'Select which brain should process your Telegram posts.', 'telebridge-pro' ); ?></p>
				
				<select name="telebridge_active_ai_provider" id="ai-provider-selector">
					<option value="google" <?php selected( get_option('telebridge_active_ai_provider'), 'google' ); ?>>Google Gemini (Fast & Free Tier)</option>
					<option value="openai" <?php selected( get_option('telebridge_active_ai_provider'), 'openai' ); ?>>OpenAI GPT-4o (High Precision)</option>
					<option value="gapgpt" <?php selected( get_option('telebridge_active_ai_provider'), 'gapgpt' ); ?>>GapGPT (Region Optimized)</option>
				</select>
			</div>

			<!-- Google Settings -->
			<div class="ai-settings-block" id="settings-google">
				<h4><span class="dashicons dashicons-google"></span> Google AI Studio</h4>
				<table class="form-table">
					<tr>
						<th>API Key</th>
						<td>
							<input type="password" name="telebridge_api_key_google" id="api_key_google" value="<?php echo esc_attr( get_option('telebridge_api_key_google') ); ?>" class="regular-text" />
							<button type="button" class="button telebridge-test-api" data-provider="google"><?php esc_html_e( 'Test Connection', 'telebridge-pro' ); ?></button>
							<span class="test-result"></span>
						</td>
					</tr>
				</table>
			</div>

			<!-- OpenAI Settings -->
			<div class="ai-settings-block" id="settings-openai" style="display:none;">
				<h4><span class="dashicons dashicons-superhero"></span> OpenAI</h4>
				<table class="form-table">
					<tr>
						<th>API Key</th>
						<td>
							<input type="password" name="telebridge_api_key_openai" id="api_key_openai" value="<?php echo esc_attr( get_option('telebridge_api_key_openai') ); ?>" class="regular-text" />
							<button type="button" class="button telebridge-test-api" data-provider="openai"><?php esc_html_e( 'Test Connection', 'telebridge-pro' ); ?></button>
							<span class="test-result"></span>
						</td>
					</tr>
				</table>
			</div>

			<!-- GapGPT Settings -->
			<div class="ai-settings-block" id="settings-gapgpt" style="display:none;">
				<h4><span class="dashicons dashicons-admin-network"></span> GapGPT</h4>
				<table class="form-table">
					<tr>
						<th>Access Token</th>
						<td>
							<input type="password" name="telebridge_api_key_gapgpt" id="api_key_gapgpt" value="<?php echo esc_attr( get_option('telebridge_api_key_gapgpt') ); ?>" class="regular-text" />
							<button type="button" class="button telebridge-test-api" data-provider="gapgpt"><?php esc_html_e( 'Test Connection', 'telebridge-pro' ); ?></button>
							<span class="test-result"></span>
						</td>
					</tr>
				</table>
			</div>

		</div>

		<!-- TAB: Post Settings -->
		<div id="tab-mapping" class="telebridge-tab-content">
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Target Post Type', 'telebridge-pro' ); ?></th>
					<td>
						<select name="telebridge_target_post_type">
							<option value="post" <?php selected( get_option('telebridge_target_post_type'), 'post' ); ?>><?php esc_html_e( 'Standard Post', 'telebridge-pro' ); ?></option>
							<option value="product" <?php selected( get_option('telebridge_target_post_type'), 'product' ); ?>><?php esc_html_e( 'WooCommerce Product', 'telebridge-pro' ); ?></option>
							<?php 
							// Dynamically list JetEngine CPTs
							$post_types = get_post_types( array( '_builtin' => false ), 'objects' ); 
							foreach ( $post_types as $pt ) {
								echo '<option value="' . esc_attr( $pt->name ) . '" ' . selected( get_option('telebridge_target_post_type'), $pt->name, false ) . '>' . esc_html( $pt->label ) . '</option>';
							}
							?>
						</select>
					</td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e( 'Default Status', 'telebridge-pro' ); ?></th>
					<td>
						<select name="telebridge_default_status">
							<option value="publish" <?php selected( get_option('telebridge_default_status'), 'publish' ); ?>><?php esc_html_e( 'Publish Immediately', 'telebridge-pro' ); ?></option>
							<option value="draft" <?php selected( get_option('telebridge_default_status'), 'draft' ); ?>><?php esc_html_e( 'Save as Draft', 'telebridge-pro' ); ?></option>
							<option value="pending" <?php selected( get_option('telebridge_default_status'), 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'telebridge-pro' ); ?></option>
						</select>
					</td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>

	</form>
</div>