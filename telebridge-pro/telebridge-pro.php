<?php
/**
 * Plugin Name: Telebridge Ultimate
 * Plugin URI: https://readystudio.ir
 * Description: سیستم جامع و یکپارچه انتقال پرامپت از تلگرام به ووکامرس. شامل: پشتیبانی از آلبوم و فایل، تولید محتوا با هوش مصنوعی (GapGPT)، دسته‌بندی خودکار و مدیریت لینک‌های AI.
 * Version: 7.2.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: telebridge-ultimate
 * Domain Path: /languages
 * License: GPL v2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Constants
define( 'TELEBRIDGE_ULTIMATE_VERSION', '7.2.0' );
define( 'TELEBRIDGE_ULTIMATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'TELEBRIDGE_ULTIMATE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class Telebridge_Ultimate {

    private static $instance = null;
    const OPTION_KEY = 'telebridge_ultimate_settings';
    
    // API Endpoints
    private $tg_api_base = 'https://api.telegram.org/bot';
    private $gapgpt_api  = 'https://api.gapgpt.app/v1/chat/completions';

    // AI Sites Manager Option (Global DB)
    private $sites_option_name = 'ai_sites_pro_global_db';

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // --- Admin Hooks ---
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // --- Meta Boxes (AI Sites Manager) ---
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_data' ] );

        // --- Frontend Hooks (AI Sites Display) ---
        add_shortcode( 'ai_sites', [ $this, 'shortcode_handler' ] );
        add_filter( 'woocommerce_product_tabs', [ $this, 'add_woocommerce_tab' ] );

        // --- REST API (Webhook) ---
        add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );

        // --- AJAX ---
        add_action( 'wp_ajax_telebridge_set_webhook', [ $this, 'ajax_set_webhook' ] );

        // --- Activation ---
        register_activation_hook( __FILE__, [ $this, 'on_activation' ] );
    }

    /**
     * Activation Hook - Setup Default Options
     */
    public function on_activation() {
        // Default System Prompt for Content
        $default_sys_content = "You are a professional SEO copywriter for AI Prompts.
Analyze the user prompt.
Return ONLY valid JSON:
{
  \"title_fa\": \"عنوان فارسی جذاب (max 8 words)\",
  \"title_en\": \"English Title (max 6 words)\",
  \"description\": \"توضیحات جذاب و کامل به فارسی (2-3 خط) درباره خروجی این پرامپت\"
}";

        // Default System Prompt for Tags
        $default_sys_tags = "Generate exactly 2 relevant Persian tags for this prompt. Comma separated. No explanation.";

        $default_settings = [
            'bot_token' => '',
            'channel_id' => '',
            'gapgpt_key' => '',
            'ai_model' => 'gpt-4o-mini',
            'price' => '',
            'cat' => '',
            'status' => 'pending',
            'sys_prompt_content' => $default_sys_content,
            'sys_prompt_tags' => $default_sys_tags
        ];

        if ( ! get_option( self::OPTION_KEY ) ) {
            add_option( self::OPTION_KEY, $default_settings );
        }
        
        // Create upload directory protection if needed
        flush_rewrite_rules();
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_telebridge-ultimate' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'telebridge-admin', TELEBRIDGE_ULTIMATE_URL . 'admin-style.css', [], TELEBRIDGE_ULTIMATE_VERSION );
        wp_enqueue_script( 'telebridge-admin', TELEBRIDGE_ULTIMATE_URL . 'admin-script.js', [ 'jquery' ], TELEBRIDGE_ULTIMATE_VERSION, true );
        
        wp_localize_script( 'telebridge-admin', 'telebridge_vars', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'telebridge_nonce' )
        ] );
    }

    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Telebridge Ultimate',
            'Telebridge Ultimate',
            'manage_options',
            'telebridge-ultimate',
            [ $this, 'render_settings_page' ],
            'dashicons-superhero',
            58
        );
    }

    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting( 'telebridge_group', self::OPTION_KEY );
    }

    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $opt = get_option( self::OPTION_KEY );
        $webhook = site_url( '/wp-json/telebridge/v1/webhook' );
        ?>
        <div class="rti-wrap">
            <header class="rti-header">
                <h1>🚀 Telebridge Ultimate <span style="font-size:12px; opacity:0.8; background:var(--tb-primary-light); color:var(--tb-primary); padding:2px 8px; border-radius:12px;">v<?php echo TELEBRIDGE_ULTIMATE_VERSION; ?></span></h1>
                <p>سیستم یکپارچه انتقال پرامپت، مدیریت فایل و هوش مصنوعی</p>
            </header>

            <form method="post" action="options.php" class="rti-form">
                <?php settings_fields( 'telebridge_group' ); ?>

                <!-- API Settings -->
                <div class="rti-card">
                    <h2 class="rti-card-title">🔑 تنظیمات API (حیاتی)</h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>توکن ربات تلگرام</label>
                            <input type="password" name="<?php echo self::OPTION_KEY; ?>[bot_token]" value="<?php echo esc_attr( $opt['bot_token'] ?? '' ); ?>" placeholder="123456:ABC..." required>
                        </div>
                        <div class="rti-col">
                            <label>آیدی کانال (Channel ID)</label>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[channel_id]" value="<?php echo esc_attr( $opt['channel_id'] ?? '' ); ?>" placeholder="@MyChannel" required>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>کلید API GapGPT</label>
                            <input type="password" name="<?php echo self::OPTION_KEY; ?>[gapgpt_key]" value="<?php echo esc_attr( $opt['gapgpt_key'] ?? '' ); ?>" placeholder="sk-..." required>
                        </div>
                        <div class="rti-col">
                            <label>مدل هوش مصنوعی</label>
                            <select name="<?php echo self::OPTION_KEY; ?>[ai_model]">
                                <option value="gpt-4o-mini" <?php selected( $opt['ai_model'] ?? 'gpt-4o-mini', 'gpt-4o-mini' ); ?>>gpt-4o-mini (سریع و اقتصادی - توصیه شده)</option>
                                <option value="gpt-4" <?php selected( $opt['ai_model'] ?? '', 'gpt-4' ); ?>>gpt-4 (دقیق‌ترین)</option>
                                <option value="gpt-3.5-turbo" <?php selected( $opt['ai_model'] ?? '', 'gpt-3.5-turbo' ); ?>>gpt-3.5-turbo</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Settings -->
                <div class="rti-card">
                    <h2 class="rti-card-title">📦 تنظیمات محصول</h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>قیمت محصول (تومان)</label>
                            <input type="number" name="<?php echo self::OPTION_KEY; ?>[price]" value="<?php echo esc_attr( $opt['price'] ?? '' ); ?>" placeholder="0">
                        </div>
                        <div class="rti-col">
                            <label>دسته‌بندی پیش‌فرض</label>
                            <?php wp_dropdown_categories( [
                                'taxonomy' => 'product_cat',
                                'name' => self::OPTION_KEY . '[cat]',
                                'selected' => $opt['cat'] ?? '',
                                'show_option_none' => '-- انتخاب --',
                                'class' => 'rti-full'
                            ] ); ?>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>وضعیت انتشار</label>
                            <select name="<?php echo self::OPTION_KEY; ?>[status]">
                                <option value="pending" <?php selected( $opt['status'] ?? 'pending', 'pending' ); ?>>در انتظار بررسی (Pending)</option>
                                <option value="draft" <?php selected( $opt['status'] ?? '', 'draft' ); ?>>پیش‌نویس (Draft)</option>
                                <option value="publish" <?php selected( $opt['status'] ?? '', 'publish' ); ?>>انتشار (Publish)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Advanced AI Prompts (New Feature) -->
                <div class="rti-card">
                    <h2 class="rti-card-title">🤖 تنظیمات پیشرفته هوش مصنوعی (System Prompts)</h2>
                    <p style="font-size:13px; color:#666; margin-bottom:15px;">در این بخش می‌توانید رفتار هوش مصنوعی را شخصی‌سازی کنید.</p>
                    
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>دستور تولید محتوا (Content Prompt)</label>
                            <textarea name="<?php echo self::OPTION_KEY; ?>[sys_prompt_content]" rows="5" placeholder="System prompt for generating title and description..."><?php echo esc_textarea( $opt['sys_prompt_content'] ?? '' ); ?></textarea>
                            <small>باید حتماً خروجی <strong>JSON</strong> تولید کند.</small>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>دستور تولید تگ (Tags Prompt)</label>
                            <textarea name="<?php echo self::OPTION_KEY; ?>[sys_prompt_tags]" rows="3" placeholder="System prompt for generating tags..."><?php echo esc_textarea( $opt['sys_prompt_tags'] ?? '' ); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="rti-actions">
                    <?php submit_button( 'ذخیره تنظیمات', 'primary large', 'submit', false ); ?>
                </div>
            </form>

            <!-- Webhook Box -->
            <div class="rti-card rti-webhook-box">
                <h3>🌐 اتصال به تلگرام</h3>
                <code><?php echo esc_url( $webhook ); ?></code>
                <button type="button" id="rti_webhook_btn" class="button button-secondary">📞 اتصال خودکار وب‌هوک</button>
                <div id="rti_msg"></div>
            </div>
        </div>
        <?php
    }

    // =========================================================================
    //                         CORE WEBHOOK LOGIC
    // =========================================================================

    public function register_webhook_endpoint() {
        register_rest_route( 'telebridge/v1', '/webhook', [
            'methods' => 'POST',
            'callback' => [ $this, 'process_webhook' ],
            'permission_callback' => '__return_true'
        ] );
    }

    /**
     * Process Incoming Webhook
     */
    public function process_webhook( $request ) {
        $data = $request->get_json_params();
        $opt = get_option( self::OPTION_KEY );

        if ( ! isset( $data['channel_post'] ) ) return new WP_REST_Response( [ 'status' => 'ignored' ], 200 );
        
        $post = $data['channel_post'];

        // 1. Security: Verify Channel
        if ( ! $this->verify_channel( $post, $opt ) ) {
            return new WP_REST_Response( [ 'error' => 'Unauthorized channel' ], 403 );
        }

        // 2. Album Handling (Prevent Duplicates)
        if ( $this->is_duplicate_album( $post ) ) {
            return new WP_REST_Response( [ 'status' => 'album_skipped' ], 200 );
        }

        // 3. Detect Media Type (Photo vs Document) & File ID
        $media_info = $this->get_media_info( $post );
        if ( ! $media_info ) {
            return new WP_REST_Response( [ 'status' => 'no_supported_media' ], 200 );
        }

        // 4. Download Media
        $attachment_id = $this->download_telegram_file( $media_info['file_id'], $opt['bot_token'] ?? '' );
        if ( is_wp_error( $attachment_id ) ) {
            $this->log( 'Media Download Failed: ' . $attachment_id->get_error_message(), 'error' );
            return new WP_REST_Response( [ 'error' => 'Download failed' ], 500 );
        }

        // 5. Extract Prompt (Caption or Filename)
        $raw_prompt = $post['caption'] ?? '';
        if ( empty( $raw_prompt ) && $media_info['type'] === 'document' ) {
            $raw_prompt = $media_info['file_name'] ?? 'File Attachment';
        }

        if ( empty( $raw_prompt ) ) return new WP_REST_Response( [ 'error' => 'Empty prompt' ], 400 );

        // 6. AI Generation
        $ai_data = $this->generate_content_with_ai( $raw_prompt, $opt );
        $title_fa = $ai_data['title_fa'] ?? wp_trim_words( $raw_prompt, 5 );
        $desc_fa = $ai_data['description'] ?? wp_trim_words( $raw_prompt, 20 );
        $title_en = $ai_data['title_en'] ?? '';

        // Add Download Button for Documents
        if ( $media_info['type'] === 'document' ) {
            $file_url = wp_get_attachment_url( $attachment_id );
            $file_size = size_format( filesize( get_attached_file( $attachment_id ) ) );
            $desc_fa .= "\n\n<div class='tb-download-box'><a href='{$file_url}' class='button alt'>📥 دانلود فایل ضمیمه ({$file_size})</a></div>";
        }

        // 7. Create Product
        $product_id = wp_insert_post( [
            'post_title'    => $title_fa,
            'post_content'  => $desc_fa,
            'post_status'   => $opt['status'] ?? 'pending',
            'post_type'     => 'product',
            'post_author'   => 1
        ] );

        if ( is_wp_error( $product_id ) ) return new WP_REST_Response( [ 'error' => 'Product creation failed' ], 500 );

        // 8. Set Meta Data
        $this->set_product_meta( $product_id, $raw_prompt, $title_en, $attachment_id, $opt, $media_info['type'] );

        // 9. Generate Tags & Categories
        $this->generate_product_tags( $product_id, $raw_prompt, $opt );
        $this->auto_categorize_product( $product_id, $raw_prompt, $opt );

        return new WP_REST_Response( [ 'success' => true, 'product_id' => $product_id ], 200 );
    }

    // --- Helpers for Webhook ---

    private function verify_channel( $post, $opt ) {
        $valid_channel = $opt['channel_id'] ?? '';
        if ( empty( $valid_channel ) ) return true; // No validation if empty

        $incoming_id = (string) $post['chat']['id'];
        $incoming_user = '@' . ( $post['chat']['username'] ?? '' );
        
        return ( $incoming_id === $valid_channel || $incoming_user === $valid_channel );
    }

    private function is_duplicate_album( $post ) {
        if ( isset( $post['media_group_id'] ) ) {
            $group_id = $post['media_group_id'];
            $transient_key = 'tb_album_' . $group_id;
            
            // If locked, skip this request (it's part of the same album)
            if ( get_transient( $transient_key ) ) {
                return true; 
            }
            
            // Lock for 60 seconds (Telegram sends albums in quick succession)
            set_transient( $transient_key, true, 60 );
        }
        return false;
    }

    private function get_media_info( $post ) {
        if ( isset( $post['photo'] ) ) {
            $photo = end( $post['photo'] ); // Largest size
            return [ 'type' => 'image', 'file_id' => $photo['file_id'] ];
        } 
        elseif ( isset( $post['document'] ) ) {
            return [ 
                'type' => 'document', 
                'file_id' => $post['document']['file_id'],
                'file_name' => $post['document']['file_name'] ?? 'file'
            ];
        }
        return null;
    }

    private function set_product_meta( $product_id, $raw_prompt, $title_en, $attachment_id, $opt, $media_type ) {
        $price = $opt['price'] ?? '0';

        update_post_meta( $product_id, '_price', $price );
        update_post_meta( $product_id, '_regular_price', $price );
        update_post_meta( $product_id, '_virtual', 'yes' );
        
        // Only set featured image if it's an image
        if ( $media_type === 'image' ) {
            set_post_thumbnail( $product_id, $attachment_id );
        }

        if ( ! empty( $opt['cat'] ) ) {
            wp_set_object_terms( $product_id, (int) $opt['cat'], 'product_cat' );
        }

        // Custom Fields
        update_post_meta( $product_id, 'prompt-text', $raw_prompt );
        update_post_meta( $product_id, 'prompt-json', '' );
        update_post_meta( $product_id, 'latin-name-product', $title_en );
        update_post_meta( $product_id, 'idea-owner', 'readystudio' );
    }

    // =========================================================================
    //                         AI GENERATION LOGIC
    // =========================================================================

    private function generate_content_with_ai( $prompt, $opt ) {
        $api_key = $opt['gapgpt_key'] ?? '';
        if ( empty( $api_key ) ) return [];

        // Use custom system prompt if available
        $system_msg = !empty($opt['sys_prompt_content']) ? $opt['sys_prompt_content'] : 
        "You are a professional SEO copywriter for AI Prompts. Analyze the prompt. Return ONLY valid JSON: {\"title_fa\": \"...\", \"title_en\": \"...\", \"description\": \"...\"}";

        $model = $opt['ai_model'] ?? 'gpt-4o-mini';
        $response = $this->call_gapgpt( $system_msg, $prompt, $api_key, $model, 800 );
        
        if ( ! $response ) return [];

        $response = str_replace( [ '```json', '```', '`' ], '', $response );
        return json_decode( trim( $response ), true ) ?: [];
    }

    private function generate_product_tags( $product_id, $prompt, $opt ) {
        $api_key = $opt['gapgpt_key'] ?? '';
        if ( empty( $api_key ) ) return;

        $system_msg = !empty($opt['sys_prompt_tags']) ? $opt['sys_prompt_tags'] : 
        "Generate exactly 2 relevant Persian tags for this prompt. Comma separated. No explanation.";
        
        $model = $opt['ai_model'] ?? 'gpt-4o-mini';
        $response = $this->call_gapgpt( $system_msg, substr($prompt, 0, 500), $api_key, $model, 100 );
        
        if ( $response ) {
            $tags = array_map( 'trim', explode( ',', $response ) );
            wp_set_object_terms( $product_id, array_slice( $tags, 0, 2 ), 'product_tag', true );
        }
    }

    // =========================================================================
    //                         AUTO CATEGORIZATION
    // =========================================================================

    private function auto_categorize_product( $product_id, $raw_prompt, $opt ) {
        $ai_key = $opt['gapgpt_key'] ?? '';
        $model = $opt['ai_model'] ?? 'gpt-4o-mini';

        $all_sites = $this->get_reference_list();
        if ( empty( $all_sites ) || empty( $ai_key ) ) return;

        // 1. Detect Type
        $type = $this->detect_prompt_type( $raw_prompt, $ai_key, $model ) ?: 'tool';

        // 2. Filter Sites
        $relevant_sites = [];
        foreach ( $all_sites as $key => $site ) {
            $site_cat = $site['cat'] ?? 'tool';
            if ( $site_cat === $type || $this->is_related_category( $type, $site_cat ) ) {
                $relevant_sites[$key] = $site;
            }
        }
        if ( empty( $relevant_sites ) ) return;

        // 3. Score Sites
        $scored = $this->score_sites( $raw_prompt, $relevant_sites, $ai_key, $model );

        // 4. Save Top 4
        $selected_sites = array_slice( $scored, 0, 4, true );
        $selected_keys = array_keys( $selected_sites );

        update_post_meta( $product_id, '_ai_sites_checked', $selected_keys );
        update_post_meta( $product_id, '_ai_sites_auto_display', 'yes' );
    }

    private function detect_prompt_type( $prompt, $api_key, $model ) {
        $system_msg = "Determine prompt type: 'image', 'video', 'audio', 'text', 'code', 'tool'. Return ONE word only.";
        $res = $this->call_gapgpt( $system_msg, substr($prompt, 0, 800), $api_key, $model, 50 );
        return $res ? strtolower( trim( $res ) ) : null;
    }

    private function is_related_category( $type1, $type2 ) {
        $relations = [
            'image' => ['tool'], 'video' => ['image', 'tool'], 
            'text' => ['code', 'tool'], 'code' => ['text', 'tool']
        ];
        return isset($relations[$type1]) && in_array($type2, $relations[$type1]);
    }

    private function score_sites( $prompt, $sites, $api_key, $model ) {
        $sites_list = implode( "\n", array_map( fn($s) => "- {$s['name']}", $sites ) );
        $system_msg = "Score relevance (0-100) of platforms for this prompt. Return JSON object: {\"Midjourney\": 90}.";
        $user_msg = "PROMPT: " . substr($prompt, 0, 400) . "\nSITES:\n" . $sites_list;

        $res = $this->call_gapgpt( $system_msg, $user_msg, $api_key, $model, 300 );
        if ( ! $res ) return $sites;

        $res = str_replace( [ '```json', '```' ], '', $res );
        $scores = json_decode( trim($res), true ) ?: [];

        $scored_result = [];
        foreach ( $sites as $key => $site ) {
            $score = $scores[ $site['name'] ] ?? 50;
            $scored_result[$key] = [ 'site' => $site, 'score' => $score ];
        }

        usort( $scored_result, fn($a, $b) => $b['score'] <=> $a['score'] );
        
        // Restore Keys
        $final = [];
        foreach ( $scored_result as $item ) {
            foreach ( $sites as $k => $s ) {
                if ( $s['name'] === $item['site']['name'] ) {
                    $final[$k] = $item;
                    break;
                }
            }
        }
        return $final;
    }

    // =========================================================================
    //                         HTTP & DOWNLOAD UTILS
    // =========================================================================

    private function call_gapgpt( $sys, $user, $key, $model, $max_tokens ) {
        $body = [
            'model' => $model,
            'messages' => [
                [ 'role' => 'system', 'content' => $sys ],
                [ 'role' => 'user', 'content' => $user ]
            ],
            'temperature' => 0.5,
            'max_tokens' => $max_tokens
        ];
        
        $res = wp_remote_post( $this->gapgpt_api, [
            'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json' ],
            'body' => json_encode( $body ),
            'timeout' => 45 // Increased timeout for better AI response
        ]);

        if ( is_wp_error( $res ) ) {
            $this->log( 'GapGPT API Error: ' . $res->get_error_message(), 'error' );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $res );
        if ( $code !== 200 ) {
            $this->log( 'GapGPT HTTP Error ' . $code, 'error' );
            return null;
        }

        $json = json_decode( wp_remote_retrieve_body( $res ), true );
        return $json['choices'][0]['message']['content'] ?? null;
    }

    private function download_telegram_file( $file_id, $token ) {
        // 1. Get File Path
        $res = wp_remote_get( $this->tg_api_base . $token . '/getFile?file_id=' . $file_id );
        if ( is_wp_error( $res ) ) return $res;
        
        $json = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! isset( $json['result']['file_path'] ) ) return new WP_Error( 'no_path', 'File path missing' );

        $file_path = $json['result']['file_path'];
        $url = "https://api.telegram.org/file/bot{$token}/{$file_path}";
        
        // 2. Download and Sideload
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url( $url );
        if ( is_wp_error( $tmp ) ) return $tmp;

        $file_array = [ 'name' => basename( $file_path ), 'tmp_name' => $tmp ];
        
        $id = media_handle_sideload( $file_array, 0 );
        
        // If error, delete temp file
        if ( is_wp_error( $id ) ) {
            @unlink( $file_array['tmp_name'] );
        }

        return $id;
    }

    private function log( $msg, $type = 'info' ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( "[Telebridge {$type}] " . $msg );
        }
    }

    // =========================================================================
    //                            AI SITES MANAGER (UI & DATA)
    // =========================================================================

    public function get_categories() {
        // SVG Icons inline for portability
        return [
            'text' => [ 'label' => 'توليد متن', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>' ],
            'image' => [ 'label' => 'توليد تصوير', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>' ],
            'video' => [ 'label' => 'توليد ويديو', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>' ],
            'audio' => [ 'label' => 'صدا و موسیقی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>' ],
            'code' => [ 'label' => 'کدنویسی', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>' ],
            'tool' => [ 'label' => 'ابزارها', 'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>' ],
        ];
    }

    public function get_reference_list() {
        $defaults = [
            'chatgpt' => [ 'name' => 'ChatGPT', 'url' => 'https://chat.openai.com', 'cat' => 'text' ],
            'claude' => [ 'name' => 'Claude', 'url' => 'https://claude.ai', 'cat' => 'text' ],
            'gemini' => [ 'name' => 'Gemini', 'url' => 'https://gemini.google.com', 'cat' => 'text' ],
            'midjourney' => [ 'name' => 'Midjourney', 'url' => 'https://www.midjourney.com', 'cat' => 'image' ],
            'dalle' => [ 'name' => 'DALL-E 3', 'url' => 'https://openai.com/dall-e-3', 'cat' => 'image' ],
            'leonardo' => [ 'name' => 'Leonardo.ai', 'url' => 'https://leonardo.ai', 'cat' => 'image' ],
            'firefly' => [ 'name' => 'Firefly', 'url' => 'https://firefly.adobe.com', 'cat' => 'image' ],
            'runway' => [ 'name' => 'Runway', 'url' => 'https://runwayml.com', 'cat' => 'video' ],
            'pika' => [ 'name' => 'Pika', 'url' => 'https://pika.art', 'cat' => 'video' ],
            'suno' => [ 'name' => 'Suno', 'url' => 'https://suno.com', 'cat' => 'audio' ],
            'notion' => [ 'name' => 'Notion AI', 'url' => 'https://www.notion.so', 'cat' => 'tool' ],
            'github_copilot' => [ 'name' => 'GitHub Copilot', 'url' => 'https://github.com/features/copilot', 'cat' => 'code' ],
            'cursor' => [ 'name' => 'Cursor', 'url' => 'https://cursor.com', 'cat' => 'code' ],
        ];
        $custom = get_option( $this->sites_option_name, [] );
        return array_merge( $defaults, $custom );
    }

    public function register_meta_box() {
        add_meta_box( 'ai_sites_box', '🌐 مدیریت سرویس‌های هوش مصنوعی (AI Sites)', [ $this, 'render_meta_box' ], 'product', 'normal', 'high' );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'save_ai_sites', 'ai_sites_nonce' );
        $checked = get_post_meta( $post->ID, '_ai_sites_checked', true ) ?: [];
        $auto_display = get_post_meta( $post->ID, '_ai_sites_auto_display', true );
        $sites = $this->get_reference_list();
        $cats = $this->get_categories();

        echo '<div class="ai-wrap" style="direction:rtl;">';
        // Header Bar with Auto Display Checkbox and Search handled by JS
        echo '<div class="ai-header-bar" style="margin-bottom:15px; display:flex; align-items:center; gap:20px;">';
        echo '<label style="font-weight:600;"><input type="checkbox" name="ai_sites_auto_display" value="yes" ' . checked( $auto_display, 'yes', false ) . '> نمایش خودکار تب در صفحه محصول</label>';
        echo '</div>';

        foreach ( $cats as $slug => $info ) {
            echo '<div class="ai-group" style="margin-bottom:15px; border:1px solid #eee; padding:10px;">';
            echo '<h4 style="margin:0 0 10px 0; display:flex; align-items:center; gap:6px;">' . $info['icon'] . ' ' . esc_html( $info['label'] ) . '</h4>';
            echo '<div class="ai-grid" style="display:flex; flex-wrap:wrap; gap:10px;">';
            foreach ( $sites as $key => $site ) {
                if ( ($site['cat'] ?? 'tool') === $slug ) {
                    $is_checked = in_array( $key, $checked );
                    echo '<label style="border:1px solid #ddd; padding:5px 10px; border-radius:5px; background:' . ($is_checked ? '#e6f4ea' : '#fff') . '; cursor:pointer; user-select:none;">';
                    echo '<input type="checkbox" name="ai_sites_checks[]" value="' . esc_attr( $key ) . '" ' . checked( $is_checked, true, false ) . '> ';
                    echo esc_html( $site['name'] );
                    echo '</label>';
                }
            }
            echo '</div></div>';
        }
        echo '</div>';
    }

    public function save_meta_data( $post_id ) {
        if ( ! isset( $_POST['ai_sites_nonce'] ) || ! wp_verify_nonce( $_POST['ai_sites_nonce'], 'save_ai_sites' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

        $checked = isset( $_POST['ai_sites_checks'] ) ? array_map( 'sanitize_text_field', $_POST['ai_sites_checks'] ) : [];
        update_post_meta( $post_id, '_ai_sites_checked', $checked );
        update_post_meta( $post_id, '_ai_sites_auto_display', isset( $_POST['ai_sites_auto_display'] ) ? 'yes' : 'no' );
    }

    public function add_woocommerce_tab( $tabs ) {
        global $post;
        if ( get_post_meta( $post->ID, '_ai_sites_auto_display', true ) === 'yes' ) {
            $tabs['ai_compatibility'] = [
                'title' => 'سازگاری با هوش مصنوعی',
                'priority' => 25,
                'callback' => [ $this, 'render_frontend' ]
            ];
        }
        return $tabs;
    }

    public function render_frontend() {
        global $post;
        $checked = get_post_meta( $post->ID, '_ai_sites_checked', true ) ?: [];
        $sites = $this->get_reference_list();
        $cats = $this->get_categories();

        if ( empty( $checked ) ) return;

        echo '<div class="ai-chips-container" style="display:flex; flex-wrap:wrap; gap:10px; direction:rtl; margin:20px 0;">';
        foreach ( $checked as $key ) {
            if ( isset( $sites[ $key ] ) ) {
                $s = $sites[ $key ];
                $cat = $s['cat'] ?? 'tool';
                $icon = $cats[ $cat ]['icon'];
                echo "<a href='{$s['url']}' target='_blank' class='ai-chip' rel='nofollow noopener'>";
                echo "<span class='ai-chip-icon'>{$icon}</span>";
                echo "<span class='ai-chip-text'>{$s['name']}</span>";
                echo "</a>";
            }
        }
        echo '</div>';
        
        // Inline CSS for Frontend Chips
        echo '<style>
            .ai-chip { display:inline-flex; align-items:center; gap:8px; padding:8px 16px; border:1px solid #e5e7eb; border-radius:50px; text-decoration:none !important; color:#374151; transition:all 0.2s ease; background:#fff; font-size:14px; }
            .ai-chip:hover { background:#00b0a4; color:#fff; border-color:#00b0a4; transform:translateY(-2px); box-shadow:0 4px 6px -1px rgba(0, 176, 164, 0.1); }
            .ai-chip-icon { display:flex; color:#9ca3af; }
            .ai-chip:hover .ai-chip-icon { color:#fff; }
        </style>';
    }

    public function shortcode_handler() {
        ob_start();
        $this->render_frontend();
        return ob_get_clean();
    }

    public function ajax_set_webhook() {
        check_ajax_referer( 'telebridge_nonce', 'nonce' );
        
        $opt = get_option( self::OPTION_KEY );
        $token = $opt['bot_token'] ?? '';
        
        if ( empty( $token ) ) wp_send_json_error( 'توکن ربات تلگرام را در تنظیمات وارد کنید.' );
        
        $url = site_url( '/wp-json/telebridge/v1/webhook' );
        $res = wp_remote_get( $this->tg_api_base . $token . '/setWebhook?url=' . urlencode( $url ) );
        
        if ( is_wp_error( $res ) ) wp_send_json_error( $res->get_error_message() );
        
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        
        if ( isset($body['ok']) && $body['ok'] ) {
            wp_send_json_success( 'وب‌هوک با موفقیت متصل شد ✅' );
        } else {
            wp_send_json_error( 'خطا از تلگرام: ' . ($body['description'] ?? 'نامشخص') );
        }
    }
}

// Init Plugin
add_action( 'plugins_loaded', [ 'Telebridge_Ultimate', 'get_instance' ] );