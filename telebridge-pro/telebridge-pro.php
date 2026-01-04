<?php
/**
 * Plugin Name: Telebridge Pro
 * Plugin URI: https://readystudio.ir
 * Description: سیستم انتقال خودکار پرامپت‌های هوش مصنوعی از تلگرام به ووکامرس با تولید عنوان و توضیح خودکار
 * Version: 6.0.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: telebridge-pro
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'TELEBRIDGE_PRO_VERSION', '6.0.0' );
define( 'TELEBRIDGE_PRO_PATH', plugin_dir_path( __FILE__ ) );
define( 'TELEBRIDGE_PRO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Telebridge_Pro {

    private static $instance = null;
    const OPTION_KEY = 'telebridge_pro_settings';
    private $tg_api_base = 'https://api.telegram.org/bot';
    private $gapgpt_api  = 'https://api.gapgpt.app/v1/chat/completions';

    /**
     * Singleton pattern
     */
    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Setup hooks and filters
     */
    public function __construct() {
        // Admin hooks
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // REST API hooks
        add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );

        // AJAX hooks
        add_action( 'wp_ajax_telebridge_set_webhook', [ $this, 'ajax_set_webhook' ] );

        // Plugin activation/deactivation
        register_activation_hook( __FILE__, [ $this, 'on_activation' ] );
        register_deactivation_hook( __FILE__, [ $this, 'on_deactivation' ] );

        // Load auto-categorization module (now separate plugin)
        // This is handled by the standalone Telebridge Auto Categorization plugin
    }

    /**
     * Note: Auto-categorization is now a separate plugin
     * No need to load it here
     */

    /**
     * Plugin activation hook
     */
    public function on_activation() {
        // Create necessary database tables or options
        $default_settings = [
            'bot_token' => '',
            'channel_id' => '',
            'gapgpt_key' => '',
            'ai_model' => 'gpt-4o-mini',
            'price' => '',
            'cat' => '',
            'status' => 'pending'
        ];

        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, $default_settings );
        }

        // Clear any cached data
        wp_cache_flush();
    }

    /**
     * Plugin deactivation hook
     */
    public function on_deactivation() {
        // Clean up if needed
        wp_cache_flush();
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Telebridge Pro', 'telebridge-pro' ),
            __( 'Telebridge Pro', 'telebridge-pro' ),
            'manage_options',
            'telebridge-pro',
            [ $this, 'render_settings_page' ],
            'dashicons-link',
            58
        );
    }

    /**
     * Enqueue admin styles and scripts
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_telebridge-pro' !== $hook ) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'telebridge-pro-admin',
            TELEBRIDGE_PRO_URL . 'assets/admin-style.css',
            [],
            TELEBRIDGE_PRO_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'telebridge-pro-admin',
            TELEBRIDGE_PRO_URL . 'assets/admin-script.js',
            [ 'jquery' ],
            TELEBRIDGE_PRO_VERSION,
            true
        );

        // Localize script
        wp_localize_script(
            'telebridge-pro-admin',
            'telebridge_vars',
            [
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'telebridge_nonce' )
            ]
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'telebridge_group', self::OPTION_KEY );
    }

    /**
     * Render admin settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'telebridge-pro' ) );
        }

        $opt = get_option( self::OPTION_KEY );
        $webhook = site_url( '/wp-json/telebridge/v1/webhook' );
        ?>
        <div class="rti-wrap">
            <header class="rti-header">
                <h1>🤖 <?php _e( 'Telebridge Pro - مدیر انتقال پرامپت', 'telebridge-pro' ); ?></h1>
                <p style="color: #666; font-size: 13px;">Ready Studio - readystudio.ir</p>
            </header>

            <form method="post" action="options.php" class="rti-form">
                <?php settings_fields( 'telebridge_group' ); ?>

                <!-- API Settings Card -->
                <div class="rti-card">
                    <h2 class="rti-card-title">🔑 <?php _e( 'تنظیمات API (حیاتی)', 'telebridge-pro' ); ?></h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label><?php _e( 'توکن ربات تلگرام', 'telebridge-pro' ); ?></label>
                            <input type="password" 
                                   name="<?php echo self::OPTION_KEY; ?>[bot_token]" 
                                   value="<?php echo esc_attr( $opt['bot_token'] ?? '' ); ?>" 
                                   placeholder="123456:ABC..." 
                                   required>
                        </div>
                        <div class="rti-col">
                            <label><?php _e( 'آیدی کانال (Channel ID)', 'telebridge-pro' ); ?></label>
                            <input type="text" 
                                   name="<?php echo self::OPTION_KEY; ?>[channel_id]" 
                                   value="<?php echo esc_attr( $opt['channel_id'] ?? '' ); ?>" 
                                   placeholder="@MyChannel" 
                                   required>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label><?php _e( 'کلید API GapGPT', 'telebridge-pro' ); ?></label>
                            <input type="password" 
                                   name="<?php echo self::OPTION_KEY; ?>[gapgpt_key]" 
                                   value="<?php echo esc_attr( $opt['gapgpt_key'] ?? '' ); ?>" 
                                   placeholder="sk-..." 
                                   required>
                            <small><?php _e( 'دریافت کلید:', 'telebridge-pro' ); ?> <a href="https://gapgpt.app/platform-v2/tokens" target="_blank">gapgpt.app/platform-v2/tokens</a></small>
                        </div>
                        <div class="rti-col">
                            <label><?php _e( 'مدل هوش مصنوعی', 'telebridge-pro' ); ?></label>
                            <select name="<?php echo self::OPTION_KEY; ?>[ai_model]" required>
                                <option value="gpt-4o-mini" <?php selected( $opt['ai_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini' ); ?>>gpt-4o-mini (<?php _e( 'توصیه شده', 'telebridge-pro' ); ?>)</option>
                                <option value="gpt-4" <?php selected( $opt['ai_model'] ?? '', 'gpt-4' ); ?>>gpt-4</option>
                                <option value="gpt-3.5-turbo" <?php selected( $opt['ai_model'] ?? '', 'gpt-3.5-turbo' ); ?>>gpt-3.5-turbo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Settings Card -->
                <div class="rti-card">
                    <h2 class="rti-card-title">📦 <?php _e( 'تنظیمات محصول', 'telebridge-pro' ); ?></h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label><?php _e( 'قیمت محصول (تومان)', 'telebridge-pro' ); ?></label>
                            <input type="number" 
                                   name="<?php echo self::OPTION_KEY; ?>[price]" 
                                   value="<?php echo esc_attr( $opt['price'] ?? '' ); ?>" 
                                   placeholder="0">
                        </div>
                        <div class="rti-col">
                            <label><?php _e( 'دسته‌بندی پیش‌فرض', 'telebridge-pro' ); ?></label>
                            <?php wp_dropdown_categories( [
                                'taxonomy' => 'product_cat',
                                'name' => self::OPTION_KEY . '[cat]',
                                'selected' => $opt['cat'] ?? '',
                                'show_option_none' => __( '-- انتخاب --', 'telebridge-pro' ),
                                'class' => 'rti-full'
                            ] ); ?>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label><?php _e( 'وضعیت انتشار', 'telebridge-pro' ); ?></label>
                            <select name="<?php echo self::OPTION_KEY; ?>[status]" class="rti-full">
                                <option value="pending" <?php selected( $opt['status'] ?? 'pending', 'pending' ); ?>><?php _e( 'منتظر تایید (توصیه شده)', 'telebridge-pro' ); ?></option>
                                <option value="draft" <?php selected( $opt['status'] ?? '', 'draft' ); ?>><?php _e( 'پیش‌نویس', 'telebridge-pro' ); ?></option>
                                <option value="publish" <?php selected( $opt['status'] ?? '', 'publish' ); ?>><?php _e( 'انتشار فوری', 'telebridge-pro' ); ?></option>
                            </select>
                            <small>⚠️ <?php _e( 'توصیه: "منتظر تایید" را انتخاب کنید تا محصول قبل از تایید نام/توضیح نشر نشود', 'telebridge-pro' ); ?></small>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="rti-actions">
                    <?php submit_button( __( 'ذخیره تنظیمات', 'telebridge-pro' ), 'primary large', 'submit', false ); ?>
                </div>
            </form>

            <!-- Webhook Configuration -->
            <div class="rti-card rti-webhook-box">
                <h3>🌐 <?php _e( 'اتصال وب‌هوک', 'telebridge-pro' ); ?></h3>
                <p><?php _e( 'آدرس Webhook برای تلگرام:', 'telebridge-pro' ); ?></p>
                <code><?php echo esc_url( $webhook ); ?></code>
                <button type="button" id="rti_webhook_btn" class="button button-secondary">
                    📞 <?php _e( 'اتصال خودکار به تلگرام', 'telebridge-pro' ); ?>
                </button>
                <div id="rti_msg"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Register REST API webhook endpoint
     */
    public function register_webhook_endpoint() {
        register_rest_route( 'telebridge/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [ $this, 'process_webhook' ],
            'permission_callback' => '__return_true'
        ] );
    }

    /**
     * Process incoming Telegram webhook
     */
    public function process_webhook( $request ) {
        $data = $request->get_json_params();
        $opt = get_option( self::OPTION_KEY );

        // Validate: Check if channel_post exists
        if ( ! isset( $data['channel_post'] ) ) {
            return new WP_REST_Response( [ 'status' => 'ignored' ], 200 );
        }

        $post = $data['channel_post'];

        // Security: Verify channel ID
        $valid_channel = $opt['channel_id'] ?? '';
        if ( $valid_channel ) {
            $incoming_id = (string) $post['chat']['id'];
            $incoming_user = '@' . ( $post['chat']['username'] ?? '' );

            if ( $incoming_id !== $valid_channel && $incoming_user !== $valid_channel ) {
                return new WP_REST_Response( [ 'error' => 'Unauthorized' ], 403 );
            }
        }

        // Validate: Check if photo exists
        if ( ! isset( $post['photo'] ) ) {
            return new WP_REST_Response( [ 'status' => 'no_photo' ], 200 );
        }

        // Step 1: Download image from Telegram
        $token = $opt['bot_token'] ?? '';
        $photo = end( $post['photo'] );
        $img_id = $this->download_image( $photo['file_id'], $token );

        if ( is_wp_error( $img_id ) ) {
            error_log( 'Telebridge: Image download failed - ' . $img_id->get_error_message() );
            return new WP_REST_Response( [ 'error' => 'Image download failed' ], 500 );
        }

        // Step 2: Get raw prompt from caption
        $raw_prompt = $post['caption'] ?? '';

        if ( empty( $raw_prompt ) ) {
            error_log( 'Telebridge: Empty prompt' );
            return new WP_REST_Response( [ 'error' => 'Empty prompt' ], 400 );
        }

        // Step 3: Generate content using AI
        $ai_key = $opt['gapgpt_key'] ?? '';
        $model = $opt['ai_model'] ?? 'gpt-4o-mini';

        $ai_data = $this->generate_content_with_ai( $raw_prompt, $ai_key, $model );

        // Step 4: Extract generated data with fallbacks
        $title_fa = ! empty( $ai_data['title_fa'] ) ? $ai_data['title_fa'] : wp_trim_words( $raw_prompt, 5 );
        $desc_fa = ! empty( $ai_data['description'] ) ? $ai_data['description'] : wp_trim_words( $raw_prompt, 15 );
        $title_en = $ai_data['title_en'] ?? '';

        // Check if AI generation was successful
        $needs_review = empty( $ai_data['title_fa'] ) || empty( $ai_data['description'] );

        // Step 5: Create WooCommerce product
        $product_id = wp_insert_post( [
            'post_title' => $title_fa,
            'post_content' => $desc_fa,
            'post_status' => 'pending', // Always pending for manual review
            'post_type' => 'product',
            'post_author' => 1
        ] );

        if ( is_wp_error( $product_id ) ) {
            error_log( 'Telebridge: Product creation failed - ' . $product_id->get_error_message() );
            return new WP_REST_Response( [ 'error' => 'Product creation failed' ], 500 );
        }

        // Step 6: Set product meta data
        $this->set_product_meta( $product_id, $raw_prompt, $title_en, $img_id, $opt );

        // Step 7: Generate 2 tags automatically
        $this->generate_product_tags( $product_id, $raw_prompt, $ai_data, $ai_key, $model );

        // Step 8: Trigger auto-categorization (AI Sites integration)
        do_action( 'rti_product_created', $product_id, $raw_prompt, $ai_key, $model );

        // Log success
        error_log( 'Telebridge: Product ' . $product_id . ' created successfully' );

        return new WP_REST_Response( [
            'success' => true,
            'product_id' => $product_id,
            'title_fa' => $title_fa,
            'title_en' => $title_en,
            'status' => 'pending'
        ], 200 );
    }

    /**
     * Set product meta data
     */
    private function set_product_meta( $product_id, $raw_prompt, $title_en, $img_id, $opt ) {
        $price = $opt['price'] ?? '0';

        // WooCommerce standard metas
        update_post_meta( $product_id, '_price', $price );
        update_post_meta( $product_id, '_regular_price', $price );
        update_post_meta( $product_id, '_virtual', 'yes' );

        // Set featured image
        set_post_thumbnail( $product_id, $img_id );

        // Set product category
        if ( ! empty( $opt['cat'] ) ) {
            wp_set_object_terms( $product_id, (int) $opt['cat'], 'product_cat' );
        }

        // Custom meta fields (JetEngine compatible)
        update_post_meta( $product_id, 'prompt-text', $raw_prompt );

        if ( ! empty( $title_en ) ) {
            update_post_meta( $product_id, 'latin-name-product', $title_en );
        }

        update_post_meta( $product_id, 'idea-owner', 'readystudio' );
    }

    /**
     * Generate 2 tags for product based on prompt
     */
    private function generate_product_tags( $product_id, $raw_prompt, $ai_data, $ai_key, $model ) {
        $system_msg = "You are a professional content categorizer.

Analyze this AI prompt and generate exactly 2 relevant product tags.
Tags should be:
- Specific and descriptive
- SEO-friendly
- In Persian
- Separated by comma

Return ONLY 2 tags, no explanation.
Example: AI Art, Image Generation";

        $response = $this->call_gapgpt_api(
            $system_msg,
            substr( $raw_prompt, 0, 500 ),
            $ai_key,
            $model,
            100
        );

        if ( ! $response ) {
            $response = 'پرامپت AI، جنریت خودکار';
        }

        // Parse tags
        $tags = array_map( 'trim', explode( ',', $response ) );
        $tags = array_slice( $tags, 0, 2 );

        if ( ! empty( $tags ) ) {
            wp_set_object_terms( $product_id, $tags, 'product_tag', true );
            error_log( 'Telebridge: Added tags to product ' . $product_id . ': ' . implode( ', ', $tags ) );
        }
    }

    /**
     * Generate product title, description, and English title using GapGPT
     */
    private function generate_content_with_ai( $prompt, $api_key, $model ) {
        if ( empty( $api_key ) || empty( $prompt ) ) {
            error_log( 'Telebridge: Missing API key or prompt' );
            return [];
        }

        $system_msg = "You are a professional SEO copywriter specializing in AI prompts.

TASK: Analyze the user's prompt and generate a professional product listing.

Return ONLY valid JSON (no markdown, no code blocks, no explanations):
{
  \"title_fa\": \"عنوان فارسی جذاب و کوتاه (حداکثر 8 کلمه)\",
  \"title_en\": \"Short English Title (Max 5 words)\",
  \"description\": \"توضیح دقیق و جذاب 2-3 خط درباره این پرامپت. دقیقاً چه تصویری/محصولی ایجاد می‌کند؟\"
}

IMPORTANT:
- Title must be catchy and SEO-friendly
- Description must be 2-3 sentences explaining EXACTLY what this prompt creates
- Return ONLY JSON, nothing else
- Use UTF-8 encoding";

        $response = $this->call_gapgpt_api(
            $system_msg,
            substr( $prompt, 0, 1500 ),
            $api_key,
            $model,
            500
        );

        if ( ! $response ) {
            error_log( 'Telebridge: AI generation failed' );
            return [];
        }

        // Clean and parse JSON
        $response = str_replace( [ '```json', '```', '`' ], '', $response );
        $response = trim( $response );

        $json = json_decode( $response, true );

        if ( ! is_array( $json ) ) {
            error_log( 'Telebridge: JSON parsing failed. Response: ' . substr( $response, 0, 200 ) );
            return [];
        }

        return $json;
    }

    /**
     * Call GapGPT API
     */
    private function call_gapgpt_api( $system_msg, $user_msg, $api_key, $model, $max_tokens = 300 ) {
        $body = [
            'model' => $model,
            'messages' => [
                [ 'role' => 'system', 'content' => $system_msg ],
                [ 'role' => 'user', 'content' => $user_msg ]
            ],
            'temperature' => 0.7,
            'max_tokens' => $max_tokens
        ];

        $response = wp_remote_post( $this->gapgpt_api, [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode( $body ),
            'timeout' => 30
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'Telebridge: GapGPT API Error - ' . $response->get_error_message() );
            return null;
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        if ( $http_code !== 200 ) {
            $body_content = wp_remote_retrieve_body( $response );
            error_log( 'Telebridge: GapGPT HTTP ' . $http_code . ' - ' . substr( $body_content, 0, 200 ) );
            return null;
        }

        $body_content = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $body_content['choices'][0]['message']['content'] ) ) {
            error_log( 'Telebridge: Invalid API response structure' );
            return null;
        }

        return $body_content['choices'][0]['message']['content'];
    }

    /**
     * Download image from Telegram
     */
    private function download_image( $file_id, $token ) {
        // Get file info from Telegram API
        $response = wp_remote_get( $this->tg_api_base . $token . '/getFile?file_id=' . $file_id );

        if ( is_wp_error( $response ) ) {
            return new WP_Error( 'api_error', 'Could not fetch file info' );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! isset( $data['result']['file_path'] ) ) {
            return new WP_Error( 'no_file_path', 'File path not found' );
        }

        $file_path = $data['result']['file_path'];
        $download_url = "https://api.telegram.org/file/bot{$token}/{$file_path}";

        // Include WordPress media functions
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download file
        $tmp_file = download_url( $download_url );

        if ( is_wp_error( $tmp_file ) ) {
            return $tmp_file;
        }

        // Handle sideload
        $attachment_id = media_handle_sideload( [
            'name' => basename( $file_path ),
            'tmp_name' => $tmp_file
        ], 0 );

        if ( is_wp_error( $attachment_id ) ) {
            @unlink( $tmp_file );
            return $attachment_id;
        }

        return $attachment_id;
    }

    /**
     * AJAX: Set Telegram webhook
     */
    public function ajax_set_webhook() {
        check_ajax_referer( 'telebridge_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Unauthorized', 'telebridge-pro' ) );
        }

        $opt = get_option( self::OPTION_KEY );
        $token = $opt['bot_token'] ?? '';

        if ( empty( $token ) ) {
            wp_send_json_error( __( 'توکن ربات را وارد کنید', 'telebridge-pro' ) );
        }

        $webhook_url = site_url( '/wp-json/telebridge/v1/webhook' );
        $response = wp_remote_get(
            $this->tg_api_base . $token . '/setWebhook?url=' . urlencode( $webhook_url )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( $response->get_error_message() );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset( $data['ok'] ) && $data['ok'] ) {
            wp_send_json_success( __( 'متصل شد! ✅', 'telebridge-pro' ) );
        } else {
            $error_msg = $data['description'] ?? __( 'خطای نامشخص', 'telebridge-pro' );
            wp_send_json_error( $error_msg );
        }
    }
}

/**
 * Initialize the plugin
 */
function telebridge_pro_init() {
    Telebridge_Pro::get_instance();
}
add_action( 'plugins_loaded', 'telebridge_pro_init' );

/**
 * Add plugin links
 */
function telebridge_pro_plugin_links( $links ) {
    $settings_link = '<a href="' . admin_url( 'admin.php?page=telebridge-pro' ) . '">' . __( 'Settings', 'telebridge-pro' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'telebridge_pro_plugin_links' );