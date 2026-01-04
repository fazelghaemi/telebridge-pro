<?php
/**
 * Plugin Name: AI Sites Manager Ultimate
 * Plugin URI: https://readystudio.ir
 * Description: سیستم مدیریت کامل لینک‌های هوش مصنوعی. گروه‌بندی خودکار، آیکون‌های اختصاصی برای هر دسته (متن، تصویر، ویدیو...)، ذخیره سرویس‌های سفارشی و رابط کاربری فوق‌العاده مدرن.
 * Version: 6.0.0
 * Author: Ready Studio
 * Author URI: https://readystudio.ir
 * Text Domain: ai-sites-ultimate
 * Domain Path: /languages
 * License: GPL v2 or later
 * Requires WP: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'AI_SITES_ULTIMATE_VERSION', '6.0.0' );
define( 'AI_SITES_ULTIMATE_PATH', plugin_dir_path( __FILE__ ) );
define( 'AI_SITES_ULTIMATE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main Plugin Class
 */
class AI_Sites_Manager_Ultimate {

    private static $instance = null;
    private $option_name = 'ai_sites_pro_global_db';

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
     * Constructor - Setup hooks
     */
    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
        add_action( 'save_post', [ $this, 'save_meta_data' ] );
        add_shortcode( 'ai_sites', [ $this, 'shortcode_handler' ] );
        add_filter( 'woocommerce_product_tabs', [ $this, 'add_woocommerce_tab' ] );
    }

    /**
     * Get all site categories with icons
     * 
     * @return array - Categories with icons and labels
     */
    private function get_categories() {
        return [
            'text' => [
                'label' => 'توليد متن و چت‌بات',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>'
            ],
            'image' => [
                'label' => 'توليد و ويرايش تصوير',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>'
            ],
            'video' => [
                'label' => 'توليد و ويرايش ويديو',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>'
            ],
            'audio' => [
                'label' => 'صدا، موسیقی و دوبله',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"></path><circle cx="6" cy="18" r="3"></circle><circle cx="18" cy="16" r="3"></circle></svg>'
            ],
            'code' => [
                'label' => 'کدنویسی و توسعه',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>'
            ],
            'tool' => [
                'label' => 'ابزارها و دستیارهای آفیس',
                'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>'
            ],
        ];
    }

    /**
     * Get complete reference list of AI sites
     * 
     * @return array - All AI sites with categories
     */
    public function get_reference_list() {
        $defaults = [
            // Text Generation
            'chatgpt' => [ 'name' => 'ChatGPT | چت جی‌پی‌تی', 'url' => 'https://chat.openai.com', 'cat' => 'text' ],
            'claude' => [ 'name' => 'Claude | کلاد', 'url' => 'https://claude.ai', 'cat' => 'text' ],
            'gemini' => [ 'name' => 'Gemini | جمینی', 'url' => 'https://gemini.google.com', 'cat' => 'text' ],
            'copilot' => [ 'name' => 'Copilot | کوپایلت', 'url' => 'https://copilot.microsoft.com', 'cat' => 'text' ],
            'perplexity' => [ 'name' => 'Perplexity | پرپلکسیتی', 'url' => 'https://www.perplexity.ai', 'cat' => 'text' ],
            'mistral' => [ 'name' => 'Mistral | میسترال', 'url' => 'https://chat.mistral.ai', 'cat' => 'text' ],
            'poe' => [ 'name' => 'Poe | پو', 'url' => 'https://poe.com', 'cat' => 'text' ],

            // Image Generation
            'midjourney' => [ 'name' => 'Midjourney | میدجرنی', 'url' => 'https://www.midjourney.com', 'cat' => 'image' ],
            'dalle' => [ 'name' => 'DALL-E 3 | دال-ای', 'url' => 'https://openai.com/dall-e-3', 'cat' => 'image' ],
            'leonardo' => [ 'name' => 'Leonardo.ai | لئونارد', 'url' => 'https://leonardo.ai', 'cat' => 'image' ],
            'firefly' => [ 'name' => 'Firefly | فایرفلای', 'url' => 'https://firefly.adobe.com', 'cat' => 'image' ],
            'flux' => [ 'name' => 'FLUX.1 | فلاکس', 'url' => 'https://blackforestlabs.ai', 'cat' => 'image' ],
            'ideogram' => [ 'name' => 'Ideogram | ایدئوگرام', 'url' => 'https://ideogram.ai', 'cat' => 'image' ],

            // Video Generation
            'runway' => [ 'name' => 'Runway | رانوی', 'url' => 'https://runwayml.com', 'cat' => 'video' ],
            'pika' => [ 'name' => 'Pika | پیکا', 'url' => 'https://pika.art', 'cat' => 'video' ],
            'sora' => [ 'name' => 'Sora | سورا', 'url' => 'https://openai.com/sora', 'cat' => 'video' ],
            'kling' => [ 'name' => 'Kling | کلینگ', 'url' => 'https://klingai.com', 'cat' => 'video' ],
            'luma' => [ 'name' => 'Luma | لوما', 'url' => 'https://lumalabs.ai', 'cat' => 'video' ],
            'heygen' => [ 'name' => 'HeyGen | هی‌جن', 'url' => 'https://www.heygen.com', 'cat' => 'video' ],

            // Audio Generation
            'suno' => [ 'name' => 'Suno | سونو', 'url' => 'https://suno.com', 'cat' => 'audio' ],
            'udio' => [ 'name' => 'Udio | یودیو', 'url' => 'https://www.udio.com', 'cat' => 'audio' ],
            'elevenlabs' => [ 'name' => 'ElevenLabs | الون‌لبز', 'url' => 'https://elevenlabs.io', 'cat' => 'audio' ],

            // Code Generation
            'github_copilot' => [ 'name' => 'GitHub Copilot', 'url' => 'https://github.com/features/copilot', 'cat' => 'code' ],
            'cursor' => [ 'name' => 'Cursor | کرسر', 'url' => 'https://cursor.com', 'cat' => 'code' ],
            'replit' => [ 'name' => 'Replit', 'url' => 'https://replit.com', 'cat' => 'code' ],
            'tabnine' => [ 'name' => 'Tabnine', 'url' => 'https://www.tabnine.com', 'cat' => 'code' ],

            // Tools & Productivity
            'notion' => [ 'name' => 'Notion AI | نوشن', 'url' => 'https://www.notion.so', 'cat' => 'tool' ],
            'gamma' => [ 'name' => 'Gamma | گاما', 'url' => 'https://gamma.app', 'cat' => 'tool' ],
            'chatpdf' => [ 'name' => 'ChatPDF', 'url' => 'https://www.chatpdf.com', 'cat' => 'tool' ],
        ];

        // Merge with custom global sites
        $user_global = get_option( $this->option_name, [] );
        $full_list = array_merge( $defaults, $user_global );

        return apply_filters( 'ai_sites_ultimate_list', $full_list );
    }

    /**
     * Register meta box for product editor
     */
    public function register_meta_box() {
        add_meta_box(
            'ai_sites_ultimate_box',
            '🌐 مدیریت جامع هوش مصنوعی (AI Manager)',
            [ $this, 'render_meta_box' ],
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Render meta box in product editor
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'save_ai_sites_ultimate', 'ai_sites_nonce' );
        
        // Get saved data
        $saved_checks = get_post_meta( $post->ID, '_ai_sites_checked', true ) ?: [];
        $saved_custom = get_post_meta( $post->ID, '_ai_sites_custom', true ) ?: [];
        $auto_display = get_post_meta( $post->ID, '_ai_sites_auto_display', true );
        
        $reference_list = $this->get_reference_list();
        $categories = $this->get_categories();

        // Group items by category
        $grouped_list = [];
        foreach ( $reference_list as $key => $data ) {
            $cat = $data['cat'] ?? 'tool';
            $grouped_list[ $cat ][ $key ] = $data;
        }
        ?>
        
        <style>
            .ai-wrap { 
                --primary: #00b0a4; 
                border: 1px solid #e5e7eb; 
                background: #fff; 
                border-radius: 8px; 
                overflow: hidden; 
            }
            
            .ai-header-bar { 
                padding: 15px; 
                background: #f9fafb; 
                border-bottom: 1px solid #e5e7eb; 
                display: flex; 
                gap: 10px; 
                flex-wrap: wrap; 
                align-items: center; 
                justify-content: space-between; 
            }
            
            .ai-search { 
                padding: 8px 12px; 
                border: 1px solid #d1d5db; 
                border-radius: 6px; 
                width: 100%; 
                max-width: 350px; 
                font-size: 13px;
                transition: all 0.2s ease;
            }
            
            .ai-search:focus {
                border-color: var(--primary);
                box-shadow: 0 0 0 3px rgba(0, 176, 164, 0.1);
                outline: none;
            }
            
            .ai-group { 
                border-bottom: 1px solid #f0f0f0; 
            }
            
            .ai-group:last-child {
                border-bottom: none;
            }
            
            .ai-group-title { 
                background: #f9fafb; 
                padding: 12px 15px; 
                font-weight: 600; 
                font-size: 13px; 
                color: #374151; 
                display: flex; 
                align-items: center; 
                gap: 8px; 
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .ai-group-title:hover {
                background: #f3f4f6;
            }
            
            .ai-group-content { 
                padding: 12px 15px; 
                display: grid; 
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); 
                gap: 10px; 
            }
            
            .ai-item { 
                display: flex; 
                align-items: center; 
                padding: 8px 10px; 
                border: 1px solid #e5e7eb; 
                border-radius: 6px; 
                transition: all 0.2s ease; 
                background: #fff;
                cursor: pointer;
            }
            
            .ai-item:hover { 
                border-color: var(--primary); 
                background: #f0fbfb;
                box-shadow: 0 2px 4px rgba(0, 176, 164, 0.1);
            }
            
            .ai-item input { 
                margin-left: 8px; 
                cursor: pointer;
            }
            
            .ai-item label { 
                font-size: 12px; 
                cursor: pointer; 
                width: 100%; 
                white-space: nowrap; 
                overflow: hidden; 
                text-overflow: ellipsis;
                margin: 0;
            }
            
            /* Add custom section */
            .ai-add-box { 
                background: #f0fbfb; 
                padding: 20px; 
                border-top: 2px solid #e5e7eb; 
            }
            
            .ai-row { 
                display: flex; 
                gap: 8px; 
                margin-bottom: 12px; 
                align-items: center; 
                flex-wrap: wrap;
            }
            
            .ai-row:last-child {
                margin-bottom: 0;
            }
            
            .ai-input { 
                padding: 8px 10px; 
                border: 1px solid #d1d5db; 
                border-radius: 6px; 
                font-size: 12px;
                transition: all 0.2s ease;
            }
            
            .ai-input:focus {
                border-color: var(--primary);
                outline: none;
            }
            
            .ai-select { 
                padding: 8px 10px; 
                border: 1px solid #d1d5db; 
                border-radius: 6px; 
                font-size: 12px; 
                min-width: 120px;
                transition: all 0.2s ease;
            }
            
            .ai-select:focus {
                border-color: var(--primary);
                outline: none;
            }
            
            .ai-global-toggle { 
                display: flex; 
                align-items: center; 
                gap: 5px; 
                background: #fff; 
                padding: 6px 10px; 
                border: 1px solid #d1d5db; 
                border-radius: 6px; 
                font-size: 11px; 
                color: #555;
                cursor: pointer;
            }
            
            .ai-global-toggle:hover {
                background: #f3f4f6;
            }
            
            .ai-btn-add { 
                background: var(--primary) !important; 
                color: #fff !important; 
                border: none !important; 
                padding: 8px 16px !important; 
                border-radius: 6px !important;
                cursor: pointer;
                font-size: 12px !important;
                font-weight: 500 !important;
                transition: all 0.2s ease !important;
            }
            
            .ai-btn-add:hover {
                background: #008b7a !important;
                box-shadow: 0 2px 6px rgba(0, 176, 164, 0.3) !important;
            }
            
            .ai-trash { 
                color: #ef4444; 
                cursor: pointer; 
                font-size: 16px;
                transition: all 0.2s ease;
            }
            
            .ai-trash:hover {
                color: #dc2626;
                transform: scale(1.2);
            }

            .ai-switch-label { 
                display: flex; 
                align-items: center; 
                gap: 8px; 
                font-size: 13px; 
                font-weight: 500;
                cursor: pointer;
            }
            
            .ai-add-box h4 {
                margin: 0 0 15px 0;
            }
        </style>

        <div class="ai-wrap">
            <!-- Header & Search -->
            <div class="ai-header-bar">
                <input type="text" id="ai_search" class="ai-search" placeholder="🔍 جستجو (مثلاً: Midjourney)...">
                <label class="ai-switch-label">
                    <input type="checkbox" name="ai_sites_auto_display" value="yes" <?php checked( $auto_display, 'yes' ); ?>>
                    نمایش خودکار در صفحه محصول
                </label>
            </div>

            <!-- Grouped AI Sites -->
            <div id="ai_container">
                <?php foreach ( $categories as $cat_slug => $cat_info ) : 
                    if ( ! isset( $grouped_list[ $cat_slug ] ) ) continue;
                ?>
                    <div class="ai-group">
                        <div class="ai-group-title">
                            <span class="ai-icon"><?php echo $cat_info['icon']; ?></span>
                            <?php echo esc_html( $cat_info['label'] ); ?>
                        </div>
                        <div class="ai-group-content">
                            <?php foreach ( $grouped_list[ $cat_slug ] as $key => $data ) : ?>
                                <div class="ai-item" data-name="<?php echo esc_attr( strtolower( $data['name'] ) ); ?>">
                                    <input type="checkbox" 
                                           id="ai_<?php echo esc_attr( $key ); ?>" 
                                           name="ai_sites_checks[]" 
                                           value="<?php echo esc_attr( $key ); ?>" 
                                           <?php checked( in_array( $key, $saved_checks ) ); ?>>
                                    <label for="ai_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $data['name'] ); ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Add Custom Sites -->
            <div class="ai-add-box">
                <h4>➕ افزودن سرویس سفارشی</h4>
                <div id="ai_repeater">
                    <?php foreach ( $saved_custom as $item ) : 
                        $c_cat = $item['cat'] ?? 'tool';
                    ?>
                        <div class="ai-row">
                            <input type="text" class="ai-input" name="ai_custom_names[]" value="<?php echo esc_attr( $item['name'] ); ?>" style="flex:1;" placeholder="نام سرویس">
                            <input type="url" class="ai-input" name="ai_custom_urls[]" value="<?php echo esc_url( $item['url'] ); ?>" style="flex:1;" placeholder="لینک">
                            <select class="ai-select" name="ai_custom_cats[]">
                                <?php foreach ( $categories as $k => $v ) {
                                    echo '<option value="' . esc_attr( $k ) . '" ' . selected( $c_cat, $k, false ) . '>' . esc_html( $v['label'] ) . '</option>';
                                } ?>
                            </select>
                            <span class="dashicons dashicons-trash ai-trash" title="حذف"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button ai-btn-add" id="ai_add_btn">افزودن سرویس جدید</button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Search functionality
            $('#ai_search').on('keyup', function() {
                var val = $(this).val().toLowerCase();
                $('.ai-item').each(function() {
                    $(this).toggle($(this).data('name').indexOf(val) > -1);
                });
                // Hide empty groups
                $('.ai-group').each(function() {
                    var visible = $(this).find('.ai-item:visible').length > 0;
                    $(this).toggle(visible);
                });
            });

            // Add custom site
            var idx = 0;
            $('#ai_add_btn').click(function() {
                var options = '';
                <?php foreach ( $categories as $k => $v ) : ?>
                    options += '<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v['label'] ); ?></option>';
                <?php endforeach; ?>

                var row = `
                <div class="ai-row">
                    <input type="text" class="ai-input" name="ai_new_names[${idx}]" style="flex:1;" placeholder="نام سرویس" required>
                    <input type="url" class="ai-input" name="ai_new_urls[${idx}]" style="flex:1;" placeholder="لینک (https://...)" required>
                    <select class="ai-select" name="ai_new_cats[${idx}]">${options}</select>
                    <label class="ai-global-toggle" title="ذخیره برای تمام محصولات">
                        <input type="checkbox" name="ai_make_global[${idx}]" value="yes"> سراسری
                    </label>
                    <span class="dashicons dashicons-trash ai-trash" title="حذف"></span>
                </div>`;
                $('#ai_repeater').append(row);
                idx++;
            });

            // Delete row
            $(document).on('click', '.ai-trash', function() {
                $(this).closest('.ai-row').fadeOut(200, function() {
                    $(this).remove();
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Save meta data
     */
    public function save_meta_data( $post_id ) {
        if ( ! isset( $_POST['ai_sites_nonce'] ) || ! wp_verify_nonce( $_POST['ai_sites_nonce'], 'save_ai_sites_ultimate' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save checked sites
        $checked = isset( $_POST['ai_sites_checks'] ) ? array_map( 'sanitize_text_field', $_POST['ai_sites_checks'] ) : [];

        // Save custom sites (existing)
        $custom_data = [];
        if ( isset( $_POST['ai_custom_names'] ) ) {
            $c_names = array_map( 'sanitize_text_field', $_POST['ai_custom_names'] );
            $c_urls = array_map( 'esc_url_raw', $_POST['ai_custom_urls'] );
            $c_cats = array_map( 'sanitize_text_field', $_POST['ai_custom_cats'] );
            
            for ( $i = 0; $i < count( $c_names ); $i++ ) {
                if ( ! empty( $c_names[ $i ] ) ) {
                    $custom_data[] = [
                        'name' => $c_names[ $i ],
                        'url' => $c_urls[ $i ],
                        'cat' => $c_cats[ $i ]
                    ];
                }
            }
        }

        // Save new custom sites
        if ( isset( $_POST['ai_new_names'] ) ) {
            $global_db = get_option( $this->option_name, [] );
            $is_global_updated = false;

            $new_names = array_map( 'sanitize_text_field', $_POST['ai_new_names'] );
            $new_urls = array_map( 'esc_url_raw', $_POST['ai_new_urls'] );
            $new_cats = array_map( 'sanitize_text_field', $_POST['ai_new_cats'] );

            foreach ( $new_names as $i => $name ) {
                if ( empty( $name ) ) {
                    continue;
                }

                $url = $new_urls[ $i ] ?? '';
                $cat = $new_cats[ $i ] ?? 'tool';
                $make_global = isset( $_POST['ai_make_global'][ $i ] ) && $_POST['ai_make_global'][ $i ] === 'yes';

                if ( $make_global ) {
                    $slug = 'custom_' . md5( $name . time() );
                    $global_db[ $slug ] = [
                        'name' => $name,
                        'url' => $url,
                        'cat' => $cat
                    ];
                    $is_global_updated = true;
                    $checked[] = $slug;
                } else {
                    $custom_data[] = [
                        'name' => $name,
                        'url' => $url,
                        'cat' => $cat
                    ];
                }
            }

            if ( $is_global_updated ) {
                update_option( $this->option_name, $global_db );
            }
        }

        // Update post meta
        update_post_meta( $post_id, '_ai_sites_checked', $checked );
        update_post_meta( $post_id, '_ai_sites_custom', $custom_data );
        update_post_meta( $post_id, '_ai_sites_auto_display', isset( $_POST['ai_sites_auto_display'] ) ? 'yes' : 'no' );
    }

    /**
     * Get product's selected AI sites
     */
    public function get_product_data( $post_id ) {
        $checked = get_post_meta( $post_id, '_ai_sites_checked', true ) ?: [];
        $custom = get_post_meta( $post_id, '_ai_sites_custom', true ) ?: [];
        $all_refs = $this->get_reference_list();
        $output = [];

        foreach ( $checked as $key ) {
            if ( isset( $all_refs[ $key ] ) ) {
                $output[] = $all_refs[ $key ];
            }
        }

        return array_merge( $output, $custom );
    }
    
    /**
     * Shortcode handler
     */
    public function shortcode_handler( $atts ) {
        global $post;
        
        if ( ! $post ) {
            return '';
        }
        
        $data = $this->get_product_data( $post->ID );
        
        if ( empty( $data ) ) {
            return '';
        }
        
        $atts = shortcode_atts( [ 'title' => 'پلتفرم‌های سازگار:' ], $atts );
        
        return $this->render_frontend_list( $data, $atts['title'] );
    }

    /**
     * Add WooCommerce product tab
     */
    public function add_woocommerce_tab( $tabs ) {
        global $post;
        
        if ( get_post_meta( $post->ID, '_ai_sites_auto_display', true ) === 'yes' ) {
            $data = $this->get_product_data( $post->ID );
            
            if ( ! empty( $data ) ) {
                $tabs['ai_compatibility'] = [
                    'title' => 'سازگاری با هوش مصنوعی',
                    'priority' => 25,
                    'callback' => [ $this, 'render_tab_content' ]
                ];
            }
        }
        
        return $tabs;
    }

    /**
     * Render WooCommerce tab content
     */
    public function render_tab_content() {
        global $post;
        echo $this->render_frontend_list( $this->get_product_data( $post->ID ) );
    }

    /**
     * Render frontend list with styles
     */
    private function render_frontend_list( $data, $title = '' ) {
        $categories = $this->get_categories();
        
        $link_icon = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="ai-link-arrow"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>';

        ob_start();
        ?>
        <div class="ai-wrapper">
            <?php if ( ! empty( $title ) ) : ?>
                <h4 class="ai-main-title"><?php echo esc_html( $title ); ?></h4>
            <?php endif; ?>
            
            <div class="ai-chips">
                <?php foreach ( $data as $site ) : 
                    $cat_slug = $site['cat'] ?? 'tool';
                    $cat_icon = $categories[ $cat_slug ]['icon'] ?? $categories['tool']['icon'];
                ?>
                    <a href="<?php echo esc_url( $site['url'] ); ?>" 
                       target="_blank" 
                       rel="nofollow noopener" 
                       class="ai-chip" 
                       title="<?php echo esc_attr( $categories[ $cat_slug ]['label'] ); ?>">
                        <span class="ai-chip-icon"><?php echo $cat_icon; ?></span>
                        <span class="ai-chip-name"><?php echo esc_html( $site['name'] ); ?></span>
                        <span class="ai-chip-link"><?php echo $link_icon; ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <style>
            .ai-wrapper { 
                margin: 25px 0; 
                direction: rtl; 
                font-family: inherit; 
            }
            
            .ai-main-title { 
                font-size: 16px; 
                margin-bottom: 15px; 
                font-weight: 700; 
                color: #1f2937; 
                position: relative; 
                display: inline-block; 
            }
            
            .ai-main-title:after { 
                content: ''; 
                display: block; 
                width: 40%; 
                height: 3px; 
                background: #00b0a4; 
                margin-top: 5px; 
                border-radius: 2px; 
            }
            
            .ai-chips { 
                display: flex; 
                flex-wrap: wrap; 
                gap: 10px; 
            }
            
            .ai-chip {
                display: inline-flex;
                align-items: center;
                background: #fff;
                border: 1px solid #e5e7eb;
                border-radius: 50px;
                padding: 6px 6px 6px 14px;
                text-decoration: none !important;
                transition: all 0.25s ease;
                box-shadow: 0 1px 2px rgba(0,0,0,0.05);
                color: #4b5563;
                font-size: 13px;
                line-height: 1;
            }
            
            .ai-chip-icon {
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f3f4f6;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                color: #6b7280;
                margin-left: 10px;
                transition: all 0.25s ease;
            }
            
            .ai-chip-name { 
                font-weight: 500; 
            }
            
            .ai-chip-link { 
                margin-right: 8px; 
                color: #9ca3af; 
                display: flex; 
                transform: scaleX(-1); 
                transition: 0.2s; 
                opacity: 0; 
                transform: translateX(5px) scaleX(-1); 
                width: 0; 
                overflow: hidden; 
            }
            
            .ai-chip:hover {
                border-color: #00b0a4;
                box-shadow: 0 4px 12px rgba(0, 176, 164, 0.15);
                transform: translateY(-2px);
                color: #00b0a4;
                padding-right: 14px;
            }
            
            .ai-chip:hover .ai-chip-icon {
                background: #00b0a4;
                color: #fff;
            }
            
            .ai-chip:hover .ai-chip-link {
                opacity: 1;
                width: auto;
                transform: translateX(0) scaleX(-1);
            }

            @media (max-width: 600px) {
                .ai-chips { 
                    gap: 8px; 
                }
                .ai-chip { 
                    font-size: 12px; 
                    padding: 5px 5px 5px 12px; 
                }
                .ai-chip-icon { 
                    width: 24px; 
                    height: 24px; 
                    margin-left: 8px; 
                }
            }
        </style>
        <?php
        return ob_get_clean();
    }
}

/**
 * Initialize plugin
 */
function ai_sites_manager_ultimate_init() {
    AI_Sites_Manager_Ultimate::get_instance();
}
add_action( 'plugins_loaded', 'ai_sites_manager_ultimate_init' );