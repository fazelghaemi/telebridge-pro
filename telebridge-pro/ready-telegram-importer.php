<?php
/*
Plugin Name: Ready Telegram Importer (Masterpiece AI)
Description: Automatically transfers Telegram posts to WooCommerce. Uses GapGPT to generate SEO-optimized Titles AND Descriptions (2-3 lines). Stores raw prompt in meta.
Version: 5.0.0
Author: Ready Studio
Text Domain: ready-importer
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Ready_Telegram_Importer {

    const OPTION_KEY = 'rti_settings_pro';
    private $tg_api_base = 'https://api.telegram.org/bot';
    private $gapgpt_api  = 'https://api.gapgpt.app/v1/chat/completions';

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'rest_api_init', array( $this, 'register_webhook_endpoint' ) );
        add_action( 'wp_ajax_rti_set_webhook', array( $this, 'ajax_set_webhook' ) );
    }

    public function add_admin_menu() {
        add_menu_page( 'ورودی تلگرام', 'ورودی تلگرام', 'manage_options', 'ready-telegram-importer', array( $this, 'render_settings_page' ), 'dashicons-nametag', 58 );
    }

    public function enqueue_assets( $hook ) {
        if ( 'toplevel_page_ready-telegram-importer' !== $hook ) return;
        wp_enqueue_style( 'rti-css', plugins_url( 'admin-style.css', __FILE__ ) );
        wp_enqueue_script( 'rti-js', plugins_url( 'admin-script.js', __FILE__ ), array( 'jquery' ), '1.0', true );
        wp_localize_script( 'rti-js', 'rti_vars', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'rti_nonce' ) ) );
    }

    public function register_settings() {
        register_setting( 'rti_group', self::OPTION_KEY );
    }

    public function render_settings_page() {
        $opt = get_option( self::OPTION_KEY );
        $webhook = site_url( '/wp-json/rti/v1/webhook' );
        ?>
        <div class="rti-wrap">
            <header class="rti-header">
                <h1>🤖 پیکربندی انتقال هوشمند (Telegram to WC)</h1>
            </header>
            
            <form method="post" action="options.php" class="rti-form">
                <?php settings_fields( 'rti_group' ); ?>
                
                <div class="rti-card">
                    <h2 class="rti-card-title">🔑 تنظیمات API (حیاتی)</h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>توکن ربات تلگرام</label>
                            <input type="password" name="<?php echo self::OPTION_KEY; ?>[bot_token]" value="<?php echo esc_attr( @$opt['bot_token'] ); ?>" placeholder="123456:ABC..." required>
                        </div>
                        <div class="rti-col">
                            <label>آیدی کانال (Channel ID)</label>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[channel_id]" value="<?php echo esc_attr( @$opt['channel_id'] ); ?>" placeholder="@MyChannel" required>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>کلید API گپ‌جی‌پی‌تی (GapGPT)</label>
                            <input type="password" name="<?php echo self::OPTION_KEY; ?>[gapgpt_key]" value="<?php echo esc_attr( @$opt['gapgpt_key'] ); ?>" placeholder="sk-..." required>
                        </div>
                        <div class="rti-col">
                            <label>مدل هوش مصنوعی</label>
                            <input type="text" name="<?php echo self::OPTION_KEY; ?>[ai_model]" value="<?php echo !empty($opt['ai_model']) ? esc_attr($opt['ai_model']) : 'gpt-3.5-turbo'; ?>" placeholder="gpt-3.5-turbo">
                            <small>مدل پیش‌فرض: gpt-3.5-turbo (سازگار با GapGPT)</small>
                        </div>
                    </div>
                </div>

                <div class="rti-card">
                    <h2 class="rti-card-title">📦 تنظیمات محصول</h2>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>قیمت محصول (تومان)</label>
                            <input type="number" name="<?php echo self::OPTION_KEY; ?>[price]" value="<?php echo esc_attr( @$opt['price'] ); ?>">
                        </div>
                        <div class="rti-col">
                            <label>دسته‌بندی پیش‌فرض</label>
                            <?php wp_dropdown_categories(['taxonomy'=>'product_cat', 'name'=>self::OPTION_KEY.'[cat]', 'selected'=>@$opt['cat'], 'show_option_none'=>'-- انتخاب --', 'class'=>'rti-full']); ?>
                        </div>
                    </div>
                    <div class="rti-row">
                        <div class="rti-col">
                            <label>وضعیت انتشار</label>
                            <select name="<?php echo self::OPTION_KEY; ?>[status]" class="rti-full">
                                <option value="publish" <?php selected(@$opt['status'], 'publish'); ?>>انتشار فوری</option>
                                <option value="draft" <?php selected(@$opt['status'], 'draft'); ?>>پیش‌نویس</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="rti-actions">
                    <?php submit_button( 'ذخیره تنظیمات', 'primary large', 'submit', false ); ?>
                </div>
            </form>

            <div class="rti-card rti-webhook-box">
                <h3>🌐 اتصال وب‌هوک</h3>
                <p>آدرس: <code><?php echo esc_url( $webhook ); ?></code></p>
                <button type="button" id="rti_webhook_btn" class="button button-secondary">🔗 اتصال خودکار به تلگرام</button>
                <div id="rti_msg"></div>
            </div>
        </div>
        <?php
    }

    public function register_webhook_endpoint() {
        register_rest_route( 'rti/v1', '/webhook', array( 'methods' => 'POST', 'callback' => array( $this, 'process_webhook' ), 'permission_callback' => '__return_true' ) );
    }

    public function process_webhook( $req ) {
        $data = $req->get_json_params();
        $opt = get_option( self::OPTION_KEY );
        
        // Validation
        if ( ! isset( $data['channel_post'] ) ) return new WP_REST_Response( ['status'=>'ignored'], 200 );
        $post = $data['channel_post'];
        
        // Security Check
        $valid_channel = isset($opt['channel_id']) ? $opt['channel_id'] : '';
        if ( $valid_channel ) {
            $incoming_id = (string)$post['chat']['id'];
            $incoming_user = '@'.($post['chat']['username'] ?? '');
            if ( $incoming_id !== $valid_channel && $incoming_user !== $valid_channel ) {
                return new WP_REST_Response( ['error'=>'Unauthorized'], 403 );
            }
        }

        if ( ! isset( $post['photo'] ) ) return new WP_REST_Response( ['status'=>'no_photo'], 200 );

        // 1. Download Image
        $token = $opt['bot_token'] ?? '';
        $photo = end( $post['photo'] );
        $img_id = $this->dl_image( $photo['file_id'], $token );
        if ( is_wp_error( $img_id ) ) return new WP_REST_Response( ['error'=>'Img fail'], 500 );

        // 2. AI Processing (GapGPT)
        $raw_prompt = isset( $post['caption'] ) ? $post['caption'] : '';
        $ai_key = $opt['gapgpt_key'] ?? '';
        $model = !empty($opt['ai_model']) ? $opt['ai_model'] : 'gpt-3.5-turbo';
        
        $ai_data = $this->generate_content_with_ai( $raw_prompt, $ai_key, $model );

        // Fallback if AI fails
        $title_fa = !empty($ai_data['title_fa']) ? $ai_data['title_fa'] : wp_trim_words($raw_prompt, 5);
        $desc_fa  = !empty($ai_data['description']) ? $ai_data['description'] : $raw_prompt; // Fallback content
        $title_en = !empty($ai_data['title_en']) ? $ai_data['title_en'] : '';

        // 3. Create Product
        $pid = wp_insert_post([
            'post_title'   => $title_fa,
            'post_content' => $desc_fa, // AI generated description here!
            'post_status'  => $opt['status'] ?? 'draft',
            'post_type'    => 'product',
            'post_author'  => 1
        ]);

        if ( ! is_wp_error( $pid ) ) {
            $price = $opt['price'] ?? '0';
            update_post_meta( $pid, '_price', $price );
            update_post_meta( $pid, '_regular_price', $price );
            update_post_meta( $pid, '_virtual', 'yes' );
            set_post_thumbnail( $pid, $img_id );
            
            if ( !empty( $opt['cat'] ) ) wp_set_object_terms( $pid, (int)$opt['cat'], 'product_cat' );

            // Custom Meta (JetEngine)
            update_post_meta( $pid, 'prompt-text', $raw_prompt ); // Save RAW prompt separately
            if(!empty($title_en)) update_post_meta( $pid, 'latin-name-product', $title_en );
            if(isset($post['chat']['title'])) update_post_meta( $pid, 'idea-owner', $post['chat']['title'] );

            return new WP_REST_Response( ['success'=>true, 'id'=>$pid], 200 );
        }
        return new WP_REST_Response( ['error'=>'Insert failed'], 500 );
    }

    private function generate_content_with_ai( $prompt, $key, $model ) {
        if( empty($key) || empty($prompt) ) return [];

        // STRICT Prompt Engineering
        $sys_msg = "You are a professional SEO copywriter. Analyze the user's prompt.
        Return a JSON object with these 3 keys:
        1. 'title_fa': A short, catchy Persian product title (max 8 words). SEO optimized.
        2. 'title_en': A short English product title.
        3. 'description': A high-quality, 2 to 3 line Persian description explaining exactly what this prompt creates. It must be accurate and persuasive for buyers.
        Do NOT output markdown. ONLY JSON.";

        $body = [
            'model' => $model,
            'messages' => [
                ['role'=>'system', 'content'=>$sys_msg],
                ['role'=>'user', 'content'=>substr($prompt, 0, 1500)]
            ],
            'temperature' => 0.7
        ];

        $res = wp_remote_post( $this->gapgpt_api, [
            'headers' => ['Authorization'=>'Bearer '.$key, 'Content-Type'=>'application/json'],
            'body' => json_encode($body),
            'timeout' => 30
        ]);

        if ( is_wp_error( $res ) ) return [];
        $data = json_decode( wp_remote_retrieve_body( $res ), true );
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        // Cleanup JSON
        $content = str_replace(['```json', '```'], '', $content);
        $json = json_decode(trim($content), true);

        return is_array($json) ? $json : [];
    }

    private function dl_image( $fid, $token ) {
        $res = wp_remote_get( $this->tg_api_base . $token . '/getFile?file_id=' . $fid );
        if ( is_wp_error( $res ) ) return $res;
        $b = json_decode( wp_remote_retrieve_body( $res ), true );
        if ( ! isset( $b['result']['file_path'] ) ) return new WP_Error( 'err', 'path' );
        
        $url = "https://api.telegram.org/file/bot$token/" . $b['result']['file_path'];
        
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        
        $tmp = download_url( $url );
        if( is_wp_error( $tmp ) ) return $tmp;
        
        $id = media_handle_sideload( ['name'=>basename($b['result']['file_path']), 'tmp_name'=>$tmp], 0 );
        if( is_wp_error( $id ) ) @unlink( $tmp );
        return $id;
    }

    public function ajax_set_webhook() {
        check_ajax_referer( 'rti_nonce', 'nonce' );
        $opt = get_option( self::OPTION_KEY );
        $t = $opt['bot_token'] ?? '';
        if(!$t) wp_send_json_error('توکن ربات را وارد کنید');
        
        $url = site_url('/wp-json/rti/v1/webhook');
        $r = wp_remote_get( $this->tg_api_base . $t . '/setWebhook?url=' . urlencode($url) );
        if(is_wp_error($r)) wp_send_json_error($r->get_error_message());
        
        $b = json_decode(wp_remote_retrieve_body($r), true);
        if($b['ok']) wp_send_json_success('متصل شد! ✅');
        else wp_send_json_error($b['description']);
    }
}
new Ready_Telegram_Importer();


